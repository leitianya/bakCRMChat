#!/bin/bash

# CRMChat Docker 开发环境管理脚本
# 仓库结构：本脚本位于仓库根目录，代码位于 ./crmchat，配套文件位于 ./help/docker

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR"

# 配置文件（可自定义，默认 docker-compose.yml）
COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.yml}"

# 应用代码目录（ThinkPHP 项目根，含 think 命令与 composer.json）
APP_DIR="./crmchat"

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# 帮助信息
show_help() {
    echo -e "${GREEN}CRMChat Docker 管理脚本${NC}"
    echo ""
    echo "用法: $0 [选项]"
    echo ""
    echo "选项:"
    echo "  install   安装并启动（清理数据，首次部署）"
    echo "  start     启动容器"
    echo "  restart   重启容器"
    echo "  stop      停止容器"
    echo "  delete    删除容器和数据"
    echo "  logs      查看日志"
    echo "  -h, --help 显示帮助"
    echo ""
    echo "环境变量:"
    echo "  COMPOSE_FILE  指定 compose 文件 (默认: docker-compose.yml)"
    echo ""
    echo "启动后的访问地址:"
    echo "  安装向导    http://localhost:8011/install"
    echo "  管理后台    http://localhost:8011/admin"
    echo "  客服后台    http://localhost:8011/kefu"
    echo "  WebSocket   ws://localhost:20108"
    echo ""
    echo "示例:"
    echo "  $0 install                    # 首次部署（清理数据）"
    echo "  COMPOSE_FILE=docker-compose.build.yml $0 install   # 使用其他配置"
    echo "  $0 start                      # 启动服务"
    echo "  $0 logs                       # 查看日志"
}

# 检查 docker-compose 是否可用（兼容 v1 与 v2 插件）
check_docker() {
    if command -v docker-compose &> /dev/null; then
        DOCKER_COMPOSE="docker-compose"
    elif docker compose version &> /dev/null 2>&1; then
        DOCKER_COMPOSE="docker compose"
    else
        echo -e "${RED}错误: 未检测到 docker-compose 或 docker compose 插件${NC}"
        exit 1
    fi
    if [ ! -f "$COMPOSE_FILE" ]; then
        echo -e "${RED}错误: 配置文件 $COMPOSE_FILE 不存在${NC}"
        exit 1
    fi
}

# 准备运行环境（.env、数据目录、权限）
prepare_env() {
    # 首次运行时从示例文件生成 .env
    if [ ! -f "$APP_DIR/.env" ] && [ -f "$APP_DIR/.example.env" ]; then
        echo "生成 .env 配置文件..."
        cp "$APP_DIR/.example.env" "$APP_DIR/.env"
    fi

    # 创建数据与日志目录（MySQL 初始化要求数据目录必须为空）
    mkdir -p help/docker/mysql/data help/docker/mysql/log help/docker/nginx/log

    # 设置目录权限
    echo "设置目录权限..."
    chmod -R 777 "$APP_DIR/runtime" 2>/dev/null
    chmod -R 777 "$APP_DIR/public" 2>/dev/null
    chmod 777 "$APP_DIR/.env" 2>/dev/null
    chmod 777 "$APP_DIR/.version" 2>/dev/null
}

