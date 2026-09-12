<?php
// +----------------------------------------------------------------------
// | CRMChat [ CRMEB 出品客服系统 ]
// +----------------------------------------------------------------------
// | AI Agent 会话模型
// +----------------------------------------------------------------------

namespace app\models\aiagent;

use crmeb\basic\BaseModel;
use crmeb\traits\ModelTrait;

/**
 * AI Agent 会话模型（eb_ai_agent_conversation）
 * Class AiAgentConversation
 * @package app\models\aiagent
 */
class AiAgentConversation extends BaseModel
{
    use ModelTrait;

    /**
     * 表名（不含 eb_ 前缀）
     * @var string
     */
    protected $name = 'ai_agent_conversation';

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
