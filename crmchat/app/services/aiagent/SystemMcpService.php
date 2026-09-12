<?php
// +----------------------------------------------------------------------
// | CRMChat [ CRMEB 出品客服系统 ]
// +----------------------------------------------------------------------
// | 系统内置 MCP：客服业务工具（客户查询/访问查询/话术/关键词自动回复）
// +----------------------------------------------------------------------

namespace app\services\aiagent;

use app\dao\chat\ChatAutoReplyDao;
use app\dao\chat\ChatServiceDao;
use app\dao\chat\ChatServiceDialogueRecordDao;
use app\dao\chat\ChatServiceRecordDao;
use app\dao\chat\ChatServiceSpeechcraftDao;
use app\dao\chat\ChatUserDao;
use app\dao\chat\user\ChatUserGroupDao;
use app\dao\chat\user\ChatUserLabelAssistDao;
use app\dao\chat\user\ChatUserLabelDao;
use app\dao\other\CategoryDao;
use app\dao\other\SiteStatisticsDao;
use app\services\chat\ChatServiceSpeechcraftCateServices;
use app\services\chat\user\ChatUserLabelAssistServices;

/**
 * 系统内置 MCP 服务（进程内直连，不走 HTTP）。
 *
 * 工具直接复用各业务 Dao 查询，对外形态与远程 MCP 工具一致
 * （definition 三件套 name/description/inputSchema，写工具携带 interaction=execute
 * 交给对话层确认卡），工具名由 McpToolService 统一加 `{server_key}.` 前缀。
 * 覆盖业务域：客户（查询/完善资料/打标/标签/分组）、访问与对话记录、话术（分类/查询/新增）、
 * 关键词自动回复、客服（查询/上下线/接待统计）、站点经营统计。
 * Class SystemMcpService
 * @package app\services\aiagent
 */
class SystemMcpService
{
    /**
     * 内置 MCP 的 Server 标识（工具命名空间前缀，远程 Server 禁止占用）
     */
    const SERVER_KEY = 'system';

    /**
     * 内置 MCP 展示名称
     */
    const SERVER_NAME = '系统内置';

    /**
     * @var ChatUserDao 客户 Dao
     */
    protected $userDao;

    /**
     * @var ChatServiceRecordDao 接待记录 Dao
     */
    protected $recordDao;

    /**
     * @var ChatServiceSpeechcraftDao 话术 Dao
     */
    protected $speechcraftDao;

    /**
     * @var ChatAutoReplyDao 关键词自动回复 Dao
     */
    protected $autoReplyDao;

    /**
     * @var ChatServiceDao 客服 Dao
     */
    protected $serviceDao;

    /**
     * @var ChatUserGroupDao 客户分组 Dao
     */
    protected $groupDao;

    /**
     * @var ChatUserLabelDao 客户标签 Dao
     */
    protected $labelDao;

    /**
     * @var ChatUserLabelAssistDao 客户标签关联 Dao
     */
    protected $labelAssistDao;

    /**
     * @var CategoryDao 分类 Dao（标签分类 type=0 与话术分类 type=1 同表）
     */
    protected $categoryDao;

    /**
     * @var ChatServiceDialogueRecordDao 对话消息 Dao
     */
    protected $dialogueRecordDao;

    /**
     * @var SiteStatisticsDao 站点访问统计 Dao
     */
    protected $siteStatisticsDao;

    /**
     * SystemMcpService constructor.
     * @param ChatUserDao $userDao
     * @param ChatServiceRecordDao $recordDao
     * @param ChatServiceSpeechcraftDao $speechcraftDao
     * @param ChatAutoReplyDao $autoReplyDao
     * @param ChatServiceDao $serviceDao
     * @param ChatUserGroupDao $groupDao
     * @param ChatUserLabelDao $labelDao
     * @param ChatUserLabelAssistDao $labelAssistDao
     * @param CategoryDao $categoryDao
     * @param ChatServiceDialogueRecordDao $dialogueRecordDao
     * @param SiteStatisticsDao $siteStatisticsDao
     */
    public function __construct(
        ChatUserDao $userDao,
        ChatServiceRecordDao $recordDao,
        ChatServiceSpeechcraftDao $speechcraftDao,
        ChatAutoReplyDao $autoReplyDao,
        ChatServiceDao $serviceDao,
        ChatUserGroupDao $groupDao,
        ChatUserLabelDao $labelDao,
        ChatUserLabelAssistDao $labelAssistDao,
        CategoryDao $categoryDao,
        ChatServiceDialogueRecordDao $dialogueRecordDao,
        SiteStatisticsDao $siteStatisticsDao
    ) {
        $this->userDao           = $userDao;
        $this->recordDao         = $recordDao;
        $this->speechcraftDao    = $speechcraftDao;
        $this->autoReplyDao      = $autoReplyDao;
        $this->serviceDao        = $serviceDao;
        $this->groupDao          = $groupDao;
        $this->labelDao          = $labelDao;
        $this->labelAssistDao    = $labelAssistDao;
        $this->categoryDao       = $categoryDao;
        $this->dialogueRecordDao = $dialogueRecordDao;
        $this->siteStatisticsDao = $siteStatisticsDao;
    }

