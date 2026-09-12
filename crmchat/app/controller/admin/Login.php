<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2020 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------
namespace app\controller\admin;


use app\validate\system\SystemAdminValidata;
use crmeb\exceptions\AdminException;
use crmeb\utils\Captcha;
use app\services\system\admin\SystemAdminServices;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Request;
use think\Response;

/**
 * 后台登陆
 * Class Login
 * @package app\controller\admin
 */
class Login
{

    /**
     * 触发滑块验证码的登录错误次数阈值
     * @var int
     */
    const LOGIN_ERROR_NUM = 3;

    /**
     * @var Request
     */
    protected $request;

    /**
     * Login constructor.
     * @param SystemAdminServices $services
     */
    public function __construct(SystemAdminServices $services)
    {
        $this->services = $services;
        $this->request = app()->request;
    }

    /**
     * 验证码
     * @return $this|Response
     */
    public function captcha()
    {
        return app('json')->success(app()->make(Captcha::class)->create([], true));
    }

    /**
     * @return mixed
     */
    public function ajcaptcha()
    {
        $captchaType = $this->request->get('captchaType', 'blockPuzzle');
        return app('json')->success(aj_captcha_create($captchaType));
    }

    /**
     * 一次验证
     * @return mixed
     */
    public function ajcheck()
    {
        [$token, $pointJson, $captchaType] = $this->request->postMore([
            ['token', ''],
            ['pointJson', ''],
            ['captchaType', ''],
        ], true);
        try {
            aj_captcha_check_one($captchaType, $token, $pointJson);
            return app('json')->success();
        } catch (\Throwable $e) {
            return app('json')->fail('滑块验证失败');
        }
    }

    /**
     * 登陆
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function login()
    {
        [$account, $password, $captchaVerification, $captchaType] = $this->request->postMore([
            'account',
            'pwd',
            ['captchaVerification', ''],
            ['captchaType', '']
        ], true);

        // 登录错误超过3次后，必须先通过滑块验证码
        $errorNum = $this->services->getLoginErrorNum($account);
        if ($errorNum >= self::LOGIN_ERROR_NUM) {
            if ($captchaVerification === '') {
                return app('json')->fail('请先完成滑块验证', ['error_num' => $errorNum]);
            }
            try {
                aj_captcha_check_two($captchaType, $captchaVerification);
            } catch (\Throwable $e) {
                return app('json')->fail('滑块验证失败', ['error_num' => $errorNum]);
            }
        }

        validate(SystemAdminValidata::class)->scene('get')->check(['account' => $account, 'pwd' => $password]);

        try {
            $loginData = $this->services->login($account, $password, 'admin');
        } catch (AdminException $e) {
            // 记录登录错误次数，超过阈值后触发滑块验证码
            return app('json')->fail($e->getMessage(), ['error_num' => $this->services->incLoginErrorNum($account)]);
        }

        // 登陆成功清除错误计数
        $this->services->clearLoginErrorNum($account);

        return app('json')->success($loginData);
    }

    /**
     * 获取后台登录页轮播图以及LOGO
     * @return mixed
     */
    public function info()
    {
        return app('json')->success($this->services->getLoginInfo());
    }
}
