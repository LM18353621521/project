<?php

namespace app\vpay\controller;

use Think\Exception;
use Think\Db;
class Transfer extends Sign  //BaseController
{
    /**
     * 跳转转出页面
     */
    public function turn_out()
    {
        if (IS_POST) {
            $account=I("account");
            if(empty($account)){
                $this->ajaxError("对方账户不能为空！");
            }
            $sessionmem=parent::getAccount();
            if(empty($sessionmem)){
                $this->ajaxError("未登录！");
            }
            if($account==$sessionmem['account'] || $account==$sessionmem['id']){
                $this->ajaxError("收款人不能是自己！");
            }
            $condition['id'] = $account;
            $condition['account'] = $account;

            $member=M("member")->whereor($condition)->find();
            if(empty($member)){
                $this->ajaxError("收款人不存在！");
            }
            $this->ajaxSuccess($member);
        }else{
            return $this->fetch();
        }
    }

    /**
     * 扫码
     */
    public function qr_text()
    {
        $url = trim(I('qr_text'));
        $memberId = parent::getAccountId();
//        echo $url;die;
        if (empty($url)) $this->ajaxError("扫码失败！");

        if (0 !== strpos($url, "http://btpaywallet.com/index.php/Vpay/transfer/turnin_infor?id=")) $this->ajaxError("扫码失败，请重新扫码！");

        $res = preg_match('/\.*\d*$/', $url, $match);
        if ($res) {
            if ($res && isset($match) && $match[0] == $memberId) $this->ajaxError("收款人不能是自己！");

            $is_empty = M('member')->where(array("id"=>$match[0]))->find();
            if (empty($is_empty)) $this->ajaxError("收款人不存在！");

            $this->ajaxSuccess(array("url"=>$url));
        } else {
            $this->ajaxError("扫码失败，参数缺失！");
        }

    }


    /**
     * 跳转确认转出页面
     */
    public function turnout_infor()
    {
        if (IS_POST) {
//            if (!empty(parent::get("LAST_ADD_TURNOUT")) && time() - parent::get("LAST_ADD_TURNOUT") <= 10) $this->ajaxError();
//            parent::set("LAST_ADD_TURNOUT", time());

            $id=I("id");
            $money=I("money");
            $phone=I("phone");
            if(empty($id)){
                $this->ajaxError("参数错误！");
            }
            if($money<=0){
                $this->ajaxError("金额不能小于0！");
            }
            if(empty($money) || empty($phone)){
                $this->ajaxError("金额和手机号不能为空！");
            }
            $tomember=M("member")->where(array("id"=>$id,"account"=>array("like","%".$phone)))->find();
            if(empty($tomember)){
                $this->ajaxError("手机号填写有误！");
            }
            //当前人
            $member=parent::getAccountId();
            if(empty($member)){
                $this->ajaxError("未登录！");
            }
            $member=M("member")->where(array("id"=>$member,"isDisable"=>2,"isDelete"=>2))->find();
            if(empty($member)){
                $this->ajaxError("用户不存在！");
            }
            if ($tomember['id']==$member['id']){
                $this->ajaxError("对方不能是自己！");
            }
            /*$sql="select * from transfer where userId=".$member['id']." and TIMESTAMPDIFF(SECOND,createTime,now())<10";//转出10秒限制
            $transfer=M("transfer")->query($sql);
            if(!empty($transfer)){
                $this->ajaxError("");
            }*/
            if($member['balance']<$money){
                $this->ajaxError("余额不足");
            }
            //$is_res = getTran($member['id'],$tomember['parentid']);
            $balance = $money;
//            if(!$is_res){
//                $money = $money * 0.85;
//            }
            Db::startTrans();
            try{
                $system=tpCache("vpay_spstem");
                $data=array(
                    "userId"=>$member['id'],
                    "account"=>$member['account'],
                    "toUserId"=>$id,
                    "money"=>$money,
                    "toUserAccount"=>$tomember['account'],
                    "curTransBonusRatio"=>$system['curTransBonusRatio'],
                    "userIntegral"=>$money*$system['curTransBonusRatio'],//用户获得积分
                    "curTransRatio"=>$system['curTransRatio'],
                    "targetBalance"=>$money*$system['curTransRatio'],
                    "targetIntegral"=>$money*(1-$system['curTransRatio']),
                    "createTime"=>now_datetime()
                );
                $res=M("transfer")->add($data);//添加转入转出
                if($res){
                    $remaininte=($member['integral']+$money*$system['curTransBonusRatio']);//剩余积分
                    $integrallog=integrallog($res, $member['id'], $money*$system['curTransBonusRatio'],2,  $member['integral'],$remaininte);//转出积分log
                    $remainban=($member['balance']-$balance);//剩余余额
                    $banlog=balancelog($res, $member['id'],-$balance,2, $member['balance'],$remainban);//转出余额log
                    $toremaininte=($tomember['integral']+$money*(1-$system['curTransRatio']));//目标剩余积分
                    $tointegrallog=integrallog($res, $tomember['id'], $money*(1-$system['curTransRatio']),3, $tomember['integral'],$toremaininte);//目标积分
                    $toremainban=($tomember['balance']+$money*$system['curTransRatio']);//目标剩余余额
                    $tobanlog=balancelog($res, $tomember['id'], $money*$system['curTransRatio'], 1,$tomember['balance'],$toremainban);//目标余额log

                    $memRes=M("member")->where(array("id"=>$member['id']))->save(array("balance"=>$remainban,"integral"=>$remaininte));//更改用户余额积分
                    $tomemRes=M("member")->where(array("id"=>$tomember['id']))->save(array("balance"=>$toremainban,"integral"=>$toremaininte));
                    if(empty($memRes) || empty($tomemRes)){
                        Db::rollback();
                        $this->ajaxError("用户更改余额积分错误！");
                    }

                    //余额增加，往上找15代返还
                    $balanceRelease=balanceRelease($tomember,$money*$system['curTransRatio'],$res,1);
                    if(empty($integrallog) || empty($banlog) || empty($tointegrallog) || empty($tobanlog) || empty($balanceRelease)){
                        Db::rollback();
                        $this->ajaxError("添加余额积分log失败！");
                    }
                    Db::commit();
                    $this->ajaxSuccess("转出成功");
                }else{
                    Db::rollback();
                    $this->ajaxError("添加转入转出失败！");
                }
            }catch (Exception $e){
                Db::rollback();
                $this->ajaxError("转出错误");
            }
        }else{
            $id=I("id");
            if(empty($id)){
                $this->ajaxError("参数错误！");
            }
            $member=M("member")->where(array("id"=>$id))->find();
            $this->assign("member",$member);
            $sessionmem=parent::getAccount();
            $this->assign("sessionmem",$sessionmem);
            return $this->fetch();
        }
    }