    /**
     * 内置工具定义清单（原始工具名，不带命名空间前缀）
     * @return array<int, array{name:string,description:string,inputSchema:array,interaction?:string}>
     */
    public function definitions(): array
    {
        return [
            [
                'name'        => 'customer_search',
                'description' => '查询客服系统的客户（用户/联系人）列表，支持按昵称/手机号/客户ID模糊搜索，'
                    . '可按分组、标签、性别、是否游客、注册时段过滤，返回昵称、备注、手机号、分组、标签、备注说明、在线状态与建档时间',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'keyword'    => ['type' => 'string', 'description' => '搜索关键词，匹配客户昵称/手机号/客户ID（模糊）'],
                        'group_id'   => ['type' => 'integer', 'description' => '客户分组ID（可选，需先从查询结果中得知分组ID）'],
                        'label_id'   => ['type' => 'string', 'description' => '标签ID，多个用英文逗号分隔（可选，ID 来自 customer_label_search）'],
                        'sex'        => ['type' => 'integer', 'enum' => [0, 1, 2], 'description' => '性别：0=未知 1=男 2=女（可选）'],
                        'is_tourist' => ['type' => 'integer', 'enum' => [0, 1], 'description' => '是否游客：0=正式客户 1=游客（可选）'],
                        'time'       => ['type' => 'string', 'enum' => ['today', 'yesterday', 'week', 'last week', 'month', 'last month', 'year'], 'description' => '注册（建档）时间范围（可选）'],
                        'page'       => ['type' => 'integer', 'description' => '页码，默认 1'],
                        'limit'      => ['type' => 'integer', 'description' => '每页条数，默认 10，最大 50'],
                    ],
                ],
            ],
            [
                'name'        => 'visit_search',
                'description' => '查询客户的访问（咨询接待）记录：谁在什么时间咨询了哪位客服、最后消息内容与消息数，'
                    . '支持按客户昵称/客户ID/客服user_id过滤，可限定时间范围',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'keyword'    => ['type' => 'string', 'description' => '客户昵称或客户ID（模糊）'],
                        'user_id'    => ['type' => 'integer', 'description' => '客户ID（精确过滤某客户的访问记录）'],
                        'to_user_id' => ['type' => 'integer', 'description' => '客服的 user_id（过滤某客服接待的记录）'],
                        'is_tourist' => ['type' => 'integer', 'enum' => [0, 1], 'description' => '是否游客（可选）'],
                        'start_time' => ['type' => 'integer', 'description' => '开始时间 Unix 时间戳（可选，0 不限）'],
                        'end_time'   => ['type' => 'integer', 'description' => '结束时间 Unix 时间戳（可选，0 不限）'],
                        'page'       => ['type' => 'integer', 'description' => '页码，默认 1'],
                        'limit'      => ['type' => 'integer', 'description' => '每页条数，默认 10，最大 50'],
                    ],
                ],
            ],
            [
                'name'        => 'speechcraft_search',
                'description' => '查询客服话术库（公共话术），支持按标题或内容关键词搜索、按分类过滤，'
                    . '回答客户常见问题前可先检索是否已有现成话术',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'keyword' => ['type' => 'string', 'description' => '关键词，匹配话术标题或内容（模糊）'],
                        'cate_id' => ['type' => 'integer', 'description' => '话术分类ID（可选）'],
                        'page'    => ['type' => 'integer', 'description' => '页码，默认 1'],
                        'limit'   => ['type' => 'integer', 'description' => '每页条数，默认 10，最大 50'],
                    ],
                ],
            ],
            [
                'name'             => 'speechcraft_create',
                'description'      => '向客服话术库新增一条公共话术（标题+内容，可选分类与排序）。'
                    . '创建前先用 speechcraft_search 检查是否已有同内容话术，避免重复添加',
                'inputSchema'      => [
                    'type'       => 'object',
                    'required'   => ['title', 'message'],
                    'properties' => [
                        'title'   => ['type' => 'string', 'description' => '话术标题，50 字以内'],
                        'message' => ['type' => 'string', 'description' => '话术内容，255 字以内'],
                        'cate_id' => ['type' => 'integer', 'description' => '话术分类ID（可选，0 为未分类）'],
                        'sort'    => ['type' => 'integer', 'description' => '排序，数字越大越靠前（可选，默认 0）'],
                    ],
                ],
                'requires_confirm' => true,
                'interaction'      => 'execute',
            ],
            [
                'name'        => 'keyword_reply_search',
                'description' => '查询客服账号的关键词自动回复规则（命中关键字后自动回复的内容），'
                    . '支持按关键字模糊搜索、按客服过滤',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'keyword'    => ['type' => 'string', 'description' => '关键字（模糊匹配规则的关键字）'],
                        'to_user_id' => ['type' => 'integer', 'description' => '客服的 user_id（只看某客服的规则，可选）'],
                        'page'       => ['type' => 'integer', 'description' => '页码，默认 1'],
                        'limit'      => ['type' => 'integer', 'description' => '每页条数，默认 10，最大 50'],
                    ],
                ],
            ],
            [
                'name'             => 'keyword_reply_create',
                'description'      => '为指定客服账号新增一条关键词自动回复规则：客户消息命中关键字时自动回复设定内容。'
                    . '关键字支持多个（用英文逗号分隔）；创建前先用 keyword_reply_search 检查客服是否已配置同类规则',
                'inputSchema'      => [
                    'type'       => 'object',
                    'required'   => ['kefu', 'keyword', 'content'],
                    'properties' => [
                        'kefu'    => ['type' => 'string', 'description' => '目标客服：客服ID、账号或昵称（唯一命中才执行，多个命中时会列出候选）'],
                        'keyword' => ['type' => 'string', 'description' => '触发关键字，多个用英文逗号分隔'],
                        'content' => ['type' => 'string', 'description' => '命中后自动回复的内容'],
                        'sort'    => ['type' => 'integer', 'description' => '排序（可选，默认 0）'],
                    ],
                ],
                'requires_confirm' => true,
                'interaction'      => 'execute',
            ],
            [
                'name'        => 'speechcraft_cate_search',
                'description' => '查询客服话术库的分类列表（名称、排序及各分类下的话术数量），'
                    . '可配合 speechcraft_search 按分类检索话术',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => new \stdClass(),
                ],
            ],
            [
                'name'             => 'speechcraft_cate_create',
                'description'      => '在客服话术库中新增一个话术分类（用于整理话术）。'
                    . '创建前先用 speechcraft_cate_search 确认同名分类不存在',
                'inputSchema'      => [
                    'type'       => 'object',
                    'required'   => ['name'],
                    'properties' => [
                        'name' => ['type' => 'string', 'description' => '分类名称，如：售后话术'],
                        'sort' => ['type' => 'integer', 'description' => '排序，数字越大越靠前（可选，默认 0）'],
                    ],
                ],
                'requires_confirm' => true,
                'interaction'      => 'execute',
            ],
            [
                'name'        => 'customer_label_search',
                'description' => '查询客户标签列表（标签名、所属标签分类、使用该标签的客户数），'
                    . '标签ID可用于 customer_search 的 label_id 过滤',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'cate_id' => ['type' => 'integer', 'description' => '标签分类ID（可选，过滤某一分类下的标签）'],
                        'page'    => ['type' => 'integer', 'description' => '页码，默认 1'],
                        'limit'   => ['type' => 'integer', 'description' => '每页条数，默认 50，最大 100'],
                    ],
                ],
            ],
            [
                'name'        => 'customer_group_search',
                'description' => '查询客户分组列表（分组名及各分组下的客户数），'
                    . '分组ID可用于 customer_search 的 group_id 过滤',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => new \stdClass(),
                ],
            ],
            [
                'name'             => 'customer_update',
                'description'      => '完善/更新客户资料：可修改备注昵称、手机号、所属分组、备注说明。'
                    . '仅传入需要修改的字段；分组ID需先通过 customer_group_search 确认，'
                    . '修改前建议先用 customer_search 查出客户当前资料',
                'inputSchema'      => [
                    'type'       => 'object',
                    'required'   => ['id'],
                    'properties' => [
                        'id'              => ['type' => 'integer', 'description' => '客户ID'],
                        'remark_nickname' => ['type' => 'string', 'description' => '备注昵称（可选）'],
                        'phone'           => ['type' => 'string', 'description' => '手机号（可选，11 位）'],
                        'group_id'        => ['type' => 'integer', 'description' => '客户分组ID（可选）'],
                        'remarks'         => ['type' => 'string', 'description' => '客户备注说明（可选，255 字以内）'],
                    ],
                ],
                'requires_confirm' => true,
                'interaction'      => 'execute',
            ],
            [
                'name'        => 'kefu_search',
                'description' => '查询客服账号列表（账号、昵称、手机号、所属应用、在线状态、启用状态、分组），'
                    . '可按昵称搜索、按在线/启用状态过滤',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'keyword' => ['type' => 'string', 'description' => '客服昵称（模糊）'],
                        'online'  => ['type' => 'integer', 'enum' => [0, 1], 'description' => '在线状态：0=离线 1=在线（可选）'],
                        'status'  => ['type' => 'integer', 'enum' => [0, 1], 'description' => '启用状态：0=禁用 1=启用（可选）'],
                        'page'    => ['type' => 'integer', 'description' => '页码，默认 1'],
                        'limit'   => ['type' => 'integer', 'description' => '每页条数，默认 10，最大 50'],
                    ],
                ],
            ],
            [
                'name'             => 'kefu_online_set',
                'description'      => '设置客服的在线/离线状态（上线=开始接待，离线=暂停接待）。'
                    . '注意：此为手动置线，客服端实际连接建立或断开时状态会以真实连接为准刷新',
                'inputSchema'      => [
                    'type'       => 'object',
                    'required'   => ['kefu', 'online'],
                    'properties' => [
                        'kefu'   => ['type' => 'string', 'description' => '目标客服：客服ID、账号或昵称（唯一命中才执行，多个命中时会列出候选）'],
                        'online' => ['type' => 'integer', 'enum' => [0, 1], 'description' => '目标状态：0=离线 1=在线'],
                    ],
                ],
                'requires_confirm' => true,
                'interaction'      => 'execute',
            ],
            [
                'name'        => 'dialogue_search',
                'description' => '查询客户与客服之间的聊天消息记录（内容、方向、时间、消息类型），'
                    . '支持按内容关键词、时间范围过滤。可用于了解客户咨询内容、上下文回顾；'
                    . 'uid 可用 customer_search 结果的客户ID或 kefu_search 结果的 user_id',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'uid'        => ['type' => 'integer', 'description' => '会话参与方ID（chat_user id），查该客户/客服相关的全部消息'],
                        'msn'        => ['type' => 'string', 'description' => '消息内容关键词（模糊）'],
                        'start_time' => ['type' => 'integer', 'description' => '开始时间 Unix 时间戳（可选，0 不限）'],
                        'end_time'   => ['type' => 'integer', 'description' => '结束时间 Unix 时间戳（可选，0 不限）'],
                        'page'       => ['type' => 'integer', 'description' => '页码，默认 1'],
                        'limit'      => ['type' => 'integer', 'description' => '每页条数，默认 20，最大 50'],
                    ],
                ],
            ],
            [
                'name'             => 'customer_label_set',
                'description'      => '为客户设置或取消标签（打标/去标）。'
                    . '标签ID通过 customer_label_search 获取；修改前建议先用 customer_search 查看客户当前标签',
                'inputSchema'      => [
                    'type'       => 'object',
                    'required'   => ['customer_id'],
                    'properties' => [
                        'customer_id'      => ['type' => 'integer', 'description' => '客户ID'],
                        'add_label_ids'    => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => '要添加的标签ID列表（可选）'],
                        'remove_label_ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => '要取消的标签ID列表（可选）'],
                    ],
                ],
                'requires_confirm' => true,
                'interaction'      => 'execute',
            ],
            [
                'name'        => 'kefu_stats',
                'description' => '统计客服接待数据：按客服汇总时间段内的接待客户数、接待会话数、客户发来消息数、客服回复消息数。'
                    . '用于回答"哪个客服接待最多""客服工作量"类问题',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'time' => ['type' => 'string', 'enum' => ['today', 'yesterday', 'week', 'last week', 'month', 'last month', 'year'], 'description' => '统计时段，默认 today（今天）'],
                        'kefu' => ['type' => 'string', 'description' => '只看某个客服：客服ID、账号或昵称（可选）'],
                    ],
                ],
            ],
            [
                'name'        => 'site_stats',
                'description' => '站点经营统计：时间段内新增客户数（正式/游客）、咨询接待数、消息量、'
                    . '页面访问量（PV）与独立访客IP数、来访来源与地域 Top5。用于回答"今天/本周来了多少新客户""流量情况"类问题',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'time' => ['type' => 'string', 'enum' => ['today', 'yesterday', 'week', 'last week', 'month', 'last month', 'year'], 'description' => '统计时段，默认 today（今天）'],
                    ],
                ],
            ],
        ];
    }

    /**
     * 内置 MCP 的虚拟 Server 行（供 MCP 列表与连接器选项渲染，id 固定为 0）
     * @return array
     */
    public function serverRow(): array
    {
        $definitions = $this->definitions();
        return [
            'id'              => 0,
            'name'            => self::SERVER_NAME,
            'server_key'      => self::SERVER_KEY,
            'url'             => '',
            'api_key'         => '',
            'has_api_key'     => 0,
            'headers'         => new \stdClass(),
            'timeout'         => 0,
            'description'     => 'CRMChat 内置业务工具，随系统启用、进程内直连无需配置：客户查询/完善资料/打标/标签/分组、对话与访问记录、话术分类与话术查询/新增、关键词自动回复查询/新增、客服查询/上下线/接待统计、站点经营统计',
            'tools'           => $definitions,
            'tool_count'      => count($definitions),
            'tools_sync_time' => 0,
            'status'          => 1,
            'is_system'       => 1,
        ];
    }

    /**
     * 调用内置工具（入参即模型给定的 arguments）
     * @param string $tool 工具原始名（不带前缀）
     * @param array $arguments 工具入参
     * @return array 业务数据
     * @throws \RuntimeException 工具不存在或业务校验失败时抛出
     */
    public function call(string $tool, array $arguments): array
    {
        switch ($tool) {
            case 'customer_search':
                return $this->customerSearch($arguments);
            case 'customer_update':
                return $this->customerUpdate($arguments);
            case 'customer_label_search':
                return $this->customerLabelSearch($arguments);
            case 'customer_group_search':
                return $this->customerGroupSearch($arguments);
            case 'visit_search':
                return $this->visitSearch($arguments);
            case 'speechcraft_search':
                return $this->speechcraftSearch($arguments);
            case 'speechcraft_create':
                return $this->speechcraftCreate($arguments);
            case 'speechcraft_cate_search':
                return $this->speechcraftCateSearch($arguments);
            case 'speechcraft_cate_create':
                return $this->speechcraftCateCreate($arguments);
            case 'keyword_reply_search':
                return $this->keywordReplySearch($arguments);
            case 'keyword_reply_create':
                return $this->keywordReplyCreate($arguments);
            case 'kefu_search':
                return $this->kefuSearch($arguments);
            case 'kefu_online_set':
                return $this->kefuOnlineSet($arguments);
            case 'dialogue_search':
                return $this->dialogueSearch($arguments);
            case 'customer_label_set':
                return $this->customerLabelSet($arguments);
            case 'kefu_stats':
                return $this->kefuStats($arguments);
            case 'site_stats':
                return $this->siteStats($arguments);
            default:
                throw new \RuntimeException("未知的系统内置 MCP 工具：{$tool}");
        }
    }

    /**
     * 客户（用户）查询
     * @param array $args 工具入参
     * @return array
     */
    private function customerSearch(array $args): array
    {
        [$page, $limit] = $this->pageValue($args);
        $where = [];
        $keyword = trim((string)($args['keyword'] ?? ''));
        if ($keyword !== '') {
            $where['nickname'] = $keyword;
        }
        $groupId = (int)($args['group_id'] ?? 0);
        if ($groupId > 0) {
            $where['group_id'] = $groupId;
        }
        $labelId = trim((string)($args['label_id'] ?? ''));
        if ($labelId !== '') {
            $where['label_id'] = $labelId;
        }
        if (isset($args['sex']) && $args['sex'] !== '') {
            $where['sex'] = (int)$args['sex'];
        }
        if (isset($args['is_tourist']) && $args['is_tourist'] !== '') {
            $where['is_tourist'] = (int)$args['is_tourist'];
        }
        $time = trim((string)($args['time'] ?? ''));
        if ($time !== '') {
            // getUserModel 的 time 条件只接受区间字符串，这里把语义化时段换算为日期区间
            $where['time'] = $this->timeRange($time);
        }
        $list = $this->userDao->getUserModel($where)
            ->with(['groupOne', 'label'])
            ->page($page, $limit)
            ->order('id', 'desc')
            ->select()->toArray();
        $count = $this->userDao->getUserCount($where);

        $items = [];
        foreach ($list as $item) {
            $items[] = [
                'id'              => (int)$item['id'],
                'uid'             => (int)$item['uid'],
                'nickname'        => (string)$item['nickname'],
                'remark_nickname' => (string)$item['remark_nickname'],
                'phone'           => (string)$item['phone'],
                'group_id'        => (int)$item['group_id'],
                'group_name'      => (string)($item['groupOne']['group_name'] ?? ''),
                'labels'          => array_column(is_array($item['label'] ?? null) ? $item['label'] : [], 'label'),
                'remarks'         => (string)$item['remarks'],
                'online'          => (int)$item['online'],
                'is_tourist'      => (int)$item['is_tourist'],
                'sex'             => (int)$item['sex'],
                'last_ip'         => (string)$item['last_ip'],
                'create_time'     => (string)$item['create_time'],
            ];
        }
        return ['count' => $count, 'page' => $page, 'limit' => $limit, 'list' => $items];
    }

    /**
     * 完善客户信息（部分更新，仅处理传入字段）
     * @param array $args 工具入参
     * @return array
     * @throws \RuntimeException 客户不存在或未传可更新字段时抛出
     */
    private function customerUpdate(array $args): array
    {
        $id = (int)($args['id'] ?? 0);
        if ($id <= 0) {
            throw new \RuntimeException('请指定要完善的客户ID');
        }
        $customer = $this->userDao->get($id);
        if (!$customer) {
            throw new \RuntimeException("客户不存在：{$id}");
        }
        $data = [];
        foreach (['remark_nickname', 'phone', 'remarks'] as $field) {
            if (array_key_exists($field, $args)) {
                $data[$field] = trim((string)$args[$field]);
            }
        }
        if (array_key_exists('group_id', $args)) {
            $groupId = (int)$args['group_id'];
            if ($groupId > 0 && !$this->groupDao->get($groupId)) {
                throw new \RuntimeException("客户分组不存在：{$groupId}");
            }
            $data['group_id'] = $groupId;
        }
        if ($data === []) {
            throw new \RuntimeException('未提供任何需要修改的字段（可修改：remark_nickname/phone/group_id/remarks）');
        }
        if (isset($data['phone']) && $data['phone'] !== '' && !preg_match('/^1[3-9]\d{9}$/', $data['phone'])) {
            throw new \RuntimeException('手机号格式不正确');
        }
        if (isset($data['remarks']) && mb_strlen($data['remarks']) > 255) {
            throw new \RuntimeException('客户备注不能超过 255 字');
        }
        $this->userDao->update($id, $data);
        return ['id' => $id, 'updated' => array_keys($data)];
    }

    /**
     * 客户标签查询（含所属分类与使用客户数）
     * @param array $args 工具入参
     * @return array
     */
    private function customerLabelSearch(array $args): array
    {
        $page = max(1, (int)($args['page'] ?? 1));
        $limit = (int)($args['limit'] ?? 50);
        $limit = max(1, min(100, $limit));
        $where = [];
        $cateId = (int)($args['cate_id'] ?? 0);
        if ($cateId > 0) {
            $where['cate_id'] = $cateId;
        }
        $list = $this->labelDao->getDataList($where, ['*'], 'sort', $page, $limit);
        $count = $this->labelDao->count($where);
        $cateNames = $this->labelCateNames(array_filter(array_column($list, 'cate_id')));
        $useCounts = $this->labelAssistDao->countGroupByLabel();

        $items = [];
        foreach ($list as $item) {
            $cateId = (int)$item['cate_id'];
            $labelId = (int)$item['id'];
            $items[] = [
                'id'         => $labelId,
                'label'      => (string)$item['label'],
                'cate_id'    => $cateId,
                'cate_name'  => (string)($cateNames[$cateId] ?? ''),
                'sort'       => (int)$item['sort'],
                'use_count'  => (int)($useCounts[$labelId] ?? 0),
            ];
        }
        return ['count' => $count, 'page' => $page, 'limit' => $limit, 'list' => $items];
    }

    /**
     * 客户分组查询（含各组客户数）
     * @param array $args 工具入参
     * @return array
     */
    private function customerGroupSearch(array $args): array
    {
        $list = $this->groupDao->getDataList([], ['*'], 'id', 0, 0);
        $useCounts = $this->userDao->countGroupByGroupId();

        $items = [];
        foreach ($list as $item) {
            $groupId = (int)$item['id'];
            $items[] = [
                'id'         => $groupId,
                'group_name' => (string)$item['group_name'],
                'use_count'  => (int)($useCounts[$groupId] ?? 0),
            ];
        }
        return ['count' => count($items), 'list' => $items];
    }

    /**
     * 访问（咨询接待）记录查询
     * @param array $args 工具入参
     * @return array
     */
    private function visitSearch(array $args): array
    {
        [$page, $limit] = $this->pageValue($args);
        // delete=1：排除软删除记录（recordModel 约定键）
        $where = [
            'add_time_start' => (int)($args['start_time'] ?? 0),
            'add_time_end'   => (int)($args['end_time'] ?? 0),
            'delete'         => 1,
        ];
        $keyword = trim((string)($args['keyword'] ?? ''));
        if ($keyword !== '') {
            $where['title'] = $keyword;
        }
        $userId = (int)($args['user_id'] ?? 0);
        if ($userId > 0) {
            $where['user_id'] = $userId;
        }
        $toUserId = (int)($args['to_user_id'] ?? 0);
        if ($toUserId > 0) {
            $where['to_user_id'] = $toUserId;
        }
        if (isset($args['is_tourist']) && $args['is_tourist'] !== '') {
            $where['is_tourist'] = (int)$args['is_tourist'];
        }
        $list = $this->recordDao->recordModel($where, $page, $limit, ['thisUser', 'dialogueUser'])
            ->order('add_time', 'desc')
            ->select()->toArray();
        $count = $this->recordDao->recordCount($where);

        $items = [];
        foreach ($list as $item) {
            $customer = is_array($item['thisUser'] ?? null) ? $item['thisUser'] : [];
            $kefuUser = is_array($item['dialogueUser'] ?? null) ? $item['dialogueUser'] : [];
            $customerName = ($customer['remark_nickname'] ?? '') ?: ($customer['nickname'] ?? '') ?: (string)$item['nickname'];
            $items[] = [
                'id'                => (int)$item['id'],
                'customer_id'       => (int)$item['user_id'],
                'customer_nickname' => $customerName,
                'kefu_user_id'      => (int)$item['to_user_id'],
                'kefu_nickname'     => ($kefuUser['remark_nickname'] ?? '') ?: ($kefuUser['nickname'] ?? ''),
                'message'           => mb_substr((string)$item['message'], 0, 200),
                'message_type'      => (int)$item['message_type'],
                'message_num'       => (int)$item['mssage_num'],
                'is_tourist'        => (int)$item['is_tourist'],
                'visit_time'        => $this->formatTime($item['add_time']),
                'last_active_time'  => $this->formatTime($item['update_time']),
            ];
        }
        return ['count' => $count, 'page' => $page, 'limit' => $limit, 'list' => $items];
    }

    /**
     * 话术查询（公共话术库）
     * @param array $args 工具入参
     * @return array
     */
    private function speechcraftSearch(array $args): array
    {
        [$page, $limit] = $this->pageValue($args);
        $where = ['kefu_id' => 0];
        $keyword = trim((string)($args['keyword'] ?? ''));
        if ($keyword !== '') {
            $where['keyword'] = $keyword;
        }
        $cateId = (int)($args['cate_id'] ?? 0);
        if ($cateId > 0) {
            $where['cate_id'] = $cateId;
        }
        $list = $this->speechcraftDao->getSpeechcraftList($where, $page, $limit);
        $count = $this->speechcraftDao->count($where);
        $cateNames = $this->speechcraftCateNames(array_filter(array_column($list, 'cate_id')));

        $items = [];
        foreach ($list as $item) {
            $cateId = (int)$item['cate_id'];
            $items[] = [
                'id'         => (int)$item['id'],
                'title'      => (string)$item['title'],
                'message'    => (string)$item['message'],
                'cate_id'    => $cateId,
                'cate_name'  => (string)($cateNames[$cateId] ?? ''),
                'sort'       => (int)$item['sort'],
                'created_at' => $this->formatTime($item['add_time']),
            ];
        }
        return ['count' => $count, 'page' => $page, 'limit' => $limit, 'list' => $items];
    }

    /**
     * 话术新增（公共话术库）
     * @param array $args 工具入参
     * @return array
     * @throws \RuntimeException 内容重复或校验失败时抛出
     */
    private function speechcraftCreate(array $args): array
    {
        $title = trim((string)($args['title'] ?? ''));
        $message = trim((string)($args['message'] ?? ''));
        if ($title === '') {
            throw new \RuntimeException('话术标题不能为空');
        }
        if (mb_strlen($title) > 50) {
            throw new \RuntimeException('话术标题不能超过 50 字');
        }
        if ($message === '') {
            throw new \RuntimeException('话术内容不能为空');
        }
        if (mb_strlen($message) > 255) {
            throw new \RuntimeException('话术内容不能超过 255 字');
        }
        // 与后台管理口径一致：同一内容不重复入库
        if ($this->speechcraftDao->count(['message' => $message, 'kefu_id' => 0]) > 0) {
            throw new \RuntimeException('已存在相同内容的话术，请勿重复添加');
        }
        $res = $this->speechcraftDao->save([
            'title'    => $title,
            'message'  => $message,
            'cate_id'  => (int)($args['cate_id'] ?? 0),
            'sort'     => (int)($args['sort'] ?? 0),
            'add_time' => time(),
            'kefu_id'  => 0,
        ]);
        return ['id' => (int)($res->id ?? 0), 'title' => $title];
    }

    /**
     * 关键词自动回复查询
     * @param array $args 工具入参
     * @return array
     */
    private function keywordReplySearch(array $args): array
    {
        [$page, $limit] = $this->pageValue($args);
        $where = [];
        $keyword = trim((string)($args['keyword'] ?? ''));
        if ($keyword !== '') {
            $where['keyword'] = $keyword;
        }
        $toUserId = (int)($args['to_user_id'] ?? 0);
        if ($toUserId > 0) {
            $where['user_id'] = $toUserId;
        }
        $list = $this->autoReplyDao->getReply($where, $page, $limit);
        $count = $this->autoReplyDao->count($where);
        $nicknames = $this->serviceDao->getNicknameByUserIds(array_unique(array_column($list, 'user_id')));

        $items = [];
        foreach ($list as $item) {
            $userId = (int)$item['user_id'];
            $items[] = [
                'id'            => (int)$item['id'],
                'keyword'       => (string)$item['keyword'],
                'content'       => (string)$item['content'],
                'kefu_user_id'  => $userId,
                'kefu_nickname' => (string)($nicknames[$userId] ?? ''),
                'appid'         => (string)$item['appid'],
                'sort'          => (int)$item['sort'],
                'created_at'    => $this->formatTime($item['add_time']),
            ];
        }
        return ['count' => $count, 'page' => $page, 'limit' => $limit, 'list' => $items];
    }

    /**
     * 关键词自动回复新增（归属到指定客服账号）
     * @param array $args 工具入参
     * @return array
     * @throws \RuntimeException 客服无法唯一定位时抛出
     */
    private function keywordReplyCreate(array $args): array
    {
        $keyword = trim((string)($args['keyword'] ?? ''));
        $content = trim((string)($args['content'] ?? ''));
        $kefu = trim((string)($args['kefu'] ?? ''));
        if ($keyword === '') {
            throw new \RuntimeException('触发关键字不能为空');
        }
        if ($content === '') {
            throw new \RuntimeException('回复内容不能为空');
        }
        if ($kefu === '') {
            throw new \RuntimeException('请指定要配置关键词回复的客服');
        }
        $service = $this->resolveKefu($kefu);
        $res = $this->autoReplyDao->save([
            'keyword'  => $keyword,
            'content'  => $content,
            'user_id'  => (int)$service['user_id'],
            'appid'    => (string)$service['appid'],
            'sort'     => (int)($args['sort'] ?? 0),
            'add_time' => time(),
        ]);
        return [
            'id'            => (int)($res->id ?? 0),
            'kefu'          => (string)$service['nickname'],
            'kefu_user_id'  => (int)$service['user_id'],
            'keyword'       => $keyword,
        ];
    }

    /**
     * 话术分类查询（含各分类话术数量）
     * @param array $args 工具入参
     * @return array
     */
    private function speechcraftCateSearch(array $args): array
    {
        $where = ['type' => 1, 'owner_id' => 0];
        $names = $this->categoryDao->getColumn($where, 'name', 'id');
        $sorts = $this->categoryDao->getColumn($where, 'sort', 'id');
        $useCounts = $this->speechcraftDao->countGroupByCate();

        $items = [];
        foreach ($names as $cateId => $name) {
            $cateId = (int)$cateId;
            $items[] = [
                'id'             => $cateId,
                'name'           => (string)$name,
                'sort'           => (int)($sorts[$cateId] ?? 0),
                'speech_count'   => (int)($useCounts[$cateId] ?? 0),
            ];
        }
        usort($items, function ($a, $b) {
            return $b['sort'] <=> $a['sort'];
        });
        return ['count' => count($items), 'list' => $items];
    }

    /**
     * 话术分类新增
     * @param array $args 工具入参
     * @return array
     * @throws \RuntimeException 分类已存在或名称非法时抛出
     */
    private function speechcraftCateCreate(array $args): array
    {
        $name = trim((string)($args['name'] ?? ''));
        if ($name === '') {
            throw new \RuntimeException('分类名称不能为空');
        }
        if (mb_strlen($name) > 100) {
            throw new \RuntimeException('分类名称不能超过 100 字');
        }
        // 同类型下查重（getColumn 走精确 where，不依赖搜索器）
        if ($this->categoryDao->getColumn(['type' => 1, 'owner_id' => 0, 'name' => $name], 'id')) {
            throw new \RuntimeException("分类「{$name}」已存在，请勿重复添加");
        }
        $res = $this->categoryDao->save([
            'name'     => $name,
            'sort'     => (int)($args['sort'] ?? 0),
            'type'     => 1,
            'owner_id' => 0,
            'add_time' => time(),
        ]);
        return ['id' => (int)($res->id ?? 0), 'name' => $name];
    }

    /**
     * 客服查询
     * @param array $args 工具入参
     * @return array
     */
    private function kefuSearch(array $args): array
    {
        [$page, $limit] = $this->pageValue($args);
        $where = [];
        $keyword = trim((string)($args['keyword'] ?? ''));
        if ($keyword !== '') {
            $where['nickname'] = $keyword;
        }
        // online/status 搜索器无空值守卫，仅在明确传入时加入条件
        if (isset($args['online']) && $args['online'] !== '') {
            $where['online'] = (int)$args['online'];
        }
        if (isset($args['status']) && $args['status'] !== '') {
            $where['status'] = (int)$args['status'];
        }
        $list = $this->serviceDao->getServiceList($where, $page, $limit);
        $count = $this->serviceDao->count($where);

        $items = [];
        foreach ($list as $item) {
            $items[] = [
                'id'         => (int)$item['id'],
                'user_id'    => (int)$item['user_id'],
                'account'    => (string)$item['account'],
                'nickname'   => (string)$item['nickname'],
                'phone'      => (string)$item['phone'],
                'appid'      => (string)$item['appid'],
                'online'     => (int)$item['online'],
                'status'     => (int)$item['status'],
                'group_name' => (string)($item['chatgroup']['name'] ?? ''),
                'add_time'   => (string)$item['add_time'],
            ];
        }
        return ['count' => $count, 'page' => $page, 'limit' => $limit, 'list' => $items];
    }

    /**
     * 客服上线/下线（与 WebSocket 上下线处理同口径：客服账号、客服会话身份、接待记录三处同步）
     * @param array $args 工具入参
     * @return array
     * @throws \RuntimeException 客服无法唯一定位时抛出
     */
    private function kefuOnlineSet(array $args): array
    {
        $online = (int)($args['online'] ?? 0) === 1 ? 1 : 0;
        $kefu = trim((string)($args['kefu'] ?? ''));
        if ($kefu === '') {
            throw new \RuntimeException('请指定要设置上下线的客服');
        }
        $service = $this->resolveKefu($kefu);
        $userId = (int)$service['user_id'];
        if ($userId <= 0) {
            throw new \RuntimeException("客服「{$kefu}」未绑定会话账号，无法设置在线状态");
        }
        // 与 KefuHandler::online 保持一致的三表同步；WebSocket 消息推送不在进程内工具职责内
        $this->serviceDao->update(['user_id' => $userId], ['online' => $online]);
        $this->userDao->update(['id' => $userId], ['online' => $online]);
        $this->recordDao->update(['to_user_id' => $userId], ['online' => $online]);
        return [
            'kefu'          => (string)$service['nickname'],
            'kefu_user_id'  => $userId,
            'online'        => $online,
        ];
    }

    /**
     * 对话消息记录查询
     * @param array $args 工具入参
     * @return array
     */
    private function dialogueSearch(array $args): array
    {
        $page = max(1, (int)($args['page'] ?? 1));
        $limit = (int)($args['limit'] ?? 20);
        $limit = max(1, min(50, $limit));
        $where = [
            'uid'        => (int)($args['uid'] ?? 0),
            'msn'        => trim((string)($args['msn'] ?? '')),
            'start_time' => (int)($args['start_time'] ?? 0),
            'end_time'   => (int)($args['end_time'] ?? 0),
        ];
        // 新到旧排序，便于 AI 优先看最近对话
        $list = $this->dialogueRecordDao->dialogueModel($where, $page, $limit)
            ->order('add_time', 'desc')
            ->select()->toArray();
        $count = $this->dialogueRecordDao->dialogueCount($where);
        $names = $this->userDao->getNicknameMapByIds(array_merge(
            array_column($list, 'user_id'),
            array_column($list, 'to_user_id')
        ));

        $items = [];
        foreach ($list as $item) {
            $items[] = [
                'id'           => (int)$item['id'],
                'from_user_id' => (int)$item['user_id'],
                'from_name'    => (string)($names[(int)$item['user_id']] ?? ''),
                'to_user_id'   => (int)$item['to_user_id'],
                'to_name'      => (string)($names[(int)$item['to_user_id']] ?? ''),
                'message'      => mb_substr(strip_tags((string)$item['msn']), 0, 300),
                'message_type' => $this->msnTypeLabel((int)$item['msn_type']),
                'time'         => $this->formatTime($item['add_time']),
            ];
        }
        return ['count' => $count, 'page' => $page, 'limit' => $limit, 'list' => $items];
    }

    /**
     * 客户打标（设置/取消标签）
     * @param array $args 工具入参
     * @return array
     * @throws \RuntimeException 客户或标签不存在时抛出
     */
    private function customerLabelSet(array $args): array
    {
        $customerId = (int)($args['customer_id'] ?? 0);
        if ($customerId <= 0) {
            throw new \RuntimeException('请指定要打标的客户ID');
        }
        $customer = $this->userDao->get($customerId);
        if (!$customer) {
            throw new \RuntimeException("客户不存在：{$customerId}");
        }
        $addIds = array_values(array_filter(array_map('intval', (array)($args['add_label_ids'] ?? []))));
        $removeIds = array_values(array_filter(array_map('intval', (array)($args['remove_label_ids'] ?? []))));
        if (!$addIds && !$removeIds) {
            throw new \RuntimeException('请至少提供 add_label_ids 或 remove_label_ids 之一');
        }
        // 校验标签存在性，避免写入脏关联
        $existIds = array_map('intval', (array)$this->labelDao->getColumn([['id', 'in', array_merge($addIds, $removeIds)]], 'id'));
        foreach (array_merge($addIds, $removeIds) as $labelId) {
            if (!in_array($labelId, $existIds, true)) {
                throw new \RuntimeException("标签不存在：{$labelId}（可通过 customer_label_search 查询有效标签）");
            }
        }
        /** @var ChatUserLabelAssistServices $assistServices */
        $assistServices = app()->make(ChatUserLabelAssistServices::class);
        $assistServices->updateLabel([$customerId], $addIds, $removeIds);
        // 读取打标结果：assist 表存客户↔标签关联（chat_user_label.user_id 是标签创建者，不能用）
        $assistRows = $this->labelAssistDao->getDataList(['user_id' => $customerId], ['label_id'], 'label_id', 0, 0);
        $labelIds = array_map('intval', array_column((array)$assistRows, 'label_id'));
        $names = $labelIds ? (array)$this->labelDao->getColumn([['id', 'in', $labelIds]], 'label', 'id') : [];
        return [
            'customer_id' => $customerId,
            'added'       => $addIds,
            'removed'     => $removeIds,
            'labels'      => array_map('strval', array_values($names)),
        ];
    }

    /**
     * 客服接待统计
     * @param array $args 工具入参
     * @return array
     */
    private function kefuStats(array $args): array
    {
        $time = trim((string)($args['time'] ?? 'today'));
        [$start, $end] = $this->timeRangeTs($time);
        if (!$start) {
            throw new \RuntimeException("无法识别的统计时段：{$time}");
        }
        $receptions = $this->recordDao->receptionGroupByKefu($start, $end);
        $received = $this->dialogueRecordDao->messageGroupByToUser($start, $end);
        $sent = $this->dialogueRecordDao->messageGroupByUser($start, $end);

        // 汇总所有出现过的客服会话身份，仅保留客服（chat_service.user_id）
        $uids = array_unique(array_merge(array_keys($receptions), array_keys($received), array_keys($sent)));
        $nicknames = $this->serviceDao->getNicknameByUserIds($uids);
        $kefuFilter = trim((string)($args['kefu'] ?? ''));
        if ($kefuFilter !== '') {
            $service = $this->resolveKefu($kefuFilter);
            $nicknames = [(int)$service['user_id'] => (string)$service['nickname']];
        }

        $items = [];
        foreach ($nicknames as $userId => $nickname) {
            $userId = (int)$userId;
            $reception = is_array($receptions[$userId] ?? null) ? $receptions[$userId] : [];
            $items[] = [
                'kefu_user_id'       => $userId,
                'kefu_nickname'      => (string)$nickname,
                'customers'          => (int)($reception['customers'] ?? 0),
                'records'            => (int)($reception['records'] ?? 0),
                'received_messages'  => (int)($received[$userId] ?? 0),
                'sent_messages'      => (int)($sent[$userId] ?? 0),
            ];
        }
        usort($items, function ($a, $b) {
            return $b['customers'] <=> $a['customers'];
        });
        return [
            'time'  => $time,
            'range' => [date('Y-m-d H:i:s', $start), date('Y-m-d H:i:s', $end)],
            'count' => count($items),
            'list'  => $items,
        ];
    }

    /**
     * 站点经营统计
     * @param array $args 工具入参
     * @return array
     */
    private function siteStats(array $args): array
    {
        $time = trim((string)($args['time'] ?? 'today'));
        [$start, $end] = $this->timeRangeTs($time);
        if (!$start) {
            throw new \RuntimeException("无法识别的统计时段：{$time}");
        }
        $range = ['start_time' => $start, 'end_time' => $end];
        // 新增客户按建档时间口径（chat_user.create_time 为 datetime，走 getUserModel 的 time 条件）
        $customers = $this->userDao->getUserCount(['time' => $this->timeRange($time)]);
        $tourists = $this->userDao->getUserCount(['time' => $this->timeRange($time), 'is_tourist' => 1]);
        $visits = $this->siteStatisticsDao->visitStats(date('Y-m-d H:i:s', $start), date('Y-m-d H:i:s', $end));

        return [
            'time'  => $time,
            'range' => [date('Y-m-d H:i:s', $start), date('Y-m-d H:i:s', $end)],
            'customers' => [
                'new_total' => $customers,
                'tourist'   => $tourists,
                'formal'    => $customers - $tourists,
            ],
            'receptions' => $this->recordDao->recordCount(['add_time_start' => $start, 'add_time_end' => $end, 'delete' => 1]),
            'messages'   => $this->dialogueRecordDao->dialogueCount($range),
            'visits'     => [
                'pv'        => $visits['total'],
                'unique_ip' => $visits['ip_count'],
                'sources'   => array_column((array)$visits['sources'], 'total', 'source'),
                'provinces' => array_column((array)$visits['provinces'], 'total', 'province'),
            ],
        ];
    }

    /**
     * 消息类型编码转中文
     * @param int $type
     * @return string
     */
    private function msnTypeLabel(int $type): string
    {
        switch ($type) {
            case 1: return '文字';
            case 2: return '表情';
            case 3: return '图片';
            case 4: return '语音';
            case 5: return '商品';
            case 6: return '订单';
            default: return '其他';
        }
    }

    /**
     * 解析客服标识（ID/账号/昵称）为客服记录：唯一命中才返回，多个命中列出候选
     * @param string $kefu 客服标识
     * @return array 客服记录（id/appid/user_id/account/nickname）
     * @throws \RuntimeException 未找到或命中多条时抛出
     */
    private function resolveKefu(string $kefu): array
    {
        $list = $this->serviceDao->getKefuListByKeyword($kefu);
        if (!$list) {
            throw new \RuntimeException("未找到客服「{$kefu}」，请确认客服账号或昵称");
        }
        if (count($list) > 1) {
            $candidates = [];
            foreach ($list as $item) {
                $candidates[] = sprintf('ID:%d 账号:%s 昵称:%s', $item['id'], $item['account'], $item['nickname']);
            }
            throw new \RuntimeException('客服标识命中多条记录，请改用唯一的客服ID指定：' . implode('；', $candidates));
        }
        return $list[0];
    }

    /**
     * 话术分类ID到名称映射
     * @param array $cateIds
     * @return array<int,string> cate_id => name
     */
    private function speechcraftCateNames(array $cateIds): array
    {
        if (!$cateIds) {
            return [];
        }
        /** @var ChatServiceSpeechcraftCateServices $cateServices */
        $cateServices = app()->make(ChatServiceSpeechcraftCateServices::class);
        $cates = $cateServices->getColumn([['id', 'in', array_map('intval', array_unique($cateIds))]], 'name', 'id');
        return is_array($cates) ? $cates : [];
    }

    /**
     * 标签分类ID到名称映射
     * @param array $cateIds
     * @return array<int,string> cate_id => name
     */
    private function labelCateNames(array $cateIds): array
    {
        if (!$cateIds) {
            return [];
        }
        $cates = $this->categoryDao->getColumn([['id', 'in', array_map('intval', array_unique($cateIds))]], 'name', 'id');
        return is_array($cates) ? $cates : [];
    }

    /**
     * 语义化时段换算为 [起始Unix时间戳, 结束Unix时间戳]
     * @param string $time today/yesterday/week/last week/month/last month/year
     * @return array [int $start, int $end]，无法识别返回 [0, 0]
     */
    private function timeRangeTs(string $time): array
    {
        switch ($time) {
            case 'today':
                return [strtotime('today'), strtotime('today 23:59:59')];
            case 'yesterday':
                return [strtotime('yesterday'), strtotime('yesterday 23:59:59')];
            case 'week':
                return [strtotime('monday this week'), time()];
            case 'last week':
                return [strtotime('monday last week'), strtotime('sunday last week 23:59:59')];
            case 'month':
                return [strtotime('first day of this month'), time()];
            case 'last month':
                return [strtotime('first day of last month'), strtotime('last day of last month 23:59:59')];
            case 'year':
                return [strtotime('first day of january this year'), time()];
            default:
                return [0, 0];
        }
    }

    /**
     * 语义化时段换算为 getUserModel 可识别的日期区间（Y/m/d-Y/m/d H:i:s）
     * @param string $time today/yesterday/week/last week/month/last month/year
     * @return string 起始-结束区间，无法识别返回空串
     */
    private function timeRange(string $time): string
    {
        [$start, $end] = $this->timeRangeTs($time);
        if (!$start) {
            return '';
        }
        return date('Y/m/d', $start) . '-' . date('Y/m/d H:i:s', $end);
    }

    /**
     * 分页参数归一化（工具入参 → [page, limit]）
     * @param array $args 工具入参
     * @return array [int $page, int $limit]
     */
    private function pageValue(array $args): array
    {
        $page = max(1, (int)($args['page'] ?? 1));
        $limit = (int)($args['limit'] ?? 10);
        return [$page, max(1, min(50, $limit))];
    }

    /**
     * 时间值归一化为 Y-m-d H:i:s：兼容模型读出的 int 时间戳与已格式化的日期字符串
     * @param mixed $value
     * @return string
     */
    private function formatTime($value): string
    {
        if (is_numeric($value)) {
            return (int)$value > 0 ? date('Y-m-d H:i:s', (int)$value) : '';
        }
        return (string)$value;
    }
}
