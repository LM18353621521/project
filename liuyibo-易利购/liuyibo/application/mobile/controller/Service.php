<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/6/20
 * Time: 15:33
 */
namespace app\mobile\controller;

use think\Db;
use think\Page;
use think\AjaxPage;
use think\Session;




class Service extends MobileBase{

    public function index(){
        //{分类 品牌 故障类型}数据
       $repairs = Db::name("repair_cat")->where(array('parent_id'=>0))->select();
       $brand = Db::name('repair_cat')
           ->field('id,name,image')
           ->where(array('parent_id'=>1,'is_show'=>1))
           ->order('sort_order')
           ->limit('6')->select();

       $fault_type = Db::name('repair_spec')
           ->field('id,name,image')
           ->where(array('cat_id'=>1,'is_show'=>1))
           ->order('sort_order')
           ->select();

       $this->assign('parent_id',1);
       $this->assign('fault_type',$fault_type);
       $this->assign('brand',$brand);
       $this->assign('repairs',$repairs);
       return $this->fetch();
   }

    /**第一步 选择手机品牌
     * @return mixed
     */
   public function select_brand($p2=''){
       $parent_id = I('get.id/d');

       if($p2){
           $parents = M('RepairCat')
               ->field('parent_id')
               ->where(array('id'=>$p2))
               ->find();
           $parent_id = $parents['parent_id'];
       }
       $title = M('RepairCat')->field('name')->where(array('id'=>$parent_id))->find();

       setcookie("title",$title['name'], time()+1800);


       $result = M('RepairCat')
           ->field('id,name,image')
           ->where(array('parent_id'=>$parent_id,'is_show'=>1))
           ->order('sort_order')
           ->select();


           $this->assign('brand',$result);
           $this->assign('title',$title['name']);
          return $this->fetch('select_brand');
   }

    /**
     * 选择手机型号
     */
    public  function select_model_now($p2=''){
        $parent_id = I('get.id/d');
        $act = I('act');
        $plan_id = I('plan_id');

        if($plan_id){
            $plan_date = M('RepairPlan')->where(array('id'=>$plan_id))->find();
            if(!$plan_date) header("Location: /mobile/Service/index");
            $cat_date = M('RepairCat')->field('parent_id_path')->where(array('id'=>$plan_date['cat_id']))->find();
            $cat_id =  explode('_',$cat_date['parent_id_path']);
            $parent_id = $cat_id['2'];
            M('RepairPlan')->where(array('id'=>$plan_id))->delete();
        }



        $parent = M('RepairCat')
            ->field('id,name,parent_id_path')
            ->where(array('parent_id'=>$parent_id,'is_show'=>1))
            ->order('sort_order')
            ->select();

        if($act == 'type'){
            $this->assign('brand',$parent);
            return $this->fetch('select_type');
        }
        $parent_name = M('RepairCat')
            ->field('id,name,parent_id')
            ->where(array('id'=>$parent_id))
            ->find();

        $model_date = M('RepairCat')->field('id,name,image')->where(array('parent_id'=>$parent_name['parent_id'],'is_show'=>1))->select();

        $this->assign('model_date',$model_date);
        $this->assign('title',$_COOKIE['title']);
        $this->assign('brand',$parent);
        $this->assign('parent2',$parent_name);//上一级维修分类
        return $this->fetch();

    }
    /*
     * 选择故障与故障内容
     */
    public function fault_Spec(){
        $id = I('id');
        $act = I('act');
        if($act == fault){
            $fault = M('RepairFault')   //下拉框数据
                ->field('id,name')
                ->where(array('spec_id'=>$id,'is_show'=>1))
                ->order('sort_order')
                ->select();
            $this->assign('fault',$fault);

           return $this->fetch('select_fault');
        }else{
            $id =  explode('_',$id);
            $spec = M('RepairSpec')
                ->field('name,id,image')
                ->where(array('cat_id'=>$id[1],'is_show'=>1))
                ->order('sort_order')
                ->select();
            $this->assign('spec',$spec);
        }



        return $this->fetch('select_spec');

    }




   /**
    *第二部 选择手机型号
    */
  /* public  function select_model($p2=''){
       $parent_id = I('get.id/d');
       if($p2){
           $parent_id = $p2;
       }

       $parent = M('RepairCat')
               ->field('id,name')
               ->where(array('parent_id'=>$parent_id,'is_show'=>1))
               ->order('sort_order')
               ->select();

       $parent_name = M('RepairCat')
               ->field('id,name')
               ->where(array('id'=>$parent_id))
               ->find();

       $this->assign('title',$_COOKIE['title']);
       $this->assign('brand',$parent);
       $this->assign('parent2',$parent_name);//上一级维修分类
       return $this->fetch('select_model');

   }*/

