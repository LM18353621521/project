<?php

namespace app\vpay\controller;

use app\common\logic\CartLogic;
use app\common\logic\UsersLogic;
use app\common\model\Users;
use think\Controller;
use think\Session;
use think\Request;
use think\View;
use think\Config;
use think\Response;
use think\exception\DbException;
use think\exception\HttpResponseException;

class Base extends Controller {


    Public function _initialize()
    {

        parent::_initialize();
        Session::start();
    }

    // 登录错误
    public function ajaxLoginError($info)
    {
        $this->response(-2, $info, null);
    }

    //输出ajax错误
    public function ajaxError($info)
    {
        $this->response(-1, $info, null);
    }

    //输出ajax成功
    public function ajaxSuccess($data = null)
    {
        $this->response(0, "成功", $data);

    }


    /* -2：未登录
     * -1：失败
     * 0：成功
 　　100：请求错误
 　　101：未登录
 　　102：缺少签名
 　　103：缺少参数
 　　200：服务器出错
 　　201：服务不可用
 　　202：服务器正在重启*/
    public function response($code, $message = '', $data = array())
    {
        if (!(is_numeric($code))) {
            return '';
        }
        $result = array(
            'code' => $code,
            'msg' => $message,
            'data' => $data
        );
        exit(json_encode($result));
    }

    // 保持数据
    public function set($key, $val)
    {
        // 获取token
        $token = I('token');

        // 如果没有token，应该是web登录
        if (empty($token)) {
            cookie($key,$val,24*3600);
            session($key, $val);
        } else {
            // 获取保持的数据
            $session = S($token);

            // 如果没有数据
            if (empty($session)) {
                $session = array();
            }
            $session[$key] = $val;

            S($token, $session);
        }
    }

    // 获取数据
    public function get($key)
    {

        // 获取token
        $token = I('token');

        // 如果没有token，应该是web登录
        if (empty($token)) {
            if(cookie($key) || $_SESSION[$key]){
                $data = $_SESSION[$key] ? $_SESSION[$key] : cookie($key);
            }
            return $data;
        } else {
            // 获取保持的数据
            $session = S($token);

            // 如果没有数据
            if (empty($session)) {
                $session = array();
            }
            return $session[$key];
        }
    }

    // 删除数据
    public function remove($key)
    {
        // 获取token
        $token = I('token');

        // 如果没有token，应该是web登录
        if (empty($token)) {
            $_SESSION[$key] = null;
            unset($_SESSION[$key]);
        } else {
            // 获取保持的数据
            $session = S($token);

            // 如果没有数据
            if (empty($session)) {
                return;
            }
            $session[$key] = null;
            unset($session[$key]);

            // 如果没有数据
            if (empty($session)) {
                S($token, null);
            } else {
                S($token, $session);
            }
        }
    }

    // 设置用户信息
    public function setAccount($account)
    {
        $this->set('account', $account);
    }

    // 计算距离
    public function getDistance($lat1, $lng1, $lat2, $lng2)
    {
        $earthRadius = 6367000; //approximate radius of earth in meters

        $lat1 = ($lat1 * pi()) / 180;
        $lng1 = ($lng1 * pi()) / 180;
        $lat2 = ($lat2 * pi()) / 180;
        $lng2 = ($lng2 * pi()) / 180;

        $calcLongitude = $lng2 - $lng1;
        $calcLatitude = $lat2 - $lat1;
        $stepOne = pow(sin($calcLatitude / 2), 2) + cos($lat1) * cos($lat2) * pow(sin($calcLongitude / 2), 2);
        $stepTwo = 2 * asin(min(1, sqrt($stepOne)));
        $calculatedDistance = $earthRadius * $stepTwo;

        return round($calculatedDistance);
    }

