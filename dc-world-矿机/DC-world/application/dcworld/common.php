<?php


use think\Db;
use think\Cache;
use app\common\logic\JssdkLogic;

function getwxconfig(){
    $wx_config = Cache::get('weixin_config');
    if(!$wx_config){
        $wx_config = M('wx_user')->find(); //获取微信配置
        Cache::set('weixin_config',$wx_config,0);
    }
    $jssdk = new JssdkLogic($wx_config['appid'], $wx_config['appsecret']);
    $signPackage = $jssdk->GetSignPackage();
    return $signPackage;
}


/**
 *  积分log
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
        'createTime' => now_datetime(),
        'updateTime' => now_datetime(),
    );
    if($num > 0){
        M('member')->where(array("id"=>$userId))->setInc('integralSum',$num);//累积积分统计
        $member=M("member")->where(array("id"=>$userId))->find();//升级身份等级
        $level_info = M('vpay_level')->order('level_id')->select();
        if($level_info){
            foreach($level_info as $k=>$v){
                if($member['integralSum'] >= $v['level_integral']){
                    $level = $level_info[$k]['level_id'];
                }
            }
            $updata = array();//更新累计修复额度
            //累计额度达到新等级，更新会员折扣
            if(isset($level) && $level>$member['level']){
                $updata['level'] = $level;
            }

            M('member')->where(array("id"=>$userId))->save($updata);
        }
    }
    return M('integrallog')->add($data);
}

/**
 * 余额log
 * @param $reflectId 各表id
 * @param $userId   用户id
 * @param $num  数量
 * @param $type 类型
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
        'createTime' => now_datetime(),
        'updateTime' => now_datetime(),
    );
    return M('balancelog')->add($data);
}
/**
 * 格式化的当前日期
 *
 * @return false|string
 */
function now_datetime()
{
    return date("Y-m-d H:i:s");
}


function getTran($userId,$touserId){
    $count=M("member")->where(array("id"=>$touserId))->find();
    if($count){
        if($count['id'] == $userId){
            return 1;
        }else{
            return getTran($userId,$count['parentId']);
        }
    }else{
        return 0;
    }
}

// 余额动态释放
function balanceRelease($user,$banlance,$reflectId,$type){
    //TODO vip身份释放返还
    vipExchangeRelease($user,$banlance,$reflectId,$type);
    //end
    $switch = M('config')->where(array('inc_type'=>'vpay_spstem_role','name' => 'switch'))->find();
    if($switch['value']){
        $returndata=1;
        $data = releaseRule($user,$banlance,$reflectId,$type);
        $returndata=$returndata && $data;
    }else{
        $system=tpCache("vpay_spstem");
        $onebalance=$banlance*$system['oneRate'];
        $twobalance=$banlance*$system['twoRate'];
        $otherbalance=$banlance*$system['otherRate'];
        $returndata=1;
        if(!empty($user) && !empty($user['parentId'])){
            for($i=1;$i<=$system['members'];$i++){
                //上级推荐人
                //$user=M("member")->where(array("id"=>$user['parentId']))->find();
                $user = M('member')
                    ->alias("t")
                    ->field("t.*,b.*")
                    ->join("vpay_level b","t.level=b.level_id","LEFT")
                    ->where(array("t.id"=>$user['parentId']))
                    ->find();
                //判断是否能享受加速待返及币的流通
                if(empty($user)){
                    break;
                }
                if(empty($user['p3']) || empty($user['p4'])){
                    continue;
                }
                //推荐人推荐的个数
                //往上找满足9代，判断推荐人数
                if($i>$system['subMember']){
                    $count=M("member")->where(array("parentId"=>$user['id']))->count();
                    if($count<10){//推荐人数小于10人不拿
                        continue;
                    }else{
                        if($otherbalance>=0.01){//小于0.01,数据库没有改变则返回错误
                            //$add_log = balancelog($reflectId, $user['id'], +$otherbalance, $type=14, $before=$user['balance'], $after=$user['balance']+$otherbalance);
                            $save_mem=M("member")->where(array("id"=>$user['id']))->setInc("other_balance",$otherbalance);
                            //写入记录日志
                            releaselog($user,$reflectId,$otherbalance,$type);
                            //积分变更
                            /*if($user['integral']>0 && $user['integral']<$otherbalance){//有积分但是不够扣的，改为0；
                                $reduceItg=$user['integral'];//减少积分
                                $remainitg=0;//剩余积分
                                $integrallog=integrallog($reflectId, $user['id'],-$reduceItg,6,$user['integral'],$remainitg);//返佣积分log
                                $memItg=M("member")->where(array("id"=>$user['id']))->save(array("integral"=>$remainitg));//更改用户积分
                            }else if($user['integral']>0 && $user['integral']>$otherbalance){
                                $reduceItg=$otherbalance;//减少积分
                                $remainitg=$user['integral']-$otherbalance;//剩余积分
                                $integrallog=integrallog($reflectId, $user['id'],-$reduceItg,6,$user['integral'],$remainitg);//返佣积分log
                                $memItg=M("member")->where(array("id"=>$user['id']))->save(array("integral"=>$remainitg));//更改用户积分
                            }else{//积分为0，不添加log
                                $integrallog=1;
                                $memItg=1;
                            }*/
                            //$data=$add_log && $save_mem && $integrallog && $memItg;
                            //$data=$save_mem && $integrallog && $memItg;
                            $data=$save_mem;
                            $returndata=$returndata && $data;
                        }
                    }
                }else if($i > $system['subMemberMin']){//不满足10层，推荐人数小于3个不拿
                    $count=M("member")->where(array("parentId"=>$user['id']))->count();
                    if($count<3){//推荐人数小于3人不拿
                        continue;
                    }else{
                        if($otherbalance>=0.01){//小于0.01,数据库没有改变则返回错误
                            $save_mem=M("member")->where(array("id"=>$user['id']))->setInc("other_balance",$otherbalance);
                            //写入记录日志
                            releaselog($user,$reflectId,$otherbalance,$type);
                            $data=$save_mem;
                            $returndata=$returndata && $data;
                        }
                    }
                }else if($i <= $system['subMemberMin']){//不满足5层，推荐人数小于2个不拿
                    $data=updateMemberRebate($i,$user,$onebalance,$twobalance,$otherbalance,$reflectId,$type);
                    $returndata=$returndata && $data;
                }
            }
        }
        $returndata=$returndata;
    }
    return $returndata;
}