# 清理数据
cleanup() {
    echo -e "${YELLOW}=== 清理旧数据 ===${NC}"

    # 删除 install.lock 文件（存在则允许重新进入安装向导）
    if [ -f "$APP_DIR/public/install/install.lock" ]; then
        echo "删除 install.lock..."
        rm -f "$APP_DIR/public/install/install.lock"
    fi

    # 删除 MySQL 数据目录内容
    if [ -d "help/docker/mysql/data" ] && [ -n "$(ls -A help/docker/mysql/data 2>/dev/null)" ]; then
        echo "删除 MySQL 数据..."
        rm -rf help/docker/mysql/data/*
    fi

    # 删除 runtime 目录内容
    if [ -d "$APP_DIR/runtime" ] && [ -n "$(ls -A "$APP_DIR/runtime" 2>/dev/null)" ]; then
        echo "删除 runtime 缓存..."
        rm -rf "$APP_DIR/runtime"/*
    fi

    prepare_env
}

# 清理网络
cleanup_network() {
    echo "清理可能存在的冲突网络..."

    # 删除可能冲突的网络（docker-compose 默认使用目录名_app_net）
    for net in $(docker network ls --format "{{.Name}}" | grep -E "(app_net|crmchat_app_net)"); do
        echo "删除网络: $net"
        docker network rm "$net" 2>/dev/null
    done

    # 清理未使用的网络
    docker network prune -f 2>/dev/null
}

# 安装（清理数据并启动）
do_install() {
    check_docker

    echo -e "${YELLOW}=== 停止旧容器 ===${NC}"
    $DOCKER_COMPOSE -f "$COMPOSE_FILE" down 2>/dev/null

    cleanup
    cleanup_network

    echo -e "${YELLOW}=== 安装并启动 Docker 环境 ===${NC}"
    $DOCKER_COMPOSE -f "$COMPOSE_FILE" up -d --build

    echo ""
    echo -e "${GREEN}=== 安装完成 ===${NC}"
    echo "配置文件: $COMPOSE_FILE"
    echo "下一步: 浏览器打开 http://localhost:8011 进入安装向导"
    echo "数据库信息(安装向导中填写):"
    echo "  MySQL:  服务器地址 mysql  端口 3306  数据库 crmeb_chat  用户 crmchat  密码 123456"
    echo "  Redis:  服务器地址 redis  端口 6379  密码 123456"
    echo "查看日志: $0 logs"
}

# 启动
do_start() {
    check_docker
    prepare_env
    cleanup_network
    echo -e "${YELLOW}=== 启动容器 ===${NC}"
    $DOCKER_COMPOSE -f "$COMPOSE_FILE" up -d
    echo ""
    echo -e "${GREEN}=== 启动完成 ===${NC}"
    echo "配置文件: $COMPOSE_FILE"
    echo "访问地址: http://localhost:8011"
}

# 重启
do_restart() {
    check_docker
    echo -e "${YELLOW}=== 重启容器 ===${NC}"
    $DOCKER_COMPOSE -f "$COMPOSE_FILE" restart
    echo ""
    echo -e "${GREEN}=== 重启完成 ===${NC}"
}

# 停止
do_stop() {
    check_docker
    echo -e "${YELLOW}=== 停止容器 ===${NC}"
    $DOCKER_COMPOSE -f "$COMPOSE_FILE" down
    echo -e "${GREEN}=== 已停止 ===${NC}"
}

# 删除
do_delete() {
    check_docker
    echo -e "${YELLOW}=== 删除容器和数据 ===${NC}"
    $DOCKER_COMPOSE -f "$COMPOSE_FILE" down -v
    rm -rf help/docker/mysql/data/* 2>/dev/null
    rm -rf "$APP_DIR/runtime"/* 2>/dev/null
    rm -f "$APP_DIR/public/install/install.lock" 2>/dev/null
    echo -e "${GREEN}=== 已删除 ===${NC}"
}

# 查看日志
do_logs() {
    check_docker
    $DOCKER_COMPOSE -f "$COMPOSE_FILE" logs -f
}

# 交互式菜单
show_menu() {
    echo ""
    echo "  1、安装并启动"
    echo "  2、启动容器"
    echo "  3、重启容器"
    echo "  4、停止容器"
    echo "  5、删除容器和数据"
    echo "  6、查看日志"
    echo "  7、查看帮助"
    echo "  8、退出"
    echo ""
    read -p "请选择操作 (1-8): " choice
    echo ""

    case $choice in
        1) do_install ;;
        2) do_start ;;
        3) do_restart ;;
        4) do_stop ;;
        5) do_delete ;;
        6) do_logs ;;
        7) show_help; show_menu ;;
        8) echo "已退出"; exit 0 ;;
        *) echo "无效选择，请重试"; show_menu ;;
    esac
}

# 主逻辑
if [ $# -eq 0 ]; then
    # 无参数时显示交互式菜单
    show_menu
else
    case "$1" in
        install)
            do_install
            ;;
        start)
            do_start
            ;;
        restart)
            do_restart
            ;;
        stop)
            do_stop
            ;;
        delete)
            do_delete
            ;;
        logs)
            do_logs
            ;;
        -h|--help)
            show_help
            ;;
        *)
            show_help
            exit 1
            ;;
    esac
fi