    //获取用户IP地址
    public function getIp(){
        if(!empty($_SERVER["HTTP_CLIENT_IP"]))
        {
            $cip = $_SERVER["HTTP_CLIENT_IP"];
        }
        else if(!empty($_SERVER["HTTP_X_FORWARDED_FOR"]))
        {
            $cip = $_SERVER["HTTP_X_FORWARDED_FOR"];
        }
        else if(!empty($_SERVER["REMOTE_ADDR"]))
        {
            $cip = $_SERVER["REMOTE_ADDR"];
        }
        else
        {
            $cip = '';
        }
        preg_match("/[\d\.]{7,15}/", $cip, $cips);
        $cip = isset($cips[0]) ? $cips[0] : 'unknown';
        unset($cips);
        return $cip;
    }

    // 根据当前用户IP获取经纬度
    public function getLngLat()
    {
        $getIp = $this->getIp();
        $content = file_get_contents("http://api.map.baidu.com/location/ip?ak=omi69HPHpl5luMtrjFzXn9df&ip={$getIp}&coor=bd09ll");
        $json = json_decode($content);
        $memberlng = $json->{'content'}->{'point'}->{'x'};
        $memberlat = $json->{'content'}->{'point'}->{'y'};
        return array($memberlng, $memberlat);
    }

    // 根据经纬度获取地址(城市)，返回带'市'的去掉'市'，方便查询数据库
    public function getLocation($lng, $lat)
    {
        //http://api.map.baidu.com/geocoder/v2/?ak=omi69HPHpl5luMtrjFzXn9df&location=30.548397,104.04701&output=json&pois=1
        $content = file_get_contents("http://api.map.baidu.com/geocoder/v2/?ak=omi69HPHpl5luMtrjFzXn9df&location=$lat,$lng&output=json&pois=1");
        $json = json_decode($content);
        //$city = $json->{'result'}->{'addressComponent'}->{'city'};
        $district = $json->{'result'}->{'addressComponent'}->{'district'};
        //return substr($city,0,strpos($city,'市'));
        return $district;
    }

    // 根据经纬度获取region信息
    public function getRegionByLngLat($lng, $lat)
    {
        //http://api.map.baidu.com/geocoder/v2/?ak=omi69HPHpl5luMtrjFzXn9df&location=30.548397,104.04701&output=json&pois=1
        $content = file_get_contents("http://api.map.baidu.com/geocoder/v2/?ak=omi69HPHpl5luMtrjFzXn9df&location=$lat,$lng&output=json&pois=1");
        $json = json_decode($content);
        $province = $json->{'result'}->{'addressComponent'}->{'province'};
        $city = $json->{'result'}->{'addressComponent'}->{'city'};
        $district = $json->{'result'}->{'addressComponent'}->{'district'};
        $namepath = substr($province,0,strpos($province,'省'))." / ".substr($city,0,strpos($city,'市'))." / ".$district;
        $region = M('region')->where("name_path like '$namepath'")->find();

        // 如果没有区获取市
        if(empty($region)){
            $namepath = substr($province,0,strpos($province,'省'))." / ".substr($city,0,strpos($city,'市'));
            $region = M('region')->where("name_path like '$namepath'")->find();
        }

        // 如果市获取省
        if(empty($region)){
            $namepath = substr($province,0,strpos($province,'省'));
            $region = M('region')->where("name_path like '$namepath'")->find();
        }
        return $region;
    }

    //根据地区获取经纬度
    public function getLngLatByRegion($regionid){
        $region = M('region') -> where(array("id"=>$regionid)) -> find();
        if(empty($region)){
            return "地区不存在";
        }
        $regionName = $region['name'];
        $city = M('region') -> where(array("id"=>$region['parent_id'])) -> find();
        if(empty($city)){
            return "地区不存在";
        }
        $cityName = $city['name'];
        $content = file_get_contents("http://api.map.baidu.com/geocoder?address=$regionName&output=json&key=omi69HPHpl5luMtrjFzXn9df&city=$cityName");
        $json = json_decode($content);
        $memberlng = $json->{'result'}->{'location'}->{'lng'};
        $memberlat = $json->{'result'}->{'location'}->{'lat'};
        return array($memberlng, $memberlat);
    }