/**
 * 提取出来的返佣的方法
 * @param $i
 * @param $user
 * @param $onebalance
 * @param $twobalance
 * @param $otherbalance
 * @return bool|int
 */
function updateMemberRebate($i,$user,$onebalance,$twobalance,$otherbalance,$reflectId,$type){
    if($i==1 && $onebalance>=0.01){//第一代返佣
        //分开插入member,目的就是在积分为负值的时候不要更改原有数据
        //$add_log = balancelog($reflectId, $user['id'], +$onebalance, $type=14, $before=$user['balance'], $after=$user['balance']+$onebalance);
        $save_mem=M("member")->where(array("id"=>$user['id']))->setInc("other_balance",$onebalance);
        //写入记录日志
        releaselog($user,$reflectId,$onebalance,$type);
        /* if($user['integral']>0 && $user['integral']<$onebalance){//有积分但是不够扣的，改为0；
             $reduceItg=$user['integral'];//减少积分
             $remainitg=0;//剩余积分
             $integrallog=integrallog($reflectId, $user['id'],-$reduceItg,6,$user['integral'],$remainitg);//返佣积分log
             $memItg=M("member")->where(array("id"=>$user['id']))->save(array("integral"=>$remainitg));//更改用户积分
         }else if($user['integral']>0 && $user['integral']>$onebalance){
             $reduceItg=$onebalance;//减少积分
             $remainitg=$user['integral']-$onebalance;//剩余积分
             $integrallog=integrallog($reflectId, $user['id'],-$reduceItg,6,$user['integral'],$remainitg);//返佣积分log
             $memItg=M("member")->where(array("id"=>$user['id']))->save(array("integral"=>$remainitg));//更改用户积分
         }else{//积分为0，不添加log
             $integrallog=1;
             $memItg=1;
         }*/
    }else if($i==2 && $twobalance>=0.01){//第二代返佣
        //$add_log = balancelog($reflectId, $user['id'], +$twobalance, $type=14, $before=$user['balance'], $after=$user['balance']+$twobalance);
        $save_mem=M("member")->where(array("id"=>$user['id']))->setInc("other_balance",$twobalance);
        //写入记录日志
        releaselog($user,$reflectId,$twobalance,$type);
        //变更积分
        /* if($user['integral']>0 && $user['integral']<$twobalance){//有积分但是不够扣的，改为0；
             $reduceItg=$user['integral'];//减少积分
             $remainitg=0;//剩余积分
             $integrallog=integrallog($reflectId, $user['id'],-$reduceItg,6,$user['integral'],$remainitg);//返佣积分log
             $memItg=M("member")->where(array("id"=>$user['id']))->save(array("integral"=>$remainitg));//更改用户积分
         }else if($user['integral']>0 && $user['integral']>$twobalance){
             $reduceItg=$twobalance;//减少积分
             $remainitg=$user['integral']-$twobalance;//剩余积分
             $integrallog=integrallog($reflectId, $user['id'],-$reduceItg,6,$user['integral'],$remainitg);//返佣积分log
             $memItg=M("member")->where(array("id"=>$user['id']))->save(array("integral"=>$remainitg));//更改用户积分
         }else{//积分为0，不添加log
             $integrallog=1;
             $memItg=1;
         }*/
    }else if($otherbalance>=0.01){
        //$add_log = balancelog($reflectId, $user['id'], +$otherbalance, $type=14, $before=$user['balance'], $after=$user['balance']+$otherbalance);
        $save_mem=M("member")->where(array("id"=>$user['id']))->setInc("other_balance",$otherbalance);
        //写入记录日志
        releaselog($user,$reflectId,$otherbalance,$type);
        //积分变更
        /*if($user['integral']>0 && $user['integral']<$otherbalance){//有积分但是不够扣的，改为0；
            $reduceItg=$user['integral'];//减少积分
            $remainitg=0;//剩余积分
            $integrallog=integrallog($reflectId, $user['id'],-$reduceItg,6,$user['integral'],$remainitg);//返佣积分log
            $memItg=M("member")->where(array("id"=>$user['id']))->save(array("integral"=>$remainitg));//更改用户积分
        }else if($user['integral']>0 && $user['integral']>$otherbalance){
            $reduceItg=$otherbalance;//减少积分
            $remainitg=$user['integral']-$otherbalance;//剩余积分
            $integrallog=integrallog($reflectId, $user['id'],-$reduceItg,6,$user['integral'],$remainitg);//返佣积分log
            $memItg=M("member")->where(array("id"=>$user['id']))->save(array("integral"=>$remainitg));//更改用户积分
        }else{//积分为0，不添加log
            $integrallog=1;
            $memItg=1;
        }*/
    }else{
        //$add_log=1;
        $save_mem=1;
        //$integrallog=1;
        //$memItg=1;
    }
    //$data=$add_log && $save_mem && $integrallog && $memItg;
    //$data=$save_mem && $integrallog && $memItg;
    return $save_mem;
}

