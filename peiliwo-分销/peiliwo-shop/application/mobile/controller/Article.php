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
        if(empty($user)){
            $this->redirect(U('Mobile/User/login'));
        }else{
            $user = M('users')->where(array('user_id'=>$user['user_id']))->find();
            if($user['level']>1){
                $this->redirect(U('Mobile/Index/index'));
            }
        }
        return $this->fetch();
    }





}