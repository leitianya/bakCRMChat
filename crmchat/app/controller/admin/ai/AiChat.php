<?php
// +----------------------------------------------------------------------
// | CRMChat [ CRMEB 出品客服系统 ]
// +----------------------------------------------------------------------
// | AI 对话控制器
// +----------------------------------------------------------------------
namespace app\controller\admin\ai;

use app\controller\admin\AuthController;
use app\services\ai\AiChatServices;
use crmeb\exceptions\AdminException;
use think\facade\Config;
use think\facade\Log;

/**
 * AI 对话
 *
 * 提供后台管理端统一 AI 对话接口（当前供富文本编辑器 AI 写作使用），
 * 以 SSE（text/event-stream）逐块推送，事件载荷为 JSON：
 * - {event: start}             开始生成
 * - {event: delta, content}    增量文本
 * - {event: reasoning, content} 思维链增量（推理模型）
 * - {event: done}              生成完成
 * - {event: error, msg}        生成异常
 * Class AiChat
 * @package app\controller\admin\ai
 */
class AiChat extends AuthController
{
    /**
     * 构造方法
     * AiChat constructor.
     * @param AiChatServices $services
     */
    public function __construct(AiChatServices $services)
    {
        parent::__construct();
        $this->services = $services;
    }

    /**
     * AI 流式对话（SSE）
     *
     * @return void 直接输出 SSE 响应流
     */
    public function chat_stream()
    {
        [$prompt, $prompts] = $this->request->postMore([
            ['prompt', ''],
            ['prompts', []],
        ], true);

        if (trim((string)$prompt) === '') {
            return $this->fail('请输入AI提示词');
        }
        $prompts = array_values(array_filter(array_map('strval', (array)$prompts), function ($p) {
            return trim($p) !== '';
        }));

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

        // SSE 响应头（X-Accel-Buffering: no 关闭 nginx 代理缓冲，保证逐块推送）。
        // 流式 echo+exit 不经过框架响应发送阶段，须手动补上 AllowOriginMiddleware 的跨域头
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

        // 取消脚本超时限制，保证流式持续输出
        @ini_set('zlib.output_compression', '0');
        @set_time_limit(0);

        // SSE 事件推送
        $send = function (array $payload): void {
            echo 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n\n";
            if (ob_get_level() > 0) {
                @ob_flush();
            }
            flush();
        };

        try {
            $send(['event' => 'start']);
            $this->services->chatStream((string)$prompt, $prompts, function (array $delta) use ($send) {
                // 思维链与正文分别以独立事件推送（reasoning 为推理模型思维链增量）
                $event = ($delta['type'] ?? 'content') === 'reasoning' ? 'reasoning' : 'delta';
                $send(['event' => $event, 'content' => (string)($delta['text'] ?? '')]);
            });
            $send(['event' => 'done']);
        } catch (\Throwable $e) {
            $send(['event' => 'error', 'msg' => $e->getMessage()]);
        }
        exit;
    }
}
