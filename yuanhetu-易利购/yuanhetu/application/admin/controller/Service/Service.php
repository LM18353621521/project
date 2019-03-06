<?php
/**操作维修分类
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/6/13
 * Time: 9:39
 */
namespace  app\admin\controller\Service;

use  app\admin\controller\Base;
use app\admin\logic\GoodsLogic;
use app\admin\model\RepairCat;
use app\admin\model\RepairSpec;
use app\admin\logic\SearchWordLogic;
use think\AjaxPage;
use think\Loader;
use think\Page;
use think\Db;

class Service extends Base{


      public function run(){
          Db::query('truncate __PREFIX__repair_spec');
          Db::query('truncate __PREFIX__repair_cat');
          Db::query('truncate __PREFIX__repair_fault');
      }


    /**分類列表
     * @return mixed
     */
      public function serviceclassify(){

          $GoodsLogic = new GoodsLogic();
          $cat_list = $GoodsLogic->repair_cat_list('repair_cat');
          $this->assign('cat_list',$cat_list);
          return $this->fetch();

      }
     /**属性列表
     * @return mixed
     */
      public function servicespec(){

          $spec_count = DB::name('repair_spec')->count();
          $page = new Page($spec_count, 10);
          $show = $page->show();
          $spec_list = DB::name('repair_spec')
              ->alias('s')
              ->field('s.*,a.id as cat_id,a.name as cat_name')
              ->join('repair_cat a', 'a.id = s.cat_id', 'LEFT')
              ->limit($page->firstRow, $page->listRows)
              ->select();
          $this->assign('list', $spec_list);
          $this->assign('page', $show);
          $this->assign('pager', $page);
          return $this->fetch();

      }
    /**故障列表
     * @return mixed
     */
    public function servicefault(){
        $spec_count = DB::name('repair_fault')->count();
        $page = new Page($spec_count, 10);
        $show = $page->show();
        $fault_list = DB::name('repair_fault')
            ->alias('s')
            ->field('s.*,a.id as spec_id,a.name as spec_name')
            ->join('repair_spec a', 'a.id = s.spec_id', 'LEFT')
            ->limit($page->firstRow, $page->listRows)
            ->select();
        $this->assign('list', $fault_list);
        $this->assign('page', $show);
        $this->assign('pager', $page);
        return $this->fetch();

    }

