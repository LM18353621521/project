<?php
namespace app\admin\controller\Distribut;

use app\admin\controller\Base;
use app\common\logic\UsersLogic;
use app\common\model\Users;
use think\AjaxPage;
use think\Controller;
use app\admin\logic\GoodsLogic;
use app\admin\logic\SearchWordLogic;
use think\Url;
use think\Config;
use think\Page;
use think\Verify;
use think\Db;

class Report extends Base
{
    public $begin;
    public $end;

    public function _initialize(){
        parent::_initialize();
        $start_time = I('start_time');
        if(I('start_time')){
            $begin = urldecode($start_time);
            $end_time = I('end_time');
            $end = urldecode($end_time);
        }else{
            $begin = date('Y-m-d', strtotime("-7 days"));//30天前
            $end = date('Y-m-d', strtotime('-1 days'));
        }
        $this->assign('start_time',$begin);
        $this->assign('end_time',$end);
        $this->begin = strtotime($begin);
        $this->end = strtotime($end)+86399;
    }

    /*
     * 直推奖列表
     * */
    public function firstList(){
        $account_log = DB::name('account_log')->where(['type'=>4])->group('user_id')->count();
        $page = new Page($account_log, 10);
        $show = $page->show();
        $user_info = DB::name('account_log')
                        ->alias('a')
                        ->field('a.*,s.*,sum(a.user_money) as total_money,count(a.log_id) as total_num,c.level_name')
                        ->join('users s', 'a.user_id = s.user_id', 'LEFT')
                        ->join('user_level c','c.level_id = s.level')
                        ->where(['a.type'=>4])
                        ->limit($page->firstRow, $page->listRows)
                        ->group('a.user_id')
                        ->select();
        $this->assign('userInfo',$user_info);
        $this->assign('page', $show);
        $this->assign('pager', $page);
        return $this->fetch();
    }

    /*
     * 团队奖列表
     * */
    public function teamList(){
        $account_log = DB::name('account_log')->where('type',5)->count();
        $page = new Page($account_log, 10);
        $show = $page->show();
        $user_info = DB::name('account_log')
            ->alias('s')
            ->field('s.*,a.*')
            ->join('users a', 'a.user_id = s.user_id', 'LEFT')
            ->where('type',5)
            ->limit($page->firstRow, $page->listRows)
            ->select();
        $this->assign('userInfo',$user_info);
        return $this->fetch();
    }

    /*
     * 市场补助发放
     * */
    public function subsidyList(){
        $now = strtotime(date('Y-m-d', strtotime('-1 days')));
        $today['today_amount'] = M('account_log')->where("change_time>$now AND type = 3")->sum('user_money');//今日销售总额
        $today['today_subsidy'] = M('account_log')->where("change_time>$now and type = 3")->count();//今日订单数
        if ($today['today_subsidy'] == 0) {
            $today['sign'] = round(0, 2);
        } else {
            $today['sign'] = round($today['today_amount'] / $today['today_subsidy'], 2);
        }
        $this->assign('today',$today);
        $begin = $this->begin;
        $end = $this->end;
        $res = Db::name("account_log")
            ->field(" COUNT(*) as tnum,sum(user_money) as amount, FROM_UNIXTIME(change_time,'%Y-%m-%d') as gap ")
            ->where(" change_time >$begin and change_time < $end AND type = 3")
            ->group('gap')
            ->select();
        foreach ($res as $val){
            $arr[$val['gap']] = $val['tnum'];
            $brr[$val['gap']] = $val['amount'];
            $tnum += $val['tnum'];
            $tamount += $val['amount'];
        }

        for($i=$this->begin;$i<=$this->end;$i=$i+24*3600){
            $tmp_num = empty($arr[date('Y-m-d',$i)]) ? 0 : $arr[date('Y-m-d',$i)];
            $tmp_amount = empty($brr[date('Y-m-d',$i)]) ? 0 : $brr[date('Y-m-d',$i)];
            $tmp_sign = empty($tmp_num) ? 0 : round($tmp_amount/$tmp_num,2);
            $order_arr[] = $tmp_num;
            $amount_arr[] = $tmp_amount;
            $sign_arr[] = $tmp_sign;
            $date = date('Y-m-d',$i);
            $list[] = array('day'=>$date,'order_num'=>$tmp_num,'amount'=>$tmp_amount,'sign'=>$tmp_sign,'end'=>date('Y-m-d',$i+24*60*60));
            $day[] = $date;
        }
        rsort($list);


        $user_info[] = M('users')->where(['level'=>['GT',1],'total_jackpot'=>['EGT',distributCache('settlement.share_price')]])->count();//获取补助奖分够的人数
        $user_info[] = M('users')->where(['level'=>['GT',1],'total_jackpot'=>['LT',distributCache('settlement.share_price')]])->count();//获取补助奖还能分的人数
        $user_info[] = M('users')->where(['level'=>['LT',2],'total_jackpot'=>['LT',distributCache('settlement.share_price')]])->count();//获取补助奖不能分的人数

        $this->assign('list',$list);
        $result = array('num'=>$order_arr,'amount'=>$amount_arr,'sign'=>$sign_arr,'time'=>$day,'info'=>$user_info);
        $this->assign('result',json_encode($result));



        return $this->fetch();
    }


   