    /**
     * 转出记录
     */
    public function turnout_record()
    {
        if(IS_POST){
            $sessionmem=parent::getAccount();
            $page = (int)I('p') ? (int)I('p') : 0;
            $list = 10;
            if(empty($sessionmem)){
                $this->ajaxError("未登录！");
            }
            $list=M("transfer")
                ->alias("t")
                ->join("balancelog b","t.id=b.reflectId",'LEFT')
                ->join("member m", "t.toUserId=m.id",'LEFT')
                ->field("t.*,m.profilePhoto profilephoto,m.nickname,b.num balance")
                ->limit($page*$list, $list)
                ->order("t.id desc")
                ->where(array("t.userId"=>$sessionmem['id'],"b.type"=>2,"b.userId"=>$sessionmem['id']))->select();
            $this->ajaxSuccess($list);
        }else{
            return $this->fetch();
        }
    }

    /**
     * 兑换积分
     */
    public function exchange_integral()
    {
        if(IS_POST){
            if (!empty(parent::get("LAST_ADD_EXCHANGE")) && time() - parent::get("LAST_ADD_EXCHANGE") <= 10) $this->ajaxError();
            parent::set("LAST_ADD_EXCHANGE", time());

            $member=parent::getAccount();
            $balance=I("balance");
            if($balance<=0){
                $this->ajaxError("兑换金额填写错误！");
            }
            if(empty($balance)){
                $this->ajaxError("兑换金额不能为空！");
            }
            if($member['balance']<$balance){
                $this->ajaxError("余额不足！");
            }
            if(!is_int($balance/100)){
                return $this->ajaxError("必须是100的整数倍！");
            }
            $system=tpCache("vpay_spstem");
            Db::startTrans();
            try{
                $data=array(
                    "user_id"=>$member['id'],
                    "balance"=>-$balance,
                    "integral"=>$balance*$system['exchangeRatio'],
                    "create_time"=>now_datetime()
                );
                $res=M("exchange")->add($data);
                if(empty($res)){
                    Db::rollback();
                    $this->ajaxError("添加兑换记录错误！");
                }
                $remaininte=($member['integral']+$balance*$system['exchangeRatio']);//剩余积分
                $integrallog=integrallog($res, $member['id'],$balance*$system['exchangeRatio'],1,$member['integral'],$remaininte);//转出积分log
                $remainban=($member['balance']-$balance);//剩余余额
                $banlog=balancelog($res, $member['id'],-$balance,8, $member['balance'],$remainban);//转出余额log
                if(empty($integrallog) || empty($banlog)){
                    Db::rollback();
                    $this->ajaxError("添加余额积分log失败！");
                }
                $memRes=M("member")->where(array("id"=>$member['id']))->save(array("balance"=>$remainban,"integral"=>$remaininte));//更改用户余额积分
                if(empty($memRes)){
                    Db::rollback();
                    $this->ajaxError("用户更改余额积分错误！");
                }
                $exchangeRes=exchangeRelease($member,$balance,$res,2);
                if(empty($exchangeRes)){
                    Db::rollback();
                    $this->ajaxError("积分兑换返佣错误！");
                }
                Db::commit();
                $this->ajaxSuccess("积分兑换成功");
            }catch (Exception $e){
                Db::rollback();
                $this->ajaxError("积分兑换错误！");
            }
        }else{
            $sessionmem=parent::getAccount();
            if(empty($sessionmem)){
                $this->redirect("vpay/login/login");
            }
            $system=tpCache("vpay_spstem");
            $sessionmem['tointegral']=$sessionmem['balance']*$system['exchangeRatio'];
            $this->assign("vo",$sessionmem);
            return $this->fetch();
        }
    }

