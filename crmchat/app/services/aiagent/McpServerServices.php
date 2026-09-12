<?php
// +----------------------------------------------------------------------
// | CRMChat [ CRMEB 出品客服系统 ]
// +----------------------------------------------------------------------
// | 远程 MCP Server 登记管理服务
// +----------------------------------------------------------------------

namespace app\services\aiagent;

use app\dao\aiagent\AiMcpServerDao;
use crmeb\basic\BaseServices;
use crmeb\exceptions\AdminException;
use think\facade\Db;

/**
 * 远程 MCP Server 的登记、校验与工具清单同步管理（eb_ai_mcp_server）。
 * Class McpServerServices
 * @package app\services\aiagent
 */
class McpServerServices extends BaseServices
{
    /**
     * 密钥占位符：更新时回传该值表示保持原密钥不变
     */
    const SECRET_KEEP = '__KEEP__';

    /**
     * McpServerServices constructor.
     * @param AiMcpServerDao $dao
     */
    public function __construct(AiMcpServerDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * Server 列表（密钥脱敏）：系统内置 MCP 虚拟行固定首位，其后为登记的远程 Server
     * @return array
     */
    public function getList(): array
    {
        $list = array_merge([$this->systemRow()], array_map([$this, 'format'], $this->dao->getAllList()));
        return $list;
    }

    /**
     * Server 详情（密钥以占位符回显）
     * @param int $id Server ID
     * @return array
     */
    public function get(int $id): array
    {
        if ($id === 0) {
            return $this->systemRow();
        }
        return $this->format($this->find($id));
    }

    /**
     * 新增/修改 Server
     * @param array $data 表单数据
     * @param int $id 编辑时的 Server ID，0 表示新增
     * @return int Server ID
     */
    public function save(array $data, int $id = 0): int
    {
        $payload = $this->buildData($data, $id);
        if ($id > 0) {
            $old = $this->find($id);
            $apiKey = trim((string)$payload['api_key']);
            // 密钥留空或回传占位符时保持原值，避免编辑表单必须重填密钥
            if ($apiKey === '' || $apiKey === self::SECRET_KEEP) {
                $payload['api_key'] = (string)$old['api_key'];
            }
            // 命名空间前缀变化会使已同步工具的外部名称失效，需重新同步
            if ($payload['server_key'] !== (string)$old['server_key']) {
                $payload['tools'] = '';
                $payload['tools_sync_time'] = 0;
            }
            $payload['update_time'] = time();
            $this->dao->updateById($id, $payload);
            return $id;
        }

        $payload['create_time'] = time();
        $payload['update_time'] = time();
        return $this->dao->insertServer($payload);
    }

    /**
     * 以 mcpServers JSON 配置（Cursor/Claude Desktop 风格）新增或编辑 Server
     * @param string $raw JSON 配置文本，形如 {"mcpServers":{"key":{"url":"https://...","headers":{...}}}}
     * @param int $id 编辑时的 Server ID，0 表示新增（新增支持一次导入多条）
     * @return array<int> 保存后的 Server ID 列表
     */
    public function saveJson(string $raw, int $id = 0): array
    {
        $config = json_decode(trim($raw), true);
        if (trim($raw) === '' || !is_array($config) || json_last_error() !== JSON_ERROR_NONE) {
            throw new AdminException('JSON 格式不正确，请检查配置内容');
        }
        $servers = $config['mcpServers'] ?? null;
        if (!is_array($servers) || $servers === []) {
            throw new AdminException('配置需包含 mcpServers 节点，且至少登记一个 Server');
        }
        if ($id > 0 && count($servers) !== 1) {
            throw new AdminException('编辑单个 Server 时，配置中只能包含一条 mcpServers 记录');
        }

        $forms = [];
        foreach ($servers as $key => $item) {
            if (!is_array($item)) {
                throw new AdminException('Server "' . $key . '" 的配置必须是对象');
            }
            $forms[] = $this->buildFormFromJsonItem((string)$key, $item);
        }

        if ($id > 0) {
            return [$this->save($forms[0], $id)];
        }

        return Db::transaction(function () use ($forms) {
            $ids = [];
            foreach ($forms as $form) {
                $ids[] = $this->save($form, 0);
            }
            return $ids;
        });
    }

    /**
     * 将单条 mcpServers 配置转换为表单数据（校验统一由 buildData 完成）
     * @param string $key 配置名，作为 Server 标识
     * @param array $item 配置内容
     * @return array
     */
    private function buildFormFromJsonItem(string $key, array $item): array
    {
        if (isset($item['command'])) {
            throw new AdminException('Server "' . $key . '"：暂不支持本地进程（command）类型，仅提供远程 url（Streamable HTTP）接入');
        }
        $disabled = !empty($item['disabled']);
        return [
            'name'        => trim((string)($item['name'] ?? '')) ?: $key,
            'server_key'  => $key,
            'url'         => trim((string)($item['url'] ?? '')),
            'api_key'     => trim((string)($item['api_key'] ?? '')),
            'headers'     => is_array($item['headers'] ?? null) ? $item['headers'] : [],
            'timeout'     => (int)($item['timeout'] ?? 15),
            'description' => trim((string)($item['description'] ?? '')),
            'status'      => $disabled ? 0 : 1,
        ];
    }

    /**
     * 启停 Server
     * @param int $id Server ID
     * @param int $status 状态（0=停用 1=启用）
     */
    public function setStatus(int $id, int $status): void
    {
        $this->find($id);
        $this->dao->updateById($id, [
            'status'      => $status === 1 ? 1 : 0,
            'update_time' => time(),
        ]);
    }

    /**
     * 删除 Server（对话中引用其工具的白名单项将随工具消失而自然失效）
     * @param int $id Server ID
     */
    public function delete(int $id): void
    {
        $this->find($id);
        $this->dao->deleteById($id);
    }

    /**
     * 同步远端工具清单并回写缓存
     * @param int $id Server ID
     * @return array 同步到的工具清单 [{name,description,inputSchema}]
     */
    public function syncTools(int $id): array
    {
        $server = $this->find($id);
        try {
            $tools = RemoteMcpClient::fromServer($server)->listTools();
        } catch (\Throwable $e) {
            throw new AdminException('MCP 工具同步失败：' . $e->getMessage());
        }
        $this->dao->updateById($id, [
            'tools'           => json_encode($tools, JSON_UNESCAPED_UNICODE),
            'tools_sync_time' => time(),
            'update_time'     => time(),
        ]);
        return $tools;
    }

    /**
     * 测试远端连通性（执行完整 initialize 握手并拉取工具清单，不回写工具缓存）
     * @param int $id Server ID
     * @return array [ok=>1|0, tool_count=>int, cost_ms=>int, message=>string]
     */
    public function testConnection(int $id): array
    {
        $server = $this->find($id);
        $start = microtime(true);
        try {
            $tools = RemoteMcpClient::fromServer($server)->listTools();
        } catch (\Throwable $e) {
            return [
                'ok'         => 0,
                'tool_count' => 0,
                'cost_ms'    => (int)round((microtime(true) - $start) * 1000),
                'message'    => '连接失败：' . $e->getMessage(),
            ];
        }
        return [
            'ok'         => 1,
            'tool_count' => count($tools),
            'cost_ms'    => (int)round((microtime(true) - $start) * 1000),
            'message'    => '连接成功，发现 ' . count($tools) . ' 个工具',
        ];
    }

    /**
     * 启用中的 Server 原始行（含明文密钥，仅供工具并入与调用分派）
     * @return array
     */
    public function getEnabledServers(): array
    {
        return $this->dao->getEnabledList();
    }

    /**
     * 按命名空间前缀查找启用中的 Server 原始行
     * @param string $serverKey Server 标识
     * @return array|null
     */
    public function findEnabledByKey(string $serverKey): ?array
    {
        if ($serverKey === '') {
            return null;
        }
        $server = $this->dao->getEnabledByKey($serverKey);
        return $server ?: null;
    }

    /**
     * 查询 Server 原始行，不存在抛出异常
     * @param int $id Server ID
     * @return array
     */
    public function find(int $id): array
    {
        $server = $this->dao->getById($id);
        if (!$server) {
            throw new AdminException('MCP Server 不存在');
        }
        return $server;
    }

    /**
     * 表单校验并归一化为可入库字段
     * @param array $data 表单数据
     * @param int $exceptId 编辑时排除自身 ID
     * @return array
     */
    private function buildData(array $data, int $exceptId = 0): array
    {
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            throw new AdminException('请输入 Server 名称');
        }

        $serverKey = strtolower(trim((string)($data['server_key'] ?? '')));
        if (!preg_match('/^[a-z][a-z0-9_-]{0,31}$/', $serverKey)) {
            throw new AdminException('Server 标识只能由小写字母、数字、下划线、中划线组成，且以字母开头');
        }
        // system 为系统内置 MCP 保留标识，避免命名空间前缀冲突
        if ($serverKey === SystemMcpService::SERVER_KEY) {
            throw new AdminException('Server 标识 "' . SystemMcpService::SERVER_KEY . '" 为系统内置 MCP 保留，请更换');
        }
        if ($this->dao->countByKey($serverKey, $exceptId) > 0) {
            throw new AdminException('Server 标识已存在，请更换');
        }

        $url = trim((string)($data['url'] ?? ''));
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            throw new AdminException('接口地址必须以 http:// 或 https:// 开头');
        }

