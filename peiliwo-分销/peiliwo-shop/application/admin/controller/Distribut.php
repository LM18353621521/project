<?php
namespace app\admin\controller;

use app\common\logic\UsersLogic;
use app\common\model\Users;
use think\AjaxPage;
use think\Controller;
use app\admin\logic\GoodsLogic;
use app\admin\logic\SearchWordLogic;
use think\Url;
use think\Config;
use think\Page;
use think\Verify;
use think\Db;

class Distribut extends Base
{
    /**
     * 分销设置
     */
    public function index(){
        /*配置列表*/
        $group_list = [
            'role_info' => '角色分销',
        ];
        $this->assign('group_list',$group_list);
        $inc_type =  I('get.inc_type','role_info');
        $this->assign('inc_type',$inc_type);
        $config = tpCache('distribut');

        if($inc_type == 'role_info'){
            $Ad =  M('user_level');
            $p = $this->request->param('p');
            $res = $Ad->order('level_id')->page($p.',10')->select();
            if($res){
                foreach ($res as $val){
                    $list[] = $val;
                }
            }
            $this->assign('list',$list);
            $count = $Ad->count();
            $Page = new Page($count,10);
            $show = $Page->show();
            $this->assign('page',$show);
        }
//        dump($config);exit;
        $this->assign(  'config',$config);//当前配置项
        //C('TOKEN_ON',false);
        return $this->fetch($inc_type);
    }

    public function prize(){
        $prize_list = [
            'distribution'=>'普通分销奖',
            'recommend'=>'推荐奖',
            'sales_reward'=>'销售额分红奖(季度)',
            'twolayers_reward'=>'二层外分红奖(季度)',
//            'region_prize'=>'区域保护奖(季度)',
        ];
        $level_id = I('id');
        $inc_type =  I('inc_type','distribution');

        $config = distributCache($inc_type.'-'.$level_id);

        if ($inc_type == 'recommend') {


        }

        $config =  db('distribut_system')->order('id desc')->select();
        $configs = array();
        foreach($config as &$val){
            $configs[$val['name']]=$val['value'];
        }

        //等级列表
        $level_list =db('user_level')->where(array('level_id'=>array('gt',0)))->order('level_id asc')->select();
        //当前编辑的等级
        $this_level =db('user_level')->where(array('level_id'=>$level_id))->find();

        $this->assign('prize_list', $prize_list);
        $this->assign('inc_type', $inc_type);

        $this->assign('level_id',$level_id);
        $this->assign('level_list',$level_list);//会员等级
        $this->assign('this_level',$this_level);//会员等级
        $this->assign('config',$configs);//当前配置项

        return $this->fetch('first_prize');
    }

    /*
     * 新增修改配置
     */
    public function ajax_prize()
    {
        $param = I('post.');
        $inc_type = $param['inc_type'];

        $inc_type_level = $param['inc_type']."-".$param['id'];

        //等级列表
        $level_list =db('user_level')->order('level_id asc')->select();

        if($inc_type=='distribution'){
            unset($param['inc_type']);
            unset($param['id']);
            distributCache($inc_type_level,$param,0,array('distribution_level'=>1));
//            if($param['id']<=2){
//                $param1['distribution_first_'.$param['id'].'_0'] = $param['distribution_first_'.$param['id'].'_0'];
//                distributCache($inc_type_level,$param1,0,array('distribution_level'=>1));
//                $param2['distribution_second_'.$param['id'].'_0'] = $param['distribution_second_'.$param['id'].'_0'];
//                distributCache($inc_type_level,$param2,0,array('distribution_level'=>2));
//            }else{
//                $param1 =array();
//                $param2 =array();
//                foreach($level_list as $value){
//                    if($param['distribution_first_'.$param['id'].'_'.$value['level_id']]){
//                        $param1['distribution_first_'.$param['id'].'_'.$value['level_id']] = $param['distribution_first_'.$param['id'].'_'.$value['level_id']];
//                    }
//                }
//                if(empty($param1)){
//                    distributCache($inc_type_level,$param1,0,array('distribution_level'=>1));
//                }
//
//            }

            $param1 = $param['distribution_'];
//            distributCache($inc_type,$param,0,array('distribution_level'=>1));

        }
        unset($param['inc_type']);
        unset($param['id']);
        distributCache($inc_type_level,$param,0,array('distribution_level'=>1));

        die;












        unset($param['inc_type']);
        $logo = explode('-',$inc_type);
        if (in_array('recommended_prize',$logo)){
            unset($param['recommendgrade']);
            $params[] = serialize($param);
            $param = $params;
        }
        // 判断奖项，对应数组序列化存
        if (in_array('general_prize', $logo)){
            $param['general_prize_level'] = serialize($param['general_prize_level']);
        } elseif( in_array('special_prize', $logo)) {
            $param['special_prize_level'] = serialize($param['special_prize_level']);
        } elseif( in_array('management_prize', $logo)) {
            $param['management_prize_level'] = serialize($param['management_prize_level']);
        }
        distributCache($inc_type,$param,0,1);
        exit(json_encode(['code' => 1]));
    }


