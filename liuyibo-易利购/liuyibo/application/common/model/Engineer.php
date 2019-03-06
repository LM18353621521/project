<?php
/**
 * Created by Lu.
 * User: Administrator
 * Date: 2018/6/13
 * Time: 17:05
 */
namespace app\common\model;

use think\Model;
use think\Db;

/**
 * @package Home\Model
 */
class Engineer extends Model
{
    //获取工程师基本信息
    public function getEngineerBaseInfo($user_id,$type=1){
        $repair_join=Db::name('repair_join')->where(array('user_id'=>$user_id,'type'=>$type))->find();
        return $repair_join;
    }
    //获取取工程师的扩展信息
    public function getEngineerExtendInfo($user_id,$type=1){
        $repair_join=Db::name('repair_join')->where(array('user_id'=>$user_id,'type'=>$type))->find();
        $repair_join_info =Db::name('repair_joininfo')->where(['join_id'=>$repair_join['join_id']])->find();

        //可修改的品牌
        $repair_join_info['repair_cat_list'] =array();

        if($repair_join_info['repair_cat']){
            $repair_join_info['repair_cat_list'] =Db::name('repair_cat')->where('id','in',$repair_join_info['repair_cat'])->select();
        }

        //可修改的故障
        $repair_join_info['repair_fault_list'] =array();
        if($repair_join_info['repair_fault']){
            $repair_join_info['repair_fault_list'] =Db::name('repair_fault')->where('id','in',$repair_join_info['repair_fault'])->select();
        }

        $repair_join_info['where_know'] = unserialize($repair_join_info['where_know']);

        return $repair_join_info;
    }



}