    /**
     * 添加分類
     */
    public function addrepaircat(){

        $GoodsLogic = new GoodsLogic();
        if(IS_GET)
        {
            $goods_category_info = M('repair_cat')->where('id='.I('GET.id',0))->find();

            $level_cat = $GoodsLogic->find_cat_parent_cat($goods_category_info['id'],'repair_cat'); // 获取分类默认选中的下拉框
            $cat_list = M('repair_cat')->where("parent_id = 0")->select(); // 已经改成联动菜单
            $this->assign('level_cat',$level_cat);
            $this->assign('cat_list',$cat_list);
            $this->assign('goods_category_info',$goods_category_info);

            return $this->fetch('_addCat');
            exit;
        }
        $GoodsCategory = D('RepairCat'); //

        $type = I('id') > 0 ? 2 : 1; // 标识自动验证时的 场景 1 表示插入 2 表示更新
        //ajax提交验证
        if(I('is_ajax') == 1)
        {
            // 数据验证
            $validate = \think\Loader::validate('GoodsCategory');
            if(!$validate->batch()->check(input('post.')))
            {
                $error = $validate->getError();
                $error_msg = array_values($error);
                $return_arr = array(
                    'status' => -1,
                    'msg' => $error_msg[0],
                    'data' => $error,
                );
                $this->ajaxReturn($return_arr);
            } else {
               $GoodsCategory->data(input('post.'),true); // 收集数据

                $GoodsCategory->parent_id = I('parent_id_1');

                input('parent_id_2') && ($GoodsCategory->parent_id = input('parent_id_2'));
                //编辑判断

                if($type == 2){
                    $children_where = array(
                        'parent_id_path'=>array('like','%_'.I('id')."_%")
                    );
                    $children = M('repair_cat')->where($children_where)->max('level');
                    if (I('parent_id_1')) {
                        $parent_level = M('repair_cat')->where(array('id' => I('parent_id_1')))->getField('level', false);
                        if (($parent_level + $children) > 4) {
                            $return_arr = array(
                                'status' => -1,
                                'msg'   => $parent_level.'商品分类最多为5级'.$children,
                                'data'  => '',
                            );
                            $this->ajaxReturn($return_arr);
                        }
                    }
                    if (I('parent_id_2')) {
                        $parent_level = M('repair_cat')->where(array('id' => I('parent_id_2')))->getField('level', false);
                        if (($parent_level + $children) > 4) {
                            $return_arr = array(
                                'status' => -1,
                                'msg'   => '商品分类最多为5级',
                                'data'  => '',
                            );
                            $this->ajaxReturn($return_arr);
                        }
                    }
                }

                //查找同级分类是否有重复分类
                $par_id = ($GoodsCategory->parent_id > 0) ? $GoodsCategory->parent_id : 0;
                $same_cate = M('repair_cat')->where(['parent_id'=>$par_id , 'name'=>$GoodsCategory['name']])->find();

                if($same_cate){
                    $return_arr = array(
                        'status' => 0,
                        'msg' => '同级已有相同分类存在',
                        'data' => '',
                    );
                    $this->ajaxReturn($return_arr);
                }

                if ($GoodsCategory->id > 0 && $GoodsCategory->parent_id == $GoodsCategory->id) {
                    //  编辑
                    $return_arr = array(
                        'status' => 0,
                        'msg' => '上级分类不能为自己',
                        'data' => '',
                    );
                    $this->ajaxReturn($return_arr);
                }

                if($GoodsCategory->id > 0 && $GoodsCategory->parent_id == $GoodsCategory->id)
                {
                    //  编辑
                    $return_arr = array(
                        'status' => -1,
                        'msg'   => '上级分类不能为自己',
                        'data'  => '',
                    );
                    $this->ajaxReturn($return_arr);
                }

                /*if($GoodsCategory->commission_rate > 100)
                {
                    //  编辑
                    $return_arr = array(
                        'status' => -1,
                        'msg'   => '分佣比例不得超过100%',
                        'data'  => '',
                    );
                    $this->ajaxReturn($return_arr);
                }*/
//                $repair_spec = new RepairSpec();
                if ($type == 2)
                {
                    $GoodsCategory->isUpdate(true)->save(); // 写入数据到数据库
                    $GoodsLogic->refresh_repaircat(I('id'));
                }
                else
                {

                    $GoodsCategory->save(); // 写入数据到数据库
                    $insert_id = $GoodsCategory->getLastInsID();
                    $GoodsLogic->refresh_repaircat($insert_id);


                   /* if($GoodsCategory->parent_id == 0){

                     $lastid = Db::query("select MAX(id) as c from __PREFIX__repair_spec");
                     $lastid = $lastid[0]['c'] + 1;
                     $data['spec_id']              =$insert_id;
                     $data['sort_order']           =$GoodsCategory->sort_order;
                     $data['add_time']             =time();
                     $data['is_show']              =1;
                     $data['level']                =1;
                     $data['name']                 =$GoodsCategory->name;
                     $data['mobile_name']          =$GoodsCategory->mobile_name;
                     $data['parent_id_path']       ='0_'.$lastid;
                     Db::name('repair_spec')->insert($data);
                    }*/

                }
                $return_arr = array(
                    'status' => 1,
                    'msg'   => '操作成功',
                    'data'  => array('url'=>U('Admin/Service.Service/serviceclassify')),
                );
                $this->ajaxReturn($return_arr);

            }
        }
    }


    /**
     * 添加属性
     */
    public function addrepairspec(){

        if(IS_POST){
            $data = I('post.');
            $specId = $data['id'];
            if(!$specId){
                $data['add_time'] = time();
                $r = M('repair_spec')->insertGetId($data);
            }else{
                unset($data['id']);
                $r = M('repair_spec')->where('id', $specId)->save($data);
            }
            exit($r ? $this->success("操作成功", U('Admin/Service.Service/servicespec')) : $this->error("操作失败", U('Admin/Service.Service/servicespec')));
        }
        $spec_id = I('get.spec_id/d', 0);
        if ($spec_id) {
            $info = DB::name('repair_spec')
                ->alias('s')
                ->field('s.*,a.id as cat_id,a.name as cat_name')
                ->join('repair_cat a', 'a.id = s.cat_id', 'LEFT')
                ->where(array('s.id' => $spec_id))
                ->find();
            $this->assign('info', $info);
        }
        $cat_info = M('repair_cat')->where(array('parent_id' => 0, 'level' => 1))->select();
        $this->assign("cat_info",$cat_info);
        return $this->fetch('_addSpec');

    }



