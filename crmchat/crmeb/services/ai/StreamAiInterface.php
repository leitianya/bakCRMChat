<?php
// +----------------------------------------------------------------------
// | CRMChat [ CRMEB 出品客服系统 ]
// +----------------------------------------------------------------------
// | AI 流式对话适配器接口
// +----------------------------------------------------------------------
namespace crmeb\services\ai;

/**
 * 流式 AI 对话适配器接口
 *
 * 实现该接口的适配器可走真流式逐块回调；
 * 仅实现 AiInterface 的适配器由 AiProtocol 自动降级为非流式一次性回调。
 * Class StreamAiInterface
 * @package crmeb\services\ai
 */
interface StreamAiInterface
{
    /**
     * AI 流式对话
     * @param string $message 对话内容
     * @param callable $onChunk 增量回调 function(array $delta): void，$delta = ['type' => 'reasoning'|'content', 'text' => string]
     * @param array $options 附加选项（prompts: 提示词一维数组）
     * @throws \RuntimeException 配置缺失或请求失败时抛出
     */
    public function chatStream(string $message, callable $onChunk, array $options = []): void;
}