/**
 * 积分兑换返佣
 * @param $user
 * @param $integral 转换以后的积分
 * @param $reflectId 关联id
 * @return bool|int
 */
function exchangeRelease($user,$balance,$reflectId,$type){
    $system=tpCache("vpay_spstem");
    //$bonus=tpCache("bonus");
    $onebalance=$balance*$system['oneExchangeRate'];
    $twobalance=$balance*$system['twoExchangeRate'];
    $otherbalance=$balance*$system['otherExchangeRate'];
    //$returnbalance=$balance*$system['signRate']*$system['exchangeRate'];//转换后的积分乘以积分的每日反余额比例千分之二再乘以积分释放比例
    $returndata=1;

    if(!empty($user) && !empty($user['parentId'])) {
        for ($i = 1; $i <= $system['numbers']; $i++) {
            //上级推荐人
            $user = M('member')
                ->alias("t")
                ->field("t.*,b.*")
                ->join("vpay_level b","t.level=b.level_id","LEFT")
                ->where(array("t.id"=>$user['parentId']))
                ->find();
            //判断是否能享受加速待返及币的流通
            if(empty($user)){
                break;
            }
            if(empty($user['p3']) || empty($user['p4'])){
                continue;
            }
            $data=updateMemberRebate($i,$user,$onebalance,$twobalance,$otherbalance,$reflectId,$type);
            $returndata=$returndata && $data;
        }
    }
//    if($returnbalance>=0.01){
//        if(!empty($user) && !empty($user['parentId'])){
//            for($i=1;$i<=$system['exchangeMember'];$i++){
//                $user=M("member")->where(array("id"=>$user['parentId']))->find();
//                if(empty($user) || empty($user['parentId'])){
//                    break;
//                }
//                $save_mem=M("member")->where(array("id"=>$user['id']))->save(array("other_balance"=>($user['other_balance']+$returnbalance)));
//                $data=$save_mem;
//                $returndata=$returndata && $data;
//            }
//        }
//    }
    return $returndata;
}
/**
 * vip流通余额返佣
 * @param $user
 * @param $integral 转换以后的积分
 * @param $reflectId 关联id
 * @return bool|int
 */
function vipExchangeRelease($user,$balance,$reflectId,$type){
    $system=tpCache("vpay_spstem");
    $onebalance=$balance*$system['extraRate1'];
    $twobalance=$balance*$system['extraRate2'];
    $returndata=1;
    if(!empty($user) && !empty($user['parentId'])){
        $userInfo = getVipResult($user);
        if($userInfo){
            $save_mem=M("member")->where(array("id"=>$userInfo['id']))->setInc("vip_balance",$onebalance);
            //写入记录日志
            releaselog($userInfo,$reflectId,$onebalance,$type,1);
            $data=$save_mem;
            $returndata=$returndata && $data;
        }
        $userInfo2 = getVipResult($userInfo);
        if($userInfo2){
            $save_mem=M("member")->where(array("id"=>$userInfo2['id']))->setInc("vip_balance",$twobalance);
            //写入记录日志
            releaselog($userInfo2,$reflectId,$twobalance,$type,1);
            $data=$save_mem;
            $returndata=$returndata && $data;
        }
    }
    $returndata=$returndata;
    return $returndata;
}

