FROM debian:12-slim

ARG DEBIAN_FRONTEND=noninteractive

# CRMChat 仅兼容 PHP 7.1 ~ 7.4。
# Debian 12 默认提供 PHP 8.2，因此使用 DEB.SURY.ORG 安装 PHP 7.4，
# 同时保留 Debian 12 的受支持系统基础，避免 Debian 11 归档源失效问题。
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        gnupg2 \
        lsb-release; \
    mkdir -p /etc/apt/keyrings; \
    curl -fsSL https://packages.sury.org/php/apt.gpg \
        | gpg --dearmor -o /etc/apt/keyrings/deb.sury.org-php.gpg; \
    echo "deb [signed-by=/etc/apt/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ bookworm main" \
        > /etc/apt/sources.list.d/php.list; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        php7.4-cli \
        php7.4-common \
        php7.4-fpm \
        php7.4-bcmath \
        php7.4-curl \
        php7.4-gd \
        php7.4-mbstring \
        php7.4-mysql \
        php7.4-opcache \
        php7.4-redis \
        php7.4-xml \
        php7.4-zip \
        php7.4-dev \
        php-pear \
        build-essential \
        pkg-config \
        libssl-dev; \
    update-alternatives --set php /usr/bin/php7.4; \
    update-alternatives --set phpize /usr/bin/phpize7.4; \
    update-alternatives --set php-config /usr/bin/php-config7.4; \
    printf '\n' | pecl install swoole-4.8.13; \
    echo 'extension=swoole.so' > /etc/php/7.4/mods-available/swoole.ini; \
    phpenmod -v 7.4 swoole; \
    sed -ri 's!^listen = .*!listen = 9000!' /etc/php/7.4/fpm/pool.d/www.conf; \
    sed -ri 's!^;?clear_env = .*!clear_env = no!' /etc/php/7.4/fpm/pool.d/www.conf; \
    mkdir -p /run/php /var/www; \
    php -v; \
    php -m | grep -E '^(bcmath|curl|fileinfo|gd|mbstring|mysqli|pcntl|pdo_mysql|redis|sockets|swoole|xml|zip)$'; \
    php --ri redis >/dev/null; \
    php --ri swoole >/dev/null; \
    apt-get purge -y --auto-remove \
        php7.4-dev \
        php-pear \
        build-essential \
        pkg-config \
        libssl-dev; \
    apt-get clean; \
    rm -rf /var/lib/apt/lists/* /tmp/pear ~/.pearrc

WORKDIR /var/www

EXPOSE 9000

STOPSIGNAL SIGQUIT

CMD ["/usr/sbin/php-fpm7.4", "--nodaemonize", "--fpm-config", "/etc/php/7.4/fpm/php-fpm.conf"]
