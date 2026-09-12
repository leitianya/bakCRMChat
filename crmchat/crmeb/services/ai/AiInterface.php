<?php
// +----------------------------------------------------------------------
// | CRMChat [ CRMEB 出品客服系统 ]
// +----------------------------------------------------------------------
// | AI 协议适配器接口
// +----------------------------------------------------------------------
namespace crmeb\services\ai;

/**
 * AI 对话适配器统一接口
 *
 * 非流式与流式实现分别由 AiInterface / StreamAiInterface 约束，
 * AiProtocol 按适配器能力自动选择调用方式。
 * Class AiInterface
 * @package crmeb\services\ai
 */
interface AiInterface
{
    /**
     * 注入模型配置
     * @param array $config 配置键值对（api_key / base_url / model / temperature / max_tokens / timeout）
     * @return mixed
     */
    public function setConfig(array $config);

    /**
     * AI 对话（非流式）
     * @param string $message 对话内容
     * @param array $options 附加选项（prompts: 提示词一维数组）
     * @return array ['content' => string, 'model' => string, 'usage' => array]
     * @throws \RuntimeException 配置缺失、请求失败或平台返回错误时抛出
     */
    public function chat(string $message, array $options = []): array;
}
