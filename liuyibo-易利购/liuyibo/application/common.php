<?php

use think\Db;
//根据ID获取团队
function get_team($id,$type=1)
{
    if($type==1){
        //获取所有
        $res=get_superior($id);
        if($res){
            foreach ($res as $key => $value) {
                $res2[]=get_subordinate($value);
            }
            foreach ($res2 as $key2 => $value2) {
                foreach ($value2 as $key3 => $value3) {
                    foreach ($value3 as $key4 => $value4) {
                        $res3[]=$value4;
                    }
                }
            }
            $res4=array_merge($res,$res3);
            return array_unique($res4);
        }else{
            $res3=get_subordinate($id);
            foreach ($res3 as $key => $value) {
                foreach ($value as $key2 => $value2) {
                    $res4[]=$value2;
                }
            }
            $res4[]=$id;
            return array_unique($res4);
        }
    }else{
        //获取下级
        $res3=get_subordinate($id);
        foreach ($res3 as $key => $value) {
            foreach ($value as $key2 => $value2) {
                $res4[]=$value2;
            }
        }
        if(empty($res4)){
            return [];
        }else{
            return array_unique($res4);
        }
    }
}
//根据ID获取上级
function get_superior($id,$arr=[]){
    if($id){
        $pid=Db::name('users')->where(['user_id'=>['in',$id]])->value('first_leader');
        if($pid){
            $arr[]=$pid;
            return get_superior($pid,$arr);
        }else{
            return $arr;
        }
    }else{
        return $arr;
    }
}
//根据ID获取下级
function get_subordinate($id,$arr=[]){
    if($id){
        $pid=Db::name('users')->where(['first_leader'=>['in',$id]])->column('user_id');
        if($pid){
            $arr[]=$pid;
            $ids=implode(',',$pid);
            return get_subordinate($ids,$arr);
        }else{
            return $arr;
        }
    }else{
        return $arr;
    }
}
/**
 * 会员升级
 * @param
 * @return bool
 */
function user_upgrade($id)
{

    $user=Db::name('users')->where(['user_id'=>$id])->find();
    //消费者升级消费商
    if($user['level']==1){
        $user_level2=Db::name('user_level')->where('level_id=2')->find();
        //累计购买零售专区产品
        if($user['pay_points']>=$user_level2['amount']){
            Db::name('users')->where(['user_id'=>$user['user_id']])->setInc('level');
            return vpay_level_log($user['user_id'],$user['mobile'],'前台升级',$user['level'],$user['level']+1,2);
        }else{
            return 0;
        }
    }//消费商升级代理商
    elseif($user['level']==2){
        $user_level=Db::name('user_level')->where('level_id=3')->find();
        //获取团队（所有下线）
        $userids2=get_team($id,2);
        $ids2=implode(',',$userids2);
        $team_performance=Db::name('users')->where(['user_id'=>['in',$ids2],'level'=>['<=',2]])->sum('pay_points');
        //团队流水
        if($team_performance>=$user_level['discount']){
            $res=Db::name('users')->where(['user_id'=>$user['user_id']])->setInc('level');
        }else{
            return 0;
        }
                
        if($res){
            vpay_level_log($user['user_id'],$user['mobile'],'前台升级',$user['level'],$user['level']+1,2);
            $user2=Db::name('users')->where(['user_id'=>$user['first_leader']])->find();
            //代理商升级合伙人
            if($user2['level']==3){
                //自己是代理商
                $user_level2=Db::name('user_level')->where('level_id=4')->find();
                $count=Db::name('users')->where(['first_leader'=>['in',$user2['user_id']],'level'=>'3'])->count();

                //直推代理商
                if($count>=$user_level2['region_code']){
                    Db::name('users')->where(['user_id'=>$user2['user_id']])->setInc('level');
                    return vpay_level_log($user2['user_id'],$user2['mobile'],'前台升级',$user2['level'],$user2['level']+1,2);
                }else{
                    return $res;
                }
            }else{
                return $res;
            }
        }else{
            return 0;
        }
    }elseif($user['level']==3){
        //代理商升级合伙人

        //自己是代理商
        $user_level2=Db::name('user_level')->where('level_id=4')->find();
        $count=Db::name('users')->where(['first_leader'=>['in',$user['user_id']],'level'=>'3'])->count();
        //直推代理商
        if($count>=$user_level2['region_code']){
            Db::name('users')->where(['user_id'=>$user['user_id']])->setInc('level');
            return vpay_level_log($user['user_id'],$user['mobile'],'前台升级',$user['level'],$user['level']+1,2);
        }else{
            return 0;
        }
    }else{
        return 0;
    }
}

/**
 * 会员等级变更记录
 * @param
 * @return bool
 */
function vpay_level_log($user_id,$account,$desc,$before_level,$after_level,$type)
{
    $data = array(
        'user_id' => $user_id,
        'account' => $account,
        'desc' => $desc,
        'before_level' => $before_level,
        'after_level' => $after_level,
        'type' => $type,
        'change_time' => time()
    );
    return Db::name('vpay_level_log')->insert($data);
}
/**
 * 库存变更记录表
 * @param
 * @return bool
 */
function digitalassets_log($user_id,$order_id,$goods_id,$num,$desc,$before,$after,$type)
{
    $data = array(
        'user_id' => $user_id,
        'order_id' => $order_id,
        'goods_id' => $goods_id,
        'num' => $num,
        'desc' => $desc,
        'before' => $before,
        'after' => $after,
        'type' => $type,
        'createTime' => date('Y-m-d H:i:s'),
        'updateTime' => date('Y-m-d H:i:s')
    );
    return Db::name('digitalassets')->insert($data);
}
/**
 *  配额log
 * @param $reflectId 各表id
 * @param $userId   用户id
 * @param $num  数量
 * @param $type 类型：1：转入 2：转出 3：交易买入 4：交易卖出 5：积分释放
 * @param $before  变更前
 * @return mixed
 */

function integrallog($reflectId, $userId, $num, $type, $before,$after)
{
    $data = array(
        'reflectId' => $reflectId,
        'userId' => $userId,
        'type' => $type,
        'num' => $num,
        'before' => $before,
        'after' => $after,
        'createTime' => date('Y-m-d H:i:s'),
        'updateTime' => date('Y-m-d H:i:s'),
    );
    return M('integrallog')->add($data);
}

/**
 * 收益积分log
 * @param $reflectId 各表id
 * @param $userId   用户id
 * @param $num  数量
 * @param $type 类型 1：充值；2：申请提现；3：提现失败返还 4：直推下级业绩返点 5：代理商补贴  6 : 直推代理商收入 7：月销售额收入；8：收益积分购买产品；9：转出；10：转入； 11：寄售商品收入 12：合伙人补贴；13：转让手续费 14 : 回收出售商品
 * @param $before  变更前
 * @return mixed
 */

function balancelog($reflectId, $userId, $num, $type, $before,$after)
{
    $data = array(
        'reflectId' => $reflectId,
        'userId' => $userId,
        'type' => $type,
        'num' => $num,
        'before' => $before,
        'after' => $after,
        'createTime' => date('Y-m-d H:i:s'),
        'updateTime' => date('Y-m-d H:i:s'),
    );
    $user=M('users')->where(array("user_id"=>$userId))->find();
    $arr=[15];
    if($num < 0&&in_array($type,$arr)){
        //升级
        if($user['level']==1){
            user_upgrade($userId);
        }
        $system=tpCache('ylg_spstem_role');
        //购物返兑换积分
        if($system['redeem_points']>0){
            $redeem_points=$system['redeem_points']*abs($num);
            //购物返兑换积分
            Db::name('users')->where('user_id',$user['user_id'])->inc('distribut_money',$redeem_points)->update();
            shoppinglog($reflectId,$user['user_id'],$redeem_points,4,$user['distribut_money'],$user['distribut_money']+$redeem_points);
        }
        if($user['first_leader']){
            //直推奖 
            $user5=M('users')->where(array("user_id"=>$user['first_leader']))->find();
            if($user5){
                if($user5['level']==1){
                    $push_point=$system['push_point1'];
                    $push_point2=$system['push_point11'];
                }elseif($user5['level']==2){
                    $push_point=$system['push_point2'];
                    $push_point2=$system['push_point22'];
                }elseif($user5['level']==3){
                    $push_point=$system['push_point3'];
                    $push_point2=$system['push_point33'];
                }elseif($user5['level']==4){
                    $push_point=$system['push_point4'];
                    $push_point2=$system['push_point44'];
                }elseif($user5['level']==5){
                    $push_point=$system['push_point5'];
                    $push_point2=$system['push_point55'];
                }else{
                    $push_point=0;
                    $push_point2=0;
                }
                $money5=$push_point*abs($num);
                $money6=$push_point2*abs($num);
                if($money5>0){
                    //直推奖 返兑换积分
                    Db::name('users')->where('user_id',$user5['user_id'])->inc('distribut_money',$money5)->update();
                    shoppinglog($user['user_id'],$user5['user_id'],$money5,3,$user5['distribut_money'],$user5['distribut_money']+$money5);
                }
                if($money6>0&&$money6*(1-$system['personal_income'])>0){
                    //直推奖 返收益积分
                    Db::name('users')->where('user_id',$user5['user_id'])->inc('user_money',$money6*(1-$system['personal_income']))->update();
                    balancelog($user['user_id'],$user5['user_id'],$money6,21,$user5['user_money'],$user5['user_money']+$money6);

                    //个人所得税
                    balancelog($user5['user_id'],$user5['user_id'],-($money6*$system['personal_income']),19,$user5['user_money']+$money6,$user5['user_money']+$money6-($money6*$system['personal_income']));
                }
            }
        }
        
        //累积业绩
        if($type==15){
            M('users')->where(array("user_id"=>$userId))->inc('pay_points',abs($num))->inc('monthly_performance',abs($num))->update();
        }

        if($user['second_leader']){
            $second_leader=explode(",",$user['second_leader']);
            krsort($second_leader);
            $second_leader2=$second_leader;
            //unset($second_leader[0]);
            $i=0;
            $j=0;
            foreach ($second_leader as $key => $value) {
                //升级
                user_upgrade($value);
                
                //找到代理商或者董事
                $pid=Db::name('users')->where(['user_id'=>['in',$value],'level'=>['in','3,5']])->find();
                if($pid){
                    //代理商
                    if($pid['level']==3){
                        $money=$system['agent_rebate']*abs($num);
                        $type2=16;
                        $i++;
                    }else{
                        //董事
                        $money=$system['reserve_funds2']*abs($num);
                        $type2=17;
                        $j++;
                    }
                    if($i>1&&$j>1){
                        break;
                    }
                    if($money>0&&$i<=1&&$j<=1){
                        //团队奖
                        M('users')->where(array("user_id"=>$pid['user_id']))->inc('user_money',$money*(1-$system['personal_income']))->inc('total_amount',$money*(1-$system['personal_income']))->update();
                        balancelog($pid['user_id'],$pid['user_id'],$money,$type2,$pid['user_money'],$pid['user_money']+$money);

                        //个人所得税
                        balancelog($pid['user_id'],$pid['user_id'],-($money*$system['personal_income']),19,$pid['user_money']+$money,$pid['user_money']+$money-($money*$system['personal_income']));
                        if($type2==16){
                            //合伙人收益返点
                            if($pid['first_leader']){
                                $users2=M('users')->where(array("user_id"=>$pid['first_leader'],'level'=>['in','4']))->find();
                                if($users2){
                                    //直推代理商收入
                                    $total_amount=$money*(1-$system['personal_income']);

                                    $money1 = $total_amount*$system['agent_partner'];
                                    Db::name('users')->where('user_id',$users2['user_id'])->inc('user_money',$money1*(1-$system['personal_income']))->update();
                                    balancelog($users2['user_id'],$users2['user_id'],$money1,12,$users2['user_money'],$users2['user_money']+$money1);

                                    //个人所得税
                                    balancelog($users2['user_id'],$users2['user_id'],-($money1*$system['personal_income']),19,$users2['user_money']+$money1,$users2['user_money']+$money1-($money1*$system['personal_income']));
                                }
                            }
                        }
                    }
                    
                }else{
                    continue;
                }
            }
            if($user['level']<=2){
                foreach ($second_leader2 as $key3 => $value3) {
                    //找到合伙人
                    $pid2=Db::name('users')->where(['user_id'=>['in',$value3],'level'=>['in','3,4']])->find();
                    if($pid2){
                        if($pid2['level']==3){
                            break;
                        }
                        //直推消费商以及所有下级的业绩补贴
                        $rebate_revenue = abs($num);
                        $amount = $rebate_revenue*$system['agent_partner2'];
                        if($amount>0){
                            //合伙人收益返点
                            Db::name('users')->where('user_id',$pid2['user_id'])->inc('user_money',$amount*(1-$system['personal_income']))->update();
                            balancelog($pid2['user_id'],$pid2['user_id'],$amount,20,$pid2['user_money'],$pid2['user_money']+$amount);

                            //个人所得税
                            balancelog($pid2['user_id'],$pid2['user_id'],-($amount*$system['personal_income']),19,$pid2['user_money']+$amount,$pid2['user_money']+$amount-($amount*$system['personal_income']));
                        }
                        break;
                    }else{
                        continue;
                    }
                }
            }
        }
    }
    return M('balancelog')->add($data);
}
/**
 *  兑换积分log
 * @param $reflectId 各表id
 * @param $userId   用户id
 * @param $num  数量
 * @param $type 类型：1：收入；2：支出 ；3；直推返兑换积分；8：购买商品 9：购物返兑换积分；18：兑换专区退款；
 * @param $before  变更前
 * @return mixed
 */

