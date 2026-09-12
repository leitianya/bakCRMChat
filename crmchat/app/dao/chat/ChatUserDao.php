<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2020 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------

namespace app\dao\chat;


use app\models\chat\ChatUser;
use crmeb\basic\BaseDao;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;

/**
 * Class ChatUserDao
 * @package app\dao\chat
 */
class ChatUserDao extends BaseDao
{

    /**
     * 获取当前模型
     * @return string
     */
    protected function setModel(): string
    {
        return ChatUser::class;
    }

    /**
     * @return mixed
     */
    public function max(array $where)
    {
        return $this->getModel()->where($where)->max('uid');
    }

    /**
     * 获取用户列表
     * @param array $where
     * @param string $field
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getUserList(array $where, string $field = '*')
    {
        return $this->search()->when(isset($where['ids']) && $where['ids'], function ($query) use ($where) {
            $query->whereIn('id', $where['ids']);
        })->field($field)->select()->toArray();
    }

    /**
     * @param array $where
     * @return \crmeb\basic\BaseModel|mixed|\think\Model
     */
    public function searchModel(array $where = [])
    {
        return $this->search($where);
    }

    /**
     * 按客户查询条件统计数量（条件口径与 getUserModel 一致）
     * @param array $where
     * @return int
     */
    public function getUserCount(array $where): int
    {
        return (int)$this->getUserModel($where)->count();
    }

    /**
     * 按分组统计客户数
     * @return array<int,int> group_id => 客户数
     */
    public function countGroupByGroupId(): array
    {
        $list = $this->getModel()->field(['group_id', 'COUNT(*) AS total'])->group('group_id')->select()->toArray();
        return array_column($list, 'total', 'group_id');
    }

    /**
     * 按客户ID集合取昵称映射（优先备注昵称）
     * @param array $ids
     * @return array<int,string> id => 昵称
     */
    public function getNicknameMapByIds(array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $list = $this->getModel()->whereIn('id', array_map('intval', $ids))
            ->field(['id', 'nickname', 'remark_nickname'])->select()->toArray();
        $map = [];
        foreach ($list as $item) {
            $map[(int)$item['id']] = $item['remark_nickname'] ?: $item['nickname'];
        }
        return $map;
    }

    /**
     * @param array $where
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function kefuStatistics(array $where)
    {
        $type = $where['type'] ?? 0;
        return $this->search()
            ->where('is_tourist', $where['is_tourist'])
            ->when(isset($where['user_id']) && $where['user_id'], function ($query) use ($where) {
                $query->where('id', $where['user_id']);
            })->when(isset($where['appid']) && $where['appid'], function ($query) use ($where) {
                $query->where('appid', $where['appid']);
            })->whereBetweenTime('create_time', $where['startTime'], $where['endTime'])
            ->field([!$type ? "DATE_FORMAT(create_time,'%Y-%m-%d') as month" : "DATE_FORMAT(create_time,'%Y-%m') as month", 'count(*) as number'])
            ->group('month')
            ->select()->toArray();
    }

    /**
     * 搜索用户统计
     * @param string $time
     * @param int $isTourist
     * @return array
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws \think\db\exception\DbException
     */
    public function getKefuMobileStatisticsList(string $time, int $isTourist = 0)
    {
        if (in_array($time, ['today', 'yesterday'])) {
            $fieldTime = "DATE_FORMAT(create_time,'%H') as time";
        } else if (in_array($time, ['quarter', 'year'])) {
            $fieldTime = "DATE_FORMAT(create_time,'%Y-%m') as time";
        } else if (in_array($time, ['week', 'month', 'last week', 'last month'])) {
            $fieldTime = "DATE_FORMAT(create_time,'%Y-%m-%d') as time";
        } elseif (preg_match('/^lately+[1-9]{1,3}/', $time)) {
            $day = (int)str_replace('lately', '', $time);
            if ($day > 90 && $day < 365) {
                $fieldTime = "DATE_FORMAT(create_time,'%Y-%m') as time";
            } elseif ($day > 365) {
                $fieldTime = "DATE_FORMAT(create_time,'%Y') as time";
            } elseif ($day < 30) {
                $fieldTime = "DATE_FORMAT(create_time,'%Y-%m-%d') as time";
            } elseif ($day <= 1) {
                $fieldTime = "DATE_FORMAT(create_time,'%H') as time";
            } else {
                $fieldTime = "DATE_FORMAT(create_time,'%Y-%m-%d') as time";
            }
        } elseif (strstr($time, '-') !== false) {
            $fieldTime = "DATE_FORMAT(create_time,'%Y-%m-%d') as time";
        } else {
            $fieldTime = "DATE_FORMAT(create_time,'%Y-%m-%d') as time";
        }
        return $this->search()->where('is_tourist', $isTourist)->when(true, function ($query) use ($time) {
            $time = $time ?: 'today';
            if (strstr($time, '/') !== false) {
                [$year, $month] = explode('/', $time);
                $time = $year . '/' . $month . '/01 00:00:00-' . $year . '/' . $month . '/' . days_in_month($year, $month) . ' 23:59:59';
            }
            time_model($query, $time, 'create_time');
        })->field([$fieldTime, 'count(*) as number'])->group('time')->order('time', 'asc')->select()->toArray();
    }

    /**
     * @param $where
     * @return \crmeb\basic\BaseModel
     */
    public function getUserModel($where)
    {
        return $this->getModel()->when(isset($where['time']) && $where['time'], function ($query) use ($where) {
            if (strstr($where['time'], '-') !== false) {
                [$startTime, $endTime] = explode('-', $where['time']);
                if ($startTime == $endTime) {
                    $endTime = $endTime . ' 23:59:59';
                }
                $time = $startTime . '-' . $endTime;
            }
            time_model($query, $time, 'create_time');
        })->when(isset($where['nickname']) && $where['nickname'], function ($query) use ($where) {
            if (isset($where['field_key']) && $where['field_key'] && in_array($where['field_key'], ['id', 'phone', 'nickname'])) {
                $query->where($where['field_key'], $where['nickname']);
            } else {
                $query->where('nickname|id|phone', 'like', '%' . $where['nickname'] . '%');
            }
        })->when(isset($where['group_id']) && $where['group_id'], function ($query) use ($where) {
            $query->where('group_id', $where['group_id']);
        })->when(isset($where['label_id']) && $where['label_id'], function ($query) use ($where) {
            $labelId = array_filter(array_map('intval', explode(',', $where['label_id'])), function ($var) {
                return $var > 0;
            });
            $query->whereIn('id', function ($query) use ($labelId) {
                $query->name('chat_user_label_assist')->whereIn('label_id', $labelId)->field(['user_id']);
            });
        })->when(isset($where['sex']) && $where['sex'] !== '', function ($query) use ($where) {
            $query->where('sex', $where['sex']);
        })->when(isset($where['user_type']) && $where['user_type'], function ($query) use ($where) {
            $query->where('type', $where['user_type']);
        })->when(isset($where['is_tourist']) && $where['is_tourist'] !== '', function ($query) use ($where) {
            $query->where('is_tourist', $where['is_tourist'] == 2 ? 0 : $where['is_tourist']);
        });
    }
}
