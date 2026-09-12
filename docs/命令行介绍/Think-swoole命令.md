## SWOOLE 命令详解


### 启动服务
```
php think swoole start
```
### 停止服务

```
php think swoole stop
```
### 重启服务

```
php think swoole restart
```
### 平滑重启服务

```
php think swoole reload
```

### 客服系统启动命令

> 请使用sudo更改命令启动用户,不然会出现某些缓存写入无法被www用户打开的问题

```
sudo -u www php think swoole restart
```
