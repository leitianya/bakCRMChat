<?php
// +----------------------------------------------------------------------
// | CRMChat [ CRMEB 出品客服系统 ]
// +----------------------------------------------------------------------
// | AI 自动化任务无头执行器
// +----------------------------------------------------------------------

namespace app\services\aiagent;

use app\dao\aiagent\AiAgentTaskRunDao;
use crmeb\exceptions\AdminException;
use crmeb\services\HttpService;
use think\facade\Log;

/**
 * AI 自动化任务无头执行器
 *
 * 编排单次任务运行：领取运行记录（数据库 CAS 防重复投递）→ 创建审计会话 →
 * 以无人值守模式驱动 AgentChatService 工具调用循环 → 交付校验与兜底投递 →
 * 运行终态落库。执行引擎/工具白名单/Skill 注入均复用对话主流程。
 *
 * 交付策略（双保险）：
 * - 主路径：模型在工具循环内自主调用交付类工具（如远端 MCP 提供的发送类工具），
 *   收件目标受工具端许可白名单硬约束；
 * - 兜底路径：运行结束时校验工具轨迹中无成功交付，则由本服务按任务通知配置直接投递最终答复
 *   （企业微信群机器人；CRMChat 暂无内置邮件服务，邮件渠道仅落日志提示）。
 * Class AgentTaskRunnerService
 * @package app\services\aiagent
 */
class AgentTaskRunnerService
{
    /**
     * 运行状态：执行中
     */
    const STATUS_RUNNING = 'running';

    /**
     * 运行状态：成功
     */
    const STATUS_SUCCESS = 'success';

    /**
     * 运行状态：失败
     */
    const STATUS_FAILED = 'failed';

    /**
     * 运行状态：软超时（已产出部分结论后强制收尾）
     */
    const STATUS_TIMEOUT = 'timeout';

    /**
     * AgentChatService 单次对话默认工具轮数（任务未指定时的兜底值）
     */
    const DEFAULT_MAX_STEPS = 10;

    /**
     * @var AgentTaskServices 任务与运行数据服务
     */
    private $tasks;

    /**
     * @var AgentChatService 对话执行引擎（复用工具调用循环）
     */
    private $chat;

    /**
     * @var AiAgentTaskRunDao 任务运行记录 Dao
     */
    private $runDao;

    /**
     * AgentTaskRunnerService constructor.
     * @param AgentTaskServices $tasks
     * @param AgentChatService $chat
     * @param AiAgentTaskRunDao $runDao
     */
    public function __construct(AgentTaskServices $tasks, AgentChatService $chat, AiAgentTaskRunDao $runDao)
    {
        $this->tasks = $tasks;
        $this->chat = $chat;
        $this->runDao = $runDao;
    }

    /**
     * 执行单次任务运行（队列 Job 唯一入口）
     *
     * @param int $runId 运行记录 ID（必须处于 running 态，否则直接忽略——状态即锁，天然防重复投递）
     * @throws AdminException 运行记录或任务不存在
     */
    public function run(int $runId): void
    {
        $run = $this->tasks->getRun($runId);
        if ((string)$run['status'] !== self::STATUS_RUNNING) {
            return;
        }
        $task = $this->tasks->getTask((int)$run['task_id'], 0, false);
        $startedAt = time();

        // 数据库 CAS 领取：仅 running 态可置 started_at，并发重复投递只有一个赢家
        $claimed = $this->runDao->claimRunning($runId, $startedAt);
        if (!$claimed) {
            return;
        }

        $adminId = (int)$task['admin_id'];
        $instruction = (string)$task['instruction'];
        // 独立审计会话：每次运行新建，标题带任务名与日期，工具调用全过程落会话消息可回放
        $conversationId = app()->make(AiAgentServices::class)->createConversation(
            $adminId,
            '【自动任务】' . (string)$task['name'] . ' ' . date('m-d H:i', $startedAt)
        );
        $this->tasks->updateRun($runId, ['conversation_id' => $conversationId]);

        $timeout = max(60, (int)$task['timeout']);
        $autonomy = (int)$task['autonomy'] === AgentTaskServices::AUTONOMY_AUTO
            ? AgentTaskServices::AUTONOMY_AUTO
            : AgentTaskServices::AUTONOMY_READONLY;
        // getTask() 返回原始库记录，JSON 字段是字符串，必须先解码（强转 (array) 会得到单元素字符串数组）
        $serverIds = $this->decodeArray($task['server_ids'] ?? '');
        $skillKeys = $this->decodeArray($task['skill_keys'] ?? '');
        $notifyChannels = $this->decodeArray($task['notify_channels'] ?? '');
        $runtimeOptions = [
            'unattended' => true,
            'autonomy' => $autonomy,
            'max_steps' => (int)min(30, max(3, (int)$task['max_steps'] ?: self::DEFAULT_MAX_STEPS)),
            'deadline' => $startedAt + $timeout,
        ];

        $summary = '';
        $error = '';
        $usage = [];
        $toolCallCount = 0;
        $status = self::STATUS_SUCCESS;
        // 交付目标显式注入：任务指令可能只写「发送到指定邮箱」而未带具体地址，
        // 无人值守下模型无法追问，必须把任务配置的收件目标透传给模型，防止编造收件人
        $instruction .= "\n\n[运行时配置] 收件邮箱（交付结果必须发送到此地址）：" . ((string)$task['notify_email'] !== '' ? $task['notify_email'] : '未配置');
        try {
            $result = $this->chat->chat(
                $adminId,
                $conversationId,
                $instruction,
                (int)$task['model_id'],
                $serverIds,
                $skillKeys,
                $runtimeOptions
            );
            $summary = trim((string)($result['content'] ?? ''));
            $usage = (array)($result['usage'] ?? []);
            $toolCalls = (array)($result['tool_calls'] ?? []);
            $toolCallCount = count($toolCalls);
            // 软超时判定：循环因 deadline 提前收尾时，产出标注 timeout 而非 failed（结论仍有效）
            if (time() >= $startedAt + $timeout) {
                $status = self::STATUS_TIMEOUT;
            }
            // 交付校验：模型未在循环内成功调用交付工具时，按任务通知配置兜底投递
            if (!$this->hasDelivered($toolCalls)) {
                $delivered = $this->deliverFallback(
                    (string)$task['name'],
                    $summary !== '' ? $summary : '（本次运行未产出正文）',
                    $status,
                    $notifyChannels,
                    (string)$task['notify_email']
                );
                $error = $delivered ? '' : '模型未调用交付工具且兜底投递未完成（请检查通知渠道配置）';
            }
        } catch (\Throwable $e) {
            $status = self::STATUS_FAILED;
            $error = mb_substr($e->getMessage(), 0, 900);
            Log::error('[AgentTask] 任务运行失败: ' . $e->getMessage(), [
                'run_id' => $runId,
                'task_id' => (int)$task['id'],
                'conversation_id' => $conversationId,
            ]);
        }

        // 失败/超时时按通知配置发送失败提醒（与结果交付共用通道，独立 try-catch 不影响终态落库）
        if (in_array($status, [self::STATUS_FAILED, self::STATUS_TIMEOUT], true)) {
            try {
                $this->deliverFallback(
                    (string)$task['name'],
                    $summary !== '' ? $summary : ('运行' . ($status === self::STATUS_FAILED ? '失败' : '超时') . '：' . $error),
                    $status,
                    $notifyChannels,
                    (string)$task['notify_email']
                );
            } catch (\Throwable $e) {
                Log::error('[AgentTask] 失败提醒发送异常: ' . $e->getMessage(), ['run_id' => $runId]);
            }
        }

        $this->tasks->updateRun($runId, [
            'status' => $status,
            'summary' => mb_substr($summary, 0, 60000),
            'error' => mb_substr($error, 0, 1000),
            'tool_calls' => $toolCallCount,
            'usage' => json_encode($usage, JSON_UNESCAPED_UNICODE),
            'finished_at' => time(),
        ]);
    }

