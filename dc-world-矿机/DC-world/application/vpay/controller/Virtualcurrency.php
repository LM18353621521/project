<?php
namespace app\vpay\controller;
use think\Db;

class Virtualcurrency extends Sign
{
    /**
     * 首页
     */
    public function digital_assets()
    {
        $user=parent::getAccount();
        if(empty($user)){
            $this->redirect("Vpay/Login/login","未登录!");
        }
        $vir=M("virtualcurrency")->where(array("userId"=>$user['id']))->find();
        $system = tpCache("vpay_spstem_currency");
        $this->assign("vir",$vir);
        $this->assign("user",$user);
        $this->assign("system",$system);
        $this->assign("name",tpCache("vpay_spstem.name"));
        return $this->fetch();
    }

    /**
     * 转出vpay
     */
    public function dturn_out()
    {
        if(IS_POST){
            $entrustNum=I("post.entrustNum");
            $toUser=trim(I("post.toUser"));

            //防止连点
            if (!empty(parent::get("TRANSACTION_VPAY")) && time() - parent::get("TRANSACTION_VPAY") <= 10) {
                $this->ajaxError();
            }
            parent::set("TRANSACTION_VPAY", time());


            if(empty($toUser)){
                $this->ajaxError("转出地址不能为空！");
            }
            if(empty($entrustNum)){
                $this->ajaxError("数量不能为空！");
            }
            if($entrustNum<0.1){
                $this->ajaxError("转出数量不能小于0.1！");
            }
            $user=parent::getAccount();
            if(empty($user)){
                $this->ajaxError("未登录");
            }
            $vir=M("virtualcurrency")->where(array("userId"=>$user['id']))->find();
            if(empty($vir)){
                $this->ajaxError("没有对应的数字资产！");
            }
            if($vir['vpay']<$entrustNum){
                $this->ajaxError("数量不足！");
            }
            $condition['id'] = $toUser;
            $condition['_logic'] = 'OR';
            $condition['account'] = $toUser;
            $condition['wallet'] = $toUser;
            $tomember=M("member")->where($condition)->find();
            if(empty($tomember)){
                $this->ajaxError("目标用户不存在！");
            }
            if ($tomember['id'] == $user['id']) {
                $this->ajaxError("非法操作！");
            }
            $tovir=M("virtualcurrency")->where(array("userId"=>$tomember['id']))->find();
            if(empty($tovir)){
                $this->ajaxError("目标用户没有对应的数字资产！");
            }
            M()->startTrans();
            try{
                $data=array(
                    "user_account"=>$user['account'],//转出账户
                    "user_id"=>$user['id'],
                    "touser_account"=>$tomember['account'],//接收账户
                    "touser_id"=>$tomember['id'],
                    "entrustNum"=>$entrustNum,
                    "type"=>3,//转出Vpay
                    "createTime"=>now_datetime()
                );
                $res=M("vpayout")->add($data);
                if(empty($res)){
                    M()->rollback();
                    $this->ajaxError("转出错误！");
                }
                $vres=M("virtualcurrency")->where(array("id"=>$vir['id']))->save(array("vpay"=>($vir['vpay']-$entrustNum)));
                $tovres=M("virtualcurrency")->where(array("id"=>$tovir['id']))->save(array("vpay"=>($tovir['vpay']+$entrustNum)));
                if(empty($vres) || empty($tovres)){
                    M()->rollback();
                    $this->ajaxError("更改数字资产XTU错误！");
                }
                M()->commit();
                $this->ajaxSuccess("转出成功！");
            }catch (Exception $e){
                M()->rollback();
                $this->ajaxError("转出失败！");
            }
        }else{
            $this->assign("name",tpCache("vpay_spstem.name"));
            return $this->fetch();
        }
    }

    /**
     *  Vpay转出记录
     */
    public function transaction_record()
    {
        if(IS_POST){
            $name=I("post.type");//用户区别是转入还是转出
            $user=parent::getAccount();
            $page = (int)I('p') ? (int)I('p') : 0;
            $list = 10;
            if(empty($user)){
                $this->ajaxError("未登录！");
            }
            if($name=="2"){//转入
                $vlist=M("vpayout")
                    ->where(array("touser_id"=>$user['id'],"type"=>3))
                    ->limit($page*$list, $list)
                    ->order("id desc")
                    ->select();
                $this->ajaxSuccess($vlist);
            }else{
                $vlist=M("vpayout")
                    ->where(array("user_id"=>$user['id'],"type"=>3))
                    ->limit($page*$list, $list)
                    ->order("id desc")
                    ->select();
                $this->ajaxSuccess($vlist);
            }
        }else{
            return $this->fetch();
        }
    }

    /**
     *  xpay同步异步加载
     */
    public function xpay()
    {

            $type=I("type");//交易类型
            $currency=I("currency");//币种
            $trans_type=I("trans_type");//流通类型
            $page = (int)I('p') ? (int)I('p') : 0;
            $list = 10;
            $member = parent::get("account");
            $vir=M("virtualcurrency")->where(array("userId"=>$member['id']))->find();//数字资产
            $data=array();//要返回的数据集合
            $balance=$member['balance'];//余额
            $price=M("vctransaction")->field("max(price) maxprice,min(price) minprice")->where(array("type"=>$type,"currency"=>$currency))->find();//根据交易类型和币种取最高最低
            $data['balance']=$balance;
            $data['maxprice']=$price['maxprice'];
            $data['minprice']=$price['minprice'];
            $system=tpCache("vpay_spstem_currency");
            $cur=M("currency")->where(array("id"=>$currency))->find();
            $payname=$cur['china_name'];//名称
            $curprice=$system[$cur['name']];//当前价格
            $payvir=$vir[$cur['name']];//资产

            $data['payname']=$payname;
            $data['curprice']=$curprice;
            $data['payvir']=$payvir;
            $data['currency']=$currency;

            /*$list = M('vctransaction')
                ->alias("t")
                ->join("left join member s on t.sellerId=s.id")
                ->join("left join member b on t.buyerId=b.id")
                ->field("t.*,s.nickname sname,b.nickname bname,s.profilePhoto sprofilePhoto,b.profilePhoto bprofilePhoto")
                ->where(array("t.type"=>$type, "t.currency"=>$currency, "t.trans_type"=>$trans_type,"t.status"=>1))
                ->limit($page*$list, $list)
                ->order("t.id desc")
                ->select();*/
            $sql="SELECT t.id,t.seller,t.sellerId,t.buyer,t.buyerId,t.price,t.type,t.status,t.createTime,t.updateTime,t.currency,t.trans_type,
                  (t.entrustNum-(case when vt.entrustNum is null THEN 0 else vt.entrustNum END)) entrustNum,
                    s.nickname sname,b.nickname bname,s.profilePhoto sprofilephoto,b.profilePhoto bprofilephoto
                    from tp_vctransaction t
                    LEFT JOIN tp_member s on t.sellerId=s.id
                    left join tp_member b on t.buyerId=b.id
                    LEFT JOIN  (SELECT v.inner_id,SUM(v.entrustNum) entrustNum,v.trans_type from tp_vctransaction v  where v.inner_id !=0 GROUP BY v.inner_id,v.trans_type) vt
                    on t.id=vt.inner_id and t.trans_type=vt.trans_type WHERE t.inner_id=0 and t.type=$type and t.currency=$currency and t.trans_type=$trans_type and t.status=1
                    ORDER  by t.id asc limit ".$page*$list.",".$list;
            $list=Db::query($sql);
            $data['list']=$list;
            //$this->ajaxSuccess($data);
            $this->assign("data",$data);
            $type=I("type");//交易类型
            $currency=I("currency");//币种
            $trans_type=I("trans_type");//流通类型
            $this->assign("type",$type);
            $this->assign("currency",$currency);
            $this->assign("trans_type",$trans_type);
            $currencylist=M("currency")->order("id asc")->select();
            $this->assign("currencylist",$currencylist);
            return $this->fetch();

    }

