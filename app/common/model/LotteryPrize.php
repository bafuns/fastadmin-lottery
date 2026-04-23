<?php

namespace app\common\model;

use think\Model;

class LotteryPrize extends Model
{
    protected $name = 'lottery_prize';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';
    protected $auto = [];
    protected $insert = [];
    protected $update = [];

    // 奖品类型
    const TYPE_REAL = 1;    // 实物
    const TYPE_POINTS = 2;  // 积分
    const TYPE_COUPON = 3;  // 优惠券
    const TYPE_THANKS = 4;  // 谢谢参与

    public function getTypeTextAttr($value, $data)
    {
        $types = [
            self::TYPE_REAL => '实物',
            self::TYPE_POINTS => '积分',
            self::TYPE_COUPON => '优惠券',
            self::TYPE_THANKS => '谢谢参与'
        ];
        return $types[$data['type']] ?? '未知';
    }

    // 关联活动
    public function activity()
    {
        return $this->belongsTo('LotteryActivity', 'activity_id');
    }
}