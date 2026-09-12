# CRMChat 项目 AI 协作规则

> 本文件是本项目 AI 协作规则的**唯一权威来源（Single Source of Truth）**，适配所有主流 AI 开发工具（ZCode、Claude Code、Cursor、Windsurf、GitHub Copilot、OpenAI Codex、Gemini CLI、Cline、Aider 等）。
> 仓库根目录的各工具专属文件（`AGENTS.md`、`CLAUDE.md`、`.cursor/rules/*.mdc`、`.github/copilot-instructions.md`、`.windsurfrules`、`.clinerules`、`.aider.conf.yml`）只做转发指向本目录。**规则内容只在本文件维护，不要分散到其他文件。**
> 开始任何代码改动前，AI 助手必须先完整阅读本文件并严格遵循。

## 1. 项目概述

CRMChat 是基于 **ThinkPHP 6 + Swoole 4 + Redis + MySQL + Vue** 的独立高性能客服系统（CRMEB 出品，遵循木兰协议）。包含三个端：

| 端 | 说明 | 入口 |
|---|---|---|
| 管理后台 admin | 平台运营管理 | `http://域名/admin` |
| 客服端 kefu | 商家客服接待（PC + APP） | `http://域名/kefu` |
| 用户端 mobile | 访客咨询（H5/小程序/网页挂件） | 超链接/内嵌/二维码接入 |

## 2. 仓库结构

```
CRMChat/                        # 仓库根（git 根目录）
├── crmchat/                    # 后端 ThinkPHP6 + Swoole 应用（主要开发目录）
│   ├── app/
│   │   ├── controller/         # 控制器，按 admin / kefu / mobile 三端分包
│   │   ├── services/           # 业务逻辑层
│   │   ├── dao/                # 数据访问层
│   │   ├── models/             # 数据模型层
│   │   ├── validate/           # 表单验证器（按 chat / kefu / system 分包）
│   │   ├── jobs/               # 队列任务（think-queue）
│   │   ├── webscoket/          # WebSocket 处理（注意：目录名历史上就是 webscoket 拼写，勿"纠正"）
│   │   ├── http/middleware/    # 中间件，按 admin / kefu / mobile 分包
│   │   ├── lang/               # 多语言
│   │   └── Request.php         # 全局请求类（getMore 等）
│   ├── crmeb/                  # CRMEB 基础库：BaseServices / BaseModel / BaseDao、异常类、命令、工具服务
│   ├── config/                 # 配置（swoole.php、database.php、queue.php 等）
│   ├── route/                  # 路由：admin.php / kefu.php / mobile.php / route.php
│   ├── public/                 # Web 入口与静态资源（install/ 为安装向导）
│   ├── database/               # 数据库脚本
│   ├── .example.env            # 环境变量模板（真实 .env 不入库）
│   └── composer.json
├── template/admin/             # 管理后台前端（Vue 2 + iView，iview-admin 结构）
├── template/uniapp/            # 客服端 APP（uni-app）
├── help/docker/                # Docker 配置（nginx / php / mysql）
├── docker-compose.yml          # 本地环境编排
├── run.sh                      # Docker 环境管理脚本
├── update.sql / update_v1.1.sql # 版本升级 SQL
├── docs/                       # 项目文档（整理自官方 Gitee Wiki，含勘误与补充；索引见 docs/README.md）
└── .crmchat/                   # 本目录：AI 协作规则
```

## 3. 技术栈与运行环境（硬性约束）

- **PHP 7.1 ~ 7.4**（运行于 7.4 容器）。**禁止使用 PHP 8+ 语法**：`match` 表达式、枚举、构造器属性提升、`readonly`、命名参数、`never` 返回类型等。
- ThinkPHP 6 + `topthink/think-swoole` 3.x（Swoole 常驻内存）；MySQL 8.0（utf8mb4）；Redis 8。
- 管理后台前端：Vue 2 + iView；客服端 APP：uni-app。
- 不支持 Windows 环境直接运行。
- 数据表前缀为 `eb_`。