    /**
     * 异步获取购买和出售信息
     */
    public function getTransData(){
        if (IS_POST) {
            $type=I("type");//交易类型
            $currency=I("currency");//币种
            $trans_type=I("trans_type");//流通类型
            $page = (int)I('p') ? (int)I('p') : 0;
            $list = 10;
            /*$data = M('vctransaction')
                ->alias("t")
                ->join("left join member s on t.sellerId=s.id")
                ->join("left join member b on t.buyerId=b.id")
                ->field("t.*,s.nickname sname,b.nickname bname,s.profilePhoto sprofilePhoto,b.profilePhoto bprofilePhoto")
                ->where(array("t.type"=>$type, "t.currency"=>$currency, "t.trans_type"=>$trans_type,"t.status"=>1))//未完成的订单
                ->limit($page*$list, $list)
                ->order("t.id desc")
                ->select();*/
            $sql="SELECT t.id,t.seller,t.sellerId,t.buyer,t.buyerId,t.price,t.type,t.status,t.createTime,t.updateTime,t.currency,t.trans_type,
                  (t.entrustNum-(case when vt.entrustNum is null THEN 0 else vt.entrustNum END)) entrustNum,
                    s.nickname sname,b.nickname bname,s.profilePhoto sprofilephoto,b.profilePhoto bprofilephoto
                    from vctransaction t 
                    LEFT JOIN member s on t.sellerId=s.id
                    left join member b on t.buyerId=b.id
                    LEFT JOIN  (SELECT v.inner_id,SUM(v.entrustNum) entrustNum,v.trans_type from vctransaction v  where v.inner_id !=0 GROUP BY v.inner_id,v.trans_type) vt
                    on t.id=vt.inner_id and t.trans_type=vt.trans_type WHERE t.inner_id=0 and t.type=$type and t.currency=$currency and t.trans_type=$trans_type and t.status=1
                    ORDER  by t.id asc limit ".$page*$list.",".$list;
            $data=M()->query($sql);
            $this->ajaxSuccess($data);
        }
    }


    /**
     *  出售订单加载数据
     */
    public function sell_order()
    {
        if (IS_POST) {
            $type=I("type");//交易类型
            $currency=I("currency");//币种
            $trans_type=I("trans_type");//流通类型
            $member = parent::get("account");

            $vir=M("virtualcurrency")->where(array("userId"=>$member['id']))->find();//数字资产
            $data=array();//要返回的数据集合
            $balance=$member['balance'];//余额
            $system=tpCache("xpay");
            $cur=M("currency")->where(array("id"=>$currency))->find();
            $payname=$cur['china_name'];//名称
            $curprice=$system[$cur['name']];//当前价格
            $payvir=$vir[$cur['name']];//资产

            $data['balance']=$balance;
            $data['payname']=$payname;
            $data['curprice']=$curprice;
            $data['payvir']=$payvir;
            $data['type']=$type;
            $data['currency']=$currency;
            $data['trans_type']=$trans_type;
            $this->ajaxSuccess($data);
        } else {
            $member=parent::getAccount();
            $type=I("type");//交易类型
            $currency=I("currency");//币种
            $trans_type=I("trans_type");//流通类型
            $this->assign("type",$type);
            $this->assign("currency",$currency);
            $this->assign("trans_type",$trans_type);
            $currencylist=M("currency")->order("id asc")->select();
            $bankcount=M("bankcard")->where(array("memberId"=>$member['id']))->count();
            $this->assign("currencylist",$currencylist);
            //银行卡是否有
            $this->assign("bankcount",$bankcount);
            return $this->fetch();
        }
    }

