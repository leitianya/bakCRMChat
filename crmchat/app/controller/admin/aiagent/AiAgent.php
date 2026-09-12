<?php
// +----------------------------------------------------------------------
// | CRMChat [ CRMEB 出品客服系统 ]
// +----------------------------------------------------------------------
// | AI Agent 后台接口控制器
// +----------------------------------------------------------------------

namespace app\controller\admin\aiagent;

use app\controller\admin\AuthController;
use app\services\aiagent\AgentChatService;
use app\services\aiagent\AiAgentServices;
use think\facade\Config;

/**
 * AI Agent 后台接口（对话/会话/消息/配置选项）
 * Class AiAgent
 * @package app\controller\admin\aiagent
 */
class AiAgent extends AuthController
{
    /**
     * @var AiAgentServices Agent 会话服务
     */
    protected $agents;

    /**
     * @var AgentChatService Agent 对话服务
     */
    protected $chatService;

    /**
     * AiAgent constructor.
     * @param AiAgentServices $agents
     * @param AgentChatService $chatService
     */
    public function __construct(AiAgentServices $agents, AgentChatService $chatService)
    {
        parent::__construct();
        $this->agents = $agents;
        $this->chatService = $chatService;
    }

    /**
     * 对话基础选项（可用模型、连接器与 Skill 清单）
     * @return mixed
     */
    public function options()
    {
        return $this->success('ok', $this->chatService->options());
    }

    /**
     * 会话列表（当前管理员的全部会话）
     * @return mixed
     */
    public function conversations()
    {
        return $this->success('ok', $this->agents->conversations((int)$this->adminId));
    }

    /**
     * 会话消息明细
     * @param int $conversationId 会话 ID
     * @return mixed
     */
    public function messages(int $conversationId)
    {
        return $this->success('ok', $this->agents->messages($conversationId, (int)$this->adminId));
    }

    /**
     * 删除会话（仅限本人会话）
     * @param int $id 会话 ID
     * @return mixed
     */
    public function deleteConversation(int $id)
    {
        $this->agents->deleteConversation($id, (int)$this->adminId);
        return $this->success('删除成功');
    }

    /**
     * 修改会话（标题/置顶，仅限本人会话，title 与 is_pin 至少传一项）
     * @param int $id 会话 ID
     * @return mixed
     */
    public function updateConversation(int $id)
    {
        $title = trim((string)$this->request->param('title', ''));
        // is_pin 未传时置为 -1（不修改），避免与「取消置顶 0」混淆
        $isPin = $this->request->param('is_pin');
        $this->agents->updateConversation($id, (int)$this->adminId, $title, $isPin === null ? -1 : (int)$isPin);
        return $this->success('修改成功');
    }

    /**
     * 发送对话消息（会话直接挂载，支持选择模型/连接器/Skill）
     * @return mixed
     */
    public function chat()
    {
        $data = $this->request->postMore([
            ['conversation_id', 0],
            ['message', ''],
            ['model_id', 0],
            ['mcp_server_ids', []],
            ['skill_keys', []],
        ]);
        $result = $this->chatService->chat(
            (int)$this->adminId,
            (int)$data['conversation_id'],
            (string)$data['message'],
            (int)$data['model_id'],
            (array)$data['mcp_server_ids'],
            (array)$data['skill_keys']
        );
        return $this->success('ok', $result);
    }

    /**
     * 流式对话（SSE）：思维链与正文逐块推送
     *
     * 事件载荷为 JSON：start / skills / reasoning / delta / tool / confirm / replace / followups / done / error
     * @return void 直接输出 SSE 响应流
     */
    public function chatStream()
    {
        $data = $this->request->postMore([
            ['conversation_id', 0],
            ['message', ''],
            ['model_id', 0],
            ['mcp_server_ids', []],
            ['skill_keys', []],
        ]);

        // SSE 响应头（X-Accel-Buffering: no 关闭 nginx 代理缓冲，保证逐块推送）。
        // 流式 echo+exit 不经过框架响应发送阶段，须手动补上 AllowOriginMiddleware 的跨域头
        $this->sendSseHeaders();

        $send = function (array $payload): void {
            echo 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n\n";
            if (ob_get_level() > 0) {
                @ob_flush();
            }
            flush();
        };

        try {
            $this->chatService->chatStream(
                (int)$this->adminId,
                (int)$data['conversation_id'],
                (string)$data['message'],
                (int)$data['model_id'],
                (array)$data['mcp_server_ids'],
                (array)$data['skill_keys'],
                $send
            );
        } catch (\Throwable $e) {
            $send(['event' => 'error', 'msg' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * 流式处理交互卡片（SSE）：execute 类 approve=按快照执行 / reject=取消固定话术；
     * ask_user 类 approve=用户答案回传模型继续 / reject=以未回答结果回传
     *
     * 事件载荷与 chatStream 一致
     * @return void 直接输出 SSE 响应流
     */
    public function confirmStream()
    {
        $data = $this->request->postMore([
            ['conversation_id', 0],
            ['message_id', 0],
            ['action', 'approve'],
            ['answers', []],
            ['custom', ''],
        ]);

        // SSE 响应头（与 chatStream 一致）
        $this->sendSseHeaders();

        $send = function (array $payload): void {
            echo 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n\n";
            if (ob_get_level() > 0) {
                @ob_flush();
            }
            flush();
        };

        try {
            $this->chatService->confirmStream(
                (int)$this->adminId,
                (int)$data['conversation_id'],
                (int)$data['message_id'],
                (string)$data['action'] === 'reject' ? 'reject' : 'approve',
                is_array($data['answers']) ? array_values(array_map('strval', $data['answers'])) : [],
                (string)$data['custom'],
                $send
            );
        } catch (\Throwable $e) {
            $send(['event' => 'error', 'msg' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * 输出 SSE 响应头：手动补跨域头（流式 echo+exit 不走框架响应阶段），
     * 关闭输出缓冲与脚本超时，保证流式持续输出
     */
    protected function sendSseHeaders(): void
    {
        $origin = $this->request->header('origin');
        $cookieDomain = Config::get('cookie.domain', '');
        $corsHeader = Config::get('cookie.header');
        if ($origin && ('' == $cookieDomain || strpos($origin, $cookieDomain))) {
            $corsHeader['Access-Control-Allow-Origin'] = $origin;
        }
        foreach ($corsHeader as $name => $value) {
            header($name . ': ' . $value);
        }
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, private');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        // 清空全部输出缓冲并取消脚本超时限制
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        @ini_set('zlib.output_compression', '0');
        @set_time_limit(0);
    }
}