## 4. 本地开发与常用命令

### Docker 环境（推荐）

```bash
./run.sh install   # 首次部署（会清空数据，随后浏览器打开 http://localhost:8011 进入安装向导）
./run.sh start     # 启动容器
./run.sh restart   # 重启容器
./run.sh stop      # 停止容器
./run.sh logs      # 跟踪日志
```

| 服务 | 地址 | 备注 |
|---|---|---|
| HTTP（nginx→php-fpm） | http://localhost:8011 | 管理后台 /admin，客服后台 /kefu |
| WebSocket（swoole） | ws://localhost:20108 | 常驻内存进程 |
| MySQL | localhost:33061 | 库 `crmeb_chat`，用户 `crmchat` / `123456` |
| Redis | localhost:63791 | 密码 `123456` |

### think 命令（在 `crmchat/` 目录内执行）

```bash
php think make:dao kefu@chat/Foo       # 创建 Dao（app/dao/kefu/chat/FooDao.php）
php think make:service kefu@chat/Foo   # 创建 Service（app/services/kefu/chat/FooServices.php）
php think key    # 生成/写入应用 KEY（crmeb\command\Key）
```

> 注意：README 中写的 `make:services` 是旧写法，实际注册的命令是 `make:service`（单数）与 `make:dao`，见 `config/console.php`。

Composer 已配置阿里云镜像（见 `composer.json` repositories）。新增依赖前必须确认兼容 PHP 7.1~7.4。

## 5. 后端架构分层（必须遵守）

请求链路：**路由 → 中间件（鉴权/权限/日志）→ 控制器 → 验证器 → services → dao → models**

1. **控制器**只做参数接收与响应，不写业务逻辑；三端控制器分别继承对应端的 `app/controller/{admin|kefu|mobile}/AuthController.php`。
2. **业务逻辑**一律写在 `app/services/`，继承 `crmeb\basic\BaseServices`。
3. **数据访问**写在 `app/dao/`，继承 `crmeb\basic\BaseDao`。
4. **模型**放 `app/models/`，继承 `crmeb\basic\BaseModel`；**禁止使用 `Db::table()` 直接操作数据库**。
5. services 里只组合数据；**模型/Dao 里只写查询条件**，数据组装放到 services 层。
6. **表单验证**放 `app/validate/` 对应分包，不要在控制器里手写验证规则。
7. 获取请求参数统一用 `app\Request` 的 `getMore()`（支持默认值与列表解构），不要直接用 `$_GET/$_POST`。
8. JSON 响应统一使用控制器基类的 `$this->success(...)` / `$this->fail(...)`。
9. 错误处理统一抛异常：`AdminException`（后台）、`AuthException`（权限）等，见 `crmeb/exceptions/`，由统一异常处理器输出；错误码与提示语统一管理以便多语言。
10. **异步任务**放 `app/jobs/`，通过 think-queue 队列执行；Swoole 侧任务参考 `crmeb\services\SwooleTaskService`。
11. **WebSocket** 消息处理在 `app/webscoket/handler/`（AdminHandler / KefuHandler / UserHandler），公共逻辑在 `BaseHandler` / `Manager` / `Room` / `Response`。
12. 路由按端写在 `route/admin.php`、`route/kefu.php`、`route/mobile.php`；新接口先在对应路由文件注册。
13. 后台增删改查表单优先使用 form-builder（`crmeb\services\FormBuilder`）生成，不手写后台 CRUD 页面。

## 6. 编码规范（PSR-2 / PSR-4 基础上）

### 命名

