<?php

namespace app\vpay\controller;

use Think\Db;
/**
 * 交易
 * Class TransactionController
 * @package Home\Controller
 */
class Transaction extends Sign
{
    /**
     * 交易买入 & 创建订单
     */
    public function purchase()
    {
        $memberId = parent::get('account')['id'];
        if (IS_POST) {
            $num = trim(I('num')); //支付密码
            $sum = floatval(I('sum')); //交易数量
            $bankcardid = (int)I('bankcardid'); //银行卡id

            if (!in_array($sum, C("TRANSACTION_NUM_BUY"))) $this->ajaxError("交易数额错误，请刷新页面后重试！");
            $bankcardinfo = M('bankcard')->where(array("id"=>$bankcardid, "isDelete"=>0))->find();
            if (empty($bankcardid) || $bankcardinfo['memberId'] != $memberId) $this->ajaxError("非法操作");
            $member = M("member")->find($memberId);
            if ($member['paypassword'] != md5($num)) $this->ajaxError("支付密码错误！");
            if ($member['balance'] < C("TRANSACTION_BOND")) $this->ajaxError("扣除保证金".C("TRANSACTION_BOND")."失败，请兑换余额。");

            //防止连点
            if (!empty(parent::get("TRANSACTION_BUY_TIME")) && time() - parent::get("TRANSACTION_BUY_TIME") <= 10) {
                $this->ajaxError();
            }
            parent::set("TRANSACTION_BUY_TIME", time());

            //买入订单创建
            //扣除保证金100
            Db::startTrans();
            $dec_bond = M('member')->where(array("id"=>$memberId))->setDec("balance", C("TRANSACTION_BOND"));
            if ($dec_bond) {
                //创建买入订单
                $data = array();
                $data['buyer'] = $bankcardinfo['realName'];
                $data['buyerId'] = $memberId;
                $data['buyerBankCardId'] = $bankcardid;
                $data['entrustNum'] = $sum;
                $data['status'] = 1; //挂单
                $data['createTime'] = now_datetime();
                $data['type'] = 1; //买入
                $insert_buy = M('transaction')->add($data);
                if ($insert_buy) {
                    //写扣除保证金余额变动记录(要写入reflectid， 调到写交易记录后边)
                    $insert_log = balancelog($insert_buy, $memberId, -C("TRANSACTION_BOND"), $type=6, $before=$member['balance'], $after=$member['balance']-C("TRANSACTION_BOND"));
                    if ($insert_log) {
                        Db::commit();
                        $this->ajaxSuccess("创建订单成功！");
                    } else {
                        Db::rollback();
                        $this->ajaxError("日志写入失败！");
                    }
                } else {
                    Db::rollback();
                    $this->ajaxError("交易记录插入失败！");
                }
            } else {
                Db::rollback();
                $this->ajaxError("扣除保证金".C("TRANSACTION_BOND")."失败！");
            }
        } else {
            //用户选择银行卡
            $cardid = (int)I('cardid');

            $default_bankcard = M('bankcard')
                ->alias("c")
                ->join("bank b","c.bankId=b.bankId",'LEFT')
                ->where(array("memberId"=>$memberId, "isDefault"=>1,'isDelete'=>0))
                ->find();

            if ($cardid) {
                $default_bankcard_select = M('bankcard')
                    ->alias("c")
                    ->join("bank b","c.bankId=b.bankId",'LEFT')
                    ->where(array("memberId"=>$memberId, "id"=>$cardid))
                    ->find();
                if (!empty($default_bankcard_select)) {
                    $default_bankcard = $default_bankcard_select;
                }
            }

            if ($default_bankcard) {
                $dbcard = 1;
                $this->assign("default_bankcard", $default_bankcard);
            } else {
                $dbcard = 0;
            }

            //交易金额
            $transaction_num = C('TRANSACTION_NUM_BUY');

            $this->assign("transaction_num", $transaction_num);
            $this->assign("db_flag", $dbcard);
            return $this->fetch('transaction/purchase');
        }
    }

