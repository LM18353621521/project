<?php
return  array(
    'system'=>array('name'=>'系统','child'=>array(
                array('name' => '设置','child' => array(
                        array('name'=>'商城设置','act'=>'index','op'=>'Systems','c'=>'Systems'),
                        //array('name'=>'支付方式','act'=>'index1','op'=>'Systems'),
                        array('name'=>'地区&配送','act'=>'region','op'=>'Tools','c'=>'Systems'),
                        array('name'=>'短信模板','act'=>'index','op'=>'SmsTemplate','c'=>'Systems'),
                        //array('name'=>'接口对接','act'=>'index3','op'=>'Systems'),
                        //array('name'=>'验证码设置','act'=>'index4','op'=>'Systems'),
                        //array('name'=>'友情链接','act'=>'linkList','op'=>'Article','c'=>'Systems'),
                        array('name'=>'清除缓存','act'=>'cleanCache','op'=>'Systems','c'=>'Systems'),
                        array('name'=>'自提点','act'=>'index','op'=>'Pickup','c'=>'Systems'),
                        array('name'=>'自定义菜单','act'=>'adminMenuList','op'=>'Diyadminmenu','c'=>'Systems'),
                        array('name'=>'菜单选择','act'=>'menuList','op'=>'Selectmenu','c'=>'Systems'),
                )),
                array('name' => '广告','child' => array(
                        array('name'=>'广告列表','act'=>'adList','op'=>'Ad','c'=>'Article'),
                        array('name'=>'广告位置','act'=>'positionList','op'=>'Ad','c'=>'Article'),
                )),
                array('name' => '权限','child'=>array(
                        array('name' => '管理员列表', 'act'=>'index', 'op'=>'Admin','c'=>'Systems'),
                        array('name' => '角色管理', 'act'=>'role', 'op'=>'Admin','c'=>'Systems'),
                        array('name'=>'权限资源列表','act'=>'right_list','op'=>'Systems','c'=>'Systems'),
                        array('name' => '管理员日志', 'act'=>'log', 'op'=>'Admin','c'=>'Systems'),
                        //array('name' => '供应商列表', 'act'=>'supplier', 'op'=>'Admin'),
                )),
                array('name' => '模板','child'=>array(
                        array('name' => '模板设置', 'act'=>'templateList', 'op'=>'Template','c'=>'Systems'),
//                      array('name' => '手机首页', 'act'=>'mobile_index', 'op'=>'Template','c'=>'Systems'),
                )),
                array('name' => '数据','child'=>array(
                        array('name' => '数据备份', 'act'=>'index', 'op'=>'Tools','c'=>'Systems'),
                        array('name' => '数据还原', 'act'=>'restore', 'op'=>'Tools','c'=>'Systems'),
                        //array('name' => 'ecshop数据导入', 'act'=>'ecshop', 'op'=>'Tools','c'=>'Systems'),
                        //array('name' => '淘宝csv导入', 'act'=>'taobao', 'op'=>'Tools','c'=>'Systems'),
                        //array('name' => 'SQL查询', 'act'=>'log', 'op'=>'Admin','c'=>'Systems'),
                ))
    )),

    'shop'=>array('name'=>'商城','child'=>array(
            array('name' => '商品','child' => array(
                array('name' => '商品列表', 'act' => 'goodsList', 'op' => 'Goods', 'c' => 'Goods'),
                array('name' => '商品分类', 'act' => 'categoryList', 'op' => 'Goods', 'c' => 'Goods'),
                array('name' => '库存日志', 'act' => 'stock_list', 'op' => 'Goods', 'c' => 'Goods'),
                array('name' => '商品模型', 'act' => 'goodsTypeList', 'op' => 'Goods', 'c' => 'Goods'),
                array('name' => '商品规格', 'act' => 'specList', 'op' => 'Goods', 'c' => 'Goods'),
                array('name' => '品牌列表', 'act' => 'brandList', 'op' => 'Goods', 'c' => 'Goods'),
                array('name' => '商品属性', 'act' => 'goodsAttributeList', 'op' => 'Goods', 'c' => 'Goods'),
                array('name' => '评论列表', 'act' => 'index', 'op' => 'Comment', 'c' => 'Goods'),
                array('name' => '商品咨询', 'act' => 'ask_list', 'op' => 'Comment', 'c' => 'Goods'),
                array('name' => '身份产品', 'act' => 'type_identity_list', 'op' => 'Goods', 'c' => 'Goods'),

            )),
            array('name' => '订单','child'=>array(
                    array('name' => '订单列表', 'act'=>'index', 'op'=>'Order', 'c' => 'Order'),
                    array('name' => '虚拟订单', 'act'=>'virtual_list', 'op'=>'Order', 'c' => 'Order'),
                    array('name' => '发货单', 'act'=>'delivery_list', 'op'=>'Order', 'c' => 'Order'),
                    array('name' => '退款单', 'act'=>'refund_order_list', 'op'=>'Order', 'c' => 'Order'),
                    array('name' => '退换货', 'act'=>'return_list', 'op'=>'Order', 'c' => 'Order'),
                    array('name' => '添加订单', 'act'=>'add_order', 'op'=>'Order', 'c' => 'Order'),
                    array('name' => '订单日志','act'=>'order_log','op'=>'Order', 'c' => 'Order'),
                    array('name' => '服务订单','act'=>'to_shop','op'=>'Order', 'c' => 'Order'),
                    array('name' => '寄修订单','act'=>'to_shop_send','op'=>'Order', 'c' => 'Order'),
                    array('name' => '上门订单','act'=>'to_shop_home','op'=>'Order', 'c' => 'Order'),
                    array('name' => '到店订单','act'=>'to_shop_door','op'=>'Order', 'c' => 'Order'),
                    array('name' => '发票管理','act'=>'index', 'op'=>'Invoice', 'c' => 'Invoice'),
                    array('name' => '退款原因','act'=>'refundreasonList', 'op'=>'Refundreason', 'c' => 'Refundreason'),
                    // array('name' => '拼团列表','act'=>'team_list','op'=>'Team'),
                    // array('name' => '拼团订单','act'=>'order_list','op'=>'Team'),
            )),
            array('name' => '促销','child' => array(
                    array('name' => '抢购管理', 'act'=>'flash_sale', 'op'=>'Promotion', 'c' => 'Promotion'),
                    array('name' => '团购管理', 'act'=>'group_buy_list', 'op'=>'Promotion', 'c' => 'Promotion'),
                    array('name' => '优惠促销', 'act'=>'prom_goods_list', 'op'=>'Promotion', 'c' => 'Promotion'),
                    array('name' => '订单促销', 'act'=>'prom_order_list', 'op'=>'Promotion', 'c' => 'Promotion'),
                    array('name' => '优惠券','act'=>'index', 'op'=>'Coupon', 'c' => 'Coupon', 'c' => 'Coupon'),
                    array('name' => '预售管理','act'=>'pre_sell_list', 'op'=>'Promotion', 'c' => 'Promotion'),
                    //array('name' => '拼团管理','act'=>'index', 'op'=>'Team'),
            )),
            array('name' => '统计','child' => array(
                    array('name' => '销售概况', 'act'=>'index', 'op'=>'Report', 'c' => 'Report'),
                    array('name' => '销售排行', 'act'=>'saleTop', 'op'=>'Report', 'c' => 'Report'),
                    array('name' => '会员排行', 'act'=>'userTop', 'op'=>'Report', 'c' => 'Report'),
                    array('name' => '销售明细', 'act'=>'saleList', 'op'=>'Report', 'c' => 'Report'),
                    array('name' => '会员统计', 'act'=>'user', 'op'=>'Report', 'c' => 'Report'),
                    array('name' => '运营概览', 'act'=>'finance', 'op'=>'Report', 'c' => 'Report'),
                    array('name' => '平台支出记录','act'=>'expense_log','op'=>'Report', 'c' => 'Report'),
            )),
            array('name' => '维修','child' => array(
                array('name' => '维修分类', 'act'=>'serviceclassify', 'op'=>'Service', 'c' => 'Service'),
                array('name' => '维修属性', 'act'=>'servicespec', 'op'=>'Service', 'c' => 'Service'),
                array('name' => '维修故障', 'act'=>'servicefault', 'op'=>'Service', 'c' => 'Service'),
                array('name' => '评价', 'act'=>'index', 'op'=>'Evaluate', 'c' => 'Evaluate'),
             )),
    )),
    'user'=>array('name'=>'会员','child'=>array(
        array('name' => '会员','child'=>array(
            array('name'=>'会员列表','act'=>'index','op'=>'User','c'=>'User'),
            array('name'=>'会员等级','act'=>'levelList','op'=>'User','c'=>'User'),
            array('name'=>'充值记录','act'=>'recharge','op'=>'User','c'=>'User'),
            array('name'=>'提现申请','act'=>'withdrawals','op'=>'User','c'=>'User'),
            array('name'=>'汇款记录','act'=>'remittance','op'=>'User','c'=>'User'),
            array('name'=>'会员整合','act'=>'integrate','op'=>'User','c'=>'User'),
            array('name'=>'会员签到','act'=>'signList','op'=>'User','c'=>'User'),
        ))
    )),
    'supplier'=>array('name'=>'门店','child'=>array(
        array('name' => '门店','child'=>array(
            array('name' => '基础设置', 'act'=>'supplier_config', 'op'=>'Supplier','c'=>'Supplier'),
            array('name' => '门店列表', 'act'=>'index', 'op'=>'Supplier','c'=>'Supplier'),
            array('name' => '工程师列表', 'act'=>'engineer_list', 'op'=>'Supplier','c'=>'Supplier'),
            array('name' => '门店申请', 'act'=>'supplier_join_list', 'op'=>'Supplier','c'=>'Supplier'),
            array('name'=>'工程师申请','act'=>'engineer_join_list','op'=>'Supplier','c'=>'Supplier'),
            array('name'=>'工程师提现','act'=>'withdraws_list','op'=>'Supplier','c'=>'Supplier'),
            array('name'=>'转账汇款记录','act'=>'remittance','op'=>'Supplier','c'=>'Supplier'),
        )),
    )),
    'distribut'=>array('name'=>'云分销','child'=>array(
        array('name' => '分销','child' => array(
            array('name' => '分销设置', 'act'=>'index', 'op'=>'Distribut','c'=>'Distribut'),
            array('name' => '分销商品列表', 'act'=>'goods_list', 'op'=>'Distribut','c'=>'Distribut'),
            array('name' => '分销商列表', 'act'=>'distributor_list', 'op'=>'Distribut','c'=>'Distribut'),
            array('name' => '分销关系', 'act'=>'tree', 'op'=>'Distribut','c'=>'Distribut'),
            array('name' => '分成日志', 'act'=>'rebate_log', 'op'=>'Distribut','c'=>'Distribut'),
        ))
    )),
    'wechat'=>array('name'=>'微信','child'=>array(
        array('name' => '微信','child' => array(
            array('name' => '公众号配置', 'act'=>'index', 'op'=>'Wechat','c'=>'Wechat'),
            array('name' => '微信菜单管理', 'act'=>'menu', 'op'=>'Wechat','c'=>'Wechat'),
            array('name' => '自动回复', 'act'=>'auto_reply', 'op'=>'Wechat','c'=>'Wechat'),
            array('name' => '粉丝列表', 'act'=>'fans_list', 'op'=>'Wechat','c'=>'Wechat'),
            array('name' => '模板消息', 'act'=>'template_msg', 'op'=>'Wechat','c'=>'Wechat'),
            array('name' => '素材管理', 'act'=>'materials', 'op'=>'Wechat','c'=>'Wechat'),
            array('name' => '一键关注设置', 'act'=>'follow', 'op'=>'Wechat','c'=>'Wechat'),
            //array('name' => '图文回复', 'act'=>'img', 'op'=>'Wechat'),
        )),
        array('name' => '微官网','child' => array(
            array('name' => '模板设置', 'act'=>'index', 'op'=>'Wechat','c'=>'Wechat'),
            array('name' => '文章管理', 'act'=>'menu', 'op'=>'Wechat','c'=>'Wechat'),
            array('name' => '幻灯片管理', 'act'=>'auto_reply', 'op'=>'Wechat','c'=>'Wechat'),
            array('name' => '基础设置', 'act'=>'fans_list', 'op'=>'Wechat','c'=>'Wechat'),
        )),
        array('name' => '微信卡券','child' => array(
            array('name' => '代金券', 'act'=>'index', 'op'=>'Wechat','c'=>'Wechat'),
            array('name' => '折扣券', 'act'=>'menu', 'op'=>'Wechat','c'=>'Wechat'),
            array('name' => '兑换券', 'act'=>'auto_reply', 'op'=>'Wechat','c'=>'Wechat'),
            array('name' => '优惠券', 'act'=>'fans_list', 'op'=>'Wechat','c'=>'Wechat'),
            array('name' => '兑券记录', 'act'=>'template_msg', 'op'=>'Wechat','c'=>'Wechat'),
            array('name' => '其他设置', 'act'=>'materials', 'op'=>'Wechat','c'=>'Wechat'),
        ))
    )),
    'miniprogram'=>array('name'=>'小程序','child'=>array(
        array('name' => '小程序','child' => array(
            array('name' => '小程序配置', 'act'=>'index', 'op'=>'miniprogram','c'=>'miniprogram'),
        ))
    )),
    'plugins'=>array('name'=>'应用','child'=>array(
        array('name' => '应用','child' => array(
            array('name' => '应用概况', 'act'=>'index','op'=>'Plugins','c'=>'Plugins'),
        )),
        array('name' => '全民拼团','child' => array(
            array('name' => '拼团管理', 'act'=>'index','op'=>'Team','c'=>'Team'),
            array('name' => '拼团订单', 'act'=>'order_list','op'=>'Team','c'=>'Team'),
            array('name' => '拼团活动列表', 'act'=>'team_list','op'=>'Team','c'=>'Team'),
            array('name' => '拼单成员列表', 'act'=>'team_found','op'=>'Team','c'=>'Team'),
            array('name' => '幻灯片设置', 'act'=>'ad','op'=>'Team','c'=>'Team'),
            array('name' => '佣金设置', 'act'=>'bonus','op'=>'Team','c'=>'Team'),
            //array('name' => '基础设置', 'act'=>'order_list','op'=>''),
        )),
        array('name' => '全民砍价','child' => array(
            array('name' => '砍价商品', 'act'=>'order_list', 'op'=>'Team'),
            array('name' => '已售罄', 'act'=>'menu', 'op'=>''),
            array('name' => '未开始', 'act'=>'auto_reply', 'op'=>''),
            array('name' => '已结束', 'act'=>'fans_list', 'op'=>''),
            array('name' => '已下架', 'act'=>'template_msg', 'op'=>''),
            array('name' => '回收站', 'act'=>'materials', 'op'=>''),
            array('name' => '分享设置', 'act'=>'materials', 'op'=>''),
            array('name' => '消息通知', 'act'=>'materials', 'op'=>''),
            array('name' => '其他设置', 'act'=>'materials', 'op'=>''),
        )),
        array('name' => '文章营销','child'=>array(
            array('name' => '文章列表', 'act'=>'articleList', 'op'=>'Article','c'=>'Article'),
            array('name' => '文章分类', 'act'=>'categoryList', 'op'=>'Article','c'=>'Article'),
            array('name' => '公告管理', 'act'=>'notice_list', 'op'=>'Article','c'=>'Article'),
        )),
        array('name' => '专题专区','child'=>array(
            array('name' => '专题列表', 'act'=>'topicList', 'op'=>'Topic','c'=>'Article'),
        )),
        array('name' => '友情链接','child'=>array(
            array('name'=>'友情链接','act'=>'linkList','op'=>'Article','c'=>'Article'),
        )),
        array('name' => '自定义装修','child'=>array(
            array('name'=>'模板列表','act'=>'renovationIndex','op'=>'Plugins','c'=>'Plugins'),
            array('name'=>'自定义页面','act'=>'renovation','op'=>'Plugins','c'=>'Plugins'),
            array('name'=>'自定义导航栏','act'=>'navigationList','op'=>'Systems','c'=>'Systems'),
            array('name'=>'页面设置','act'=>'renovation33','op'=>'Plugins','c'=>'Plugins'),
        )),
        array('name' => '活动海报','child' => array(
            array('name' => '海报管理', 'act'=>'index', 'op'=>''),
            array('name' => '基础设置', 'act'=>'menu', 'op'=>''),
            array('name' => '自定义菜单', 'act'=>'diymenuList', 'op'=>'Diymenu', 'c' => 'Diymenu'),
        )),
        array('name' => '调研报名','child' => array(
            array('name' => '模板管理', 'act'=>'index', 'op'=>''),
            array('name' => '分类管理', 'act'=>'menu', 'op'=>''),
        )),
        array('name' => '兑换中心','child' => array(
            array('name' => '兑换管理', 'act'=>'index', 'op'=>''),
            array('name' => '兑换分类', 'act'=>'menu', 'op'=>''),
            array('name' => '兑换记录', 'act'=>'menu', 'op'=>''),
            array('name' => '其他设置', 'act'=>'menu', 'op'=>''),
        )),
        array('name' => '消息群发','child' => array(
            array('name' => '消息群发', 'act'=>'index', 'op'=>''),
            array('name' => '模板设置', 'act'=>'menu', 'op'=>''),
        )),
    )),
    'mobile'=>array('name'=>'模板','child'=>array(
            // array('name' => '设置','child' => array(
            array('name' => '模板设置','child' => array(
                    array('name' => '模板设置', 'act'=>'templateList', 'op'=>'Template'),
                    array('name' => '手机支付', 'act'=>'templateList', 'op'=>'Template'),
                    array('name' => '微信二维码', 'act'=>'templateList', 'op'=>'Template'),
                    array('name' => '第三方登录', 'act'=>'templateList', 'op'=>'Template'),
                    array('name' => '导航管理', 'act'=>'finance', 'op'=>'Report'),
                    array('name' => '广告管理', 'act'=>'finance', 'op'=>'Report'),
                    array('name' => '广告位管理', 'act'=>'finance', 'op'=>'Report'),
            )),
    )),

    'resource'=>array('name'=>'插件','child'=>array(
            array('name' => '云服务','child' => array(
                array('name' => '插件库', 'act'=>'index', 'op'=>'Plugin','c'=>'Plugins'),
                //array('name' => '数据备份', 'act'=>'index', 'op'=>'Tools'),
                //array('name' => '数据还原', 'act'=>'restore', 'op'=>'Tools'),
            )),
           /* array('name' => 'App','child' => array(
                array('name' => '安卓APP管理', 'act'=>'index', 'op'=>'MobileApp'),
                array('name' => '苹果APP管理', 'act'=>'ios_audit', 'op'=>'MobileApp'),
            ))*/
    )),
);