    /**
     *  发布出售订单
     */
    public function sellPublish()
    {
        if(IS_POST)
        {
            if (!empty(parent::get("LAST_ADD_SELLPUBLISH")) && time() - parent::get("LAST_ADD_SELLPUBLISH") <= 10) $this->ajaxError();
            parent::set("LAST_ADD_SELLPUBLISH", time());
            $type=I("post.type");
            $currency=I("post.currency");
            $trans_type=I("post.trans_type");
            $price=I("post.price");
            $entrustNum=I("post.entrustNum");

            //返回值
            $returnData['type']=$type;
            $returnData['currency']=$currency;
            $returnData['trans_type']=$trans_type;

            if (empty($type) || empty($currency) || empty($trans_type)){
                $this->ajaxError("参数错误");
            }
            $user=parent::getAccount();
            if(empty($user)){
                $this->ajaxError("未登录！");
            }
            if(empty($price) || empty($entrustNum)){
                $this->ajaxError("价格或数量为空！");
            }
            if($price<=0 || $entrustNum<=0){
                $this->ajaxError("价格和数量填写错误！");
            }
            if($price*$entrustNum<0.01){
                $this->ajaxError("总金额不能小于0.01！");
            }
            $cur=M("currency")->where(array("id"=>$currency))->find();
            $vir=M("virtualcurrency")->where(array("userId"=>$user['id']))->find();
            if($vir[$cur['name']]<$entrustNum){
                $this->ajaxError("币种数量不足！");
            }
            if($type==1){//现金交易需要添加银行卡
                $banklist=M("bankcard")->where(array("memberId"=>$user['id']))->select();
                if(empty($banklist)){
                    $this->ajaxError("请添加银行卡，再进行交易！");
                }
            }

            $data=array(
                "inner_id"=>0,//初始为0，有交易则代表交易id
                "seller"=>$user['account'],
                "sellerId"=>$user['id'],
                "price"=>$price,
                "entrustNum"=>$entrustNum,
                "type"=>$type,
                "status"=>1,//已挂单
                "createTime"=>now_datetime(),
                "updateTime"=>now_datetime(),
                "currency"=>$currency,
                "trans_type"=>$trans_type
            );
            M()->startTrans();//开始事务
            $res=M("vctransaction")->add($data);
            if(empty($res)){
                $this->ajaxError("购买订单发布失败！");
            }
            $virres=M("virtualcurrency")->where(array("userId"=>$user['id']))->save(array($cur['name']=>($vir[$cur['name']]-$entrustNum)));//更改币数量
            if(empty($virres)){
                M()->rollback();
                $this->ajaxError("更改币种数量失败！");
            }
            M()->commit();
            $returnData['status']=1;//默认寻找未完成
            $this->ajaxSuccess($returnData);
        }
    }

    /**
     * 添加银行卡
     */
    public function addBankCard()
    {
        $memberId = parent::get("account")['id'];
        if (IS_POST) {
            $realname = trim(I('realname'));
            $no  = trim(I('no'));
            $bankid = (int)I('bankid');
            $branch = trim(I('branch'));
            $isdefault = trim(I('isdefault')) == 'true' ? 1 : 0;

            if (empty($realname)) $this->ajaxError("请填写持卡人姓名！");
            if (empty($no)) $this->ajaxError("请填写银行卡账号！");
            if (!isBankCard($no)) $this->ajaxError("请填写正确格式的银行卡号！");
            if (empty($bankid)) $this->ajaxError("请选择开户行！");
            if (empty($branch)) $this->ajaxError("请填写支行信息！");

            if (!empty(parent::get("LAST_ADD_BANKCARD")) && time() - parent::get("LAST_ADD_BANKCARD") <= 10) $this->ajaxError();
            parent::set("LAST_ADD_BANKCARD", time());


            $data = array();
            $data['memberId'] = $memberId;
            $data['bankId'] = $bankid;
            $data['branch'] = $branch;
            $data['realName'] = $realname;
            $data['no'] = $no;
            M()->startTrans();
            if (1 == $isdefault) {
                $data['isDefault'] = 1;
                $step1 = M("bankcard")->where(array("memberId"=>$memberId, "isDelete"=>0))->save(array("isDefault"=>0));
                if (false !== $step1) {
                    $step2 = M("bankcard")->add($data);
                    if ($step2) {
                        M()->commit();
                        $this->ajaxSuccess("添加成功！");
                    } else {
                        M()->rollback();
                        $this->ajaxError("添加失败！");
                    }
                } else {
                    M()->rollback();
                    $this->ajaxError("添加失败！");
                }
            } else if (0 == $isdefault) {
                $step = M("bankcard")->add($data);
                if ($step) {
                    M()->commit();
                    $this->ajaxSuccess("添加成功！");
                } else {
                    M()->rollback();
                    $this->ajaxError("添加失败！");
                }
            }
        } else {
            $type = I('type');
            $currency=I("currency");//币种
            $trans_type=I("trans_type");//流通类型
            $this->assign("type",$type);
            $this->assign("currency",$currency);
            $this->assign("trans_type",$trans_type);
            //银行列表
            $banklist = M('bank')->select();
            $this->assign('banklist', $banklist);
            $this->display('Virtualcurrency/add_bank');
        }
    }

    /**
     *  购买订单加载数据
     */
    public function purchase_order()
    {
        if (IS_POST) {
            $type=I("type");//交易类型
            $currency=I("currency");//币种
            $trans_type=I("trans_type");//流通类型
            $member = parent::get("account");

            $vir=M("virtualcurrency")->where(array("id"=>$member['id']))->find();//数字资产
            $data=array();//要返回的数据集合
            $balance=$member['balance'];//余额
            $system=tpCache("xpay");
            $cur=M("currency")->where(array("id"=>$currency))->find();
            $payname=$cur['china_name'];//名称
            $curprice=$system[$cur['name']];//当前价格
            $payvir=$vir[$cur['name']];//资产

            $data['balance']=$balance;
            $data['payname']=$payname;
            $data['curprice']=$curprice;
            $data['payvir']=$payvir;
            $data['type']=$type;
            $data['currency']=$currency;
            $data['trans_type']=$trans_type;
            $this->ajaxSuccess($data);
        } else {
            $member=parent::getAccount();
            $type=I("type");//交易类型
            $currency=I("currency");//币种
            $trans_type=I("trans_type");//流通类型
            $this->assign("type",$type);
            $this->assign("currency",$currency);
            $this->assign("trans_type",$trans_type);
            $currencylist=M("currency")->order("id asc")->select();
            $bankcount=M("bankcard")->where(array("memberId"=>$member['id']))->count();
            $this->assign("currencylist",$currencylist);
            //银行卡是否有
            $this->assign("bankcount",$bankcount);
            return $this->fetch();
        }
    }

