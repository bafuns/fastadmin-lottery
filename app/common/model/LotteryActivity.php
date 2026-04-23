<?php

namespace app\common\model;

use think\Model;

class LotteryActivity extends Model
{
    protected $name = 'lottery_activity';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';
    protected $deleteTime = 'delete_time';
    protected $auto = [];
    protected $insert = [];
    protected $update = [];

    // 获取活动状态文本
    public function getStatusTextAttr($value, $data)
    {
        $now = time();
        if ($data['is_active'] == 0) {
            return '已禁用';
        }
        if ($data['start_time'] > $now) {
            return '未开始';
        }
        if ($data['end_time'] < $now) {
            return '已结束';
        }
        return '进行中';
    }

    // 关联奖品
    public function prizes()
    {
        return $this->hasMany('LotteryPrize', 'activity_id');
    }
}