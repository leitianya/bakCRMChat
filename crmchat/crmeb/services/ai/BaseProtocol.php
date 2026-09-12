<?php
// +----------------------------------------------------------------------
// | CRMChat [ CRMEB 出品客服系统 ]
// +----------------------------------------------------------------------
// | AI 协议适配器基类
// +----------------------------------------------------------------------
namespace crmeb\services\ai;

/**
 * AI 协议适配器基类
 *
 * 按「协议族」拆分实现：本类只负责配置的读取与消息结构组装，不访问任何数据表；
 * 模型配置的解析由调用方完成后以键值对注入（setConfig）。
 * Class BaseProtocol
 * @package crmeb\services\ai
 */
abstract class BaseProtocol implements AiInterface
{
    /**
     * 模型配置（由调用方解析后注入）
     * @var array
     */
    protected $config = [];

    /**
     * 注入模型配置
     * @param array $config 配置键值对（api_key / base_url / model / temperature / max_tokens / timeout）
     */
    public function setConfig(array $config)
    {
        $this->config = $config;
    }

    /**
     * 读取单个配置项
     * @param string $key 配置键
     * @param mixed $default 默认值
     * @return mixed
     */
    protected function getConfigField(string $key, $default = '')
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * 读取待调用的模型标识
     * @return string
     */
    protected function model(): string
    {
        return trim((string)$this->getConfigField('model'));
    }

    /**
     * 读取采样温度（缺省 0.70）
     * @return float
     */
    protected function temperature(): float
    {
        $value = (float)$this->getConfigField('temperature', 0.7);
        // 温度夹在 OpenAI 兼容协议通用区间内，避免脏数据导致上游直接报错
        return max(0, min(2, $value));
    }

    /**
     * 读取单次回复最大 token 数（0 表示不限制、不下发该参数）
     * @return int
     */
    protected function maxTokens(): int
    {
        return max(0, (int)$this->getConfigField('max_tokens', 0));
    }

    /**
     * 读取接口超时秒数（缺省 120）
     * @return int
     */
    protected function timeout(): int
    {
        $timeout = (int)$this->getConfigField('timeout', 120);
        return $timeout > 0 ? $timeout : 120;
    }

    /**
     * 组装 OpenAI 兼容消息结构
     *
     * prompts（提示词一维数组）作为 system 消息前置，其后为当轮用户消息。
     * @param string $message 用户消息内容
     * @param array $options 附加选项（prompts: 提示词一维数组）
     * @return array messages 消息数组
     */
    protected function buildMessages(string $message, array $options = []): array
    {
        $messages = [];
        foreach ((array)($options['prompts'] ?? []) as $prompt) {
            $prompt = trim((string)$prompt);
            if ($prompt !== '') {
                $messages[] = ['role' => 'system', 'content' => $prompt];
            }
        }
        $messages[] = ['role' => 'user', 'content' => $message];
        return $messages;
    }

    /**
     * 安全编码上游请求体
     *
     * 配置或提示词可能含有非 UTF-8 字节，直接 json_encode() 会返回 false，
     * curl 会把它当作空请求体发出导致上游报错；统一替换非法字节并显式抛错。
     * @param array $data 请求数据
     * @return string
     * @throws \RuntimeException 编码失败时抛出
     */
    protected function encodeRequestBody(array $data): string
    {
        // JSON_INVALID_UTF8_SUBSTITUTE 需 PHP 7.2+，7.1 下退化为普通编码（失败时由下方兜底抛错）
        $flags = JSON_UNESCAPED_UNICODE;
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
        }
        $json = json_encode($data, $flags);
        if ($json === false || $json === '') {
            throw new \RuntimeException('AI请求参数编码失败：' . json_last_error_msg());
        }
        return $json;
    }

    /**
     * 剥离响应体中可能的多层 data 包装，直至定位到 choices 结构
     * @param array $result 已解码的响应数组
     * @return array
     */
    protected function unwrapChoices(array $result): array
    {
        $data = $result;
        while (is_array($data) && !isset($data['choices']) && isset($data['data']) && is_array($data['data'])) {
            $data = $data['data'];
        }
        return is_array($data) ? $data : [];
    }

    /**
     * 从 choices 结构中提取标准对话结果
     * @param array $data 已剥离包装的响应数组
     * @param string $fallbackModel 响应未回传模型名时使用的兜底模型标识
     * @return array ['content' => string, 'reasoning' => string, 'model' => string, 'usage' => array]
     */
    protected function parseChatResult(array $data, string $fallbackModel = ''): array
    {
        $message = $data['choices'][0]['message'] ?? [];
        return [
            'content' => (string)($message['content'] ?? ''),
            // 推理模型（DeepSeek R1 / GLM-Zero 等）的思维过程字段
            'reasoning' => trim((string)($message['reasoning_content'] ?? $message['reasoning'] ?? '')),
            'model' => (string)($data['model'] ?? $fallbackModel),
            'usage' => (array)($data['usage'] ?? []),
        ];
    }
}
