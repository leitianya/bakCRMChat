<?php
// +----------------------------------------------------------------------
// | CRMChat [ CRMEB 出品客服系统 ]
// +----------------------------------------------------------------------
// | 一号通平台服务管理器
// +----------------------------------------------------------------------
namespace crmeb\services\yihaotong;

use crmeb\basic\BaseManager;
use crmeb\services\yihaotong\storage\Yihaotong;
use think\facade\Config;
use think\Container;

/**
 * 一号通服务管理器
 *
 * 用法：app()->make(Serve::class, [['yihaotong_appid' => ..., 'yihaotong_appsecret' => ...]])->getUser()
 * 凭证来自「一号通设置」（eb_system_config：yihaotong_appid / yihaotong_appsecret），
 * 由调用方（helper yihaotong_config()）解析后以数组注入。
 * 参考 crmeb 标准版 crmeb/services/yihaotong/Serve 移植。
 * Class Serve
 * @package crmeb\services\yihaotong
 * @mixin Yihaotong
 */
class Serve extends BaseManager
{
    /**
     * 空间名
     * @var string
     */
    protected $namespace = '\\crmeb\\services\\yihaotong\\storage\\';

    /**
     * 默认驱动
     * @return mixed
     */
    protected function getDefaultDriver()
    {
        return Config::get('serve.default', 'yihaotong');
    }

    /**
     * 获取类的实例
     *
     * 传入的凭证数组作为驱动配置直接下发，由驱动自行创建 AccessTokenServeService。
     * @param $class
     * @return mixed|void
     */
    protected function invokeClass($class)
    {
        if (!class_exists($class)) {
            throw new \RuntimeException('class not exists: ' . $class);
        }
        $this->getConfigFile();

        if (!$this->config) {
            $this->config = Config::get($this->configFile . '.stores.' . $this->name, []);
        }

        $handle = Container::getInstance()->invokeClass($class, [$this->name, $this->config, $this->configFile]);
        $this->config = [];
        return $handle;
    }
}