    /**
     * 积分兑换记录
     */
    public function record_exchange()
    {
        if(IS_POST){
            $member=parent::getAccount();
            $page = (int)I('p') ? (int)I('p') : 0;
            $list = 10;
            if(empty($member)){
                $this->ajaxError("未登录！");
            }
            $list=M("exchange")
                ->where(array("user_id"=>$member['id']))
                ->limit($page*$list, $list)
                ->order("id desc")
                ->select();
            $this->ajaxSuccess($list);
        }else{
            return $this->fetch();
        }
    }

    /**
 * 扫码转入记录
 */
    public function into_record()
    {
        if(IS_POST){
            $sessionmem=parent::getAccount();
            $page = (int)I('p') ? (int)I('p') : 0;
            $list = 10;
            if(empty($sessionmem)){
                $this->ajaxError("未登录！");
            }
            $list=M("transfer")
                ->alias("t")
                ->join("balancelog b","t.id=b.reflectId",'LEFT')
                ->join(" member m","t.userId=m.id",'LEFT')
                ->field("t.*,m.profilePhoto profilephoto,m.nickname,b.num balance")
                ->limit($page*$list, $list)
                ->order("t.id desc")
                ->where(array("t.toUserId"=>$sessionmem['id'],"b.userId"=>$sessionmem['id'],"b.type"=>1))->select();
            $this->ajaxSuccess($list);
        }else{
            return $this->fetch();
        }
    }

    /**
     * 扫码转出记录
     */
    public function code_into_record()
    {
        $sessionmem=parent::getAccount();
        if(empty($sessionmem)){
            $this->redirect("vpay/login/login");
        }
        $list=M("transfer")
            ->alias("t")
            ->join("left join balancelog b on t.id=b.reflectId")
            ->field("t.*,b.num balance")
            ->where(array("t.userId"=>$sessionmem['id'],"b.userId"=>$sessionmem['id'],"b.type"=>1))->select();
        $this->assign("list",$list);
        return $this->fetch();
    }

    /**
     * 转出扫码界面
     */
    public function change_into()
    {
        $memberId = parent::getAccountId();
        $member = M("member")->where(array("id"=>$memberId))->find();
        vendor('phpqrcode.phpqrcode');
        $url = "http://" . $_SERVER['HTTP_HOST'] . "/index.php/Vpay/transfer/turnin_infor?id=" . $member['id'];

        $after_path = 'public/qrcode/'.md5($url).'.png';
        //保存路径
        $path =  ROOT_PATH.$after_path;

        //判断是该文件是否存在
        if(!is_file($path))
        {
            //实例化
            $qr = new \QRcode();
            //1:url,3: 容错级别：L、M、Q、H,4:点的大小：1到10
            $qr::png($url,'./'.$after_path, "M", 6,TRUE);
        }

        $this->assign('qrcodeImg',request()->domain().'/'.$after_path);
        return $this->fetch();
    }

