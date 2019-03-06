<?php
/**
 * Created by PhpStorm.
 * User: asus
 * Date: 2018/10/9
 * Time: 18:45
 */

namespace app\admin\controller\World;

use app\admin\controller\Base;
use think\AjaxPage;
use think\Page;
use think\Db;

class World extends Base
{

    /**
     * 模拟释放
     * @return mixed
     */
    public function test_relsase()
    {
        $pdata = input('post.');

        $time = strtotime($pdata['release_date']);
        $WorldLogic = new \app\common\logic\WorldLogic();
        $WorldLogic->static_release($time);
        //管理员日志
        adminLog('模拟释放');
        $this->ajaxReturn(array('status' => 1, 'msg' => '释放成功！'));

    }

    public function index()
    {
        return $this->fetch();
    }

    public function system()
    {
        /*配置列表*/
        $group_list = [
            'basic' => '基本设置',
            'mine_set' => '矿场规则设置',
            'shell_set' => '沙滩规则设置',
            'spstem_sms' => '短信设置',
            'poster' => 'APP下载海报设置',
            'qr_poster' => '分享海报设置',
            'tips' => '提示语/广告语设置',
//            'recharge' => '收款设置',
//            'distribut_prize' => '分销奖项',
//            'release_rule' => '释放规则',
        ];
        $this->assign('group_list', $group_list);
        $inc_type = I('get.inc_type', 'basic');
        $this->assign('inc_type', $inc_type);
        $config = worldCa($inc_type);
        if ($inc_type == 'shop_info') {
            $province = M('region')->where(array('parent_id' => 0))->select();
            $city = M('region')->where(array('parent_id' => $config['province']))->select();
            $area = M('region')->where(array('parent_id' => $config['city']))->select();
            $this->assign('province', $province);
            $this->assign('city', $city);
            $this->assign('area', $area);
        }
        $this->assign('config', $config);//当前配置项
        //C('TOKEN_ON',false);
        return $this->fetch($inc_type);
    }

    /*
     * 新增修改配置
     */
    public function handle()
    {
        $param = I('post.');
        $inc_type = $param['inc_type'];
        unset($param['inc_type']);
        worldCa($inc_type, $param);

        //管理员日志
        adminLog('修改矿机配置(' . $inc_type . ')');

        $this->success("操作成功", U('World.World/system', array('inc_type' => $inc_type)));
    }

    /**
     * 等级列表
     * @return mixed
     */
    public function levelList()
    {
        $list = db('world_level')->select();
        $this->assign('list', $list);
        return $this->fetch();
    }

    /**
     * 角色详情
     * @return mixed
     * Author:Faramita
     */
    public function level()
    {
        $act = I('get.act', 'add');
        $this->assign('act', $act);
        $level_id = I('get.level_id');
        if ($level_id) {
            //获取处理好的配置数组
            //获取角色信息
            $level_info = db('world_level')->where('level_id=' . $level_id)->find();
            $this->assign('info', $level_info);

            //购买权益
            $buy_rights = explode(",", $level_info['buy_rights']);
            $this->assign('buy_rights', $buy_rights);

            //升级条件
            $up_condition = unserialize($level_info['up_condition']);
            $this->assign('up_condition', $up_condition);

            //释放比例
            $release_rate = unserialize($level_info['release_rate']);
            $this->assign('release_rate', $release_rate);
//            dump($release_rate);

        }
        //矿机列表
        $miner_list = db('world_miner')->where(array('type' => 1))->order('miner_price asc ,miner_id')->select();


        $this->assign('miner_list', $miner_list);
        return $this->fetch();
    }