function getVipResult($user){
    if(!empty($user) && !empty($user['parentId'])) {
        $userInfo = M('member')
            ->alias("t")
            ->field("t.*,b.*")
            ->join("vpay_level b","t.level=b.level_id","LEFT")
            ->where(array("t.id"=>$user['parentId']))
            ->find();
        if($userInfo['level'] == 3){
            return $userInfo;
        }else{
            return getVipResult($userInfo);
        }
    }else{
        return false;
    }
}


/*
 * 释放规则角色分佣
 * */
function releaseRule($user,$balance,$reflectId,$type){
    $system=tpCache("vpay_spstem_role");
    $system['pushs'] = unserialize($system['pushs']);
    $system['integral'] = unserialize($system['integral']);
    $save_mem = 1;
    $returndata=1;
    $status = 0;
    if(!empty($user) && !empty($user['parentId'])){
        for($i=1;$i<=$system['numbers'];$i++){
            //上级推荐人
            //$user=M("member")->where(array("id"=>$user['parentId']))->find();
            $user = M('member')
                ->alias("t")
                ->field("t.*,b.*")
                ->join("vpay_level b","t.level=b.level_id",'LEFT')
                ->where(array("t.id"=>$user['parentId']))
                ->find();
            //判断是否能享受加速待返及币的流通
            if(empty($user)){
                break;
            }
            if(empty($user['p3']) || empty($user['p4'])){
                continue;
            }
            foreach($system['pushs'] as $key => $val){
                if($i <= $val[1]){
                    $count=M("member")->where(array("parentId"=>$user['id']))->count();
                    if($count < $val[0]){//推荐人数小于10人不拿
                        break;
                    }else{
                        $num =M("balancelog")->where(array("userId"=>$user['id'],'type'=>8))->sum('num');
                        if(!$num){
                            break;
                        }
                        $num = abs($num);
                        foreach($system['integral'] as $key => $item){
                            if($num >= $item[0]){
                                $t1 = $item[1];
                                $t2 = $item[2];
                                $t3 = $item[3];
                                $status = 1;//判断当前用户是否满足余额转积分条件开关控制
                            }
                        }
                        if(!$status){
                            break;
                        }
                        $status = 0;
                        $t1 = $t1 ? $t1 : 0;
                        $t2 = $t2 ? $t2 : 0;
                        $t3 = $t3 ? $t3 : 0;
                        $onebalance   = $balance * $t1;
                        $twobalance   = $balance * $t2;
                        $otherbalance = $balance * $t3;
                        if($i==1 && $onebalance>=0.01){//第一代返佣
                            $save_mem=M("member")->where(array("id"=>$user['id']))->setInc("other_balance",$onebalance);
                            //写入记录日志
                            releaselog($user,$reflectId,$onebalance,$type);
                        }else if($i==2 && $twobalance>=0.01){//第二代返佣

                            $save_mem=M("member")->where(array("id"=>$user['id']))->setInc("other_balance",$twobalance);
                            //写入记录日志
                            releaselog($user,$reflectId,$twobalance,$type);
                        }else if($otherbalance>=0.01){

                            $save_mem=M("member")->where(array("id"=>$user['id']))->setInc("other_balance",$otherbalance);
                            //写入记录日志
                            releaselog($user,$reflectId,$otherbalance,$type);
                        }
                        $returndata=$save_mem;
                        break;
                    }
                }
            }
        }
    }
    $returndata=$returndata;
    return $returndata;
}

/**
 * 验证银行卡号是否有效(前提为16位或19位数字组合)
 *
 * @param $cardNum                      银行卡号
 * @return bool                         有效返回true,否则返回false
 */
function isBankCard($cardNum)
{
    // 第一步,反转银行卡号
    $cardNum = strrev($cardNum);

    // 第二步,计算各位数字之和
    $sum = 0;
    for ($i = 0; $i < strlen($cardNum); ++$i) {
        $item = substr($cardNum, $i, 1);
        //
        if ($i % 2 == 0) {
            $sum += $item;
        } else {
            $item *= 2;
            $item = $item > 9 ? $item - 9 : $item;
            $sum += $item;
        }
    }

    // 第三步,判断数字之和余数是否为0
    if ($sum % 10 == 0) {
        return true;
    } else {
        return false;
    }
}

/**
 * 验证身份证号是否正确
 *
 * @param $number
 * @return bool
 */
