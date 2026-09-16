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
use crmeb\exceptions\AdminException;
use think\facade\Config;
use think\facade\Log;

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
     * 输出 SSE 响应头：手动补跨域头（流式 echo+exit 不走框架响应发送阶段），
     * 关闭输出缓冲与脚本超时，保证流式持续输出
     */
    protected function sendSseHeaders(): void
    {
        // SSE 的 echo+exit 流式输出仅支持 php-fpm 承载；若请求被 nginx 反代到 swoole HTTP 服务，
        // echo 只会写进 worker 进程 stdout、exit 会触发 "swoole exit" 异常，均无法完成流式推送
        if (preg_match('/cli/i', php_sapi_name())) {
            throw new AdminException('SSE 流式接口仅支持 php-fpm 承载，请检查 nginx 配置，勿将该接口反代到 swoole HTTP 服务');
        }

        // 先清空全部输出缓冲并丢弃其中的脏输出（clean 而非 flush，避免污染 SSE 流），
        // 确保 header 调用之前没有任何字节发送到客户端
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // 若缓冲清空后仍提示头已发送，说明更早阶段有裸输出（如 BOM、调试输出等），
        // 记录输出来源便于定位根因，并以明确的业务异常替代晦涩的 headers already sent
        if (headers_sent($sentFile, $sentLine)) {
            Log::error(sprintf('SSE 头发送失败：响应体已于 %s:%d 开始输出', $sentFile, $sentLine));
            throw new AdminException('流式响应建立失败：响应头已发送，请查看日志定位提前输出');
        }

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

        @ini_set('zlib.output_compression', '0');
        @set_time_limit(0);
    }
}