    /**
     * 会员等级添加编辑删除
     */
    public function levelHandle()
    {
        $data = I('post.');

        //购买权益
        $buy_rights = $data['buy_rights'];
        $data['buy_rights'] = $buy_rights ? implode(",", $buy_rights) : 0;

        //升级条件
        $up_condition_one = $data['up_condition_one'];
        $up_condition_one['switch'] = isset($up_condition_one['switch']) ? $up_condition_one['switch'] : 0;
        $up_condition['one'] = $up_condition_one;
        $up_condition_two = $data['up_condition_two'];
        $up_condition_two['switch'] = isset($up_condition_two['switch']) ? $up_condition_two['switch'] : 0;
        $up_condition['two'] = $up_condition_two;
        $up_condition_three = $data['up_condition_three'];
        $up_condition_three['switch'] = isset($up_condition_three['switch']) ? $up_condition_one['switch'] : 0;
        $up_condition['three'] = $up_condition_three;
        $data['up_condition'] = serialize($up_condition);

        //释放比列
        $release_rate['release_rate_switch'] = $data['release_rate_switch'];
        $release_rate['distribut_number'] = $data['distribut_number'];
        $release_rate['distribut_level'] = $data['distribut_level'];
        $release_rate['rate'] = $data['rate'];
        $data['release_rate'] = serialize($release_rate);

        if ($data['act'] == 'add') {
            $r = db('world_level')->insert($data);
            if ($r !== false) {
                //存储条件配置
                //管理员日志
                adminLog('添加会员等级(' . $data['level_name'] . ')');
                $return = ['status' => 1, 'msg' => '添加成功', 'result' => ''];
            } else {
                $return = ['status' => 0, 'msg' => '添加失败，数据库未响应', 'result' => ''];
            }

        }
        if ($data['act'] == 'edit') {
            $r = db('world_level')->where('level_id=' . $data['level_id'])->update($data);
            if ($r !== false) {
                //存储条件配置
                //管理员日志
                adminLog('修改会员等级(ID:' . $data['level_id'] . ')');
                $return = ['status' => 1, 'msg' => '编辑成功', 'result' => ''];
            } else {
                $return = ['status' => 0, 'msg' => '编辑失败，数据库未响应', 'result' => ''];
            }

        }
        if ($data['act'] == 'del') {
            //检测是否有属于该角色的用户，且不是初始角色
            $check_role_del = db('member')->where(['level' => $data['level_id']])->select();
            if ($data['level_id'] == 1) {
                $return = ['status' => 0, 'msg' => '删除失败，游客等级不允许删除', 'result' => ''];
                $this->ajaxReturn($return);
            }
            if (empty($check_role_del) && $data['level_id'] != '1') {

                //删除角色
                $r = db('world_level')->where('level_id=' . $data['level_id'])->delete();
                //删除当前角色所有条件配置

                //删除当前角色其他配置

                if ($r !== false) {
                    //管理员日志
                    adminLog('删除会员等级(ID:' . $data['level_id'] . ')');
                    $return = ['status' => 1, 'msg' => '删除成功', 'result' => ''];
                } else {
                    $return = ['status' => 0, 'msg' => '删除失败，数据库未响应', 'result' => ''];
                }
            } else {
                $return = ['status' => 0, 'msg' => '删除失败，当前还有属于该角色的用户', 'result' => ''];
            }
        }
        $this->ajaxReturn($return);
    }


