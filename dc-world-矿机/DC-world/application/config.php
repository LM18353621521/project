<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

return [
    // +----------------------------------------------------------------------
    // | 应用设置
    // +----------------------------------------------------------------------

    // 应用命名空间
    'app_namespace'          => 'app',
    // 应用调试模式 
    'app_debug'              => true,
    // 应用Trace
    'app_trace'              => false,
    // 应用模式状态
    'app_status'             => '',
    // 是否支持多模块
    'app_multi_module'       => true,
    // 入口自动绑定模块
    'auto_bind_module'       => false,
    // 注册的根命名空间
    'root_namespace'         => [],
    // 扩展函数文件
    'extra_file_list'        => [THINK_PATH . 'helper' . EXT,APP_PATH.'function.php'],
    // 默认输出类型
    'default_return_type'    => 'html',
    // 默认AJAX 数据返回格式,可选json xml ...
    'default_ajax_return'    => 'json',
    // 默认JSONP格式返回的处理方法
    'default_jsonp_handler'  => 'jsonpReturn',
    // 默认JSONP处理方法
    'var_jsonp_handler'      => 'callback',
    // 默认时区
    'default_timezone'       => 'PRC',
    // 是否开启多语言
    'lang_switch_on'         => false,
    // 默认全局过滤方法 用逗号分隔多个
    'default_filter'         => 'htmlspecialchars',
    // 默认语言
    'default_lang'           => 'zh-cn',
    // 应用类库后缀
    'class_suffix'           => false,
    // 控制器类后缀
    'controller_suffix'      => false,

    // +----------------------------------------------------------------------
    // | 模块设置
    // +----------------------------------------------------------------------

    // 默认模块名
    'default_module'         => 'Dcworld',
    // 禁止访问模块
    'deny_module_list'       => ['common'],
    // 默认控制器名
    'default_controller'     => 'Index',
    // 默认操作名
    'default_action'         => 'index',
    // 默认验证器
    'default_validate'       => '',
    // 默认的空控制器名
    'empty_controller'       => 'Error',
    // 操作方法后缀
    'action_suffix'          => '',
    // 自动搜索控制器
    'controller_auto_search' => false,

    // +----------------------------------------------------------------------
    // | URL设置
    // +----------------------------------------------------------------------

    // PATHINFO变量名 用于兼容模式
    'var_pathinfo'           => 's',
    // 兼容PATH_INFO获取
    'pathinfo_fetch'         => ['ORIG_PATH_INFO', 'REDIRECT_PATH_INFO', 'REDIRECT_URL'],
    // pathinfo分隔符
    'pathinfo_depr'          => '/',
    // URL伪静态后缀
    'url_html_suffix'        => 'html',
    // URL普通方式参数 用于自动生成
    'url_common_param'       => false,
    // URL参数方式 0 按名称成对解析 1 按顺序解析
    'url_param_type'         => 0,
    // 是否开启路由
    'url_route_on'           => true,
    // 路由使用完整匹配
    'route_complete_match'   => false,
    // 路由配置文件（支持配置多个）
    'route_config_file'      => ['route'],
    // 是否强制使用路由
    'url_route_must'         => false,
    // 域名部署
    'url_domain_deploy'      => false,
    // 域名根，如thinkphp.cn
    'url_domain_root'        => '',
    // 是否自动转换URL中的控制器和操作名
    'url_convert'            => false,
    // 默认的访问控制器层
    'url_controller_layer'   => 'controller',
    // 表单请求类型伪装变量
    'var_method'             => '_method',
    // 表单ajax伪装变量
    'var_ajax'               => '_ajax',
    // 表单pjax伪装变量
    'var_pjax'               => '_pjax',
    // 是否开启请求缓存 true自动缓存 支持设置请求缓存规则
    'request_cache'          => false,
    // 请求缓存有效期
    'request_cache_expire'   => null,

    // +----------------------------------------------------------------------
    // | 模板设置
    // +----------------------------------------------------------------------

    'template'               => [
        // 模板引擎类型 支持 php think 支持扩展
        'type'         => 'Think',
        // 模板路径
        'view_path'    => '',
        // 模板后缀
        'view_suffix'  => 'html',
        // 模板文件名分隔符
        'view_depr'    => DS,
        // 模板引擎普通标签开始标记
        'tpl_begin'    => '{',
        // 模板引擎普通标签结束标记
        'tpl_end'      => '}',
        // 标签库标签开始标记
        'taglib_begin' => '{',
        // 标签库标签结束标记
        'taglib_end'   => '}',
    ],

    // 视图输出字符串内容替换
    'view_replace_str'       => [],
    // 默认跳转页面对应的模板文件
    'dispatch_success_tmpl'  => THINK_PATH . 'tpl' . DS . 'dispatch_jump.tpl',
    'dispatch_error_tmpl'    => THINK_PATH . 'tpl' . DS . 'dispatch_jump.tpl',

    // +----------------------------------------------------------------------
    // | 异常及错误设置
    // +----------------------------------------------------------------------

    // 异常页面的模板文件 
    'exception_tmpl'         => THINK_PATH . 'tpl' . DS . 'think_exception.tpl',
    // errorpage 错误页面
    'error_tmpl'         => THINK_PATH . 'tpl' . DS . 'think_error.tpl',


    // 错误显示信息,非调试模式有效
    'error_message'          => '页面错误！请稍后再试～',
    // 显示错误信息
    'show_error_msg'         => false,
    // 异常处理handle类 留空使用 \think\exception\Handle
    'exception_handle'       => '',

    // +----------------------------------------------------------------------
    // | 日志设置
    // +----------------------------------------------------------------------

    'log'                    => [
        // 日志记录方式，内置 file socket 支持扩展
        'type'  => 'File',
        // 日志保存目录
        'path'  => LOG_PATH,
        // 日志记录级别
        'level' => ['debug'],
        // 日志开关  1 开启 0 关闭
        'switch' => 1,
    ],

    // +----------------------------------------------------------------------
    // | Trace设置 开启 app_trace 后 有效
    // +----------------------------------------------------------------------
    'trace'                  => [
        // 内置Html Console 支持扩展
        'type' => 'Console',
    ],

    // +----------------------------------------------------------------------
    // | 缓存设置
    // +----------------------------------------------------------------------
/**/
    'cache'                  => [
        // 驱动方式
        'type'   => 'File',
        // 缓存保存目录
        'path'   => CACHE_PATH,
        // 缓存前缀
        'prefix' => 'e459d613186e6512ff8b849b0ac77b2e',
        // 缓存有效期 0表示永久缓存
        'expire' => 1,
    ],

/*
    'cache'                  => [
        // 驱动方式
        'type'   => 'redis',
        'host'       => '192.168.0.201', // 指定redis的地址
    ],
*/
    // +----------------------------------------------------------------------
    // | 会话设置
    // +----------------------------------------------------------------------

    'session'                => [
        'id'             => '',
        // SESSION_ID的提交变量,解决flash上传跨域
        'var_session_id' => '',
        // SESSION 前缀
        'prefix'         => 'think',
        // 驱动方式 支持redis memcache memcached
        'type'           => '',
        // 是否自动开启 SESSION
        'auto_start'     => true,
        'expire' => 86400,
    ],

    // +----------------------------------------------------------------------
    // | Cookie设置
    // +----------------------------------------------------------------------
    'cookie'                 => [
        // cookie 名称前缀
        'prefix'    => '',
        // cookie 保存时间
        'expire'    => 0,
        // cookie 保存路径
        'path'      => '/',
        // cookie 有效域名
        'domain'    => '',
        //  cookie 启用安全传输
        'secure'    => false,
        // httponly设置
        'httponly'  => '',
        // 是否使用 setcookie
        'setcookie' => true,
    ],

    //分页配置
    'paginate'               => [
        'type'      => 'bootstrap',
        'var_page'  => 'page',
        'list_rows' => 15,
    ],
    // 密码加密串
    'AUTH_CODE' => "TPSHOP", //安装完毕之后不要改变，否则所有密码都会出错

    'ORDER_STATUS' =>[
        0 => '待确认',
        1 => '已确认',
        2 => '已收货',
        3 => '已取消',
        4 => '已完成',//评价完
        5 => '已作废',
    ],
    'SHIPPING_STATUS' => array(
        0 => '未发货',
        1 => '已发货',
        2 => '部分发货'
    ),
    'PAY_STATUS' => array(
        0 => '未支付',
        1 => '已支付',
        2 => '部分支付',
        3 => '已退款',
        4 => '拒绝退款'
    ),
    'SEX' => [
        0 => '保密',
        1 => '男',
        2 => '女'
    ],
    'COUPON_TYPE' => [
        0 => '下单赠送',
        1 => '指定发放',
        2 => '免费领取',
        3 => '线下发放',
    ],
    'PROM_TYPE' => [
        0 => '默认',
        1 => '抢购',
        2 => '团购',
        3 => '优惠'
    ],
    'TEAM_FOUND_STATUS' => array(
        '0'=>'待开团',
        '1'=>'已开团',
        '2'=>'拼团成功',
        '3'=>'拼团失败',
    ),
    'TEAM_FOLLOW_STATUS' => array(
        '0'=>'待拼单',
        '1'=>'拼单成功',
        '2'=>'成团成功',
        '3'=>'成团失败',
    ),
    'WHERE_KNOW' => array(
        '1'=>'网站',
        '2'=>'微信',
        '3'=>'朋友介绍',
        '4'=>'线下',
        '5'=>'其他'
    ),
    'TEAM_TYPE' => [0 => '分享团', 1 => '佣金团', 2 => '抽奖团'],
    // 订单用户端显示状态
    'WAITPAY'=>' AND o.pay_status = 0 AND o.order_status = 0 AND o.pay_code !="cod" ', //订单查询状态 待支付
    'WAITSEND'=>' AND (o.pay_status=1 OR o.pay_code="cod") AND o.shipping_status !=1 AND o.order_status in(0,1) ', //订单查询状态 待发货
    'WAITRECEIVE'=>' AND o.shipping_status=1 AND o.order_status = 1 ', //订单查询状态 待收货
    'WAITCCOMMENT'=> ' AND o.order_status=2 ', // 待评价 确认收货     //'FINISHED'=>'  AND order_status=1 ', //订单查询状态 已完成
    'FINISH'=> ' AND o.order_status = 4 ', // 已完成
    'CANCEL'=> ' AND o.order_status = 3 ', // 已取消
    'CANCELLED'=> 'AND o.order_status = 5 ',//已作废

    // 服务订单状态
    'WAITDO' => ' AND o.order_status = 1 ',
    'DOING' => ' AND o.order_status = 1 AND o.pay_status = 1 ',
    'FINISH' => ' AND o.pay_status = 1 AND o.order_status in(2,3,4) ',

    'ORDER_STATUS_DESC' => [
        'WAITPAY' => '待支付',
        'WAITSEND'=>'待发货',
        'PORTIONSEND'=>'部分发货',
        'WAITRECEIVE'=>'待收货',
        'WAITCCOMMENT'=> '待评价',
        'CANCEL'=> '已取消',
        'FINISH'=> '已完成', //
        'CANCELLED'=> '已作废'
    ],

    'REFUND_STATUS'=>array(
        -2 => '服务单取消',//会员取消
        -1 => '审核失败',//不同意
        0  => '待审核',//卖家审核
        1  => '审核通过',//同意
        2  => '买家发货',//买家发货
        3  => '已完成',//服务单完成
    ),
    /**
     * 售后类型
     */
    'RETURN_TYPE'=>array(
        0=>'仅退款',
        1=>'退货退款',
        2=>'换货',
    ),
    //短信使用场景
    'SEND_SCENE' => array(
        '1'=>array('用户注册','验证码${code}，用户注册新账号, 请勿告诉他人，感谢您的支持!','regis_sms_enable'),
        '2'=>array('修改密码','您的动态码为:${code}，您正在进行秘密重置操作，如非本人操作，请忽略本短信','forget_pwd_sms_enable'),
//        '2'=>array('用户找回密码','验证码${code}，用于密码找回，如非本人操作，请及时检查账户安全','forget_pwd_sms_enable'),
        '3'=>array('客户下单','您有新订单，收货人：${consignee}，联系方式：${phone}，请您及时查收.','order_add_sms_enable'),
        '4'=>array('客户支付','客户下的单(订单ID:${order_id})已经支付，请及时发货.','order_pay_sms_enable'),
        '5'=>array('商家发货','尊敬的${user_name}用户，您的订单已发货，收货人${consignee}，请您及时查收','order_shipping_sms_enable'),
        '6'=>array('身份验证','尊敬的用户，您的验证码为${code}, 请勿告诉他人.','bind_mobile_sms_enable'),
        '7'=>array('购买虚拟商品通知','尊敬的用户，您购买的虚拟商品【${goods_name}】兑换码已生成,请注意查收.','virtual_goods_sms_enable'),
        '8'=>array('门店通知','尊敬的用户，您有新订单待处理,当前状态:${status},订单摘要:${remark},请及时处理.','to_door_order_sms_enable'),

    ),

        //短信使用场景
    'SEND_MSG' => array(
        '1'=>array('推荐下级消息','恭喜您，您成功推荐$nickname成为您的直属粉丝，时间：$add_time','extend_template_msg_enable'),
        '2'=>array('下级下单消息','您下级粉丝$nickname已成功购买了$goods, 您的佣金为$distribut_money, 订单号:$order_sn, 时间：$add_time','lower_buy_msg_enable'),
    ),

    'APP_TOKEN_TIME' => 60 * 60 * 24 , //App保持token时间 , 此处为1天

    /**
     *  订单用户端显示按钮
     *   去支付     AND pay_status=0 AND order_status=0 AND pay_code ! ="cod"
     *   取消按钮  AND pay_status=0 AND shipping_status=0 AND order_status=0
     *   确认收货  AND shipping_status=1 AND order_status=0
     *   评价      AND order_status=1
     *   查看物流  if(!empty(物流单号))
     *   退货按钮（联系客服）  所有退换货操作， 都需要人工介入   不支持在线退换货
     */

    /*分页每页显示数*/
    'PAGESIZE' => 10,

    'WX_PAY2' => 1,

    /**假设这个访问地址是 www.tpshop.cn/home/goods/goodsInfo/id/1.html
     *就保存名字为 home_goods_goodsinfo_1.html
     *配置成这样, 指定 模块 控制器 方法名 参数名
     */
    'HTML_CACHE_ARR'=> [
    ['mca'=>'home_Goods_goodsInfo','p'=>['id']],
    ['mca'=>'home_Index_index'],  // 缓存首页静态页面
    ['mca'=>'home_Goods_ajaxComment','p'=>['goods_id','commentType','p']],  // 缓存评论静态页面 http://www.tpshop2.0.com/index.php?m=Home&c=Goods&a=ajaxComment&goods_id=142&commentType=1&p=1
    ['mca'=>'home_Goods_ajax_consult','p'=>['goods_id','consult_type','p']],  // 缓存咨询静态页面 http://www.tpshop2.0.com/index.php?m=Home&c=Goods&a=ajax_consult&goods_id=142&consult_type=0&p=2
    ],

    /*订单操作*/
    'CONVERT_ACTION'=>[
        'pay'=> '付款',
        'pay_cancel'=>'取消付款',
        'confirm'=>'确认订单',
        'cancel'=>'取消确认',
        'invalid'=>'作废订单',
        'remove'=>'删除订单',
        'delivery'=>'确认发货',
        'delivery_confirm'=>'确认收货',
    ],
    'COUPON_USER_TYPE'=>['全店通用','指定商品可用','指定分类商品可用'],
    'image_upload_limit_size'=>1024 * 1024 * 5,//上传图片大小限制
    /*每分钟*/
    /*每小时 某分*/
    /*每天 某时:某分*/
    /*每周-某天 某时:某分  0=周日*/
    /*每月-某天 某时:某分*/
    /*某月-某日 某时-某分*/
    /*某年-某月-某日 某时-某分*/
    'sys_crond_timer' => array('*', '*:i', 'H:i', '@-w H:i', '*-d H:i', 'm-d H:i', 'Y-m-d H:i'),
    'crond'=> array('*' => ['app\command\Test::firstTest'],  //每分钟
        '00:00'      => [],  //每周 ------------
        '*-01 00:00' => [],  //每月--------
        '*:00'       => [],  //每小时---------

    ),
    'ZH_WORDS'  => '们以我到他会作时要动国产的一是工就年阶义发成部民可出能方进在了不和有大这主中人上为来分生对于学下级地个用同行面说种过命度革而多子后自社加小机也经力线本电高量长党得实家定深法表着水理化争现所二起政三好十战无农使性前等反体合斗路图把结第里正新开论之物从当两些还天资事队批点育重其思与间内去因件日利相由压员气业代全组数果期导平各基或月毛然如应形想制心样干都向变关问比展那它最及外没看治提五解系林者米群头意只明四道马认次文通但条较克又公孔领军流入接席位情运器并飞原油放立题质指建区验活众很教决特此常石强极土少已根共直团统式转别造切九你取西持总料连任志观调七么山程百报更见必真保热委手改管处己将修支识病象几先老光专什六型具示复安带每东增则完风回南广劳轮科北打积车计给节做务被整联步类集号列温装即毫知轴研单色坚据速防史拉世设达尔场织历花受求传口断况采精金界品判参层止边清至万确究书术状厂须离再目海交权且儿青才证低越际八试规斯近注办布门铁需走议县兵固除般引齿千胜细影济白格效置推空配刀叶率述今选养德话查差半敌始片施响收华觉备名红续均药标记难存测士身紧液派准斤角降维板许破述技消底床田势端感往神便贺村构照容非搞亚磨族火段算适讲按值美态黄易彪服早班麦削信排台声该击素张密害侯草何树肥继右属市严径螺检左页抗苏显苦英快称坏移约巴材省黑武培著河帝仅针怎植京助升王眼她抓含苗副杂普谈围食射源例致酸旧却充足短划剂宣环落首尺波承粉践府鱼随考刻靠够满夫失包住促枝局菌杆周护岩师举曲春元超负砂封换太模贫减阳扬江析亩木言球朝医校古呢稻宋听唯输滑站另卫字鼓刚写刘微略范供阿块某功套友限项余倒卷创律雨让骨远帮初皮播优占死毒圈伟季训控激找叫云互跟裂粮粒母练塞钢顶策双留误础吸阻故寸盾晚丝女散焊功株亲院冷彻弹错散商视艺灭版烈零室轻血倍缺厘泵察绝富城冲喷壤简否柱李望盘磁雄似困巩益洲脱投送奴侧润盖挥距触星松送获兴独官混纪依未突架宽冬章湿偏纹吃执阀矿寨责熟稳夺硬价努翻奇甲预职评读背协损棉侵灰虽矛厚罗泥辟告卵箱掌氧恩爱停曾溶营终纲孟钱待尽俄缩沙退陈讨奋械载胞幼哪剥迫旋征槽倒握担仍呀鲜吧卡粗介钻逐弱脚怕盐末阴丰雾冠丙街莱贝辐肠付吉渗瑞惊顿挤秒悬姆烂森糖圣凹陶词迟蚕亿矩康遵牧遭幅园腔订香肉弟屋敏恢忘编印蜂急拿扩伤飞露核缘游振操央伍域甚迅辉异序免纸夜乡久隶缸夹念兰映沟乙吗儒杀汽磷艰晶插埃燃欢铁补咱芽永瓦倾阵碳演威附牙芽永瓦斜灌欧献顺猪洋腐请透司危括脉宜笑若尾束壮暴企菜穗楚汉愈绿拖牛份染既秋遍锻玉夏疗尖殖井费州访吹荣铜沿替滚客召旱悟刺脑措贯藏敢令隙炉壳硫煤迎铸粘探临薄旬善福纵择礼愿伏残雷延烟句纯渐耕跑泽慢栽鲁赤繁境潮横掉锥希池败船假亮谓托伙哲怀割摆贡呈劲财仪沉炼麻罪祖息车穿货销齐鼠抽画饲龙库守筑房歌寒喜哥洗蚀废纳腹乎录镜妇恶脂庄擦险赞钟摇典柄辩竹谷卖乱虚桥奥伯赶垂途额壁网截野遗静谋弄挂课镇妄盛耐援扎虑键归符庆聚绕摩忙舞遇索顾胶羊湖钉仁音迹碎伸灯避泛亡答勇频皇柳哈揭甘诺概宪浓岛袭谁洪谢炮浇斑讯懂灵蛋闭孩释乳巨徒私银伊景坦累匀霉杜乐勒隔弯绩招绍胡呼痛峰零柴簧午跳居尚丁秦稍追梁折耗碱殊岗挖氏刃剧堆赫荷胸衡勤膜篇登驻案刊秧缓凸役剪川雪链渔啦脸户洛孢勃盟买杨宗焦赛旗滤硅炭股坐蒸凝竟陷枪黎救冒暗洞犯筒您宋弧爆谬涂味津臂障褐陆啊健尊豆拔莫抵桑坡缝警挑污冰柬嘴啥饭塑寄赵喊垫丹渡耳刨虎笔稀昆浪萨茶滴浅拥穴覆伦娘吨浸袖珠雌妈紫戏塔锤震岁貌洁剖牢锋疑霸闪埔猛诉刷狠忽灾闹乔唐漏闻沈熔氯荒茎男凡抢像浆旁玻亦忠唱蒙予纷捕锁尤乘乌智淡允叛畜俘摸锈扫毕璃宝芯爷鉴秘净蒋钙肩腾枯抛轨堂拌爸循诱祝励肯酒绳穷塘燥泡袋朗喂铝软渠颗惯贸粪综墙趋彼届墨碍启逆卸航衣孙龄岭骗休借',

];