    //分销商品列表
    public function goods_list()
    {
        $GoodsLogic = new GoodsLogic();
        $brandList = $GoodsLogic->getSortBrands();
        $categoryList = $GoodsLogic->getSortCategory();
        $this->assign('categoryList', $categoryList);
        $this->assign('brandList', $brandList);
        return $this->fetch();
    }

    public function ajaxGoodsList()
    {
        $where = ' 1 = 1 '; // 搜索条件
        I('intro') && $where = "$where and " . I('intro') . " = 1";
        I('brand_id') && $where = "$where and brand_id = " . I('brand_id');
        (I('is_on_sale') !== '') && $where = "$where and is_on_sale = " . I('is_on_sale');
        $cat_id = I('cat_id');
        // 关键词搜索
        $key_word = I('key_word') ? trim(I('key_word')) : '';
        if ($key_word) {
            $where = "$where and (goods_name like '%$key_word%' or goods_sn like '%$key_word%')";
        }
        if ($cat_id > 0) {
            $grandson_ids = getCatGrandson($cat_id);
            $where .= " and cat_id in(" . implode(',', $grandson_ids) . ") "; // 初始化搜索条件
        }
        $where .= " and commission > 0 ";
        $count = M('Goods')->where($where)->count();
        $Page = new AjaxPage($count, 20);
        $show = $Page->show();
        $order_str = "`{$_POST['orderby1']}` {$_POST['orderby2']}";
        $goodsList = M('Goods')->where($where)->order($order_str)->limit($Page->firstRow . ',' . $Page->listRows)->select();

        $catList = D('goods_category')->select();
        $catList = convert_arr_key($catList, 'id');
        $this->assign('catList', $catList);
        $this->assign('goodsList', $goodsList);
        $this->assign('page', $show);
        return $this->fetch();
    }

    /**
     * 分销设置
     */
    public function set()
    {
        header("Location:" . U('Admin/Systems/index', array('inc_type' => 'distribut')));
        exit;
    }

    /**分销关系
     * @return mixed
     */
    public function tree()
    {
        $where = 'is_distribut = 1';
        if ($this->request->param('user_id')) $where = "user_id = '{$this->request->param('user_id')}'";
        $list = M('users')->where($where)->select();
        $this->assign('list', $list);
        return $this->fetch();
    }

    /**
     * 获取某个人下级元素
     */
    public function ajax_lower()
    {
        $id = $this->request->param('id');
        $userlevel = $this->request->param('userlevel');
        $userlevel_field = '';
        if ($userlevel == "first_leader") {
            $userlevel_field = "second_leader";
        } else if ($userlevel == "second_leader") {
            $userlevel_field = "third_leader";
        }
        $where = '';
        if ($userlevel == 'first_leader') $where .= "first_leader =" . $id;
        if ($userlevel == 'second_leader') $where .= "second_leader =" . $id;
        if ($userlevel == 'third_leader') $where .= "third_leader =" . $id;
        $list = M('users')->where($where)->select();
        $_list = array();
        foreach ($list as $key => $val) {
            $_t = $val;
            $_t['user_level'] = $userlevel_field;
            $_list[] = $_t;
        }
        $this->assign('list', $_list);
        return $this->fetch();
    }

    //分销商列表
    public function distributor_list()
    {
        return $this->fetch();
    }

    /**
     * 会员列表
     */
    public function distributorajaxindex()
    {
        // 搜索条件
        $condition = array();
        I('mobile') ? $condition['mobile'] = I('mobile') : false;
        I('email') ? $condition['email'] = I('email') : false;
        I('first_leader') && ($condition['first_leader'] = I('first_leader')); // 查看一级下线人有哪些
        I('second_leader') && ($condition['second_leader'] = I('second_leader')); // 查看二级下线人有哪些
        I('third_leader') && ($condition['third_leader'] = I('third_leader')); // 查看三级下线人有哪些
        $sort_order = I('order_by') . ' ' . I('sort');
        $condition['is_distribut'] = 1;
        $model = M('users');
        $count = $model->where($condition)->count();
        $Page = new AjaxPage($count, 10);
        //  搜索条件下 分页赋值
        foreach ($condition as $key => $val) {
            $Page->parameter[$key] = urlencode($val);
        }
        $userList = $model->where($condition)->order($sort_order)->limit($Page->firstRow . ',' . $Page->listRows)->select();

        $show = $Page->show();
        $this->assign('userList', $userList);
        $this->assign('level', M('user_level')->getField('level_id,level_name'));
        $this->assign('page', $show);// 赋值分页输出
        $this->assign('pager', $Page);
        return $this->fetch();
    }

