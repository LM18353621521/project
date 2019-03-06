<?php

namespace app\admin\controller\Plugins;

use app\admin\controller\Base;
use app\common\logic\OrderLogic;
use app\common\logic\TeamActivityLogic;
use app\common\model\Order;
use app\common\model\TeamActivity;
use app\common\model\TeamFollow;
use app\common\model\TeamFound;
use think\Loader;
use think\Db;
use think\Page;

class Plugins extends Base
{
    public function index()
    {
        return $this->fetch();
    }
    /*
     * 店铺装修
     * */
    public function renovationIndex(){


        $data = Db::name('diypage')->where('')->select();
        $this->assign('data',$data);
        return $this->fetch();
    }


    public function ajaxRenovationList(){


        $data = Db::name('diypage')->where('')->select();

        return $this->ajaxReturn($data);
    }


    public function ajaxRenovation(){


        $data = Db::name('diypage')->where('status',1)->find();

        return $this->ajaxReturn($data);
    }
    /*
     * 装修保存
     * */
    public function save(){

        $data = I("post.");
        if($data['id'] > 0){
            $data['lastedittime'] = time();
            $result = Db::name('diypage')->where('id',$data['id'])->save($data);
        }else{
            $data['createtime'] = time();
            $result = Db::name('diypage')->save($data);
        }
        $this->ajaxReturn(['msg'=>'保存成功！']);
    }
    /*
     * 装修删除
     * */
    public function delete(){
        $id = I("id/d");
        $result = Db::name('diypage')->where('id',$id)->delete();
        $result ? $this->ajaxReturn(['msg'=>'删除成功！']) : $this->ajaxReturn(['msg'=>'删除失败！']);
    }
    /*
     * 店铺装修
     *
     * */
    public function renovation(){
        $id = I("id/d");
        $data = Db::name('diypage')->where('id',$id)->find();
        $this->assign('data',$data);
        return $this->fetch();
    }
}
