<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\model\LotteryActivity;
use app\common\model\LotteryPrize;
use app\common\model\LotteryRecord;
use app\common\model\LotteryChance;
use think\Db;
use think\Exception;

/**
 * 抽奖接口
 */
class Lottery extends Api
{
    protected $noNeedLogin = ['index', 'draw', 'getChance'];
    protected $noNeedRight = ['*'];

    public function _initialize()
    {
        parent::_initialize();
        $this->userModel = new \app\common\model\User();
    }

    /**
     * 获取当前活动信息
     */
    public function index()
    {
        $activity = LotteryActivity::where('is_active', 1)
            ->where('start_time', '<=', time())
            ->where('end_time', '>=', time())
            ->find();

        if (!$activity) {
            $this->error('当前没有进行中的抽奖活动');
        }

        // 获取奖品列表
        $prizes = LotteryPrize::where('activity_id', $activity['id'])
            ->where('is_active', 1)
            ->order('sort', 'asc')
            ->select();

        // 获取当前用户抽奖次数
        $chance = [];
        if ($this->auth->id) {
            $chance = LotteryChance::where('activity_id', $activity['id'])
                ->where('user_id', $this->auth->id)
                ->find();
        }

        $result = [
            'activity' => $activity,
            'prizes' => $prizes,
            'user_chance' => [
                'total_remain' => $chance ? ($activity['total_chances'] - $chance['total_used']) : ($activity['total_chances'] > 0 ? $activity['total_chances'] : 999),
                'today_remain' => $chance ? ($activity['daily_chances'] - $chance['today_used']) : ($activity['daily_chances'] > 0 ? $activity['daily_chances'] : 999),
                'last_date' => $chance['last_date'] ?? null
            ]
        ];

        $this->success('ok', $result);
    }

    /**
     * 获取用户剩余抽奖次数
     */
    public function getChance()
    {
        $activity_id = input('activity_id', 0, 'intval');

        if (!$this->auth->id) {
            $this->error('请先登录');
        }

        $activity = LotteryActivity::get($activity_id);
        if (!$activity) {
            $this->error('活动不存在');
        }

        $chance = LotteryChance::where('activity_id', $activity_id)
            ->where('user_id', $this->auth->id)
            ->find();

        // 检查日期是否需要重置每日次数
        $today = date('Y-m-d');
        if ($chance && $chance['last_date'] != $today) {
            $chance->today_used = 0;
            $chance->last_date = $today;
            $chance->save();
        }

        $total_remain = $activity['total_chances'] > 0 
            ? $activity['total_chances'] - ($chance ? $chance['total_used'] : 0) 
            : 999;
        $today_remain = $activity['daily_chances'] > 0 
            ? $activity['daily_chances'] - ($chance ? $chance['today_used'] : 0) 
            : 999;

        $this->success('ok', [
            'total_remain' => max(0, $total_remain),
            'today_remain' => max(0, $today_remain),
        ]);
    }