function shoppinglog($reflectId, $userId, $num, $type, $before,$after)
{
    $data = array(
        'reflectId' => $reflectId,
        'userId' => $userId,
        'type' => $type,
        'num' => $num,
        'before' => $before,
        'after' => $after,
        'createTime' => date('Y-m-d H:i:s'),
        'updateTime' => date('Y-m-d H:i:s'),
    );
    return M('shoppinglog')->add($data);
}
/**
 * shop检验登陆
 * @param
 * @return bool
 */
function is_login()
{
    if (isset($_SESSION['admin_id']) && $_SESSION['admin_id'] > 0) {
        return $_SESSION['admin_id'];
    } else {
        return false;
    }
}

function star($star)
{
    switch ($star) {
        case 1:
            return '<img src="__STATIC__/assets/images/start1.png" alt="">
                    <img src="__STATIC__/assets/images/start2.png" alt="">
                    <img src="__STATIC__/assets/images/start2.png" alt="">
                    <img src="__STATIC__/assets/images/start2.png" alt="">
                    <img src="__STATIC__/assets/images/start2.png" alt="">';
            break;
        case 2:
            return '<img src="__STATIC__/assets/images/start1.png" alt="">
                    <img src="__STATIC__/assets/images/start1.png" alt="">
                    <img src="__STATIC__/assets/images/start2.png" alt="">
                    <img src="__STATIC__/assets/images/start2.png" alt="">
                    <img src="__STATIC__/assets/images/start2.png" alt="">';
            break;
        case 3:
            return '<img src="__STATIC__/assets/images/start1.png" alt="">
                    <img src="__STATIC__/assets/images/start1.png" alt="">
                    <img src="__STATIC__/assets/images/start1.png" alt="">
                    <img src="__STATIC__/assets/images/start2.png" alt="">
                    <img src="__STATIC__/assets/images/start2.png" alt="">';
            break;
        case 4:
            return '<img src="__STATIC__/assets/images/start1.png" alt="">
                    <img src="__STATIC__/assets/images/start1.png" alt="">
                    <img src="__STATIC__/assets/images/start1.png" alt="">
                    <img src="__STATIC__/assets/images/start1.png" alt="">
                    <img src="__STATIC__/assets/images/start2.png" alt="">';
            break;
        case 5:
            return '<img src="__STATIC__/assets/images/start1.png" alt="">
                    <img src="__STATIC__/assets/images/start1.png" alt="">
                    <img src="__STATIC__/assets/images/start1.png" alt="">
                    <img src="__STATIC__/assets/images/start1.png" alt="">
                    <img src="__STATIC__/assets/images/start1.png" alt="">';
            break;
        default:
            # code...
            break;
    }
}

/**
 * api获取星星的图片
 */
function img_star($num)
{
    return request()->domain() . "/public/images/start/stars" . $num . ".gif";
}

/*
 *
 * */
function api_img_url($img_path, $default = '/public/upload/head_pic/20180328/fe41b33cb76e5e0b10a0aa9a73b39589.png')
{
    //判断域名
    if (preg_match('/[a-zA-z]+:\/\/[^\s]*/', $img_path)) {
        return $img_path;
    } else {
        if (!$img_path)
            return request()->domain() . $default;

        return request()->domain() . $img_path;
    }
}

/**
 * 获取用户信息
 * @param $user_id_or_name  用户id 邮箱 手机 第三方id
 * @param int $type 类型 0 user_id查找 1 邮箱查找 2 手机查找 3 第三方唯一标识查找
 * @param string $oauth 第三方来源
 * @return mixed
 */
function get_user_info($user_id_or_name, $type = 0, $oauth = '')
{

    $map = array();
    if ($type == 0)
        $map['user_id'] = $user_id_or_name;
    if ($type == 1)
        $map['email'] = $user_id_or_name;
    if ($type == 2)
        $map['mobile'] = $user_id_or_name;

    if ($type == 3 || $type == 4) {
        //获取用户信息
        $column = ($type == 3) ? 'openid' : 'unionid';
        $thirdUser = M('OauthUsers')->where([$column => $user_id_or_name, 'oauth' => $oauth])->find();
        $map['user_id'] = $thirdUser['user_id'];
    }
    $user = M('users')->where($map)->find();
    return $user;
}

/**
 * 更新会员等级,折扣，消费总额
 * @param $user_id  用户ID
 * @return boolean
 */
function update_user_level($user_id)
{
    $total_amount = M('order')->master()->where("user_id=:user_id AND pay_status=1 and order_status not in (3,5)")->bind(['user_id' => $user_id])->sum('order_amount+user_money');
    //准备验证升级角色,此处是支付完成的地方----------标记<QualificationLogic>
    $qualificationLogic = new \app\common\logic\QualificationLogic();
    $qualificationLogic->prepare_update_level($user_id);
    $user = session('user');
    $updata['total_amount'] = $total_amount;//更新累计修复额度
    M('users')->where("user_id", $user_id)->save($updata);

}

/**
 *  商品缩略图 给于标签调用 拿出商品表的 original_img 原始图来裁切出来的
 * @param type $goods_id 商品id
 * @param type $width 生成缩略图的宽度
 * @param type $height 生成缩略图的高度
 */
function goods_thum_images($goods_id, $width, $height)
{
    if (empty($goods_id)) return '';

    //判断缩略图是否存在
    $path = "public/upload/goods/thumb/$goods_id/";
    $goods_thumb_name = "goods_thumb_{$goods_id}_{$width}_{$height}";

    // 这个商品 已经生成过这个比例的图片就直接返回了
    if (is_file($path . $goods_thumb_name . '.jpg')) return '/' . $path . $goods_thumb_name . '.jpg';
    if (is_file($path . $goods_thumb_name . '.jpeg')) return '/' . $path . $goods_thumb_name . '.jpeg';
    if (is_file($path . $goods_thumb_name . '.gif')) return '/' . $path . $goods_thumb_name . '.gif';
    if (is_file($path . $goods_thumb_name . '.png')) return '/' . $path . $goods_thumb_name . '.png';

    $original_img = M('Goods')->cache(true, 3600)->where("goods_id", $goods_id)->getField('original_img');
    if (empty($original_img)) {
        return '/public/images/icon_goods_thumb_empty_300.png';
    }

    $ossClient = new \app\common\logic\OssLogic;
    if (($ossUrl = $ossClient->getGoodsThumbImageUrl($original_img, $width, $height))) {
        return $ossUrl;
    }

    $original_img = '.' . $original_img; // 相对路径
    if (!is_file($original_img)) {
        return '/public/images/icon_goods_thumb_empty_300.png';
    }

    try {
        vendor('topthink.think-image.src.Image');
        if (strstr(strtolower($original_img), '.gif')) {
            vendor('topthink.think-image.src.image.gif.Encoder');
            vendor('topthink.think-image.src.image.gif.Decoder');
            vendor('topthink.think-image.src.image.gif.Gif');
        }
        $image = \think\Image::open($original_img);

        $goods_thumb_name = $goods_thumb_name . '.' . $image->type();
        // 生成缩略图
        !is_dir($path) && mkdir($path, 0777, true);
        // 参考文章 http://www.mb5u.com/biancheng/php/php_84533.html  改动参考 http://www.thinkphp.cn/topic/13542.html
        $image->thumb($width, $height, 2)->save($path . $goods_thumb_name, NULL, 100); //按照原图的比例生成一个最大为$width*$height的缩略图并保存
        //图片水印处理
        $water = tpCache('water');
        if ($water['is_mark'] == 1) {
            $imgresource = './' . $path . $goods_thumb_name;
            if ($width > $water['mark_width'] && $height > $water['mark_height']) {
                if ($water['mark_type'] == 'img') {
                    //检查水印图片是否存在
                    $waterPath = "." . $water['mark_img'];
                    if (is_file($waterPath)) {
                        $quality = $water['mark_quality'] ?: 80;
                        $waterTempPath = dirname($waterPath) . '/temp_' . basename($waterPath);
                        $image->open($waterPath)->save($waterTempPath, null, $quality);
                        $image->open($imgresource)->water($waterTempPath, $water['sel'], $water['mark_degree'])->save($imgresource);
                        @unlink($waterTempPath);
                    }
                } else {
                    //检查字体文件是否存在,注意是否有字体文件
                    $ttf = './hgzb.ttf';
                    if (file_exists($ttf)) {
                        $size = $water['mark_txt_size'] ?: 30;
                        $color = $water['mark_txt_color'] ?: '#000000';
                        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
                            $color = '#000000';
                        }
                        $transparency = intval((100 - $water['mark_degree']) * (127 / 100));
                        $color .= dechex($transparency);
                        $image->open($imgresource)->text($water['mark_txt'], $ttf, $size, $color, $water['sel'])->save($imgresource);
                    }
                }
            }
        }
        $img_url = '/' . $path . $goods_thumb_name;

        return $img_url;
    } catch (think\Exception $e) {

        return $original_img;
    }
}

/**
 * 商品相册缩略图
 */