    /**
     * 取消买入订单
     */
    public function cancelOrder()
    {
        if (IS_POST) {
            $id = (int)I('id');
            $memberId = parent::get('account')['id'];
            $member = parent::get('account');
            if (empty($id)) $this->ajaxError("参数错误！");
            $transaction = M('transaction')->where(array("id"=>$id, "type"=>1, "buyerId"=>$memberId))->find();
            if (empty($transaction)) $this->ajaxError("订单信息不存在！");

            //防止连点
            if (!empty(parent::get("TRANSACTION_BUY_CANCELORDER")) && time() - parent::get("TRANSACTION_BUY_CANCELORDER") <= 10) {
                $this->ajaxError();
            }
            parent::set("TRANSACTION_BUY_CANCELORDER", time());


            Db::startTrans();
            //①更改订单状态
            $update_order = M('transaction')->where(array("id"=>$id, "type"=>1, "buyerId"=>$memberId))->save(array("status"=>5));
            if ($update_order) {
                //②回加保证金
                $add_bond = M('member')->where(array("id"=>$memberId))->setInc("balance", C("TRANSACTION_BOND"));
                if ($add_bond) {
                    //③写入保证金回加记录
                    $insert_log = balancelog($id, $memberId, C("TRANSACTION_BOND"), $type=13, $before=$member['balance'], $after=$member['balance']+C("TRANSACTION_BOND"));
                    if ($insert_log) {
                        Db::commit();
                        $this->ajaxSuccess("取消订单成功！");
                    } else {
                        Db::rollback();
                        $this->ajaxError("日志写入失败！");
                    }
                } else {
                    Db::rollback();
                    $this->ajaxError("回加保证金失败！");
                }
            } else {
                Db::rollback();
                $this->ajaxError("订单状态更新错误！");
            }

        } else {
            $this->ajaxError("请求错误！");
        }
    }

    /**
     * 交易卖出 & 创建订单
     */
    public function sellOut()
    {
        $memberId = parent::get('account')['id'];
        if (IS_POST) {
            $num = trim(I('num')); //支付密码
            $sum = floatval(I('sum')); //交易数量
            $bankcardid = (int)I('bankcardid'); //银行卡id
            $remark = trim(I("remark")); //备注

            $system=tpCache("vpay_spstem");
            $poundage = $system['poundage'] ? $system['poundage'] : 0;//手续费

            if (!in_array($sum, C("TRANSACTION_NUM_SELL"))) $this->ajaxError("交易数额错误，请刷新页面后重试！");
            $bankcardinfo = M('bankcard')->where(array("id"=>$bankcardid, "isDelete"=>0))->find();
            if (empty($bankcardid) || $bankcardinfo['memberId'] != $memberId) $this->ajaxError("非法操作");
            $member = M("member")->find($memberId);
            if ($member['paypassword'] != md5($num)) $this->ajaxError("支付密码错误！");
            if ($member['balance'] < $sum) $this->ajaxError("扣除卖出余额失败，请兑换余额！");
            if ($member['balance'] < $sum+($sum*$system['poundage'])) $this->ajaxError("手续费不足，请兑换余额！");

            //防止连点
            if (!empty(parent::get("TRANSACTION_SELL_TIME")) && time() - parent::get("TRANSACTION_SELL_TIME") <= 10) {
                $this->ajaxError();
            }
            parent::set("TRANSACTION_SELL_TIME", time());
            //卖出订单创建 扣除卖出余额
            Db::startTrans();
            $dec_balance = M('member')->where(array("id"=>$memberId))->setDec("balance", $sum+($sum*$poundage));
            if ($dec_balance) {
                //创建卖出订单
                $data = array();
                $data['seller'] = $bankcardinfo['realName'];
                $data['sellerId'] = $memberId;
                $data['sellerBankCardId'] = $bankcardid;
                $data['entrustNum'] = $sum;
                $data['status'] = 1; //挂单
                $data['createTime'] = now_datetime();
                $data['type'] = 2; //卖出
                $data['remark'] = $remark;
                $insert_sell = M('transaction')->add($data);
                if ($insert_sell) {
                    //写扣除余额变动记录(要写入reflectid， 调到写交易记录后边)
                    $insert_log = balancelog($insert_sell, $memberId, -$sum, $type=4, $before=$member['balance'], $after=$member['balance']-$sum);
                    if($system['poundage'] > 0.001){
                        $insert_log = balancelog($insert_sell, $memberId, -($sum*$poundage), $type=18, $before=$member['balance']-$sum, $after=$member['balance']-($sum+($sum*$poundage)));
                    }
                    if ($insert_log) {
                        Db::commit();
                        $this->ajaxSuccess("创建订单成功！");
                    } else {
                        Db::rollback();
                        $this->ajaxError("日志写入失败！");
                    }
                } else {
                    Db::rollback();
                    $this->ajaxError("交易记录插入失败！");
                }
            } else {
                Db::rollback();
                $this->ajaxError("扣除卖出余额失败！");
            }
        } else {
            //用户选择银行卡
            $cardid = (int)I('cardid');

            $default_bankcard = M('bankcard')
                ->alias("c")
                ->join("bank b","c.bankId=b.bankId",'LEFT')
                ->where(array("memberId"=>$memberId, "isDefault"=>1,'isDelete'=>0))
                ->find();

            if ($cardid) {
                $default_bankcard_select = M('bankcard')
                    ->alias("c")
                    ->join("bank b","c.bankId=b.bankId",'LEFT')
                    ->where(array("memberId"=>$memberId, "id"=>$cardid))
                    ->find();
                if (!empty($default_bankcard_select)) {
                    $default_bankcard = $default_bankcard_select;
                }
            }

            if ($default_bankcard) {
                $dbcard = 1;
                $this->assign("default_bankcard", $default_bankcard);
            } else {
                $dbcard = 0;
            }

            //交易金额
            $transaction_num = C('TRANSACTION_NUM_SELL');

            $this->assign("transaction_num", $transaction_num);
            $this->assign("db_flag", $dbcard);
            return $this->fetch('transaction/sell_out');
        }
    }

