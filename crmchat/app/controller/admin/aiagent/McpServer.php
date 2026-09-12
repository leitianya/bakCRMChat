<?php
// +----------------------------------------------------------------------
// | CRMChat [ CRMEB 出品客服系统 ]
// +----------------------------------------------------------------------
// | 远程 MCP Server 管理后台接口控制器
// +----------------------------------------------------------------------

namespace app\controller\admin\aiagent;

use app\controller\admin\AuthController;
use app\services\aiagent\McpServerServices;

/**
 * 远程 MCP Server 管理后台接口
 * Class McpServer
 * @package app\controller\admin\aiagent
 */
class McpServer extends AuthController
{
    /**
     * @var McpServerServices 远程 MCP Server 服务
     */
    protected $services;

    /**
     * McpServer constructor.
     * @param McpServerServices $services
     */
    public function __construct(McpServerServices $services)
    {
        parent::__construct();
        $this->services = $services;
    }

    /**
     * Server 列表
     * @return mixed
     */
    public function index()
    {
        return $this->success('ok', $this->services->getList());
    }

    /**
     * Server 详情
     * @param int $id Server ID
     * @return mixed
     */
    public function read(int $id)
    {
        return $this->success('ok', $this->services->get($id));
    }

    /**
     * 保存 Server（表单或 JSON 配置两种方式）
     * @param int $id Server ID，传 0 表示新增
     * @return mixed
     */
    public function save(int $id = 0)
    {
        $data = (array)$this->request->param();
        $config = trim((string)($data['config'] ?? ''));
        if ($config !== '') {
            $ids = $this->services->saveJson($config, $id);
            return $this->success('保存成功', ['id' => $id > 0 ? ($ids[0] ?? $id) : ($ids[0] ?? 0), 'ids' => $ids]);
        }
        $serverId = $this->services->save($data, $id);
        return $this->success('保存成功', ['id' => $serverId]);
    }

    /**
     * 删除 Server
     * @param int $id Server ID
     * @return mixed
     */
    public function delete(int $id)
    {
        $this->services->delete($id);
        return $this->success('删除成功');
    }

    /**
     * 修改启用状态
     * @param int $id Server ID
     * @return mixed
     */
    public function status(int $id)
    {
        $this->services->setStatus($id, (int)$this->request->param('status', 0));
        return $this->success('修改成功');
    }

    /**
     * 同步远端工具清单
     * @param int $id Server ID
     * @return mixed
     */
    public function sync(int $id)
    {
        $tools = $this->services->syncTools($id);
        return $this->success('同步成功', ['count' => count($tools), 'tools' => $tools]);
    }

    /**
     * 连通性测试
     * @param int $id Server ID
     * @return mixed
     */
    public function test(int $id)
    {
        return $this->success('ok', $this->services->testConnection($id));
    }
}