    /**
     *  发布购买订单
     */
    public function buyPublish()
    {
        if(IS_POST)
        {
            if (!empty(parent::get("LAST_ADD_BUYPUBLISH")) && time() - parent::get("LAST_ADD_BUYPUBLISH") <= 10) $this->ajaxError();
            parent::set("LAST_ADD_BUYPUBLISH", time());
            $type=I("post.type");
            $currency=I("post.currency");
            $trans_type=I("post.trans_type");
            $price=I("post.price");
            $entrustNum=I("post.entrustNum");
            if (empty($type) || empty($currency) || empty($trans_type)){
                $this->ajaxError("参数错误");
            }
            //返回值
            $returnData['type']=$type;
            $returnData['currency']=$currency;
            $returnData['trans_type']=$trans_type;

            $user=parent::getAccount();
            if(empty($user)){
                $this->ajaxError("未登录！");
            }
            if(empty($price) || empty($entrustNum)){
                $this->ajaxError("价格或数量为空！");
            }
            if($price<=0 || $entrustNum<=0){
                $this->ajaxError("价格或数量填写错误！");
            }
            if($price*$entrustNum<0.01){
                $this->ajaxError("总金额不能小于0.01！");
            }
            $data=array(
                "inner_id"=>0,
                "buyer"=>$user['account'],
                "buyerId"=>$user['id'],
                "price"=>$price,
                "entrustNum"=>$entrustNum,
                "type"=>$type,
                "status"=>1,//已挂单
                "createTime"=>now_datetime(),
                "updateTime"=>now_datetime(),
                "currency"=>$currency,
                "trans_type"=>$trans_type
            );
            M()->startTrans();//开始事务
            $res=M("vctransaction")->add($data);
            if(empty($res)){
                $this->ajaxError("购买订单发布失败！");
            }
            if($type==1){//现金交易---添加保证金
                $banklist=M("bankcard")->where(array("memberId"=>$user['id']))->select();
                if(empty($banklist)){
                    $this->ajaxError("请添加银行卡，再进行交易！");
                }
                if ($user['balance'] < C("VIRTUALCURRENCY_BOND")) $this->ajaxError("扣除保证金".C("VIRTUALCURRENCY_BOND")."失败，请兑换余额。");
                //保证金log
                $insert_log = balancelog($res, $user['id'], -C("VIRTUALCURRENCY_BOND"), $type=9, $before=$user['balance'], $after=$user['balance']-C("VIRTUALCURRENCY_BOND"));
                $dec_bond = M('member')->where(array("id"=>$user['id']))->setDec("balance", C("VIRTUALCURRENCY_BOND"));
                if(empty($insert_log) || empty($dec_bond)){
                    M()->rollback();
                    $this->ajaxError("扣除用户保证金出错!");
                }
            }else if($type==2){//余额交易---直接扣除余额
                if($user['balance']<$price*$entrustNum){
                    M()->rollback();
                    $this->ajaxError("余额不足！");
                }
                //余额log（数字资产）
                $insert_log = balancelog($res, $user['id'], -$price*$entrustNum, $type=11, $before=$user['balance'], $after=$user['balance']-$price*$entrustNum);
                $dec_bond = M('member')->where(array("id"=>$user['id']))->setDec("balance", $price*$entrustNum);
                if(empty($insert_log) || empty($dec_bond)){
                    M()->rollback();
                    $this->ajaxError("扣除用户余额错误!");
                }
            }
            M()->commit();
            $returnData['status']=1;//默认寻找未完成
            $this->ajaxSuccess($returnData);
        }
    }

