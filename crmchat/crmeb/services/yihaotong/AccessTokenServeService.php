<?php
// +----------------------------------------------------------------------
// | CRMChat [ CRMEB 出品客服系统 ]
// +----------------------------------------------------------------------
// | 一号通平台 API 服务（token 获取与请求封装）
// +----------------------------------------------------------------------
namespace crmeb\services\yihaotong;

use crmeb\exceptions\ApiException;
use crmeb\services\CacheService;
use crmeb\services\HttpService;

/**
 * 一号通 AccessToken 服务
 *
 * 以一号通 appid / appsecret 换取 access_token（平台侧缓存），并封装带鉴权头的平台请求。
 * 参考 crmeb 标准版 crmeb/services/yihaotong/AccessTokenServeService 移植。
 * Class AccessTokenServeService
 * @package crmeb\services\yihaotong
 */
class AccessTokenServeService extends HttpService
{
    /**
     * 一号通平台账号（appid）
     * @var string
     */
    protected $account;

    /**
     * 一号通平台密钥（appsecret）
     * @var string
     */
    protected $secret;

    /**
     * 当前 access_token
     * @var string
     */
    protected $accessToken;

    /**
     * token 缓存键前缀
     * @var string
     */
    protected $cacheTokenPrefix = "_crmeb_plat";

    /**
     * 一号通网关地址
     * @var string
     */
    protected $apiHost = 'https://sms.crmeb.net/api/';

    /**
     * 沙盒网关地址
     * @var string
     */
    protected $sandBoxApi = 'https://api_v2.crmeb.net/api/';

    /**
     * 沙盒模式
     * @var bool
     */
    protected $sandBox = false;

    /**
     * 登录接口
     */
    const USER_LOGIN = "v2/user/login";

    /**
     * AccessTokenServeService constructor.
     * @param string $account 平台账号（appid）
     * @param string $secret 平台密钥（appsecret）
     */
    public function __construct(string $account, string $secret)
    {
        $this->account = $account;
        $this->secret = $secret;
    }

    /**
     * 获取凭证配置
     * @return array
     */
    public function getConfig()
    {
        return [
            'access_key' => $this->account,
            'secret_key' => $this->secret
        ];
    }

    /**
     * 获取缓存 token（无缓存时向平台换取并缓存）
     * @return mixed
     * @throws ApiException 平台返回错误时抛出
     */
    public function getToken()
    {
        $accessTokenKey = md5($this->account . '_v2_' . $this->secret . $this->cacheTokenPrefix);
        $cacheToken = CacheService::get($accessTokenKey);
        if (!$cacheToken) {
            $getToken = $this->getTokenFromServer();
            CacheService::set($accessTokenKey, $getToken['access_token'], $getToken['expires_in'] - 60);
            $cacheToken = $getToken['access_token'];
        }
        $this->accessToken = $cacheToken;

        return $cacheToken;
    }

    /**
     * 从平台获取 token
     * @return mixed
     * @throws ApiException 凭证缺失或平台返回错误时抛出
     */
    public function getTokenFromServer()
    {
        if (!$this->account || !$this->secret) {
            throw new ApiException('请先登录一号通平台！');
        }
        $params = [
            'access_key' => $this->account,
            'secret_key' => $this->secret,
        ];
        $response = $this->postRequest($this->get(self::USER_LOGIN), $params);
        $response = json_decode((string)$response, true);
        if (!$response) {
            throw new ApiException('获取token失败');
        }
        if ($response['status'] === 200) {
            return $response['data'];
        } else {
            throw new ApiException('获取token失败：' . ($response['msg'] ?? ''));
        }
    }

    /**
     * 平台请求（自动携带鉴权头）
     * @param string $url 接口相对地址
     * @param array $data 请求数据
     * @param string $method 请求方式
     * @param bool $isHeader 是否携带鉴权头
     * @param array $header 附加请求头
     * @return array|mixed
     * @throws ApiException 请求失败或平台返回错误时抛出
     */
    public function httpRequest(string $url, array $data = [], string $method = 'POST', bool $isHeader = true, array $header = [])
    {
        if ($isHeader) {
            $this->getToken();
            if (!$this->accessToken) {
                throw new ApiException('配置已更改或token已失效');
            }
            $header = array_merge($header, ['Authorization:Bearer-' . $this->accessToken]);
        }

        $res = $this->request($this->get($url), $method, $data, $header);
        if (!$res) {
            throw new ApiException('平台错误：发生异常，请稍后重试');
        }
        $result = json_decode((string)$res, true) ?: false;
        if (!isset($result['status']) || $result['status'] != 200) {
            throw new ApiException(isset($result['msg']) ? '平台错误：' . $result['msg'] : '平台错误：发生异常，请稍后重试');
        }
        return $result['data'] ?? [];
    }

    /**
     * 拼接完整接口地址
     * @param string $apiUrl 相对地址
     * @return string
     */
    public function get(string $apiUrl = '')
    {
        if ($this->sandBox) {
            return $this->sandBoxApi . $apiUrl;
        }
        return $this->apiHost . $apiUrl;
    }
}
