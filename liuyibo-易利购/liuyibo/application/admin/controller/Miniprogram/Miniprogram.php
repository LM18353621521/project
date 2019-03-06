<?php

namespace app\admin\controller\Miniprogram;

use think\Db;
use think\AjaxPage;
use think\Page;
use think\Cache;
use app\common\model\WxMaterial;
use app\common\logic\WechatLogic;
use app\common\model\WxTplMsg;
use app\common\util\WechatUtil;
use app\common\model\WxNews;
use app\common\model\WxReply;
use app\admin\controller\Base;

class Miniprogram extends Base
{
    private $wx_user;

    function __construct()
    {
        parent::__construct();
        $this->wx_user = Db::name('wx_user')->find();
    }

    public function index(){
        //$wechat_list = M('wx_user')->select();
        //$this->assign('lists',$wechat_list);
        //return $this->fetch();
        $wx_user = M('wx_user')->find();
        header("Location:".U('Miniprogram.Miniprogram/setting',['id'=>$wx_user['id']]));
        exit;
    }


    public function setting()
    {
        $id = I('get.id');
        $wechat = M('wx_user')->where(array('id'=>$id))->find();
        if(empty($wechat)){
            return $this->error('请先在公众号配置添加公众号，才能进行微信菜单管理', U('Admin/Wechat/index'));
        }
        if(IS_POST){
            $post_data = input('post.');
            $post_data['web_expires'] = 0;

            $row = M('wx_user')->where(array('id'=>$id))->update($post_data);
            if($row){
                $wx_config = M('wx_user')->find(); //获取微信配置
                Cache::set('weixin_config',$wx_config,0);
                exit($this->success("修改成功"));
            }else{
                exit($this->error("修改失败"));
            }
        }
        //$apiurl = 'http://'.$_SERVER['HTTP_HOST'].'/index.php?m=api&c=Wechat&a=handleMessage';
        $apiurl = 'http://'.$_SERVER['HTTP_HOST'].'/index.php?m=Home&c=Miniprogram.Miniprogram&a=index';

        $this->assign('wechat',$wechat);
        $this->assign('apiurl',$apiurl);

        return $this->fetch();
    }

}