    //分成日志
    public function rebate_log()
    {

        return $this->fetch();
    }

    public function ajax_rebate_log(){
        $input = I('');
        // dump($input);die;
        $search_key = $input['search_key'];//搜索方式
        $search_value = $input['search_value'];//input框的值
        $type = $input['type'];
        $where =  array();
        if($search_key && $search_value){
            // if($search_key == 'user_id'){
            //     $userdata = M('users')->where(['mobile' => $search_value])->field(array('user_id'))->select();
            //     $user_string = '';
            //     foreach ($userdata as $k => $v) {
            //          $user_string = $v['user_id'].',';
            //     }
            //     $where[$search_key] =  array('in',$user_string);
            // else {
                $where[$search_key] =  array('like','%'.$search_value.'%');//字段名=。。。
            // }
        }

        if($type != null){
            $where['type'] = $type;
        }

        $count = M('rebate_log')->where($where)->count();
        $Page = new AjaxPage($count, 10);
//        $lists = M('rebate_log')->order("field(status,2,1,0,3,4)")->where($where)->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $lists = M('rebate_log')->order("order_id desc,id asc")->where($where)->limit($Page->firstRow . ',' . $Page->listRows)->select();
        // dump($lists);die;
        $_lists = array();
        foreach ($lists as $key => $val) {
            $_t = $val;
            $_t['user_name'] = M('users')->where(['user_id'=>$val['user_id']])->value('nickname');
            // $status_string = ['未付款','已付款','等待分成(已收货)','已分成','已取消'];
            $status_string =[0=>"<font color='#DC143C'>未付款</font>",1=>"<font color='#436EEE'>已付款</font>",2=>"<font color='#00CD00'>等待分成(已收货)</font>",3=>"<font color='#969696'>已分成</font>",4=>"<font color='#BFEFFF'>已取消</font>"];
            if($val['can_distribution'] == 1){
                $status_string[1] = "<font color='#00CD00'>等待分成(已付款)</font>";
            }
            // $_t['is_off'] = $val['status'] == 3 ? "<font color='red'>已分成</font>" : '确定分成';
            $_t['statuss'] = $status_string[$_t['status']] ;
            $_t['yesurl'] = url('Distribut/yesurl', array('id' => $val['id']));
            $_lists[] = $_t;
        }
         $show = $Page->show();

        // dump($_lists);
        $this->assign('page', $show);// 赋值分页输出
        $this->assign('pager', $Page);
        $this->assign('lists', $_lists);
        return $this->fetch();
    }

    //确定分成
    public function yesurl(){
        $id = input('id/d');
        $rebate = Db::name('rebate_log')->where(['id'=>$id])->find();
        //判断分成时间状态
        $state_time =  $rebate['can_distribution'] == 1 ? 'pay_time' : 'confirm';
        $affirm_msg = $rebate['can_distribution'] == 1 ? '付款后' : '确认收货后';
        if(!$rebate)
            $this->error('该分成记录不存在');
        if($rebate['status'] >= 3 && $rebate['status'] < $rebate['can_distribution'])
            $this->error('不能修改分成记录');

        $date=$rebate['type']==2? tpCache('distribut.give_vip_date'): tpCache('distribut.date') ;

        if(!$rebate[$state_time] || (time() - $rebate[$state_time]) <= $date * 86400)
            $this->error($affirm_msg.'，未达到规定时间进行确认分成。'.$date.'天');

        Db::startTrans();

        //更新记录
        $data['confirm_time'] = time();
        $data['status'] = 3;
        $result = Db::name('rebate_log')->where(['id'=>$id])->update($data);
        //更新分成金额
        $user_logic = new UsersLogic();

        switch($rebate['type']){
            case 0:
                $desc="分佣获得余额-分销奖";
                $type=1;
                break;
            case 1:
                $desc="分佣获得余额-推荐奖";
                $type=2;
                break;
            case 2:
                $desc="赠送名额-返钱";
                $type=6;
                break;
        }

        $rebate_log = ['desc'=>$desc,'order_sn'=>$rebate['order_sn'],'order_id'=>$rebate['order_id'],'type'=>$type];
        $set_user = $user_logic->setAccountOrPoints($rebate['user_id'],'account',$rebate['money'],$rebate_log);
        //更新用户所累积的分佣金额
        $user = Users::get($rebate['user_id']);
        $user->distribut_money += $rebate['money'];
        $user_add = $user->save();

        //添加账户金额记录
        if($set_user !== true || !$result || !$user_add)
        {
            Db::rollback();
            $this->error('操作失败，稍后再试！');
        }

        Db::commit();
        $this->Success('操作成功');
    }

}