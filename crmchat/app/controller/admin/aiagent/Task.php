<?php
// +----------------------------------------------------------------------
// | CRMChat [ CRMEB 出品客服系统 ]
// +----------------------------------------------------------------------
// | AI 自动化任务后台接口控制器
// +----------------------------------------------------------------------

namespace app\controller\admin\aiagent;

use app\controller\admin\AuthController;
use app\jobs\AgentTaskJob;
use app\services\aiagent\AgentTaskServices;

/**
 * AI 自动化任务后台接口（任务 CRUD/启停/立即执行/运行历史/运行详情）
 * Class Task
 * @package app\controller\admin\aiagent
 */
class Task extends AuthController
{
    /**
     * @var AgentTaskServices 自动化任务服务
     */
    protected $services;

    /**
     * Task constructor.
     * @param AgentTaskServices $services
     */
    public function __construct(AgentTaskServices $services)
    {
        parent::__construct();
        $this->services = $services;
    }

    /**
     * 任务列表（当前管理员，分页）
     * @return mixed
     */
    public function index()
    {
        [$page, $limit] = $this->pageParams();
        return $this->success('ok', $this->services->index((int)$this->adminId, $page, $limit));
    }

    /**
     * 解析分页参数
     * @return array{int,int}
     */
    protected function pageParams(): array
    {
        $page = max(1, (int)$this->request->param('page/d', 1));
        $limit = (int)$this->request->param('limit/d', 0);
        if ($limit < 1 || $limit > 100) {
            $limit = 20;
        }
        return [$page, $limit];
    }

    /**
     * 创建/编辑任务（编辑时表单需携带 id）
     * @return mixed
     */
    public function save()
    {
        $data = $this->request->postMore([
            ['id', 0],
            ['name', ''],
            ['instruction', ''],
            ['model_id', 0],
            ['server_ids', []],
            ['skill_keys', []],
            ['autonomy', AgentTaskServices::AUTONOMY_READONLY],
            ['schedule_type', AgentTaskServices::SCHEDULE_DAILY],
            ['schedule_value', ''],
            ['max_steps', 10],
            ['timeout', 300],
            ['notify_channels', []],
            ['notify_email', ''],
            ['status', 1],
        ]);
        $id = $this->services->save((int)$this->adminId, $data);
        return $this->success('保存成功', ['id' => $id]);
    }

    /**
     * 任务详情
     * @param int $id 任务 ID
     * @return mixed
     */
    public function read(int $id)
    {
        return $this->success('ok', $this->services->read($id, (int)$this->adminId));
    }

    /**
     * 删除任务（含运行历史）
     * @param int $id 任务 ID
     * @return mixed
     */
    public function delete(int $id)
    {
        $this->services->delete($id, (int)$this->adminId);
        return $this->success('删除成功');
    }

    /**
     * 修改任务启停状态
     * @param int $id 任务 ID
     * @return mixed
     */
    public function status(int $id)
    {
        $status = (int)$this->request->param('status', 0);
        $this->services->setStatus($id, (int)$this->adminId, $status);
        return $this->success($status === 1 ? '已启用' : '已停用');
    }

    /**
     * 立即执行任务（与调度执行同链路入队，兼作测试入口）
     * @param int $id 任务 ID
     * @return mixed
     */
    public function trigger(int $id)
    {
        $runId = $this->services->trigger($id, (int)$this->adminId);
        AgentTaskJob::dispatch('doJob', [$runId]);
        return $this->success('已加入执行队列', ['run_id' => $runId]);
    }

    /**
     * 任务运行历史（分页）
     * @param int $id 任务 ID
     * @return mixed
     */
    public function runs(int $id)
    {
        [$page, $limit] = $this->pageParams();
        return $this->success('ok', $this->services->runs($id, (int)$this->adminId, $page, $limit));
    }

    /**
     * 全量运行记录（当前管理员的全部任务，分页，附任务名称）
     * @return mixed
     */
    public function runIndex()
    {
        [$page, $limit] = $this->pageParams();
        return $this->success('ok', $this->services->runIndex((int)$this->adminId, $page, $limit));
    }

    /**
     * 运行详情（含关联会话消息，回放执行全过程）
     * @param int $id 运行记录 ID
     * @return mixed
     */
    public function runDetail(int $id)
    {
        return $this->success('ok', $this->services->runDetail($id, (int)$this->adminId));
    }
}
