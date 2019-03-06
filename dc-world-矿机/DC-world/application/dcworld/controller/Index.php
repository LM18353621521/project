<?php

namespace app\dcworld\controller;

use think\Request;
use think\Db;
use think\AjaxPage;


class Index extends Sign
{
    public $miner_list = [];

    public function _initialize()
    {
        parent::_initialize();
        $user = $this->getAccount();

        $user_info = M('member')->where(array('id' => $user['id'], 'isDelete' => 2))->find();
        if (empty($user_info)) {
            $this->redirect(U('Login/exitLogin'));
        }

        $this->user = $user;
        $this->user_id = $user['id'];
    }

    public function dc_static_release()
    {
        return;
        $time = strtotime(date("Y-m-d", time()));
        set_time_limit(0);
        ini_set('memory_limit', '1024M');

        $t1 = microtime(true);

        //查找释放会员、矿机
        $WorldLogic = new \app\common\logic\WorldLogic();
        $WorldLogic->get_release_miner($time);
        //开始进行释放
        $result = $WorldLogic->start_release_miner();
        $t2 = microtime(true);
        echo '耗时' . round($t2 - $t1, 3) . '秒';
        if ($result) {
            //继续执行
//            header('Refresh: 1; url=' . U('Dcworld/Index/dc_static_release'));//三秒以后跳转百度
        }
    }

    public function index()
    {
        $user_id = $this->user_id;
        $user_info = session('account');

        $WorldLogic = new \app\common\logic\WorldLogic();
        $day_data = $WorldLogic->OrePoolStatistics($user_info);

        //头像更改
        if (IS_POST) {
            if ($_FILES['head_pic']['tmp_name']) {
                $file = $this->request->file('head_pic');
                $image_upload_limit_size = config('image_upload_limit_size');
                $validate = ['size' => $image_upload_limit_size, 'ext' => 'jpg,png,gif,jpeg'];
                $dir = 'public/upload/profilePhoto/';
                if (!($_exists = file_exists($dir))) {
                    $isMk = mkdir($dir, 0777, true);
                }
                $parentDir = date('Ymd');
                $info = $file->validate($validate)->move($dir, true);
                if ($info) {
                    $post['profilePhoto'] = '/' . $dir . $parentDir . '/' . $info->getFilename();
                } else {
                    $this->error($file->getError());//上传错误提示错误信息
                }
            }

            M('member')->where(array('id' => $user_id))->update($post);

            //修改头像
            $uinfo['profilePhoto'] = '/' . $dir . $parentDir . '/' . $info->getFilename();

        }
        //            $account=parent::get("account");
        //            print_r($account);die;
        //            if(empty($account)){
        //                $this->redirect(U("Home/Login/login"));
        //            }

        $uid = $this->getAccountId();
        $uinfo = M('member')
            ->alias("t")
            ->field("t.*,b.*")
            ->join("world_level b", "t.level=b.level_id", 'LEFT')
            ->where(array("id" => $uid))
            ->find();

        //获取当前所有可返矿机
        $where_m = array(
            'um.user_id' => $user_id,
            'status' => 1,
            'source_type' => array('not in', [2, 4]),
        );
        $user_miner = M('world_users_miner')
            ->alias('um')
            ->field('um.*')
            ->where($where_m)
            ->select();

        $wait_income_dc = 0;//待收取DC
        $income_surplus = 0;//矿机总算力
        foreach ($user_miner as $val) {
//            $this_income_dc=$val['income_all']*$val['release_rate']/100;
//            $wait_income_dc += ($this_income_dc>$val['income_surplus'])?$val['income_surplus']:$this_income_dc;//剩余不够返等于剩余的
            $income_surplus += $val['income_surplus'];
        }

        //代收取
        $where = array('user_id' => $user_id);
        $today = strtotime("Today", time());
        $where['create_time'] = array('between', array($today, time()));
        $world_release = M('world_release')->where($where)->find();

        //代收取 dc
        $wait_income_dc = $world_release['surplus_dc'];

        //汇率
        $dc_cny_rate = worldCa('basic.dc_cny_rate');
        $wait_income_cny = $dc_cny_rate * $wait_income_dc;

        $day_data['wait_income_dc'] = sprintf("%.3f", $wait_income_dc);
        $day_data['wait_income_cny'] = sprintf("%.3f", $wait_income_cny);
        $day_data['income_surplus'] = sprintf("%.3f", $income_surplus);

        //dan当日已返收益
        $today = strtotime('Today');
        $where = array(
            'user_id' => $user_info['id'],
            'type' => array('in', array(9)),
            'change_time' => array('gt', $today)
        );
        //今日已返DC
        $today_back = M('world_dc_log')->field("sum(number) as total_number")->where($where)->find();

        //加速释放收益
        $where = array(
            'user_id' => $user_info['id'],
            'type' => array('in', array(3)),
            'change_time' => array('gt', $today)
        );
        $speed_income = M('world_dc_log')->field("sum(number) as total_number")->where($where)->find();
        $day_data['speed_income'] = $speed_income['total_number'];

        //我的矿机今日收益
        $today_income = $day_data['wait_income_dc'] + sprintf("%.3f", $today_back['total_number']);
        $day_data['today_income'] = sprintf("%.3f", $today_income);

        //我的运行矿机数量
        $day_data['miner_total_num'] = count($user_miner);

        //广告语
        // $news = worldCa('tips.news');
        $news = M('article')->where(['article_id' => 43])->find();
        $app_version = worldCa('basic.app_version');
        $this->assign('app_version', $app_version);


        $this->assign('day_data', $day_data);
        $this->assign('news', $news);

        $this->assign('uinfo', $uinfo);
        $system = tpCache("vpay_spstem");
        $this->assign('appVersion', $system['appVersion']);
        apilog("", time(), '首页');
        return $this->fetch();

    }

