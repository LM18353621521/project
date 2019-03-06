<?php
namespace Home\Controller;

use Think\Controller;

class PayAliAppController extends BaseController
{
    //APPID
    private $appId = '2017071107711557';
    //私钥
    private $rsaPrivateKey = 'MIIEowIBAAKCAQEAlzrdwS7XC1HZRVKfo0SSs2bVh6f1hbarEn3Q65wtQMSZ6Gqlgvwt/R6pxCLItE9WnixXNO+5WY/i3IVtHxV2b+9IWU4jKzQ+QYSqaMyPh4JoMuM1P/K71h4IdBphdqf3BXPM19Ng9HCMr+COR3jGtVMjMJLUI50w0DwRM76/QLZZ9uvfaA7BNNPsefd4BpRZ83D03za9bfhdsUVASiMzFbXXeSJBrhqv3YJhA3CHIRJoRQJLewkye6fdtlooS75PBkfzsXGeyRSJ9aeLocSm5zvGod9awkHB/3h5u0nO7eGdPw2e8NuOIv2mlC0IjWXpwO04ytFI08N3kAeniDEkZQIDAQABAoIBACcIP4ID5+b5Ch31VFScd0yshwJLXHhVjFPqe0jEd32XAK5XED79fZUuG90OqUS4kX+jrCJymSE/nOsT2PVD4dzEIqVCIJufEU5xwlXoLkdoZiJ0OCM4MDj0aXQl9u/cLEqQ99bgrM6KWhVu3OofhxH30kZQL0a95IJqbnovikdWI9F1falVgY6RHoq84k+mNxUvAc1NoiwrS0KczMbqtXvt/HBllaV/DuLlrg76lLfG/JDkxZlxv+Wbf1kKmlKmchzyYG+PvP76cWMxPCCucRWjvTTR3I0Sb9336X8LIke52MMiluAngdVgfjNtHzXnQ8WgVyUkyRfGKYQoTgFStgUCgYEAxpGMeUbxijKlbQ+Vteoa07M9R3RJAAfbk1h47sy5U6DsZGKpPeJSxJLWRoGsHvkP56NAIw6Jp/6AGDQIyuj0Zc0wB3bU5dbeRh6kAWvPweoenAWXOz9JLI0QcUehg/03W71vPizWPu1byCgx2+StAOjmLT7GQi3oT3H7GHqFsNcCgYEAwvhDbbbh70j4d0Z91P2yxGMSBL0xdaJTWAHVmuGgeNmLltwk3crFegaal/Y/bLTTWnMIev6OUnxgpctMD+x4gZN4IIEi5X3CsUSQIm9OyCQiIgVQaUNUpdi4CsSeDpIaUcazywtEpcAT5gVCKSXBneZEcv/HEYlHPi27KbvL4SMCgYEAwpM/HlvpNa15MoxR/GdBEG8Tvh/xpIkOnazVG9MaSxtmaNvQ0WYkCqGEPKS2X8dY0XfD0lZdh3O4W38pmoN5cQQGa1oDNpE9T2KY/ReDBpZ+lg5YaeMStggos4goeei3xTq0di2DZzg5dsIEUWAcMscFPhLEPXc0rByZmxv8QxMCgYAU5WbUq3UroDaBEh0KZuZyBew4dc6HPQ6RsCCkqOn6CdbcJFwPKVxg57RJ9Sp3DCpa11lhVUcLsCjrnA5a5o1D1fpaAX0r+36SYTbRefyHltfRraAgqAa6f6+597i49w+7FADREjQZT6zSSl386v8FXViYurErP/tSvrQAlRAU4QKBgCXrk8wlnCqe/7cKFfjJ/ldxWlbvmjKGiZDnqKiYok5W9FOYNbvhLIBvRkxbjNh/iDCXKxLINKmF6rm+pRLzcNN0UxW8Igh4GTl1XanGJ1b9Pi2dE9SS92uPLH7IgqwwPN1hYTeSteZV8wxsfuq87wrn0tbofKeYemOMS4d2M/4g';
    //支付宝公钥
    private $alipayrsaPublicKey = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAjaQ6+N04lOnhZLT2mPH955ymyEzoLjDTrGrFAb357J0B4dv45KAtPod/zn6kD3OqR2dNvqNkctsLQv965XJE6wREIkb3O2lnx5rtKUKZd+5Bbci8Ep1Ah5lh8HCMWYLgZ7AIOxOl7JNzMHz2f7x/tuvkL/8rXDRRO920lJNx3iOYLRWhzNi/ZQrm5sN5Gzg2BRHK7Coaoo0UJizbdLzPhPxPaYVcKfHt7TAS0xCJfgjqWGuWK6IoZMT3REMQHT5W3VO3rLiUxr14Thb5J9qY8fY759QF/V84uWXf7zIuF0sezjYDZzqfT0aknjWaoGzRcam8uTCTrx0PUF7t3Kwc6wIDAQAB';
    //请求URL
    //private $gatewayUrl = "https://openapi.alipaydev.com/gateway.do";
    private $gatewayUrl = "https://openapi.alipay.com/gateway.do";
    //签名方式
    private $signType = "RSA2";