    // 最近的代理
    public function getNearestAgen()
    {
        $lnglat = $this->getLngLat();
        $memberlng = $lnglat[0];
        $memberlat = $lnglat[1];
        $agen = M('Agen')->field('id,name,account,level,lat,lng')->where('status = 2')->select();
        $agenlist = array();
        foreach ($agen as $v) {
            $agenlat = $v['lat'];
            $agenlng = $v['lng'];
            $agenlist[] = array('id' => $v['id'], 'upagenname' => $v['name'], 'upagenaccount' => $v['account'], 'upagenlevel' => $v['level'], 'juli' => $this->getDistance($memberlat, $memberlng, $agenlat, $agenlng));
        }
        foreach ($agenlist as $vv) {
            $jl[] = $vv['juli'];
        }
        array_multisort($jl, SORT_ASC, $agenlist);
        for ($i = 0; $i < 5; $i++) {
            if ($agenlist[$i] != null) {
                $list[] = $agenlist[$i];
            }
        }
        return $list[0];
    }

    // 获取所有附近的代理，返回list
    // 若有level参数，则获取当前等级的所有上级代理，计算距离
    public function getAllNearAgen($level)
    {
        $getIp = $this->getIp();
        $content = file_get_contents("http://api.map.baidu.com/location/ip?ak=omi69HPHpl5luMtrjFzXn9df&ip={$getIp}&coor=bd09ll");
        $json = json_decode($content);
        $memberlng = $json->{'content'}->{'point'}->{'x'};
        $memberlat = $json->{'content'}->{'point'}->{'y'};
        if (isNumeric($level)) {
            if ($level == 1) {
                $agen = M('Agen')->field('id,name,account,level,lat,lng,companyid')->where('id=1')->select();
            } else {
                $agen = M('Agen')->field('id,name,account,level,lat,lng,companyid')->where("level < $level and status = 2")->select();
            }
            //$agen = M('Agen')->field('id,name,level,lat,lng')->where(array('level' => $level-1))->select();
        } else {
            $agen = M('Agen')->field('id,name,account,level,lat,lng,companyid')->select();
        }
        $agenlist = array();
        foreach ($agen as $v) {
            $agenlat = $v['lat'];
            $agenlng = $v['lng'];
            $agenlist[] = array('id' => $v['id'], 'upagenname' => $v['name'], 'upagenaccount' => $v['account'], 'upagenlevel' => $v['level'],'companyid' => $v['companyid'], 'juli' => $this->getDistance($memberlat, $memberlng, $agenlat, $agenlng));
        }
        foreach ($agenlist as $vv) {
            $jl[] = $vv['juli'];
        }
        array_multisort($jl, SORT_ASC, $agenlist);
        for ($i = 0; $i < 5; $i++) {
            if ($agenlist[$i] != null) {
                $list[] = $agenlist[$i];
            }
        }
        return $list;
    }

    // 获取用户微信openID
    public function getWxUser($code)
    {
        $appid = "wx1d8a9b06a78204b7";
        $secret = "c04504581fbb5aa4ab7a6d91b42b075a";
        $get_token_url = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid=' . $appid . '&secret=' . $secret . '&code=' . $code . '&grant_type=authorization_code';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $get_token_url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURL_SSLVERSION_SSL, 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        $res = curl_exec($ch);
        curl_close($ch);
        $json_obj = json_decode($res, true);

        //根据openid和access_token查询用户信息
        //$access_token = $json_obj->access_token;
        $openid = $json_obj['openid'];
        /*$get_user_info_url = 'https://api.weixin.qq.com/sns/userinfo?access_token=' . $access_token . '&openid=' . $openid . '&lang=zh_CN';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $get_user_info_url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURL_SSLVERSION_SSL, 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        $res = curl_exec($ch);
        curl_close($ch);

        //解析json
        $user_obj = json_decode($res, true);*/
        return $openid;
    }