    /**
     * 选择银行卡
     */
    public function bankList_purchase()
    {
        return $this->fetch('transaction/bank_list_purchase');
    }

    /**
     * 选择银行卡
     */
    public function bankList_sell()
    {
        return $this->fetch('transaction/bank_list_sell');
    }


    /**
     * 添加银行卡
     */
    public function addBankCard()
    {
        $memberId = parent::get("account")['id'];
        if (IS_POST) {
            $userId = (int)I('memberid');
            $realName = trim(I('realname'));
            $mobile  = trim(I('mobile'));
            $no  = trim(I('no'));
            $bankid = (int)I('bankid');
            $branch = trim(I('branch'));
            $isdefault = trim(I('isdefault')) == 'true' ? 1 : 0;

            if ($memberId != $userId) $this->ajaxError("非法操作！");
            if (empty($realName)) $this->ajaxError("请填写持卡人姓名！");
            if (empty($no)) $this->ajaxError("请填写银行卡账号！");
            if (empty($mobile)) $this->ajaxError("请填写手机号码！");
            if (!isBankCard($no)) $this->ajaxError("请填写正确格式的银行卡号！");
            if (empty($bankid)) $this->ajaxError("请选择开户行！");
            if (empty($branch)) $this->ajaxError("请填写支行信息！");

            if (!empty(parent::get("LAST_ADD_BANKCARD")) && time() - parent::get("LAST_ADD_BANKCARD") <= 10) $this->ajaxError();
            parent::set("LAST_ADD_BANKCARD", time());


            $data = array();
            $data['memberId'] = $userId;
            $data['bankId'] = $bankid;
            $data['mobile'] = $mobile;
            $data['branch'] = $branch;
            $data['realName'] = $realName;
            $data['no'] = $no;
            Db::startTrans();
            if (1 == $isdefault) {
                $data['isDefault'] = 1;
                $step1 = M("bankcard")->where(array("memberId"=>$userId, "isDelete"=>0))->save(array("isDefault"=>0));
                if (false !== $step1) {
                    $step2 = M("bankcard")->add($data);
                    if ($step2) {
                        Db::commit();
                        $this->ajaxSuccess("添加成功！");
                    } else {
                        Db::rollback();
                        $this->ajaxError("添加失败！");
                    }
                } else {
                    Db::rollback();
                    $this->ajaxError("添加失败！");
                }
            } else if (0 == $isdefault) {
                $step = M("bankcard")->add($data);
                if ($step) {
                    Db::commit();
                    $this->ajaxSuccess("添加成功！");
                } else {
                    Db::rollback();
                    $this->ajaxError("添加失败！");
                }
            }
        } else {
            $type = I('type');
            //银行列表
            $banklist = M('bank')->select();
            $this->assign('banklist', $banklist);
            $this->assign('memberId', $memberId);
            $this->assign('type', $type);
            return $this->fetch('transaction/add_bankcard');
        }
    }

    /**
     * 买入记录
     */
    public function buyinLoglist()
    {
        if (IS_POST) {
            $page = (int)I('p') ? (int)I('p') : 0;
            $list = 20;
            $memberId = parent::get("account")['id'];
            $logs = M('transaction')
                ->alias("t")
                ->field("t.createTime,t.entrustNum,b.realName")
                ->join("bankcard b","t.buyerBankCardId=b.id",'LEFT')
                ->where(array("t.buyerId"=>$memberId, "t.status"=>4)) //状态4 已确认
                ->limit($page*$list, $list)
                ->order("t.id desc")
                ->select();
            $this->ajaxSuccess($logs);
        } else {
            return $this->fetch('transaction/buyin_loglist');
        }
    }

    /**
     * 未完成订单（买入）
     */
    public function unOrderList()
    {
        if (IS_POST) {
            $page = (int)I('p') ? (int)I('p') : 0;
            $list = 10;
            $memberId = parent::get("account")['id'];
            $orders = M('transaction')
                ->alias("t")
                ->join("member m","t.buyerId=m.id",'LEFT')
                ->field("t.*,m.profilephoto")
                ->where(array("t.buyerId"=>$memberId, "t.status"=>1))
                ->limit($page*$list, $list)
                ->order("t.id desc")
                ->select();
            $this->ajaxSuccess($orders);
        } else {
            $this->assign("src", 1);
            return $this->fetch('transaction/uncomplete_orderlist');
        }
    }

