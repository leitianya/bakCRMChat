<?php
// +----------------------------------------------------------------------
// | CRMChat [ CRMEB 出品客服系统 ]
// +----------------------------------------------------------------------
// | AI 模型管理服务
// +----------------------------------------------------------------------

namespace app\services\ai;

use app\dao\ai\AiModelDao;
use app\services\system\config\SystemConfigServices;
use crmeb\basic\BaseServices;
use crmeb\exceptions\AdminException;
use crmeb\services\ai\AiInterface;
use crmeb\services\ai\AiProtocol;

/**
 * AI 模型管理服务
 *
 * 职责：模型登记的增删改查 + 把模型记录解析为协议适配器可用的配置，
 * 协议实现本身在 crmeb\services\ai\protocol 下，不感知数据表。
 * Class AiModelServices
 * @package app\services\ai
 */
class AiModelServices extends BaseServices
{
    /**
     * 密钥占位符：更新时回传该值表示保持原密钥不变
     * @var string
     */
    const SECRET_KEEP = '__KEEP__';

    /**
     * 系统内置默认协议：一号通 AI
     *
     * 凭证来自「一号通登录/设置」写入的全局系统配置（yihaotong_appid/yihaotong_appsecret），
     * 无需在 eb_ai_model 登记模型记录，未显式指定模型的对话接口统一以此协议兜底。
     */
    const DEFAULT_PROTOCOL = 'yihaotong';