    /**
     * 获取APP支付数据
     * @param $orderid 订单id
     */
    public function getAppPay()
    {
        $orderid = I("post.orderid");
        $order = M('onlineorder')
            ->where("id = $orderid")
            ->find();
        if (empty($order)) {
            $this->ajaxError("订单不存在");
        }
        $truepay = $this->ordercheck($order);
        $sn = $order['sn'];
        $amount = $truepay;

        $bizcontent = json_encode([
            'body' => '心体荟-购买商品',
            'subject' => '乐荟云商',
            'out_trade_no' => $sn,//此订单号为商户唯一订单号
            'total_amount' => $amount,//保留两位小数
            'product_code' => 'QUICK_MSECURITY_PAY'
        ]);

        require_once('alipayapp/aop/AopClient.php');
        require_once('alipayapp/aop/request/AlipayTradeAppPayRequest.php');
        $aop = new \AopClient();

        //沙箱测试支付宝开始
        $aop->gatewayUrl = $this->gatewayUrl;

        //实际上线app id需真实的
        $aop->appId = $this->appId;
        $aop->rsaPrivateKey = $this->rsaPrivateKey;
        $aop->format = "json";
        $aop->charset = "UTF-8";
        $aop->signType = $this->signType;
        //**沙箱测试支付宝结束
        //实例化具体API对应的request类,类名称和接口名称对应,当前调用接口名称：alipay.trade.app.pay
        $request = new \AlipayTradeAppPayRequest();
        //支付宝回调地址
        $request->setNotifyUrl("http://".$_SERVER['HTTP_HOST']."/index.php/Home/PayAliApp/notify");
        $request->setBizContent($bizcontent);
        //这里和普通的接口调用不同，使用的是sdkExecute
        $response = $aop->sdkExecute($request);
        $this->ajaxSuccess($response);
        /* //返回值转JSON-方便查看-实际上无需转换
        $response = urldecode($response);
        $result = $this->urlParaToJson($response);

        $bct = $result['biz_content'];
        $bct = json_decode($bct);

        $result['biz_content'] = $bct;
        $this->ajaxSuccess($result);*/
    }