    /*
     * DC币明细
     * */
    public function dc_coin()
    {

        if(IS_POST){
            //判断分页时搜索条件
            $condition = I('condition');
            $search_key = I('search_key');
            $search = input("");

            switch ($condition) {
                case 1: //手机
                    $where['m.account'] = $search_key;
                    break;
                case 2: // 昵称
                    $where['m.nickname'] = $search_key;
                    break;
                case 3: // ID
                    $where['m.id'] = $search_key;
                    break;
                default:
                    break;
            }

            if ($search['start_time']&&$search['end_time']) {
                $search['start_time']=strtotime($search['start_time']);
                $search['end_time']=strtotime($search['end_time']);
                $where['t.change_time'] = array(array('gt', $search['start_time']), array('lt', $search['end_time']));
            }
            // 分页输入
            if (empty($pageSize)) {
                $pageSize = 10;
            }
            // 总条数
            $count = Db::name('world_dc_log')
                ->alias("t")->join("__MEMBER__ m ", " m.id=t.user_id", 'LEFT')
                ->where($where)
                ->count();
            $Page = new AjaxPage($count, 15);
            $show = $Page->show();

            // 进行分页数据查询
            $list = M('world_dc_log')
                ->alias("t")
                ->join("__MEMBER__ m ", "m.id=t.user_id", 'LEFT')
                ->field("t.*,m.nickname,m.account")
                ->where($where)
                ->limit($Page->firstRow . ',' . $Page->listRows)
                ->order('t.log_id DESC')
                ->select();
            if (!empty($list)) {
                foreach ($list as $k => $v) {
                    switch ($v['type']) {
                        case 1 :
                            $list[$k]['type_str'] = "平台添加";
                            break;
                        default:
                            $list[$k]['type_str'] = "收入";
                            break;
                    }

                }
            }

            // 统计
            $sum = M('world_dc_log')
                ->alias("t")->join("__MEMBER__ m", " m.id=t.user_id", 'LEFT')
                ->field("count(1) as number")
                ->where($where)
                ->order('t.log_id DESC')
                ->find();

            // 输出数据
            $this->assign('list', $list);
            $this->assign('sum', $sum);

            $this->assign('page', $show);// 赋值分页输出
            $this->assign('pager', $Page);
            return $this->fetch('ajax_dc_coin');
        }else{
            return $this->fetch();
        }

    }

    /*
     * shell明细
     * */
    public function shell()
    {
        if(IS_POST){
            //判断分页时搜索条件

            $condition = I('condition');
            $search_key = I('search_key');

            $search = input("");
            $this->assign('search',$search);

            switch ($condition) {
                case 1: //手机
                    $where['m.account'] = $search_key;
                    break;
                case 2: // 昵称
                    $where['m.nickname'] = $search_key;
                    break;
                case 3: // ID
                    $where['m.id'] = $search_key;
                    break;
                default:
                    break;
            }

            if ($search['start_time']&&$search['end_time']) {
                $search['start_time']=strtotime($search['start_time']);
                $search['end_time']=strtotime($search['end_time']);
                $where['t.change_time'] = array(array('gt', $search['start_time']), array('lt', $search['end_time']));
            }

            // 分页输入
            if (empty($pageSize)) {
                $pageSize = 10;
            }

            // 总条数
            $count = Db::name('world_shell_log')
                ->alias("t")->join("__MEMBER__ m ", " m.id=t.user_id", 'LEFT')
                ->where($where)
                ->count();
            $page = new Page($count, $pageSize);
            $show = $page->show();


            // 进行分页数据查询
            $list = M('world_shell_log')
                ->alias("t")
                ->join("__MEMBER__ m ", "m.id=t.user_id", 'LEFT')
                ->field("t.*,m.nickname,m.account")
                ->where($where)
                ->limit($page->firstRow . ',' . $page->listRows)
                ->order('t.log_id DESC')
                ->select();
            if (!empty($list)) {
                foreach ($list as $k => $v) {
                    switch ($v['type']) {
                        case 1 :
                            $list[$k]['type_str'] = "平台添加";
                            break;
                        default:
                            $list[$k]['type_str'] = "收入";
                            break;
                    }

                }
            }

            // 统计
            $sum = M('world_shell_log')
                ->alias("t")->join("__MEMBER__ m", " m.id=t.user_id", 'LEFT')
                ->field("count(1) as number")
                ->where($where)
                ->order('t.log_id DESC')
                ->find();

            // 输出数据
            $this->assign('list', $list);
            $this->assign('sum', $sum);

            $this->assign('page', $show);
            $this->assign('pager', $page);
        }
        return $this->fetch();
    }

