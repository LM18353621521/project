<?php
namespace Home\Controller;

use Think\Controller;

class PayController extends BaseController
{
    /*进入主页面*/
    public function index()
    {
        $this->display("pay");
    }

    // TODO 支付测试
    //支付回调
    public function notify()
    {
        $id = I('orderid');
        // 订单ID为空
        if (empty($id)) {
            $this->ajaxError("订单ID为空");
        }

        // 检索订单
        $onlineOrder = M('onlineorder')
            ->lock(true)
            ->where(array('id' => $id, 'status' => 1))
            ->find();

        if (empty($onlineOrder)) {
            $this->ajaxError('订单为空');
        }

        $member = M('member')->where(array("id"=>$onlineOrder['sourceid'],"isDelete"=>2,"isDisable"=>2))->find();
        if(empty($member)){
            $this->ajaxError("会员账号不存在或被冻结");
        }

        if($onlineOrder['integral'] > $member['integral'] && $onlineOrder['integral']>0){
            $this->ajaxError("积分不足，无法支付");
        }

        M()->startTrans();
        try {
            $truepay = $onlineOrder['total'] - $onlineOrder['integral'];

            // 付款记录
            $pay = array(
                "sourceId" => $id,
                "sourceType" => 1,
                "type" => 1,
                "status" => 2,
                "moneyType" => 2,
                "payment" => "会员订单在线支付",
                "param" => "实付款:".$truepay.",使用积分:".$onlineOrder['integral'],
                'createTime' => date("Y-m-d H:i:s"),
                "updateTime" => date("Y-m-d H:i:s")
            );
            $payLog = M("pay")->add($pay);

            if (!$payLog) {
                M()->rollback();
                $this->ajaxError("新增付款记录失败");
            }

            // 检索会员
            $member = M('member')
                ->lock(true)
                ->where(array("id" => $onlineOrder['sourceid']))
                ->find();

            // 更新订单状态
            $up = M('onlineorder')->where(array("id" => $id))->setField(array(
                "status" => 2,
                "payType" => 1 //微信
            ));

            if (!$up) {
                M()->rollback();
                $this->ajaxError("支付更新订单失败");
            }

            // 更新付款记录
            $payup = M('pay')->where(array(
                'sourceId' => $id
            ))->setField(array(
                'status' => '1'
            ));

            if (!$payup) {
                M()->rollback();
                $this->ajaxError("更新付款记录失败");
            }

            // 资金记录-积分变更
            $ointegral = $onlineOrder['integral'];
            if($ointegral > 0){
                $mintegral = $member ['integral'];
                //更新会员积分
                $res = M('member')->where(array("id" => $member['id']))->setField(array(
                    "integral" => array('exp', $member ['integral'] - $ointegral)
                ));

                if ($res) {
                    $momeyLog = array(
                        "accountId" => $onlineOrder['sourceid'],
                        "accountType" => 1,
                        "sourceId" => $id,
                        "sourceType" => 1,
                        "type" => 2,
                        "moneyType" => 1,
                        "beforeMoney" => $mintegral,
                        "changeMoney" => $ointegral,
                        'afterMoney' => $mintegral - $ointegral,
                        "createTime" => date("Y-m-d H:i:s"),
                        "info" => "会员订单支付扣除积分"
                    );
                    $momeyLogId = M("moneylog")->add($momeyLog);
                    if ($momeyLogId) {
                        //清空order
                        parent::remove('order');
                        M()->commit();
                        $this->ajaxSuccess('支付完成');
                    } else {
                        M()->rollback();
                        $this->ajaxError('未能保存资金记录');
                    }
                } else {
                    M()->rollback();
                    $this->ajaxError('会员扣除积分失败');
                }
            }
        } catch (\Exception $_e) {
            $ex = $_e;
            M()->rollback();
            $this->ajaxError($ex);
        }
        M()->commit();
        $this->ajaxSuccess('支付完成');
    }

    //获取二维码数据
    public function getPayQrcode(){
        $orderid = I('orderid');
        $qrcode = parent::getQrcode($orderid);
        if($qrcode == false){
            $this->ajaxError("订单不存在");
        }
        $this->ajaxSuccess($qrcode);
    }

    //获取统一下单->Jsapi数据
    public function getPayJsapi(){
        $orderid = I('orderid');
        $qrcode = parent::getJsapi($orderid);
        if($qrcode == false){
            $this->ajaxError("openId或订单不存在");
        }
        $this->ajaxSuccess($qrcode);
    }
}