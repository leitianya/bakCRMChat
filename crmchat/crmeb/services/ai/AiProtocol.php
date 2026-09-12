<?php
// +----------------------------------------------------------------------
// | CRMChat [ CRMEB 出品客服系统 ]
// +----------------------------------------------------------------------
// | AI 协议适配器工厂
// +----------------------------------------------------------------------
namespace crmeb\services\ai;

/**
 * AI 对话能力的统一入口
 *
 * 协议实现内置于 crmeb/services/ai/protocol/，按「协议族」适配上游接口；
 * 当前内置 OpenAI 兼容协议（覆盖 DeepSeek、通义千问、Kimi、智谱 GLM 等主流服务）。
 *
 * 使用方式：
 *   AiProtocol::make('openai_compatible', ['base_url' => ..., 'api_key' => ..., 'model' => ...])->chat('问题');
 * Class AiProtocol
 * @package crmeb\services\ai
 */
class AiProtocol
{
    /**
     * 内置协议族清单
     * @var array
     */
    protected const PROTOCOLS = [
        'openai_compatible' => [
            'class' => \crmeb\services\ai\protocol\OpenAiCompatible::class,
            'name' => 'OpenAI 兼容协议',
            'description' => '适用于 DeepSeek、通义千问、Kimi、智谱 GLM、Moonshot、豆包、OpenAI 及 vLLM/Ollama 等自建网关',
        ],
        'yihaotong' => [
            'class' => \crmeb\services\ai\protocol\Yihaotong::class,
            'name' => '一号通 AI',
            'description' => '复用「一号通设置」的全局凭证（appid/appsecret），无需单独填写 API Key',
        ],
    ];

    /**
     * 获取协议下拉选项（供后台模型管理表单渲染）
     * @return array [['protocol' => string, 'name' => string, 'description' => string]]
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::PROTOCOLS as $protocol => $meta) {
            $options[] = [
                'protocol' => $protocol,
                'name' => $meta['name'],
                'description' => $meta['description'],
            ];
        }
        return $options;
    }

    /**
     * 判断协议标识是否受支持
     * @param string $protocol 协议标识
     * @return bool
     */
    public static function has(string $protocol): bool
    {
        return isset(self::PROTOCOLS[$protocol]);
    }

    /**
     * 读取协议展示名称
     * @param string $protocol 协议标识
     * @return string 未登记的协议返回标识本身
     */
    public static function name(string $protocol): string
    {
        return (string)(self::PROTOCOLS[$protocol]['name'] ?? $protocol);
    }

    /**
     * 创建协议适配器实例
     * @param string $protocol 协议标识（openai_compatible）
     * @param array $config 模型配置键值对
     * @return AiInterface 已注入配置的适配器实例
     * @throws \InvalidArgumentException 协议不受支持时抛出
     */
    public static function make(string $protocol, array $config = []): AiInterface
    {
        if (!isset(self::PROTOCOLS[$protocol])) {
            throw new \InvalidArgumentException('不支持的 AI 协议：' . $protocol);
        }

        $class = (string)self::PROTOCOLS[$protocol]['class'];
        /** @var AiInterface $handler */
        $handler = new $class();
        $handler->setConfig($config);
        return $handler;
    }

    /**
     * 流式对话（按适配器能力自动降级）
     *
     * 适配器实现 StreamAiInterface 时走真流式逐块回调，
     * 否则发起非流式请求后一次性回调，调用方无需关心协议差异。
     *
     * @param AiInterface $handler 适配器实例
     * @param string $message 对话内容
     * @param callable $onChunk 增量回调 function(array $delta): void，$delta = ['type' => 'reasoning'|'content', 'text' => string]
     * @param array $options 附加选项（prompts: 提示词一维数组）
     * @throws \RuntimeException 上游请求失败时抛出
     */
    public static function chatStream(AiInterface $handler, string $message, callable $onChunk, array $options = []): void
    {
        // 内嵌思维链过滤器：部分推理模型不返回标准 reasoning 字段，而是把思维链以
        // <think>...</think>（含 </think_never_used_xxx> 等变体闭合标签）内嵌在正文流中，
        // 在唯一出口统一改道/剥离，避免内部过程与标签混入最终答复（如编辑器 AI 写作场景）。
        $state = self::newThinkFilterState();

        if ($handler instanceof StreamAiInterface) {
            $handler->chatStream($message, function (array $delta) use ($onChunk, &$state): void {
                if (($delta['type'] ?? 'content') !== 'reasoning') {
                    self::filterThinkChunk($state, (string)($delta['text'] ?? ''), $onChunk);
                    return;
                }
                $onChunk($delta);
            }, $options);
            self::flushThinkFilter($state, $onChunk);
            return;
        }

        $result = $handler->chat($message, $options + ['stream' => 0]);
        if (!empty($result['reasoning'])) {
            $onChunk(['type' => 'reasoning', 'text' => (string)$result['reasoning']]);
        }
        if (($result['content'] ?? '') !== '') {
            self::filterThinkChunk($state, (string)$result['content'], $onChunk);
            self::flushThinkFilter($state, $onChunk);
        }
    }

    /**
     * 创建内嵌思维链流式过滤器状态
     * @return array 过滤器状态（引用传递给 filterThinkChunk / flushThinkFilter）
     */
    private static function newThinkFilterState(): array
    {
        return [
            'inThink' => false,   // 当前是否位于 <think> 标签内部
            'pending' => '',      // 跨块截断的标签前缀缓冲
            'hasContent' => false, // 正文是否已开始外发（用于判定孤立闭合标记前的文本归属）
        ];
    }