    /**
     * AiModelServices constructor.
     * @param AiModelDao $dao
     */
    public function __construct(AiModelDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 模型列表（分页）
     * @param array $where 查询条件（status/protocol/keyword）
     * @param int $page 页码
     * @param int $limit 每页条数
     * @return array ['list' => array, 'count' => int]
     */
    public function getList(array $where = [], int $page = 1, int $limit = 20): array
    {
        $count = $this->dao->count($where);
        $list = $this->dao->getDataList($where, ['*'], ['sort' => 'desc', 'id' => 'asc'], $page, $limit);
        foreach ($list as &$item) {
            $item = $this->formatModel($item);
        }
        unset($item);
        return ['list' => $list, 'count' => $count];
    }

    /**
     * 模型详情
     * @param int $id 模型ID
     * @return array
     */
    public function getDetail(int $id): array
    {
        return $this->formatModel($this->dao->findById($id), true);
    }

    /**
     * 新增模型
     * @param array $data 表单数据
     * @return int 模型ID
     */
    public function save(array $data): int
    {
        $payload = $this->buildData($data);
        $payload['is_default'] = (int)($payload['is_default'] ?? 0);
        $payload['create_time'] = time();
        $payload['update_time'] = time();
        if ($payload['is_default'] === 1 && (int)($payload['status'] ?? 1) === 1) {
            $payload['is_default'] = 0;
            $id = (int)$this->dao->save($payload)->id;
            $this->setDefault($id);
            return $id;
        }
        return (int)$this->dao->save($payload)->id;
    }

    /**
     * 修改模型
     * @param int $id 模型ID
     * @param array $data 表单数据
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        $old = $this->dao->findById($id);
        if (!$old) {
            throw new AdminException('AI 模型不存在');
        }

        $payload = $this->buildData($data, $id);
        // 密钥留空或回传占位符时保持原值，避免编辑表单必须重填密钥
        if (($payload['api_key'] ?? '') === '' || $payload['api_key'] === self::SECRET_KEEP) {
            $payload['api_key'] = (string)($old['api_key'] ?? '');
        }
        $payload['update_time'] = time();

        $this->dao->update($id, $payload);
        if ((int)($payload['is_default'] ?? 0) === 1) {
            $this->setDefault($id);
        }
        return true;
    }

    /**
     * 删除模型
     * @param int $id 模型ID
     * @return bool
     */
    public function delete(int $id): bool
    {
        if (!$this->dao->findById($id)) {
            throw new AdminException('AI 模型不存在');
        }
        $this->dao->delete($id);
        return true;
    }

    /**
     * 修改模型状态
     * @param int $id 模型ID
     * @param int $status 状态（0=停用 1=启用）
     * @return bool
     */
    public function setStatus(int $id, int $status): bool
    {
        if (!$this->dao->findById($id)) {
            throw new AdminException('AI 模型不存在');
        }
        $this->dao->update($id, $status === 0 ? ['status' => 0, 'is_default' => 0] : ['status' => 1]);
        return true;
    }

    /**
     * 设为系统默认模型
     * @param int $id 模型ID
     * @return bool
     */
    public function setDefault(int $id): bool
    {
        $model = $this->dao->findById($id);
        if (!$model) {
            throw new AdminException('AI 模型不存在');
        }
        if ((int)$model['status'] !== 1) {
            throw new AdminException('请先启用该模型再设为默认');
        }
        $this->dao->clearDefaultExcept($id);
        $this->dao->update($id, ['is_default' => 1]);
        return true;
    }

    /**
     * 模型连通性测试：按表单配置构造协议适配器发起一次真实对话请求（不落库）
     * @param array $data 表单数据（与保存表单一致，编辑时需携带 id 以继承原密钥）
     * @return array [ok=>1|0, cost_ms=>int, message=>string]
     */
    public function testModel(array $data): array
    {
        $id = (int)($data['id'] ?? 0);
        $old = $id > 0 ? $this->dao->findById($id) : null;
        if ($old) {
            $data = array_merge($old, $data);
            // 编辑场景密钥回传占位符或留空时，使用原密钥真实测试
            if (in_array(trim((string)($data['api_key'] ?? '')), ['', self::SECRET_KEEP], true)) {
                $data['api_key'] = (string)$old['api_key'];
            }
        }
        // 名称仅用于展示，测试场景不参与查重
        $data['name'] = trim((string)($data['name'] ?? '')) ?: ((string)($data['model'] ?? ''));
        $payload = $this->buildData($data, $id);

        $driver = AiProtocol::make($payload['protocol'], $this->buildConfig($payload));
        $start = microtime(true);
        try {
            $result = $driver->chat('连通性测试，请直接回复：ok', ['stream' => 0]);
        } catch (\Throwable $e) {
            return [
                'ok'      => 0,
                'cost_ms' => (int)round((microtime(true) - $start) * 1000),
                'message' => '连通性测试失败：' . $e->getMessage(),
            ];
        }
        $costMs = (int)round((microtime(true) - $start) * 1000);
        $content = trim((string)($result['content'] ?? ''));
        return [
            'ok'      => 1,
            'cost_ms' => $costMs,
            'message' => $content !== ''
                ? '连通性测试成功，模型已正常响应（耗时 ' . $costMs . 'ms）'
                : '连通性测试通过，但模型未返回内容，请检查模型配置',
        ];
    }

    /**
     * 可用模型清单（下拉选择用，不含密钥）
     * @param bool $onlyEnabled 仅返回启用中的模型
     * @return array
     */
    public function getAvailableModels(bool $onlyEnabled = true): array
    {
        $list = $onlyEnabled ? $this->dao->getAllEnabled() : $this->dao->getDataList([], ['*'], ['sort' => 'desc', 'id' => 'asc']);
        $result = [];
        foreach ($list as $item) {
            $result[] = [
                'id'             => (int)$item['id'],
                'name'           => (string)$item['name'],
                'protocol'       => (string)$item['protocol'],
                'protocol_name'  => AiProtocol::name((string)$item['protocol']),
                'provider'       => (string)$item['provider'],
                'model'          => (string)$item['model'],
                'supports_tools' => (int)$item['supports_tools'],
                'is_default'     => (int)$item['is_default'],
                'status'         => (int)$item['status'],
            ];
        }
        return $result;
    }

    /**
     * 内置协议族清单（后台表单渲染用）
     * @return array
     */
    public function getProtocols(): array
    {
        return AiProtocol::options();
    }

    /**
     * 按模型 ID 创建协议适配器实例
     * @param int $modelId 模型ID
     * @return AiInterface
     */
    public function getHandler(int $modelId = 0): AiInterface
    {
        $model = $this->dao->findById($modelId);
        if (!$model) {
            throw new AdminException('AI 模型不存在，请在「AI Agent → 模型管理」中检查配置');
        }
        if ((int)$model['status'] !== 1) {
            throw new AdminException('AI 模型[' . $model['name'] . ']已停用，请在「AI Agent → 模型管理」中启用');
        }
        return AiProtocol::make((string)$model['protocol'], $this->buildConfig($model));
    }

    /**
     * 对话接口用适配器解析：显式指定模型走登记模型，缺省走内置一号通 AI
     *
     * 与 getHandler 的差异：本方法在 model_id 为 0 时不依赖「系统默认模型」，
     * 直接使用一号通全局凭证，保证后台内置 AI 能力开箱可用。
     * @param int $modelId 模型ID，传 0 使用内置一号通 AI
     * @return AiInterface
     */
    public function getChatHandler(int $modelId = 0): AiInterface
    {
        return $modelId > 0 ? $this->getHandler($modelId) : $this->getYihaotongHandler();
    }

    /**
     * 创建内置一号通 AI 适配器
     *
     * 一号通 appid / appsecret 由「一号通登录」写入 eb_system_config 全站共享，
     * 模型标识留空表示由一号通平台侧决定实际模型。
     * @return AiInterface
     */
    public function getYihaotongHandler(): AiInterface
    {
        return AiProtocol::make(self::DEFAULT_PROTOCOL, [
            'api_key'     => '',
            'base_url'    => '',
            'model'       => '',
            'temperature' => 0.7,
            'max_tokens'  => 2048,
        ]);
    }

    /**
     * 一号通凭证是否已配置（模型管理一键配置入口的状态判断）
     * @return int 1=已配置 0=未配置
     */
    public function yihaotongConfigured(): int
    {
        /** @var SystemConfigServices $configServices */
        $configServices = app()->make(SystemConfigServices::class);
        $appid = trim((string)$configServices->getConfigValue('yihaotong_appid', ''));
        $secret = trim((string)$configServices->getConfigValue('yihaotong_appsecret', ''));
        return ($appid !== '' && $secret !== '') ? 1 : 0;
    }

    /**
     * 模型记录 → 协议适配器配置
     * @param array $model 模型记录
     * @return array
     */
    public function buildConfig(array $model): array
    {
        return [
            'api_key'     => (string)($model['api_key'] ?? ''),
            'base_url'    => (string)($model['base_url'] ?? ''),
            'full_url'    => (int)($model['full_url'] ?? 0),
            'model'       => (string)($model['model'] ?? ''),
            'temperature' => (float)($model['temperature'] ?? 0.7),
            'max_tokens'  => (int)($model['max_tokens'] ?? 2048),
        ];
    }

    /**
     * 表单校验并归一化为可入库字段
     * @param array $data 表单数据
     * @param int $exceptId 编辑时排除自身ID
     * @return array
     */
    protected function buildData(array $data, int $exceptId = 0): array
    {
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            throw new AdminException('请填写模型显示名称');
        }
        if ($this->dao->countByName($name, $exceptId) > 0) {
            throw new AdminException('模型显示名称已存在');
        }

        $protocol = trim((string)($data['protocol'] ?? ''));
        if (!AiProtocol::has($protocol)) {
            throw new AdminException('请选择正确的 AI 协议');
        }

        $model = trim((string)($data['model'] ?? ''));
        if ($model === '') {
            throw new AdminException('请填写模型标识（如 deepseek-chat、qwen-plus）');
        }

        $baseUrl = trim((string)($data['base_url'] ?? ''));
        if ($baseUrl !== '' && !preg_match('#^https?://#i', $baseUrl)) {
            throw new AdminException('接口地址必须以 http:// 或 https:// 开头');
        }

        $temperature = (float)($data['temperature'] ?? 0.7);
        if ($temperature < 0 || $temperature > 2) {
            throw new AdminException('采样温度取值范围为 0 ~ 2');
        }

        return [
            'name'            => $name,
            'protocol'        => $protocol,
            'provider'        => trim((string)($data['provider'] ?? '')),
            'model'           => $model,
            'base_url'        => $baseUrl,
            'api_key'         => trim((string)($data['api_key'] ?? '')),
            'full_url'        => (int)($data['full_url'] ?? 0) === 1 ? 1 : 0,
            'supports_tools'  => (int)($data['supports_tools'] ?? 0) === 1 ? 1 : 0,
            'supports_vision' => (int)($data['supports_vision'] ?? 0) === 1 ? 1 : 0,
            'context_window'  => max(0, (int)($data['context_window'] ?? 0)),
            'max_tokens'      => max(0, (int)($data['max_tokens'] ?? 2048)),
            'temperature'     => $temperature,
            'is_default'      => (int)($data['is_default'] ?? 0) === 1 ? 1 : 0,
            'sort'            => (int)($data['sort'] ?? 0),
            'status'          => (int)($data['status'] ?? 1) === 0 ? 0 : 1,
        ];
    }

