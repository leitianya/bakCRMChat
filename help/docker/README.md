# CRMChat Docker 部署指南

CRMChat 是基于 ThinkPHP 6 + Swoole + Redis + MySQL + Vue 开发的独立客服系统，本目录提供 Docker 一键部署所需的所有配套文件。

> compose 文件（`docker-compose.yml`）与管理脚本（`run.sh`）位于仓库根目录，
> 请在仓库根目录执行本指南中的命令。

## 一、环境要求

- Docker 20.10+（含 docker-compose 或 docker compose 插件）
- 可用端口：`8011`（Web）、`20108`（WebSocket）、`33061`（MySQL）、`63791`（Redis）、`9000`（php-fpm）

## 二、快速开始

```bash
# 1. 克隆项目
git clone https://gitee.com/ZhongBangKeJi/CRMChat.git
cd CRMChat

# 2. 一键安装并启动（自动生成 .env、清理旧数据、构建镜像）
./run.sh install

# 3. 浏览器打开安装向导完成安装
#    http://localhost:8011
```

安装向导中填写数据库与 Redis 连接信息（容器网络内部地址）：

| 服务 | 服务器地址 | 端口 | 数据库/库名 | 用户名 | 密码 |
|------|-----------|------|------------|--------|------|
| MySQL | mysql | 3306 | crmeb_chat | crmchat | 123456 |
| Redis | redis | 6379 | db 0 | - | 123456 |

安装完成后访问：

- 安装向导：http://localhost:8011/install
- 管理后台：http://localhost:8011/admin
- 客服后台：http://localhost:8011/kefu
- WebSocket：ws://localhost:20108（客服聊天长连接）

## 三、常用命令

```bash
./run.sh install   # 首次部署（清理数据并启动）
./run.sh start     # 启动容器
./run.sh restart   # 重启容器
./run.sh stop      # 停止容器
./run.sh delete    # 删除容器和数据（含 MySQL 数据）
./run.sh logs      # 查看日志
```

## 四、服务说明

| 容器 | 说明 | 端口 |
|------|------|------|
| crmchat_nginx | Web 入口，静态资源与 PHP 反代 | 8011 -> 80 |
| crmchat_php | PHP-FPM，处理 HTTP 请求 | 9000 -> 9000 |
| crmchat_swoole | think-swoole，提供 WebSocket 长连接与消息队列（CRMEB_CHAT） | 20108 -> 20108 |
| crmchat_mysql | MySQL 8.0 | 33061 -> 3306 |
| crmchat_redis | Redis | 63791 -> 6379 |

## 五、常见问题

### 1. MySQL 启动失败：data directory has files in it

MySQL 初始化要求数据目录为空。执行 `./run.sh install` 会自动清理 `help/docker/mysql/data`；
若仍失败，可手动清理：

```bash
./run.sh stop
rm -rf help/docker/mysql/data/*
./run.sh start
```

### 2. Swoole 容器反复重启

Swoole 启动依赖 MySQL 与 Redis，且首次部署前需要完成安装向导（生成 `.env` 与数据库表）。
请先完成安装，再观察 `docker logs -f crmchat_swoole`。

### 3. 客服聊天无法连接 WebSocket

- 确认 `crmchat_swoole` 容器运行中：`docker ps | grep swoole`
- 确认宿主机已放行 `20108` 端口
- 后台"系统设置"中的 WebSocket 地址应填写 `ws://宿主机IP:20108`（或通过 nginx 的 `/ws` 反代）

### 4. 重新安装

删除安装锁后重新访问安装向导：

```bash
rm -f crmchat/public/install/install.lock
```

更多细节（目录结构、数据持久化、容器内命令等）见同目录 [docker.md](docker.md)。