    /*
     * 转入转出
     * */
    public function transfer()
    {

        $model = M('world_dc_transfer');
        $map = array();
        $mtype = I('mtype');

        $condition = I('condition');
        $search_key = I('search_key');
        switch ($condition) {
            case 1: //手机
                $map['m.account'] = array('like', "%$search_key%");
                break;
            case 2: // ID
                $map['t.id'] = $search_key;
                break;
            case 3: //昵称
                $map['m.nickname'] = array('like', "%$search_key%");
                break;
            case 4: //手机
                $map['tm.account'] = array('like', "%$search_key%");
                break;
            default:
                break;
        }

        if ($mtype == 1) {
            $map['stock'] = array('gt', 0);
        }
        if ($mtype == -1) {
            $map['stock'] = array('lt', 0);
        }
        $id = I('id');
        if ($id) {
            $map['id'] = array('like', "%$id%");
        }
        $ctime = urldecode(I('ctime'));
        if ($ctime) {
            $gap = explode(' - ', $ctime);
            $this->assign('start_time', $gap[0]);
            $this->assign('end_time', $gap[1]);
            $this->assign('ctime', $gap[0] . ' - ' . $gap[1]);
            $map['t.create_time'] = array(array('gt', strtotime($gap[0])), array('lt', strtotime($gap[1])));
        }
        $count = $model->alias("t")
            ->join("member m", "m.id=t.user_id", 'left')
            ->join("member tm", "tm.id=t.to_user_id", 'left')
            ->field("t.*,m.nickname mname,tm.nickname tmname")
            ->where($map)
            ->count();
        $Page = new Page($count, 20);
        $show = $Page->show();
        $this->assign('pager', $Page);
        $this->assign('page', $show);// 赋值分页输出
        $list = $model
            ->alias("t")
            ->join("member m", "m.id=t.user_id", 'left')
            ->join("member tm", "tm.id=t.to_user_id", 'left')
            ->field("t.*,m.nickname mname,tm.nickname tmname")
            ->where($map)
            ->order('t.id DESC')
            ->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $this->assign('list', $list);
        return $this->fetch();
    }

    /**
     * shell兑换明细
     */
    public function world_shell_exchange()
    {
        if(IS_POST){
            //判断分页时搜索条件
            $condition = I('condition');
            $search_key = I('search_key');
            $search = input("");

            switch ($condition) {
                case 1: //手机
                    $where['m.account'] = $search_key;
                    break;
                case 2: // 昵称
                    $where['m.nickname'] = $search_key;
                    break;
                case 3: // ID
                    $where['m.id'] = $search_key;
                    break;
                default:
                    break;
            }

            if ($search['start_time']&&$search['end_time']) {
                $search['start_time']=strtotime($search['start_time']);
                $search['end_time']=strtotime($search['end_time']);
                $where['ws.create_time'] = array(array('gt', $search['start_time']), array('lt', $search['end_time']));
            }

            // 分页输入
            if (empty($pageSize)) {
                $pageSize = 15;
            }

            // 总条数
            $count = Db::name('world_shell_exchange')
                ->alias("ws")->join("__MEMBER__ m ", " m.id=ws.user_id", 'LEFT')
                ->where($where)
                ->count();

            $Page = new AjaxPage($count, 15);
            $show = $Page->show();

            // 进行分页数据查询
            $list = M('world_shell_exchange')
                ->alias("ws")
                ->join("__MEMBER__ m ", "m.id=ws.user_id", 'LEFT')
                ->field("ws.*,m.nickname,m.account")
                ->where($where)
                ->limit($Page->firstRow . ',' . $Page->listRows)
                ->order('ws.id DESC')
                ->select();

            // 统计
            $sum = M('world_shell_exchange')
                ->alias("ws")->join("__MEMBER__ m", " m.id=ws.user_id", 'LEFT')
                ->field("count(1) as number")
                ->where($where)
                ->order('ws.id DESC')
                ->find();

            // 输出数据
            $this->assign('list', $list);
            $this->assign('sum', $sum);
            $this->assign('page', $show);// 赋值分页输出
            $this->assign('pager', $Page);
            return $this->fetch('ajax_world_shell_exchange');
        }else{
            return $this->fetch();
        }

    }

    /*
     * DC币释放明细
     * 
     */
    public function dc_release_log()
    {
        //判断分页时搜索条件
        return $this->fetch();
    }

