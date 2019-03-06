<?php

namespace app\mobile\controller;
use think\Controller;
use think\Db;


class Download extends Controller
{
    public function index()
    {
        return $this->fetch();
    }

    //APP下载页面
    public function app_description(){

        $data = db::name('management')->where(array('type'=>1))->find();
        if(strpos($_SERVER['HTTP_USER_AGENT'], 'iPhone')||strpos($_SERVER['HTTP_USER_AGENT'], 'iPad')){
            $data['link'] = $data['ios_link'];
            $data['qr_code'] = $data['android_code'];
        }else if(strpos($_SERVER['HTTP_USER_AGENT'], 'Android')) {
            $data['link'] = $data['android_link'];
            $data['qr_code'] = $data['ios_code'];
        }else{

        }
        $data['details'] = htmlspecialchars_decode($data['details']);

        $this->assign('data',$data);
        return $this->fetch();
    }
}