    //代理资金变动
    public function agenmoneylog($id, $sourcetype, $moneytype, $beforemoney, $changemoney, $info, $type)
    {
        $sn = make_order_sn();
        /*插入现金流量表*/
        $data['accountId'] = $id;
        $data['accountType'] = '2';//1 会员 2代理
        $data['sourceId'] = $sn;
        $data['sourceType'] = $sourcetype;//1 会员订单，2 会员返佣 3代理提现
        if ($type == 'add') {
            $data['type'] = 1;// 1增加 2减少
        } else {
            $data['type'] = 2;// 1增加 2减少
        }
        $data['moneyType'] = $moneytype;   // 1 积分 2 金币
        $data['beforeMoney'] = $beforemoney;
        $data['changeMoney'] = $changemoney;
        $data['afterMoney'] = $beforemoney + $changemoney;
        $data['createTime'] = date("Y-m-d H:i:s");
        $data['info'] = $info;
        $addmoneylog = M("moneylog")->add($data);
        return $addmoneylog;
    }
    //会员资金变动
    public function usermoneylog($id, $sourcetype, $moneytype, $beforemoney, $changemoney, $info, $type)
    {
        $sn = make_order_sn();
        /*插入现金流量表*/
        $data['accountId'] = $id;
        $data['accountType'] = '1';//1 会员 2代理
        $data['sourceId'] = $sn;
        $data['sourceType'] = $sourcetype;//1 会员订单，2 会员返佣 3代理提现
        if ($type == 'add') {
            $data['type'] = 1;// 1增加 2减少
        } else {
            $data['type'] = 2;// 1增加 2减少
        }
        $data['moneyType'] = $moneytype;   // 1 积分 2 金币
        $data['beforeMoney'] = $beforemoney;
        $data['changeMoney'] = $changemoney;
        $data['afterMoney'] = $beforemoney + $changemoney;
        $data['createTime'] = date("Y-m-d H:i:s");
        $data['info'] = $info;
        $addmoneylog = M("moneylog")->add($data);
        return $addmoneylog;
    }


    //获取微信支付二维码信息
    public function getQrcode($orderid){
        // 接收订单信息，利用订单ID查询到订单sn和价格
        $order = M('onlineorder')
            -> where("id = $orderid")
            ->find();
        if(empty($order)){
            return false;
        }
        $goodsName = $order['sn'];
        $goodsPrice = $order['total'];
        // 构造请求二维码的链接（获取二维码支付，请求native.php）
        $url = 'http://'.$_SERVER['HTTP_HOST'].'/wxpay/example/native.php?goodsName='.$goodsName.'&goodsPrice='.$goodsPrice;
        // 用curl方法获取二维码
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        $data = curl_exec($ch);
        // preg_match_all函数进行全局正则表达式匹配,获取到二维码的链接。
        preg_match('/<\s*img\s+[^>]*?src\s*=\s*(\'|\")(.*?)\\1[^>]*?\/?\s*>/i',$data,$matches);
        preg_match('/\|\|(.*)\|\|/',$matches[0],$aac);
        // 构造返回信息
        $obj['status'] = 'success';
        $obj['code'] = $aac[1];    // 此值为返回交易码【用来确认订单支付状态的】
        $obj['price'] = $goodsPrice;
        $obj['msg'] = '/wxpay/example/'.$matches['2']; // 请求返回的二维码
        return json_encode($obj);// 返回json
    }

    //获取微信统一下单->JSAPI支付信息
    public function getJsapi($orderid){
        $openid = $this->get('account')['openid'];
        if(empty($openid)){
            return false;
        }
        // 接收订单信息，利用订单ID查询到订单sn和价格
        $order = M('onlineorder')
            -> where("id = $orderid")
            ->find();
        if(empty($order)){
            return false;
        }
        $goodsName = $order['sn'];
        $goodsPrice = $order['total'];
        // 构造请求链接（jsapi.php）
        $url = 'http://'.$_SERVER['HTTP_HOST'].'/wxpay/example/jsapi.php?goodsName='.$goodsName.'&goodsPrice='.$goodsPrice.'&openId='.$openid;
        // 用curl方法获取数据
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        $data = curl_exec($ch);
        return json_encode($data);// 返回json
    }

    //登录验证
    public function loginCheck()
    {
        $member = $this->get('account');
        if(empty($member)){
            if (IS_AJAX){
                exit( $this->ajaxLoginError("请登录"));
            }else{
                $this->redirect('home/index/login');
            }
        }
    }
}