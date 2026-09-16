<?php
// +----------------------------------------------------------------------
// | CRMChat [ CRMEB 出品客服系统 ]
// +----------------------------------------------------------------------
// | AI 对话服务
// +----------------------------------------------------------------------
namespace app\services\ai;

use crmeb\basic\BaseServices;
use crmeb\exceptions\AdminException;
use crmeb\services\ai\AiInterface;
use crmeb\services\ai\AiProtocol;

/**
 * AI 对话
 *
 * 复用「AI Agent → 模型管理」的模型体系提供流式/非流式对话能力，
 * 优先使用系统默认模型，未设置默认模型时回退内置一号通 AI，
 * 供后台富文本编辑器 AI 写作等功能复用。
 * Class AiChatServices
 * @package app\services\ai
 */
class AiChatServices extends BaseServices
{
    /**
     * 按模型管理构建协议适配器实例
     *
     * 优先使用系统默认模型（AI Agent → 模型管理 中标记默认且启用的模型），
     * 未设置默认模型时回退内置一号通 AI（复用「一号通设置」全局凭证），开箱可用。
     * @return AiInterface 已注入配置的适配器
     * @throws AdminException 默认模型不存在或已停用时抛出
     */
    public function getChatHandler(): AiInterface
    {
        /** @var AiModelServices $modelServices */
        $modelServices = app()->make(AiModelServices::class);
        return $modelServices->getDefaultHandler();
    }

    /**
     * AI 流式对话
     * @param string $prompt 用户提示词
     * @param array $prompts 系统提示词一维数组
     * @param callable $onChunk 增量回调 function(array $delta): void，$delta = ['type' => 'reasoning'|'content', 'text' => string]
     * @throws AdminException|\RuntimeException 模型不可用、一号通凭证缺失或上游请求失败时抛出
     */
    public function chatStream(string $prompt, array $prompts, callable $onChunk): void
    {
        $handler = $this->getChatHandler();
        AiProtocol::chatStream($handler, $prompt, $onChunk, ['prompts' => $prompts]);
    }
}