    /**
     * 确认打款订单（买入）
     */
    public function confirmOrderList()
    {
        if (IS_POST) {
            $page = (int)I('p') ? (int)I('p') : 0;
            $list = 10;
            $memberId = parent::get("account")['id'];
            $orders = M('transaction')
                ->alias("t")
                ->join("member m","t.buyerId=m.id","LEFT")
                ->field("t.*,m.profilephoto")
                ->where(array("t.buyerId"=>$memberId, "t.status"=>array("in", array(2,3))))
                ->limit($page*$list, $list)
                ->order("t.id desc")
                ->select();
            $this->ajaxSuccess($orders);
        } else {
            $this->assign("src", 2);
            return $this->fetch('transaction/confirm_orderlist');
        }
    }

    /**
     * 完成订单（买入）
     */
    public function completeOrderList()
    {
        if (IS_POST) {
            $page = (int)I('p') ? (int)I('p') : 0;
            $list = 10;
            $memberId = parent::get("account")['id'];
            $orders = M('transaction')
                ->alias("t")
                ->join("member m","t.buyerId=m.id",'LEFT')
                ->field("t.id,t.buyer,t.entrustNum,t.status,t.createTime,m.profilephoto")
                ->where(array("t.buyerId"=>$memberId, "t.status"=>4))
                ->limit($page*$list, $list)
                ->order("t.id desc")
                ->select();
            $this->ajaxSuccess($orders);
        } else {
            $this->assign("src", 3);
            return $this->fetch('transaction/complete_orderlist');
        }
    }


    /**
     * 确认打款
     */
    /*public function confirm_cashout()
    {
        if (IS_POST) {
            $id = (int)I('id');
            $memberId = parent::get("account")['id'];

            if (empty($id)) $this->ajaxError("参数错误！");
            $trans_log = M('transaction')
                ->where(array("id"=>$id,'buyerId'=>$memberId, 'type'=>1, 'status'=>2))
                ->find();
            if (empty($trans_log)) $this->ajaxError("非法操作！");

            $res = M('transaction')
                ->where(array("id"=>$id,'buyerId'=>$memberId, 'type'=>1, 'status'=>2))
                ->save(array("status"=>3));
            if ($res) {
                $this->ajaxSuccess("已确认打款！");
            } else {
                $this->ajaxError("操作失败！");
            }
        } else {
            $this->ajaxError("请求方式错误！");
        }
    }*/



    /**
     * 确认收款
     */
    public function confirm_cashin()
    {
        if (IS_POST) {
            $id = (int)I('id');
            $memberId = parent::get("account")['id'];

            if (empty($id)) $this->ajaxError("参数错误！");
            $trans_log = M('transaction')
                ->where(array("id"=>$id,'sellerId'=>$memberId, 'type'=>2, 'status'=>3))
                ->find();
            if (empty($trans_log)) $this->ajaxError("非法操作！");


            //防止连点
            if (!empty(parent::get("TRANSACTION_CONFIRM_CASHIN")) && time() - parent::get("TRANSACTION_CONFIRM_CASHIN") <= 10) {
                $this->ajaxError();
            }
            parent::set("TRANSACTION_CONFIRM_CASHIN", time());
            $system=tpCache("vpay_spstem");
            //更改状态
            Db::startTrans();
            $res = M('transaction')
                ->where(array("id"=>$id,'sellerId'=>$memberId, 'type'=>2, 'status'=>3))
                ->save(array("status"=>4));
            if ($res) {
                //余额给买方加上
                $buyer = M('member')->find($trans_log['buyerId']);
                $seller = M('member')->find($trans_log['sellerId']);
                if (empty($buyer) || $buyer['id'] == $memberId) {
                    $this->ajaxError('交易信息错误！');
                }
                //$add_balance = M('member')->where(array("id"=>$trans_log['buyerId']))->setInc("balance", $trans_log['entrustNum']);
                $release = $trans_log['entrustNum']-($trans_log['entrustNum']*$system['release']);
                //同时添加保证金(根据后台设置是否拆分余额比例)
                $add_balance = M('member')->where(array("id"=>$trans_log['buyerId']))->setInc("balance", (($trans_log['entrustNum']-$release)+C("TRANSACTION_BOND")));
                $add_integral = M('member')->where(array("id"=>$trans_log['buyerId']))->setInc("integral", $release);
                $add_integral = M('member')->where(array("id"=>$trans_log['sellerId']))->setInc("integral", $trans_log['entrustNum']*$system['integralSell']);
                if ($add_balance && $add_integral) {
                    //插入余额变动记录
                    $insert_log = balancelog($trans_log['id'], $trans_log['buyerId'], $trans_log['entrustNum']-$release, $type=3, $buyer['balance'], $buyer['balance']+$trans_log['entrustNum']-$release);
                    $tointegrallog=integrallog($trans_log['id'], $trans_log['buyerId'], $release,10, $buyer['integral'],$buyer['integral']+$release);//目标积分
                    $tointegrallog=integrallog($trans_log['id'], $trans_log['sellerId'], $trans_log['entrustNum']*$system['integralSell'],11, $seller['integral'],$seller['integral']+$trans_log['entrustNum']*$system['integralSell']);//目标积分

                    //余额增加，往上找15代返还
                    $balanceRelease=balanceRelease($buyer,$trans_log['entrustNum']-$release,$trans_log['id'],3);
                    $bond_log = balancelog($trans_log['id'], $trans_log['buyerId'], C("TRANSACTION_BOND"), $type=7, ($buyer['balance']+$trans_log['entrustNum']), ($buyer['balance']+$trans_log['entrustNum']+C("TRANSACTION_BOND")));
                    if ($insert_log && $bond_log && $balanceRelease) {
                        Db::commit();
                        $this->ajaxSuccess("已确认收款！");
                    } else {
                        Db::rollback();
                        $this->ajaxError("记录插入失败！");
                    }
                } else {
                    Db::rollback();
                    $this->ajaxError("买方加余额失败！");
                }
            } else {
                Db::rollback();
                $this->ajaxError("操作失败！");
            }
        } else {
            $this->ajaxError("请求方式错误！");
        }
    }