    public function ajax_dc_release_log()
    {
        $begin = strtotime(I('add_time_begin'));
        $end = strtotime(I('add_time_end'));
        //判断分页时搜索条件
        $condition = I('condition');
        $search_key = I('search_key');
        // 类型
        $type = I('type');

        $where['a.type'] = array('in', [3, 6]);
        if ($type) {
            $where['a.type'] = $type;
        }

        switch ($condition) {
            case 1: //手机
                $where['m.account'] = array('like', '%' . $search_key . '%');
                break;
            case 2: // 昵称
                $where['m.nickname'] = array('like', '%' . $search_key . '%');
                break;
            case 3: // ID
                $where['a.order_sn'] = array('like', '%' . $search_key . '%');
                break;
            default:
                break;
        }

        if ($begin && $end) {
            $where['a.change_time'] = array('between', "$begin,$end");;
        } elseif ($begin && !$end) {
            $where['a.change_time'] = array('gt', $begin);
        } elseif (!$begin && $end) {
            $where['a.change_time'] = array('lt', $end);
        }


        // 总条数
        $count = M('world_dc_log')
            ->alias("a")
            ->join("__MEMBER__ m ", "m.id=a.user_id", 'LEFT')
            ->where($where)
            ->count();
        $Page = new AjaxPage($count, 15);
        $show = $Page->show();

        // 进行分页数据查询
        $list = M('world_dc_log')
            ->alias("a")
            ->join("__MEMBER__ m ", "m.id=a.user_id", 'LEFT')
            ->join("world_users_miner um ", "a.order_id=um.id", 'LEFT')
            ->join("world_miner wm ", "um.miner_id=wm.miner_id", 'LEFT')
            ->field("a.*,m.nickname,m.account,wm.miner_name")
            ->where($where)
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->order('a.log_id DESC')
            ->select();

        foreach ($list as $key => $value) {
        }

        // 输出数据
        $this->assign('list', $list);
        $this->assign('page', $show);// 赋值分页输出
        $this->assign('pager', $Page);
        return $this->fetch();
    }

    /**
     * DC币交易列表
     */
    public function dc_trade_list()
    {
        return $this->fetch();
    }

    public function ajax_dc_trade_list()
    {
        $begin = strtotime(I('add_time_begin'));
        $end = strtotime(I('add_time_end'));
        $status = I('status');
        $keyType = I("keytype");
        $keywords = I('keywords', '', 'trim');

        //搜索功能参数
        $where = array();
        if ($begin && $end) {
            $where['a.create_time'] = array('between', "$begin,$end");;
        } elseif ($begin && !$end) {
            $where['a.create_time'] = array('gt', $begin);
        } elseif (!$begin && $end) {
            $where['a.create_time'] = array('lt', $end);
        }

        if ($status) {
            $where['a.status'] = array('eq', $status);
        }

        switch ($keyType) {
            case "baccount":
                $where['m1.account'] = array('like', '%' . $keywords . '%');
                break;
            case "saccount":
                $where['m2.account'] = array('like', '%' . $keywords . '%');
                break;
            case "order_sn":
                $where['a.order_sn'] = array('like', '%' . $keywords . '%');
                break;
        }

        //订单数据
        $count = Db::name('world_dc_trade')->alias('a')
            ->join('member m1', 'a.buyer_user_id=m1.id', 'left')
            ->join('member m2', 'a.seller_user_id=m2.id', 'left')
            ->where($where)
            ->count();
        $Page = new AjaxPage($count, 15);
        $show = $Page->show();
        $orderList = Db::name('world_dc_trade')->alias('a')
            ->join('member m1', 'a.buyer_user_id=m1.id', 'left')
            ->join('member m2', 'a.seller_user_id=m2.id', 'left')
            ->field('a.*,m1.account as baccount,m1.nickname as bnickname,m1.profilePhoto as bprofilePhoto,m1.trade_num as btrade_num,m2.account as saccount,m2.nickname as snickname,m2.profilePhoto as sprofilePhoto,m2.trade_num as strade_num')
            ->where($where)->limit($Page->firstRow, $Page->listRows)->order('id desc')->select();

        $statusList = array(
            '1' => "待接单",
            '2' => "已接单",
            '3' => "已付款",
            '4' => "已完成",
            '-1' => "已取消",
        );
        $this->assign('orderList', $orderList);
        $this->assign('statusList', $statusList);
        $this->assign('page', $show);// 赋值分页输出
        $this->assign('pager', $Page);
        return $this->fetch();
    }