    /**
     * 执行抽奖
     */
    public function draw()
    {
        $activity_id = input('activity_id', 0, 'intval');

        if (!$this->auth->id) {
            $this->error('请先登录');
        }

        // 获取活动
        $activity = LotteryActivity::get($activity_id);
        if (!$activity) {
            $this->error('活动不存在');
        }

        if (!$activity['is_active']) {
            $this->error('活动已禁用');
        }

        $now = time();
        if ($activity['start_time'] > $now) {
            $this->error('活动尚未开始');
        }

        if ($activity['end_time'] < $now) {
            $this->error('活动已结束');
        }

        // 检查抽奖次数
        $chance = LotteryChance::where('activity_id', $activity_id)
            ->where('user_id', $this->auth->id)
            ->find();

        $today = date('Y-m-d');

        // 首次抽奖或日期变更
        if (!$chance) {
            $chance = LotteryChance::create([
                'activity_id' => $activity_id,
                'user_id' => $this->auth->id,
                'total_used' => 0,
                'today_used' => 0,
                'last_date' => $today,
                'create_time' => $now,
                'update_time' => $now
            ]);
        }

        // 检查日期变更
        if ($chance['last_date'] != $today) {
            $chance->today_used = 0;
            $chance->last_date = $today;
        }

        // 检查总次数限制
        if ($activity['total_chances'] > 0 && $chance['total_used'] >= $activity['total_chances']) {
            $this->error('您的抽奖次数已用完');
        }

        // 检查每日次数限制
        if ($activity['daily_chances'] > 0 && $chance['today_used'] >= $activity['daily_chances']) {
            $this->error('今日抽奖次数已用完');
        }

        // 执行抽奖
        Db::startTrans();
        try {
            // 获取可用奖品
            $prizes = LotteryPrize::where('activity_id', $activity_id)
                ->where('is_active', 1)
                ->where(function($query) use ($today) {
                    $query->whereOr([
                        ['total_stock', '=', 0],
                        ['total_stock', '>', 0]
                    ]);
                })
                ->order('sort', 'asc')
                ->select();

            // 计算权重
            $weights = [];
            $totalWeight = 0;
            foreach ($prizes as $prize) {
                // 检查库存
                if ($prize['total_stock'] > 0) {
                    $stock = Db::name('lottery_prize')
                        ->where('id', $prize['id'])
                        ->value('total_stock');
                    if ($stock <= 0) {
                        continue;
                    }
                }

                // 检查每日库存
                if ($prize['daily_stock'] > 0) {
                    $dailyUsed = LotteryRecord::where('activity_id', $activity_id)
                        ->where('prize_id', $prize['id'])
                        ->whereTime('create_time', 'today')
                        ->count();
                    if ($dailyUsed >= $prize['daily_stock']) {
                        continue;
                    }
                }

                $weights[$prize['id']] = $prize['weight'];
                $totalWeight += $prize['weight'];
            }

            if (empty($weights)) {
                throw new Exception('奖品已全部发放完毕');
            }

            // 按权重抽奖
            $rand = mt_rand(1, $totalWeight);
            $cumulative = 0;
            $prizeId = 0;

            foreach ($weights as $id => $weight) {
                $cumulative += $weight;
                if ($rand <= $cumulative) {
                    $prizeId = $id;
                    break;
                }
            }

            $prize = LotteryPrize::get($prizeId);

            // 减少库存
            if ($prize['total_stock'] > 0) {
                LotteryPrize::where('id', $prizeId)->setDec('total_stock');
            }

            // 记录抽奖
            $record = LotteryRecord::create([
                'activity_id' => $activity_id,
                'user_id' => $this->auth->id,
                'prize_id' => $prize['id'],
                'prize_name' => $prize['name'],
                'prize_type' => $prize['type'],
                'prize_value' => $prize['value'],
                'ip' => $this->request->ip(),
                'device' => $this->request->header('user-agent'),
                'status' => 1,
                'create_time' => $now
            ]);

            // 更新用户抽奖次数
            $chance->total_used = $chance['total_used'] + 1;
            $chance->today_used = $chance['today_used'] + 1;
            $chance->save();

            // 实物奖品需要发放(这里预留接口)
            if ($prize['type'] == 1) {
                // TODO: 实物奖品发放逻辑
            } elseif ($prize['type'] == 2) {
                // 积分奖品自动发放
                $this->addPoints($this->auth->id, intval($prize['value']));
            }

            Db::commit();

            $this->success('抽奖成功', [
                'record_id' => $record->id,
                'prize' => [
                    'id' => $prize->id,
                    'name' => $prize->name,
                    'type' => $prize->type,
                    'value' => $prize->value,
                    'image' => $prize->image
                ],
                'remain' => [
                    'total' => $activity['total_chances'] > 0 ? $activity['total_chances'] - $chance->total_used : 999,
                    'today' => $activity['daily_chances'] > 0 ? $activity['daily_chances'] - $chance->today_used : 999
                ]
            ]);

        } catch (\Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
    }

    /**
     * 获取用户抽奖记录
     */
    public function records()
    {
        $page = input('page', 1, 'intval');
        $limit = input('limit', 10, 'intval');

        if (!$this->auth->id) {
            $this->error('请先登录');
        }

        $list = LotteryRecord::where('user_id', $this->auth->id)
            ->order('create_time', 'desc')
            ->page($page, $limit)
            ->select();

        $this->success('ok', $list);
    }

    /**
     * 发放积分(需要根据实际积分表调整)
     */
    protected function addPoints($userId, $points)
    {
        // 这里需要根据实际的积分表来调整
        // 示例: 
        // \app\common\model\User::where('id', $userId)->setInc('score', $points);
        return true;
    }
}