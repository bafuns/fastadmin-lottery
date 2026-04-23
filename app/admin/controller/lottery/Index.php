<?php

namespace app\admin\controller\lottery;

use app\common\controller\Backend;
use app\common\model\LotteryActivity;
use app\common\model\LotteryPrize;
use app\common\model\LotteryRecord;
use app\common\model\LotteryChance;
use think\Db;

/**
 * 抽奖管理
 */
class Index extends Backend
{
    protected $model = null;
    protected $searchFields = 'id,title';

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new LotteryActivity();
    }

    /**
     * 查看列表
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            
            $list = $this->model
                ->where($where)
                ->where('delete_time', 0)
                ->order($sort, $order)
                ->paginate($limit);

            $result = ['total' => $list->total(), 'rows' => $list->items()];
            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 获取活动列表
     */
    public function getActivities()
    {
        $list = $this->model
            ->where('delete_time', 0)
            ->order('id', 'desc')
            ->select();
        
        return json(['code' => 1, 'msg' => 'ok', 'data' => $list]);
    }

    /**
     * 获取奖品列表
     */
    public function getPrizes()
    {
        $activity_id = input('activity_id', 0, 'intval');
        
        if (!$activity_id) {
            return json(['code' => 0, 'msg' => '请选择活动', 'data' => []]);
        }
        
        $prizeModel = new LotteryPrize();
        $list = $prizeModel
            ->where('activity_id', $activity_id)
            ->order('sort', 'asc')
            ->select();
        
        return json(['code' => 1, 'msg' => 'ok', 'data' => $list]);
    }

    /**
     * 获取抽奖记录
     */
    public function getRecords()
    {
        $page = input('page', 1, 'intval');
        $limit = input('limit', 20, 'intval');
        $user_id = input('user_id', 0, 'intval');
        $status = input('status', 0, 'intval');
        
        $recordModel = new LotteryRecord();
        $where = [];
        
        if ($user_id) {
            $where['user_id'] = $user_id;
        }
        if ($status) {
            $where['status'] = $status;
        }
        
        $list = $recordModel
            ->where($where)
            ->order('id', 'desc')
            ->page($page, $limit)
            ->select();
        
        $total = $recordModel->where($where)->count();
        
        return json(['code' => 1, 'msg' => 'ok', 'data' => ['list' => $list, 'total' => $total]]);
    }

    /**
     * 编辑活动
     */
    public function editActivity()
    {
        $id = input('id', 0, 'intval');
        
        if ($this->request->isPost()) {
            $params = $this->request->post('row/a');
            if ($params) {
                $params['start_time'] = strtotime($params['start_time']);
                $params['end_time'] = strtotime($params['end_time']);
                
                if ($id) {
                    $result = $this->model->save($params, ['id' => $id]);
                } else {
                    $params['create_time'] = time();
                    $params['update_time'] = time();
                    $result = $this->model->save($params);
                }
                
                if ($result !== false) {
                    return json(['code' => 1, 'msg' => '操作成功']);
                }
                return json(['code' => 0, 'msg' => '操作失败']);
            }
            return json(['code' => 0, 'msg' => '参数错误']);
        }
        
        $row = $id ? $this->model->get($id) : [];
        $this->view->assign('row', $row);
        return $this->view->fetch();
    }

    /**
     * 切换活动状态
     */
    public function toggleActivity()
    {
        $id = input('id', 0, 'intval');
        $is_active = input('is_active', 1, 'intval');
        
        $result = $this->model->where('id', $id)->update(['is_active' => $is_active]);
        
        if ($result !== false) {
            return json(['code' => 1, 'msg' => '操作成功']);
        }
        return json(['code' => 0, 'msg' => '操作失败']);
    }

    /**
     * 删除活动
     */
    public function deleteActivity()
    {
        $id = input('id', 0, 'intval');
        
        // 删除活动下的奖品和记录
        Db::name('lottery_prize')->where('activity_id', $id)->delete();
        Db::name('lottery_record')->where('activity_id', $id)->delete();
        Db::name('lottery_chance')->where('activity_id', $id)->delete();
        
        $result = $this->model->where('id', $id)->update(['delete_time' => time()]);
        
        if ($result !== false) {
            return json(['code' => 1, 'msg' => '删除成功']);
        }
        return json(['code' => 0, 'msg' => '删除失败']);
    }

    /**
     * 编辑奖品
     */
    public function editPrize()
    {
        $id = input('id', 0, 'intval');
        $activity_id = input('activity_id', 0, 'intval');
        
        if ($this->request->isPost()) {
            $params = $this->request->post('row/a');
            if ($params) {
                $prizeModel = new LotteryPrize();
                
                if ($id) {
                    $result = $prizeModel->save($params, ['id' => $id]);
                } else {
                    $params['activity_id'] = $activity_id;
                    $params['create_time'] = time();
                    $params['update_time'] = time();
                    $result = $prizeModel->save($params);
                }
                
                if ($result !== false) {
                    return json(['code' => 1, 'msg' => '操作成功']);
                }
                return json(['code' => 0, 'msg' => '操作失败']);
            }
            return json(['code' => 0, 'msg' => '参数错误']);
        }
        
        $prizeModel = new LotteryPrize();
        $row = $id ? $prizeModel->get($id) : [];
        $this->view->assign('row', $row);
        return $this->view->fetch();
    }

    /**
     * 删除奖品
     */
    public function deletePrize()
    {
        $id = input('id', 0, 'intval');
        
        $result = Db::name('lottery_prize')->where('id', $id)->delete();
        
        if ($result !== false) {
            return json(['code' => 1, 'msg' => '删除成功']);
        }
        return json(['code' => 0, 'msg' => '删除失败']);
    }

    /**
     * 发放奖品
     */
    public function receivePrize()
    {
        $id = input('id', 0, 'intval');
        
        $recordModel = new LotteryRecord();
        $record = $recordModel->get($id);
        
        if (!$record) {
            return json(['code' => 0, 'msg' => '记录不存在']);
        }
        
        if ($record->status != 1) {
            return json(['code' => 0, 'msg' => '奖品已发放或已过期']);
        }
        
        // 更新状态
        $record->status = 2;
        $record->receive_time = time();
        $result = $record->save();
        
        // TODO: 根据奖品类型发放实物/积分/优惠券
        
        if ($result !== false) {
            return json(['code' => 1, 'msg' => '发放成功']);
        }
        return json(['code' => 0, 'msg' => '发放失败']);
    }
}