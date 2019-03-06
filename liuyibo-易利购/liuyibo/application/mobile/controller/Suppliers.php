<?php

namespace app\mobile\controller;

use think\db;
use think\Page;
use think\Request;

class Suppliers extends MobileBase
{
    private  $latitude = 23.12463;
    private  $longitude = 113.36199;
    private  $user = [];
    private  $where = [];

    public function _initialize()
    {
        parent::_initialize();
        $user = session('user');
        
        if (!$user) {  // 没有登录

            if(strstr($_SERVER['HTTP_USER_AGENT'],'MicroMessenger') && $is_bind_account){
                header("location:" . U('Mobile/User/bind_guide'));//微信浏览器, 调到绑定账号引导页面
            }else{
                header("location:" . U('Mobile/User/login'));
            }
            exit;
        }
        if ($this->user['level'] == 1) {
            $this->error('成为会员才可浏览哦~');
        }

        // 电脑模拟登录   是一个对象
        if (is_object($user)) {
            $users = $user->toArray();
            if ($users['latitude']) $this->latitude = $users['latitude'];
            if ($users['longitude']) $this->longitude = $users['longitude'];
            $this->user = $user;
        } else { // 微信登录  是一个数组
            if ($user['latitude']) $this->latitude = $user['latitude'];
            if ($user['longitude']) $this->longitude = $user['longitude'];
            $this->user = $user;

        }
    }

    public function suppliers_list(){
        $type_info = Db::name('suppliers_type')->where(['is_show' => 1])->select();
        $suppliers_count = Db::name('suppliers')->field('suppliers_id')->where(['is_check' => 1])->count();

        $this->assign('type_info', $type_info);
        $this->assign('count', $suppliers_count);
        return $this->fetch();
    }

    /**
     * 门店详情页面
     */
    public function suppliers_info($suppliers_id){
        $field = 'suppliers_id,suppliers_content,suppliers_name,suppliers_img,suppliers_desc,suppliers_contacts,suppliers_phone,province_id,city_id,district_id,address,lon,lat, ROUND(6378.138 *2 * ASIN(SQRT(POW(SIN( ( '.$this->latitude.' * PI( ) /180 -  `lat` * PI( ) /180) /2 ) , 2 )+COS(  '.$this->latitude.' * PI( ) /180) * COS(  `lat` * PI( ) /180 )*POW(SIN(( '.$this->longitude.' * PI( ) /180 -  `lon` * PI( ) /180 ) /2), 2 ))) *1000 ) AS distance ';
        
        $suppliers = Db::name('suppliers')
                    ->field($field)
                    ->where('suppliers_id', $suppliers_id)
                    ->find();
        $suppliers['suppliers_content'] = htmlspecialchars_decode($suppliers['suppliers_content']);
        $this->assign('suppliers', $suppliers);
        return $this->fetch();

    }

    public function suppliers_map($suppliers_id){
        $field = 'suppliers_id,suppliers_name,lat,lon,address';
        $suppliers = Db::name('suppliers')
            ->field($field)
            ->where('suppliers_id', $suppliers_id)
            ->find();
        $this->assign('suppliers',$suppliers);
        return $this->fetch();
    }

