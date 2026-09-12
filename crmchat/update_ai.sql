-- +----------------------------------------------------------------------
-- | CRMChat AI 写作接口配置（配合后台富文本编辑器 AI 写作功能）
-- | 内容：新增「AI 接口配置」配置分类及 OpenAI 兼容接口的配置项
-- | 执行方式（示例）：
-- |   mysql -u<user> -p --default-character-set=utf8mb4 <库名> < update_ai.sql
-- +----------------------------------------------------------------------

-- 配置分类：AI 接口配置
INSERT INTO `eb_system_config_tab` (`pid`, `title`, `eng_title`, `status`, `info`, `icon`, `type`, `sort`)
VALUES (0, 'AI接口配置', 'ai_config', 1, 0, '', 0, 90);

-- 配置项（接口地址 / API Key / 模型标识为必填展示项，其余为隐藏可选项）
SET @ai_tab_id = LAST_INSERT_ID();

INSERT INTO `eb_system_config`
    (`menu_name`, `type`, `input_type`, `config_tab_id`, `parameter`, `upload_type`, `required`, `width`, `high`, `value`, `info`, `desc`, `sort`, `status`)
VALUES
    ('ai_base_url', 'text', 'input', @ai_tab_id, '', 0, '接口地址', 100, 0, '"https://api.deepseek.com"', '接口地址', 'OpenAI 兼容接口地址，如 https://api.deepseek.com 或带 /v1 前缀地址，自动补全 /chat/completions', 0, 1),
    ('ai_api_key', 'text', 'input', @ai_tab_id, '', 0, 'API Key', 100, 0, '""', 'API Key', 'AI 服务密钥', 0, 1),
    ('ai_model', 'text', 'input', @ai_tab_id, '', 0, '模型标识', 100, 0, '""', '模型标识', '如 deepseek-chat、qwen-plus、glm-4 等', 0, 1),
    ('ai_temperature', 'text', 'input', @ai_tab_id, '', 0, '', 100, 0, '""', '采样温度', '可选，0~2，默认 0.7', 0, 2),
    ('ai_max_tokens', 'text', 'input', @ai_tab_id, '', 0, '', 100, 0, '""', '最大输出 Token', '可选，0 表示不限制', 0, 2),
    ('ai_timeout', 'text', 'input', @ai_tab_id, '', 0, '', 100, 0, '""', '接口超时秒数', '可选，默认 120', 0, 2),
    ('ai_full_url', 'radio', 'input', @ai_tab_id, '1=>完整地址模式\n0=>自动补全', 0, '', 100, 0, '""', '地址补全方式', '可选，1=地址即最终 /chat/completions 地址，0=自动补全', 0, 2),
    ('ai_protocol', 'radio', 'input', @ai_tab_id, '1=>OpenAI兼容协议\n2=>一号通AI', 0, '', 100, 0, '1', 'AI 协议', '一号通AI复用「一号通设置」的全局凭证；OpenAI兼容协议需填写下方接口地址与 API Key', 0, 1),
    ('yihaotong_appid', 'text', 'input', @ai_tab_id, '', 0, '', 100, 0, '""', '一号通 AppId', '由「一号通设置」登录成功后自动写入，无需手动填写', 0, 2),
    ('yihaotong_appsecret', 'text', 'input', @ai_tab_id, '', 0, '', 100, 0, '""', '一号通 AppSecret', '由「一号通设置」登录成功后自动写入，无需手动填写', 0, 2);

-- 后台菜单：系统设置 下新增「一号通设置」
INSERT INTO `eb_system_menus`
    (`pid`, `module`, `menu_name`, `menu_path`, `unique_auth`, `sort`, `is_show`, `is_show_path`, `auth_type`, `is_del`)
VALUES
    (12, 'admin', '一号通设置', '/admin/setting/yihaotong', 'setting-yihaotong', 11, 1, 0, 1, 0);