    /**
     * 支付宝支付回调
     */
    public function notify()
    {
        require_once('alipayapp/aop/AopClient.php');
        $aop = new \AopClient;
        $aop->alipayrsaPublicKey = $this->alipayrsaPublicKey;
        //此处验签方式必须与下单时的签名方式一致
        $flag = $aop->rsaCheckV1($_POST, NULL, $this->signType);
        //验签通过后再实现业务逻辑，比如修改订单表中的支付状态。
        /**
         * ①验签通过后核实如下参数out_trade_no、total_amount、seller_id
         * ②修改订单表
         **/
        //打印success，应答支付宝。必须保证本界面无错误。只打印了success，否则支付宝将重复请求回调地址。

        if ($flag) {
            //状态 TRADE_SUCCESS
            $status = $_POST['trade_status'];
            if ($status == "TRADE_SUCCESS") {
                //订单sn
                $sn = $_POST['out_trade_no'];
                /*//卖家支付宝id
                $sellerid = $_POST['seller_id'];
                //金额
                $tamount = $_POST['total_amount'];
                //实收金额
                $ramount = $_POST['receipt_amount'];*/

                if (empty($sn)) {
                    $this->ajaxError("订单不存在");
                }

                // 检索订单
                $onlineOrder = M('onlineorder')
                    ->lock(true)
                    ->where(array('sn' => $sn, 'status' => 1))
                    ->find();

                if (empty($onlineOrder)) {
                    $this->ajaxError('订单为空');
                }

                $id = $onlineOrder['id'];

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
                        "param" => "实付款:" . $truepay . ",使用积分:" . $onlineOrder['integral'],
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
                        "payType" => 2 //支付宝
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
                    if ($ointegral > 0) {
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
                                //推送消息
                                $member = M('member')->where(array('id' => $onlineOrder['sourceid']))->find();
                                $agen = M('agen')->where(array('id' => $onlineOrder['toagenid']))->find();
                                if ($agen && $agen['pushtag'] != null) {
                                    send_pub($agen['pushtag'], "尊敬的：" . $agen['account'] . "，会员" . $member['account'] . "给您下单了，请注意及时发货哟");
                                }
                                //$this->ajaxSuccess('支付完成');
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
                //通知支付宝处理成功
                echo 'success';
            }
        } else {
            $this->ajaxError("验签失败");
        }
    }

    /**
     * 查询订单信息
     * @param $out_trade_no     订单号
     * @return array
     */
    public function orderQuery()
    {
        $out_trade_no = I('post.reqsn');

        require_once('alipayapp/aop/AopClient.php');
        require_once('alipayapp/aop/request/AlipayTradeQueryRequest.php');
        $aop = new \AopClient();
        $aop->gatewayUrl = $this->gatewayUrl;
        $aop->appId = $this->appId;
        $aop->rsaPrivateKey = $this->rsaPrivateKey;
        $aop->alipayrsaPublicKey = $this->alipayrsaPublicKey;
        $aop->signType = $this->signType;
        $request = new \AlipayTradeQueryRequest();
        $bizcontent = json_encode([
            'out_trade_no' => $out_trade_no,//此订单号为商户唯一订单号
            'trade_no' => ''
        ]);
        $request->setBizContent($bizcontent);

        $result = $aop->execute($request);

        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $resultCode = $result->$responseNode->code;
        if (!empty($resultCode) && $resultCode == 10000) {
            $this->ajaxSuccess("支付成功");
            //echo "成功";
        } else {
            $this->ajaxError("交易处理中");
            //echo "失败";
        }
    }

    /**
     * 验证订单信息
     * @param $orderid 订单号
     * @return 实际支付金额
     */
    public function ordercheck($order)
    {
        $member = M('member')->where(array("id" => $order['sourceid'], "isDelete" => 2, "isDisable" => 2))->find();
        if (empty($member)) {
            $this->ajaxError("会员账号不存在或被冻结");
        }

        if ($order['integral'] > $member['integral'] && $order['integral'] > 0) {
            $this->ajaxError("积分不足，无法支付");
        }

        $truepay = $order['total'] - $order['integral'];
        return $truepay;
    }

    /**
     * 产生一个指定长度的随机字符串,并返回给用户
     * @param type $len 产生字符串的长度
     * @return string 随机字符串
     */
    private function getRandomString($len = 32)
    {
        $chars = array(
            "a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k",
            "l", "m", "n", "o", "p", "q", "r", "s", "t", "u", "v",
            "w", "x", "y", "z", "A", "B", "C", "D", "E", "F", "G",
            "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R",
            "S", "T", "U", "V", "W", "X", "Y", "Z", "0", "1", "2",
            "3", "4", "5", "6", "7", "8", "9"
        );
        $charsLen = count($chars) - 1;
        // 将数组打乱
        shuffle($chars);
        $output = "";
        for ($i = 0; $i < $len; $i++) {
            $output .= $chars[mt_rand(0, $charsLen)];
        }
        return $output;
    }

    public function urlParaToJson($para)
    {
        $strs = explode("&", $para);
        $result = array();
        for ($i = 0; $i < sizeof($strs); $i++) {
            $temp = explode("=", $strs[$i]);
            if (sizeof($temp) < 2) {
                $result[$temp[0]] = '';
            } else {
                $result[$temp[0]] = $temp[1];
            }
        }
        return $result;
    }
}