    public function ajax_getSupplier(){
         // 查询门店分类信息  未处理
        

        // 获取查询条件  门店type_id   默认全部
        //  检测查询条件  是否重置
        $where['is_check'] = 1;
        I('post.where_action') ? $where_action = I('post.where_action') : false;
        if ($where_action == 1) { // 不重置
            $where =  session('where');
        } elseif ($where_action == 2){  // 按店铺名查询
            $name = input('post.suppliers_name');
            $where = ' is_check = 1 AND  suppliers_name like "%'.$name.'%"';  // 索引会失效
            empty($name) && exit(json_encode(['code' => -11, 'msg' => '请输入店铺信息']));
        } else {  // 重置
            I('post.type_id') ?  $where['type_id'] = I('post.type_id') : false;
        }
        
        // 获取排序条件
        $orderby = I('post.orderby_action') ?  I('post.orderby_action') : 'distance';
        $field = 'suppliers_id,suppliers_name,suppliers_img,suppliers_desc,suppliers_contacts,suppliers_phone,province_id,city_id,district_id,address,lon,lat, ROUND(6378.138 *2 * ASIN(SQRT(POW(SIN( ( '.$this->latitude.' * PI( ) /180 -  `lat` * PI( ) /180) /2 ) , 2 )+COS(  '.$this->latitude.' * PI( ) /180) * COS(  `lat` * PI( ) /180 )*POW(SIN(( '.$this->longitude.' * PI( ) /180 -  `lon` * PI( ) /180 ) /2), 2 ))) *1000 ) AS distance ';
        $suppliers_count = Db::name('suppliers')->field($field)->where($where)->count();
        $sql_a = Db::name('suppliers')->getLastSql();
        $Page = new Page($suppliers_count,12);
        $suppliers_list = Db::name('suppliers')
                            ->field($field)
                            ->where($where)
                            ->limit($Page->firstRow.','.$Page->listRows)
                            ->order($orderby)
                            ->select();
        $sql_b = Db::name('suppliers')->getLastSql();

        session('where', $where);
        $arr['code'] = 200;
        $arr['data'] = $suppliers_list;
        $arr['num'] = $suppliers_count;
//        $arr['msg'] = $sql_a;
//        $arr['msg2'] = $sql_b;
        exit(json_encode($arr));
    }

    /*
     * 服务门店地址  网点大全
     * */
    public function server_door(){

        $user = session('user');
        $this->assign('latitude', $user['latitude']);//
        $this->assign('longitude', $user['longitude']);//
        $this->assign('type', I('get.type'));// 选择的服务类型
        $this->assign('logo', I('get.logo'));// 标识  哪里过来的
        $this->assign('plan_id', I('get.plan_id'));// 维修plan_id

        $this->assign('action', I('get.action'));// cart2的页面分别
        $this->assign('goods_id', I('get.goods_id'));//商品ID
        $this->assign('goods_num', I('get.goods_num'));// 商品数量
        $this->assign('item_id', I('get.item_id'));// 订单结算 商品规格


        return $this->fetch();
    }

    /*
     * 网点列表
     * **/
    public function server_door_list(){
        $suppliers_data = $this->ajax_ini_getSuppliers(true);
        $this->assign('suppliers', $suppliers_data);
        return $this->fetch();
    }

    /**
     * 门店地址
     */
    public function door_address(){
        $user = session('user');
        $this->assign('latitude', $user['latitude']);//
        $this->assign('longitude', $user['longitude']);//
        // 获取当前ip地址
        return $this->fetch();
    }


    /**
     * ajax查询店铺
     * action  标识符  name 查门店名  ID  查门店id
     */
    public function ajax_getSupplier_nxtx(){
        exit;
        //  接收查询方式
        $action = input('param.action');
        $limit = 7;
        if ($action == 'id'){
            $id = input('param.suppliers_id');
            $where = ' is_check = 1 AND suppliers_id ='.$id;
            empty($id) && exit(json_encode(['code' => -11, 'msg' => '请输入店铺信息']));
        } elseif($action == 'name'){
            $name = input('param.suppliers_name');
            $where = ' is_check = 1 AND  suppliers_name like "%'.$name.'%"';  // 索引会失效
            empty($name) && exit(json_encode(['code' => -11, 'msg' => '请输入店铺信息']));
        } elseif($action = 'linkage'){
            // echo 1;
            // exit;
            I('post.province') ? $province = I('post.province') : false; //经度 数字大
            I('post.city') ? $city = I('post.city') : false; //纬度 数字大
            I('post.district') ? $district = I('post.district') : false; //纬度 数字大
            // 查询地址表id
            $where['is_check'] = 1;
            if ($province) $where['province_id'] = $province;
            if ($city) $where['city_id'] = $city;
            if ($district)$where['district_id'] = $district;
            $limit = 999; // 
        } else {
            exit;
        }
        $latitude = input('param.lat');  // 纬度
        $longitude = input('param.lon'); // 经度
        empty($latitude) && exit(json_encode(['code' => -11, 'msg' => '查询异常~']));
        empty($longitude) && exit(json_encode(['code' => -11, 'msg' => '查询异常']));
        $suppliers_count = Db::name('suppliers')->where($where)->count();
        if (!$suppliers_count) exit(json_encode(['code' => -11, 'msg' => '无店铺信息', 'data' => null, 'num' => 0]));
        $field = 'suppliers_id,is_check,suppliers_name,suppliers_img,suppliers_desc,suppliers_contacts,suppliers_phone,province_id,city_id,district_id,address ,ROUND(6378.138 *2 * ASIN(SQRT(POW(SIN( ( '.$latitude.' * PI( ) /180 -  `lat` * PI( ) /180) /2 ) , 2 )+COS(  '.$latitude.' * PI( ) /180) * COS(  `lat` * PI( ) /180 )*POW(SIN(( '.$longitude.' * PI( ) /180 -  `lon` * PI( ) /180 ) /2), 2 ))) *1000 ) AS distance ';
        $suppliers_info = Db::name('suppliers')->field($field)->where($where)->order('distance ASC')->limit($limit)->select();
        $arr['code'] = 1;
        $arr['data'] = $suppliers_info;
        $arr['num'] = $suppliers_count;
        $arr['msg'] = '查询成功';
        exit(json_encode($arr));
    }

