-- +----------------------------------------------------------------------
-- | CRMChat [ CRMEB 出品客服系统 ]
-- +----------------------------------------------------------------------
-- | AI Agent 智能体模块升级 SQL（移植自 CRMEB v8 aiagent 模块）
-- | 包含：AI 模型登记表、AI Agent 会话/消息/自动化任务表、MCP Server 注册表、后台菜单
-- | 全部语句幂等（CREATE TABLE IF NOT EXISTS / 菜单 NOT EXISTS 判断），可重复执行
-- | 执行方式：mysql -u<user> -p <库名> --default-character-set=utf8mb4 < update_aiagent.sql
-- +----------------------------------------------------------------------

-- ============ 1. AI 模型登记表 ============
CREATE TABLE IF NOT EXISTS `eb_ai_model` (
    `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '模型 ID',
    `name` varchar(100) NOT NULL DEFAULT '' COMMENT '模型显示名称',
    `protocol` varchar(50) NOT NULL DEFAULT 'openai_compatible' COMMENT '协议族（crmeb\\services\\ai\\AiProtocol 登记）',
    `provider` varchar(100) NOT NULL DEFAULT '' COMMENT '厂商',
    `model` varchar(150) NOT NULL DEFAULT '' COMMENT '模型标识（如 deepseek-chat）',
    `base_url` varchar(255) NOT NULL DEFAULT '' COMMENT 'OpenAI 兼容接口地址',
    `full_url` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '完整URL模式：1=请求地址已是完整接口路径，不再补全 /chat/completions',
    `api_key` varchar(255) NOT NULL DEFAULT '' COMMENT 'API Key',
    `supports_tools` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '是否支持工具调用：0否 1是',
    `supports_vision` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '是否支持图片理解：0否 1是',
    `context_window` int unsigned NOT NULL DEFAULT '0' COMMENT '上下文窗口（token，0=未知）',
    `max_tokens` int unsigned NOT NULL DEFAULT '2048' COMMENT '单次回复最大 token 数（0=不限制）',
    `temperature` decimal(3, 1) NOT NULL DEFAULT '0.7' COMMENT '采样温度（0-2）',
    `is_default` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '是否系统默认模型：0否 1是',
    `sort` int NOT NULL DEFAULT '0' COMMENT '排序（越大越优先）',
    `status` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '状态：0停用 1启用',
    `create_time` int unsigned NOT NULL DEFAULT '0' COMMENT '创建时间',
    `update_time` int unsigned NOT NULL DEFAULT '0' COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_status` (`status`, `is_default`),
    KEY `idx_sort` (`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='AI 模型登记表';

-- ============ 2. AI Agent 会话表 ============
CREATE TABLE IF NOT EXISTS `eb_ai_agent_conversation` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '会话 ID',
    `admin_id` int unsigned NOT NULL DEFAULT '0' COMMENT '管理员 ID',
    `title` varchar(120) NOT NULL DEFAULT '' COMMENT '会话标题',
    `is_pin` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '是否置顶：0否 1是',
    `create_time` int unsigned NOT NULL DEFAULT '0' COMMENT '创建时间',
    `update_time` int unsigned NOT NULL DEFAULT '0' COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_admin` (`admin_id`),
    KEY `idx_update_time` (`update_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='AI Agent 会话表';

-- ============ 3. AI Agent 消息表 ============
CREATE TABLE IF NOT EXISTS `eb_ai_agent_message` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '消息 ID',
    `conversation_id` bigint unsigned NOT NULL DEFAULT '0' COMMENT '会话 ID',
    `role` varchar(20) NOT NULL DEFAULT '' COMMENT '角色：user/assistant/tool/confirm',
    `content` longtext NULL COMMENT '消息内容',
    `tool_name` varchar(100) NOT NULL DEFAULT '' COMMENT '工具名称',
    `tool_payload` longtext NULL COMMENT '工具参数或结果 JSON',
    `create_time` int unsigned NOT NULL DEFAULT '0' COMMENT '创建时间',
    PRIMARY KEY (`id`),
    KEY `idx_conversation` (`conversation_id`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='AI Agent 消息表';

-- ============ 4. AI Agent 自动化任务表 ============
CREATE TABLE IF NOT EXISTS `eb_ai_agent_task` (
    `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '任务 ID',
    `name` varchar(100) NOT NULL DEFAULT '' COMMENT '任务名称',
    `admin_id` int unsigned NOT NULL DEFAULT '0' COMMENT '创建管理员 ID',
    `instruction` text NULL COMMENT '任务指令（无人值守时发给模型的完整执行指令）',
    `model_id` int unsigned NOT NULL DEFAULT '0' COMMENT '模型 ID（0=系统默认）',
    `server_ids` varchar(500) NOT NULL DEFAULT '' COMMENT '连接器 ID 列表 JSON（空=全部可用）',
    `skill_keys` varchar(500) NOT NULL DEFAULT '' COMMENT '挂载 Skill 标识 JSON',
    `autonomy` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '自主级别：1=只读 2=需审批（暂按只读执行） 3=全自动',
    `schedule_type` tinyint unsigned NOT NULL DEFAULT '2' COMMENT '调度类型：1=每隔N分钟 2=每天HH:MM 3=每周周几|HH:MM 4=每月几日|HH:MM 5=每年月|日|HH:MM',
    `schedule_value` varchar(100) NOT NULL DEFAULT '' COMMENT '调度值（分钟数 / HH:MM / 周几|HH:MM / 几日|HH:MM / 月|日|HH:MM）',
    `max_steps` int unsigned NOT NULL DEFAULT '10' COMMENT '单次运行最大工具调用轮数',
    `timeout` int unsigned NOT NULL DEFAULT '300' COMMENT '单次运行软超时（秒）',
    `notify_channels` varchar(200) NOT NULL DEFAULT '' COMMENT '完成通知渠道 JSON：is_email/is_ent_wechat',
    `notify_email` varchar(200) NOT NULL DEFAULT '' COMMENT '通知收件邮箱（is_email 渠道必填）',
    `status` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '状态：0停用 1启用',
    `last_run_time` int unsigned NOT NULL DEFAULT '0' COMMENT '上次运行时间',
    `next_run_time` int unsigned NOT NULL DEFAULT '0' COMMENT '下次运行时间',
    `create_time` int unsigned NOT NULL DEFAULT '0' COMMENT '创建时间',
    `update_time` int unsigned NOT NULL DEFAULT '0' COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_status_next` (`status`, `next_run_time`),
    KEY `idx_admin` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='AI Agent 自动化任务表';

-- ============ 5. AI Agent 任务运行记录表 ============
CREATE TABLE IF NOT EXISTS `eb_ai_agent_task_run` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '运行 ID',
    `task_id` int unsigned NOT NULL DEFAULT '0' COMMENT '任务 ID',
    `conversation_id` bigint unsigned NOT NULL DEFAULT '0' COMMENT '关联会话 ID（全过程审计回放）',
    `status` varchar(20) NOT NULL DEFAULT 'running' COMMENT '状态：running/success/failed/timeout',
    `trigger_type` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '触发方式：1=调度 2=手动立即执行',
    `trigger_time` int unsigned NOT NULL DEFAULT '0' COMMENT '调度触发时间点',
    `confirm_message_id` bigint unsigned NOT NULL DEFAULT '0' COMMENT '关联确认卡消息 ID（对话创建任务场景）',
    `summary` text NULL COMMENT '运行结果摘要（最终答复）',
    `error` varchar(1000) NOT NULL DEFAULT '' COMMENT '失败原因或兜底说明',
    `tool_calls` int unsigned NOT NULL DEFAULT '0' COMMENT '工具调用次数',
    `usage` varchar(1000) NOT NULL DEFAULT '' COMMENT 'token 用量 JSON',
    `started_at` int unsigned NOT NULL DEFAULT '0' COMMENT '开始时间',
    `finished_at` int unsigned NOT NULL DEFAULT '0' COMMENT '结束时间',
    PRIMARY KEY (`id`),
    KEY `idx_task` (`task_id`, `id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='AI Agent 任务运行记录表';

-- ============ 6. 远程 MCP Server 注册表 ============
CREATE TABLE IF NOT EXISTS `eb_ai_mcp_server` (
    `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'MCP Server ID',
    `name` varchar(100) NOT NULL DEFAULT '' COMMENT '服务显示名称',
    `server_key` varchar(64) NOT NULL DEFAULT '' COMMENT '服务标识（工具命名空间前缀）',
    `url` varchar(255) NOT NULL DEFAULT '' COMMENT 'MCP Server 地址（Streamable HTTP）',
    `api_key` varchar(255) NOT NULL DEFAULT '' COMMENT '访问密钥（X-Mcp-Key 请求头）',
    `headers` text NULL COMMENT '附加请求头 JSON',
    `timeout` int unsigned NOT NULL DEFAULT '15' COMMENT '请求超时（秒）',
    `tools` text NULL COMMENT '最近一次同步的工具清单 JSON',
    `tools_sync_time` int unsigned NOT NULL DEFAULT '0' COMMENT '工具同步时间',
    `description` varchar(500) NOT NULL DEFAULT '' COMMENT '备注说明',
    `status` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '状态：0停用，1启用',
    `create_time` int unsigned NOT NULL DEFAULT '0' COMMENT '创建时间',
    `update_time` int unsigned NOT NULL DEFAULT '0' COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_server_key` (`server_key`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='远程 MCP Server 注册表';

-- ============ 7. 后台菜单（一级入口 + 接口权限节点） ============
-- 一级菜单「AI Agent」：点击直接进入 AI 对话页（模型/MCP/Skill/任务管理在对话页内以遮盖层打开）
INSERT INTO `eb_system_menus` (`pid`, `icon`, `menu_name`, `module`, `controller`, `action`, `api_url`, `methods`, `params`, `sort`, `is_show`, `is_show_path`, `access`, `menu_path`, `path`, `auth_type`, `header`, `is_header`, `unique_auth`, `is_del`)
SELECT 0, 'md-aperture', 'AI Agent', 'admin', 'aiagent', 'index', '', '', '[]', 3, 1, 0, 1, '/admin/aiagent/index', '', 1, 'aiagent', 1, 'aiagent-manage', 0
WHERE NOT EXISTS (SELECT 1 FROM (SELECT 1 FROM `eb_system_menus` WHERE `menu_name` = 'AI Agent' AND `pid` = 0 AND `is_del` = 0 LIMIT 1) AS t);

SET @aiagent_pid = (SELECT `id` FROM `eb_system_menus` WHERE `menu_name` = 'AI Agent' AND `pid` = 0 AND `is_del` = 0 ORDER BY `id` ASC LIMIT 1);

-- 接口权限节点（auth_type=2，is_show=0：不进侧边栏，供角色授权使用）
-- 7.1 对话与会话
INSERT INTO `eb_system_menus` (`pid`, `menu_name`, `module`, `controller`, `action`, `api_url`, `methods`, `params`, `sort`, `is_show`, `is_show_path`, `access`, `auth_type`, `is_del`)
SELECT @aiagent_pid, m.a AS menu_name, 'admin' AS module, m.b AS controller, m.c AS action, m.d AS api_url, m.e AS methods, '[]' AS params, 0 AS sort, 0 AS is_show, 0 AS is_show_path, 1 AS access, 2 AS auth_type, 0 AS is_del FROM (
    SELECT 'AI 对话配置选项' AS a, 'aiagent' AS b, 'options' AS c, 'api/admin/aiagent/options' AS d, 'GET' AS e UNION ALL
    SELECT 'AI 对话会话列表', 'aiagent', 'conversations', 'api/admin/aiagent/conversations', 'GET' UNION ALL
    SELECT 'AI 对话消息记录', 'aiagent', 'messages', 'api/admin/aiagent/messages/<conversation_id>', 'GET' UNION ALL
    SELECT '删除 AI 对话会话', 'aiagent', 'deleteConversation', 'api/admin/aiagent/conversation/<id>', 'DELETE' UNION ALL
    SELECT '修改 AI 对话会话', 'aiagent', 'updateConversation', 'api/admin/aiagent/conversation/<id>', 'PUT' UNION ALL
    SELECT 'AI Agent 对话', 'aiagent', 'chat', 'api/admin/aiagent/chat', 'POST' UNION ALL
    SELECT 'AI Agent 流式对话', 'aiagent', 'chatStream', 'api/admin/aiagent/chat_stream', 'POST' UNION ALL
    SELECT 'AI Agent 待确认操作处理', 'aiagent', 'confirmStream', 'api/admin/aiagent/confirm_stream', 'POST'
) m
WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menus` x WHERE x.`menu_name` = m.a AND x.`api_url` = m.d AND x.`is_del` = 0);

-- 7.2 AI 自动化任务
INSERT INTO `eb_system_menus` (`pid`, `menu_name`, `module`, `controller`, `action`, `api_url`, `methods`, `params`, `sort`, `is_show`, `is_show_path`, `access`, `auth_type`, `is_del`)
SELECT @aiagent_pid, m.a AS menu_name, 'admin' AS module, m.b AS controller, m.c AS action, m.d AS api_url, m.e AS methods, '[]' AS params, 0 AS sort, 0 AS is_show, 0 AS is_show_path, 1 AS access, 2 AS auth_type, 0 AS is_del FROM (
    SELECT 'AI 自动化任务列表' AS a, 'aiagent' AS b, 'Task/index' AS c, 'api/admin/aiagent/tasks' AS d, 'GET' AS e UNION ALL
    SELECT '创建 AI 自动化任务', 'aiagent', 'Task/save', 'api/admin/aiagent/task', 'POST' UNION ALL
    SELECT 'AI 自动化任务详情', 'aiagent', 'Task/read', 'api/admin/aiagent/task/<id>', 'GET' UNION ALL
    SELECT '编辑 AI 自动化任务', 'aiagent', 'Task/save', 'api/admin/aiagent/task/<id>', 'PUT' UNION ALL
    SELECT '删除 AI 自动化任务', 'aiagent', 'Task/delete', 'api/admin/aiagent/task/<id>', 'DELETE' UNION ALL
    SELECT '修改 AI 自动化任务状态', 'aiagent', 'Task/status', 'api/admin/aiagent/task/<id>/status', 'PUT' UNION ALL
    SELECT '立即执行 AI 自动化任务', 'aiagent', 'Task/trigger', 'api/admin/aiagent/task/<id>/run', 'POST' UNION ALL
    SELECT 'AI 自动化任务运行列表', 'aiagent', 'Task/runs', 'api/admin/aiagent/task/<id>/runs', 'GET' UNION ALL
    SELECT 'AI 自动化任务全量运行记录', 'aiagent', 'Task/runIndex', 'api/admin/aiagent/task_runs', 'GET' UNION ALL
    SELECT 'AI 自动化任务运行详情', 'aiagent', 'Task/runDetail', 'api/admin/aiagent/task_run/<id>', 'GET'
) m
WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menus` x WHERE x.`menu_name` = m.a AND x.`api_url` = m.d AND x.`is_del` = 0);

-- 7.3 MCP Server 管理
INSERT INTO `eb_system_menus` (`pid`, `menu_name`, `module`, `controller`, `action`, `api_url`, `methods`, `params`, `sort`, `is_show`, `is_show_path`, `access`, `auth_type`, `is_del`)
SELECT @aiagent_pid, m.a AS menu_name, 'admin' AS module, m.b AS controller, m.c AS action, m.d AS api_url, m.e AS methods, '[]' AS params, 0 AS sort, 0 AS is_show, 0 AS is_show_path, 1 AS access, 2 AS auth_type, 0 AS is_del FROM (
    SELECT 'MCP Server 列表' AS a, 'aiagent' AS b, 'McpServer/index' AS c, 'api/admin/aiagent/mcp_servers' AS d, 'GET' AS e UNION ALL
    SELECT '创建 MCP Server', 'aiagent', 'McpServer/save', 'api/admin/aiagent/mcp_server', 'POST' UNION ALL
    SELECT 'MCP Server 详情', 'aiagent', 'McpServer/read', 'api/admin/aiagent/mcp_server/<id>', 'GET' UNION ALL
    SELECT '编辑 MCP Server', 'aiagent', 'McpServer/save', 'api/admin/aiagent/mcp_server/<id>', 'PUT' UNION ALL
    SELECT '删除 MCP Server', 'aiagent', 'McpServer/delete', 'api/admin/aiagent/mcp_server/<id>', 'DELETE' UNION ALL
    SELECT '修改 MCP Server 状态', 'aiagent', 'McpServer/status', 'api/admin/aiagent/mcp_server/<id>/status', 'PUT' UNION ALL
    SELECT '同步 MCP Server 工具', 'aiagent', 'McpServer/sync', 'api/admin/aiagent/mcp_server/<id>/sync', 'POST' UNION ALL
    SELECT '测试 MCP Server 连接', 'aiagent', 'McpServer/test', 'api/admin/aiagent/mcp_server/<id>/test', 'POST'
) m
WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menus` x WHERE x.`menu_name` = m.a AND x.`api_url` = m.d AND x.`is_del` = 0);

-- 7.4 Skill 管理
INSERT INTO `eb_system_menus` (`pid`, `menu_name`, `module`, `controller`, `action`, `api_url`, `methods`, `params`, `sort`, `is_show`, `is_show_path`, `access`, `auth_type`, `is_del`)
SELECT @aiagent_pid, m.a AS menu_name, 'admin' AS module, m.b AS controller, m.c AS action, m.d AS api_url, m.e AS methods, '[]' AS params, 0 AS sort, 0 AS is_show, 0 AS is_show_path, 1 AS access, 2 AS auth_type, 0 AS is_del FROM (
    SELECT 'Skill 列表' AS a, 'aiagent' AS b, 'Skill/index' AS c, 'api/admin/aiagent/skills' AS d, 'GET' AS e UNION ALL
    SELECT '创建 Skill', 'aiagent', 'Skill/save', 'api/admin/aiagent/skill', 'POST' UNION ALL
    SELECT 'Skill 详情', 'aiagent', 'Skill/read', 'api/admin/aiagent/skill/<key>', 'GET' UNION ALL
    SELECT '编辑 Skill', 'aiagent', 'Skill/update', 'api/admin/aiagent/skill/<key>', 'PUT' UNION ALL
    SELECT '删除 Skill', 'aiagent', 'Skill/delete', 'api/admin/aiagent/skill/<key>', 'DELETE' UNION ALL
    SELECT '修改 Skill 状态', 'aiagent', 'Skill/status', 'api/admin/aiagent/skill/<key>/status', 'PUT' UNION ALL
    SELECT 'Skill 附属资料列表', 'aiagent', 'Skill/attachmentList', 'api/admin/aiagent/skill/<key>/attachments', 'GET' UNION ALL
    SELECT 'Skill 附属资料详情', 'aiagent', 'Skill/attachmentRead', 'api/admin/aiagent/skill/<key>/attachment', 'GET' UNION ALL
    SELECT '保存 Skill 附属资料', 'aiagent', 'Skill/attachmentSave', 'api/admin/aiagent/skill/<key>/attachment', 'POST' UNION ALL
    SELECT '删除 Skill 附属资料', 'aiagent', 'Skill/attachmentDelete', 'api/admin/aiagent/skill/<key>/attachment', 'DELETE'
) m
WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menus` x WHERE x.`menu_name` = m.a AND x.`api_url` = m.d AND x.`is_del` = 0);

-- 7.5 AI 模型管理
INSERT INTO `eb_system_menus` (`pid`, `menu_name`, `module`, `controller`, `action`, `api_url`, `methods`, `params`, `sort`, `is_show`, `is_show_path`, `access`, `auth_type`, `is_del`)
SELECT @aiagent_pid, m.a AS menu_name, 'admin' AS module, m.b AS controller, m.c AS action, m.d AS api_url, m.e AS methods, '[]' AS params, 0 AS sort, 0 AS is_show, 0 AS is_show_path, 1 AS access, 2 AS auth_type, 0 AS is_del FROM (
    SELECT 'AI 模型列表' AS a, 'ai' AS b, 'AiModel/index' AS c, 'api/admin/ai/model/list' AS d, 'GET' AS e UNION ALL
    SELECT 'AI 协议族清单', 'ai', 'AiModel/protocols', 'api/admin/ai/model/protocols', 'GET' UNION ALL
    SELECT 'AI 模型详情', 'ai', 'AiModel/read', 'api/admin/ai/model/detail/<id>', 'GET' UNION ALL
    SELECT '新增 AI 模型', 'ai', 'AiModel/save', 'api/admin/ai/model/save', 'POST' UNION ALL
    SELECT '修改 AI 模型', 'ai', 'AiModel/update', 'api/admin/ai/model/update/<id>', 'PUT' UNION ALL
    SELECT '删除 AI 模型', 'ai', 'AiModel/delete', 'api/admin/ai/model/delete/<id>', 'DELETE' UNION ALL
    SELECT '修改 AI 模型状态', 'ai', 'AiModel/set_status', 'api/admin/ai/model/set_status/<id>', 'PUT' UNION ALL
    SELECT '设为默认 AI 模型', 'ai', 'AiModel/set_default', 'api/admin/ai/model/set_default/<id>', 'PUT' UNION ALL
    SELECT 'AI 模型连通性测试', 'ai', 'AiModel/test', 'api/admin/ai/model/test', 'POST'
) m
WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menus` x WHERE x.`menu_name` = m.a AND x.`api_url` = m.d AND x.`is_del` = 0);

-- ============ 8. 增量：一号通配置状态接口权限节点（2026-09-12） ============
SET @aiagent_pid = (SELECT `id` FROM `eb_system_menus` WHERE `menu_name` = 'AI Agent' AND `pid` = 0 AND `is_del` = 0 ORDER BY `id` ASC LIMIT 1);
INSERT INTO `eb_system_menus` (`pid`, `menu_name`, `module`, `controller`, `action`, `api_url`, `methods`, `params`, `sort`, `is_show`, `is_show_path`, `access`, `auth_type`, `is_del`)
SELECT @aiagent_pid, '一号通配置状态', 'ai', 'AiModel', 'yihaotong_status', 'api/admin/ai/model/yihaotong_status', 'GET', '[]', 0, 0, 0, 1, 2, 0
WHERE NOT EXISTS (SELECT 1 FROM (SELECT 1 FROM `eb_system_menus` WHERE `menu_name` = '一号通配置状态' AND `api_url` = 'api/admin/ai/model/yihaotong_status' AND `is_del` = 0 LIMIT 1) AS t);
