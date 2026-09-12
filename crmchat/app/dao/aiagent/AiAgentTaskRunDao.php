<?php
// +----------------------------------------------------------------------
// | CRMChat [ CRMEB 出品客服系统 ]
// +----------------------------------------------------------------------
// | AI 自动化任务运行记录 Dao
// +----------------------------------------------------------------------

namespace app\dao\aiagent;

use app\models\aiagent\AiAgentTask;
use app\models\aiagent\AiAgentTaskRun;
use crmeb\basic\BaseDao;

/**
 * AI 自动化任务运行记录 Dao（eb_ai_agent_task_run）
 * Class AiAgentTaskRunDao
 * @package app\dao\aiagent
 */
class AiAgentTaskRunDao extends BaseDao
{
    /**
     * 绑定模型
     * @return string
     */
    protected function setModel(): string
    {
        return AiAgentTaskRun::class;
    }

    /**
     * 运行记录分页（按任务，按 ID 倒序）
     * @param int $taskId 任务 ID
     * @param int $page 页码
     * @param int $limit 每页条数
     * @return array{list:array,count:int}
     */
    public function getPageByTask(int $taskId, int $page, int $limit): array
    {
        $query = $this->search()->where('task_id', $taskId);
        $count = (int)(clone $query)->count();
        $list = $query->order('id', 'desc')->page($page, $limit)->select()->toArray();
        return ['list' => $list, 'count' => $count];
    }

    /**
     * 全量运行记录分页（当前管理员全部任务，附任务名称，join 任务表）
     * @param int $adminId 管理员 ID
     * @param int $page 页码
     * @param int $limit 每页条数
     * @return array{list:array,count:int}
     */
    public function getPageWithTaskByAdmin(int $adminId, int $page, int $limit): array
    {
        $query = $this->search()->alias('r')
            // join 数组键是真实表名（含前缀），必须用 getTable() 显式带出
            ->join([(new AiAgentTask())->getTable() => 't'], 't.id = r.task_id')
            ->where('t.admin_id', $adminId);
        $count = (int)(clone $query)->count();
        $list = $query
            ->field('r.*,t.name AS task_name')
            ->order('r.id', 'desc')
            ->page($page, $limit)
            ->select()
            ->toArray();
        return ['list' => $list, 'count' => $count];
    }

    /**
     * 按主键读取运行记录
     * @param int $runId 运行记录 ID
     * @return array|null
     */
    public function getById(int $runId): ?array
    {
        $run = $this->search()->where('id', $runId)->find();
        return $run ? $run->toArray() : null;
    }

    /**
     * 新增运行记录
     * @param array $data 运行数据
     * @return int 运行记录 ID
     */
    public function insertRun(array $data): int
    {
        return (int)$this->getModel()->insertGetId($data);
    }

    /**
     * 按主键更新运行记录（返回影响行数，记录不存在时静默为 0）
     * @param int $runId 运行记录 ID
     * @param array $data 更新数据
     * @return int
     */
    public function updateById(int $runId, array $data): int
    {
        return (int)$this->search()->where('id', $runId)->update($data);
    }

    /**
     * 按任务删除全部运行记录
     * @param int $taskId 任务 ID
     * @return int
     */
    public function deleteByTaskId(int $taskId): int
    {
        return (int)$this->search()->where('task_id', $taskId)->delete();
    }

    /**
     * 读取孤儿运行 ID 列表（running 超过任务超时阈值 + 120 秒仍未终态，join 任务表取 timeout）
     * @param int $now 当前时间戳
     * @return array
     */
    public function findOrphanRunIds(int $now): array
    {
        return $this->search()->alias('r')
            // join 数组键是真实表名（含前缀），必须用 getTable() 显式带出
            ->join([(new AiAgentTask())->getTable() => 't'], 't.id = r.task_id')
            ->where('r.status', 'running')
            ->where('r.started_at', '>', 0)
            ->whereRaw('r.started_at + t.timeout + 120 < ' . $now)
            ->column('r.id');
    }

    /**
     * 按 ID 列表批量更新运行记录
     * @param array $runIds 运行记录 ID 列表
     * @param array $data 更新数据
     * @return int
     */
    public function updateByIds(array $runIds, array $data): int
    {
        return (int)$this->search()->whereIn('id', $runIds)->update($data);
    }

    /**
     * 数据库 CAS 领取：仅 running 态可写 started_at，并发重复投递只有一个赢家
     * @param int $runId 运行记录 ID
     * @param int $startedAt 领取时间戳
     * @return int 影响行数（0 表示领取失败）
     */
    public function claimRunning(int $runId, int $startedAt): int
    {
        return (int)$this->search()
            ->where('id', $runId)
            ->where('status', 'running')
            ->update(['started_at' => $startedAt]);
    }
}