    /**
     * 输出格式化：脱敏密钥并补充协议名
     * @param array $item 模型记录
     * @param bool $detail 是否详情场景（详情仅给出是否已配置的布尔提示）
     * @return array
     */
    protected function formatModel(array $item, bool $detail = false): array
    {
        if (!$item) {
            return [];
        }
        $apiKey = (string)($item['api_key'] ?? '');
        $item['protocol_name'] = AiProtocol::name((string)$item['protocol']);
        $item['has_api_key'] = $apiKey !== '' ? 1 : 0;
        $item['api_key'] = $detail ? ($apiKey !== '' ? self::SECRET_KEEP : '') : $this->maskSecret($apiKey);
        $item['temperature'] = (float)$item['temperature'];
        $item['max_tokens'] = (int)$item['max_tokens'];
        $item['context_window'] = (int)$item['context_window'];
        $item['full_url'] = (int)($item['full_url'] ?? 0);
        $item['supports_tools'] = (int)$item['supports_tools'];
        $item['supports_vision'] = (int)$item['supports_vision'];
        $item['is_default'] = (int)$item['is_default'];
        $item['status'] = (int)$item['status'];
        $item['sort'] = (int)$item['sort'];
        return $item;
    }

    /**
     * 密钥脱敏展示
     * @param string $secret 原始密钥
     * @return string
     */
    protected function maskSecret(string $secret): string
    {
        if ($secret === '') {
            return '';
        }
        $len = strlen($secret);
        if ($len <= 8) {
            return str_repeat('*', $len);
        }
        return substr($secret, 0, 4) . str_repeat('*', (int)min(12, $len - 8)) . substr($secret, -4);
    }
}