    /**
     *  卖出、购买
     */
    public function trade()
    {
        if(IS_POST)
        {
            if (!empty(parent::get("LAST_ADD_BUYPUBLISH")) && time() - parent::get("LAST_ADD_BUYPUBLISH") <= 10) $this->ajaxError();
            parent::set("LAST_ADD_BUYPUBLISH", time());

            $id=I("post.id");
            $changeNum=I("post.changeNum");//变更数量
            $pwd=I("post.pwd");
            $type=I("post.type");
            $currency=I("post.currency");
            $trans_type=I("post.trans_type");

            if(empty($id) || empty($pwd) || empty($type) || empty($currency) || empty($trans_type)){
                parent::remove("LAST_ADD_BUYPUBLISH");
                $this->ajaxError("参数错误！");
            }
            $user=parent::getAccount();
            //返回值
            $returnData['type']=$type;
            $returnData['currency']=$currency;
            $returnData['trans_type']=$trans_type;
            if(empty($user)){
                parent::remove("LAST_ADD_BUYPUBLISH");
                $this->ajaxError("未登录！");
            }
            //校验支付密码
            if(md5($pwd)!=$user['paypassword']){
                parent::remove("LAST_ADD_BUYPUBLISH");
                $this->ajaxError("支付密码错误！");
            }
            if(empty($changeNum) || $changeNum<0){
                parent::remove("LAST_ADD_BUYPUBLISH");
                $this->ajaxError("数量不能小于0！");
            }
            M()->startTrans();
            M("vctransaction")->lock(true);
            //全部参数校验
            //$vctrans=M("vctransaction")->lock(true)->where(array("id"=>$id,"type"=>$type,"currency"=>$currency,"status"=>1,"trans_type"=>$trans_type))->find();
            //查询的是当前订单的剩余量
            $sql="SELECT t.id,t.seller,t.sellerId,t.buyer,t.buyerId,t.price,t.type,t.status,t.createTime,t.updateTime,t.currency,t.trans_type,
                  (t.entrustNum-(case when vt.entrustNum is null THEN 0 else vt.entrustNum END)) entrustNum
                    from vctransaction t
                    LEFT JOIN  (SELECT v.inner_id,SUM(v.entrustNum) entrustNum,v.trans_type from vctransaction v  where v.inner_id =$id) vt
                    on t.id=vt.inner_id and t.trans_type=vt.trans_type WHERE t.id=$id and t.type=$type and t.currency=$currency and t.trans_type=$trans_type and t.status=1";
            $sqlRes=M()->query($sql);
            if(empty($sqlRes) || count($sqlRes) != 1){
                parent::remove("LAST_ADD_BUYPUBLISH");
                M()->rollback();
                $this->ajaxError("参数错误！");
            }
            $vctrans=$sqlRes[0];
            if(empty($vctrans)){
                parent::remove("LAST_ADD_BUYPUBLISH");
                M()->rollback();
                $this->ajaxError("参数错误！");
            }
            if($vctrans['sellerid']==$user['id'] || $vctrans['buyerid']==$user['id']){//校验交易方是不是自己
                parent::remove("LAST_ADD_BUYPUBLISH");
                M()->rollback();
                $this->ajaxError("交易方不能是自己！");
            }
            $cur=M("currency")->where(array("id"=>$currency))->find();//币种
            $vir=M("virtualcurrency")->where(array("userId"=>$user['id']))->find();
            if($changeNum>$vctrans['entrustnum']){//变更数量应该小于等于购买币种数量
                parent::remove("LAST_ADD_BUYPUBLISH");
                M()->rollback();
                $this->ajaxError("数量超出了订单数量！");
            }
            //如果当前订单的数量等于原始发布订单的数量，则更改原始订单为已完成,无论是买还是卖
            if($changeNum==$vctrans['entrustnum']){
                $vc_save=M("vctransaction")->where(array("id"=>$vctrans['id']))->save(array("status"=>4));//更改原始订单状态
                if(empty($vc_save)){
                    parent::remove("LAST_ADD_BUYPUBLISH");
                    M()->rollback();
                    $this->ajaxError("下单失败！");
                }
            }
            if($trans_type==1){//别人发布的购买订单，当前人是要卖
                //If($vir[$cur['name']]<$vctrans['entrustnum']){
                If($vir[$cur['name']]<$changeNum){//当前人币数量校验
                    parent::remove("LAST_ADD_BUYPUBLISH");
                    M()->rollback();
                    $this->ajaxError($cur['china_name']."数量不足，无法交易！");
                }
                //先把当前人扣币，无论是现金还是余额都要扣币
                $vir_save = M('virtualcurrency')->where(array("userId"=>$user['id']))->setDec($cur['name'],$changeNum);
                if(empty($vir_save)){
                    parent::remove("LAST_ADD_BUYPUBLISH");
                    M()->rollback();
                    $this->ajaxError("扣除".$cur['china_name']."失败！");
                }
                //如果是余额交易，直接交易成功，如果是现金交易
                if($type==1){//现金交易
                    //更改订单为未付款，同时扣币
                    /*$data=array(
                        "seller"=>$user['account'],
                        "sellerId"=>$user['id'],
                        "status"=>2,//未付款
                        "updateTime"=>now_datetime()
                    );
                    $res=M("vctransaction")->where(array("id"=>$id))->save($data);//更改订单状态
                    if(empty($res)){
                        M()->rollback();
                        $this->ajaxError("下单失败！");
                    }*/
                    //复制当前订单，更改订单为未付款，同时扣币
                    $vctrans_copy=$vctrans;
                    $vctrans_copy['inner_id']=$vctrans['id'];
                    $vctrans_copy['seller']=$user['account'];
                    $vctrans_copy['sellerId']=$vctrans['sellerid'];
                    $vctrans_copy['buyerId']=$vctrans['buyerid'];
                    $vctrans_copy['status']=2;//未付款
                    $vctrans_copy['entrustNum']=$changeNum;//当前的变换数量
                    $vctrans_copy['createTime']=now_datetime();
                    $vctrans_copy['updateTime']=now_datetime();
                    unset($vctrans_copy['id']);
                    $vc_add=M("vctransaction")->add($vctrans_copy);//每一条订单都作为新订单存入
                    if(empty($vc_add)){
                        parent::remove("LAST_ADD_BUYPUBLISH");
                        M()->rollback();
                        $this->ajaxError("下单失败！");
                    }
                    //等待对方付款
                    $returnData['status']=2;//返回值，用于页面跳转
                }else if($type==2){//余额交易
                    /*//直接改为确认,扣除币
                    $data=array(
                        "seller"=>$user['account'],
                        "sellerId"=>$user['id'],
                        "status"=>4,//直接确认
                        "updateTime"=>now_datetime()
                    );

                    $res=M("vctransaction")->where(array("id"=>$id))->save($data);//更改订单状态
                    if(empty($res)){
                        M()->rollback();
                        $this->ajaxError("下单失败！");
                    }*/
                    //复制当前订单
                    $vctrans_copy=$vctrans;
                    $vctrans_copy['inner_id']=$vctrans['id'];
                    $vctrans_copy['seller']=$user['account'];
                    $vctrans_copy['sellerId']=$user['id'];
                    $vctrans_copy['buyerId']=$vctrans['buyerid'];
                    $vctrans_copy['status']=4;//直接确认
                    $vctrans_copy['entrustNum']=$changeNum;//当前的变换数量
                    $vctrans_copy['createTime']=now_datetime();
                    $vctrans_copy['updateTime']=now_datetime();
                    unset($vctrans_copy['id']);
                    $vc_add=M("vctransaction")->add($vctrans_copy);//每一条订单都作为新订单存入
                    if(empty($vc_add)){
                        parent::remove("LAST_ADD_BUYPUBLISH");
                        M()->rollback();
                        $this->ajaxError("下单失败！");
                    }
                    //给买方加币
                    $vir_buy_save=M("virtualcurrency")->where(array("userId"=>$vctrans['buyerid']))->setInc($cur['name'],$changeNum);
                    if(empty($vir_buy_save)){
                        M()->rollback();
                        $this->ajaxError("买方加币失败！");
                    }

                    if($vctrans['price']*$changeNum>=0.01){//数据库中balance为两位小数，避免错误
                        $mem_save=M("member")->where(array("id"=>$user['id']))->save(array("balance"=>($user['balance']+$vctrans['price']*$changeNum))); //当前人加钱
                        if(empty($mem_save)){
                            parent::remove("LAST_ADD_BUYPUBLISH");
                            M()->rollback();
                            $this->ajaxError("更改用户金额失败！");
                        }
                        //余额log（数字资产）
                        $log_add = balancelog($vc_add, $user['id'], $vctrans['price']*$changeNum, $type=12, $before=$user['balance'], $after=$user['balance']+$vctrans['price']*$changeNum);
                        $balanceRelease=balanceRelease($user,$vctrans['price']*$changeNum,$vc_add);//余额增加，往上找15代返还
                        if(empty($log_add) || empty($balanceRelease)){
                            parent::remove("LAST_ADD_BUYPUBLISH");
                            M()->rollback();
                            $this->ajaxError("添加余额log错误！");
                        }
                    }
                    $returnData['status']=4;//返回值，用于页面跳转
                }
            }else if($trans_type==2){//别人发布的出售订单，当前人是要买
                if($type==1){
                    if ($user['balance'] < C("VIRTUALCURRENCY_BOND")) $this->ajaxError("扣除保证金".C("VIRTUALCURRENCY_BOND")."失败，请兑换余额。");
                    /*//状态改为2，等待对方线下打款
                    $data=array(
                        "buyer"=>$user['account'],
                        "buyerId"=>$user['id'],
                        "status"=>2,//未付款
                        "updateTime"=>now_datetime()
                    );
                    $res=M("vctransaction")->where(array("id"=>$id))->save($data);//更改订单状态
                    if(empty($res)){
                        M()->rollback();
                        $this->ajaxError("下单失败！");
                    }*/
                    //复制当前订单，状态改为2，等待对方线下打款
                    $vctrans_copy=$vctrans;
                    $vctrans_copy['inner_id']=$vctrans['id'];
                    $vctrans_copy['buyer']=$user['account'];
                    $vctrans_copy['buyerId']=$user['id'];
                    $vctrans_copy['sellerId']=$vctrans['sellerid'];
                    $vctrans_copy['status']=2;//直接确认
                    $vctrans_copy['entrustNum']=$changeNum;//当前的变换数量
                    $vctrans_copy['createTime']=now_datetime();
                    $vctrans_copy['updateTime']=now_datetime();
                    unset($vctrans_copy['id']);
                    $vc_add=M("vctransaction")->add($vctrans_copy);//每一条订单都作为新订单存入
                    if(empty($vc_add)){
                        parent::remove("LAST_ADD_BUYPUBLISH");
                        M()->rollback();
                        $this->ajaxError("下单失败！");
                    }
                    //保证金log
                    $insert_log = balancelog($vc_add, $user['id'], -C("VIRTUALCURRENCY_BOND"), $type=9, $before=$user['balance'], $after=$user['balance']-C("VIRTUALCURRENCY_BOND"));
                    $dec_bond = M('member')->where(array("id"=>$user['id']))->setDec("balance", C("VIRTUALCURRENCY_BOND"));
                    if(empty($insert_log) || empty($dec_bond)){
                        parent::remove("LAST_ADD_BUYPUBLISH");
                        M()->rollback();
                        $this->ajaxError("扣除用户保证金出错!");
                    }
                    $returnData['status']=2;//返回值，用于页面跳转
                }else if($type==2){//余额交易
                    //校验余额是否够
                    if($user['balance']<$vctrans['price']*$changeNum){
                        parent::remove("LAST_ADD_BUYPUBLISH");
                        M()->rollback();
                        $this->ajaxError("余额不足，请去充值！");
                    }
                    /*//状态改为4，更改订单状态
                    $data=array(
                        "buyer"=>$user['account'],
                        "buyerId"=>$user['id'],
                        "status"=>4,
                        "updateTime"=>now_datetime()
                    );
                    $res=M("vctransaction")->where(array("id"=>$id))->save($data);//更改订单状态
                    if(empty($res)){
                        M()->rollback();
                        $this->ajaxError("下单失败！");
                    }*/
                    //复制当前订单，状态改为2，等待对方线下打款
                    $vctrans_copy=$vctrans;
                    $vctrans_copy['inner_id']=$vctrans['id'];
                    $vctrans_copy['buyer']=$user['account'];

                    $vctrans_copy['buyerId']=$user['id'];
                    $vctrans_copy['sellerId']=$vctrans['sellerid'];
                    $vctrans_copy['status']=4;//直接确认
                    $vctrans_copy['entrustNum']=$changeNum;//当前的变换数量
                    $vctrans_copy['createTime']=now_datetime();
                    $vctrans_copy['updateTime']=now_datetime();
                    unset($vctrans_copy['id']);
                    $vc_add=M("vctransaction")->add($vctrans_copy);//每一条订单都作为新订单存入
                    if(empty($vc_add)){
                        parent::remove("LAST_ADD_BUYPUBLISH");
                        M()->rollback();
                        $this->ajaxError("下单失败！");
                    }
                    if($vctrans['price']*$changeNum>=0.01){//避免因数据库问题产生错误
                        //当前人扣除余额
                        $mem_save=M("member")->where(array("id"=>$user['id']))->save(array("balance"=>($user['balance']-$vctrans['price']*$changeNum)));
                        if(empty($mem_save)){
                            parent::remove("LAST_ADD_BUYPUBLISH");
                            M()->rollback();
                            $this->ajaxError("更改用户金额失败！");
                        }
                        //买方balancelog(数字资产)
                        $log_add = balancelog($vc_add, $user['id'], -$vctrans['price']*$changeNum, $type=11, $before=$user['balance'], $after=$user['balance']-$vctrans['price']*$changeNum);
                        if(empty($log_add)){
                            parent::remove("LAST_ADD_BUYPUBLISH");
                            M()->rollback();
                            $this->ajaxError("添加余额log错误！");
                        }
                        $sell_mem=M("member")->where(array("id"=>$vctrans['sellerid']))->find();
                        //卖方添加余额
                        $mem_save=M("member")->where(array("id"=>$vctrans['sellerid']))->setInc("balance",$vctrans['price']*$changeNum);
                        if(empty($mem_save)){
                            parent::remove("LAST_ADD_BUYPUBLISH");
                            M()->rollback();
                            $this->ajaxError("更改用户金额失败！");
                        }
                        //卖方balancelog(数字资产)
                        $sell_log_add = balancelog($vc_add, $vctrans['sellerid'], $vctrans['price']*$changeNum, $type=12, $before=$sell_mem['balance'], $after=$sell_mem['balance']+$vctrans['price']*$changeNum);

                        //余额增加，往上找15代返还
                        $balanceRelease=balanceRelease($sell_mem,$vctrans['price']*$changeNum,$vc_add);
                        if(empty($sell_log_add) || empty($balanceRelease)){
                            parent::remove("LAST_ADD_BUYPUBLISH");
                            M()->rollback();
                            $this->ajaxError("添加余额log错误！");
                        }
                    }
                    //当前人加币
                    $vir_save=M("virtualcurrency")->where(array("userId"=>$user['id']))->setInc($cur['name'],$changeNum);
                    if(empty($vir_save)){
                        parent::remove("LAST_ADD_BUYPUBLISH");
                        M()->rollback();
                        $this->ajaxError("更新".$cur['china_name']."失败!");
                    }
                    $returnData['status']=4;//返回值，用于页面跳转
                }
            }
            M()->commit();
            $this->ajaxSuccess($returnData);
        }
    }

