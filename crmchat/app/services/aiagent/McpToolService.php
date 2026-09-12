<?php
// +----------------------------------------------------------------------
// | CRMChat [ CRMEB 出品客服系统 ]
// +----------------------------------------------------------------------
// | AI Agent MCP 工具发现与调用服务
// +----------------------------------------------------------------------

namespace app\services\aiagent;

/**
 * 为 Agent 提供当前系统 MCP 工具的发现与调用能力。
 *
 * 工具来源有两类：
 * - 系统内置 MCP（server_key=system，虚拟 Server 固定排在首位）：客服业务工具进程内直连，
 *   由 SystemMcpService 提供定义与执行，不依赖 eb_ai_mcp_server 登记；
 * - 远程 Server：eb_ai_mcp_server 表中启用中的记录（与后台 MCP 管理页同源同权），
 *   工具名加 `{server_key}.` 前缀，调用统一经 RemoteMcpClient 走 JSON-RPC 2.0 over HTTP。
 *
 * 另有对话层虚拟工具（不注册到 MCP，由对话层拦截处理）：
 * - crmeb_ask_user：向用户提问，系统弹出选择卡，用户提交的答案作为工具结果回传
 * - crmeb_task_create / crmeb_task_list：自动化任务创建与查询（对话层拦截）
 * Class McpToolService
 * @package app\services\aiagent
 */
class McpToolService
{
    /**
     * 交付类需确认工具（消息推送能力）：无人值守的自动化任务中，
     * 这类工具允许跳过确认直接执行；当前内置工具均为业务读写类（确认卡约束），
     * 远端工具的交互声明以「同步工具」缓存的 interaction 字段为准
     */
    const DELIVERY_CONFIRM_TOOLS = [];

    /**
     * 用户问询虚拟工具名（不注册到 MCP，仅由对话层拦截处理）
     */
    const ASK_USER_TOOL = 'crmeb_ask_user';

    /**
     * 自动化任务创建虚拟工具名（不注册到 MCP，仅由对话层拦截处理，无人值守不下发）
     */
    const TASK_CREATE_TOOL = 'crmeb_task_create';

    /**
     * 自动化任务查询虚拟工具名（不注册到 MCP，仅由对话层拦截处理，无人值守不下发）
     */
    const TASK_LIST_TOOL = 'crmeb_task_list';

    /**
     * @var McpServerServices 远程 MCP Server 服务
     */
    protected $servers;

    /**
     * @var SystemMcpService 系统内置 MCP 服务
     */
    protected $systemMcp;

    /**
     * McpToolService constructor.
     * @param McpServerServices $servers
     * @param SystemMcpService $systemMcp
     */
    public function __construct(McpServerServices $servers, SystemMcpService $systemMcp)
    {
        $this->servers = $servers;
        $this->systemMcp = $systemMcp;
    }

    /**
     * 系统内置 MCP 是否可用：内置客服业务工具随系统常驻，恒为 true
     * @return bool
     */
    public function available(): bool
    {
        return true;
    }

    /**
     * 连接器选项（供对话页按 MCP 服务粒度选择）：系统内置 MCP 固定首位，其后为启用中的远程 Server
     * @return array<int, array{id:int,name:string,server_key:string,description:string,tool_count:int,is_system:int}>
     */
    public function serverOptions(): array
    {
        $options = [];
        foreach ($this->unifiedServers() as $server) {
            $options[] = [
                'id'          => (int)$server['id'],
                'name'        => (string)$server['name'],
                'server_key'  => (string)$server['server_key'],
                'description' => (string)($server['description'] ?? ''),
                'tool_count'  => count($this->serverTools($server)),
                'is_system'   => (int)($server['is_system'] ?? 0),
            ];
        }
        return $options;
    }

    /**
     * 按所选连接器聚合可调用工具定义（未选择任何连接器时返回空，表示本次对话不挂载工具）
     * @param array $serverIds 连接器 ID 列表
     * @param bool $unattended 无人值守（自动化任务）模式：不注册用户问询虚拟工具，
     *                          只读模式下剔除全部需确认写工具（交付类除外）
     * @param int $autonomy 自主级别：1=只读 2=审批（按只读降级） 3=全自动（保留全部工具）
     * @return array<int, array{name:string,description:string,inputSchema:array}>
     */
    public function definitionsForServers(array $serverIds, bool $unattended = false, int $autonomy = 1): array
    {
        $serverIds = array_values(array_unique(array_map('intval', $serverIds)));
        if ($serverIds === []) {
            return [];
        }
        $definitions = [];
        foreach ($this->unifiedServers() as $server) {
            if (in_array((int)$server['id'], $serverIds, true)) {
                foreach ($this->serverTools($server) as $tool) {
                    // 无人值守只读模式：需确认的写工具（交付类除外）从工具清单剔除，模型不可见，
                    // 幻觉调用将被白名单拒绝；交付类工具的收件目标由任务配置兜底
                    if ($unattended && $autonomy !== 3) {
                        $name = (string)$tool['name'];
                        $interaction = (string)($tool['interaction'] ?? '');
                        if ($interaction !== '' && !in_array($name, self::DELIVERY_CONFIRM_TOOLS, true)) {
                            continue;
                        }
                    }
                    $definitions[] = $tool;
                }
            }
        }
        $definitions = array_map([$this, 'decorateDefinition'], $definitions);
        // 无人值守没有可交互的用户，不注册问询虚拟工具，也不注册任务管理虚拟工具（防任务自我修改调度）
        if (!$unattended) {
            $definitions[] = $this->askUserDefinition();
            $definitions[] = $this->taskCreateDefinition();
            $definitions[] = $this->taskListDefinition();
        }
        return $definitions;
    }