function isIdentityCard($number)
{
    //验证长度
    if (strlen($number) != 18) {
        return false;
    }

    //验证是否符合规则
    if (!preg_match("/(\d{18})|(\d{17}(\d|X|x))/i", $number)) {
        return false;
    }

    //每位数对应的乘数因子
    $factors = array(7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2);

    //计算身份证号前17位的和
    $sum = 0;
    for ($index = 0; $index < 17; ++$index) {
        $num = substr($number, $index, 1);

        $sum += $num * $factors[$index];
    }

    //将和对11取余
    $mod = $sum % 11;

    //根据获得的余数，获取验证码
    $verifyCode = "";
    switch ($mod) {
        case 0:
            $verifyCode = "1";
            break;
        case 1:
            $verifyCode = "0";
            break;
        case 2:
            $verifyCode = "X";
            break;
        default:
            $verifyCode = 12 - $mod;
            break;
    }

    //核对校验码和身份证最后一位
    if ($verifyCode == substr($number, -1, 1)) {
        return true;
    }

    return false;
}

// 验证url地址
function isUrl($str)
{
    return (preg_match("/^http:\/\/[A-Za-z0-9]+\.[A-Za-z0-9]+[\/=\?%\-&_~`@[\]\':+!]*([^<>\"\"])*$/", $str)) ? true : false;
}

// 验证电话号码
function isPhone($str)
{
    return (preg_match("/^1[345789]\d{9}$/", $str)) ? true : false;
}

// 验证邮编
function isZip($str)
{
    return (preg_match("/^[1-9]\d{5}$/", $str)) ? true : false;
}

// 是否是数字
function isNumeric($val)

{
    return (preg_match('/^[-+]?[0-9]*.?[0-9]+$/', $val)) ? true : false;
}

// 是否超过最大长度
function isMaxLength($val, $max)
{
    return (strlen($val) <= (int)$max);
}

// 超过最大数
function isMaxValue($number, $max)
{
    return ($number > $max);
}

// 验证用户名,$value传递值;$minLen最小长度;$maxLen最长长度;只允许下划线+汉字+英文+数字（不支持其它特殊字符）
function isUsername($value, $minLen = 2, $maxLen = 30)
{
    if (!$value) return false;
    return preg_match('/^[_wdx{4e00}-x{9fa5}]{' . $minLen . ',' . $maxLen . '}$/iu', $value);
}

/**
 * 是否为空值
 */
function isEmpty($str)
{
    $str = trim($str);
    return !empty($str) ? true : false;
}

/**
 * 数字验证
 * param:$flag : int是否是整数，float是否是浮点型
 */
function isNum($str)
{
    return (preg_match("/^[1-9][0-9]*$/", $str)) ? true : false;
}

// 是否是数字或英文组合
function isNumLetter($val)
{
    return (preg_match('/[A-Za-z0-9]+$/', $val)) ? true : false;
}

// 是否同时包含数字和英文，必须同时包含数字和英文
function isNumAndLetter($val)
{
    return (preg_match("/[A-Za-z]/",$val)&& preg_match("/\d/",$val)) ? true : false;
}

// 是否是6位数字密码
function is6Num($val)
{
    return (preg_match("/[0-9]{6}$/",$val)) ? true : false;
}

// 微信号验证
function isWebchat($val)
{
    return (preg_match("/^[-_a-zA-Z0-9]{5,19}$/",$val)) ? true : false;
}

/**
 * 请求接口返回内容
 * @param  string $url [请求的URL地址]
 * @param  string $params [请求的参数]
 * @param  int $ipost [是否采用POST形式]
 * @return  string
 */
function juhecurl($url, $params = false, $ispost = 0)
{
    $httpInfo = array();
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.22 (KHTML, like Gecko) Chrome/25.0.1364.172 Safari/537.22');
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if ($ispost) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_URL, $url);
    } else {
        if ($params) {
            curl_setopt($ch, CURLOPT_URL, $url . '?' . $params);
        } else {
            curl_setopt($ch, CURLOPT_URL, $url);
        }
    }
    $response = curl_exec($ch);
    if ($response === FALSE) {
        return false;
    }
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $httpInfo = array_merge($httpInfo, curl_getinfo($ch));
    curl_close($ch);
    return $response;
}

function releaselog($user,$reflectId,$num,$type,$status = 0){
    $result = array(
        'reflectId' => $reflectId,
        'userId' => $user['id'],
        'num' => $num,
        'type' => $type,
        'status' => $status,
        'createTime'=> now_datetime()
    );
    $insert_log = M('releaselog')->add($result);
    return $insert_log;
}
/**
 * 用户账号管理 增改
 * @param  $id [账号ID 只在修改时有]
 * @param  $uid [用户ID]
 * @param  $type [1 支付宝 2 微信 3 银行卡]
 * @param  $account [账号]
 * @param  $account_name [账号名称]
 * @param  $account_code [收款码路径 支付宝和微信才有]
 * @param  $bank_name [银行名称]
 * @param  $bank_branch [支行名称]
 * @return  bool
 */
