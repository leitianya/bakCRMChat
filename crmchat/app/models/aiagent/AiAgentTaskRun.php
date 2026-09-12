<?php
// +----------------------------------------------------------------------
// | CRMChat [ CRMEB 出品客服系统 ]
// +----------------------------------------------------------------------
// | AI 自动化任务运行记录模型
// +----------------------------------------------------------------------

namespace app\models\aiagent;

use crmeb\basic\BaseModel;
use crmeb\traits\ModelTrait;

/**
 * AI 自动化任务运行记录模型（eb_ai_agent_task_run）
 * Class AiAgentTaskRun
 * @package app\models\aiagent
 */
class AiAgentTaskRun extends BaseModel
{
    use ModelTrait;

    /**
     * 表名（不含 eb_ 前缀）
     * @var string
     */
    protected $name = 'ai_agent_task_run';

    /**
     * 主键
     * @var string
     */
    protected $pk = 'id';

    /**
     * 关闭自动时间戳（时间字段为 int 时间戳且由服务层手动写入，
     * 避免全局 auto_timestamp 配置在读模型时把 int 误当 datetime 格式化）
     * @var bool
     */
    protected $autoWriteTimestamp = false;
}
