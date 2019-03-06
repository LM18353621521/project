<?php
namespace Home\Controller;

use Think\Controller;

class PayWxController extends BaseController
{
    //接口API URL前缀
    const API_URL_PREFIX = 'https://api.mch.weixin.qq.com';
    //下单地址URL
    const UNIFIEDORDER_URL = "/pay/unifiedorder";
    //查询订单URL
    const ORDERQUERY_URL = "/pay/orderquery";
    //关闭订单URL
    const CLOSEORDER_URL = "/pay/closeorder";

    //curl代理设置，只有需要代理的时候才设置，不需要代理，设置为0.0.0.0和0
    const CURL_PROXY_HOST = "0.0.0.0";
    const CURL_PROXY_PORT = 0;

    //公众账号ID
    private $wxappid = 'wx1d8a9b06a78204b7';
    //商户号
    private $mch_id = '1482185502';
    //支付密钥
    private $key = 'NM26I8ZVS4ILTKC2502SH16CQTM675K8';
    //APPSECRET
    private $secret = 'c04504581fbb5aa4ab7a6d91b42b075a';
    //支付回调路径
    private $notify_url = "http://www.lehmall.com/index.php/Home/PayWx/notify";
    //证书路径-不需要
    //private $SSLCERT_PATH = '__ROOT__/wxpay/cert/apiclient_cert.pem';
    //private $SSLKEY_PATH = '__ROOT__/wxpay/cert/apiclient_key.pem';

    /**
     * 属性赋值为APP的
     */
    public function setAppConfig()
    {
        $this->wxappid = "wxa4ab2adb1a8934e4";
        $this->mch_id = "1485602182";
        $this->key = "e81a4b4624e5e0c05018805c3739fb46";
        $this->secret = "2ba002adc916888ce5c4558e2d7736f4";
        $this->notify_url = "http://www.lehmall.com/index.php/Home/PayWx/appNotify";
    }

    /**
     * 微信统一下单
     * @param $params 下单参数
     */
    public function unifiedOrder($params)
    {
        //trade_type:JSAPI--公众号支付、NATIVE--原生扫码支付、APP--app支付
        $out_trade_no = $params['out_trade_no'];
        $total_fee = $params['total_fee'];
        $trade_type = $params['trade_type'];
        $nonce_str = $this->getRandomString();
        $openid = $params['openid'];

        $params['appid'] = $this->wxappid;//公众账号ID
        $params['mch_id'] = $this->mch_id;//商户号
        $params['nonce_str'] = $nonce_str;//随机串
        $params['spbill_create_ip'] = $_SERVER['REMOTE_ADDR'];//终端ip
        $params['body'] = "心体荟-购买商品";
        $params['attach'] = "乐荟云商";
        $params['out_trade_no'] = $out_trade_no;//订单号
        $params['total_fee'] = $total_fee;//金额
        $params['notify_url'] = $this->notify_url;
        $params['trade_type'] = $trade_type;
        if (!empty($openid) && $trade_type == 'JSAPI') {
            $params['openid'] = $openid;
        }

        //获取签名数据
        $sign = $this->MakeSign($params);
        $params['sign'] = $sign;
        $xml = $this->data_to_xml($params);
        $response = $this->postXmlCurl($xml, self::API_URL_PREFIX . self::UNIFIEDORDER_URL);
        if (!$response) {
            return false;
        }
        $result = $this->xml_to_data($response);
        if (!empty($result['result_code']) && !empty($result['err_code'])) {
            $result['err_msg'] = $this->error_code($result['err_code']);
        }
        return $result;
    }

    /**
     * 获取支付二维码
     * @param $orderid 订单id
     */
    public function getQrcode()
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
        $params = array();
        $params['out_trade_no'] = $sn; //必填项 自定义的订单号
        $params['total_fee'] = $amount * 100; //必填项 订单金额 单位为分所以要*100
        $params['trade_type'] = 'NATIVE'; //必填项 交易类型
        $result = $this->unifiedOrder($params);