function account_edit($id = 0,$uid,$type = 1,$account = '',$account_name = '',$account_code = '',$bank_name = '',$bank_branch = ''){

    if (!$uid || !$account || !$account_name ) return false;

    $data = [
        'user_id' => $uid,
        'type' =>$type,
        'account' => $account,
        'account_name' => $account_name,
        'account_code' => $account_code,
        'bank_name' => $bank_name,
        'bank_branch' => $bank_branch,
        'create_time' => time(),
    ];
    switch ($type) {
        case 1 :
            $data['type_name'] = '支付宝';
            break;
        case 2 :
            $data['type_name'] = '微信';
            break;
        case 3 :
            $data['type_name'] = '银行卡';
            break;
        default:
            $data['type_name'] = '';
            break;
    }
    if ($id) {
        $result = M('world_user_account')->where(['id'=>$id])->save($data);
    }else{
        $result = M('world_user_account')->add($data);
    }
    if ($result) {
        return true;
    }else{
        return false;
    }
}

/**
 * 生成贝壳
 */
function create_shell($user_id){
    $give_shell =M('world_giveshell')->where(array('user_id'=>$user_id))->find();
    if(empty($give_shell)||$give_shell['shell_surplus']<=0){
        return array('id'=>0,'shell_info'=>array());
    }

    $where = array('user_id'=>$user_id);
    $today = strtotime("Today",time());
    $where['create_time'] =array('between',array($today,time()));
    $get_shell =M('world_getshell')->where($where)->find();
    if($get_shell){
        $shell_list = unserialize($get_shell['shell_info']);
        return array('id'=>$get_shell['id'],'shell_info'=>$shell_list);
    }

    $shell_set = worldCa('shell_set');//沙滩规则

    $min=$shell_set['ten_shell_num_min'];//贝壳最值小
    $max=$shell_set['ten_shell_num_max'];//贝壳最大值

    $shell_info=array();//贝壳数据列表

    $shell_surplus=$give_shell['shell_surplus'];//剩余可捡贝壳数
    $shell_total=0;//沙滩贝壳总值

    for($i=0;$i<$shell_set['ten_shell_num'];$i++){
        $shell_size= rand($min*100000,$max*100000);
        $shell_size=$shell_size/100000;
        $shell_size=sprintf("%.3f",$shell_size);//单个贝壳的数值

        if(($shell_surplus-$shell_total)<=0){
            continue;
        }
        if(($shell_surplus-$shell_total)<$shell_size){
            $shell_size=$shell_surplus-$shell_total;
        }
        $shell_total+=$shell_size;
        $shell_info[$i]['status']=0;
        $shell_info[$i]['num']=$shell_size;
    };

//    dump($shell_info);

    $data = array(
        'user_id'=>$user_id,
        'create_time'=>time(),
        'giveshell_id'=>$give_shell['id'],
        'shell_info'=>serialize($shell_info),
    );
//    dump($data);
    $res =M('world_getshell')->add($data);
    return array('id'=>$res,'shell_info'=>$shell_info);
}

/**
 * 生成贝壳
 */
function create_oil($user_id){
    $where = array('user_id'=>$user_id);
    $today = strtotime("Today",time());
    $where['create_time'] =array('between',array($today,time()));
    $world_release =M('world_release')->where($where)->find();
    if(empty($world_release)){
        return array();
    }

    if($world_release['oil_info']){
        $oil_list = unserialize($world_release['oil_info']);
        return array('id'=>$world_release['id'],'oil_info'=>$oil_list);
    }

    $dc_num = $world_release['dc_num'];
    $oil_num = $world_release['oil_num'];
    $oil_num_float = $world_release['oil_num_float'];

    $oil_avg = $world_release['dc_num']/$oil_num;

    $min = $oil_avg-($oil_avg*$oil_num_float)/100;

    $max = $oil_avg+($oil_avg*$oil_num_float)/100;

    $bonus_total = $dc_num*10000;
    $bonus_count=$oil_num;
    $bonus_max=$max*10000;
    $bonus_min=$min*10000;

    $result_bonus = getBonus($bonus_total, $bonus_count, $bonus_max, $bonus_min);

    $oil_list=[];
    $num=0;
    foreach($result_bonus as $key=>$val){

        $oil=$val/10000;
        $oil=sprintf('%.3f',floor($oil*1000)/1000);
        $oil_list[$key]['status']=0;
        $oil_list[$key]['num']=$oil;
        $num+=$oil;
    }

    $oil_info= serialize($oil_list);
    $res =M('world_release')->where(array('id'=>$world_release['id']))->update(array('oil_info'=>$oil_info,'surplus_dc'=>$num));
    return array('id'=>$world_release['id'],'oil_info'=>$oil_list);

}

