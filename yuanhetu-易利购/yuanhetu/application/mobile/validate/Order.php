<?php

namespace app\mobile\validate;

use think\Validate;

/**
 * 用户分销验证器
 * Class Distribut
 * @package app\mobile\validate
 */
class Order extends Validate
{
    //验证规则
    protected $rule = [
        'order_type'                 =>'require|max:3',
        'suppliers_id'                    =>'require',
        'plan_id'                    =>'require',
        'username'                   =>'require',
        'phone'                      =>'require|max:11',
        'address'                    =>'require',
    ];

    //错误信息
    protected $message  = [
        'suppliers_id.require'    => '请选择门店',
        'order_type.require'      => '请选择服务方式',
        'order_type.max'          => '订单异常',
        'plan_id.require'         => '订单异常',
        'username.require'        => '请填写完整的联系信息',
        'phone.require'           => '请填写完整的联系信息',
        'address.require'         => '请填写完整的联系信息',
        'phone.max'               => '请填写有效的联系方式',
    ];

}