    /**
     * 附加交互标记：带 interaction 声明的工具 requires_confirm=true；其余默认可直接执行
     * @param array $tool 工具定义
     * @return array
     */
    private function decorateDefinition(array $tool): array
    {
        $interaction = (string)($tool['interaction'] ?? '');
        $tool['requires_confirm'] = $interaction !== '';
        $tool['interaction'] = $interaction;
        return $tool;
    }

    /**
     * 用户问询虚拟工具定义：模型调用后系统弹出选择卡（单选/多选/文本），用户提交的答案作为工具结果回传。
     * 该工具不注册到 MCP，仅在对话层被拦截处理，永远不会真实执行。
     * @return array
     */
    private function askUserDefinition(): array
    {
        return [
            'name'             => self::ASK_USER_TOOL,
            'description'      => '向用户提问以获取决策或补充信息，系统以选择卡形式展示，用户提交后答案回传。'
                . '适用于：需要用户做主观选择、确认偏好、多步操作前请用户拍板；'
                . '业务实时数据请使用查询工具，不要用提问代替查询',
            'inputSchema'      => [
                'type'       => 'object',
                'required'   => ['question', 'type'],
                'properties' => [
                    'question'     => ['type' => 'string', 'description' => '用中文提出的问题，一次只问一个问题，表述具体明确'],
                    'type'         => ['type' => 'string', 'enum' => ['single', 'multi', 'text'], 'description' => 'single=单选 multi=多选 text=开放输入'],
                    'options'      => [
                        'type'        => 'array',
                        'description' => '选项列表（type 为 single/multi 时必填），2-4 个为宜，label 必须用中文',
                        'items'       => [
                            'type'       => 'object',
                            'properties' => [
                                'value' => ['type' => 'string', 'description' => '选项标识（传回模型的值）'],
                                'label' => ['type' => 'string', 'description' => '选项中文文案（展示给用户）'],
                            ],
                        ],
                    ],
                    'allow_custom' => ['type' => 'boolean', 'description' => '是否允许用户自定义输入，默认允许'],
                ],
            ],
            'requires_confirm' => true,
            'interaction'      => 'ask_user',
        ];
    }

    /**
     * 自动化任务创建虚拟工具定义：把用户的周期性诉求落成无人值守自动化任务，
     * 用户确认后真实创建（对话层拦截，不注册到 MCP，无人值守模式不下发）。
     * @return array
     */
    private function taskCreateDefinition(): array
    {
        return [
            'name'        => self::TASK_CREATE_TOOL,
            'description' => '创建周期性自动化任务（无人值守定时执行，到期自动完成并把结果推送给用户）。'
                . '仅适用于用户表达「每天/每周/每月定时自动做某事」的周期性诉求；'
                . '当场就能完成的一次性操作禁止使用本工具，直接执行即可。'
                . 'instruction 必须改写为无人值守可独立执行的完整指令：明确数据口径、时间范围用「昨日/上周」等相对量、'
                . '结果推送到哪个渠道与收件人；推送渠道为邮件时收件邮箱必填，未知时先用 crmeb_ask_user 询问用户',
            'inputSchema' => [
                'type'       => 'object',
                'required'   => ['name', 'instruction', 'schedule_type', 'schedule_value'],
                'properties' => [
                    'name'           => ['type' => 'string', 'description' => '任务名称，简短中文，如：每日经营数据推送'],
                    'instruction'    => ['type' => 'string', 'description' => '无人值守可独立执行的完整任务指令：做什么、数据口径与时间范围（相对量）、结果如何推送'],
                    'schedule_type'  => ['type' => 'integer', 'enum' => [1, 2, 3, 4, 5], 'description' => '调度类型：1=每隔N分钟 2=每天 3=每周 4=每月 5=每年'],
                    'schedule_value' => ['type' => 'string', 'description' => '调度值：类型1传分钟数（如 30）；类型2传 HH:MM（如 12:50）；类型3传 周几|HH:MM（如 1|09:00，1=周一）；类型4传 几日|HH:MM（如 1|09:00，仅 1-28 日）；类型5传 月|日|HH:MM（如 1|1|09:00）'],
                    'notify_channel' => ['type' => 'string', 'enum' => ['is_email', 'is_ent_wechat'], 'description' => '结果推送渠道：is_email=邮件（默认） is_ent_wechat=企业微信群机器人'],
                    'notify_email'   => ['type' => 'string', 'description' => '推送渠道为邮件时的收件邮箱（选邮件渠道时必填）'],
                ],
            ],
            'requires_confirm' => true,
            'interaction'      => 'execute',
        ];
    }

