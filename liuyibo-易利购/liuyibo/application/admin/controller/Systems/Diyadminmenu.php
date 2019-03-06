<?php
/**后台菜单
 * Created by PhpStorm.
 * User: M
 * Date: 2018/8/10
 * Time: 9:39
 */
namespace  app\admin\controller\Systems;

use  app\admin\controller\Base;
use app\admin\logic\GoodsLogic;
use app\admin\model\RepairCat;
use app\admin\model\RepairSpec;
use app\admin\logic\SearchWordLogic;
use think\AjaxPage;
use think\Loader;
use think\Page;
use think\Db;

class Diyadminmenu extends Base{

    # 菜单列表
    public function adminMenuList(){
        
        $where = "";
        $keyword = I('keyword');
        $where = $keyword ? " name like '%$keyword%' " : "";

        $number = DB::name('diyadmin_menu')->where($where)->count();
        $Page  = new Page($number,15);
        $show = $Page->show();

        $list = DB::name('diyadmin_menu')->where($where)->limit($Page->firstRow.','.$Page->listRows)->select();
        $parentMenu = array();
        foreach ($list as $key => $value) {
            $where = array('id'=>$value['pid']);
            $parent= M('diyadmin_menu')->where('id='.$value['pid'])->find();
            $list[$key]['parentName'] = $parent['name']; // 上级菜单名称
        }
        $this->assign('list',$list);
        $this->assign('number',$number);
        $this->assign('pager',$Page);
        $this->assign('page',$show);// 赋值分页输出
        return $this->fetch();
      }

    # 添加菜单
    public function addAdminMenu(){

        if(IS_POST){
            $data = I('post.');
            $menuId = $data['id'];

            if (!empty($data['cat_id_2'])) {
                $data['pid'] = $data['cat_id_2'];
            }else {
                $data['pid'] = $data['cat_id'];
            }

            if(!$menuId){
                $data['add_time'] = time();
                $data['is_show'] = 1;
                $r = M('diyadmin_menu')->insertGetId($data);
            }else{
                unset($data['id']);
                $r = M('diyadmin_menu')->where('id', $menuId)->save($data);
            }
            exit($r ? $this->success("操作成功", U('Admin/Systems.Diyadminmenu/adminMenuList')) : $this->error("操作失败", U('Admin/Systems.Diyadminmenu/addAdminMenu')));
        }
        $list = DB::name('diyadmin_menu')->select();
        $parentMenu = array(); // 选择上级菜单的数据
        foreach ($list as $key => $value) {
            if (!empty($value['eng_name'])) {
                $parentMenu[] = [
                    'id' => $value['id'],
                    'name' => $value['name'],
                ];
            }
        }
        $this->assign('parentMenu',$parentMenu);            

        $menu_id = I('get.menu_id/d', 0);
        if ($menu_id) {
            $where = array('id'=>$menu_id);
            $info = DB::name('diyadmin_menu')->where($where)->find();
            $this->assign('info', $info);
            $level_cat[2] = $info['pid'] ? $info['pid'] : 0; // 上级ID

            $grandpa = DB::name('diyadmin_menu')->where('id',$info['pid'])->find();
            $level_cat[1] = $grandpa['pid'] ? $grandpa['pid'] : 0; // 上上级ID
        }else{
            $level_cat[2] = 0;
            $level_cat[1] = 0;
        }
        $this->assign('level_cat', $level_cat);   
        return $this->fetch();
    }

    # 删除菜单
    public function delAdminMenu(){

        $id = I('post.menu_id','');
        if (empty($id)) {
            $this->ajaxReturn(['status' => -1,'msg' =>"非法操作！",'data'  =>'']);
        }
        // 判断是否存在
        $diyMenu = Db::name("diyadmin_menu")->where("id={$id}")->select();
        if (empty($diyMenu)) {
            $this->ajaxReturn(['status' => -1,'msg' => '数据不存在','data' => '']);
        }
        // 删除分类
        $result = DB::name('diyadmin_menu')->where('id',$id)->delete();
        if (empty($result)) {
            $this->ajaxReturn(['status' => -1,'msg' =>'操作失败','url'=>U('Admin/Diymenu.Diymenu/diymenuList')]);
          
        }else{
            $this->ajaxReturn(['status' => 1,'msg' =>'操作成功','url'=>U('Admin/Diymenu.Diymenu/diymenuList')]);
        }
    } 
    public function getParent(){
        $parent_id = I('get.parent_id/d'); // 商品分类 父id
        $list = M('diyadmin_menu')->where("pid", $parent_id)->select();

        foreach($list as $k => $v)
        $html .= "<option value='{$v['id']}'>{$v['name']}</option>";
        exit($html);
    }  
}