    /**
     * 过滤器逐块处理正文增量
     *
     * 1. <think>...</think> 完整思维链：标签内文本改道为 reasoning 增量；
     * 2. 漏发开标签、仅输出 </think_xxx> 变体闭合标记：正文开始前的文本实为思维链，改道 reasoning；
     *    正文已开始后出现的孤立闭合标记视为噪声，仅剥离标记本身；
     * 3. 标签可能跨块截断，未判定完整性的尾部短前缀暂存缓冲。
     *
     * @param array $state 过滤器状态
     * @param string $text 本次到达的正文增量
     * @param callable $onChunk 增量回调 function(array $delta): void，$delta = ['type' => 'reasoning'|'content', 'text' => string]
     */
    private static function filterThinkChunk(array &$state, string $text, callable $onChunk): void
    {
        if ($text === '') {
            return;
        }
        $buffer = $state['pending'] . $text;
        $state['pending'] = '';

        while ($buffer !== '') {
            if ($state['inThink']) {
                // 思维链模式：扫描闭合标签（兼容 </think_xxx> 变体）
                $matched = preg_match('~</think[^>]*>~i', $buffer, $m, PREG_OFFSET_CAPTURE) ? $m[0] : null;
                if ($matched === null) {
                    $hold = self::thinkHoldSuffix($buffer);
                    $emit = substr($buffer, 0, strlen($buffer) - strlen($hold));
                    if ($emit !== '') {
                        $onChunk(['type' => 'reasoning', 'text' => $emit]);
                    }
                    $state['pending'] = $hold;
                    return;
                }
                $pos = (int)$matched[1];
                if ($pos > 0) {
                    $onChunk(['type' => 'reasoning', 'text' => substr($buffer, 0, $pos)]);
                }
                $buffer = substr($buffer, $pos + strlen((string)$matched[0]));
                $state['inThink'] = false;
                continue;
            }

            // 正文模式：扫描 <think> 开标签
            $matched = preg_match('~<think[^>]*>~i', $buffer, $m, PREG_OFFSET_CAPTURE) ? $m[0] : null;
            // 部分兼容网关会漏掉开标签、只输出 </think_xxx>。闭合标记本身不能进入最终答复。
            $closing = preg_match('~</think[^>]*>~i', $buffer, $close, PREG_OFFSET_CAPTURE) ? $close[0] : null;
            if ($closing !== null && ($matched === null || (int)$closing[1] < (int)$matched[1])) {
                $pos = (int)$closing[1];
                $pre = substr($buffer, 0, $pos);
                if ($state['hasContent']) {
                    // 正文已开始：前置文本仍是正文，仅剥离标记
                    if ($pre !== '') {
                        $onChunk(['type' => 'content', 'text' => $pre]);
                    }
                } elseif (trim($pre) !== '') {
                    // 正文尚未开始：标记前的文本实为思维链，改道 reasoning
                    $onChunk(['type' => 'reasoning', 'text' => $pre]);
                    $state['hasContent'] = true;
                }
                $buffer = substr($buffer, $pos + strlen((string)$closing[0]));
                continue;
            }
            if ($matched === null) {
                $hold = self::thinkHoldSuffix($buffer);
                $emit = substr($buffer, 0, strlen($buffer) - strlen($hold));
                if ($emit !== '') {
                    $onChunk(['type' => 'content', 'text' => $emit]);
                    $state['hasContent'] = true;
                }
                $state['pending'] = $hold;
                return;
            }
            $pos = (int)$matched[1];
            if ($pos > 0) {
                $onChunk(['type' => 'content', 'text' => substr($buffer, 0, $pos)]);
                $state['hasContent'] = true;
            }
            $buffer = substr($buffer, $pos + strlen((string)$matched[0]));
            $state['inThink'] = true;
        }
    }

    /**
     * 流结束后冲刷过滤器残留缓冲（跨块截断的未闭合标签前缀）
     * @param array $state 过滤器状态
     * @param callable $onChunk 增量回调 function(array $delta): void，$delta = ['type' => 'reasoning'|'content', 'text' => string]
     */
    private static function flushThinkFilter(array &$state, callable $onChunk): void
    {
        $pending = (string)$state['pending'];
        if ($pending === '') {
            return;
        }
        $state['pending'] = '';
        if ($state['inThink']) {
            $onChunk(['type' => 'reasoning', 'text' => $pending]);
            return;
        }
        $onChunk(['type' => 'content', 'text' => $pending]);
        $state['hasContent'] = true;
    }

    /**
     * 提取缓冲尾部可能是跨块截断标签前缀的短后缀（自最后一个未闭合的 "<" 起）
     * @param string $buffer 当前缓冲
     * @return string 需暂存待下一块合并判定的后缀，无需暂存时返回空串
     */
    private static function thinkHoldSuffix(string $buffer): string
    {
        $lastLt = strrpos($buffer, '<');
        if ($lastLt === false) {
            return '';
        }
        $suffix = substr($buffer, $lastLt);
        // 已闭合（含 >）说明不是跨块截断的标签前缀
        if (strpos($suffix, '>') !== false) {
            return '';
        }
        return $suffix;
    }
}
