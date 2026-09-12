# Docker 部署（本项目本地/测试环境）

> 官方 wiki 的安装文档基于宝塔面板（见[PHP设置](./PHP设置.md)、[站点配置](./站点配置.md)、[程序安装](./程序安装.md)）。本项目仓库自带一套 Docker 编排（nginx + php-fpm 7.4 + swoole + MySQL 8 + Redis），适合本地开发与测试环境，无需宝塔。架构与目录映射的深入说明见 [`help/docker/docker.md`](../../help/docker/docker.md)。

## 环境要求

- Docker 与 docker-compose（v1 或 v2 插件均可，`run.sh` 自动检测）
- 首次构建需联网拉取镜像（php 镜像基于 `help/docker/php/Dockerfile` 本地构建：PHP 7.4 + swoole 4.8 + redis 扩展）

## 快速开始

在仓库根目录执行：

```bash
./run.sh install   # 首次部署（会清理旧数据并自动创建 .env）
```

然后浏览器打开安装向导完成初始化：<http://localhost:8011/install>（安装界面填写项参见[程序安装](./程序安装.md)，数据库/Redis 信息按下表填写即可）。

日常管理：

```bash
./run.sh start     # 启动容器
./run.sh restart   # 重启容器
./run.sh stop      # 停止容器（数据保留）
./run.sh logs      # 跟踪日志
./run.sh delete    # 删除容器和数据（危险）
```

## 服务与端口

| 服务 | 容器名 | 本地地址 | 说明 |
|---|---|---|---|
| nginx | `crmchat_nginx` | <http://localhost:8011> | 管理后台 `/admin`，客服后台 `/kefu`，WebSocket 走 `/ws` 反代 |
| swoole | `crmchat_swoole` | ws://localhost:20108 | WebSocket 长连接 + 内置消息队列 worker |
| php-fpm | `crmchat_php` | localhost:9000 | 承载普通 HTTP 接口 |
| MySQL | `crmchat_mysql` | localhost:33061 | 库 `crmeb_chat`，用户 `crmchat` / `123456`（root 同密码） |
| Redis | `crmchat_redis` | localhost:63791 | 密码 `123456` |

## 安装向导填写参考

| 安装界面配置项 | 填写值 |
|---|---|
| 数据库地址 | `mysql`（容器内互连，不要填 localhost） |
| 数据库名 | `crmeb_chat` |
| 数据库用户 / 密码 | `crmchat` / `123456` |
| Redis 地址 / 密码 | `redis` / `123456` |
| 管理员账号密码 | 自行设置（预置账号为 admin / 123456，安装时自定义过则以自定义为准） |

## 重要：改代码后要重启 swoole

Swoole 是常驻内存进程。修改 WebSocket、队列、启动期加载的代码或 composer 依赖后，**必须重启 swoole 容器才生效**：

```bash
docker restart crmchat_swoole
```

普通 HTTP 接口由 php-fpm 承载，PHP 代码改动即时生效。语法检查与日志：

```bash
docker exec crmchat_php php -l /var/www/app/xxx.php   # 语法检查
docker logs -f crmchat_swoole                          # swoole 日志
./run.sh logs                                          # 全部容器日志
```

ThinkPHP 业务日志在 `crmchat/runtime/log/`。

## 数据持久化

- MySQL 数据：`help/docker/mysql/data/`
- 上传文件：`crmchat/public/uploads/`
- `./run.sh stop/start` 不会丢数据；`./run.sh install` 与 `./run.sh delete` 会清空

## 常见问题

- **MySQL 起不来**：`help/docker/mysql/data` 初始化时必须为空目录，若曾用 root 身份写入可能留下 root 属主文件，清理后重试 `./run.sh install`。
- **端口冲突**：8011 / 20108 / 33061 / 63791 / 9000 被占用时，修改 `docker-compose.yml` 中对应 `ports` 左侧的宿主机端口。
- **安装后数据获取失败**：重启 swoole 容器，见[常见问题](../常见问题/安装后出现：数据获取失败.md)。