        $headers = $this->normalizeHeaders($data['headers'] ?? '');

        return [
            'name'        => $name,
            'server_key'  => $serverKey,
            'url'         => $url,
            'api_key'     => trim((string)($data['api_key'] ?? '')),
            'headers'     => $headers === [] ? '' : json_encode($headers, JSON_UNESCAPED_UNICODE),
            'timeout'     => (int)max(3, min(120, (int)($data['timeout'] ?? 15))),
            'description' => trim((string)($data['description'] ?? '')),
            'status'      => (int)($data['status'] ?? 1) === 1 ? 1 : 0,
        ];
    }

    /**
     * 归一化附加请求头为键值对（表单可传数组或 JSON 字符串）
     * @param string|array|null $value 请求头输入
     * @return array<string,string>
     */
    private function normalizeHeaders($value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($value)) {
            return [];
        }
        $headers = [];
        foreach ($value as $name => $item) {
            $name = trim((string)$name);
            // 请求头名称必须是合法 token，避免换行注入
            if ($name === '' || !preg_match('/^[A-Za-z0-9-]+$/', $name)) {
                continue;
            }
            // 头值剔除 CR/LF，防止换行头注入
            $headers[$name] = str_replace(["\r", "\n"], '', trim((string)$item));
        }
        return $headers;
    }

    /**
     * 输出格式化：脱敏密钥、解析 JSON 列并补充工具计数
     * @param array $server Server 记录
     * @return array
     */
    private function format(array $server): array
    {
        $apiKey = (string)($server['api_key'] ?? '');
        $tools = json_decode((string)($server['tools'] ?? ''), true);
        $server['api_key'] = $apiKey !== '' ? self::SECRET_KEEP : '';
        $server['has_api_key'] = $apiKey !== '' ? 1 : 0;
        $server['headers'] = json_decode((string)($server['headers'] ?? ''), true) ?: new \stdClass();
        $server['tools'] = is_array($tools) ? $tools : [];
        $server['tool_count'] = count($server['tools']);
        $server['timeout'] = (int)($server['timeout'] ?? 15);
        $server['tools_sync_time'] = (int)($server['tools_sync_time'] ?? 0);
        $server['status'] = (int)($server['status'] ?? 0);
        $server['is_system'] = 0;
        return $server;
    }

    /**
     * 系统内置 MCP 虚拟行（不落库，id 固定为 0，工具实时取自 SystemMcpService）
     * @return array
     */
    private function systemRow(): array
    {
        /** @var SystemMcpService $systemMcp */
        $systemMcp = app()->make(SystemMcpService::class);
        return $systemMcp->serverRow();
    }
}