    /**
     * 自动化任务查询虚拟工具定义：查询当前管理员的任务清单（对话层拦截，只读直执行）
     * @return array
     */
    private function taskListDefinition(): array
    {
        return [
            'name'        => self::TASK_LIST_TOOL,
            'description' => '查询当前用户的自动化任务清单（名称/调度时间/启停状态/下次运行时间），用于回答「我有哪些定时任务」「之前设置的自动任务」类问题',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'page' => ['type' => 'integer', 'description' => '页码，默认 1，每页 20 条'],
                ],
            ],
        ];
    }

    /**
     * 调用工具：`system.{tool}` 走内置进程内分发，`{server_key}.{tool}` 经 RemoteMcpClient 转发
     * @param string $name 工具对外名称
     * @param array $arguments 工具入参
     * @param array $allowed 授权白名单
     * @return array 业务数据（MCP content.text 已解析为业务数组，便于上层直接读取 count/list 等业务字段）
     */
    public function call(string $name, array $arguments, array $allowed): array
    {
        if (!in_array($name, $allowed, true)) {
            throw new \RuntimeException("Agent 未授权调用 MCP 工具：{$name}");
        }
        $pos = strpos($name, '.');
        if ($pos === false) {
            throw new \RuntimeException("未识别的 MCP 工具：{$name}");
        }
        $serverKey = substr($name, 0, $pos);
        $toolName = substr($name, $pos + 1);
        // 系统内置 MCP：进程内直连，返回值即业务数据
        if ($serverKey === SystemMcpService::SERVER_KEY) {
            return $this->systemMcp->call($toolName, $arguments);
        }
        $server = $this->servers->findEnabledByKey($serverKey);
        if ($server === null) {
            throw new \RuntimeException("未识别的 MCP 工具：{$name}");
        }
        $result = RemoteMcpClient::fromServer($server)->callTool($toolName, $arguments);
        // MCP tools/call 返回 content.text（JSON 字符串），解析为业务数据数组，
        // 使工具结果形态统一，便于上层直接读取 count/list 等业务字段
        $text = '';
        foreach ($result['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= (string)($block['text'] ?? '');
            }
        }
        if ($text === '') {
            return $result;
        }
        $decoded = json_decode($text, true);
        return is_array($decoded) ? $decoded : ['text' => $text];
    }

    /**
     * 有效 Server 统一列表：系统内置 MCP 虚拟行固定首位，其后为启用中的远程 Server
     * @return array<int, array>
     */
    private function unifiedServers(): array
    {
        $builtin = $this->systemMcp->serverRow();
        $remote = $this->servers->getEnabledServers();
        foreach ($remote as &$server) {
            $server['is_system'] = 0;
        }
        return array_merge([$builtin], $remote);
    }

    /**
     * 单个 Server 已同步工具的对外形态（名称加 server_key 前缀）
     * @param array $server Server 原始记录（tools 为 JSON 字符串或定义数组）
     * @return array<int, array{name:string,description:string,inputSchema:array,interaction?:string}>
     */
    private function serverTools(array $server): array
    {
        $prefix = trim((string)($server['server_key'] ?? '')) . '.';
        $decoded = is_array($server['tools'] ?? null)
            ? $server['tools']
            : json_decode((string)($server['tools'] ?? ''), true);
        if (!is_array($decoded)) {
            return [];
        }
        $tools = [];
        foreach ($decoded as $tool) {
            $toolName = trim((string)($tool['name'] ?? ''));
            if ($toolName === '') {
                continue;
            }
            $item = [
                'name'        => $prefix . $toolName,
                'description' => (string)($tool['description'] ?? ''),
                'inputSchema' => is_array($tool['inputSchema'] ?? null)
                    ? $tool['inputSchema']
                    : ['type' => 'object', 'properties' => new \stdClass()],
            ];
            // 透传同步缓存中的交互声明（旧缓存无该字段则视为可直接执行，重新同步后生效）
            if ((string)($tool['interaction'] ?? '') !== '') {
                $item['interaction'] = (string)$tool['interaction'];
            }
            $tools[] = $item;
        }
        return $tools;
    }
}
