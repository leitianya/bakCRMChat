<?php
// +----------------------------------------------------------------------
// | CRMChat [ CRMEB 出品客服系统 ]
// +----------------------------------------------------------------------
// | 远程 MCP Server 登记模型
// +----------------------------------------------------------------------

namespace app\models\aiagent;

use crmeb\basic\BaseModel;
use crmeb\traits\ModelTrait;

/**
 * 远程 MCP Server 登记模型（eb_ai_mcp_server）
 * Class AiMcpServer
 * @package app\models\aiagent
 */
class AiMcpServer extends BaseModel
{
    use ModelTrait;

    /**
     * 表名（不含 eb_ 前缀）
     * @var string
     */
    protected $name = 'ai_mcp_server';

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
