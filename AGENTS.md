# AGENTS.md — AI 工具入口

本项目的 AI 协作规则**唯一来源**是 [`.crmchat/AGENTS.md`](.crmchat/AGENTS.md)，请先完整阅读并严格遵循其全部内容（项目概述、目录结构、PHP 7.1~7.4 硬性约束、架构分层 Controller → Services → Dao → Models、编码规范、安全禁区、Swoole 改动后重启验证等）。

各 AI 工具的专属入口文件（`CLAUDE.md`、`.cursor/rules/*.mdc`、`.github/copilot-instructions.md`、`.windsurfrules`、`.clinerules`、`.aider.conf.yml`）同样只做转发，规则请勿分散维护，一律在 `.crmchat/AGENTS.md` 修改。
