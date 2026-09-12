<?php
// +----------------------------------------------------------------------
// | CRMChat [ CRMEB 出品客服系统 ]
// +----------------------------------------------------------------------
// | 远程 MCP Server 客户端（JSON-RPC 2.0 over Streamable HTTP）
// +----------------------------------------------------------------------

namespace app\services\aiagent;

/**
 * 远程 MCP Server 客户端（JSON-RPC 2.0 over Streamable HTTP）
 *
 * 按 MCP Streamable HTTP 传输规范向 Server 地址 POST JSON-RPC 请求：
 * 请求头同时声明 application/json 与 text/event-stream，响应兼容
 * 纯 JSON 与 SSE（data: 行）两种载荷；自动完成 initialize 握手、
 * Mcp-Session-Id 会话保持与 tools/list 游标分页。
 * Class RemoteMcpClient
 * @package app\services\aiagent
 */
class RemoteMcpClient
{
    /**
     * 客户端声明的 MCP 协议版本
     */
    const PROTOCOL_VERSION = '2024-11-05';

    /**
     * tools/list 游标分页最大页数（防御死循环）
     */
    const MAX_TOOL_PAGES = 10;

    /**
     * @var string Server 接口地址
     */
    private $url;

    /**
     * @var array<string,string> 附加请求头（鉴权密钥与自定义头）
     */
    private $headers;

    /**
     * @var int 请求超时（秒）
     */
    private $timeout;

    /**
     * @var string|null Server 返回的会话 ID（initialize 后有效）
     */
    private $sessionId = null;

    /**
     * @var bool 是否已完成 initialize 握手
     */
    private $initialized = false;

    /**
     * @var int JSON-RPC 请求自增 id
     */
    private $requestId = 0;

    /**
     * @param string $url Server 接口地址
     * @param array $headers 附加请求头键值对
     * @param int $timeout 请求超时（秒）
     */
    public function __construct(string $url, array $headers = [], int $timeout = 15)
    {
        $this->url = $url;
        $this->headers = $headers;
        $this->timeout = $timeout;
    }

    /**
     * 由 eb_ai_mcp_server 记录构建客户端
     * @param array $server Server 记录（url/api_key/headers/timeout 字段）
     * @return self
     * @throws \RuntimeException 地址未配置时抛出
     */
    public static function fromServer(array $server): self
    {
        $url = trim((string)($server['url'] ?? ''));
        if ($url === '') {
            throw new \RuntimeException('MCP Server 接口地址未配置');
        }
        $headers = [];
        $apiKey = trim((string)($server['api_key'] ?? ''));
        if ($apiKey !== '') {
            $headers['X-Mcp-Key'] = $apiKey;
        }
        $extra = json_decode((string)($server['headers'] ?? ''), true);
        if (is_array($extra)) {
            foreach ($extra as $name => $value) {
                if (is_string($name) && $name !== '' && $value !== null) {
                    $headers[$name] = (string)$value;
                }
            }
        }
        return new self($url, $headers, (int)max(3, min(120, (int)($server['timeout'] ?? 15))));
    }