    /**
     * 卖出记录
     */
    public function selloutLoglist()
    {
        if (IS_POST) {
            $page = (int)I('p') ? (int)I('p') : 0;
            $list = 20;
            $memberId = parent::get("account")['id'];
            $logs = Db::name('transaction')
                ->alias("t")
                ->field("t.createTime,t.entrustNum,b.realName")
                ->join("bankcard b"," t.buyerBankCardId=b.id", 'LEFT')
                ->where(array("t.sellerId"=>$memberId, "t.status"=>4)) //状态4 已确认
                ->limit($page*$list, $list)
                ->order("t.id desc")
                ->select();
            // dump(Db::name('transaction')->getLastSql());
            // die;
            $this->ajaxSuccess($logs);
        } else {
            return $this->fetch('transaction/sellout_loglist');
        }
    }

    /**
     * 未完成订单（卖出）
*/
    public function unOrderListSell()
    {
        if (IS_POST) {
            $page = (int)I('p') ? (int)I('p') : 0;
            $list = 10;
            $memberId = parent::get("account")['id'];
            $orders = M('transaction')
                ->alias("t")
                ->join("member m","t.sellerId=m.id","LEFT")
                ->field("t.id,t.seller,t.entrustNum,t.status,t.createTime,m.profilephoto")
                ->where(array("t.sellerId"=>$memberId, "t.status"=>1))
                ->limit($page*$list, $list)
                ->order("t.id desc")
                ->select();
            $this->ajaxSuccess($orders);
        } else {
            $this->assign("src", 1);
            return $this->fetch('transaction/uncomplete_orderlist_sell');
        }
    }

    /**
     * 确认收款订单（卖出）
     */
    public function confirmOrderListSell()
    {
        if (IS_POST) {
            $page = (int)I('p') ? (int)I('p') : 0;
            $list = 10;
            $memberId = parent::get("account")['id'];
            $orders = M('transaction')
                ->alias("t")
                ->join("member m","t.sellerId=m.id","LEFT")
                ->field("t.*,m.profilephoto")
                ->where(array("t.sellerId"=>$memberId, "t.status"=>array("in", array(2,3))))
                ->limit($page*$list, $list)
                ->order("t.id desc")
                ->select();
            $this->ajaxSuccess($orders);
        } else {
            $this->assign("src", 2);
            return $this->fetch('transaction/confirm_orderlist_sell');
        }
    }

    /**
     * 完成订单（卖出）
     */
    public function completeOrderListSell()
    {
        if (IS_POST) {
            $page = (int)I('p') ? (int)I('p') : 0;
            $list = 10;
            $memberId = parent::get("account")['id'];
            $orders = M('transaction')
                ->alias("t")
                ->join("member m","t.sellerId=m.id","LEFT")
                ->field("t.id,t.seller,t.entrustNum,t.status,t.createTime,m.profilephoto")
                ->where(array("t.sellerId"=>$memberId, "t.status"=>4))
                ->limit($page*$list, $list)
                ->order("t.id desc")
                ->select();
            $this->ajaxSuccess($orders);
        } else {
            $this->assign("src", 3);
            return $this->fetch('transaction/complete_orderlist_sell');
        }
    }

