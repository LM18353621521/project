<?php

namespace app\mobile\controller;

use think\Request;
use think\db;

class Apply extends MobileBase{
    /**
     * 用户申请升级角色等级的页面
     * @return mixed
     * Author:Faramita
     */
    public function apply_form(){
        $user_id = session('user')['user_id'];
        //判断当前用户是否有申请
        $check = db('user_apply')->where(['user_id'=>$user_id,'status'=>0,'handle_time'=>0])->find();
        if($check){
            $this->error('您已有处于审核状态的申请，请耐心等待工作人员审核');
        }
        //获取所有有申请条件的角色id
        $QualificationLogic = new \app\common\logic\QualificationLogic();
        $roles = db('distribut_system')
            ->where(['inc_type'=>$QualificationLogic::CATE_OPERATE,'value'=>['NEQ',0],'name'=>'application'])
            ->order('level_id ASC')
            ->group('level_id')->column('level_id');
        $role = implode(',',$roles);
        $level_info = db('user_level')->where(['level_id'=>['IN',$role]])->select();
        //获取代理区域配置
        foreach($level_info as $k => $val){
            if($val['region_code'] && $val['level_id'] == 5){
                $region_level = $val['region_code'];
                break;
            }
        }
        if($region_level){
            $region = db('region')->where(['level'=>1])->select();
            //省代理
            $region_content = "<select name='region_code_province' onchange='get_city(this)' id='province'>";
            foreach($region as $k => $val){
                $region_content .= "<option value='".$val['id']."'>".$val['name']."</option>";
            }
            $region_content .= "</select>";
            if($region_level >= 2){
                //市代理
                $region_content .= "<select name='region_code_city' onchange='get_area(this)' id='city'>";
                $region_content .= "</select>";
            }
            if($region_level == 3){
                //区代理
                $region_content .= "<select name='region_code_district' id='district'>";
                $region_content .= "</select>";
            }
            $this->assign('region_content', $region_content);
        }
        $this->assign('level_info',$level_info);
        return $this->fetch();
    }

    /**
     * ajax提交添加/更新申请信息
     * Author:Faramita
     */
    public function ajax_opera_apply(){
        if ($this->request->method() == 'POST') {
            $user_id = session('user')['user_id'];
            $data = input('post.');
            //验证数据
            $QualificationLogic = new \app\common\logic\QualificationLogic();
            if($data['level'] == 4){
                //体验店验证
                $check_buy = $QualificationLogic->validate_qualification($data['level'],$user_id,[$QualificationLogic::CATE_BUY]);
                if(!$check_buy){
                    $this->ajaxReturn(['status'=>-1,'msg'=>'您需要购买体验店产品才能申请成为体验店','data'=>'']);
                }
                $use_region = db('region')->select();
                foreach($use_region as $k => $val){
                    $region_arr[$val['id']] = $val['name'];
                }
                //拼接门店地址
                $data['store_address'] = $region_arr[$data['province']].$region_arr[$data['city']].$region_arr[$data['district']].$data['address'];
                unset($data['region_code_province'],$data['region_code_city'],$data['region_code_district']);
            }elseif($data['level'] == 5){
                //代理验证
                if($data['region_code_district']){
                    $data['region_code'] = $data['region_code_district'];
                }elseif($data['region_code_city']){
                    $data['region_code'] = $data['region_code_city'];
                }elseif($data['region_code_province']){
                    $data['region_code'] = $data['region_code_province'];
                }
                $result = $QualificationLogic->validate_region_proxy($data['region_code']);
                if(!$result){
                    $this->ajaxReturn(['status'=>-1,'msg'=>'当前选择区域已存在代理','data'=>'']);
                    return;
                }
                unset($data['region_code_province'],$data['region_code_city'],$data['region_code_district'],$data['store_img'],$data['payment_voucher']);
            }else{
                unset($data['region_code_province'],$data['region_code_city'],$data['region_code_district'],$data['store_img'],$data['payment_voucher']);
            }
            $data['user_id'] = $user_id;
            $data['status'] = 0;//未审核
            $data['validate_status'] = 0;//未验证
            $data['apply_time'] = time();
            $data['handle_time'] = 0;
            //判断申请表
            $check_update = db('user_apply')->where(['user_id'=>$user_id,'level'=>$data['level'],'status'=>2])->find();
            if($check_update){
                $operate_apply = db('user_apply')->where(['apply_id'=>$check_update['apply_id']])->update($data);
            }else{
                $operate_apply = db('user_apply')->insert($data);
            }
            if($operate_apply){
                $this->ajaxReturn(['status'=>1,'msg'=>'申请已提交，请耐心等候审核。','data'=>'']);
                return;
            }else{
                $this->ajaxReturn(['status'=>-1,'msg'=>'提交出错了，请检查网络是否错误。','data'=>'']);
                return;
            }
        }else{
            $this->ajaxReturn(['status'=>-1,'msg'=>'错误的提交方式','data'=>'']);
        }
    }

    /**
     * 每个区域只可能拥有一个代理,判断当前选中的所有区域是否有代理
     * Author:Faramita
     */
    public function check_region_proxy_only(){
        $region_code = input('region_code');
        $ignore_code = input('ignore_code')?input('ignore_code'):0;
        $QualificationLogic = new \app\common\logic\QualificationLogic();
        $result = $QualificationLogic->validate_region_proxy($region_code,$ignore_code);
        if($result){
            $this->ajaxReturn(['status'=>1,'msg'=>'当前区域尚没有代理','data'=>'']);
        }else{
            $this->ajaxReturn(['status'=>-1,'msg'=>'当前区域已存在代理','data'=>'']);
        }
    }

    /**
     * 成功页面
     * @return mixed
     * Author:Faramita
     */
    public function apply_success(){
        return $this->fetch();
    }

    public function  test(){
//        $first = Db::name('users')->where(['first_leader' => 8, 'level' => 2])-> count();
//        $sencond = Db::name('users')->where(['second_leader' => 8, 'level' => 2])-> count();
//        $third = Db::name('users')->where(['third_leader' => 8, 'level' => 2])-> count();

         $logic = new \app\common\logic\DistributPrizeLogic();

//        $logic->setUserId(11);
//        $order['order_id'] = 248;
        $logic->region_city_prize();
//        $logic->region_partner_prize();
        // $logic->management_prize();
//        echo 1;die;
//        dump( $user);die;

    }

    public function prize(){
        $action = input('act');
        if ($action == 'GDZPJT') {
            $Distribut = new \app\common\logic\DistributPrizeLogic();
            $prize =  I('get.action',0);
            switch ($prize) {
                case 'market':
                    $Distribut->market_settle_prize();
                    break;
                case 'management':
                    $Distribut->management_prize();
                    break;
                case 'city':
                    $Distribut->region_city_prize();
                    break;
                case 'partner':
                    $Distribut->region_partner_prize();
                    break;
                default:
                    break;
            }
        }
    }

}