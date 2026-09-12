FROM docker.1ms.run/php:7.4-fpm

# CRMChat 官方运行环境要求 PHP 7.1 ~ 7.4（见项目 README.md）
# 必需扩展：swoole、redis、fileinfo（fileinfo 已随官方镜像内置）
# 必需函数：curl_init、openssl_encrypt、gd_info（curl、openssl 已随官方镜像内置）

# 安装系统依赖与 PHP 扩展
# 注：bullseye 已于 2026-08 结束 LTS，security 源 Release 文件过期，需跳过有效期检查才能 update
RUN apt-get -o Acquire::Check-Valid-Until=false update && apt-get install -y \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libzip-dev \
    libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        bcmath \
        gd \
        pdo_mysql \
        mysqli \
        zip \
        mbstring \
        sockets \
        pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && pecl install swoole-4.8.13 \
    && docker-php-ext-enable swoole \
    && php -m | grep -E "swoole|redis" \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www