    /**
     * DC交易详情
     */
    public function trade_detail()
    {
        $id = I('id');

        $trade = Db::name('world_dc_trade')->alias('a')
            ->join('member m1', 'a.buyer_user_id=m1.id', 'left')
            ->join('member m2', 'a.seller_user_id=m2.id', 'left')
            ->field('a.*,m1.account as baccount,m1.nickname as bnickname,m1.profilePhoto as bprofilePhoto,m1.trade_num as btrade_num,m2.account as saccount,m2.nickname as snickname,m2.profilePhoto as sprofilePhoto,m2.trade_num as strade_num')
            ->where(array('a.id' => $id))->find();

        $statusList = array(
            '1' => "待接单",
            '2' => "已接单",
            '3' => "已付款",
            '4' => "已完成",
            '-1' => "已取消",
        );

        $pay_status = array(
            '1' => "支付宝",
            '2' => "微信",
            '3' => "银行卡",
        );

        if ($trade['pid'] == 0) {
            $where = array(
                'pid' => $trade['id'],
            );
            $orderList = Db::name('world_dc_trade')->alias('a')
                ->join('member m1', 'a.buyer_user_id=m1.id', 'left')
                ->join('member m2', 'a.seller_user_id=m2.id', 'left')
                ->field('a.*,m1.account as baccount,m1.nickname as bnickname,m1.profilePhoto as bprofilePhoto,m1.trade_num as btrade_num,m2.account as saccount,m2.nickname as snickname,m2.profilePhoto as sprofilePhoto,m2.trade_num as strade_num')
                ->where($where)->order('id desc')->select();
        }

        $this->assign('statusList', $statusList);
        $this->assign('pay_status', $pay_status);
        $this->assign('order', $trade);
        $this->assign('orderList', $orderList);
        return $this->fetch();
    }

    public function view_details()
    {
        $data = I('get.');


        $where = array();
        $where['m.id'] = $data['id'];

        //判断表
        if ($data['type'] == 1) {
            $model = M('world_dc_log');
        } elseif ($data['type'] == 2) {
            $model = M('world_shell_log');
        }

        $count = $model
            ->alias('c')
            ->join('member m', 'c.user_id = m.id', 'LEFT')
            ->where($where)
            ->count();
        $page = new Page($count, 10);
        $show = $page->show();

        $list = $model
            ->alias('c')
            ->field('c.*,m.id,m.account,m.nickname')
            ->join('member m', 'c.user_id = m.id', 'LEFT')
            ->where($where)
            ->limit($page->firstRow . ',' . $page->listRows)
            ->select();
        $this->assign('count', $count);
        $this->assign('type', $data['type']);
        $this->assign('id', $data['id']);
        $this->assign('list', $list);
        $this->assign('page', $show);
        return $this->fetch();
    }


    /**
     * 注册赠送Dc列表
     */
    public function giveshell()
    {
        return $this->fetch();
    }

    public function ajax_giveshell()
    {
        $begin = strtotime(I('add_time_begin'));
        $end = strtotime(I('add_time_end'));
        $status = I('status');
        $keyType = I("keytype");
        $keywords = I('keywords', '', 'trim');

        //搜索功能参数
        $where = array();
        if ($begin && $end) {
            $where['a.create_time'] = array('between', "$begin,$end");;
        } elseif ($begin && !$end) {
            $where['a.create_time'] = array('gt', $begin);
        } elseif (!$begin && $end) {
            $where['a.create_time'] = array('lt', $end);
        }

        if ($status) {
            $where['a.status'] = array('eq', $status);
        }

        switch ($keyType) {
            case "baccount":
                $where['m.account'] = array('like', '%' . $keywords . '%');
                break;
            case "nickname":
                $where['m.nickname'] = array('like', '%' . $keywords . '%');
                break;
        }

        //订单数据
        $count = Db::name('world_giveshell')->alias('a')
            ->join('member m', 'a.user_id=m.id', 'left')
            ->where($where)
            ->count();
        $Page = new AjaxPage($count, 15);
        $show = $Page->show();
        $orderList = Db::name('world_giveshell')->alias('a')
            ->join('member m', 'a.user_id=m.id', 'left')
            ->field('a.*,m.nickname,m.account')
            ->where($where)->limit($Page->firstRow, $Page->listRows)->order('id desc')->select();

        $this->assign('orderList', $orderList);
        $this->assign('page', $show);// 赋值分页输出
        $this->assign('pager', $Page);
        return $this->fetch();
    }

