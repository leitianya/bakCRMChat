<?php
// +----------------------------------------------------------------------
// | CRMChat [ CRMEB 出品客服系统 ]
// +----------------------------------------------------------------------
// | AI 对话服务
// +----------------------------------------------------------------------
namespace app\services\ai;

use app\services\system\config\SystemConfigServices;
use crmeb\basic\BaseServices;
use crmeb\exceptions\AdminException;
use crmeb\services\ai\AiInterface;
use crmeb\services\ai\AiProtocol;

/**
 * AI 对话
 *
 * 读取「系统设置 → AI 接口配置」的 OpenAI 兼容接口配置（接口地址 / API Key / 模型标识），
 * 经 crmeb\services\ai\AiProtocol 适配后提供流式/非流式对话能力，
 * 供后台富文本编辑器 AI 写作等功能复用。
 * Class AiChatServices
 * @package app\services\ai
 */
class AiChatServices extends BaseServices
{
    /**
     * 系统配置键 → 协议适配器配置字段映射
     * @var array
     */
    protected const CONFIG_FIELDS = [
        'base_url' => 'ai_base_url',
        'api_key' => 'ai_api_key',
        'model' => 'ai_model',
        'temperature' => 'ai_temperature',
        'max_tokens' => 'ai_max_tokens',
        'timeout' => 'ai_timeout',
        'full_url' => 'ai_full_url',
    ];

    /**
     * AI 协议配置值 → 协议标识映射（配置单选值为整数）
     * @var array
     */
    protected const PROTOCOL_MAP = [1 => 'openai_compatible', 2 => 'yihaotong'];

    /**
     * 按系统配置构建协议适配器实例
     *
     * 依「系统设置 → AI 接口配置」的 AI 协议（ai_protocol）分支：
     * - 一号通AI：凭证取「一号通设置」（yihaotong_appid / yihaotong_appsecret）；
     * - OpenAI兼容协议（默认）：取接口地址 / API Key / 模型标识配置。
     * @return AiInterface 已注入配置的适配器
     * @throws AdminException 未完成对应配置时抛出
     */
    public function getChatHandler(): AiInterface
    {
        /** @var SystemConfigServices $configServices */
        $configServices = app()->make(SystemConfigServices::class);
        $protocolValue = (int)$configServices->getConfigValue('ai_protocol', 1);
        $protocol = self::PROTOCOL_MAP[$protocolValue] ?? 'openai_compatible';
        if ($protocol === 'yihaotong') {
            $config = [];
            foreach (['appid' => 'yihaotong_appid', 'appsecret' => 'yihaotong_appsecret'] as $field => $configName) {
                $value = $configServices->getConfigValue($configName);
                if ($value !== null && $value !== '') {
                    $config[$field] = $value;
                }
            }
            // 模型标识可选，一号通未填写时由平台侧默认
            $model = $configServices->getConfigValue('ai_model');
            if ($model !== null && $model !== '') {
                $config['model'] = $model;
            }
            return AiProtocol::make('yihaotong', $config);
        }

        $config = [];
        foreach (self::CONFIG_FIELDS as $field => $configName) {
            $value = $configServices->getConfigValue($configName);
            if ($value !== null && $value !== '') {
                $config[$field] = $value;
            }
        }
        if (trim((string)($config['base_url'] ?? '')) === '' || trim((string)($config['api_key'] ?? '')) === '') {
            throw new AdminException('尚未配置 AI 接口，请先到「系统设置 → AI 接口配置」填写接口地址与 API Key');
        }

        return AiProtocol::make('openai_compatible', $config);
    }

    /**
     * AI 流式对话
     * @param string $prompt 用户提示词
     * @param array $prompts 系统提示词一维数组
     * @param callable $onChunk 增量回调 function(array $delta): void，$delta = ['type' => 'reasoning'|'content', 'text' => string]
     * @throws AdminException|\RuntimeException 配置缺失或上游请求失败时抛出
     */
    public function chatStream(string $prompt, array $prompts, callable $onChunk): void
    {
        $handler = $this->getChatHandler();
        AiProtocol::chatStream($handler, $prompt, $onChunk, ['prompts' => $prompts]);
    }
}