    /**
     * 扫码转出页面
     */
    public function turnin_infor()
    {
        if (IS_POST) {
            if (!empty(parent::get("LAST_ADD_TURNIN")) && time() - parent::get("LAST_ADD_TURNIN") <= 10) $this->ajaxError();
            parent::set("LAST_ADD_TURNIN", time());

            $id=I("id");
            $money=I("money");
            if(empty($id)){
                $this->ajaxError("参数错误！");
            }
            if($money<=0){
                $this->ajaxError("金额错误！");
            }
            $tomember=M("member")->where(array("id"=>$id,))->find();
            if(empty($tomember)){
                $this->ajaxError("目标账户不存在！");
            }
            //当前人
            $member=parent::getAccountId();
            if(empty($member)){
                $this->ajaxError("未登录！");
            }
            $member=M("member")->where(array("id"=>$member,"isDisable"=>2,"isDelete"=>2))->find();
            if(empty($member)){
                $this->ajaxError("用户不存在！");
            }
            /*$sql="select * from transfer where userId=".$member['id']." and TIMESTAMPDIFF(SECOND,createTime,now())<10";//转出10秒限制
            $transfer=M("transfer")->query($sql);
            if(!empty($transfer)){
                $this->ajaxError("");
            }*/
            if($member['balance']<$money){
                $this->ajaxError("余额不足");
            }
            if ($tomember['id']==$member['id']){
                $this->ajaxError("对方不能是自己！");
            }
            Db::startTrans();
            try{
                $system=tpCache("vpay_spstem");
                $data=array(
                    "userId"=>$member['id'],
                    "account"=>$member['account'],
                    "toUserId"=>$id,
                    "money"=>$money,
                    "toUserAccount"=>$tomember['account'],
                    "curTransBonusRatio"=>$system['curTransBonusRatio'],
                    "userIntegral"=>$money*$system['curTransBonusRatio'],//用户获得积分
                    "curTransRatio"=>$system['curTransRatio'],
                    "targetBalance"=>$money*$system['curTransRatio'],
                    "targetIntegral"=>$money*(1-$system['curTransRatio']),
                    "createTime"=>now_datetime()
                );
                $res=M("transfer")->add($data);//添加转入转出
                if($res){
                    $remaininte=($member['integral']+$money*$system['curTransBonusRatio']);//剩余积分
                    $integrallog=integrallog($res, $member['id'], $money*$system['curTransBonusRatio'],2,  $member['integral'],$remaininte);//转出积分log
                    $remainban=($member['balance']-$money);//剩余余额
                    $banlog=balancelog($res, $member['id'],-$money,2, $member['balance'],$remainban);//转出余额log
                    $toremaininte=($tomember['integral']+$money*(1-$system['curTransRatio']));//目标剩余积分
                    $tointegrallog=integrallog($res, $tomember['id'], $money*(1-$system['curTransRatio']),3, $tomember['integral'],$toremaininte);//目标积分
                    $toremainban=($tomember['balance']+$money*$system['curTransRatio']);//目标剩余余额
                    $tobanlog=balancelog($res, $tomember['id'], $money*$system['curTransRatio'], 1,$tomember['balance'],$toremainban);//目标余额log

                    $memRes=M("member")->where(array("id"=>$member['id']))->save(array("balance"=>$remainban,"integral"=>$remaininte));//更改用户余额积分
                    $tomemRes=M("member")->where(array("id"=>$tomember['id']))->save(array("balance"=>$toremainban,"integral"=>$toremaininte));
                    if(empty($memRes) || empty($tomemRes)){
                        Db::rollback();
                        $this->ajaxError("用户更改余额积分错误！");
                    }
                    //余额增加，往上找15代返还
                    $balanceRelease=balanceRelease($tomember,$money*$system['curTransRatio'],$res,1);

                    if(empty($integrallog) || empty($banlog) || empty($tointegrallog) || empty($tobanlog) || empty($balanceRelease)){
                        Db::rollback();
                        $this->ajaxError("添加余额积分log失败！");
                    }
                    Db::commit();
                    $this->ajaxSuccess("转出成功");
                }else{
                    Db::rollback();
                    $this->ajaxError("添加转入转出失败！");
                }
            }catch (Exception $e){
                Db::rollback();
                $this->ajaxError("转出错误");
            }
        }else{
            $id=I("id");
            if(empty($id)){
                $this->ajaxError("参数错误！");
            }
            $member=M("member")->where(array("id"=>$id))->find();//目标用户
            $this->assign("member",$member);
            $sessionmem=parent::getAccount();
            $this->assign("sessionmem",$sessionmem);
            return $this->fetch();
        }
    }

}