    public function baseUploadImg()
    {
        $user_id = $this->user_id;
        $data = I("post.");
        //头像更改
        $base64 = $data['head_img'];
        $dir = 'public/upload/profilePhoto/';

        apilog($data, $user_id, '上传头像');
        $result = base64_image_content($base64, $dir);

        if ($result) {
            $post['profilePhoto'] = $result;
            M('member')->where(array('id' => $user_id))->update($post);
            $result = ['status' => 1, 'data' => $result];
            $this->ajaxSuccess($result);
        }
    }


    public function test()
    {
        $min = 0.01;
        $max = 10;

        for ($i = 0; $i < 100; $i++) {
            $result = rand($min * 10000, $max * 10000);
            $result = $result / 10000;
            $result = sprintf("%.4f", $result);
            echo $result;
            echo "<br>";
        }


        return $this->fetch();
    }

    //算力页面
    public function world_power()
    {
        $user = session('account');
        $user_info = M('member')->where(array('id' => $user['id']))->find();
        $general_income = M('world_power_log')->where(array('user_id' => $user_info['id'], 'number' => array('gt', 0)))->sum('number');
        $total_expenditure = M('world_power_log')->where(array('user_id' => $user_info['id'], 'number' => array('lt', 0)))->sum('number');
        //收入条件
        $type_income_list = array(
            '1' => '激活矿机',
            '2' => '激活奖励',
            '3' => '采购矿机',
            '4' => '推广奖励',
        );
        //支出条件
        $type_expend_list = array(
            '5' => '日常衰减',
            '6' => '加速衰减',
            '7' => '矿机报废'
        );

        $this->assign('type_income_list', $type_income_list);
        $this->assign('type_expend_list', $type_expend_list);
        $this->assign('cny_proportion', worldCa('basic.dc_cny_rate'));
        $this->assign('user_info', $user_info);
        $this->assign('general_income', $general_income);
        $this->assign('total_expenditure', $total_expenditure);
        return $this->fetch();
    }

    public function ajax_world_power()
    {
        //数据
        $user_info = session('account');
        $state = I('state');
        $type = I('type');
        //查询条件
        $where = array();
        $where['user_id'] = $user_info['id'];
        if ($state == 'income' || empty($state)) {
            $where['number'] = array('gt', 0);
        } elseif ($state == 'expend') {
            $where['number'] = array('lt', 0);
        }

        if ($type) {
            $where['type'] = $type;
        }

        //分页数据
        $count = db('world_power_log')->where($where)->count();
        $Page = new AjaxPage($count, 10);
        $date = db('world_power_log')->where($where)->limit($Page->firstRow, $Page->listRows)->order('log_id desc')->select();
        //$show = $Page->show(); //暂时无用

        $this->assign('date', $date);
        return $this->fetch();
    }

    /**
     * 检查是否有新消息
     */
    public function check_new_notice()
    {
        $user_id = $this->user_id;
        $notice = M('user_notice')->where(array('user_id' => $user_id, 'is_tips' => 0))->order('id desc')->find();
        if (empty($notice)) {
            $this->ajaxError('');
        }
        $this->ajaxSuccess($notice);
    }

}