    /**
     * 买入中心
     */
    public function buy_center()
    {
        if (IS_POST) {
            $num = (int)I('num');
            $page = (int)I('p') ? (int)I('p') : 0;
            $list = 10;
            $memberId = parent::get("account")['id'];
            $orders = M('transaction')
                ->alias("t")
                ->join("member m","t.sellerId=m.id",'LEFT')
                ->field("t.id,t.seller,t.entrustNum,t.status,t.createTime,m.profilephoto")
                ->where(array("t.sellerId"=>array("neq",$memberId),"entrustNum"=>$num, "t.status"=>1, "t.type"=>2)) //挂单  卖出
                ->limit($page*$list, $list)
                ->order("t.id desc")
                ->select();
            $this->ajaxSuccess($orders);
        } else {
            $bankcardid = (int)I('id');
            //卖出金额
            $transaction_num = C('TRANSACTION_NUM_SELL');

            $this->assign("bankcardid", $bankcardid);
            $this->assign("transaction_num", $transaction_num);
            return $this->fetch('transaction/buy_center');
        }
    }

    /**
     * 买入中心购买
     */
    public function center_buyin()
    {
        if (IS_POST) {
            $id = (int)I('id');
            $bankcardid = (int)I('bankcardid');

            $memberId = parent::get("account")['id'];
            $member = parent::get("account");

            if (empty($id) || empty($bankcardid)) $this->ajaxError("参数错误！");
            if ($member['balance'] < C("TRANSACTION_BOND")) $this->ajaxError("扣除保证金".C("TRANSACTION_BOND")."失败，请兑换余额。");

            $trans_info = M("transaction")
                ->alias("t")
                ->join("member m","t.sellerId=m.id",'LEFT')
                ->field("t.id,t.seller,t.entrustNum,t.status,t.createTime,m.profilephoto")
                ->where(array("t.sellerId"=>array("neq",$memberId), "t.status"=>1, "t.type"=>2))
                ->find();
            if (empty($trans_info)) $this->ajaxError("非法操作！");
            $bankcardinfo = M('bankcard')->where(array("id"=>$bankcardid, "isDelete"=>0))->find();
            if (empty($bankcardid) || $bankcardinfo['memberId'] != $memberId) $this->ajaxError("非法操作");

            Db::startTrans();
            //写入记录 变更状态
            $res = M('transaction')
                ->where(array("id"=>$id))
                ->save(array(
                    "buyer" => $bankcardinfo['realName'],
                    "buyerId" => $memberId,
                    "buyerBankCardId" => $bankcardid,
                    "status" => 3//直接改为状态为3，就是已打款，对方直接收款
                ));
            if(empty($res)){
                Db::rollback();
                $this->ajaxError("操作失败！");
            }

            //买方扣除保证金
            $dec_bond = M('member')->where(array("id"=>$memberId))->setDec("balance", C("TRANSACTION_BOND"));
            $add_log = balancelog($id, $memberId, -C("TRANSACTION_BOND"), $type=6, $before=$member['balance'], $after=$member['balance']-C("TRANSACTION_BOND"));
            if(empty($dec_bond) || empty($add_log)){
                Db::rollback();
                $this->ajaxError("操作失败！");
            }
            Db::commit();
            $this->ajaxSuccess("操作成功，请完成打款。");
        } else {
            $this->ajaxError("请求方式错误！");
        }
    }

    /**
     * 卖出中心
     */
    public function sell_center()
    {
        if (IS_POST) {
            $num = (int)I('num');
            $page = (int)I('p') ? (int)I('p') : 0;
            $list = 10;
            $memberId = parent::get("account")['id'];
            $orders = Db::name('transaction')
                ->alias("t")
                ->join("__MEMBER__ m ","t.buyerId=m.id", 'LEFT')
                ->field("t.id,t.buyer,t.entrustNum,t.status,t.createTime,m.profilephoto")
                ->where(array("t.buyerId"=>array("neq",$memberId),"entrustNum"=>$num, "t.status"=>1, "t.type"=>1)) //挂单  买入
                ->limit($page*$list, $list)
                ->order("t.id desc")
                ->select();
            $this->ajaxSuccess($orders);
        } else {
            $bankcardid = (int)I('id');
            //买入金额
            $transaction_num = C('TRANSACTION_NUM_BUY');

            $this->assign("bankcardid", $bankcardid);
            $this->assign("transaction_num", $transaction_num);
            return $this->fetch('transaction/sell_center');
        }
    }