function get_sub_images($sub_img, $goods_id, $width, $height)
{
    //判断缩略图是否存在
    $path = "public/upload/goods/thumb/$goods_id/";
    $goods_thumb_name = "goods_sub_thumb_{$sub_img['img_id']}_{$width}_{$height}";

    //这个缩略图 已经生成过这个比例的图片就直接返回了
    if (is_file($path . $goods_thumb_name . '.jpg')) return '/' . $path . $goods_thumb_name . '.jpg';
    if (is_file($path . $goods_thumb_name . '.jpeg')) return '/' . $path . $goods_thumb_name . '.jpeg';
    if (is_file($path . $goods_thumb_name . '.gif')) return '/' . $path . $goods_thumb_name . '.gif';
    if (is_file($path . $goods_thumb_name . '.png')) return '/' . $path . $goods_thumb_name . '.png';

    $ossClient = new \app\common\logic\OssLogic;
    if (($ossUrl = $ossClient->getGoodsAlbumThumbUrl($sub_img['image_url'], $width, $height))) {
        return $ossUrl;
    }

    $original_img = '.' . $sub_img['image_url']; //相对路径
    if (!is_file($original_img)) {
        return '/public/images/icon_goods_thumb_empty_300.png';
    }

    try {
        vendor('topthink.think-image.src.Image');
        if (strstr(strtolower($original_img), '.gif')) {
            vendor('topthink.think-image.src.image.gif.Encoder');
            vendor('topthink.think-image.src.image.gif.Decoder');
            vendor('topthink.think-image.src.image.gif.Gif');
        }
        $image = \think\Image::open($original_img);

        $goods_thumb_name = $goods_thumb_name . '.' . $image->type();
        // 生成缩略图
        !is_dir($path) && mkdir($path, 0777, true);
        // 参考文章 http://www.mb5u.com/biancheng/php/php_84533.html  改动参考 http://www.thinkphp.cn/topic/13542.html
        $image->thumb($width, $height, 2)->save($path . $goods_thumb_name, NULL, 100); //按照原图的比例生成一个最大为$width*$height的缩略图并保存
        //图片水印处理
        $water = tpCache('water');
        if ($water['is_mark'] == 1) {
            $imgresource = './' . $path . $goods_thumb_name;
            if ($width > $water['mark_width'] && $height > $water['mark_height']) {
                if ($water['mark_type'] == 'img') {
                    //检查水印图片是否存在
                    $waterPath = "." . $water['mark_img'];
                    if (is_file($waterPath)) {
                        $quality = $water['mark_quality'] ?: 80;
                        $waterTempPath = dirname($waterPath) . '/temp_' . basename($waterPath);
                        $image->open($waterPath)->save($waterTempPath, null, $quality);
                        $image->open($imgresource)->water($waterTempPath, $water['sel'], $water['mark_degree'])->save($imgresource);
                        @unlink($waterTempPath);
                    }
                } else {
                    //检查字体文件是否存在,注意是否有字体文件
                    $ttf = './hgzb.ttf';
                    if (file_exists($ttf)) {
                        $size = $water['mark_txt_size'] ?: 30;
                        $color = $water['mark_txt_color'] ?: '#000000';
                        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
                            $color = '#000000';
                        }
                        $transparency = intval((100 - $water['mark_degree']) * (127 / 100));
                        $color .= dechex($transparency);
                        $image->open($imgresource)->text($water['mark_txt'], $ttf, $size, $color, $water['sel'])->save($imgresource);
                    }
                }
            }
        }
        $img_url = '/' . $path . $goods_thumb_name;

        return $img_url;
    } catch (think\Exception $e) {

        return $original_img;
    }
}

/**
 * 刷新商品库存, 如果商品有设置规格库存, 则商品总库存 等于 所有规格库存相加
 * @param type $goods_id 商品id
 */
function refresh_stock($goods_id)
{
    $count = M("SpecGoodsPrice")->where("goods_id", $goods_id)->count();
    if ($count == 0) return false; // 没有使用规格方式 没必要更改总库存

    $store_count = M("SpecGoodsPrice")->where("goods_id", $goods_id)->sum('store_count');
    M("Goods")->where("goods_id", $goods_id)->save(array('store_count' => $store_count)); // 更新商品的总库存
}

/**
 * 根据 order_goods 表扣除商品库存
 * @param $order |订单对象或者数组
 * @throws \think\Exception
 */
function minus_stock($order)
{
    $orderGoodsArr = M('OrderGoods')->master()->where("order_id", $order['order_id'])->select();
    foreach ($orderGoodsArr as $key => $val) {
        $Goods=M('Goods')->where("goods_id", $val['goods_id'])->find();
        //库存变更记录
        digitalassets_log($order['user_id'],$order['order_id'],$val['goods_id'],$val['goods_num'],'库存减少',$Goods['store_count'],$Goods['store_count']-$val['goods_num'],1);

        // 有选择规格的商品
        if (!empty($val['spec_key'])) {   // 先到规格表里面扣除数量 再重新刷新一个 这件商品的总数量
            $SpecGoodsPrice = new \app\common\model\SpecGoodsPrice();
            $specGoodsPrice = $SpecGoodsPrice::get(['goods_id' => $val['goods_id'], 'key' => $val['spec_key']]);
            $specGoodsPrice->where(['goods_id' => $val['goods_id'], 'key' => $val['spec_key']])->setDec('store_count', $val['goods_num']);
            refresh_stock($val['goods_id']);
        } else {
            $specGoodsPrice = null;
            M('Goods')->where("goods_id", $val['goods_id'])->setDec('store_count', $val['goods_num']); // 直接扣除商品总数量
        }
        
        M('Goods')->where("goods_id", $val['goods_id'])->setInc('sales_sum', $val['goods_num']); // 增加商品销售量
        //更新活动商品购买量
        if ($val['prom_type'] == 1 || $val['prom_type'] == 2) {
            $GoodsPromFactory = new \app\common\logic\GoodsPromFactory();
            $goodsPromLogic = $GoodsPromFactory->makeModule($val, $specGoodsPrice);
            $prom = $goodsPromLogic->getPromModel();
            if ($prom['is_end'] == 0) {
                $tb = $val['prom_type'] == 1 ? 'flash_sale' : 'group_buy';
                M($tb)->where("id", $val['prom_id'])->setInc('buy_num', $val['goods_num']);
                M($tb)->where("id", $val['prom_id'])->setInc('order_num');
            }
        }
    }
}

/**
 * 邮件发送
 * @param $to    接收人
 * @param string $subject 邮件标题
 * @param string $content 邮件内容(html模板渲染后的内容)
 * @throws Exception
 * @throws phpmailerException
 */
function send_email($to, $subject = '', $content = '')
{
    vendor('phpmailer.PHPMailerAutoload'); ////require_once vendor/phpmailer/PHPMailerAutoload.php';
    //判断openssl是否开启
    $openssl_funcs = get_extension_funcs('openssl');
    if (!$openssl_funcs) {
        return array('status' => -1, 'msg' => '请先开启openssl扩展');
    }
    $mail = new PHPMailer;
    $config = tpCache('smtp');
    $mail->CharSet = 'UTF-8'; //设定邮件编码，默认ISO-8859-1，如果发中文此项必须设置，否则乱码
    $mail->isSMTP();
    //Enable SMTP debugging
    // 0 = off (for production use)
    // 1 = client messages
    // 2 = client and server messages
    $mail->SMTPDebug = 0;
    //调试输出格式
    //$mail->Debugoutput = 'html';
    //smtp服务器
    $mail->Host = $config['smtp_server'];
    //端口 - likely to be 25, 465 or 587
    $mail->Port = $config['smtp_port'];

    if ($mail->Port == 465) $mail->SMTPSecure = 'ssl';// 使用安全协议
    //Whether to use SMTP authentication
    $mail->SMTPAuth = true;
    //用户名
    $mail->Username = $config['smtp_user'];
    //密码
    $mail->Password = $config['smtp_pwd'];
    //Set who the message is to be sent from
    $mail->setFrom($config['smtp_user']);
    //回复地址
    //$mail->addReplyTo('replyto@example.com', 'First Last');
    //接收邮件方
    if (is_array($to)) {
        foreach ($to as $v) {
            $mail->addAddress($v);
        }
    } else {
        $mail->addAddress($to);
    }

    $mail->isHTML(true);// send as HTML
    //标题
    $mail->Subject = $subject;
    //HTML内容转换
    $mail->msgHTML($content);
    //Replace the plain text body with one created manually
    //$mail->AltBody = 'This is a plain-text message body';
    //添加附件
    //$mail->addAttachment('images/phpmailer_mini.png');
    //send the message, check for errors
    if (!$mail->send()) {
        return array('status' => -1, 'msg' => '发送失败: ' . $mail->ErrorInfo);
    } else {
        return array('status' => 1, 'msg' => '发送成功');
    }
}

/**
 * 检测是否能够发送短信
 * @param unknown $scene
 * @return multitype:number string
 */
function checkEnableSendSms($scene)
{

    $scenes = C('SEND_SCENE');
    $sceneItem = $scenes[$scene];
    if (!$sceneItem) {
        return array("status" => -1, "msg" => "场景参数'scene'错误!");
    }
    $key = $sceneItem[2];
    $sceneName = $sceneItem[0];
    $config = tpCache('sms');
    $smsEnable = $config[$key];

    if (!$smsEnable) {
        return array("status" => -1, "msg" => "['$sceneName']发送短信被关闭'");
    }
    //判断是否添加"注册模板"
    $size = M('sms_template')->where("send_scene", $scene)->count('tpl_id');
    if (!$size) {
        return array("status" => -1, "msg" => "请先添加['$sceneName']短信模板");
    }

    return array("status" => 1, "msg" => "可以发送短信");
}

/**
 * 发送短信逻辑
 * @param unknown $scene
 */
function sendSms($scene, $sender, $params, $unique_id = 0)
{
    $smsLogic = new \app\common\logic\SmsLogic;
    return $smsLogic->sendSms($scene, $sender, $params, $unique_id);
}

/**
 * 查询快递
 * @param $postcom  快递公司编码
 * @param $getNu  快递单号
 * @return array  物流跟踪信息数组
 */
function queryExpress($postcom, $getNu)
{
    /*    $url = "http://wap.kuaidi100.com/wap_result.jsp?rand=".time()."&id={$postcom}&fromWeb=null&postid={$getNu}";
        //$resp = httpRequest($url,'GET');
        $resp = file_get_contents($url);
        if (empty($resp)) {
            return array('status'=>0, 'message'=>'物流公司网络异常，请稍后查询');
        }
        preg_match_all('/\\<p\\>&middot;(.*)\\<\\/p\\>/U', $resp, $arr);
        if (!isset($arr[1])) {
            return array( 'status'=>0, 'message'=>'查询失败，参数有误' );
        }else{
            foreach ($arr[1] as $key => $value) {
                $a = array();
                $a = explode('<br /> ', $value);
                $data[$key]['time'] = $a[0];
                $data[$key]['context'] = $a[1];
            }
            return array( 'status'=>1, 'message'=>'1','data'=> array_reverse($data));
        }*/
    $url = "https://m.kuaidi100.com/query?type=" . $postcom . "&postid=" . $getNu . "&id=1&valicode=&temp=0.49738534969422676";
    $resp = httpRequest($url, "GET");
    return json_decode($resp, true);
}

/**
 * 获取某个商品分类的 儿子 孙子  重子重孙 的 id
 * @param type $cat_id
 */
function getCatGrandson($cat_id)
{
    $GLOBALS['catGrandson'] = array();
    $GLOBALS['category_id_arr'] = array();
    // 先把自己的id 保存起来
    $GLOBALS['catGrandson'][] = $cat_id;
    // 把整张表找出来
    $GLOBALS['category_id_arr'] = M('GoodsCategory')->cache(true, TPSHOP_CACHE_TIME)->getField('id,parent_id');
    // 先把所有儿子找出来
    $son_id_arr = M('GoodsCategory')->where("parent_id", $cat_id)->cache(true, TPSHOP_CACHE_TIME)->getField('id', true);
    foreach ($son_id_arr as $k => $v) {
        getCatGrandson2($v);
    }
    return $GLOBALS['catGrandson'];
}

/**
 * 获取某个文章分类的 儿子 孙子  重子重孙 的 id
 * @param type $cat_id
 */
function getArticleCatGrandson($cat_id)
{
    $GLOBALS['ArticleCatGrandson'] = array();
    $GLOBALS['cat_id_arr'] = array();
    // 先把自己的id 保存起来
    $GLOBALS['ArticleCatGrandson'][] = $cat_id;
    // 把整张表找出来
    $GLOBALS['cat_id_arr'] = M('ArticleCat')->getField('cat_id,parent_id');
    // 先把所有儿子找出来
    $son_id_arr = M('ArticleCat')->where("parent_id", $cat_id)->getField('cat_id', true);
    foreach ($son_id_arr as $k => $v) {
        getArticleCatGrandson2($v);
    }
    return $GLOBALS['ArticleCatGrandson'];
}