/**
 * 订单加速
 * @param $order_id 订单id
 * @return array
 */
function calculation_accelerate($order_id = 0){
    $account = session('account'); // 用户信息
    $where = ['order_id'=>$order_id,'user_id'=>$account['id']];
    $order = M('world_order')->where($where)->find();
    if (!$order) return false;
    Db::startTrans();
    try{
        // $parent_arr = recursion_get_superior($account['parentId'],10); 
        // 转增的订单取的用户上级是被转赠的上级
        if ($order['type'] == 1) {
            $order_user = $order['user_id'];
         }elseif ($order['type'] == 2) {
            $order_user = $order['to_user_id'];
         }
        $order_u = M('member')->field('parentId')->where(['id'=>$order_user])->find();
        // 递归找上级信息
        $parent_arr = recursion_get_superior($order_u['parentId'],10);

        if ($parent_arr) {
            foreach ($parent_arr as $key => $value) {
                if ($value['release_rate']) {
                    // 序列化加速释放规则
                    $release_rate = unserialize($value['release_rate']);
                    // 释放比例未开启 没有资格享有加速
                    if (!$release_rate['release_rate_switch']) continue;
                    // 通过代数找到直推条件
                    foreach ($release_rate['distribut_level'] as $key2 => $val2) {
                        if ($val2 == $value['series']) {                            
                            // 应满足直推数量
                            $distribut_number = $release_rate['distribut_number'][$key2];
                            // 当前用户直推数量
                            $downline_mun = M('member')->where(['parentId'=>$value['parent_id']])->count();
                            // 直推数量不满足条件 没有资格享有加速     
                            if ($downline_mun < $distribut_number) continue;
                            // 应加速数量 向下取整三位小数
                            $accelerate_mun = floor($order['total_amount'] * $release_rate['rate'][$key2] * 10) / 1000;
                            // 矿机释放                                                                       
                            $release = miner_release($value['parent_id'],$accelerate_mun,$order_id);  
                            // 释放不成功 回滚
                            if (!$release) {
                                Db::rollback(); 
                                return false;
                            }  
                        }
                    }
                }
            }
            Db::commit();
            return true;
        }else{
            // 没有上级 直接return
            Db::commit();
            return true;
        }
    }catch (Exception $e){
        Db::rollback();
    }
}
/**
 * 递归查找上级信息
 * @param $parent_id 上级id
 * @param $series 层级数 0为无限极
 * @param $now_series 当前层级
 * @param $parent_arr 要返回的上级数组
 * @return array
 */
function recursion_get_superior($parent_id = 0,$series = 0,$now_series=1,$parent_arr=array())
{
    // 设置了层级并且 当前层级>指定的层级 return数组
    if ($series && ($now_series>$series)) return $parent_arr;

    $parent_info = M('member')
        ->alias('m')
        ->field('m.*,l.release_rate')
        ->join('world_level l ', ' m.level=l.level_id', 'LEFT') 
        ->where(['m.id'=>$parent_id])
        ->find();

    if ($parent_info ) {

        $parent_arr[] = [
            'series' => $now_series, // 层级数
            'parent_id' => $parent_id, // 上级ID
            'release_rate' => $parent_info['release_rate'] // 加速设置
        ];
        $now_series++; // 当前层级数+1

        if ($parent_info['parentId']) {

            return recursion_get_superior($parent_info['parentId'],$series,$now_series,$parent_arr);
        }else{
            return $parent_arr;
        }
    }else{
        return $parent_arr;
    }
}

/**
 * 矿机释放
 * @param $user_id 要加速的用户ID
 * @param $release_mun 释放数量
 * @param $order_id 订单ID
 * @return bool
 */