    /**
     * 管理奖
     */
    public function managementList(){
        $type = 8;
        // 前一天时间
        $now = strtotime(date('Y-m-d', strtotime('-1 days')));
        $last_day = strtotime(date('Y-m-d', time()));
        //今日销售总额
        $today['today_amount'] = Db::name("account_log")
                                    ->where(['change_time' => ['between', $now.','.$last_day], 'type' =>$type])
                                    ->sum('user_money');
        //今日订单数
        $today['today_subsidy'] =  Db::name("account_log")
                                    ->where(['change_time' => ['between', $now.','.$last_day], 'type' =>$type])
                                    ->count();

        // 人均客单价
        if ($today['today_subsidy']) {
            $today['sign'] = round($today['today_amount'] / $today['today_subsidy'], 2);
        } else {
            $today['sign'] = round(0, 2);
        }

        // 时间初始化  近七天
        $begin = strtotime(date('Y-m-d', strtotime("-6 days")));
        $end = strtotime(date('Y-m-d', time()).'23.59.59');

        $res = Db::name("account_log")
            ->field(" COUNT(*) as tnum,sum(user_money) as amount, FROM_UNIXTIME(change_time,'%Y-%m-%d') as gap ")
            ->where(['change_time' => ['between', $begin.','.$end], 'type' =>$type])
            ->group('gap')
            ->select();
        // dump(Db::name("account_log")->getLastSql());
        // dump($res);
        // die;

        // 整合  每一天 时间为键名
        foreach ($res as $val){
            // 统计数量
            $arr[$val['gap']] = $val['tnum'];
            // $tnum += $val['tnum'];
            // 统计金额
            $brr[$val['gap']] = $val['amount'];
            // $tamount += $val['amount'];
        }
        

        // 按每天来
        for($i=$begin;$i<=$end;$i=$i+24*3600){
            // 是否含有当天的时间
            $tmp_num = empty($arr[date('Y-m-d',$i)]) ? 0 : $arr[date('Y-m-d',$i)];
            // 是否含有当天金额
            $tmp_amount = empty($brr[date('Y-m-d',$i)]) ? 0 : $brr[date('Y-m-d',$i)];
            // 当天有数据，计算 人均客单价
            $tmp_sign = empty($tmp_num) ? 0 : round($tmp_amount/$tmp_num,2);
            $date = date('Y-m-d',$i);
            // dump($tmp_num);
            $list[] = array('day'=>$date,'order_num'=>$tmp_num,'amount'=>$tmp_amount,'sign'=>$tmp_sign,'end'=>date('Y-m-d',$i+24*60*60));
            // 统计做图表数据
            $order_arr[] = $tmp_num;
            $amount_arr[] = $tmp_amount;
            $sign_arr[] = $tmp_sign;
            $day[] = $date;
        }
        rsort($list);
        $this->assign('list',$list);

        $user_info[] = Db::name("account_log")
                        ->where(['type' => $type, 'change_time' => ['between',$begin.','.$end]])
                        ->count();//获取补助奖分够的人数
        // dump($user_info);
        // dump(Db::name("account_log")->getLastSql());
        // die;

        $result = array('num'=>$order_arr,'amount'=>$amount_arr,'sign'=>$sign_arr,'time'=>$day,'info'=>$user_info);
        $this->assign('result',json_encode($result));

        $this->assign('today',$today);
        return $this->fetch();
    }

    /**
     * 市代理
     */
    public function regionCityList(){
        $city_list = Db::name("users")
                    ->alias('u')
                    ->field('r.*,u.user_id,u.region_code,u.nickname,u.mobile')
                    ->join('__REPORT__ r', 'u.user_id = r.user_id', 'LEFT')
                    ->where([
                        'level' => 5,
                        'region_code' => ['NEQ', 0]
                    ])
                    ->select();
        foreach ($city_list as $key => $val) {
            $city_id = explode(',', $val['region_code']);
            $name = Db::name('region')->where(['id' => ['in', $city_id]])->column('name');
            $city_list[$key]['city'] = join(',', $name);
        }
        $this->assign('list', $city_list);
        return $this->fetch();
    }

    //
    public function prizeHandOut(){
        $Distribut = new \app\common\logic\DistributPrizeLogic();
        $prize =  I('get.action',0);
        switch ($prize) {
            case 'market':
                $price = $Distribut->market_settle_prize();
                break;
            case 'management':
                $price = $Distribut->management_prize();
                break;
            case 'city':
                $price = $Distribut->region_city_prize();
                break;
            case 'partner':
                $price = $Distribut->region_partner_prize();
                break;
            default:
                break;
        }
        return $this->success("分成成功！", U('Mobile/User/index'));
    }

    /**
     * 合伙人
     */
    public function regionPartnerList(){
        $city_list = Db::name("users")
                    ->alias('u')
                    ->field('r.*,u.user_id,u.region_code,u.nickname,u.mobile')
                    ->join('__REPORT__ r', 'u.user_id = r.user_id', 'LEFT')
                    ->where([
                        'level' => 6
                    ])
                    ->select();
        $this->assign('list', $city_list);
        return $this->fetch();
    }
}