        //验签
        if ($this->validSign($result)) {
            $obj['status'] = 'success';
            $obj['price'] = $amount;
            $obj['code'] = $result["code_url"];
            $obj['sn'] = $sn;
            $this->ajaxSuccess($obj);
        } else {
            $this->ajaxError("验签失败！");
        }
    }

    /**
     * 获取JSAPI支付信息
     * @param $orderid 订单id
     */
    public function getJsapi()
    {
        $openid = $this->get('account')['openid'];
        if (empty($openid)) {
            $this->ajaxError("用户未绑定微信");
        }
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
        $params = array();
        $params['out_trade_no'] = $sn; //必填项 自定义的订单号
        $params['total_fee'] = $amount * 100; //必填项 订单金额 单位为分所以要*100
        $params['trade_type'] = 'JSAPI'; //必填项 交易类型
        $params['openid'] = $openid; //JSAPI需要填写
        $result = $this->unifiedOrder($params);

        //验签
        if ($this->validSign($result)) {
            $obj = $this->getJsApiParameters($result);
            $this->ajaxSuccess($obj);
        } else {
            $this->ajaxError("验签失败！");
        }
    }

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
        //属性赋值为APP的
        $this->setAppConfig();
        //同一订单信息
        $truepay = $this->ordercheck($order);
        $sn = $order['sn'];
        $amount = $truepay;
        $params = array();
        $params['out_trade_no'] = $sn; //必填项 自定义的订单号
        $params['total_fee'] = $amount * 100; //必填项 订单金额 单位为分所以要*100
        $params['trade_type'] = 'APP'; //必填项 交易类型
        $result = $this->unifiedOrder($params);

        //验签
        if ($this->validSign($result)) {
            $params = array();
            $params['appid'] = $this->wxappid;
            $params['partnerid'] = $this->mch_id;
            $params['prepayid'] = $result['prepay_id'];
            $params['package'] = 'Sign=WXPay';
            $params['noncestr'] = $this->getRandomString();
            $params['timestamp'] = time();
            $params['sign'] = $this->MakeSign($params);

            $obj = json_encode($params);

            $result['nonceStr'] = $params['noncestr'];
            $result['timeStamp'] = $params['timestamp'];
            $result['sign'] = $params['sign'];
            //$obj = $this->getAppPayParameters($result);
            $this->ajaxSuccess(array('android'=>$obj,'ios'=>$result));
        } else {
            $this->ajaxError("验签失败！");
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
     * 查询订单信息
     * @param $out_trade_no     订单号
     * @return array
     */
    public function orderQuery()
    {
        $out_trade_no = I('post.reqsn');
        $params = array();
        $params['appid'] = $this->wxappid;
        $params['mch_id'] = $this->mch_id;
        $params['nonce_str'] = $this->getRandomString();
        $params['out_trade_no'] = $out_trade_no;
        //获取签名数据
        $sign = $this->MakeSign($params);
        $params['sign'] = $sign;
        $xml = $this->data_to_xml($params);
        $response = $this->postXmlCurl($xml, self::API_URL_PREFIX . self::ORDERQUERY_URL);
        if (!$response) {
            $this->ajaxError("请求数据失败");
        }
        $result = $this->xml_to_data($response);
        //验签
        if ($this->validSign($result)) {
            if (!empty($result['result_code']) && !empty($result['err_code'])) {
                $result['err_msg'] = $this->error_code($result['err_code']);
            }
            $this->ajaxSuccess($result);
        } else {
            $this->ajaxError("验签失败！");
        }
    }

    /**
     * APP查询订单信息
     * @param $out_trade_no     订单号
     * @return array
     */
    public function appOrderQuery()
    {
        //属性赋值为APP的
        $this->setAppConfig();

        $out_trade_no = I('post.reqsn');
        $params = array();
        $params['appid'] = $this->wxappid;
        $params['mch_id'] = $this->mch_id;
        $params['nonce_str'] = $this->getRandomString();
        $params['out_trade_no'] = $out_trade_no;
        //获取签名数据
        $sign = $this->MakeSign($params);
        $params['sign'] = $sign;
        $xml = $this->data_to_xml($params);
        $response = $this->postXmlCurl($xml, self::API_URL_PREFIX . self::ORDERQUERY_URL);
        if (!$response) {
            $this->ajaxError("请求数据失败");
        }
        $result = $this->xml_to_data($response);
        //验签
        if ($this->validSign($result)) {
            if (!empty($result['result_code']) && !empty($result['err_code'])) {
                $result['err_msg'] = $this->error_code($result['err_code']);
            }
            if($result['result_code'] == "SUCCESS"){
                $this->ajaxSuccess("支付成功");
            } else {
                $this->ajaxError("交易处理中");
            }
        } else {
            $this->ajaxError("验签失败！");
        }
    }

    /**
     * 支付回调
     */
    public function notify()
    {
        //获取通知的数据
        $xml = $GLOBALS['HTTP_RAW_POST_DATA'];
        if (!$xml) {
            $this->ajaxError("xml数据异常");
        }
        //将XML转为array
        $params = $this->xml_to_data($xml);
        //存入数组
        foreach ($params as $key => $val) {
            $params[$key] = $val;
            //file_put_contents("C:/tmp.txt", $key.':'.$val.PHP_EOL, FILE_APPEND);
        }
        //参数为空,不进行处理
        if (count($params) < 1) {
            $this->ajaxError("error");
        }
        //记录微信回调通知
        M('wxnotifylog')->add(
            array(
                "attach" => $params["attach"],
                "bank_type" => $params["bank_type"],
                "cash_fee" => $params["cash_fee"],
                "fee_type" => $params["fee_type"],
                "err_code" => $params["err_code"],
                "err_code_des" => $params["err_code_des"],
                "nonce_str" => $params["nonce_str"],
                "openid" => $params["openid"],
                "out_trade_no" => $params["out_trade_no"],
                "result_code" => $params["result_code"],
                "return_code" => $params["return_code"],
                "sign" => $params["sign"],
                "time_end" => $params["time_end"],
                "total_fee" => $params["total_fee"],
                "trade_type" => $params["trade_type"],
                "transaction_id" => $params["transaction_id"]
            )
        );
        //验签
        if ($this->validSign($params)) {
            //返回状态为支付成功
            if ($params["result_code"] == 'SUCCESS') {
                // 订单sn
                $sn = $params['out_trade_no'];

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
                //通知微信处理成功
                $this->replyNotify();
            }
        } else {
            $this->ajaxError("验签失败");
        }
    }

    /**
     * APP支付回调
     */
    public function appNotify()
    {
        //获取通知的数据
        $xml = $GLOBALS['HTTP_RAW_POST_DATA'];
        if (!$xml) {
            $this->ajaxError("xml数据异常");
        }
        //将XML转为array
        $params = $this->xml_to_data($xml);
        //存入数组
        foreach ($params as $key => $val) {
            $params[$key] = $val;
            //file_put_contents("C:/tmp.txt", $key.':'.$val.PHP_EOL, FILE_APPEND);
        }
        //参数为空,不进行处理
        if (count($params) < 1) {
            $this->ajaxError("error");
        }
        //记录微信回调通知
        M('wxnotifylog')->add(
            array(
                "attach" => $params["attach"],
                "bank_type" => $params["bank_type"],
                "cash_fee" => $params["cash_fee"],
                "fee_type" => $params["fee_type"],
                "err_code" => $params["err_code"],
                "err_code_des" => $params["err_code_des"],
                "nonce_str" => $params["nonce_str"],
                "openid" => $params["openid"],
                "out_trade_no" => $params["out_trade_no"],
                "result_code" => $params["result_code"],
                "return_code" => $params["return_code"],
                "sign" => $params["sign"],
                "time_end" => $params["time_end"],
                "total_fee" => $params["total_fee"],
                "trade_type" => $params["trade_type"],
                "transaction_id" => $params["transaction_id"]
            )
        );
        //属性赋值为APP的
        $this->setAppConfig();
        //验签
        if ($this->validSign($params)) {
            //返回状态为支付成功
            if ($params["result_code"] == 'SUCCESS') {
                // 订单sn
                $sn = $params['out_trade_no'];

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
                //通知微信处理成功
                $this->replyNotify();
            }
        } else {
            $this->ajaxError("验签失败");
        }
    }

    /**
     * 验签
     * @param $array
     * @return bool
     */
    public function validSign($array)
    {
        if ("SUCCESS" == $array["return_code"]) {
            $signRsp = strtolower($array["sign"]);
            unset($array["sign"]);
            $sign = strtolower($this->MakeSign($array));
            if ($sign == $signRsp) {
                return TRUE;
            } else {
                echo "验签失败:" . $signRsp . "--" . $sign;
            }
        } else {
            echo "状态异常:" . $array["return_code"];
        }
        return FALSE;
    }

    /**
     * 生成签名
     * @return 签名
     */
    public function MakeSign($params)
    {
        //签名步骤一：按字典序排序数组参数
        ksort($params);
        $string = $this->ToUrlParams($params);
        //签名步骤二：在string后加入KEY
        $string = $string . "&key=" . $this->key;
        //签名步骤三：MD5加密
        $string = md5($string);
        //签名步骤四：所有字符转为大写
        $result = strtoupper($string);
        return $result;
    }

    /**
     * 接收通知成功后通知微信，输出XML数据
     * @param string $xml
     */
    public function replyNotify()
    {
        $data['return_code'] = 'SUCCESS';
        $data['return_msg'] = 'OK';
        $xml = $this->data_to_xml($data);
        echo $xml;
        die();
    }

    /**
     * 生成jsapi支付参数
     * @param array $result 统一支付接口返回的数据
     * @return json数据
     */
    public function getJsApiParameters($result)
    {
        if (!array_key_exists("appid", $result) || !array_key_exists("prepay_id", $result) || $result['prepay_id'] == "") {
            return "";
        }
        $params = array();
        $params['appId'] = $result["appid"];
        $timeStamp = time();
        $params['timeStamp'] = "$timeStamp";
        $params['nonceStr'] = $this->getRandomString();
        $params['package'] = "prepay_id=" . $result['prepay_id'];
        $params['signType'] = "MD5";
        $params['paySign'] = $this->MakeSign($params);

        $parameters = json_encode($params);
        return $parameters;
    }

    /**
     * 生成APP端支付参数
     * @param  $prepayid   预支付id
     */
    public function getAppPayParameters($result)
    {
        if (!array_key_exists("appid", $result) || !array_key_exists("prepay_id", $result) || $result['prepay_id'] == "") {
            return "";
        }
        $params = array();
        $params['appId'] = $this->wxappid;
        $params['partnerId'] = $this->mch_id;
        $params['prepayId'] = $result['prepay_id'];
        $params['packageValue'] = 'Sign=WXPay';
        $params['nonceStr'] = $this->getRandomString();
        $params['timeStamp'] = time();
        $params['sign'] = $this->MakeSign($params);

        $parameters = json_encode($params);
        return $parameters;
    }

    /**
     * 将参数拼接为url: key=value&key=value
     * @param   $params
     * @return  string
     */
    public function ToUrlParams($params)
    {
        $string = '';
        if (!empty($params)) {
            $array = array();
            foreach ($params as $key => $value) {
                $array[] = $key . '=' . $value;
            }
            $string = implode("&", $array);
        }
        return $string;
    }

    /**
     * 输出xml字符
     * @param   $params     参数名称
     * return   string      返回组装的xml
     **/
    public function data_to_xml($params)
    {
        if (!is_array($params) || count($params) <= 0) {
            return false;
        }
        $xml = "<xml>";
        foreach ($params as $key => $val) {
            if (is_numeric($val)) {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            } else {
                $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
            }
        }
        $xml .= "</xml>";
        return $xml;
    }

    /**
     * 将xml转为array
     * @param string $xml
     * return array
     */
    public function xml_to_data($xml)
    {
        if (!$xml) {
            return false;
        }
        //将XML转为array
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        $data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $data;
    }

    /**
     * 获取毫秒级别的时间戳
     */
    private static function getMillisecond()
    {
        //获取毫秒的时间戳
        $time = explode(" ", microtime());
        $time = $time[1] . ($time[0] * 1000);
        $time2 = explode(".", $time);
        $time = $time2[0];
        return $time;
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

    /**
     * 以post方式提交xml到对应的接口url
     *
     * @param string $xml 需要post的xml数据
     * @param string $url url
     * @param bool $useCert 是否需要证书，默认不需要
     * @param int $second url执行超时时间，默认30s
     * @throws WxPayException
     */
    private function postXmlCurl($xml, $url, $useCert = false, $second = 30)
    {
        $ch = curl_init();
        //设置超时
        curl_setopt($ch, CURLOPT_TIMEOUT, $second);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        //设置header
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        if ($useCert == true) {
            //设置证书
            //使用证书：cert 与 key 分别属于两个.pem文件
            curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');
            //curl_setopt($ch,CURLOPT_SSLCERT, WxPayConfig::SSLCERT_PATH);
            curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');
            //curl_setopt($ch,CURLOPT_SSLKEY, WxPayConfig::SSLKEY_PATH);
        }
        //post提交方式
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        //运行curl
        $data = curl_exec($ch);
        //返回结果
        if ($data) {
            curl_close($ch);
            return $data;
        } else {
            $error = curl_errno($ch);
            curl_close($ch);
            return false;
        }
    }

    /**
     * 错误代码
     * @param  $code       服务器输出的错误代码
     * return string
     */
    public function error_code($code)
    {
        $errList = array(
            'NOAUTH' => '商户未开通此接口权限',
            'NOTENOUGH' => '用户帐号余额不足',
            'ORDERNOTEXIST' => '订单号不存在',
            'ORDERPAID' => '商户订单已支付，无需重复操作',
            'ORDERCLOSED' => '当前订单已关闭，无法支付',
            'SYSTEMERROR' => '系统错误!系统超时',
            'APPID_NOT_EXIST' => '参数中缺少APPID',
            'MCHID_NOT_EXIST' => '参数中缺少MCHID',
            'APPID_MCHID_NOT_MATCH' => 'appid和mch_id不匹配',
            'LACK_PARAMS' => '缺少必要的请求参数',
            'OUT_TRADE_NO_USED' => '同一笔交易不能多次提交',
            'SIGNERROR' => '参数签名结果不正确',
            'XML_FORMAT_ERROR' => 'XML格式错误',
            'REQUIRE_POST_METHOD' => '未使用post传递参数 ',
            'POST_DATA_EMPTY' => 'post数据不能为空',
            'NOT_UTF8' => '未使用指定编码格式',
        );
        if (array_key_exists($code, $errList)) {
            return $errList[$code];
        }
    }

    //TODO 以下为获取openid方法，无效
    /**
     *
     * 通过跳转获取用户的openid，跳转流程如下：
     * 1、设置自己需要调回的url及其其他参数，跳转到微信服务器https://open.weixin.qq.com/connect/oauth2/authorize
     * 2、微信服务处理完成之后会跳转回用户redirect_uri地址，此时会带上一些参数，如：code
     *
     * @return 用户的openid
     */
    public function getOpenid()
    {
        //通过code获得openid
        if (!isset($_GET['code'])) {
            //触发微信返回code码
            $redirectUrl = urlencode('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . $_SERVER['QUERY_STRING']);
            $url = $this->createOauthUrlForCode($redirectUrl);
            header("Location: $url");
            exit();
        } else {
            //获取code码，以获取openid
            $code = $_GET['code'];
            $openid = $this->getOpenidFromMp($code);
            return $openid;
        }
    }

    /**
     * 组装获取code的url地址
     * @param string $redirectUrl 微信服务器回跳的url，需要url编码
     * @return 请求的url
     */
    private function createOauthUrlForCode($redirectUrl)
    {
        $urlObj["appid"] = $this->wxappid;
        $urlObj["redirect_uri"] = "$redirectUrl";
        $urlObj["response_type"] = "code";
        $urlObj["scope"] = "snsapi_base";
        $urlObj["state"] = "STATE" . "#wechat_redirect";
        $bizString = $this->ToUrlParams($urlObj);
        return "https://open.weixin.qq.com/connect/oauth2/authorize?" . $bizString;
    }

    /**
     * 组装获取open和access_toke的url地址
     * @param string $code ，微信跳转带回的code
     * @return 请求的url
     */
    private function createOauthUrlForOpenid($code)
    {
        $urlObj["appid"] = $this->wxappid;
        $urlObj["secret"] = $this->secret;
        $urlObj["code"] = $code;
        $urlObj["grant_type"] = "authorization_code";
        $bizString = $this->ToUrlParams($urlObj);
        return "https://api.weixin.qq.com/sns/oauth2/access_token?" . $bizString;
    }

    /**
     * 通过code 获取openid和access_token
     * @param string $code 微信跳转后的url参数code
     * @return openid
     */
    public function getOpenidFromMp($code)
    {
        $url = $this->createOauthUrlForOpenid($code);
        //初始化curl
        $ch = curl_init();
        //设置超时
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        //设置代理
        if (self::CURL_PROXY_HOST != "0.0.0.0"
            && self::CURL_PROXY_PORT != 0
        ) {
            curl_setopt($ch, CURLOPT_PROXY, self::CURL_PROXY_HOST);
            curl_setopt($ch, CURLOPT_PROXYPORT, self::CURL_PROXY_PORT);
        }
        //运行curl，结果以jason形式返回
        $res = curl_exec($ch);
        curl_close($ch);
        //取出openid
        $data = json_decode($res, true);
        $openid = $data['openid'];
        return $openid;
    }
}