/**
 * 递归调用找到 重子重孙
 * @param type $cat_id
 */
function getCatGrandson2($cat_id)
{
    $GLOBALS['catGrandson'][] = $cat_id;
    foreach ($GLOBALS['category_id_arr'] as $k => $v) {
        // 找到孙子
        if ($v == $cat_id) {
            getCatGrandson2($k); // 继续找孙子
        }
    }
}


/**
 * 递归调用找到 重子重孙
 * @param type $cat_id
 */
function getArticleCatGrandson2($cat_id)
{
    $GLOBALS['ArticleCatGrandson'][] = $cat_id;
    foreach ($GLOBALS['cat_id_arr'] as $k => $v) {
        // 找到孙子
        if ($v == $cat_id) {
            getArticleCatGrandson2($k); // 继续找孙子
        }
    }
}

/**
 * 查看某个用户购物车中商品的数量
 * @param type $user_id
 * @param type $session_id
 * @return type 购买数量
 */
function cart_goods_num($user_id = 0, $session_id = '')
{
//    $where = " session_id = '$session_id' ";
//    $user_id && $where .= " or user_id = $user_id ";
    // 查找购物车数量
//    $cart_count =  M('Cart')->where($where)->sum('goods_num');
    $cart_count = Db::name('cart')->where(function ($query) use ($user_id, $session_id) {
        $query->where('session_id', $session_id);
        if ($user_id) {
            $query->whereOr('user_id', $user_id);
        }
    })->sum('goods_num');
    $cart_count = $cart_count ? $cart_count : 0;
    return $cart_count;
}

/**
 * 获取商品库存
 * @param type $goods_id 商品id
 * @param type $key 库存 key
 */
function getGoodNum($goods_id, $key)
{
    if (!empty($key)) {
        return M("SpecGoodsPrice")
            ->alias("s")
            ->join('_Goods_ g ', 's.goods_id = g.goods_id', 'LEFT')
            ->where(['g.goods_id' => $goods_id, 'key' => $key, "is_on_sale" => 1])->getField('s.store_count');
    } else {
        return M("Goods")->where(array("goods_id" => $goods_id, "is_on_sale" => 1))->getField('store_count');
    }
}

/**
 * 获取缓存或者更新缓存
 * @param string $config_key 缓存文件名称
 * @param array $data 缓存数据  array('k1'=>'v1','k2'=>'v3')
 * @return array or string or bool
 */
function tpCache($config_key, $data = array())
{
    $param = explode('.', $config_key);
    if (empty($data)) {
        //如$config_key=shop_info则获取网站信息数组
        //如$config_key=shop_info.logo则获取网站logo字符串
        $config = F($param[0], '', TEMP_PATH);//直接获取缓存文件
        if (empty($config)) {
            //缓存文件不存在就读取数据库
            $res = D('config')->where("inc_type", $param[0])->select();
            if ($res) {
                foreach ($res as $k => $val) {
                    $config[$val['name']] = $val['value'];
                }
                F($param[0], $config, TEMP_PATH);
            }
        }
        if (count($param) > 1) {
            return $config[$param[1]];
        } else {
            return $config;
        }
    } else {
        //更新缓存
        $result = D('config')->where("inc_type", $param[0])->select();
        if ($result) {
            foreach ($result as $val) {
                $temp[$val['name']] = $val['value'];
            }
            foreach ($data as $k => $v) {
                $newArr = array('name' => $k, 'value' => trim($v), 'inc_type' => $param[0]);
                if (!isset($temp[$k])) {
                    M('config')->add($newArr);//新key数据插入数据库
                } else {
                    if ($v != $temp[$k])
                        M('config')->where("name", $k)->save($newArr);//缓存key存在且值有变更新此项
                }
            }
            //更新后的数据库记录
            $newRes = D('config')->where("inc_type", $param[0])->select();
            foreach ($newRes as $rs) {
                $newData[$rs['name']] = $rs['value'];
            }
        } else {
            foreach ($data as $k => $v) {
                $newArr[] = array('name' => $k, 'value' => trim($v), 'inc_type' => $param[0]);
            }
            M('config')->insertAll($newArr);
            $newData = $data;
        }
        return F($param[0], $newData, TEMP_PATH);
    }
}
/**
 * 获取分销配置
 * @param string $config_key 缓存文件名称
 * @param array $data 缓存数据  array('k1'=>'v1','k2'=>'v3')
 * @param int $state 附加条件标识
 * @param array $other 补充的配置参数 格式为：'键名1'=>'值1','键名2'=>'值2'
 * @return array or string or bool
 */
function distributCache($config_key, $data = array(),$state = 0,$other = array())
{
    $param = strpos($config_key,'-') ? explode('-', $config_key) : explode('.', $config_key);
    $level = strpos($config_key,'-') ? explode('-', $config_key) : '0';
    //兼容没有switch字段的配置
    if(!isset($other['switch'])){
        $other['switch'] = 0;
    }
    //缓存名称
    $cache_name = $param[0];
    if($state != 0){
        //需要区分每个角色的条件
        $cache_name = $param[0].'-'.$level[1].'-'.$state;
    }
    if (empty($data)) {
        $config = Cache($cache_name);//直接获取分销配置
        if (empty($config)) {
            //缓存文件不存在就读取数据库
            $res = db('distribut_system')->where(array("inc_type"=> $param[0],"level_id"=>$level[1],"state"=>$state))->select();
            if ($res) {
                foreach ($res as $k => $val) {
                    $config[$val['name']] = $val['value'];
                }
                Cache($cache_name, $config);
            }
        }
        if (count($param) > 1) {
            return $level ? $config : $config[$param[1]];
        } else {
            return $config;
        }
    } else {
        //更新缓存
        $result = db('distribut_system')->where(['inc_type'=>$param[0],'level_id'=>$level[1],'state'=>$state])->select();
        if ($result) {
            foreach ($result as $val) {
                //需要匹配name
                $temp2[$val['name']] = $val['value'];
            }
            foreach ($data as $k => $v) {
                $newArr = array('name' => $k, 'value' => trim($v), 'inc_type' => $param[0],'level_id' => $level[1],'state' => $state,'switch' => $other['switch']);
                if (!isset($temp2[$k])) {
                    db('distribut_system')->insert($newArr);//新key数据插入数据库
                } else {
                    db('distribut_system')->where(['name'=>$k,'level_id'=>$level[1],'state'=>$state])->update($newArr);
                }
            }
            //更新后的数据库记录
            $newRes = db('distribut_system')->where(['inc_type'=>$param[0],'level_id'=>$level[1],'state'=>$state])->select();
            foreach ($newRes as $rs) {
                $newData[$rs['name']] = $rs['value'];
            }
        } else {
            foreach ($data as $k => $v) {
                $newArr[] = array('name' => $k, 'value' => trim($v), 'inc_type' => $param[0],'level_id' => $level[1],'state' => $state,'switch' => $other['switch']);
            }
            db('distribut_system')->insertAll($newArr);
            $newData = $data;
        }
        return Cache($cache_name, $newData);
    }
}
/**
 * 删除分销配置
 * @param string $level_id 角色id
 * @param array $data 删除的数据 array('name'=>'inc_type')
 * @param int $state 附加条件标识
 * @author Faramita
 * @return array or string or bool
 */
function delDistributCache($level_id,$data,$state){
    //删除数据库中配置
    foreach ($data as $k => $v) {
        $result = db('distribut_system')->where(['name'=>$k,'inc_type'=>$v,'level_id'=>$level_id,'state'=>$state])->delete();
        if($result == false){
            return false;
        }
        //删除缓存
        if($state != 0){
            Cache($v.'-'.$level_id.'-'.$state,NULL);
        }else{
            Cache($v,NULL);
        }
    }
    return true;
}
/**
 * 记录帐户变动
 * @param   int $user_id 用户id
 * @param   float $user_money 可用余额变动
 * @param   int $pay_points 消费积分变动
 * @param   string $desc 变动说明
 * @param   float   distribut_money 分佣金额
 * @param int $order_id 订单id
 * @param string $order_sn 订单sn
 * @return  bool
 */
function accountLog($user_id, $user_money = 0, $pay_points = 0, $desc = '', $distribut_money = 0, $order_id = 0, $order_sn = '',$type = 0)
{
    /* 插入帐户变动记录 */
    $account_log = array(
        'user_id' => $user_id,
        'user_money' => $user_money,
        'pay_points' => $pay_points,
        'change_time' => time(),
        'desc' => $desc,
        'order_id' => $order_id,
        'order_sn' => $order_sn,
        'type' => $type
    );

    /* 更新用户信息 */
//    $sql = "UPDATE __PREFIX__users SET user_money = user_money + $user_money," .
//        " pay_points = pay_points + $pay_points, distribut_money = distribut_money + $distribut_money WHERE user_id = $user_id";
    $update_data = array(
        'user_money' => ['exp', 'user_money+' . $user_money],
        //'pay_points' => ['exp', 'pay_points+' . $pay_points],
        'distribut_money' => ['exp', 'distribut_money+' . $distribut_money],
    );

    if (($user_money + $distribut_money) == 0)
        return false;

    if ($type==100) {
        $users=Db::name('users')->where('user_id', $user_id)->find();
        $update = Db::name('users')->where("user_id=$user_id")->update($update_data);
        if($update){
            balancelog($order_id, $user_id,$user_money, 18,$users['user_money'],$users['user_money']+$user_money);
            shoppinglog($order_id, $user_id,$distribut_money, 18,$users['distribut_money'],$users['distribut_money']+$distribut_money);
            M('account_log')->add($account_log);
        }
        return true;
    } else {
        return false;
    }
}

/**
 * 订单操作日志
 * 参数示例
 * @param type $order_id 订单id
 * @param type $action_note 操作备注
 * @param type $status_desc 操作状态  提交订单, 付款成功, 取消, 等待收货, 完成
 * @param type $user_id 用户id 默认为管理员
 * @return boolean
 */
function logOrder($order_id, $action_note, $status_desc, $user_id = 0)
{
    $status_desc_arr = array('提交订单', '付款成功', '取消', '等待收货', '完成', '退货');
    // if(!in_array($status_desc, $status_desc_arr))
    // return false;

    $order = M('order')->master()->where("order_id", $order_id)->find();
    $action_info = array(
        'order_id' => $order_id,
        'action_user' => 0,
        'order_status' => $order['order_status'],
        'shipping_status' => $order['shipping_status'],
        'pay_status' => $order['pay_status'],
        'action_note' => $action_note,
        'status_desc' => $status_desc, //''
        'log_time' => time(),
    );
    return M('order_action')->add($action_info);
}


/**
 * 服务订单操作日志 cp  上面logOrder
 * 参数示例
 * @param type $order_id  订单id
 * @param type $action_note 操作备注
 * @param type $status_desc 操作状态  提交订单, 付款成功, 取消, 等待收货, 完成
 * @param type $user_id  用户id 默认为管理员
 * @return boolean
 */
function logServerOrder($order_id,$action_note,$status_desc,$user_id = 0)
{

    $order = M('repair_order')->master()->where("order_id", $order_id)->find();
    $action_info = array(
        'order_id'        =>$order_id,
        'action_user'     => 1,
        'order_status'    =>$order['order_status'],
        'shipping_status' =>$order['order_type'],   //  发货状态  改成   订单类型
        'pay_status'      =>$order['pay_status'],
        'action_note'     => $action_note,
        'status_desc'     => $status_desc, //  状态描述
        'log_time'        =>time(),
    );
    return M('repair_action')->add($action_info);
}

/*
 * 获取地区列表
 */
function get_region_list()
{
    return M('region')->cache(true)->getField('id,name');
}

/*
 * 获取地区列表
 */
function get_region($id){
    return M('region')->field('name')->where(array('id'=>$id))->find();
}
/*
 * 获取用户地址列表
 */
