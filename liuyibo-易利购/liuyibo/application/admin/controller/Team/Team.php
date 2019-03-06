<?php

namespace app\admin\controller\Team;

use app\admin\controller\Base;
use app\admin\model\Goods;
use app\admin\model\GoodsActivity;
use app\common\logic\TeamActivityLogic;
use app\common\model\Order;
use app\common\model\TeamActivity;
use app\common\model\TeamFollow;
use app\common\model\TeamFound;
use think\Loader;
use think\Db;
use think\Page;

class Team extends Base
{
	public function index()
	{
        $parse_type = array('0' => '满额打折', '1' => '满额优惠金额', '2' => '满额送积分', '3' => '满额送优惠券');
        $level = M('user_level')->select();
        if ($level) {
            foreach ($level as $v) {
                $lv[$v['level_id']] = $v['level_name'];
            }
        }
        $count = M('team_activity')->count();
        $Page = new Page($count, 10);
        $show = $Page->show();
        $team_list = M('team_activity')->limit($Page->firstRow . ',' . $Page->listRows)->select();
//        if ($res) {  //获得适用范围（用户等级）
//            foreach ($res as $val) {
//                if (!empty($val['group']) && !empty($lv)) {
//                    $val['group'] = explode(',', $val['group']);
//                    foreach ($val['group'] as $v) {
//                        $val['group_name'] .= $lv[$v] . ',';
//                    }
//                }
//                $prom_list[] = $val;
//            }
//        }
        $this->assign('pager', $Page);// 赋值分页输出
        $this->assign('page', $show);// 赋值分页输出
        $this->assign("parse_type", $parse_type);
        $this->assign('list', $team_list);
        return $this->fetch();
	}

	/**
	 * 拼团详情
	 * @return mixed
	 */
	public function info()
	{
        $level = M('user_level')->select();
        $this->assign('level', $level);
        $team_id = I('team_id');
        if ($team_id > 0) {
            $info = M('team_activity')->where("team_id=$team_id")->find();
            $Goods = new Goods();
            $prom_goods = $Goods->with('SpecGoodsPrice')->where(['prom_id' => $team_id, 'prom_type' => 3])->select();
            $this->assign('team_goods', $prom_goods);
            $this->assign('teamActivity', $info);
        }
        return $this->fetch();
	}

	/**
	 * 保存
	 * @throws \think\Exception
	 */
	public function save(){
        $team_id = I('id/d');
        $data = I('post.');
        $title = input('act_name');
        //$promGoods = $data['goods'];
        $promGoodsValidate = Loader::validate('Team');
        if(!$promGoodsValidate->batch()->check($data)){
            $return = ['status' => 0,'msg' =>'操作失败',
                'result'    => $promGoodsValidate->getError(),
                'token'       =>  \think\Request::instance()->token(),
            ];
            $this->ajaxReturn($return);
        }
//        $goods_ids = [];
//        $item_ids = [];
//        foreach ($promGoods as $goodsKey => $goodsVal) {
//            if (array_key_exists('goods_id', $goodsVal)) {
//                array_push($goods_ids, $goodsVal['goods_id']);
//            }
//            if (array_key_exists('item_id', $goodsVal)) {
//                $item_ids = array_merge($item_ids, $goodsVal['item_id']);
//            }
//        }
        if ($team_id) {
            M('team_activity')->where(['id' => $team_id])->save($data);
            $last_id = $team_id;
            adminLog("管理员修改了拼团 " . $title);
        } else {
            $last_id = M('team_activity')->add($data);
            adminLog("管理员添加了拼团 " . $title);
        }
        M("goods")->where(['prom_id' => $team_id, 'prom_type' => 6])->save(array('prom_id' => 0, 'prom_type' => 0));
        M("goods")->where("goods_id", "in", $data['goods_id'])->save(array('prom_id' => $last_id, 'prom_type' => 6));
        Db::name('spec_goods_price')->where(['prom_id' => $team_id, 'prom_type' => 6])->update(['prom_id' => 0, 'prom_type' => 0]);
        Db::name('spec_goods_price')->where('item_id','IN',$data['team_id'])->update(['prom_id' => $last_id, 'prom_type' => 6]);
        $this->ajaxReturn(['status'=>1,'msg'=>'编辑拼团活动成功','result']);
	}

	/**
	 * 删除拼团
	 */
	public function delete(){
	header("Content-type: text/html; charset=utf-8");
exit("功能正在开发中。。。");
	}

	/**
	 * 确认拼团
	 * @throws \think\Exception
	 */
	public function confirmFound(){
	header("Content-type: text/html; charset=utf-8");
exit("功能正在开发中。。。");
	}

	/**
	 * 拼团退款
	 */
	public function refundFound(){
	header("Content-type: text/html; charset=utf-8");
exit("功能正在开发中。。。");
	}

	/**
	 * 拼团抽奖
	 */
	public function lottery(){
	header("Content-type: text/html; charset=utf-8");
exit("功能正在开发中。。。");
	}

	/**
	 * 拼团订单
	 */
	public function team_list()
	{
        return $this->fetch();
	}

    /**
	 * 拼团订单
	 */
	public function team_found()
	{
        return $this->fetch();
	}

	/**
	 * 拼团订单详情
	 * @return mixed
	 */
	public function team_info()
	{
	header("Content-type: text/html; charset=utf-8");
exit("功能正在开发中。。。");
	}

	//拼团订单
	public function order_list(){
        return $this->fetch();
	}

	/**
	 * 团长佣金
	 */
	public function bonus(){
        return $this->fetch();
	}

	public function doBonus(){
	header("Content-type: text/html; charset=utf-8");
    exit("功能正在开发中。。。");
	}

    public function ad(){
        header("Content-type: text/html; charset=utf-8");
        exit("功能正在开发中。。。");
    }
}
