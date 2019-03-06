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

class Selectmenu extends Base{

    public function menuList()
    {
        $where = array('is_show'=>1);
        $list = DB::name('diyadmin_menu')->where($where)->select();

        $menuList = array();
        foreach ($list as $key => $value) {
            if (!empty($value['eng_name'])) { // 有英文名字 为一级菜单

                $twoWhere = array('is_show'=>1,'pid'=>$value['id']);
                $twoList = DB::name('diyadmin_menu')->where($twoWhere)->select(); //二级菜单
               
                $two = array();
                foreach ($twoList as $twoKey => $twoValue) {

                    $threeWhere = array('is_show'=>1,'pid'=>$twoValue['id']);
                    $threeList = DB::name('diyadmin_menu')->where($threeWhere)->select(); //三级菜单
                    $three = array();
                    foreach ($threeList as $threeKey => $threeValue) {
                        $three[] = array(
                            'name' => $threeValue['name'],
                            'id' => $threeValue['id'],
                            'is_select' => $threeValue['is_select'],
                        );
                    }
                    $two[] = array(
                        'name' =>$twoValue['name'],
                        'id' =>$twoValue['id'],
                        'is_select' =>$twoValue['is_select'],
                        'child' => $three
                    );  
                }
                $menuList[$value['eng_name']] = array();
                $menuList[$value['eng_name']]['name'] = $value['name']; // 一级名称赋值
                $menuList[$value['eng_name']]['id'] = $value['id']; // 一级ID赋值
                $menuList[$value['eng_name']]['is_select'] = $value['is_select']; // 一级名称赋值
                $menuList[$value['eng_name']]['child'] = $two;
            }
        }
        if(IS_POST){
            // 所有要更新的三级id
            $selectId = I('post.');

            $select['is_select'] = 0;
            $where['is_show'] = 1;
            // 清空表
            $data = DB::name('diyadmin_menu')->where('is_show',1)->save($select);

            $dataId = array();
            foreach ($selectId as $key => $value) {

                $three = DB::name('diyadmin_menu')->where(array("is_show"=>1,"id"=>$value))->find(); // 自己
                $two = DB::name('diyadmin_menu')->where(array("is_show"=>1,"id"=>$three['pid']))->find(); //上级

                $dataId[] = $two['pid'];
                $dataId[] = $two['id'];
                $dataId[] = $three['id'];
            }
            $dataUp = array_unique($dataId); // 数组去重
            foreach ($dataUp as $keyD => $valueD) {
                M('diyadmin_menu')->where('id', $valueD)->save(array('is_select'=>1));
            }
            exit($this->success("操作成功",U('Admin/Systems.Selectmenu/menuList')));
        }
        $this->assign('menuList', $menuList);
        return $this->fetch();
    }
}