function miner_release($user_id = 0,$release_mun = 0,$order_id = 0)
{
    if (!$release_mun || !$order_id) return true;
    $where = [
        'user_id' => $user_id,
        'status' => 1, // 状态必需是正常
        'release_days' => ['exp', "<= scrap_days"], // 释放的天数必需<报废天数
        'income_surplus' => ['GT', 0] // 剩余算力必需>0
    ];
    $user_miner = M('world_users_miner')->where($where)->order('sort asc,add_time asc')->select();
    if (!$user_miner) return true;
    // 要释放的矿机 默认第一个
    $release_miner = reset($user_miner);

    $unfinished_num = 0;
    // 一台矿机不够返 要释放的数量>矿机剩余数量 会返下一台矿机
    if ($release_mun > $release_miner['income_surplus']) {
        $up_data = [
            'income_surplus' => 0, // 剩余算力清0
            'update_time' => time(), // 更新时间
            'status' => 2, // 状态失效
        ];
        // 没返完的算力
        $unfinished_num = $release_mun - $release_miner['income_surplus'];
    }else{
        // 一台矿机够返
        // 剩余算力 原本剩余数量-要释放的数量
        $income_surplus = $release_miner['income_surplus'] - $release_mun;
        // <0则取0,最后一次返的时候剩余算力可能为负数
        $income_surplus = $income_surplus<0 ? 0 : $income_surplus;
        $up_data = [
            'income_surplus' => $income_surplus, // 剩余算力清0
            'update_time' => time(), // 更新时间
        ];
    }
    // 更新用户的矿机数据
    $res_one = M("world_users_miner")->where(['id'=>$release_miner['id'],'user_id'=>$user_id])->save($up_data);
    // 要释放的数量与矿机剩余数量 谁小取谁
    $release_mun = $release_mun>$release_miner['income_surplus'] ? $release_miner['income_surplus'] : $release_mun;  

    // 加速矿机的订单信息
    $miner_data = M('world_users_miner')
       ->alias('wu')
       ->field('o.order_sn,g.miner_name,g.short_name')
       ->join('world_order o ', ' wu.order_id = o.order_id', 'LEFT') 
       ->join('world_miner g ', ' wu.miner_id = g.miner_id', 'LEFT')
       ->where(['wu.id'=>$release_miner['id']])
       ->find();

    //加速释放算力
    $res=accountPowerLog($user_id, -$release_mun, '加速衰减-'.$miner_data['short_name'], $release_miner['id'], $miner_data['order_sn'],6, 1,$order_id);

    // DC币日志 更新member表DC币
    $res_two = accountDcLog($user_id, $release_mun,'激励产出-'.$miner_data['short_name'],$release_miner['id'], $miner_data['order_sn'],3,1,$order_id);


    if ($res_one && $res_two) {
        // 继续释放下一台矿机
        if ($unfinished_num > 0){
            return miner_release($user_id,$unfinished_num,$order_id);
        }else{
            return true;
        }
    }else{
        return false;
    }
}

function mb_str_split($str){
    return preg_split('/(?<!^)(?!$)/u', $str );
}



/**
 * 求一个数的平方
 * @param $n
 */
function sqr($n){
    return $n*$n;
}

/**
 * 生产min和max之间的随机数，但是概率不是平均的，从min到max方向概率逐渐加大。
 * 先平方，然后产生一个平方值范围内的随机数，再开方，这样就产生了一种“膨胀”再“收缩”的效果。
 */
function xRandom($bonus_min,$bonus_max){
    $sqr = intval(sqr($bonus_max-$bonus_min));
    $rand_num = rand(0, ($sqr-1));
    return intval(sqrt($rand_num));
}


/**
 *
 * @param $bonus_total 红包总额
 * @param $bonus_count 红包个数
 * @param $bonus_max 每个小红包的最大额
 * @param $bonus_min 每个小红包的最小额
 * @return 存放生成的每个小红包的值的一维数组
 */
function getBonus($bonus_total, $bonus_count, $bonus_max, $bonus_min) {
    $result = array();

    $average = $bonus_total / $bonus_count;

    $a = $average - $bonus_min;
    $b = $bonus_max - $bonus_min;

    //
    //这样的随机数的概率实际改变了，产生大数的可能性要比产生小数的概率要小。
    //这样就实现了大部分红包的值在平均数附近。大红包和小红包比较少。
    $range1 = sqr($average - $bonus_min);
    $range2 = sqr($bonus_max - $average);

    for ($i = 0; $i < $bonus_count; $i++) {
        //因为小红包的数量通常是要比大红包的数量要多的，因为这里的概率要调换过来。
        //当随机数>平均值，则产生小红包
        //当随机数<平均值，则产生大红包
        if (rand($bonus_min, $bonus_max) > $average) {
            // 在平均线上减钱
            $temp = $bonus_min + xRandom($bonus_min, $average);
            $result[$i] = $temp;
            $bonus_total -= $temp;
        } else {
            // 在平均线上加钱
            $temp = $bonus_max - xRandom($average, $bonus_max);
            $result[$i] = $temp;
            $bonus_total -= $temp;
        }
    }
    // 如果还有余钱，则尝试加到小红包里，如果加不进去，则尝试下一个。
    while ($bonus_total > 0) {
        for ($i = 0; $i < $bonus_count; $i++) {
            if ($bonus_total > 0 && $result[$i] < $bonus_max) {
                $result[$i]++;
                $bonus_total--;
            }
        }
    }
    // 如果钱是负数了，还得从已生成的小红包中抽取回来
    while ($bonus_total < 0) {
        for ($i = 0; $i < $bonus_count; $i++) {
            if ($bonus_total < 0 && $result[$i] > $bonus_min) {
                $result[$i]--;
                $bonus_total++;
            }
        }
    }
    return $result;
}







