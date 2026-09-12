<?php
// +----------------------------------------------------------------------
// | CRMChat [ CRMEB 出品客服系统 ]
// +----------------------------------------------------------------------
// | AI 模型管理控制器
// +----------------------------------------------------------------------

namespace app\controller\admin\ai;

use app\controller\admin\AuthController;
use app\services\ai\AiModelServices;

/**
 * AI 模型管理控制器
 *
 * 模型登记即「协议 + 凭证 + 模型标识」的一条记录，新增同协议厂商无需再写驱动代码。
 * Class AiModel
 * @package app\controller\admin\ai
 */
class AiModel extends AuthController
{
    /**
     * @var AiModelServices
     */
    protected $services;

    /**
     * AiModel constructor.
     * @param AiModelServices $services
     */
    public function __construct(AiModelServices $services)
    {
        parent::__construct();
        $this->services = $services;
    }

    /**
     * 模型列表
     * @return mixed
     */
    public function index()
    {
        $params = $this->request->getMore([
            ['status', ''],
            ['protocol', ''],
            ['keyword', ''],
            ['page', 1],
            ['limit', 20],
        ]);
        $where = array_intersect_key($params, array_flip(['status', 'protocol', 'keyword']));
        return $this->success('ok', $this->services->getList($where, max(1, (int)$params['page']), max(1, (int)$params['limit'])));
    }

    /**
     * 模型详情
     * @param int $id 模型ID
     * @return mixed
     */
    public function read(int $id)
    {
        return $this->success('ok', $this->services->getDetail($id));
    }

    /**
     * 新增模型
     * @return mixed
     */
    public function save()
    {
        $id = $this->services->save($this->request->postMore([
            ['name', ''],
            ['protocol', 'openai_compatible'],
            ['provider', ''],
            ['model', ''],
            ['base_url', ''],
            ['api_key', ''],
            ['full_url', 0],
            ['supports_tools', 0],
            ['supports_vision', 0],
            ['context_window', 0],
            ['max_tokens', 2048],
            ['temperature', 0.7],
            ['is_default', 0],
            ['sort', 0],
            ['status', 1],
        ]));
        return $this->success('添加成功', ['id' => $id]);
    }

    /**
     * 修改模型
     * @param int $id 模型ID
     * @return mixed
     */
    public function update(int $id)
    {
        $data = $this->request->postMore([
            ['name', ''],
            ['protocol', 'openai_compatible'],
            ['provider', ''],
            ['model', ''],
            ['base_url', ''],
            ['api_key', ''],
            ['full_url', 0],
            ['supports_tools', 0],
            ['supports_vision', 0],
            ['context_window', 0],
            ['max_tokens', 2048],
            ['temperature', 0.7],
            ['is_default', 0],
            ['sort', 0],
            ['status', 1],
        ]);
        $this->services->update($id, $data);
        return $this->success('修改成功');
    }

    /**
     * 删除模型
     * @param int $id 模型ID
     * @return mixed
     */
    public function delete(int $id)
    {
        $this->services->delete($id);
        return $this->success('删除成功');
    }

    /**
     * 修改模型状态
     * @param int $id 模型ID
     * @return mixed
     */
    public function set_status(int $id)
    {
        $this->services->setStatus($id, (int)$this->request->param('status', 0));
        return $this->success('修改成功');
    }

    /**
     * 设为系统默认模型
     * @param int $id 模型ID
     * @return mixed
     */
    public function set_default(int $id)
    {
        $this->services->setDefault($id);
        return $this->success('设置成功');
    }

    /**
     * 内置协议族清单（表单渲染用）
     * @return mixed
     */
    public function protocols()
    {
        return $this->success('ok', $this->services->getProtocols());
    }

    /**
     * 一号通凭证配置状态（模型管理一键配置入口）
     * @return mixed
     */
    public function yihaotong_status()
    {
        return $this->success('ok', ['configured' => $this->services->yihaotongConfigured()]);
    }

    /**
     * 模型连通性测试（按表单配置发起一次真实请求，不落库）
     * @return mixed
     */
    public function test()
    {
        $data = $this->request->postMore([
            ['id', 0],
            ['name', ''],
            ['protocol', 'openai_compatible'],
            ['provider', ''],
            ['model', ''],
            ['base_url', ''],
            ['api_key', ''],
            ['full_url', 0],
            ['supports_tools', 0],
            ['supports_vision', 0],
            ['context_window', 0],
            ['max_tokens', 2048],
            ['temperature', 0.7],
            ['is_default', 0],
            ['sort', 0],
            ['status', 1],
        ]);
        return $this->success('ok', $this->services->testModel($data));
    }
}