function get_user_address_list($user_id)
{
    $lists = M('user_address')->where(array('user_id' => $user_id))->select();
    return $lists;
}

/*
 * 获取指定地址信息
 */
function get_user_address_info($user_id, $address_id)
{
    $data = M('user_address')->where(array('user_id' => $user_id, 'address_id' => $address_id))->find();
    return $data;
}

/*
 * 获取用户默认收货地址
 */
function get_user_default_address($user_id)
{
    $data = M('user_address')->where(array('user_id' => $user_id, 'is_default' => 1))->find();
    return $data;
}

/**
 * 获取订单状态的 中文描述名称
 * @param type $order_id 订单id
 * @param type $order 订单数组
 * @return string
 */
function orderStatusDesc($order_id = 0, $order = array())
{
    if (empty($order))
        $order = M('Order')->where("order_id", $order_id)->find();

    // 货到付款
    if ($order['pay_code'] == 'cod') {
        if (in_array($order['order_status'], array(0, 1)) && $order['shipping_status'] == 0)
            return 'WAITSEND'; //'待发货',
    } else // 非货到付款
    {
        if ($order['pay_status'] == 0 && $order['order_status'] == 0)
            return 'WAITPAY'; //'待支付',
        if ($order['pay_status'] == 1 && in_array($order['order_status'], array(0, 1)) && $order['shipping_status'] == 0)
            return 'WAITSEND'; //'待发货',
        if ($order['pay_status'] == 1 && $order['shipping_status'] == 2 && $order['order_status'] == 1)
            return 'PORTIONSEND'; //'部分发货',
    }
    if (($order['shipping_status'] == 1) && ($order['order_status'] == 1))
        return 'WAITRECEIVE'; //'待收货',
    if ($order['order_status'] == 2)
        return 'WAITCCOMMENT'; //'待评价',
    if ($order['order_status'] == 3)
        return 'CANCEL'; //'已取消',
    if ($order['order_status'] == 4)
        return 'FINISH'; //'已完成',
    if ($order['order_status'] == 5)
        return 'CANCELLED'; //'已作废',
    return 'OTHER';
}

/**
 * 获取订单状态的 显示按钮
 * @param type $order_id 订单id
 * @param type $order 订单数组
 * @return array()
 */
function orderBtn($order_id = 0, $order = array())
{
    if (empty($order))
        $order = M('Order')->where("order_id", $order_id)->find();
    /**
     *  订单用户端显示按钮
     * 去支付     AND pay_status=0 AND order_status=0 AND pay_code ! ="cod"
     * 取消按钮  AND pay_status=0 AND shipping_status=0 AND order_status=0
     * 确认收货  AND shipping_status=1 AND order_status=0
     * 评价      AND order_status=1
     * 查看物流  if(!empty(物流单号))
     */
    $btn_arr = array(
        'pay_btn' => 0, // 去支付按钮
        'cancel_btn' => 0, // 取消按钮
        'receive_btn' => 0, // 确认收货
        'comment_btn' => 0, // 评价按钮
        'shipping_btn' => 0, // 查看物流
        'return_btn' => 0, // 退货按钮 (联系客服)
    );


    // 货到付款
    if ($order['pay_code'] == 'cod') {
        if (($order['order_status'] == 0 || $order['order_status'] == 1) && $order['shipping_status'] == 0) // 待发货
        {
            $btn_arr['cancel_btn'] = 1; // 取消按钮 (联系客服)
        }
        if ($order['shipping_status'] == 1 && $order['order_status'] == 1) //待收货
        {
            $btn_arr['receive_btn'] = 1;  // 确认收货
            $btn_arr['return_btn'] = 1; // 退货按钮 (联系客服)
        }
    } // 非货到付款
    else {
        if ($order['pay_status'] == 0 && $order['order_status'] == 0) // 待支付
        {
            $btn_arr['pay_btn'] = 1; // 去支付按钮
            $btn_arr['cancel_btn'] = 1; // 取消按钮
        }
        if ($order['pay_status'] == 1 && in_array($order['order_status'], array(0, 1)) && $order['shipping_status'] == 0) // 待发货
        {
            // $btn_arr['return_btn'] = 1; // 退货按钮 (联系客服)
            $btn_arr['cancel_btn'] = 1; // 取消按钮
        }
        if ($order['pay_status'] == 1 && $order['order_status'] == 1 && $order['shipping_status'] == 1) //待收货
        {
            $btn_arr['receive_btn'] = 1;  // 确认收货
            // $btn_arr['return_btn'] = 1; // 退货按钮 (联系客服)
        }
    }
    if ($order['order_status'] == 2) {
        $btn_arr['comment_btn'] = 1;  // 评价按钮
        $btn_arr['return_btn'] = 1; // 退货按钮 (联系客服)
    }
    if ($order['shipping_status'] != 0 && in_array($order['order_status'], [1, 2, 4])) {
        $btn_arr['shipping_btn'] = 1; // 查看物流
    }
    if ($order['shipping_status'] == 2 && $order['order_status'] == 1) // 部分发货
    {
        // $btn_arr['return_btn'] = 1; // 退货按钮 (联系客服)
    }

    if ($order['pay_status'] == 1 && shipping_status && $order['order_status'] == 4) // 已完成(已支付, 已发货 , 已完成)
    {
        $btn_arr['return_btn'] = 1; // 退货按钮
    }

    if ($order['order_status'] == 3 && ($order['pay_status'] == 1 || $order['pay_status'] == 4)) {
        $btn_arr['cancel_info'] = 1; // 取消订单详情
    }

    return $btn_arr;
}

/**
 * 给订单数组添加属性  包括按钮显示属性 和 订单状态显示属性
 * @param type $order
 */
function set_btn_order_status($order)
{
    $order_status_arr = C('ORDER_STATUS_DESC');
    $order['order_status_code'] = $order_status_code = orderStatusDesc(0, $order); // 订单状态显示给用户看的
    $order['order_status_desc'] = $order_status_arr[$order_status_code];
    $orderBtnArr = orderBtn(0, $order);
    return array_merge($order, $orderBtnArr); // 订单该显示的按钮
}


/**
 * 支付完成修改订单
 * @param $order_sn 订单号
 * @param array $ext 额外参数
 * @return bool|void
 */
function update_pay_status($order_sn, $ext = array())
{
    if (stripos($order_sn, 'recharge') !== false) {
        //用户在线充值
        $order = M('recharge')->where(['order_sn' => $order_sn, 'pay_status' => 0])->find();
        if (!$order) return false;// 看看有没已经处理过这笔订单  支付宝返回不重复处理操作
        M('recharge')->where("order_sn", $order_sn)->save(array('pay_status' => 1, 'pay_time' => time()));
        accountLog($order['user_id'], $order['account'], 0, '会员在线充值');
    } else {
        // 如果这笔订单已经处理过了
        $count = M('order')->master()->where("order_sn = :order_sn and pay_status = 0 OR pay_status = 2")->bind(['order_sn' => $order_sn])->count();   // 看看有没已经处理过这笔订单  支付宝返回不重复处理操作
        if ($count == 0) return false;
        // 找出对应的订单
        $order = M('order')->master()->where("order_sn", $order_sn)->find();
        //TODO 更新维修服务订单
        if($order['suppliers_id']){
            // 修改支付状态  已支付
            $repair_order_data = array('pay_status' => 1, 'pay_time' => time(), 'last_time' => time(),'pay_code' => $order['pay_code'],'pay_name' => $order['pay_name']);
            if (isset($ext['transaction_id'])) $repair_order_data['transaction_id'] = $ext['transaction_id'];
            M('repair_order')->where("order_buy_id", $order['order_id'])->save($repair_order_data);
            $server_order = Db::name('repair_order')->where(['order_buy_id' => $order['order_id']])->find();
            logServerOrder($server_order['order_id'], $server_order['order_sn'].'预约安装订单付款成功', '付款成功', $order['user_id']);//记录日志
        }
        //预售订单
        if ($order['order_prom_type'] == 4) {
            $orderGoodsArr = M('OrderGoods')->where(array('order_id' => $order['order_id']))->find();
            // 预付款支付 有订金支付 修改支付状态  部分支付
            if ($order['total_amount'] != $order['order_amount'] && $order['pay_status'] == 0) {
                //支付订金
                M('order')->where("order_sn", $order_sn)->save(array('order_sn' => date('YmdHis') . mt_rand(1000, 9999), 'pay_status' => 2, 'pay_time' => time(), 'paid_money' => $order['order_amount']));
                M('goods_activity')->where(array('act_id' => $order['order_prom_id']))->setInc('act_count', $orderGoodsArr['goods_num']);
            } else {
                //全额支付 无订金支付 支付尾款
                M('order')->where("order_sn", $order_sn)->save(array('pay_status' => 1, 'pay_time' => time()));
                $pre_sell = M('goods_activity')->where(array('act_id' => $order['order_prom_id']))->find();
                $ext_info = unserialize($pre_sell['ext_info']);
                //全额支付 活动人数加一
                if (empty($ext_info['deposit'])) {
                    M('goods_activity')->where(array('act_id' => $order['order_prom_id']))->setInc('act_count', $orderGoodsArr['goods_num']);
                }
            }
        } else {
            // 修改支付状态  已支付
            $updata = array('pay_status' => 1, 'pay_time' => time());
            if (isset($ext['transaction_id'])) $updata['transaction_id'] = $ext['transaction_id'];
            M('order')->where("order_sn", $order_sn)->save($updata);
            // 发送微信消息模板提醒
            $wechat = new \app\common\logic\WechatLogic;
            $wechat->sendTemplateMsgBuyOrder($order);
            $wechat->sendTemplateMsgServiceOrderPay($order);
        }

        // 减少对应商品的库存.注：拼团类型为抽奖团的，先不减库存
        if (tpCache('shopping.reduce') == 2) {
            if ($order['order_prom_type'] == 6) {
                $team = \app\common\model\TeamActivity::get($order['order_prom_id']);
                if ($team['team_type'] != 2) {
                    minus_stock($order);
                }
            } else {
                minus_stock($order);
            }
        }
        // 给他升级, 根据order表查看消费记录 给他会员等级升级 修改他的折扣 和 总金额
        update_user_level($order['user_id']);
        // 记录订单操作日志
        if (array_key_exists('admin_id', $ext)) {
            logOrder($order['order_id'], $ext['note'], '付款成功', $ext['admin_id']);
        } else {
            logOrder($order['order_id'], '订单付款成功', '付款成功', $order['user_id']);
        }

        //分销设置
        $maid_time = distributCache('settlement.maid_time');
        $rebate_status = $maid_time == 0 ? 2 : 1;//$rebate_status = 1 按确认收货规则，2 按订单支付分佣规则分佣
        M('rebate_log')->where("order_id", $order['order_id'])->save(array('status' => $rebate_status,'pay_time'=>time()));

        // 成为分销商条件
        $distribut_condition = distributCache('levels.condition');
        if ($distribut_condition == 1)  // 购买商品付款才可以成为分销商
            M('users')->where("user_id", $order['user_id'])->save(array('is_distribut' => 1));
        //虚拟服务类商品支付
        if ($order['order_prom_type'] == 5) {
            $OrderLogic = new \app\common\logic\OrderLogic();
            $OrderLogic->make_virtual_code($order);
        }
        if ($order['order_prom_type'] == 6) {
            $TeamOrderLogic = new \app\common\logic\TeamOrderLogic();
            $team = \app\common\model\TeamActivity::get($order['order_prom_id']);
            $TeamOrderLogic->setTeam($team);
            $TeamOrderLogic->doOrderPayAfter($order);
        }
        //发票生成
        $Invoice = new \app\admin\logic\InvoiceLogic();
        $Invoice->create_Invoice($order);

        //用户支付, 发送短信给商家  //下单
        $res = checkEnableSendSms("4");
        if (!$res || $res['status'] != 1) return;

        $sender = tpCache("sms.order_pay_sms_enable");
        if (empty($sender)) return;
        // 查出当前门店的联系方式
        $sender = Db::name('suppliers')->where(['suppliers_id' => $order['suppliers_id']])->value('suppliers_phone');
        if (empty($sender)) return;
        $params = array('status' => '订单待处理','remark'=>'请留意订单详细信息');
        sendSms("8", $sender, $params);

        // 检测当前用户是否已经绑定身份
        $leader = Db::name('users')->field('perpetual,first_leader')->where(['user_id' => $order['user_id']])->find();
        $perpetual = $leader['perpetual'];
        if(!$perpetual) { //  没有永久绑定上下级关系
            $level_state = Db::name('distribut_system')->where(['inc_type' => 'levels', 'name' => 'level_state'])->value('value');
            if ($level_state == 2){
                // 首次下单 永久绑定上下级关系
                Db::name('users')->where(['user_id' => $order['user_id']])->save(['perpetual'=>1]);
                $wechat->sendTemplateMsgBindLeader($leader['first_leader'], $order['user_id']);
            }
        }
    }

}

