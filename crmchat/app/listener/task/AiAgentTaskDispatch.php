<?php
// +----------------------------------------------------------------------
// | CRMChat [ CRMEB 出品客服系统 ]
// +----------------------------------------------------------------------
// | AI 自动化任务调度监听（Task_60 每分钟触发）
// +----------------------------------------------------------------------

namespace app\listener\task;

use app\services\aiagent\AgentTaskServices;
use crmeb\interfaces\ListenerInterface;
use think\facade\Log;

/**
 * AI 自动化任务调度监听
 *
 * 由 Swoole 定时器按分钟触发（app/webscoket/SwooleWorkerStart.php 的 Task_60 事件），
 * 仅做「到期扫描 + 入队」轻量动作，LLM 多轮调用全部在队列容器执行。
 * Class AiAgentTaskDispatch
 * @package app\listener\task
 */
class AiAgentTaskDispatch implements ListenerInterface
{
    /**
     * 扫描到期的 AI 自动化任务并入队执行
     * @param mixed $event 事件数据
     */
    public function handle($event): void
    {
        try {
            app()->make(AgentTaskServices::class)->dispatchDueTasks();
        } catch (\Throwable $e) {
            Log::error('[AgentTask] 调度扫描异常: ' . $e->getMessage());
        }
    }
}
