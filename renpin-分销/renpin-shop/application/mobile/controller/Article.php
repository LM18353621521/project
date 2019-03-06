<?php

namespace app\mobile\controller;
use think\Db;

class Article extends MobileBase
{
    /**
     * 文章内容页
     */
    public function detail()
    {
        $article_id = input('article_id/d', 1);
        $article = Db::name('article')->where("article_id", $article_id)->find();
        $this->assign('article', $article);
        return $this->fetch();
    }


    /**
     * 文章内容页
     */
    public function notice_level()
    {
        //判断登录和等级
        $user = session('user');
        $user = M('users')->where(array('user_id'=>$user['user_id']))->find();
        if(empty($user)){
            $this->redirect(U('Mobile/User/login'));
        }else{

            $apply = M('user_level_up')->where(array('user_id'=>$user['user_id'],'status'=>0))->find();
            $level = M('user_level')->column('level_id,level_name');
            $this->assign('user',$user);
            $this->assign('apply',$apply);
            $this->assign('level',$level);
        }
        return $this->fetch();
    }

    /**
     * 用户申请升级角色等级的页面
     * @return mixed
     * Author:Faramita
     */
    public function apply(){
        $user = session('user');

        $user = M('users')->where(array('user_id'=>$user['user_id']))->find();

        if(empty($user)){
            $this->redirect(U('user/login'));
            header("location:" . U('Mobile/User/login'));
        }

        $has_apply =M('user_level_up')->where(array('user_id'=>$user['user_id'],'status'=>0))->find();

        if($has_apply||$user['level']==3){
            $this->redirect(U('notice_level'));
        }

        if(IS_POST){
            $data = input('post.');

            if($data['level_id']==2){
                unset($data['province']);
                unset($data['city']);

                if($user['level']>=2){
                    $level = M('user_level')->where(array('level_id'=>2))->find();
                    $return = ['status' => 0, 'msg' => '申请提交失败，您已经是'.$level['level_name'].'身份', 'result' => ''];
                    $this->ajaxReturn($return);
                }

            }


            if($data['level_id']==3){
                $has_agent = M('users')->where(array('agent_city'=>$data['city'],'level'=>3))->find();
                if($has_agent){
                    $return = ['status' => 0, 'msg' => '申请提交失败，该城市已有代理人', 'result' => ''];
                    $this->ajaxReturn($return);
                }
            }


            $data['user_id'] = $user['user_id'];
            $data['create_time'] = time();
            $res = M('user_level_up')->add($data);

            if(!$res){
                $this->ajaxReturn(['status'=>0,'msg'=>'申请提交失败，请稍后重试！','data'=>'']);
            }
            $this->ajaxReturn(['status'=>1,'msg'=>'申请提交成功！','data'=>'']);

        }


        if($has_apply){
            $city =  M('region')->where(array('parent_id'=>$has_apply['province']))->select();
            $this->assign('city',$city);
        }

        $province = M('region')->where(array('parent_id'=>0))->select();
        $this->assign('province',$province);

        $level_list = M('user_level')->where(array('level_id'=>array('gt',1)))->select();
        $this->assign('level_list',$level_list);


        $this->assign('user',$user);
        $this->assign('apply',$has_apply);
        return $this->fetch();
    }

    public function test(){
        $res = Db::name('withdrawals')->select();
        dump($res);
    }

}