    /**
     * 服务门店详细信息
     */
    public function server_door_detail(){
        $suppliers_id = input('param.suppliers_id/d');
        $where['suppliers_id'] = $suppliers_id;
        $field = 'suppliers_id,suppliers_name,suppliers_desc,suppliers_contacts,suppliers_phone,province_id,city_id,district_id,address,lon,lat';
        $suppliers_info = Db::name('suppliers')->field($field)->where($where)->find();
        $this->assign('suppliers', $suppliers_info);
         return $this->fetch();
    }

    /*
     *  使用微信的JSSDK   要在微信页面才可以打开
     * **/
    public function door_detail_map(){
        $id = input('param.id/d');
        $field = 'lon,lat,suppliers_id,suppliers_name,address';
        $data = Db::name('suppliers')->field($field)->where(['suppliers_id'=>$id])->find();
        $this->assign('data',$data);
        if ( strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false ){
            return $this->fetch();
        } else {
            echo 'no';
        }
    }

    /**
     * 到店订单 门店选择确认
     */
    public function ajax_suppliers_confirm(){
        $suppliers_id = input('param.suppliers_id/d');
        $order_id = input('param.order_id/d');

        // 确认当前订单是否存在以及门店id是否存在
        $order_id_res = Db::name('order')->where(["order_id"=>$order_id])->find();
        $suppliers_id_res = Db::name('suppliers')->where(["suppliers_id"=>$suppliers_id])->find();

        if (!$order_id_res && !$suppliers_id_res) return false;

        // 修改信息數據   
        $data['suppliers_id'] = $suppliers_id;
        $res = Db::name('order')->where(['order_id'=>$order_id])->save($data);
        $mes = empty($res)? Db::name('order')->getLastSql():'success';
        $result['code'] = 200; 
        $result['mes'] = $mes; 
        $result['data'] = null; 
        exit(json_encode($result));
    }



    /**
     * 门店地址_map显示
     */
    public function door_address_map(){
        // 获取当前ip地址
        $user_address = session('user_address');
        $suppliers_data = $this->ajax_ini_getSuppliers(true);
        $this->assign('address', $user_address);
        $this->assign('suppliers', json_encode($suppliers_data));
        return $this->fetch();
    }

    /*
     * 存客户的经纬度
     * 上线前无用可删
     * */
    public function save_address(){
        session('user_address',input(input('param./d')));
    }