    /**
     *  订单  包括购买的和出售的
     */
    public function xpay_orderlist(){
        if(IS_POST){
            $page = (int)I('p') ? (int)I('p') : 0;
            $list = 10;
            $status=I("status");
            $type=I("type");
            //$trans_type=I("trans_type");
            $currency=I("currency");
            $user=parent::getAccount();
            if (empty($user)){
                $this->ajaxError("未登录！");
            }
            if(empty($status) || empty($type) || empty($currency)){
                $this->ajaxError("参数错误！");
            }
            $where['t.type']=$type;
            // $where['trans_type']=$trans_type;
            $where['t.currency']=$currency;
            $where['_query'] = 't.buyerId='.$user['id'].'& t.sellerId='.$user['id'].' &_logic=or';
            if($status==1){//未完成
                //$where['status']=$status;
                $sql="SELECT t.id,t.seller,t.sellerId,t.buyer,t.buyerId,t.price,t.type,t.status,t.createTime,t.updateTime,t.currency,t.trans_type,
                  (t.entrustNum-(case when vt.entrustNum is null THEN 0 else vt.entrustNum END)) entrustNum
                    from vctransaction t
                    LEFT JOIN  (SELECT v.inner_id,SUM(v.entrustNum) entrustNum,v.trans_type from vctransaction v  where v.inner_id !=0 GROUP BY v.inner_id,v.trans_type) vt
                    on t.id=vt.inner_id and t.trans_type=vt.trans_type WHERE t.inner_id=0 and t.type=$type and t.currency=$currency and  t.status=$status
                    and (t.buyerId=".$user['id']." or t.sellerId=".$user['id'].")
                    ORDER  by t.id desc limit ".$page*$list.",".$list;
                $orders=M()->query($sql);
            }else if($status==2 || $status==3){//待处理
              /*  $where['t.status']=array("in","2,3");
                $where['t.inner_id']=array("neq","0");
                $orders=M("vctransaction")
                    ->alias("t")
                    ->field("t.*,mb.nickname bnickname,ms.nickname snickname")
                    ->join("left join member mb on t.buyerId=mb.id")
                    ->join("left join member ms on t.sellerId=ms.id")
                    ->where($where)
                    ->limit($page*$list, $list)
                    ->order("t.id desc")->select();*/
                $sql="SELECT t.*,mb.nickname bnickname,ms.nickname snickname FROM vctransaction t 
                  left join member mb on t.buyerId=mb.id left join member ms on t.sellerId=ms.id  
                  WHERE t.type = $type AND t.currency = $currency AND (t.buyerId = ".$user['id']." OR t.sellerId =".$user['id']." ) AND t.status IN ('2','3') AND t.inner_id <> 0 ORDER BY t.id desc LIMIT ".$page*$list.",".$list;
                $orders=M()->query($sql);
            }else if($status==4 || $status==5){
               /* $where['t.status']=array("in","4,5");
                $where['t.inner_id']=array("neq","0");
                $orders=M("vctransaction")
                    ->alias("t")
                    ->field("t.*,mb.nickname bnickname,ms.nickname snickname")
                    ->join("left join member mb on t.buyerId=mb.id")
                    ->join("left join member ms on t.sellerId=ms.id")
                    ->where($where)
                    ->limit($page*$list, $list)
                    ->order("id desc")->select();*/
                $sql="SELECT t.*,mb.nickname bnickname,ms.nickname snickname FROM vctransaction t 
                  left join member mb on t.buyerId=mb.id left join member ms on t.sellerId=ms.id  
                  WHERE t.type = $type AND t.currency = $currency 
                  AND (t.buyerId = ".$user['id']." OR t.sellerId =".$user['id']." ) AND t.status IN ('4','5') AND t.inner_id <> 0 ORDER BY t.id desc LIMIT ".$page*$list.",".$list;
                $orders=M()->query($sql);
            }
            $this->ajaxSuccess($orders);
        }else{
            $user=parent::getAccount();
            if(empty($user)){
                $this->redirect("Home/Login/login","未登录!");
            }
            $status=I("status");
            $type=I("type");
            $trans_type=I("trans_type");
            $currency=I("currency");
            $currencys=M("currency")->order("id asc")->select();//所有币种
            $this->assign("currencys",$currencys);
            $this->assign("status",$status);
            $this->assign("type",$type);
            $this->assign("trans_type",$trans_type);
            $this->assign("currency",$currency);
            $this->assign("user",json_encode($user));
            return $this->fetch();
        }
    }