    /*
     * 钱包资产列表
     * */
    public function wallet_list()
    {
        //判断分页时搜索条件
        $condition = I('condition');
        $search_key = I('search_key');

        $where = array();

        // 总条数
        $count = Db::name('world_shell_log')
            ->alias("t")->join("__MEMBER__ m ", " m.id=t.user_id", 'LEFT')
            ->where($where)
            ->count();

        // 进行分页数据查询
        $list = M('world_wallet')
            ->alias("a")
            ->field("a.*")
            ->where($where)
            ->order('a.id')
            ->select();
        // 输出数据
        $this->assign('list', $list);
        $this->assign('count', $count);
        return $this->fetch();
    }

    /**
     * 资产添加编辑
     * @return mixed
     * Author:Faramita
     */
    public function wallet_addEdit()
    {
        $act = I('get.act', 'add');
        $this->assign('act', $act);
        $id = I('get.id');
        if ($id) {
            //获取角色信息
            $data = db('world_wallet')->where('id=' . $id)->find();
            $this->assign('info', $data);
        }
        return $this->fetch();
    }

    /**
     * 资产添加编辑删除
     */
    public function walletHandle()
    {
        $data = I('post.');
        if ($data['act'] == 'add') {
            $r = db('world_wallet')->add($data);
            if ($r !== false) {
                //管理员日志
                adminLog('添加资产信息(' . $data['level_name'] . ')');
                $return = ['status' => 1, 'msg' => '添加成功', 'result' => ''];
            } else {
                $return = ['status' => 0, 'msg' => '添加失败，数据库未响应', 'result' => ''];
            }
        }
        if ($data['act'] == 'edit') {
            $r = db('world_wallet')->where(array('id' => $data['id']))->update($data);
            if ($r !== false) {
                //存储条件配置
                //管理员日志
                adminLog('修改资产信息(ID:' . $data['id'] . ')');
                $return = ['status' => 1, 'msg' => '编辑成功', 'result' => ''];
            } else {
                $return = ['status' => 0, 'msg' => '编辑失败，数据库未响应', 'result' => ''];
            }
        }
        if ($data['act'] == 'del') {
            //删除资产
            $r = db('world_wallet')->where('id=' . $data['id'])->delete();
            if ($r !== false) {
                //管理员日志
                adminLog('删除资产信息(ID:' . $data['id'] . ')');
                $return = ['status' => 1, 'msg' => '删除成功', 'result' => ''];
            } else {
                $return = ['status' => 0, 'msg' => '删除失败，数据库未响应', 'result' => ''];
            }
        }
        $this->ajaxReturn($return);
    }

    /**
     * 个人算力排行榜
     */
    public function rank_power()
    {

        $where = array(
            'a.isDelete' => 2
        );
        $list = M('member')->alias('a')
            ->join('world_users_miner um', 'um.user_id=a.id and um.status=1 and um.income_surplus>0', 'left')
            ->where($where)
            ->field('sum(income_surplus) as income_surplus,a.id,a.nickname,a.account,a.level')
            ->order('income_surplus desc,level desc,a.id')
            ->group('a.id')
            ->limit('0,100')
            ->select();

        //等级列表
        $levelList = M('world_level')->order('level_id')->column('level_id,level_name');
        $this->assign('levelList', $levelList);

        $this->assign('list', $list);
        return $this->fetch();
    }

