<?php
// +----------------------------------------------------------------------
// | CRMChat [ CRMEB 出品客服系统 ]
// +----------------------------------------------------------------------
// | 一号通平台接口客户端
// +----------------------------------------------------------------------
namespace crmeb\services\yihaotong\storage;

use crmeb\basic\BaseStorage;
use crmeb\exceptions\AdminException;
use crmeb\services\yihaotong\AccessTokenServeService;

/**
 * 一号通接口客户端
 *
 * 封装一号通平台账号相关接口（用户信息 / 登录 / 注册 / 短信验证码）。
 * 参考 crmeb 标准版 crmeb/services/yihaotong/storage/Yihaotong 移植。
 * Class Yihaotong
 * @package crmeb\services\yihaotong\storage
 */
class Yihaotong extends BaseStorage
{
    /**
     * 一号通 API 服务
     * @var AccessTokenServeService|null
     */
    protected $accessToken;

    /**
     * Yihaotong constructor.
     * @param string $name 驱动名
     * @param array $config 配置（yihaotong_appid / yihaotong_appsecret）
     * @param string|null $configFile 配置文件名
     */
    public function __construct(string $name, array $config = [], string $configFile = null)
    {
        parent::__construct($name, $config, $configFile);
    }

    /**
     * 初始化（按注入的凭证创建 API 服务）
     * @param array $config
     * @throws AdminException 凭证缺失时抛出
     */
    protected function initialize(array $config)
    {
        $appid = (string)($config['yihaotong_appid'] ?? '');
        $appsecret = (string)($config['yihaotong_appsecret'] ?? '');
        if ($appid === '' || $appsecret === '') {
            throw new AdminException('尚未登录一号通，请先到「一号通设置」完成登录');
        }
        $this->accessToken = new AccessTokenServeService($appid, $appsecret);
    }

    /**
     * 获取用户信息（账户余额/套餐等）
     * @return array|mixed
     */
    public function getUser()
    {
        return $this->accessToken->httpRequest('v2/user/info');
    }

    /**
     * 用量记录
     * @param int $page 页码
     * @param int $limit 每页条数
     * @param int $type 记录类型（1短信 2电子面单 3物流查询 4复制）
     * @param string|int $status 短信记录状态（可选）
     * @return array|mixed
     * @throws AdminException 参数错误时抛出
     */
    public function record(int $page, int $limit, int $type, $status = '')
    {
        $typeContent = [1 => 'sms', 2 => 'expr_dump', 3 => 'expr_query', 4 => 'copy'];
        if (!isset($typeContent[$type])) {
            throw new AdminException('参数错误');
        }
        $data = ['page' => $page, 'limit' => $limit, 'type' => $typeContent[$type]];
        if ($type == 1 && $status !== '' && $status !== null) {
            $data['status'] = $status;
        }
        return $this->accessToken->httpRequest('user/record', $data);
    }

    /**
     * 发送验证码
     * @param string $phone 手机号
     * @return mixed
     */
    public function code(string $phone)
    {
        return $this->accessToken->httpRequest('user/code', ['phone' => $phone]);
    }

    /**
     * 验证验证码
     * @param string $phone 手机号
     * @param string $verify_code 验证码
     * @return mixed
     */
    public function checkCode(string $phone, string $verify_code)
    {
        return $this->accessToken->httpRequest('user/checkCode', ['phone' => $phone, 'verify_code' => $verify_code]);
    }

    /**
     * 注册
     * @param array $data 注册数据
     * @return mixed
     */
    public function register(array $data)
    {
        return $this->accessToken->httpRequest('user/register', $data);
    }

    /**
     * 登录
     * @param string $account 账号
     * @param string $password 已加密密码
     * @return mixed
     */
    public function login(string $account, string $password)
    {
        return $this->accessToken->httpRequest('v2/user/login', ['access_key' => $account, 'secret_key' => $password]);
    }
}