    /**
     *  购买订单确认打款，订单状态改为3
     */
    public function confirm_order(){
        if(IS_POST){
            $id=I("id");
            $type=I("type");
            $trans_type=I("trans_type");
            $currency=I("currency");
            $user=parent::getAccount();
            if (empty($user)){
                $this->ajaxError("未登录！");
            }

            if(empty($id) || empty($type) || empty($currency)){
                $this->ajaxError("参数错误！");
            }
            if($type!=1){//只有现金的才可以
                $this->ajaxError("参数错误！");
            }
            //订单状态改为3
            $res=M("vctransaction")->where(array("id"=>$id,"type"=>$type,"currency"=>$currency))->setInc("status",1);//订单状态改为3
            if(empty($res)){
                $this->ajaxError("确认打款失败！");
            }
            $returnData['type']=$type;
            $returnData['currency']=$currency;
            $returnData['status']=3;
            $this->ajaxSuccess($returnData);
        }
    }

    /**
     *  购买订单确认收款，订单状态改为4
     */
    public function confirm_receive(){
        if(IS_POST){
            if (!empty(parent::get("LAST_ADD_RECEIVE")) && time() - parent::get("LAST_ADD_RECEIVE") <= 10) $this->ajaxError();
            parent::set("LAST_ADD_RECEIVE", time());

            $id=I("id");
            $type=I("type");
            $trans_type=I("trans_type");
            $currency=I("currency");
            $user=parent::getAccount();
            if (empty($user)){
                $this->ajaxError("未登录！");
            }

            if(empty($id) || empty($type) || empty($currency)){
                $this->ajaxError("参数错误！");
            }
            if($type!=1){//只有现金的才可以
                $this->ajaxError("参数错误！");
            }
            //订单状态改为4
            M()->startTrans();
            $res=M("vctransaction")->where(array("id"=>$id,"type"=>$type,"currency"=>$currency))->setInc("status",1);//订单状态改为4
            if(empty($res)){
                M()->rollback();
                $this->ajaxError("确认打款失败！");
            }
            //买方加币，
            $cur=M("currency")->where(array("id"=>$currency))->find();
            $vc=M("vctransaction")->where(array("id"=>$id,"type"=>$type,"currency"=>$currency))->find();
            $vir_save=M("virtualcurrency")->where(array("userId"=>$vc['buyerid']))->setInc($cur['name'],$vc['entrustnum']);
            if(empty($vir_save)){
                M()->rollback();
                $this->ajaxError("添加".$cur['china_name']."失败！");
            }
            $buy_mem=M("member")->where(array("id"=>$vc['buyerid']))->find();
            //买方回保证金
            $insert_log = balancelog($res, $vc['buyerid'], C("VIRTUALCURRENCY_BOND"), $type=10, $before=$buy_mem['balance'], $after=$buy_mem['balance']+C("VIRTUALCURRENCY_BOND"));
            $dec_bond = M('member')->where(array("id"=>$buy_mem['id']))->setDec("balance", C("VIRTUALCURRENCY_BOND"));
            if(empty($insert_log) || empty($dec_bond)){
                M()->rollback();
                $this->ajaxError("添加用户保证金出错!");
            }
            M()->commit();
            $returnData['type']=$type;
            $returnData['currency']=$currency;
            $returnData['status']=4;
            $this->ajaxSuccess($returnData);
        }
    }