    /**
     * 卖出中心卖出
     */
    public function center_sellout()
    {
        if (IS_POST) {
            $id = (int)I('id');
            $bankcardid = (int)I('bankcardid');

            $memberId = parent::get("account")['id'];
            $member = parent::get("account");
            if (empty($id) || empty($bankcardid)) $this->ajaxError("参数错误！");
            $trans_info = M("transaction")
                ->alias("t")
                ->join("member m","t.buyerId=m.id","LEFT")
                ->field("t.id,t.buyer,t.entrustNum,t.status,t.createTime,m.profilephoto")
                ->where(array("t.buyerId"=>array("neq",$memberId), "t.status"=>1, "t.type"=>1))
                ->find();
            if (empty($trans_info)) $this->ajaxError("非法操作！");
            if ($member['balance'] < $trans_info['entrustNum']) $this->ajaxError("扣除卖出余额失败，请兑换余额！");
            $bankcardinfo = M('bankcard')->where(array("id"=>$bankcardid, "isDelete"=>0))->find();
            if (empty($bankcardid) || $bankcardinfo['memberId'] != $memberId) $this->ajaxError("非法操作");

            //写入记录 变更状态
            $res = M('transaction')
                ->where(array("id"=>$id))
                ->save(array(
                    "seller" => $bankcardinfo['realName'],
                    "sellerId" => $memberId,
                    "sellerBankCardId" => $bankcardid,
                    "status" => 2,
                ));
            if ($res) {
                $this->ajaxSuccess("操作成功，请完成打款。");
            } else {
                $this->ajaxError("操作失败！");
            }
        } else {
            $this->ajaxError("请求方式错误！");
        }
    }

    /**
     * 确认打款
     */
    public function confirm_cashout()
    {
        if (IS_POST) {
            $id = (int)I('id');
            $memberId = parent::get("account")['id'];

            if (empty($id)) $this->ajaxError("参数错误！");
            $trans_log = M('transaction')
                ->where(array("id"=>$id,'buyerId'=>$memberId, 'type'=>1, 'status'=>2))
                ->find();
            if (empty($trans_log)) $this->ajaxError("非法操作！");

            $res = M('transaction')
                ->where(array("id"=>$id,'buyerId'=>$memberId, 'type'=>1, 'status'=>2))
                ->save(array("status"=>3));
            if ($res) {
                $this->ajaxSuccess("已确认打款！");
            } else {
                $this->ajaxError("操作失败！");
            }
        } else {
            $this->ajaxError("请求方式错误！");
        }
    }


    /**
     * 确认收款(卖出中心)
     */
    public function confirm_cashin_sell()
    {
        if (IS_POST) {
            $id = (int)I('id');
            $memberId = parent::get("account")['id'];

            if (empty($id)) $this->ajaxError("参数错误！");
            $trans_log = M('transaction')
                ->where(array("id"=>$id,'sellerId'=>$memberId, 'type'=>1, 'status'=>3))
                ->find();
            if (empty($trans_log)) $this->ajaxError("非法操作！");


            //防止连点
            if (!empty(parent::get("TRANSACTION_CONFIRM_CASHIN_SELL")) && time() - parent::get("TRANSACTION_CONFIRM_CASHIN_SELL") <= 10) {
                $this->ajaxError();
            }
            parent::set("TRANSACTION_CONFIRM_CASHIN_SELL", time());
            $system=tpCache("vpay_spstem");
            //更改状态
            Db::startTrans();
            $res = M('transaction')
                ->where(array("id"=>$id,'sellerId'=>$memberId, 'type'=>1, 'status'=>3))
                ->save(array("status"=>4));
            if ($res) {
                //余额给买方加上
                $buyer  = M('member')->find($trans_log['buyerId']);
                $seller = M('member')->find($trans_log['sellerId']);

                if (empty($buyer) || empty($seller) || $buyer['id'] == $memberId) {
                    $this->ajaxError('交易信息错误！');
                }
                $release2 = $trans_log['entrustNum'] - ($trans_log['entrustNum']*$system['release2']);
                //添加余额同时添加保证金
                $add_balance = M('member')->where(array("id"=>$trans_log['buyerId']))->setInc("balance", (($trans_log['entrustNum']-$release2)+C("TRANSACTION_BOND")));
                $add_integral = M('member')->where(array("id"=>$trans_log['buyerId']))->setInc("integral", $release2);
                //减去卖方余额
                $add_balance = M('member')->where(array("id"=>$trans_log['sellerId']))->setDec("balance", $trans_log['entrustNum']);
                $add_integral = M('member')->where(array("id"=>$trans_log['sellerId']))->setInc("integral", $trans_log['entrustNum']*$system['integralSell2']);
                if ($add_balance && $add_integral) {
                    //插入余额变动记录
                    $insert_log = balancelog($trans_log['id'], $trans_log['buyerId'], $trans_log['entrustNum']-$release2, $type=3, $buyer['balance'], $buyer['balance']+$trans_log['entrustNum']-$release2);
                    $insert_log = integrallog($trans_log['id'], $trans_log['buyerId'],$release2, $type=12, $buyer['integral'], $buyer['integral']+$release2);
                    $bond_log = balancelog($trans_log['id'], $trans_log['buyerId'], C("TRANSACTION_BOND"), $type=7, ($buyer['balance']+$trans_log['entrustNum']), ($buyer['balance']+$trans_log['entrustNum']+C("TRANSACTION_BOND")));

                    $insert_seller_log = balancelog($trans_log['id'], $trans_log['sellerId'], -$trans_log['entrustNum'], $type=4, $seller['balance'], $seller['balance']-$trans_log['entrustNum']);
                    $insert_seller_log = integrallog($trans_log['id'], $trans_log['sellerId'],$trans_log['entrustNum']*$system['integralSell2'], $type=13, $seller['integral'], $seller['integral']+$trans_log['entrustNum']*$system['integralSell2']);

                    //余额增加，往上找15代返还
                    //拿比例后的价格作为释放金额
                    $balanceRelease=balanceRelease($buyer,$trans_log['entrustNum']*$system['release2'],$trans_log['id'],4);
                    if ($insert_log && $bond_log && $balanceRelease && $insert_seller_log) {
                        Db::commit();
                        $this->ajaxSuccess("已确认收款！");
                    } else {
                        Db::rollback();
                        $this->ajaxError("记录插入失败！");
                    }
                } else {
                    Db::rollback();
                    $this->ajaxError("买方加余额失败！");
                }

            } else {
                Db::rollback();
                $this->ajaxError("操作失败！");
            }
        } else {
            $this->ajaxError("请求方式错误！");
        }
    }

