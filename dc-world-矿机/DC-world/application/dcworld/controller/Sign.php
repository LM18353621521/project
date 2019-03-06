<?php

namespace app\dcworld\controller;

use app\common\logic\CartLogic;
use app\common\logic\UsersLogic;
use app\common\model\Users;
use think\Controller;
use think\Session;
use think\Request;
use think\View;
use think\Config;
use think\Response;
use think\exception\DbException;
use think\exception\HttpResponseException;

class Sign extends Base {
    Public function _initialize(){

        parent::_initialize();

        // 没有登录
        $account = $this->getAccount();
        if($this->request->get('_uid')){
            $id = (int) $this->request->get('_uid');
            $member = M("member")->where("(account='".$id."' or id='".$id."') AND isDelete=2")->find();
            parent::setAccount($member);
        }
        if(empty($account)){
            if (IS_AJAX){
                exit( $this->ajaxLoginError("请登录"));
            }else{
                $this->redirect(U("Login/login"));
            }
        }
    }

    // 获取账号信息
    function getAccount(){
        // 获取token
        $memberId = $this->get('account')['id'];
        $member = M('member')->where(array("id"=>$memberId, "isDisable"=>2, "isDelete"=>2))->find();
        if ($member){
            session('account', $member);
        } else {
            session('account', null);
        }
        return $this->get('account');
    }

    // 获取账号ID
    function getAccountId(){
        return $this->getAccount()['id'];
    }
}