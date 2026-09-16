-- +----------------------------------------------------------------------
-- | CRMChat AI 写作接口配置（配合后台富文本编辑器 AI 写作功能）
-- | 内容：AI 能力已统一由「AI Agent → 模型管理」提供，
--       清理旧版「AI 接口配置」中的 OpenAI 兼容接口配置项，
--       仅保留一号通凭证配置项（由「一号通设置」登录成功后自动写入）；
--       富文本 AI 写作改为优先使用模型管理的系统默认模型，回退内置一号通 AI
-- | 全部语句幂等，可重复执行
-- | 执行方式（示例）：
-- |   mysql -u<user> -p --default-character-set=utf8mb4 <库名> < update_ai.sql
-- +----------------------------------------------------------------------

-- 配置分类：AI 接口配置（已存在则不重复插入）
INSERT INTO `eb_system_config_tab` (`pid`, `title`, `eng_title`, `status`, `info`, `icon`, `type`, `sort`)
SELECT 0, 'AI接口配置', 'ai_config', 1, 0, '', 0, 90 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_system_config_tab` WHERE `eng_title` = 'ai_config');

SET @ai_tab_id = (SELECT `id` FROM `eb_system_config_tab` WHERE `eng_title` = 'ai_config' LIMIT 1);

-- 清理旧版 OpenAI 兼容接口配置项（AI 能力统一由「AI Agent → 模型管理」提供，后台表单同步移除）
DELETE FROM `eb_system_config`
WHERE `menu_name` IN ('ai_base_url', 'ai_api_key', 'ai_model', 'ai_temperature', 'ai_max_tokens', 'ai_timeout', 'ai_full_url', 'ai_protocol');

-- 模型登记表新增「额外请求参数」列：透传厂商私有参数（如阿里云 qwen3 系列的 enable_thinking），JSON 对象字符串
-- MySQL 8.0 不支持 ADD COLUMN IF NOT EXISTS，经 information_schema 判断后动态执行保证幂等
SET @has_extra_params = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'eb_ai_model' AND COLUMN_NAME = 'extra_params');
SET @ddl_extra_params = IF(@has_extra_params = 0,
    'ALTER TABLE `eb_ai_model` ADD COLUMN `extra_params` varchar(2000) DEFAULT NULL COMMENT ''额外请求参数（JSON 对象，原样合入对话请求体）'' AFTER `temperature`',
    'SELECT 1');
PREPARE stmt_extra_params FROM @ddl_extra_params;
EXECUTE stmt_extra_params;
DEALLOCATE PREPARE stmt_extra_params;

-- 一号通凭证配置项（由「一号通设置」登录成功后自动写入，无需手动填写；已存在则跳过）
INSERT INTO `eb_system_config`
    (`menu_name`, `type`, `input_type`, `config_tab_id`, `parameter`, `upload_type`, `required`, `width`, `high`, `value`, `info`, `desc`, `sort`, `status`)
SELECT * FROM (SELECT 'yihaotong_appid' AS menu_name, 'text' AS `type`, 'input' AS input_type, @ai_tab_id AS config_tab_id, '' AS parameter, 0 AS upload_type, '' AS `required`, 100 AS width, 0 AS high, '""' AS `value`, '一号通 AppId' AS info, '由「一号通设置」登录成功后自动写入，无需手动填写' AS `desc`, 0 AS sort, 2 AS status) t WHERE NOT EXISTS (SELECT 1 FROM `eb_system_config` WHERE `menu_name` = 'yihaotong_appid')
UNION ALL SELECT * FROM (SELECT 'yihaotong_appsecret' AS menu_name, 'text' AS `type`, 'input' AS input_type, @ai_tab_id AS config_tab_id, '' AS parameter, 0 AS upload_type, '' AS `required`, 100 AS width, 0 AS high, '""' AS `value`, '一号通 AppSecret' AS info, '由「一号通设置」登录成功后自动写入，无需手动填写' AS `desc`, 0 AS sort, 2 AS status) t WHERE NOT EXISTS (SELECT 1 FROM `eb_system_config` WHERE `menu_name` = 'yihaotong_appsecret');

-- 后台菜单：系统设置 下新增「一号通设置」（已存在则不重复插入）
INSERT INTO `eb_system_menus`
    (`pid`, `module`, `menu_name`, `menu_path`, `unique_auth`, `sort`, `is_show`, `is_show_path`, `auth_type`, `is_del`)
SELECT 12, 'admin', '一号通设置', '/admin/setting/yihaotong', 'setting-yihaotong', 11, 1, 0, 1, 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menus` WHERE `unique_auth` = 'setting-yihaotong');