    /**
     * 参数1：访问的URL，参数2：post数据(不填则为GET)，参数3：提交的$cookies,参数4：是否返回$cookies
     * @param  [type]  $url          访问的URL
     * @param  string  $post         post数据(不填则为GET)
     * @param  string  $cookie        提交的$cookies
     * @param  integer $returnCookie 是否返回$cookies
     * @return [type]                 data
     */
    public function curl_request($url,$post='',$cookie='', $returnCookie=0){
         $curl = curl_init();
         curl_setopt($curl, CURLOPT_URL, $url);
         curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)');
         curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
         curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // 跳过ssl检查项
         curl_setopt($curl, CURLOPT_AUTOREFERER, 1);
         curl_setopt($curl, CURLOPT_REFERER, "http://XXX");
         if($post) {
             curl_setopt($curl, CURLOPT_POST, 1);
             curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post));
         }
         if($cookie) {
             curl_setopt($curl, CURLOPT_COOKIE, $cookie);
         }
         curl_setopt($curl, CURLOPT_HEADER, $returnCookie);
         curl_setopt($curl, CURLOPT_TIMEOUT, 10);
         curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
         $data = curl_exec($curl);
         if (curl_errno($curl)) {
             return curl_error($curl);
         }
         curl_close($curl);
         if($returnCookie){
             list($header, $body) = explode("\r\n\r\n", $data, 2);
             preg_match_all("/Set\-Cookie:([^;]*);/", $header, $matches);
             $info['cookie']  = substr($matches[1][0], 1);
             $info['content'] = $body;
             return $info;
         }else{
             return $data;
         }
    }

    public  function test(){
        header("Content-type:text/html;charset=utf-8");
        // 先获取一级  省
        $provice = Db::name('region')->field('id as code,name')->where(['level'=>1])->select();
        foreach ($provice as $k => &$v) {
            $v['code'] = "$v[code]";
            $v['sub'] = Db::name('region')->field('id as code,name')->where(['parent_id' => $v['code'], 'level'=>2])->select();
            foreach ($v['sub'] as $key => &$value) {
                $value['code'] = "$value[code]";
                $value['sub'] = Db::name('region')->field('id as code,name')->where(['parent_id' => $value['code'], 'level'=>3])->select();
                foreach ($value['sub'] as $ke => &$val) {
                    $val['code'] = "$val[code]";
                }
            }

        }
        // dump(json_encode($provice,JSON_UNESCAPED_UNICODE));
        file_put_contents('city.txt',json_encode($provice,JSON_UNESCAPED_UNICODE));
        // 提取所有市级的id  进行绑定 区;

    }

    /*
     * 存储用户经纬度
     * */
    public function  saveUserAddress(){
        $user = session('user');
        $user['latitude'] = input('post.latitude');
        $user['longitude'] = input('post.longitude');
        session('user',$user);
    }

    /**
     * 获取用户当前地址
     */
    public function getUserAddress(){
        $user = session('user');
        $latitude = $user['latitude'];
        $longitude = $user['longitude'];
        if (!$latitude || !$longitude) return false;
        $url = 'http://api.map.baidu.com/geocoder/v2/?callback=renderReverse&location='.$latitude.','.$longitude.'&output=json&pois=1&ak=HyurfGTQ5p5WhtEsegrxisqer47BubTW';
        // $url = 'http://api.map.baidu.com/geocoder/v2/?callback=renderReverse&location=23.13267116,113.39544277939345&output=json&pois=1&ak=HyurfGTQ5p5WhtEsegrxisqer47BubTW';
        $res = $this->curl_request($url);

        // 切割 获取范围
        $address_arr = explode(',"formatted_address":',$res)[1];
        $provice_arr = explode(',"province":',$res)[1];
        $city_arr = explode(',"city":',$res)[1];
        $district_arr = explode(',"district":',$res)[1];

        //  再次切割 获取值
        $res_addres = explode(',',$address_arr)[0];
        $res_provice = explode(',',$provice_arr)[0];
        $res_city = explode(',',$city_arr)[0];
        $res_district = explode(',',$district_arr)[0];

        // 去掉双引号
        $res_addres = trim($res_addres, '"');
        $res_provice = trim($res_provice, '"');
        $res_city = trim($res_city, '"');
        $res_district = trim($res_district, '"');

        //  查找省市区ID
        $province_id = Db::name('region')->where(['name' => ['like', $res_provice]])->cache(true)->value('id');
        $city_id = Db::name('region')->where(['name' => ['like', $res_city]])->cache(true)->value('id');
        $district_id = Db::name('region')->where(['name' => ['like', $res_district]])->cache(true)->value('id');
        $res = [
            'province'=>$res_provice,
            'city' => $res_city,
            'district' => $res_district,
            'address' => $res_addres,
            'province_id' => $province_id,
            'city_id' => $city_id,
            'district_id' => $district_id,
        ];
        exit(json_encode($res));
    }

}