| 对象 | 规则 | 示例 |
|---|---|---|
| 目录、配置参数 | 小写+下划线 | `chat_service` |
| 类文件/类名 | 大驼峰，类名与文件名一致，命名空间与路径一致 | `ChatServiceServices.php` |
| 方法、属性、变量 | 小驼峰 | `getUserName` |
| 控制器方法 | 小写字母+下划线 | `get_client_ip` |
| 常量、环境变量 | 大写+下划线 | `APP_DEBUG` |
| 数据表、字段 | 小写+下划线，带 `eb_` 前缀 | `eb_chat_service` |

### 语法

- 类属性和方法必须加访问修饰符（public/protected/private）。
- 类和方法的开始花括号独立成行；控制结构开始花括号与声明同行。
- 纯 PHP 文件省略结尾 `?>`。
- 优先使用 PHP 7 语法（`??`、数组解构等）。
- **所有类、方法必须有中文注释**（含 `@param` / `@return`），复杂逻辑、多状态处加行内注释——遵循现有文件的 CRMEB 头注释风格。

## 7. 前端规范

### template/admin（管理后台，Vue 2 + iView）

- 接口调用统一放 `src/api/`；页面放 `src/pages/`；公共组件放 `src/components/`；状态放 `src/store/`；路由放 `src/router/`。
- UI 组件统一使用 iView；后台表单优先对接后端 form-builder 生成的表单 JSON。
- 命令（在 `template/admin/` 下执行）：`npm run serve` 开发、`npm run build` 打包。

### template/uniapp（客服端 APP）

- 遵循 uni-app 规范，新页面需在 `pages.json` 注册；改动注意同时兼容 H5 与 App 端。

## 8. 数据库规范

- 表名 = `eb_` 前缀 + 小写下划线；字段同规则；禁用驼峰、中文、下划线开头。
- 字符集统一 `utf8mb4` / `utf8mb4_general_ci`。
- 结构变更通过 migration（think-migration）或升级 SQL 文件（组织方式参考 `update.sql` / `update_v1.1.sql`），SQL 中表名必须带 `eb_` 前缀。

## 9. 安全与禁区

- `crmchat/.env` 不入库（已 gitignore）；不要把任何真实密钥、密码写进代码或文档。
- 不要修改 `vendor/`、`runtime/`（自动生成目录）；不要删除或改动 `crmchat/public/install/install.lock`（删除会重新触发安装向导导致清库风险）。
- 不上传、不外发用户数据与聊天记录；测试环境数据库凭据仅限本地使用。
- 新增第三方依赖需评估许可证与 PHP 7.1~7.4 / Swoole 兼容性。

## 10. 改动后如何验证

- 普通 HTTP 接口由 php-fpm 承载，PHP 代码改动即时生效；**但 Swoole 是常驻内存进程，修改 WebSocket / 队列 / 启动期加载的代码后必须执行 `docker restart crmchat_swoole` 才会生效**（composer 依赖变更同理）。
- 语法检查：`php -l <文件>`（容器内 PHP 7.4 环境）。
- 日志排查：`./run.sh logs`、`docker logs -f crmchat_swoole`；TP 业务日志在 `crmchat/runtime/log/`。
- 前端改动在 `template/admin/` 下 `npm run serve` 自测；涉及接口的改动用 curl 或页面实操验证。

## 11. Git 提交

- 遵循 `.gitee/` 下的中文 ISSUE / PR 模板。
- commit message 用中文简述变更；一次提交只做一件事，不混入无关格式化改动。
- 不要提交 `.env`、`runtime/`、日志、`.DS_Store`。

## 12. AI 助手行为守则

1. 动手前先读目标模块的现有代码，理解三端分包与分层归属，**新代码放对层**。
2. 遵循项目既有风格（CRMEB 文件头、中文注释、命名），最小化改动，不顺手重构无关代码。
3. 不要"顺手修正"历史拼写（如 `webscoket` 目录名），改名会破坏现有引用。
4. 涉及数据库结构、路由、中间件、Swoole 配置的改动，需在回复中明确说明影响面与需要的重启/迁移步骤。
5. 无法从代码确认的行为（如线上部署方式），先说明假设，不要臆造。