/**
 * 订单确认收货
 * @param $id //订单id
 * @param int $user_id
 * @return array
 */
function confirm_order($id, $user_id = 0)
{
    $where['order_id'] = $id;
    if ($user_id) {
        $where['user_id'] = $user_id;
    }
    $order = db('order')->where($where)->find();
    if ($order['order_status'] != 1)
        return array('status' => -1, 'msg' => '该订单不能收货确认');
    if (empty($order['pay_time']) || $order['pay_status'] != 1) {
        return array('status' => -1, 'msg' => '商家未确定付款，该订单暂不能确定收货');
    }
    $data['order_status'] = 2; // 已收货
    $data['pay_status'] = 1; // 已付款
    $data['confirm_time'] = time(); // 收货确认时间
    if ($order['pay_code'] == 'cod') {
        $data['pay_time'] = time();
    }
    /*$row = db('order')->where(array('order_id' => $id))->update($data);
    if ($row){
        //准备验证升级角色,此处是确认收货的地方----------标记<QualificationLogic>
        $qualificationLogic = new \app\common\logic\QualificationLogic();
        $qualificationLogic->prepare_update_level($user_id);
    }else{
        return array('status' => -3, 'msg' => '操作失败');
    }*/
    //order_give($order);// 调用送礼物方法, 给下单这个人赠送相应的礼物
    //分销设置
    $maid_time = distributCache('settlement.maid_time');
    if($maid_time){
        db('rebate_log')->where("order_id", $id)->update(array('status' => 2, 'confirm' => time()));
    }else{
        db('rebate_log')->where("order_id", $id)->update(array('confirm' => time()));
    }

    return array('status' => 1, 'msg' => '操作成功', 'url' => U('Order/order_detail', ['id' => $id]));
}

/**
 * 下单赠送活动：优惠券，积分
 * @param $order |订单数组
 */
function order_give($order)
{
    //促销优惠订单商品
    $prom_order_goods = M('order_goods')->where(['order_id' => $order['order_id'], 'prom_type' => 3])->select();
    //获取用户会员等级
//    $user_level = M('users')->where(['user_id' => $order['user_id']])->getField('level');
    foreach ($prom_order_goods as $goods) {
        //查找购买商品送优惠券活动
        $prom_goods = M('prom_goods')->where(['id' => $goods['prom_id'], 'type' => 3])->find();
        if ($prom_goods) {
            //查找购买商品送优惠券模板
            $goods_coupon = M('coupon')->where(['id' => $prom_goods['expression']])->find();
//            if ($goods_coupon && !empty($prom_goods['group'])) {
            if ($goods_coupon) {
                // 用户会员等级是否符合送优惠券活动
//                if (in_array($user_level, explode(',', $prom_goods['group']))) {
                //优惠券发放数量验证，0为无限制。发放数量-已领取数量>0
                if ($goods_coupon['createnum'] == 0 ||
                    ($goods_coupon['createnum'] > 0 && ($goods_coupon['createnum'] - $goods_coupon['send_num']) > 0)
                ) {
                    $data = array('cid' => $goods_coupon['id'], 'get_order_id' => $order['order_id'], 'type' => $goods_coupon['type'], 'uid' => $order['user_id'], 'send_time' => time());
                    M('coupon_list')->add($data);
                    // 优惠券领取数量加一
                    M('Coupon')->where("id", $goods_coupon['id'])->setInc('send_num');
                }
//                }
            }
        }
    }
    //查找订单满额促销活动
    $prom_order_where = [
        'type' => ['gt', 1],
        'end_time' => ['gt', $order['pay_time']],
        'start_time' => ['lt', $order['pay_time']],
        'money' => ['elt', $order['goods_price']]
    ];
    $prom_orders = M('prom_order')->where($prom_order_where)->order('money desc')->select();
    $prom_order_count = count($prom_orders);
    // 用户会员等级是否符合送优惠券活动
    for ($i = 0; $i < $prom_order_count; $i++) {
//        if (in_array($user_level, explode(',', $prom_orders[$i]['group']))) {
        $prom_order = $prom_orders[$i];
        if ($prom_order['type'] == 3) {
            //查找订单送优惠券模板
            $order_coupon = M('coupon')->where("id", $prom_order['expression'])->find();
            if ($order_coupon) {
                //优惠券发放数量验证，0为无限制。发放数量-已领取数量>0
                if ($order_coupon['createnum'] == 0 ||
                    ($order_coupon['createnum'] > 0 && ($order_coupon['createnum'] - $order_coupon['send_num']) > 0)
                ) {
                    $data = array('cid' => $order_coupon['id'], 'get_order_id' => $order['order_id'], 'type' => $order_coupon['type'], 'uid' => $order['user_id'], 'send_time' => time());
                    M('coupon_list')->add($data);
                    M('Coupon')->where("id", $order_coupon['id'])->setInc('send_num'); // 优惠券领取数量加一
                }
            }
        }
        //购买商品送积分
        if ($prom_order['type'] == 2) {
            accountLog($order['user_id'], 0, $prom_order['expression'], "订单活动赠送积分");
        }
        break;
//        }
    }
    $points = M('order_goods')->where("order_id", $order['order_id'])->sum("give_integral * goods_num");
    $points && accountLog($order['user_id'], 0, $points, "下单赠送积分", 0, $order['order_id'], $order['order_sn']);
}


/**
 * 查看订单是否满足条件参加活动
 * @param $order_amount
 * @return array
 */
function get_order_promotion($order_amount)
{
//    $parse_type = array('0'=>'满额打折','1'=>'满额优惠金额','2'=>'满额送倍数积分','3'=>'满额送优惠券','4'=>'满额免运费');
    $now = time();
    $prom = M('prom_order')->where("type<2 and end_time>$now and start_time<$now and money<=$order_amount")->order('money desc')->find();
    $res = array('order_amount' => $order_amount, 'order_prom_id' => 0, 'order_prom_amount' => 0);
    if ($prom) {
        if ($prom['type'] == 0) {
            $res['order_amount'] = round($order_amount * $prom['expression'] / 100, 2);//满额打折
            $res['order_prom_amount'] = round($order_amount - $res['order_amount'], 2);
            $res['order_prom_id'] = $prom['id'];
        } elseif ($prom['type'] == 1) {
            $res['order_amount'] = $order_amount - $prom['expression'];//满额优惠金额
            $res['order_prom_amount'] = $prom['expression'];
            $res['order_prom_id'] = $prom['id'];
        }
    }
    return $res;
}

/**
 * 计算订单金额
 * @param int $user_id 用户id
 * @param $order_goods 购买的商品
 * @param string $shipping_code 物流code
 * @param int $shipping_price 物流费用, 如果传递了物流费用 就不在计算物流费
 * @param int $province 省份
 * @param int $city 城市
 * @param int $district 县
 * @param int $pay_points 积分
 * @param int $user_money 余额
 * @param int $coupon_id 优惠券
 * @return array
 */
