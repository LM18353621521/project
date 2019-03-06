<?php

namespace app\vpay\controller;
use app\common\logic\JssdkLogic;
use Think\Db;
use think\Page;
use app\common\logic\CartLogic;

class Index extends Sign {


    public function index(){
        if(IS_POST){

        }else{
            // 轮播图
            $account=parent::get("account");
            if(empty($account)){
                $this->redirect(U("Vpay/Login/login"));
            }
            $show = M('show')->field("title,url,img")->order("sort asc")->limit(2)->select();
            $this->assign('showImg', $show);
            $this->assign('userInfo', $account);
            return $this->fetch();
        }
    }


    public function usermessage()
    {
        $id = parent::get("account")['id'];
        $member = M("member")
            ->alias("t")
            ->field("t.*,b.*")
            ->join('vpay_level b', 't.level = b.level_id', 'LEFT')
            ->where(array('id'=>$id))
            ->find();
        //ajax获取金额
        $system= tpCache('vpay_spstem');
        $user=M("member")->where(array("id"=>$id))->find();
        if(empty($user['signtime']) || date("Y-m-d", strtotime($user['signtime'])) != date("Y-m-d", time())){
            $sign_balance=$user['integral']*$system['signRate'];
            $res=M("member")->where(array("id"=>$id))->save(array("sign_balance"=>$sign_balance));//首页之前添加签到余额
            //$member['sign_balance']=$sign_balance+$user['other_balance'];//静态释放的余额+余额变更和积分兑换的
            $member['sign_balance']=bcadd($sign_balance, $user['other_balance'], 2);
        }
        if(date('Y-m-d',strtotime($member['sign_time']))==date('Y-m-d')){
            $member['sign']=true;//已签到
        }else{
            $member['sign']=false;
        }
        if ($member) {
            $this->ajaxSuccess($member);
        } else {
            $this->ajaxError('请登录！');
        }
    }
    /**
     * 签到积分释放
     */
    public function sign_balance()
    {
        if (!empty(parent::get("LAST_ADD_SIGN")) && time() - parent::get("LAST_ADD_SIGN") <= 10) $this->ajaxError();
        parent::set("LAST_ADD_SIGN", time());

        $user = parent::getAccount();
        Db::startTrans();
        if($user['vip_balance'] > 0){
            $user['integral'] = $user['vip_balance'] + $user['integral'];
            $integrallog=integrallog('', $user['id'],$user['vip_balance'],9,$user['integral']-$user['vip_balance'],$user['integral']);//vip积分log
        }
        $static_balance=bcadd($user['balance'], $user['sign_balance'], 2);
        $balance=bcadd($static_balance, $user['other_balance'], 2);
        $integral= bcadd($user['sign_balance'], $user['other_balance'], 2);

        if($user['integral']>0 && $user['integral']<$integral){//有积分但是不够扣的，改为0；
            $balance = $balance - ($integral - $user['integral']);//扣完所有积分，剩余释放不返
            $reduceItg=$user['integral'];//减少积分
            $remainitg=0;//剩余积分
        }else if($user['integral']>0 && $user['integral']>$integral){
            $reduceItg=$integral;//减少积分
            $remainitg=$user['integral']-$integral;//剩余积分
        }else{//积分为0的时候
            $balance = $balance - $integral;
            $reduceItg=0;
            $remainitg=0;
        }
        $data=array(
            "balance"=>$balance,
            "sign_balance"=>0,
            "other_balance"=>0,
            "vip_balance"=>0,
            "sign_time"=>now_datetime(),
            'integral' => $remainitg,
        );
        $res = M("member")->where(array("id"=>$user['id']))->save($data);
        if($balance>=0.01){
            $add_log = balancelog($res, $user['id'],$reduceItg, $type=5, $before=$user['balance'], $after=$balance);
        }else{
            $add_log=1;
        }
        if($reduceItg>=0.01){
            $inte_log=integrallog($res, $user['id'],-$reduceItg,4,$user['integral'],$remainitg);//返佣积分log
        }else{
            $inte_log=1;
        }
        //$inte_log = integrallog($res, $user['id'],-$user['sign_balance'], $type=4, $before=$user['integral'], $after=$user['integral'] - $user['sign_balance']);
        if (empty($res) || empty($add_log) || empty($inte_log)) {
            Db::rollback();
            $this->ajaxError('签到失败！');
        } else {
            Db::commit();
            $this->ajaxSuccess("签到成功！");
        }

    }

    /**
     * 签到积分释放
     */
    public function signOut()
    {
        $user = parent::get("account");
        $data=array(
            "sign_balance"=>0,
            "sign_time"=>now_datetime()
        );
        $res = M("member")->where(array("id"=>$user['id']))->save($data);
        if (empty($res)) {
            $this->ajaxError('签到失败！');
        } else {
            $this->ajaxSuccess("签到成功！");
        }
    }
}