    /**
     * JSON 字符串/数组安全解码为数组（任务原始记录中的 JSON 字段专用）
     * @param string|array|null $value JSON 字符串或已是数组
     * @return array
     */
    private function decodeArray($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode((string)$value, true);
        return is_array($decoded) ? array_values($decoded) : [];
    }

    /**
     * 校验工具轨迹中是否存在成功交付（交付类工具无错误结果即视为已交付）
     * @param array $toolCalls 工具调用轨迹（chat() 返回的 tool_calls：name/arguments/result）
     * @return bool
     */
    private function hasDelivered(array $toolCalls): bool
    {
        foreach ($toolCalls as $call) {
            $name = (string)($call['name'] ?? '');
            if (in_array($name, McpToolService::DELIVERY_CONFIRM_TOOLS, true)
                && is_array($call['result'] ?? null)
                && empty($call['result']['error'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * 兜底投递：按任务通知配置将文本直接投递到企业微信群机器人（邮件渠道 CRMChat 暂无底层实现，仅记录日志）
     *
     * 不经消息通知模板管道（AI 产出即成品正文，模板渲染无意义且依赖后台预配模板），
     * 直连渠道发送能力；单渠道失败互不影响。
     *
     * @param string $taskName 任务名（用于标题）
     * @param string $content 正文
     * @param string $status 运行状态（用于标题后缀）
     * @param array $channels 通知渠道列表（is_email/is_ent_wechat）
     * @param string $email 收件邮箱
     * @return bool 任一渠道投递成功即 true
     */
    private function deliverFallback(string $taskName, string $content, string $status, array $channels, string $email): bool
    {
        switch ($status) {
            case self::STATUS_FAILED:
                $statusText = '失败';
                break;
            case self::STATUS_TIMEOUT:
                $statusText = '超时（部分结论）';
                break;
            default:
                $statusText = '完成';
        }
        $subject = "【AI 任务】{$taskName} 运行{$statusText}";
        $delivered = false;
        if (in_array('is_email', $channels, true) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // CRMChat 暂无内置邮件服务（未引入 SMTP/邮件驱动），邮件渠道兜底投递不可用，仅记录日志提示
            Log::error('[AgentTask] 邮件兜底投递不可用（系统未接入邮件服务），任务结果请到 AI Agent 会话中查看', [
                'task' => $taskName,
                'to' => $email,
            ]);
        }
        if (in_array('is_ent_wechat', $channels, true)) {
            try {
                $delivered = $this->sendEntWechat($subject, $content) || $delivered;
            } catch (\Throwable $e) {
                Log::error('[AgentTask] 兜底企业微信投递失败: ' . $e->getMessage(), ['task' => $taskName]);
            }
        }
        return $delivered;
    }

    /**
     * 企业微信群机器人推送（Webhook 取系统配置 aiagent_qywechat_webhook）
     * @param string $title 消息标题
     * @param string $content 消息正文
     * @return bool
     */
    private function sendEntWechat(string $title, string $content): bool
    {
        $webhook = trim((string)sys_config('aiagent_qywechat_webhook', ''));
        if ($webhook === '') {
            Log::error('[AgentTask] 企业微信机器人 Webhook 未配置，跳过兜底投递');
            return false;
        }
        $markdown = "## {$title}\n" . $content;
        HttpService::postRequest($webhook, json_encode([
            'msgtype' => 'markdown',
            'markdown' => ['content' => $markdown],
        ], JSON_UNESCAPED_UNICODE));
        return true;
    }
}
