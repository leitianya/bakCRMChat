<?php
// +----------------------------------------------------------------------
// | CRMChat [ CRMEB 出品客服系统 ]
// +----------------------------------------------------------------------
// | AI 模型登记模型
// +----------------------------------------------------------------------

namespace app\models\ai;

use crmeb\basic\BaseModel;
use crmeb\traits\ModelTrait;

/**
 * AI 模型登记模型（eb_ai_model）
 *
 * 每条记录 = 一个可调用的大模型，protocol 字段决定由哪个内置协议适配器
 * （crmeb\services\ai\AiProtocol）发起请求，同协议族内新增厂商只需加记录，零代码。
 * Class AiModel
 * @package app\models\ai
 */
class AiModel extends BaseModel
{
    use ModelTrait;

    /**
     * 表名（不含 eb_ 前缀）
     * @var string
     */
    protected $name = 'ai_model';

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

    /**
     * 状态搜索器
     * @param $query
     * @param $value
     */
    public function searchStatusAttr($query, $value)
    {
        if ($value !== '' && $value !== null) {
            $query->where('status', (int)$value);
        }
    }

    /**
     * 协议族搜索器
     * @param $query
     * @param $value
     */
    public function searchProtocolAttr($query, $value)
    {
        if ($value) {
            $query->where('protocol', (string)$value);
        }
    }

    /**
     * 关键字搜索器（名称/模型标识/厂商）
     * @param $query
     * @param $value
     */
    public function searchKeywordAttr($query, $value)
    {
        if ($value) {
            $keyword = (string)$value;
            $query->where(function ($q) use ($keyword) {
                $q->whereOr('name', 'like', '%' . $keyword . '%')
                    ->whereOr('model', 'like', '%' . $keyword . '%')
                    ->whereOr('provider', 'like', '%' . $keyword . '%');
            });
        }
    }

    /**
     * 是否支持工具调用搜索器
     * @param $query
     * @param $value
     */
    public function searchSupportsToolsAttr($query, $value)
    {
        if ($value !== '' && $value !== null) {
            $query->where('supports_tools', (int)$value);
        }
    }
}