    /**
     * 拉取远端全量工具清单（含游标分页）
     * @return array<int, array{name:string,description:string,inputSchema:array,interaction?:string}>
     * @throws \RuntimeException 握手或请求失败、平台返回错误时抛出
     */
    public function listTools(): array
    {
        $this->initialize();
        $tools = [];
        $cursor = '';
        for ($page = 0; $page < self::MAX_TOOL_PAGES; $page++) {
            $result = $this->rpc('tools/list', $cursor !== '' ? ['cursor' => $cursor] : []);
            foreach ($result['tools'] ?? [] as $tool) {
                $name = trim((string)($tool['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $item = [
                    'name'        => $name,
                    'description' => (string)($tool['description'] ?? ''),
                    'inputSchema' => is_array($tool['inputSchema'] ?? null)
                        ? $tool['inputSchema']
                        : ['type' => 'object', 'properties' => new \stdClass()],
                ];
                // 透传远端交互声明（如 execute=需确认后执行），未声明则不带该字段
                if ((string)($tool['interaction'] ?? '') !== '') {
                    $item['interaction'] = (string)$tool['interaction'];
                }
                $tools[] = $item;
            }
            $cursor = trim((string)($result['nextCursor'] ?? ''));
            if ($cursor === '') {
                break;
            }
        }
        return $tools;
    }

    /**
     * 调用远端工具
     * @param string $name 远端工具原名（不含 server_key 前缀）
     * @param array $arguments 工具入参
     * @return array MCP result 载荷（content / isError 等）
     * @throws \RuntimeException 传输失败或远端报告工具执行错误时抛出
     */
    public function callTool(string $name, array $arguments): array
    {
        $this->initialize();
        $result = $this->rpc('tools/call', [
            'name'      => $name,
            'arguments' => $arguments ?: new \stdClass(),
        ]);
        if (!empty($result['isError'])) {
            $text = '';
            foreach ($result['content'] ?? [] as $block) {
                if (($block['type'] ?? '') === 'text') {
                    $text .= (string)($block['text'] ?? '');
                }
            }
            throw new \RuntimeException('远程 MCP 工具执行失败：' . ($text !== '' ? mb_substr($text, 0, 500) : '未知错误'));
        }
        return $result;
    }

    /**
     * MCP 初始化握手（幂等，进程内仅执行一次）
     * @throws \RuntimeException 握手失败时抛出
     */
    private function initialize(): void
    {
        if ($this->initialized) {
            return;
        }
        $this->rpc('initialize', [
            'protocolVersion' => self::PROTOCOL_VERSION,
            'capabilities'    => new \stdClass(),
            'clientInfo'      => ['name' => 'crmchat-aiagent', 'version' => '1.0.0'],
        ]);
        $this->initialized = true;
        $this->notify('notifications/initialized');
    }

    /**
     * 发送 JSON-RPC 请求并解析 result
     * @param string $method RPC 方法名
     * @param array $params 方法参数
     * @return array result 载荷
     * @throws \RuntimeException 传输失败或 JSON-RPC error 时抛出
     */
    private function rpc(string $method, array $params): array
    {
        $payload = json_encode([
            'jsonrpc' => '2.0',
            'id'      => ++$this->requestId,
            'method'  => $method,
            'params'  => $params ?: new \stdClass(),
        ], JSON_UNESCAPED_UNICODE);
        [$status, $contentType, $body] = $this->send($payload);
        $message = $this->decodeMessage($contentType, $body);
        if ($status >= 400 || isset($message['error'])) {
            throw new \RuntimeException('MCP Server 错误：' . $this->errorMessage($message, $status));
        }
        return is_array($message['result'] ?? null) ? $message['result'] : [];
    }

    /**
     * 发送 JSON-RPC 通知（无 id，不要求响应体）
     * @param string $method 通知方法
     * @throws \RuntimeException Server 明确拒绝时抛出
     */
    private function notify(string $method): void
    {
        $payload = json_encode([
            'jsonrpc' => '2.0',
            'method'  => $method,
            'params'  => new \stdClass(),
        ], JSON_UNESCAPED_UNICODE);
        [$status, , $body] = $this->send($payload);
        if ($status >= 400) {
            $message = $this->decodeMessage('', $body);
            throw new \RuntimeException('MCP Server 错误：' . $this->errorMessage($message, $status));
        }
    }

    /**
     * 发起 HTTP POST 并拆分状态码、内容类型与会话头
     * @param string $payload 请求体（JSON-RPC 报文）
     * @return array [HTTP 状态码, 响应 Content-Type, 响应体]
     * @throws \RuntimeException 网络失败时抛出
     */
    private function send(string $payload): array
    {
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json, text/event-stream',
        ];
        foreach ($this->headers as $name => $value) {
            $headers[] = $name . ': ' . $value;
        }
        if ($this->sessionId !== null) {
            $headers[] = 'Mcp-Session-Id: ' . $this->sessionId;
        }

        $ch = curl_init($this->url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);
        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('MCP Server 请求失败：' . ($error !== '' ? $error : '网络异常，请稍后重试'));
        }
        $status     = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $rawHeaders = substr($response, 0, $headerSize);
        $body       = trim(substr($response, $headerSize));
        $contentType = '';
        if (preg_match('/^content-type:\s*([^\r\n;]+)/im', $rawHeaders, $match)) {
            $contentType = strtolower(trim($match[1]));
        }
        // 首次响应携带会话 ID 时记住，后续请求回传
        if ($this->sessionId === null && preg_match('/^mcp-session-id:\s*([^\r\n]+)/im', $rawHeaders, $match)) {
            $this->sessionId = trim($match[1]);
        }
        return [$status, $contentType, $body];
    }

    /**
     * 解析响应载荷为 JSON-RPC 消息（兼容 SSE 格式）
     * @param string $contentType 响应 Content-Type
     * @param string $body 响应体
     * @return array 解析出的消息，无法解析时为空数组
     */
    private function decodeMessage(string $contentType, string $body): array
    {
        if ($body === '') {
            return [];
        }
        if (strpos($contentType, 'text/event-stream') !== false) {
            // SSE 载荷：取首个包含 result/error 的 data: 行
            foreach (preg_split('/\r?\n/', $body) as $line) {
                if (strpos($line, 'data:') !== 0) {
                    continue;
                }
                $message = json_decode(trim(substr($line, 5)), true);
                if (is_array($message) && (isset($message['result']) || isset($message['error']))) {
                    return $message;
                }
            }
            return [];
        }
        $message = json_decode($body, true);
        return is_array($message) ? $message : [];
    }

    /**
     * 从 JSON-RPC 消息或 HTTP 状态提取可读错误信息
     * @param array $message 已解析的响应消息
     * @param int $status HTTP 状态码
     * @return string
     */
    private function errorMessage(array $message, int $status): string
    {
        $error = $message['error'] ?? null;
        if (is_array($error)) {
            return (string)($error['message'] ?? '未知错误');
        }
        if (is_string($error) && $error !== '') {
            return $error;
        }
        if ($status >= 400) {
            return 'HTTP ' . $status;
        }
        return '响应解析失败';
    }
}