    /**
     * 添加故障
     */
    public function addrepairfault(){

        if(IS_POST){
            $data = I('post.');
            $specId = $data['id'];
            if(!$specId){
                $data['add_time'] = time();
                $r = M('repair_fault')->insertGetId($data);
            }else{
                unset($data['id']);

                $r = M('repair_fault')->where('id', $specId)->save($data);
            }

            exit($r ? $this->success("操作成功", U('Admin/Service.Service/servicefault')) : $this->error("操作失败", U('Admin/Service.Service/servicefault')));
        }
        $spec_id = I('get.spec_id/d', 0);
        if ($spec_id) {
            $info = DB::name('repair_fault')
                ->alias('s')
                ->field('s.*,a.id as spec_id,a.name as spec_name')
                ->join('repair_spec a', 'a.id = s.spec_id', 'LEFT')
                ->where(array('s.id' => $spec_id))
                ->find();
            $this->assign('info', $info);
        }
        $cat_info = M('RepairCat')->field('id,name')->where(array('parent_id'=>0))->select();
        foreach($cat_info as $k=>$v){
            $cat_info[$k]['spec_info'] = M('RepairSpec')->field('id,name')->where(array('cat_id'=>$v['id']))->select();
        }

        $this->assign("cat_info",$cat_info);
        return $this->fetch('_addFault');
    }




      //删除维修分类表
    public function delRepairCat(){
        $ids = I('post.ids','');
        empty($ids) &&  $this->ajaxReturn(['status' => -1,'msg' =>"非法操作！",'data'  =>'']);
        // 判断子分类
        $count = Db::name("repair_cat")->where("parent_id = {$ids}")->count("id");
        $count > 0 && $this->ajaxReturn(['status' => -1,'msg' =>'该分类下还有分类不得删除!']);
        // 判断是否存在
        $spec = Db::name("repair_spec")->where("cat_id={$ids}")->select();
        $spec && $this->ajaxReturn(['status' => -1,'msg' =>'该分类下还有属性分类不得删除!']);
        // 删除分类
        DB::name('repair_cat')->where('id',$ids)->delete();
        $this->ajaxReturn(['status' => 1,'msg' =>'操作成功','url'=>U('Admin/Service.Service/serviceclassify')]);
    }


    /**
     * 删除属性表数据
     */
    public function delspec(){
        $ids = I('post.suppliers_id','');
        empty($ids) &&  $this->ajaxReturn(['status' => -1,'msg' =>"非法操作！",'data'  =>'']);
        // 判断是否存在
        $spec = Db::name("repair_fault")->where("spec_id={$ids}")->select();
        $spec && $this->ajaxReturn(['status' => -1,'msg' =>'该属性下还有故障分类不得删除!']);

        // 删除分类
        DB::name('repair_spec')->where('id',$ids)->delete();
        $this->ajaxReturn(['status' => 1,'msg' =>'操作成功','url'=>U('Admin/Service.Service/servicespec')]);

    }



    /**
     * 删除故障表数据
     */
    public function delfault(){
        $ids = I('post.suppliers_id','');

        // 删除分类
        DB::name('repair_fault')->where('id',$ids)->delete();
        $this->ajaxReturn(['status' => 1,'msg' =>'操作成功','url'=>U('Admin/Service.Service/servicefault')]);
    }



    public function cat_list($cat_id = 0, $selected = 0, $re_type = true, $level = 0)
    {
        global $cat_category, $cat_category2;
        $sql = "SELECT * FROM  __PREFIX__repair_cat  ORDER BY parent_id , sort_order ASC";
        $cat_category = DB::query($sql);
        $cat_category = convert_arr_key($cat_category, 'id');
        foreach ($cat_category AS $key => $value)
        {
            if($value['level'] == 1)
                $this->cat_tree($value['id']);
        }

        return $cat_category2;
    }

    /**
     * 获取指定id下的 所有分类
     * @global type $cat_category 所有商品分类
     * @param type $id 当前显示的 菜单id
     * @return 返回数组 Description
     */
    public function cat_tree($id)
    {
        global $cat_category, $cat_category2;
        $cat_category2[$id] = $cat_category[$id];
        foreach ($cat_category AS $key => $value){
            if($value['parent_id'] == $id)
            {
                $this->cat_tree($value['id']);
                $cat_category2[$id]['have_son'] = 1; // 还有下级
            }
        }
    }




}