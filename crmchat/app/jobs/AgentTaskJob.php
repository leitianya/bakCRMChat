<?php
// +----------------------------------------------------------------------
// | CRMChat [ CRMEB 出品客服系统 ]
// +----------------------------------------------------------------------
// | AI 自动化任务队列 Job
// +----------------------------------------------------------------------

namespace app\jobs;

use app\services\aiagent\AgentTaskRunnerService;
use crmeb\basic\BaseJobs;
use crmeb\traits\QueueTrait;
use think\facade\Log;

/**
 * AI 自动化任务队列 Job
 *
 * doJob 内部自捕获异常并返回 true：运行失败由 AgentTaskRunnerService 的 run 状态机
 * 标记 failed 并通知，不走队列级重试，避免 LLM 多轮调用的重复 token 消耗。
 * Class AgentTaskJob
 * @package app\jobs
 */
class AgentTaskJob extends BaseJobs
{
    use QueueTrait;

    /**
     * 执行一次自动化任务运行
     * @param int $runId 运行记录 ID
     * @return bool 恒返回 true（领取队列消息后删除，不触发 release 重试）
     */
    public function doJob($runId): bool
    {
        $runId = (int)$runId;
        try {
            app()->make(AgentTaskRunnerService::class)->run($runId);
        } catch (\Throwable $e) {
            Log::error('[AgentTask] 队列执行异常: ' . $e->getMessage(), ['run_id' => $runId]);
        }
        return true;
    }
}
