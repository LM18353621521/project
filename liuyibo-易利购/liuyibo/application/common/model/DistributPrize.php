<?php

namespace app\common\model;
use think\Model;
use think\Db;
class DistributPrize extends Model {
    //自定义初始化
    protected static function init()
    {
        //TODO:自定义的初始化
    }


    /**
     * 获取当前角色奖项配置信息
     * @param $inc_type  奖项标识 array
     * @param $level 会员等级id
     * @return mixed
     */
    public function configCache($inc_type,$level)
    {
        $res = Db::name('distribut_system')
            ->field('name,value')
            ->where(['level_id'=> $level ,'inc_type'=>['in',$inc_type]])
            ->select();
        foreach ($res as $k => $val) {
            $config[$val['name']] = $val['value'];
        }
        return $config;
    }
}
