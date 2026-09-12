-- +----------------------------------------------------------------------
-- | CRMChat AI 写作接口配置（配合后台富文本编辑器 AI 写作功能）
-- | 内容：新增「AI 接口配置」配置分类及 OpenAI 兼容接口的配置项
-- | 全部语句幂等，可重复执行
-- | 执行方式（示例）：
-- |   mysql -u<user> -p --default-character-set=utf8mb4 <库名> < update_ai.sql
-- +----------------------------------------------------------------------

-- 配置分类：AI 接口配置（已存在则不重复插入）
INSERT INTO `eb_system_config_tab` (`pid`, `title`, `eng_title`, `status`, `info`, `icon`, `type`, `sort`)
SELECT 0, 'AI接口配置', 'ai_config', 1, 0, '', 0, 90 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_system_config_tab` WHERE `eng_title` = 'ai_config');

-- 取已存在的分类 id（不依赖 LAST_INSERT_ID，重复执行时同样取到正确值）
SET @ai_tab_id = (SELECT `id` FROM `eb_system_config_tab` WHERE `eng_title` = 'ai_config' LIMIT 1);

-- 配置项（接口地址 / API Key / 模型标识为必填展示项，其余为隐藏可选项）
-- 逐条判定，已存在的配置项跳过，不覆盖后台已填写的值
INSERT INTO `eb_system_config`
    (`menu_name`, `type`, `input_type`, `config_tab_id`, `parameter`, `upload_type`, `required`, `width`, `high`, `value`, `info`, `desc`, `sort`, `status`)
SELECT * FROM (SELECT 'ai_base_url' AS menu_name, 'text' AS `type`, 'input' AS input_type, @ai_tab_id AS config_tab_id, '' AS parameter, 0 AS upload_type, '接口地址' AS `required`, 100 AS width, 0 AS high, '"https://api.deepseek.com"' AS `value`, '接口地址' AS info, 'OpenAI 兼容接口地址，如 https://api.deepseek.com 或带 /v1 前缀地址，自动补全 /chat/completions' AS `desc`, 0 AS sort, 1 AS status) t WHERE NOT EXISTS (SELECT 1 FROM `eb_system_config` WHERE `menu_name` = 'ai_base_url')
UNION ALL SELECT * FROM (SELECT 'ai_api_key' AS menu_name, 'text' AS `type`, 'input' AS input_type, @ai_tab_id AS config_tab_id, '' AS parameter, 0 AS upload_type, 'API Key' AS `required`, 100 AS width, 0 AS high, '""' AS `value`, 'API Key' AS info, 'AI 服务密钥' AS `desc`, 0 AS sort, 1 AS status) t WHERE NOT EXISTS (SELECT 1 FROM `eb_system_config` WHERE `menu_name` = 'ai_api_key')
UNION ALL SELECT * FROM (SELECT 'ai_model' AS menu_name, 'text' AS `type`, 'input' AS input_type, @ai_tab_id AS config_tab_id, '' AS parameter, 0 AS upload_type, '模型标识' AS `required`, 100 AS width, 0 AS high, '""' AS `value`, '模型标识' AS info, '如 deepseek-chat、qwen-plus、glm-4 等' AS `desc`, 0 AS sort, 1 AS status) t WHERE NOT EXISTS (SELECT 1 FROM `eb_system_config` WHERE `menu_name` = 'ai_model')
UNION ALL SELECT * FROM (SELECT 'ai_temperature' AS menu_name, 'text' AS `type`, 'input' AS input_type, @ai_tab_id AS config_tab_id, '' AS parameter, 0 AS upload_type, '' AS `required`, 100 AS width, 0 AS high, '""' AS `value`, '采样温度' AS info, '可选，0~2，默认 0.7' AS `desc`, 0 AS sort, 2 AS status) t WHERE NOT EXISTS (SELECT 1 FROM `eb_system_config` WHERE `menu_name` = 'ai_temperature')
UNION ALL SELECT * FROM (SELECT 'ai_max_tokens' AS menu_name, 'text' AS `type`, 'input' AS input_type, @ai_tab_id AS config_tab_id, '' AS parameter, 0 AS upload_type, '' AS `required`, 100 AS width, 0 AS high, '""' AS `value`, '最大输出 Token' AS info, '可选，0 表示不限制' AS `desc`, 0 AS sort, 2 AS status) t WHERE NOT EXISTS (SELECT 1 FROM `eb_system_config` WHERE `menu_name` = 'ai_max_tokens')
UNION ALL SELECT * FROM (SELECT 'ai_timeout' AS menu_name, 'text' AS `type`, 'input' AS input_type, @ai_tab_id AS config_tab_id, '' AS parameter, 0 AS upload_type, '' AS `required`, 100 AS width, 0 AS high, '""' AS `value`, '接口超时秒数' AS info, '可选，默认 120' AS `desc`, 0 AS sort, 2 AS status) t WHERE NOT EXISTS (SELECT 1 FROM `eb_system_config` WHERE `menu_name` = 'ai_timeout')
UNION ALL SELECT * FROM (SELECT 'ai_full_url' AS menu_name, 'radio' AS `type`, 'input' AS input_type, @ai_tab_id AS config_tab_id, '1=>完整地址模式\n0=>自动补全' AS parameter, 0 AS upload_type, '' AS `required`, 100 AS width, 0 AS high, '""' AS `value`, '地址补全方式' AS info, '可选，1=地址即最终 /chat/completions 地址，0=自动补全' AS `desc`, 0 AS sort, 2 AS status) t WHERE NOT EXISTS (SELECT 1 FROM `eb_system_config` WHERE `menu_name` = 'ai_full_url')
UNION ALL SELECT * FROM (SELECT 'ai_protocol' AS menu_name, 'radio' AS `type`, 'input' AS input_type, @ai_tab_id AS config_tab_id, '1=>OpenAI兼容协议\n2=>一号通AI' AS parameter, 0 AS upload_type, '' AS `required`, 100 AS width, 0 AS high, '"1"' AS `value`, 'AI 协议' AS info, '一号通AI复用「一号通设置」的全局凭证；OpenAI兼容协议需填写下方接口地址与 API Key' AS `desc`, 0 AS sort, 1 AS status) t WHERE NOT EXISTS (SELECT 1 FROM `eb_system_config` WHERE `menu_name` = 'ai_protocol')
UNION ALL SELECT * FROM (SELECT 'yihaotong_appid' AS menu_name, 'text' AS `type`, 'input' AS input_type, @ai_tab_id AS config_tab_id, '' AS parameter, 0 AS upload_type, '' AS `required`, 100 AS width, 0 AS high, '""' AS `value`, '一号通 AppId' AS info, '由「一号通设置」登录成功后自动写入，无需手动填写' AS `desc`, 0 AS sort, 2 AS status) t WHERE NOT EXISTS (SELECT 1 FROM `eb_system_config` WHERE `menu_name` = 'yihaotong_appid')
UNION ALL SELECT * FROM (SELECT 'yihaotong_appsecret' AS menu_name, 'text' AS `type`, 'input' AS input_type, @ai_tab_id AS config_tab_id, '' AS parameter, 0 AS upload_type, '' AS `required`, 100 AS width, 0 AS high, '""' AS `value`, '一号通 AppSecret' AS info, '由「一号通设置」登录成功后自动写入，无需手动填写' AS `desc`, 0 AS sort, 2 AS status) t WHERE NOT EXISTS (SELECT 1 FROM `eb_system_config` WHERE `menu_name` = 'yihaotong_appsecret');

-- 后台菜单：系统设置 下新增「一号通设置」（已存在则不重复插入）
INSERT INTO `eb_system_menus`
    (`pid`, `module`, `menu_name`, `menu_path`, `unique_auth`, `sort`, `is_show`, `is_show_path`, `auth_type`, `is_del`)
SELECT 12, 'admin', '一号通设置', '/admin/setting/yihaotong', 'setting-yihaotong', 11, 1, 0, 1, 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menus` WHERE `unique_auth` = 'setting-yihaotong');