function calculate_price($user_id = 0, $order_goods, $shipping_code = '', $shipping_price = 0, $province = 0, $city = 0, $district = 0, $pay_points = 0, $user_money = 0, $coupon_id = 0)
{
    $couponLogic = new \app\common\logic\CouponLogic();
    $goodsLogic = new app\common\logic\GoodsLogic();
    $user = M('users')->where("user_id", $user_id)->find();// 找出这个用户
    $result = [];
    if (empty($order_goods)) {
        return array('status' => -9, 'msg' => '商品列表不能为空', 'result' => '');
    }
    $use_percent_point = tpCache('shopping.point_use_percent') / 100;     //最大使用限制: 最大使用积分比例, 例如: 为50时, 未50% , 那么积分支付抵扣金额不能超过应付金额的50%
    /*判断能否使用积分
     1..积分低于point_min_limit时,不可使用
     2.在不使用积分的情况下, 计算商品应付金额
     3.原则上, 积分支付不能超过商品应付金额的50%, 该值可在平台设置
     @{ */
    $point_rate = tpCache('shopping.point_rate'); //兑换比例: 如果拥有的积分小于该值, 不可使用
    $min_use_limit_point = tpCache('shopping.point_min_limit'); //最低使用额度: 如果拥有的积分小于该值, 不可使用

    if ($min_use_limit_point > 0 && $pay_points > 0 && $pay_points < $min_use_limit_point) {
        return array('status' => -1, 'msg' => "您使用的积分必须大于{$min_use_limit_point}才可以使用", 'result' => ''); // 返回结果状态
    }
    // 计算该笔订单最多使用多少积分
    /*  if(($use_percent_point !=1 ) && $pay_points > $result['order_integral']) {
          return array('status'=>-1,'msg'=>"该笔订单, 您使用的积分不能大于{$result['order_integral']}",'result'=>'积分'); // 返回结果状态
      }

      if(($pay_points > 0 && $use_percent_point == 0) ||  ($pay_points >0 && $result['order_integral']==0)){
          return array('status' => -1, 'msg' => "该笔订单不能使用积分", 'result' => '积分'); // 返回结果状态
      }*/

    if ($pay_points && ($pay_points > $user['pay_points']))
        return array('status' => -5, 'msg' => "你的账户可用积分为:" . $user['pay_points'], 'result' => ''); // 返回结果状态
    if ($user_money && ($user_money > $user['user_money']))
        return array('status' => -6, 'msg' => "你的账户可用余额为:" . $user['user_money'], 'result' => ''); // 返回结果状态

    $goods_id_arr = get_arr_column($order_goods, 'goods_id');
    if($order_goods[0]['goods']['type_id'] == 6 && $order_goods[0]['goods']['status'] == 0){
        $goods_arr = M('goods')->where("goods_id in(" . implode(',', $goods_id_arr) . ")")->cache(true, TPSHOP_CACHE_TIME)
            ->getField('goods_id,weight,market_price,is_free_shipping,exchange_integral,shop_price'); // 商品id 和重量对应的键值对
    }
    $goods_arr = M('goods')->where("goods_id in(" . implode(',', $goods_id_arr) . ")")->cache(true, TPSHOP_CACHE_TIME)
        ->getField('goods_id,weight,market_price,is_free_shipping,exchange_integral,shop_price'); // 商品id 和重量对应的键值对
    $goods_weight = $goods_price = $goods_integral = $quota = $cut_fee = $anum = $coupon_price = 0;  //定义一些变量
    foreach ($order_goods as $key => $val) {
        // 如果传递过来的商品列表没有定义会员价
        if (!array_key_exists('member_goods_price', $val)) {
            $user['discount'] = $user['discount'] ? $user['discount'] : 1; // 会员折扣 不能为 0
            $order_goods[$key]['member_goods_price'] = $val['member_goods_price'] = $val['goods_price'] * $user['discount'];
        }
        //trace($val['goods_id'].'-包邮否-'.$goods_arr[$val['goods_id']]['is_free_shipping'],'debug');
        //如果商品不是包邮的
        if ($goods_arr[$val['goods_id']]['is_free_shipping'] == 0)
            $goods_weight += $goods_arr[$val['goods_id']]['weight'] * $val['goods_num']; //累积商品重量 每种商品的重量 * 数量
        //计算订单可用积分
        if ($goods_arr[$val['goods_id']]['exchange_integral'] > 0) {
            //商品设置了积分兑换就用商品本身的积分。
            $result['order_integral'] += $goods_arr[$val['goods_id']]['exchange_integral'];
        } else {
            //没有就按照会员价与平台设置的比例来计算。
            $result['order_integral'] += ceil($order_goods[$key]['member_goods_price'] * $use_percent_point);
        }
        $order_goods[$key]['goods_fee'] = $val['goods_num'] * $val['member_goods_price'];    // 小计 收益积分
        $order_goods[$key]['shop_integral'] = $val['goods_num'] * $val['goods']['cost_price'];    // 小计 兑换积分
        if($order_goods[$key]['goods']['type_id'] == 6 && $order_goods[$key]['goods']['status'] == 0){
            $order_goods[$key]['quota'] = $val['goods_num'] * $val['goods']['quota'];    // 小计 配额

            $order_goods[$key]['store_count'] = Db::name('goods_setmeal')->alias('s')->join('goods g','g.goods_id = s.goods_id')
                ->where("s.id = {$val['goods_id']} and g.is_on_sale = 1")
                ->value('s.stock');
        }else{
            $order_goods[$key]['store_count'] = getGoodNum($val['goods_id'], $val['spec_key']); // 最多可购买的库存数量
        }
        if ($order_goods[$key]['store_count'] <= 0 || $order_goods[$key]['store_count'] < $order_goods[$key]['goods_num'])
            return array('status' => -10, 'msg' => $order_goods[$key]['goods_name'] . ',' . $val['spec_key_name'] . "库存不足,请重新下单", 'result' => '');

        $goods_price += $order_goods[$key]['goods_fee']; // 商品总价
        $goods_integral += $order_goods[$key]['shop_integral']; // 商品总兑换积分
        $quota += $order_goods[$key]['quota']; // 商品总配额
        $cut_fee += $val['goods_num'] * $val['market_price'] - $val['goods_num'] * $val['member_goods_price']; // 共节约
        $anum += $val['goods_num']; // 购买数量
    }
    // 优惠券处理操作
    if ($coupon_id && $user_id) {
        $coupon_price = $couponLogic->getCouponMoney($user_id, $coupon_id); // 下拉框方式选择优惠券
    }
    // 处理物流
    if ($shipping_price == 0) {
        $freight_free = tpCache('shopping.freight_free'); // 全场满多少免运费
        if ($freight_free > 0 && $goods_price >= $freight_free) {
            $shipping_price = 0;
        } else {
            $shipping_price = $goodsLogic->getFreight($shipping_code, $province, $city, $district, $goods_weight);
        }
    }


    $order_amount = $goods_price + $shipping_price - $coupon_price; // 应付金额 = 商品价格 + 物流费 - 优惠券
    $user_money = ($user_money > $order_amount) ? $order_amount : $user_money;  // 余额支付余额不能大于应付金额，原理等同于积分
    $order_amount = $order_amount - $user_money;  //余额支付抵应付金额 （如果未付完，剩余多少没付）

    // 积分支付 100 积分等于 1块钱
    if ($pay_points > floor($order_amount * $point_rate)) {
        $pay_points = floor($order_amount * $point_rate);
    }

    $integral_money = ($pay_points / $point_rate);
    $order_amount = $order_amount - $integral_money; //  积分抵消应付金额 （如果未付完，剩余多少没付）

    $total_amount = $goods_price + $shipping_price;  //订单总价

    // 订单满额优惠活动
    $order_prom = get_order_promotion($goods_price);
    //订单总价  应付金额  物流费  商品总价 节约金额 共多少件商品 积分  余额  优惠券
    $result = array(
        'total_amount' => $total_amount, // 订单总价
        'order_amount' => round($order_amount - $order_prom['order_prom_amount'], 2), // 应付金额(要减去优惠的钱)
        'shipping_price' => $shipping_price, // 物流费
        'goods_price' => $goods_price, // 商品总价
        'goods_integral' => $goods_integral, // 商品总兑换积分
        'quota' => $quota, // 商品总配额
        'cut_fee' => $cut_fee, // 共节约多少钱
        'anum' => $anum, // 商品总共数量
        'integral_money' => $integral_money,  // 积分抵消金额
        'user_money' => $user_money, // 使用余额
        'coupon_price' => $coupon_price,// 优惠券抵消金额
        'order_prom_id' => $order_prom['order_prom_id'],
        'order_prom_amount' => $order_prom['order_prom_amount'],
        'order_goods' => $order_goods, // 商品列表 多加几个字段原样返回
    );
//    echo 1;
//    dump($result);exit;
    return array('status' => 1, 'msg' => "计算价钱成功", 'result' => $result); // 返回结果状态
}

/**
 * 获取商品一二三级分类
 * @return type
 */
function get_goods_category_tree()
{
    $tree = $arr = $result = array();
    $cat_list = M('goods_category')->cache(true)->where("is_show = 1")->order('sort_order')->select();//所有分类
    if ($cat_list) {
        foreach ($cat_list as $val) {
            if ($val['level'] == 2) {
                $arr[$val['parent_id']][] = $val;
            }
            if ($val['level'] == 3) {
                $crr[$val['parent_id']][] = $val;
            }
            if ($val['level'] == 1) {
                $tree[] = $val;
            }
        }

        foreach ($arr as $k => $v) {
            foreach ($v as $kk => $vv) {
                $arr[$k][$kk]['sub_menu'] = empty($crr[$vv['id']]) ? array() : $crr[$vv['id']];
            }
        }

        foreach ($tree as $val) {
            $val['tmenu'] = empty($arr[$val['id']]) ? array() : $arr[$val['id']];
            $result[$val['id']] = $val;
        }
    }
    return $result;
}

/**
 * 写入静态页面缓存
 */
function write_html_cache($html)
{
    $html_cache_arr = C('HTML_CACHE_ARR');
    $request = think\Request::instance();
    $m_c_a_str = $request->module() . '_' . $request->controller() . '_' . $request->action(); // 模块_控制器_方法
    $m_c_a_str = strtolower($m_c_a_str);
    //exit('write_html_cache写入缓存<br/>');
    foreach ($html_cache_arr as $key => $val) {
        $val['mca'] = strtolower($val['mca']);
        if ($val['mca'] != $m_c_a_str) //不是当前 模块 控制器 方法 直接跳过
            continue;

        //if(!is_dir(RUNTIME_PATH.'html'))
        //mkdir(RUNTIME_PATH.'html');
        //$filename =  RUNTIME_PATH.'html'.DIRECTORY_SEPARATOR.$m_c_a_str;
        $filename = $m_c_a_str;
        // 组合参数
        if (isset($val['p'])) {
            foreach ($val['p'] as $k => $v)
                $filename .= '_' . $_GET[$v];
        }
        $filename .= '.html';
        \think\Cache::set($filename, $html);
        //file_put_contents($filename, $html);
    }
}

/**
 * 读取静态页面缓存
 */
function read_html_cache()
{
    $html_cache_arr = C('HTML_CACHE_ARR');
    $request = think\Request::instance();
    $m_c_a_str = $request->module() . '_' . $request->controller() . '_' . $request->action(); // 模块_控制器_方法
    $m_c_a_str = strtolower($m_c_a_str);
    //exit('read_html_cache读取缓存<br/>');
    foreach ($html_cache_arr as $key => $val) {
        $val['mca'] = strtolower($val['mca']);
        if ($val['mca'] != $m_c_a_str) //不是当前 模块 控制器 方法 直接跳过
            continue;

        //$filename =  RUNTIME_PATH.'html'.DIRECTORY_SEPARATOR.$m_c_a_str;
        $filename = $m_c_a_str;
        // 组合参数
        if (isset($val['p'])) {
            foreach ($val['p'] as $k => $v)
                $filename .= '_' . $_GET[$v];
        }
        $filename .= '.html';
        $html = \think\Cache::get($filename);
        if ($html) {
            //echo file_get_contents($filename);
            echo \think\Cache::get($filename);
            exit();
        }
    }
}

/**
 * 获取完整地址
 */
function getTotalAddress($province_id, $city_id, $district_id, $twon_id, $address = '')
{
    static $regions = null;
    if (!$regions) {
        $regions = M('region')->cache(true)->getField('id,name');
    }
    $total_address = $regions[$province_id] ?: '';
    $total_address .= $regions[$city_id] ?: '';
    $total_address .= $regions[$district_id] ?: '';
    $total_address .= $regions[$twon_id] ?: '';
    $total_address .= $address ?: '';
    return $total_address;
}

/**
 * 商品库存操作日志
 * @param int $muid 操作 用户ID
 * @param int $stock 更改库存数
 * @param array $goods 库存商品
 * @param string $order_sn 订单编号
 */
function update_stock_log($muid, $stock = 1, $goods, $order_sn = '')
{
    $data['ctime'] = time();
    $data['stock'] = $stock;
    $data['muid'] = $muid;
    $data['goods_id'] = $goods['goods_id'];
    $data['goods_name'] = $goods['goods_name'];
    $data['goods_spec'] = empty($goods['spec_key_name']) ? '' : $goods['spec_key_name'];
    $data['order_sn'] = $order_sn;
    M('stock_log')->add($data);
}

/**
 * 订单支付时, 获取订单商品名称
 * @param unknown $order_id
 * @return string|Ambigous <string, unknown>
 */
function getPayBody($order_id)
{

    if (empty($order_id)) return "订单ID参数错误";
    $goodsNames = M('OrderGoods')->where('order_id', $order_id)->column('goods_name');
    $gns = implode($goodsNames, ',');
    $payBody = getSubstr($gns, 0, 18);
    return $payBody;
}


/**
 * 随机字符串生成    2017-10-15
 * @param $length 字符串长度
 */
function createnoncestr($length = 16)
{
    $random = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    $string = "";
    for ($i = 0; $i < $length; $i++) {
        $string .= substr($random, mt_rand(0, strlen($random) - 1), 1);
    }
    return $string;
}

/**
 * 退款原路返回
 * @param $openid   退款人的openid
 * @param $ordernumber 订单号
 * @param $total_fee 订单总金额
 * @param $refund_fee 退款金额
 * @return array
 */
function wxRefund($openid, $ordernumber, $total_fee, $refund_fee)
{
    require_once 'extend/My/WxPay.php';
    $paymentPlugin = M('Plugin')->where("code='weixin' and  type = 'payment' ")->find(); // 找到微信支付插件的配置
    $config_value = unserialize($paymentPlugin['config_value']); // 配置反序列化

    $param = array(
        'appid' => $config_value['appid'],
        'mch_id' => $config_value['mchid'],
        'partnerkey' => $config_value['key'],
        'openid' => $openid,
        'ordernumber' => $ordernumber,
        'total_fee' => $total_fee,
        'refund_fee' => $refund_fee,
        'path1' => '/static/common/apiclient/apiclient_cert.pem',    //证书1路径
        'path2' => '/static/common/apiclient/apiclient_key.pem'    //证书2路径
    );

    $WxPay = new \My\WxPay();
    $result = $WxPay->refund($param);
    dump($result);
    if ($result['result_code'] === 'SUCCESS') {
        return [1];
    } else {
        return [0, $result['return_msg']];
    }

}

/**
 * @param $openid  用户openid
 * @param $money  金额
 * @param $ordernumber 支付单号
 * @param string $desc 描述信息
 * @return array
 */