    /**
     * 个人DC排行榜
     */
    public function rank_dc()
    {

        $where = array(
            'a.isDelete' => 2
        );
        $list = M('member')->alias('a')
            ->where($where)
            ->field('sum(dc_coin+frozen_dc) as dc_num,a.id,a.nickname,a.account,a.level')
            ->order('dc_num desc,level desc,a.id')
            ->group('a.id')
            ->limit('0,100')
            ->select();


        //等级列表
        $levelList = M('world_level')->order('level_id')->column('level_id,level_name');
        $this->assign('levelList', $levelList);

        $this->assign('list', $list);
        return $this->fetch();
    }

    /**
     * 个人算力排行榜
     */
    public function rank_distribut()
    {

        $where = array(
            'a.isDelete' => 2
        );
        $list = M('member')->alias('a')
            ->join('member m1', 'm1.parentId=a.id and m1.isDelete=2', 'left')
            ->where($where)
            ->field('count(m1.id) as distribut_num,a.id,a.nickname,a.account,a.level')
            ->order('distribut_num desc,a.level desc,id')
            ->group('a.id')
            ->limit('0,100')
            ->select();

        //等级列表
        $levelList = M('world_level')->order('level_id')->column('level_id,level_name');
        $this->assign('levelList', $levelList);

        $this->assign('list', $list);
        return $this->fetch();
    }

    /*
 *算力明细
 *
 */
    public function power_log()
    {
        //判断分页时搜索条件
        $type_list = array(
            '1' => '激活矿机',
            '2' => '激活奖励',
            '3' => '采购矿机',
            '4' => '推广奖励',
            '5' => '日常衰减',
            '6' => '加速衰减',
            '7' => '矿机报废'
        );
        $this->assign('type_list', $type_list);
        return $this->fetch();
    }

    public function ajax_power_log()
    {
        $begin = strtotime(I('add_time_begin'));
        $end = strtotime(I('add_time_end'));
        //判断分页时搜索条件
        $condition = I('condition');
        $search_key = I('search_key');
        // 类型
        $type = I('type');
        if ($type) {
            $where['a.type'] = $type;
        }

        switch ($condition) {
            case 1: //手机
                $where['m.account'] = array('like', '%' . $search_key . '%');
                break;
            case 2: // 昵称
                $where['m.nickname'] = array('like', '%' . $search_key . '%');
                break;
            case 3: // ID
                $where['o.order_sn'] = array('like', '%' . $search_key . '%');
                break;
            default:
                break;
        }

        if ($begin && $end) {
            $where['a.change_time'] = array('between', "$begin,$end");;
        } elseif ($begin && !$end) {
            $where['a.change_time'] = array('gt', $begin);
        } elseif (!$begin && $end) {
            $where['a.change_time'] = array('lt', $end);
        }


        // 总条数
        $count = M('world_power_log')
            ->alias("a")
            ->join("__MEMBER__ m ", "m.id=a.user_id", 'LEFT')
            ->join("world_users_miner um ", "a.order_id=case when a.type in(5,6,7) then um.id else um.order_id end", 'LEFT')
            ->join("world_order o ", "um.order_id=o.order_id", 'LEFT')
            ->join("world_miner wm ", "um.miner_id=wm.miner_id", 'LEFT')
            ->where($where)
            ->count();
        $Page = new AjaxPage($count, 15);
        $show = $Page->show();

        // 进行分页数据查询
        $list = M('world_power_log')
            ->alias("a")
            ->join("__MEMBER__ m ", "m.id=a.user_id", 'LEFT')
            ->join("world_users_miner um ", "a.order_id=case when a.type in(5,6,7) then um.id else um.order_id end", 'LEFT')
            ->join("world_order o ", "um.order_id=o.order_id", 'LEFT')
            ->join("world_miner wm ", "um.miner_id=wm.miner_id", 'LEFT')
            ->field("a.*,m.nickname,m.account,wm.miner_name,o.order_sn")
            ->where($where)
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->order('a.log_id DESC')
            ->select();

        foreach ($list as $key => $value) {
        }

        // 输出数据
        $this->assign('list', $list);
        $this->assign('page', $show);// 赋值分页输出
        $this->assign('pager', $Page);
        return $this->fetch();
    }


}