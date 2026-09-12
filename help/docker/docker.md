# CRMChat Docker 部署详细说明

本文档是 [README.md](README.md) 的补充，说明整体架构、目录映射、数据持久化与容器内常用命令。

## 一、架构说明

```
浏览器 / 客服端 APP
        │
        │  HTTP (8011)                 WebSocket (20108)
        ▼                              ▼
  ┌───────────┐  fastcgi 9000   ┌───────────┐
  │   nginx   │ ──────────────> │  php-fpm  │──┐
  └───────────┘                 └───────────┘  │  共享代码卷 ./crmchat
        │ /ws 反代                    ▲        │
        │                             └────────┘
        ▼
  ┌───────────┐   WebSocket + 消息队列（CRMEB_CHAT）
  │  swoole   │  think-swoole（php think swoole，守护进程）
  └───────────┘
        │
  ┌────┴─────┐
  │  mysql   │  业务数据
  │  redis   │  缓存 / 队列
  └──────────┘
```

- **HTTP 请求**：nginx -> php-fpm（ThinkPHP 6 应用）
- **长连接**：think-swoole 监听 `20108` 端口（`config/swoole.php` 中 `SWOOLE_PORT`），同时内置消息队列 worker（`CRMEB_CHAT`），无需单独的队列进程
- **注意**：`config/swoole.php` 以守护进程模式（`daemonize=true`）运行，compose 中通过 `tail -f runtime/swoole.log` 保持容器前台运行

## 二、目录结构

```
CRMChat/
├── docker-compose.yml        # compose 编排文件（根目录）
├── run.sh                    # 管理脚本（根目录）
└── help/docker/              # Docker 配套文件
    ├── php/Dockerfile        # PHP 7.4 + swoole-4.8 + redis 扩展
    ├── nginx/vhost.conf      # nginx 站点配置（含 /ws 反代）
    ├── mysql/data            # MySQL 数据目录（必须为空目录用于初始化）
    ├── mysql/log             # MySQL 日志
    ├── nginx/log             # nginx 日志
    ├── README.md             # 快速开始
    └── docker.md             # 本文档
```

## 三、目录映射说明

| 本地路径 | 容器路径 | 用途 | 注意事项 |
|---------|---------|------|---------|
| `./crmchat` | `/var/www` | 应用代码（ThinkPHP 项目根） | nginx 与 php-fpm、swoole 共享挂载 |
| `./crmchat/runtime` | `/var/www/runtime` | 缓存、日志、swoole.pid 等 | 需要可写权限 |
| `./help/docker/mysql/data` | `/var/lib/mysql` | MySQL 数据 | 初始化时必须为空 |
| `./help/docker/mysql/log` | `/var/log/mysql` | MySQL 日志 | 需要可写权限 |
| `./help/docker/nginx/vhost.conf` | `/etc/nginx/conf.d/default.conf` | nginx 站点配置 | 修改后需重启 nginx 容器 |
| `./help/docker/nginx/log` | `/etc/nginx/log` | nginx 日志 | 需要可写权限 |

自动创建目录：

```bash
mkdir -p help/docker/mysql/data help/docker/mysql/log help/docker/nginx/log
```

## 四、环境变量与配置

- `.env`：首次执行 `./run.sh install` 时自动从 `crmchat/.example.env` 复制生成，
  也可手动复制后修改（数据库连接在安装向导完成后自动写入）
- `SWOOLE_PORT`：swoole 监听端口，默认 `20108`（见 `crmchat/config/swoole.php`）
- MySQL 初始库：`crmeb_chat`（与 `.example.env` 中 `DATABASE` 一致），普通用户 `crmchat`，root 与用户密码均为 `123456`

## 五、数据持久化

MySQL 数据已通过 `./help/docker/mysql/data` 目录持久化；上传文件位于 `./crmchat/public/uploads`，
随代码目录一同持久化。如需重建容器但保留数据，使用：

```bash
./run.sh stop       # 仅停止，数据保留
./run.sh start      # 再次启动
```

如需彻底重置（删除全部数据并重新安装）：

```bash
./run.sh delete
./run.sh install
```

## 六、容器内常用命令

```bash
# 进入 php 容器
docker exec -it crmchat_php bash

# 进入 swoole 容器
docker exec -it crmchat_swoole bash

# 命令行一键安装（也可用安装向导）
docker exec -it crmchat_php php think install start

# 查看 swoole 运行日志
docker exec -it crmchat_swoole tail -f runtime/swoole.log

# 重启 swoole 服务（容器内）
docker exec -it crmchat_swoole php think swoole

# 消息队列独立运行（swoole 内已集成，仅调试时使用）
docker exec -it crmchat_php php think queue:listen --queue CRMEB_CHAT

# 查看各容器日志
docker logs -f crmchat_nginx
docker logs -f crmchat_php
docker logs -f crmchat_swoole
docker logs -f crmchat_mysql
```

## 七、常见错误及解决方案

### 7.1 MySQL 启动失败

**错误现象**：MySQL 容器启动失败，日志显示 `--initialize specified but the data directory has files in it. Aborting.`

**原因**：MySQL 数据目录不为空，导致初始化失败。

**解决方案**：

```bash
./run.sh stop
rm -rf help/docker/mysql/data/*
./run.sh start
```

### 7.2 页面报错：缺少 swoole 扩展

**原因**：未使用 `--build` 构建镜像，或镜像内未安装 swoole。

**解决方案**：

```bash
docker compose -f docker-compose.yml build --no-cache phpfpm
./run.sh restart
```

`help/docker/php/Dockerfile` 已安装 CRMChat 必需的扩展：`swoole-4.8.13`、`redis`、
`bcmath`、`gd`、`pdo_mysql`、`mysqli`、`zip`、`mbstring`、`sockets`、`pcntl`
（`fileinfo`、`curl`、`openssl` 随 php 官方镜像内置）。

### 7.3 swoole 容器反复重启

**原因**：MySQL/Redis 未就绪，或尚未完成安装（无 `.env`、无数据库表）。

**解决方案**：先完成安装向导，确认 mysql、redis 容器健康后，
观察 `docker logs -f crmchat_swoole` 定位具体报错。

### 7.4 端口冲突

宿主机端口 `8011`、`20108`、`33061`、`63791`、`9000` 被占用时，
修改 `docker-compose.yml` 中对应的宿主机端口映射（冒号左侧）即可。