   /**
  *第三部选择故障属性
  */
   /*public function select_brand2($p2='',$p3=''){
       $parent_id    = I('id');
       $parent2      = I('parent2');

       if($p2 && $p3){
           $parent_id = $p3;
           $parent2   = $p2;
       }
       $parent2 = M('RepairCat')
           ->field('id,name')
           ->where(array('id'=>$parent2))
           ->find();

       $parent_path = M('RepairCat')
           ->field('parent_id_path,id,name')
           ->where(array('id'=>$parent_id))
           ->find();
       $parent_id = $parent_path['parent_id_path'];

       $id =  explode('_',$parent_id);//第一类ID

       $parent = M('RepairSpec')
             ->field('name,id,image')
             ->where(array('cat_id'=>$id[1],'is_show'=>1))
             ->order('sort_order')
             ->select();
       $this->assign('title',$_COOKIE['title']);
       $this->assign('brand',$parent);
       $this->assign('parent3',$parent_path);
       $this->assign('parent2',$parent2);
       return $this->fetch('select_brand2');

   }*/

   /**
    * 第四部  维修故障
    */
  /* public function select_brand3(){
       $plan_id = I('plan_id');

       $parent_id    = I('id');
       $parent2      = I('parent2');
       $parent3      = I('parent3');

       if($plan_id){
           $plan_date = M('RepairPlan')->where(array('id'=>$plan_id))->find();
           if(!$plan_date) header("Location: /mobile/Service/index");
           $parent3 = $plan_date['cat_id'];
           $fault_date = M('RepairFault')->field('spec_id')->where(array('id'=>$plan_date['fault_id']))->find();
           $parent_id = $fault_date['spec_id'];
           M('RepairPlan')->where(array('id'=>$plan_id))->delete();

       }

       //修改功能数据
       $parent3 = M('RepairCat')
           ->field('id,name,parent_id')
           ->where(array('id'=>$parent3))
           ->find();
       $parent2 = M('RepairCat')
           ->field('id,name')
           ->where(array('id'=>$parent3['parent_id']))
           ->find();


       $parent = M('RepairFault')   //下拉框数据
           ->field('id,name')
           ->where(array('spec_id'=>$parent_id,'is_show'=>1))
           ->order('sort_order')
           ->select();

       $parent4 = M('RepairSpec')   //上级导航
           ->field('id,name')
           ->where(array('id'=>$parent_id))
           ->find();

       $this->assign('title',$_COOKIE['title']);
       $this->assign('brand',$parent);
       $this->assign('parent2',$parent2);
       $this->assign('parent3',$parent3);
       $this->assign('parent4', $parent4);
       return $this->fetch('select_brand3');

   }*/
    /*
     * 提交维修故障
     * */
   public function order_history(){
       $cat_id = I('cat_id');
       $fault_id = I('fault_id');

       //实例化session类获取user_id
       $UserId = Session::get('user');
       $UserId = $UserId['user_id'];

       empty($UserId) && exit(json_encode(array('status'=>'-100','msg'=>'请先登录')));


       $date = array(
           'user_id'=>$UserId,
           'cat_id'=>$cat_id,
           'fault_id'=>$fault_id,
           'add_time'=>time()
       );
       $id = M('RepairPlan')->add($date);

        if($id){
            setcookie("title", "" , time()-1);
            exit(json_encode(array('status'=>'1','msg'=>'提交成功','id'=>$id)));
        }else{
            exit(json_encode(array('status'=>'-11','msg'=>'操作失败')));
        }

   }


   /**
    * 维修修改
    */
   public function wxChange(){

       $step=I('step');
       if($step==3){
           return $this->select_brand2(I('parent2'),I('parent3'));
       }

       if($step==2){

          return $this->select_model(I('parent2'));
       }

       if($step==1){
          return $this->select_brand(I('parent2'));
       }


   }

   public function brand_fault(){
        $pid = I('cat_id');
        $result = array();

        $brand = Db::name('repair_cat')->field('id,name,image')->where(array('parent_id'=>$pid,'is_show'=>1))->order('sort_order')->limit('6')->select();
        $fault_type = Db::name('repair_spec')->field('id,name,image')->where(array('cat_id'=>$pid,'is_show'=>1))->order('sort_order')->select();

        //if(empty($brand) && empty($fault_type)) return json_encode(array('status'=>'-11','msg'=>'此分类没有数据'));

        $this->assign('fault_type',$fault_type);
        $this->assign('brand',$brand);
        $result['brand'] = $this->fetch('brand');
        $result['fault'] = $this->fetch('fault');
        $result['parent_id'] = $pid;

        return json_encode($result);


   }

    /*
     * 查询门店的运营时间
     * */
    public  function  getSuppliersTime(){
        $suppliers_id = input('get.suppliers_id/d');
        $suppliers_info = Db::name('suppliers')->where(['suppliers_id' => $suppliers_id])->value('business_time');
        if (!$suppliers_info) exit(json_encode(['code' => -1]));

        $result['msg'] = 'ok';
        $result['day_time'] = unserialize($suppliers_info);
        $result['code'] = 1;
        if (!$result['day_time']) $result['code'] = -1;
        exit(json_encode($result));
    }
}