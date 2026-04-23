<?php

namespace app\common\model;

use think\Model;

class LotteryRecord extends Model
{
    protected $name = 'lottery_record';
    protected $autoWriteTimestamp = false;
    protected $createTime = 'create_time';
    protected $updateTime = false;
    protected $auto = [];
    protected $insert = [];
    protected $update = [];

    // 状态
    const STATUS_PENDING = 1;   // 待领取
    const STATUS_RECEIVED = 2;  // 已领取
    const STATUS_EXPIRED = 3;   // 已过期

    public function getStatusTextAttr($value, $data)
    {
        $status = [
            self::STATUS_PENDING => '待领取',
            self::STATUS_RECEIVED => '已领取',
            self::STATUS_EXPIRED => '已过期'
        ];
        return $status[$data['status']] ?? '未知';
    }

    // 关联活动
    public function activity()
    {
        return $this->belongsTo('LotteryActivity', 'activity_id');
    }

    // 关联用户
    public function user()
    {
        return $this->belongsTo('app\common\model\User', 'user_id');
    }
}