    /**
     * 取消卖出订单
     */
    public function cancelOrderSell()
    {
        if (IS_POST) {
            $id = (int)I('id');
            $memberId = parent::get('account')['id'];
            $member = parent::get('account');
            if (empty($id)) $this->ajaxError("参数错误！");
            $transaction = M('transaction')->where(array("id"=>$id, "type"=>2, "sellerId"=>$memberId))->find();
            if (empty($transaction)) $this->ajaxError("订单信息不存在！");

            //防止连点
            if (!empty(parent::get("TRANSACTION_SELL_CANCELORDER")) && time() - parent::get("TRANSACTION_SELL_CANCELORDER") <= 10) {
                $this->ajaxError();
            }
            parent::set("TRANSACTION_SELL_CANCELORDER", time());


            $system=tpCache("vpay_spstem");
            $poundage = $system['poundage'] ? $system['poundage'] : 0;//手续费
            Db::startTrans();
            //①更改订单状态
            $update_order = M('transaction')->where(array("id"=>$id, "type"=>2, "sellerId"=>$memberId))->save(array("status"=>5));
            if ($update_order) {
                //②回加卖出金额
                $add_balan = M('member')->where(array("id"=>$memberId))->setInc("balance", $transaction['entrustNum']+($transaction['entrustNum']*$poundage));
                $add_log = balancelog($id, $memberId, $transaction['entrustNum'], $type=15, $before=$member['balance'], $after=$member['balance']+$transaction['entrustNum']);
                $add_log = balancelog($id, $memberId, $transaction['entrustNum']*$poundage, $type=19, $before=$member['balance']+$transaction['entrustNum'], $after=$member['balance']+$transaction['entrustNum']+($transaction['entrustNum']*$poundage));
                if(empty($add_log) || empty($add_balan)){
                    Db::rollback();
                    $this->ajaxError("回加金额失败！");
                }
                Db::commit();
                $this->ajaxSuccess("取消成功！");
            } else {
                Db::rollback();
                $this->ajaxError("订单状态更新错误！");
            }

        } else {
            $this->ajaxError("请求错误！");
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
            if(empty($memberId)){
                $this->ajaxError("未登录！");
            }
            $trans=M("transaction")->where(array("id"=>$id))->find();
            if(empty($trans)){
                $this->ajaxError("参数错误！");
            }
            if($memberId==$trans['buyerId']){
                $data=M("transaction")
                    ->alias("t")
                    ->join("member m","t.sellerId=m.id",'left')
                    ->join("bankcard b","t.sellerBankCardId=b.id",'left')
                    ->join("bank k","b.bankId=k.bankId",'left')
                    ->field("m.id,m.account,k.bankName,b.realName,b.no")
                    ->where(array("t.id"=>$id))
                    ->find();
            }else{
                $data=M("transaction")
                    ->alias("t")
                    ->join("member m","t.buyerId=m.id",'left')
                    ->join("bankcard b","t.buyerBankCardId=b.id",'left')
                    ->join("bank k","b.bankId=k.bankId",'left')
                    ->field("m.id,m.account,k.bankName,b.realName,b.no")
                    ->where(array("t.id"=>$id))
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