    /**
     *  取消订单，如果是购买，返还保证金，如果是出售，返还币种
     */
    public function cancel(){
        if(IS_POST){
            if (!empty(parent::get("LAST_ADD_CANCEL")) && time() - parent::get("LAST_ADD_CANCEL") <= 10) $this->ajaxError();
            parent::set("LAST_ADD_CANCEL", time());

            $id=I("id");
            $type=I("type");
            $trans_type=I("trans_type");
            $currency=I("currency");
            $user=parent::getAccount();
            if (empty($user)){
                $this->ajaxError("未登录！");
            }

            if(empty($id) || empty($type) || empty($currency)){
                $this->ajaxError("参数错误！");
            }
//            if($type!=1){//只有现金的才可以
//                $this->ajaxError("参数错误！");
//            }
            //订单状态改为5
            M()->startTrans();
            $res=M("vctransaction")->where(array("id"=>$id,"type"=>$type,"currency"=>$currency))->save(array("status"=>5));//订单状态改为5
            if(empty($res)){
                M()->rollback();
                $this->ajaxError("修改订单状态失败！");
            }
            //买方加币，
            $cur=M("currency")->where(array("id"=>$currency))->find();
            //$vc=M("vctransaction")->where(array("id"=>$id,"type"=>$type,"currency"=>$currency))->find();//订单
            $sql="SELECT t.id,t.seller,t.sellerId,t.buyer,t.buyerId,t.price,t.type,t.status,t.createTime,t.updateTime,t.currency,t.trans_type,
                  (t.entrustNum-(case when vt.entrustNum is null THEN 0 else vt.entrustNum END)) entrustNum
                    from vctransaction t
                    LEFT JOIN  (SELECT v.inner_id,SUM(v.entrustNum) entrustNum,v.trans_type from vctransaction v  where v.inner_id =$id) vt
                    on t.id=vt.inner_id and t.trans_type=vt.trans_type WHERE t.id=$id and t.type=$type and t.currency=$currency";
            $sqlRes=M()->query($sql);
            if(empty($sqlRes) || count($sqlRes) != 1){
                M()->rollback();
                $this->ajaxError("参数错误！");
            }
            $vc=$sqlRes[0];//查询的用户剩余的所有数量
            if(empty($vc)){
                M()->rollback();
                $this->ajaxError("参数错误！");
            }
            $vc_copy=$vc;
            $vc_copy['status']=5;
            $vc_copy['sellerId']=$vc['sellerid'];
            $vc_copy['buyerId']=$vc['buyerid'];
            $vc_copy['entrustNum']=$vc['entrustnum'];
            $vc_copy['createTime']=now_datetime();
            $vc_copy['updateTime']=now_datetime();
            $vc_copy['inner_id']=$id;
            unset($vc_copy['id']);
            $vc_add=M("vctransaction")->add($vc_copy);
            if(empty($vc_add)){
                M()->rollback();
                $this->ajaxError("取消订单失败！");
            }

            if($vc['trans_type']==1){//购买的，回加保证金
                //2018.05.18
                if (1 == $vc['type']) {
                    //1现金交易
                    //买方回保证金
                    $bond = C("VIRTUALCURRENCY_BOND");
                } else if(2 == $vc['type']) {
                    //2余额交易
                    $bond = $vc['entrustnum']*$vc['price'];
                }
                $insert_log = balancelog($res, $user['id'], $bond, $type=10, $before=$user['balance'], $after=$user['balance']+ $bond);
                $dec_bond = M('member')->where(array("id"=>$user['id']))->setInc("balance", $bond);
                if(empty($insert_log) || empty($dec_bond)){
                    M()->rollback();
                    $this->ajaxError("添加用户保证金出错!");
                }

            }else if($vc['trans_type']==2){//出售的，回加币种
                $vir_save=M("virtualcurrency")->where(array("userId"=>$user['id']))->setInc($cur['name'],$vc['entrustnum']);
                if(empty($vir_save)){
                    M()->rollback();
                    $this->ajaxError("添加".$cur['china_name']."失败！");
                }
            }
            M()->commit();
            $returnData['type']=$type;
            $returnData['currency']=$currency;
            $returnData['status']=5;
            $this->ajaxSuccess($returnData);
        }
    }

    /**
     * 查看银行卡详细
     */
    public function sellBankDetail(){
        if (IS_POST) {
            $id = (int)I('id');
            $memberId = parent::get('account')['id'];
            if(empty($id)){
                $this->ajaxError("参数错误！");
            }
            $vctrans=M("vctransaction")->where(array("id"=>$id))->find();
            if(empty($vctrans)){
                $this->ajaxError("参数错误！");
            }
            if($memberId==$vctrans['sellerid']){//获取对方的银行卡信息
                $bank_mem_id=$vctrans['buyeid'];
            }else{
                $bank_mem_id=$vctrans['sellerid'];
            }
            $data=M("bankcard")
                ->alias("t")
                ->join("left join bank b on t.bankId=b.bankId")
                ->where(array("t.memberId"=>$bank_mem_id,"isDefault"=>1,"isDelete"=>0))
                ->find();
            if(empty($data)){
                $data=M("bankcard")
                    ->alias("t")
                    ->join("left join bank b on t.bankId=b.bankId")
                    ->where(array("t.memberId"=>$bank_mem_id,"isDelete"=>0))
                    ->order("t.id desc")
                    ->limit(1)
                    ->find();
            }
            if(empty($data)){
                $this->ajaxError("没有信息");
            }else{
                $this->ajaxSuccess($data);
            }
        }
    }
}