function wxEnterprisePayment($openid, $money, $ordernumber, $desc = '企业付款')
{
    require_once 'extend/My/WxPay.php';
    $paymentPlugin = M('Plugin')->where("code='weixin' and  type = 'payment' ")->find(); // 找到微信支付插件的配置
    $config_value = unserialize($paymentPlugin['config_value']); // 配置反序列化

    $parameter = [
        'mch_appid' => $config_value['appid'],    //公众平台APPID
        'mchid' => $config_value['mchid'],        //商户号
        'partnerkey' => $config_value['key'],    //密钥
        'money' => $money,    //商户平台密钥
        'openid' => $openid,    //用户OPENID
        'ordernumber' => $ordernumber,    //付款订单号，不能重复
        'desc' => $desc,
        'path1' => '/static/common/apiclient/apiclient_cert.pem',    //证书1路径
        'path2' => '/static/common/apiclient/apiclient_key.pem'    //证书2路径
    ];

    $WxPay = new \My\WxPay();
    $result = $WxPay->pay($parameter);
    dump($result);

    if ($result['result_code'] === 'SUCCESS') {
        return [1];
    } else {
        return [0, $result['err_code_des']];
    }
}


/**
 * @param $enc_bank_no  卡号
 * @param $enc_true_name 姓名
 * @param $bank_code 编号
 * @param $ordernumber 支付单号
 * @param $money 金额
 * @return array
 */
function wxbankPay($enc_bank_no, $enc_true_name, $bank_code, $ordernumber, $money)
{
    require_once 'extend/My/WxPay.php';
    $paymentPlugin = M('Plugin')->where("code='weixin' and  type = 'payment' ")->find(); // 找到微信支付插件的配置
    $config_value = unserialize($paymentPlugin['config_value']); // 配置反序列化

    $param = array(
        'mch_id' => $config_value['mchid'],
        'ordernumber' => $ordernumber,
        'money' => $money,
        'enc_bank_no' => $enc_bank_no,
        'enc_true_name' => $enc_true_name,
        'bank_code' => $bank_code,
        'path1' => '/static/common/apiclient/apiclient_cert.pem',    //证书1路径
        'path2' => '/static/common/apiclient/apiclient_key.pem'    //证书2路径
    );

    $WxPay = new \My\WxPay();
    $result = $WxPay->bankPay($param);
    dump($result);
    if ($result['result_code'] === 'SUCCESS') {
        return [1];
    } else {
        return [0, $result['return_msg']];
    }

}

/**
 * 检测是否有数据
 * @param  sting $table       表名
 * @param  sting $field       字段名
 * @param  string $field_value 字段值
 * @return arr              结果集
 */
function confirm_exist($table,$field,$field_value){
    return Db::name($table)->where(["$field"=>"$field_value"])->find();
}

/**
 * 支付完成修改服务订单
 * @param $order_sn 订单号
 * @param array $ext 额外参数
 * @return bool|void
 */
function update_server_pay_status($order_sn, $ext)
{

        // 如果这笔订单已经处理过了
        // 看看有没已经处理过这笔订单  支付宝返回不重复处理操作
        $count = M('repair_order')->master()->where("order_sn = :order_sn and pay_status = 0")->bind(['order_sn' => $order_sn])->count();
        if ($count == 0) return false;
        // 找出对应的订单
        $order = M('repair_order')->master()->where("order_sn", $order_sn)->find();

        // 修改支付状态  已支付
        $updata = array('pay_status' => 1, 'pay_time' => time());
        if (isset($ext['transaction_id'])) $updata['transaction_id'] = $ext['transaction_id'];
        M('repair_order')->where("order_sn", $order_sn)->save($updata);

        // 记录订单操作日志
        logServerOrder($order['order_id'], $order['order_sn'].'订单付款'.$order['paid_price'].'元成功', '付款成功', $order['user_id']);

        // 记录订单操作日志
//        if (array_key_exists('admin_id', $ext)) {
            // 无后台操作
//            logServerOrder($order['order_id'], $ext['note'], '付款'.$order['paid_price'].'元成功', $ext['admin_id']);
//        } else {
//            logServerOrder($order['order_id'], '订单付款'.$order['paid_price'].'元成功', '付款成功', $order['user_id']);
//        }

//        //发票生成
//        $Invoice = new \app\admin\logic\InvoiceLogic();
//        $Invoice->create_Invoice($order);
       // 发送微信消息模板提醒
        $wechat = new \app\common\logic\WechatLogic;
        $wechat->sendTemplateMsgServiceOrderPay($order);




        //用户支付, 发送短信给商家  //下单
        $res = checkEnableSendSms("4");
        if (!$res || $res['status'] != 1) return;
        $sender = tpCache("sms.order_pay_sms_enable");
        if (empty($sender)) return;
        // 查出当前门店的联系方式
        $sender = Db::name('suppliers')->where(['suppliers_id' => $order['suppliers_id']])->value('suppliers_phone');
        if (empty($sender)) return;
        $params = array('status' => '订单待处理','remark'=>'请留意订单详细信息');
        sendSms("8", $sender, $params);
}

/**
 * 生成宣传海报
 * @param array  参数,包括图片和文字
 * @param string  $filename 生成海报文件名,不传此参数则不生成文件,直接输出图片
 * @return [type] [description]
 */
function createPoster($config=array(),$filename=""){
    //如果要看报什么错，可以先注释调这个header
    if(empty($filename)) header("content-type: image/png");
    $imageDefault = array(
        'left'=>0,
        'top'=>0,
        'right'=>0,
        'bottom'=>0,
        'width'=>100,
        'height'=>100,
        'opacity'=>100
    );
    $textDefault = array(
        'text'=>'',
        'left'=>0,
        'top'=>0,
        'fontSize'=>32,       //字号
        'fontColor'=>'255,255,255', //字体颜色
        'angle'=>0,
    );
    $background = $config['background'];//海报最底层得背景
    //背景方法
    $backgroundInfo = getimagesize($background);
    $backgroundFun = 'imagecreatefrom'.image_type_to_extension($backgroundInfo[2], false);
    $background = $backgroundFun($background);
    $backgroundWidth = imagesx($background);  //背景宽度
    $backgroundHeight = imagesy($background);  //背景高度

//    $backgroundWidth = 750;  //背景宽度
//    $backgroundHeight = 1650;  //背景高度

    $imageRes = imageCreatetruecolor($backgroundWidth,$backgroundHeight);
    $color = imagecolorallocate($imageRes, 0, 0, 0);
    imagefill($imageRes, 0, 0, $color);
    // imageColorTransparent($imageRes, $color);  //颜色透明
    imagecopyresampled($imageRes,$background,0,0,0,0,imagesx($background),imagesy($background),imagesx($background),imagesy($background));
    //处理了图片
    if(!empty($config['image'])){
        foreach ($config['image'] as $key => $val) {
            $val = array_merge($imageDefault,$val);
            $info = getimagesize($val['url']);
            $function = 'imagecreatefrom'.image_type_to_extension($info[2], false);
            if($val['stream']){   //如果传的是字符串图像流
                $info = getimagesizefromstring($val['url']);
                $function = 'imagecreatefromstring';
            }
            $res = $function($val['url']);
            $resWidth = $info[0];
            $resHeight = $info[1];
            //建立画板 ，缩放图片至指定尺寸
            $canvas=imagecreatetruecolor($val['width'], $val['height']);
            imagefill($canvas, 0, 0, $color);
            //关键函数，参数（目标资源，源，目标资源的开始坐标x,y, 源资源的开始坐标x,y,目标资源的宽高w,h,源资源的宽高w,h）
            imagecopyresampled($canvas, $res, 0, 0, 0, 0, $val['width'], $val['height'],$resWidth,$resHeight);
            $val['left'] = $val['left']<0?$backgroundWidth- abs($val['left']) - $val['width']:$val['left'];
            $val['top'] = $val['top']<0?$backgroundHeight- abs($val['top']) - $val['height']:$val['top'];
            //放置图像
            imagecopymerge($imageRes,$canvas, $val['left'],$val['top'],$val['right'],$val['bottom'],$val['width'],$val['height'],$val['opacity']);//左，上，右，下，宽度，高度，透明度
        }
    }
    //处理文字
    if(!empty($config['text'])){
        foreach ($config['text'] as $key => $val) {
            $val = array_merge($textDefault,$val);
            list($R,$G,$B) = explode(',', $val['fontColor']);
            $fontColor = imagecolorallocate($imageRes, $R, $G, $B);
//                $val['left'] = $val['left']<0?$backgroundWidth- abs($val['left']):$val['left'];
//                $val['top'] = $val['top']<0?$backgroundHeight- abs($val['top']):$val['top'];

            draw_txt_to($imageRes,$val,$val['text']);
//                imagettftext($imageRes,$val['fontSize'],$val['angle'],$val['left'],$val['top'],$fontColor,$val['fontPath'],$val['text']);
        }
    }
    //生成图片
    if(!empty($filename)){
        $res = imagejpeg ($imageRes,$filename,90); //保存到本地
        imagedestroy($imageRes);
        if(!$res) return false;
        return $filename;
    }else{
        imagejpeg ($imageRes);     //在浏览器上显示
        imagedestroy($imageRes);
    }

}


//自动换行
function draw_txt_to($card,$pos,$string)
{
    $pos['color'] = explode(',', $pos['fontColor']);
    $font_color = imagecolorallocate($card, $pos['color'][0], $pos['color'][1], $pos['color'][2]);
    $font_file = $pos['fontPath'];
    $_string = '';
    $__string = '';

    for ($i = 0; $i < mb_strlen($string); $i++) {
        $box = imagettfbbox($pos['fontSize'], 0, $font_file, $_string);
//        dump($box);
        $_string_length = $box[2] - $box[0];
        $box = imagettfbbox($pos['fontSize'], 0, $font_file, mb_substr($string, $i, 1));
        if ($_string_length + $box[2] - $box[0] < 360) {
            $_string .= mb_substr($string, $i, 1);
        } else {
            $__string .= $_string . "\n";
            $_string = mb_substr($string, $i, 1);
        }
    }
    $__string .= $_string;
//    echo $__string;
    $box = imagettfbbox($pos['fontSize'], 0, $font_file, mb_substr($__string, 0, 1));
    return imagettftext(
        $card,
        $pos['fontSize'],
        0,
        $pos['left'],
        $pos['top'] + ($box[3] - $box[7]),
        $font_color,
        $font_file,
        $__string);
}

/**
 * 检查上级信息是否包含指定用户
 */
 function check_leader($user_id, $leader,$model = ''){
    if (empty($model)) {
        $model = Db::name('member');
    }
    // 如果当前id 等于更改id   即这条链是包含自己下级
    if ($leader == $user_id) {
        return true;
    }

    $leader_info = $model->field('id,parentId')->where(['id' => $leader])->find();

    if ($leader_info['parentId']) {
        return check_leader($user_id,$leader_info['parentId'], $model);
    } else {
        return false;
    }
}

 /**
 * [arraytoxml 将数组转换成xml格式（简单方法）:]
 * @param [type] $data [数组]
 * @return [type]  [array 转 xml]
 */
 function arraytoxml($data){
  $str='<xml>';
  foreach($data as $k=>$v) {
   $str.='<'.$k.'>'.$v.'</'.$k.'>';
  }
  $str.='</xml>';
  return $str;
 }

/**
 * 检查上级信息是否包含指定用户
 * @return [type] [description]
 */
 function checkLeader($user_id, $leader,$model = ''){
    if (empty($model)) {
        $model = Db::name('users');
    }
    // 如果当前id 等于更改id   即这条链是包含自己下级
    if ($leader == $user_id) {
        return true;
    }

    $leader_info = $model->field('user_id,first_leader')->where(['user_id' => $leader])->find();

    if ($leader_info['first_leader']) {
        return checkLeader($user_id,$leader_info['first_leader'], $model);
    } else {
        return false;
    }
}