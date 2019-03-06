<?php

namespace app\common\logic;

use think\Exception;
use think\Model;
use think\Db;
use app\common\model\Member;
use think\redis\driver\RedisDB;

class WorldLogic extends Model
{
    const PARENT_ALGEBRA = 10;//查找父级的代数

    protected $user_id = 0;//user_id
    protected $user_info = [];//用户信息
    protected $number_of_miners = 0;//下级矿机数量统计

    protected $release_miner_list = [];//储存可释放的矿机
    protected $user_ids = [];//本次操作的用户id
    protected $release_user_info = [];//本次操作用户的信息

    protected $release_data = [];//记录用户释放的总算力

    /**
     * 矿机静态释放
     */
    public function get_release_miner($release_time = null)
    {
        $now_time = time();
        $today = strtotime('Today', time());//今天的时间戳
        $now_date = $release_time ? $release_time : $today;//购买时间小于此日期的时间可返

        $where = array(
            'status' => 1,
            'income_surplus' => array('gt', 0),
        );
        $where['add_time'] = array('lt', $now_date);
        $where['release_time'] = array('lt', $now_date);

        $user_ids = M('world_users_miner')->where($where)->order('user_id asc,id asc')->group('user_id')->column('user_id');

        $this->user_ids = $user_ids;
        $datalist = array();
        if ($user_ids) {
            //释放用户的算力信息
            $release_user_info = M('member')->where(array('id' => array('in', $user_ids)))->column('id,powers');
            $this->release_user_info = $release_user_info;
            $where['user_id'] = array('in', $user_ids);
            $datalist = M('world_users_miner')
                ->field('id,miner_id,user_id,income_all,release_rate,release_days,income_surplus,scrap_days')
                ->where($where)->order('user_id asc,id asc')->select();
        }
        foreach ($datalist as $val) {
            $this->release_miner_list[] = $val;
        }
        unset($user_ids);
        unset($release_user_info);
        unset($datalist);
    }


    public function start_release_miner1()
    {
        //矿场设置
        $mine_set = worldCa('mine_set');

        //可释放的用户矿机列表
        $user_miner_list = $this->release_miner_list;
        if (empty($user_miner_list)) {
            return false;
        }

        //释放用户的算力信息
        $release_user_info = $this->release_user_info;

        $minerList = M('world_miner')->where(array('is_del' => 0))->column('miner_id,short_name');

        $error = 0;
        //开始事务
        db()->startTrans();

        $release_data = array();
        $power_log = array();
        //开始循环释放
        foreach ($user_miner_list as $val) {
            $release_num = $val['income_all'] * $val['release_rate'] / 100;//释放的金额
            $release_days = $val['release_days'] + 1;//已释放天数

            //保留3位小数
            $release_num = floor($release_num * 1000) / 1000;

            $status = 1;//矿机状态
            if ($release_num > $val['income_surplus']) {
                $release_num = $val['income_surplus'];
                $status = 2;
            }
            if ($val['scrap_days'] <= $release_days) {
                $status = 2;
            }

            //可释放的dc
            $release_data[$val['user_id']]['dc_num'] += $release_num;

            $miner_update_data = array(
                'status' => $status,
                'update_time' => time(),
                'release_time' => time(),
                'income_surplus' => $val['income_surplus'] - $release_num,
                'release_days' => $release_days,
            );
            //更新矿机数据
            $res = Db::name('world_users_miner')->where(['id' => $val['id']])->update($miner_update_data);
            if (!$res) {
                $error++;
            }

            //更新会员算力值
            $before = $release_user_info[$val['user_id']];
            $after = $before - $release_num;
            $power_log[] = array(
                'user_id' => $val['user_id'],
                'number' => -$release_num,
                'before' => $before,
                'after' => $after,
                'change_time' => time(),
                'desc' => '日常衰减-' . $minerList[$val['miner_id']],
                'order_id' => $val['id'],
                'order_sn' => 0,
                'type' => 5,
                'union_id' => 0
            );

            $release_user_info[$val['user_id']] = $after;

            //矿机报废
            if ($status == 2) {
                if (($val['income_surplus'] - $release_num) > 0) {
                    $sub_income = $val['income_surplus'] - $release_num;
                    //更新会员算力值
                    $before = $release_user_info[$val['user_id']];
                    $after = $before - $sub_income;
                    $power_log[] = array(
                        'user_id' => $val['user_id'],
                        'number' => -$sub_income,
                        'before' => $before,
                        'after' => $after,
                        'change_time' => time(),
                        'desc' => '矿机报废-' . $minerList[$val['miner_id']],
                        'order_id' => $val['id'],
                        'order_sn' => 0,
                        'type' => 5,
                        'union_id' => 0
                    );
                    $release_user_info[$val['user_id']] = $after;
                }
            }
        }

        //组装矿场油滴数据
        $release_dc_list = [];
        foreach ($release_data as $key => $val) {
            $release_dc = array(
                'user_id' => $key,
                'dc_num' => $val['dc_num'],
                'surplus_dc' => $val['dc_num'],
                'oil_num' => $mine_set['oil_num'],
                'oil_num_float' => $mine_set['oil_num_float'],
                'create_time' => time(),
            );
            $release_dc_list[] = $release_dc;
        }

        //更新会员算力数据
        foreach ($release_user_info as $key => $val) {
            $up_member = M('member')->where(array('id' => $key))->update(array('powers' => $val));
            if (!$up_member) {
                $error++;
            }
        }

        //记录矿场油滴数据
        $res1 = M('world_release')->insertAll($release_dc_list);
        if (!$res1) {
            $error++;
        }
        //记录算力日志
        $res2 = M('world_power_log')->insertAll($power_log);
        if (!$res2) {
            $error++;
        }
        if ($error > 0) {
            //记录日志
            db()->rollback();
            apilog($user_miner_list, "失败", '执行定时释放');
            return true;
        }

        db()->commit();
        apilog($user_miner_list, "成功", '执行定时释放');
        return true;
    }

    public function start_release_miner()
    {
        //矿场设置
        $mine_set = worldCa('mine_set');

        //可释放的用户矿机列表
        $user_miner_list = $this->release_miner_list;
        if (empty($user_miner_list)) {
            return false;
        }

        //释放用户的算力信息
        $release_user_info = $this->release_user_info;

        $minerList = M('world_miner')->where(array('is_del' => 0))->column('miner_id,short_name');

        $error = 0;
        //开始事务
        Db::startTrans();

        $release_data = array();
        $power_log = array();
        $miner_update_data = array();

        //开始循环释放
        foreach ($user_miner_list as $val) {
            $release_num = $val['income_surplus'] * $val['release_rate'] / 100;//释放的金额
            $release_days = $val['release_days'] + 1;//已释放天数

            //保留3位小数
            $release_num = floor($release_num * 1000) / 1000;

            $status = 1;//矿机状态
            if ($release_num > $val['income_surplus']) {
                $release_num = $val['income_surplus'];
                $status = 2;
            }
            if (($val['income_surplus'] - $release_num) < 1) {
                $status = 2;
            }
            if ($val['scrap_days'] <= $release_days) {
                $status = 2;
            }

            //可释放的dc
            $release_data[$val['user_id']]['dc_num'] += $release_num;

            $miner_update_data[$val['id']] = array(
                'id' => $val['id'],
                'status' => $status,
                'update_time' => time(),
                'release_time' => time(),
                'income_surplus' => $val['income_surplus'] - $release_num,
                'release_days' => $release_days,
            );

            //更新矿机数据
//            $res = Db::name('world_users_miner')->where(['id' => $val['id']])->update($miner_update_data);
//            if (!$res) {
//                $error++;
//            }

            //更新会员算力值
            $before = $release_user_info[$val['user_id']];
            $after = $before - $release_num;
            $power_log[] = array(
                'user_id' => $val['user_id'],
                'number' => -$release_num,
                'before' => $before,
                'after' => $after,
                'change_time' => time(),
                'desc' => '日常衰减-' . $minerList[$val['miner_id']],
                'order_id' => $val['id'],
                'order_sn' => 0,
                'type' => 5,
                'union_id' => 0
            );

            $release_user_info[$val['user_id']] = $after;

            //矿机报废
            if ($status == 2) {
                if (($val['income_surplus'] - $release_num) > 1) {
                    $sub_income = $val['income_surplus'] - $release_num;
                    //更新会员算力值
                    $before = $release_user_info[$val['user_id']];
                    $after = $before - $sub_income;
                    $power_log[] = array(
                        'user_id' => $val['user_id'],
                        'number' => -$sub_income,
                        'before' => $before,
                        'after' => $after,
                        'change_time' => time(),
                        'desc' => '矿机报废-' . $minerList[$val['miner_id']],
                        'order_id' => $val['id'],
                        'order_sn' => 0,
                        'type' => 7,
                        'union_id' => 0
                    );
                    $release_user_info[$val['user_id']] = $after;
                }
            }
        }


        //更新用户矿机数据
        $miner_update_datas = array_chunk($miner_update_data, 500);
        foreach ($miner_update_datas as $key => $val_miner) {
            $sql = $this->create_update_sql($val_miner);
            if ($sql) {
                try {
                    $res = Db::query($sql);
                } catch (Exception $e) {
                    $error++;
                    //记录日志
                    Db::rollback();
                    apilog("", "失败", '执行定时释放');
                    return false;
                }

//                if(!$res){
//                    $error++;
//                }
            }
        }

        //组装矿场油滴数据
        $release_dc_list = [];
        foreach ($release_data as $key => $val) {
            $release_dc = array(
                'user_id' => $key,
                'dc_num' => $val['dc_num'],
                'surplus_dc' => $val['dc_num'],
                'oil_num' => $mine_set['oil_num'],
                'oil_num_float' => $mine_set['oil_num_float'],
                'create_time' => time(),
            );
            $release_dc_list[] = $release_dc;

            //更新会员算力数据
            $up_m_data['powers'] = array('exp', 'powers-' . $val['dc_num']);
            $up_member = Db::name('member')->where(array('id' => $key))->update($up_m_data);
            if (!$up_member) {
                $error++;
            }
        }

        //记录矿场油滴数据
        $release_dc_lists = array_chunk($release_dc_list, 500);
        unset($release_dc_list);
        foreach ($release_dc_lists as $val_release) {
            $res1 = Db::name('world_release')->insertAll($val_release);
            if (!$res1) {
                $error++;
            }
        }

        //记录算力明细
        $power_logs = array_chunk($power_log, 1000);
        unset($power_log);
        foreach ($power_logs as $val_log) {
            $res2 = Db::name('world_power_log')->insertAll($val_log);
            if (!$res2) {
                $error++;
            }
        }

        unset($release_dc_lists);
        unset($power_logs);

        if ($error > 0) {
            //记录日志
            Db::rollback();
            apilog("", "失败", '执行定时释放');
            return false;
        }

        Db::commit();
        apilog("", "成功", '执行定时释放');
        return true;
    }

    /**
     * 组装更新sql
     */
    public function create_update_sql($data)
    {
        if (empty($data)) {
            return false;
        }
        $sql = "UPDATE `tp_world_users_miner` SET update_time=" . time() . ",release_time=" . time() . ", ";

        $up_status = "";
        $up_income_surplus = "";
        $up_release_days = "";
        $ids = [];
        foreach ($data as $val) {
            $up_status .= " WHEN " . $val['id'] . " THEN " . $val['status'] . " ";
            $up_income_surplus .= " WHEN " . $val['id'] . " THEN " . $val['income_surplus'] . " ";
            $up_release_days .= " WHEN " . $val['id'] . " THEN " . $val['release_days'] . " ";
            $ids[] = $val['id'];
        }
        $sql .= " status = CASE id" . $up_status . " END, income_surplus = CASE id " . $up_income_surplus . " END, release_days = CASE id " . $up_release_days . " END";

        $ids = implode(",", $ids);
        $sql .= " WHERE id IN(" . $ids . ")";

        return $sql;
    }


    /**
     * 矿机静态释放
     */
    public function static_release($release_time = null)
    {

        $now_time = time();
        $today = strtotime('Today', time());//今天的时间戳
        $now_date = $release_time ? $release_time : $today;//购买时间小于此日期的时间可返

        $mine_set = worldCa('mine_set');
        $where = array(
            'a.status' => 1,
            'a.income_surplus' => array('gt', 0),
        );
        $where['a.add_time'] = array('lt', $now_date);

        //可释放的矿机
        $user_miner_list = Db::name('world_users_miner')->alias('a')
            ->join('world_miner m', 'a.miner_id=m.miner_id', 'left')
            ->join('world_order o', 'a.order_id=o.order_id', 'left')
            ->field('a.id,a.user_id,a.miner_id,a.order_id,a.scrap_days,a.release_days,a.release_rate,a.income_all,a.income_surplus,a.status,m.miner_name,m.short_name,o.order_sn')
            ->where($where)->order('a.add_time asc')->select();

        //记录日志
        apilog($user_miner_list, '执行定时释放');

        $release_data = array();
        //开始循环释放
        foreach ($user_miner_list as $val) {
            $release_num = $val['income_all'] * $val['release_rate'] / 100;//释放的金额
            $release_days = $val['release_days'] + 1;//已释放天数

            //保留3位小数
            $release_num = floor($release_num * 1000) / 1000;

            $status = 1;//矿机状态
            if ($release_num > $val['income_surplus']) {
                $release_num = $val['income_surplus'];
                $status = 2;
            }
            if ($val['scrap_days'] <= $release_days) {
                $status = 2;
            }

            //可释放的dc
            $release_data[$val['user_id']]['dc_num'] += $release_num;

            $miner_update_data = array(
                'status' => $status,
                'update_time' => time(),
                'income_surplus' => $val['income_surplus'] - $release_num,
                'release_days' => $release_days,
            );
            //更新矿机数据
            $res = Db::name('world_users_miner')->where(['id' => $val['id']])->update($miner_update_data);

            //日常释放算力
            $res = accountPowerLog($val['user_id'], -$release_num, '日常衰减-' . $val['short_name'], $val['id'], $val['order_sn'], 5, 1);
            //矿机报废
            if ($status == 2) {
                if (($val['income_surplus'] - $release_num) > 0) {
                    $sub_income = $val['income_surplus'] - $release_num;
                    //日常释放算力
                    $res = accountPowerLog($val['user_id'], -$sub_income, '矿机报废-' . $val['short_name'], $val['id'], $val['order_sn'], 7, 1);
                }
            }

        }

        $release_dc_list = [];
        foreach ($release_data as $key => $val) {
            $release_dc = array(
                'user_id' => $key,
                'dc_num' => $val['dc_num'],
                'oil_num' => $mine_set['oil_num'],
                'oil_num_float' => $mine_set['oil_num_float'],
                'create_time' => $now_time,
            );
            $release_dc_list[] = $release_dc;
        }
        $res = M('world_release')->insertAll($release_dc_list);
        return true;

    }


    /**
     * 获取用户信息
     * @param $user_id //用户id
     * @return array
     * Author:Faramita
     */
    public function getUserInfo($user_id)
    {

        $data = M("member")->where(array('id' => $user_id))->find();
        return $data;
    }

    public function UpgradeMethod($user_info)
    {
        //获取升级所需数据
        $level_data = M('world_level')->where(array('level_id' => ($user_info['level'] + 1)))->find();//上级的规则
        $up_condition = unserialize($level_data['up_condition']);
        $level_data_max = M('world_level')->max('level_id');

        //验证自己是否有资格升级
        $self_upgrade = $this->selfEscalationJudgment($up_condition, $user_info);

        //父级验证升级
        $parent_upgrade = $this->ParentUpgradeJudge($user_info);

        //判断等级是否上限，不上限给他升级
        if ($self_upgrade && ($user_info['level'] + 1 <= $level_data_max)) {
            $result = M('member')->where(array('id' => $user_info['id']))->setInc('level', 1);
        }
    }

    /*************************************激活升级 END************************************/

    /**
     * 激活条件验证
     * @param array $up_conditions 升级总条件
     * @param array $user 用户信息
     * @return array
     * Author:Faramita
     */
    public function selfEscalationJudgment($up_conditions, $user)
    {

        //无升级条件时 默认升级
        if ($up_conditions['one']['switch'] == 0 && $up_conditions['two']['switch'] == 0 && $up_conditions['three']['switch'] == 0) {
            return true;
        }

        //条件一
        if ($up_conditions['one']['switch'] == 1) {

            $condition_one = $this->UpgradeConditionOne($up_conditions['one'], $user);
        }

        //条件二
        if ($up_conditions['two']['switch'] == 1) {
            $this->UpgradeConditionTwo($up_conditions['two'], $user, $up_conditions['two']['distribut_level']);

            //是否符合条件数量
            if ($this->number_of_miners >= $up_conditions['two']['miner_number']) {
                $condition_two = true;
            } else {
                $condition_two = false;
            }

            //属性重置
            $this->number_of_miners = 0;
        } else {
            //无规则默认true
            $condition_two = true;
        }

        //条件三
        if ($up_conditions['three']['switch'] == 1) {
            $this->UpgradeConditionTwo($up_conditions['three'], $user, $up_conditions['three']['distribut_level']);
            if ($this->number_of_miners >= $up_conditions['three']['miner_number']) {
                $condition_three = true;
            } else {
                $condition_three = false;
            }

            //属性重置
            $this->number_of_miners = 0;
        } else {
            //无规则默认true
            $condition_three = true;
        }

        if ($condition_one && $condition_two && $condition_three) {
            return true;
        }
        return false;

    }

    /**
     * 条件一判断 || 统计用户矿机数量
     * @param $up_one 升级条件
     * @param $sign 标识（1、统计 0、返回布尔值）
     * Author:Faramita
     */
    public function UpgradeConditionOne($up_one, $user, $sign = 0)
    {
        $where = array(
            'miner_id' => $up_one['miner_id'],
            'user_id ' => $user['id'],
            'status' => 1
        );
        $count = M('world_users_miner')->where($where)->count();

        if ($sign) {
            $result = $count;
        } else {
            $result = $count >= $up_one['miner_number'] ? true : false;
        }

        return $result;
    }

    /**
     * 无限极分类统计下级对应矿机数据量
     * @param array $up_one 升级条件
     * @param array $user 用户信息
     * @param int $identification 层数递减标识
     * Author:Faramita
     */
    public function UpgradeConditionTwo($up_one, $user, $identification)
    {

        //标识等于0时退出
        if ($identification == 0) {
            return true;
        }

        $son_data = M("member")->where(array('parentId' => $user['id']))->select();

        //无下级的时候退出
        if (empty($son_data)) return true;

        //层数递减
        $iden = $identification - 1;

        foreach ($son_data as $k => $v) {
            //统计矿机
            $number = $this->UpgradeConditionOne($up_one, $v, 1);
            $this->number_of_miners += $number;

            //回调下级
            $this->UpgradeConditionTwo($up_one, $v, $iden);
        }

    }

    /*************************************父代升级 END************************************/
    /**
     * 父代升级
     * @param array $user 用户信息
     * @param int $identification 层数递减标识
     * Author:Faramita
     */
    public function ParentUpgradeJudge($user)
    {
        static $trace;
        $parent_algebra = self::PARENT_ALGEBRA;

        //层数结束或者没有父级
        if ($trace == $parent_algebra || empty($user['parentId'])) return false;

        //获取父级信息
        $parent_info = $this->getUserInfo($user['parentId']);

        $result = $this->MulticondJudgment($parent_info);

        $trace++;
        return $this->ParentUpgradeJudge($parent_info);

    }

    /**
     * 父代升级
     * @param array $user 用户信息
     * @param int $identification 层数递减标识
     * Author:Faramita
     */
    public function MulticondJudgment($user_info)
    {
        //所有等级信息
        $level_info = M('world_level')->where(array('level_id' => array('gt', $user_info['level'])))->select();
        $level_data_max = M('world_level')->max('level_id');

        //循环判断是否符合升级
        $upgrade_level = "";
        foreach ($level_info as $k => $v) {
            $up_condition = unserialize($v['up_condition']);

            $finish = $this->selfEscalationJudgment($up_condition, $user_info);

            if ($finish) {
                $upgrade_level = $v['level_id'];
            }
        }
        //通过则升级等级
        if ($upgrade_level && $upgrade_level <= $level_data_max) {
            $result = M('member')->where(array('id' => $user_info['id']))->update(array('level' => $upgrade_level));
            return true;
        } else {
            return false;
        }

    }

    /*************************************下n代矿机数量、收益统计 END************************************/

    /**
     * 首页我的矿池统计
     * @param array $user_info 用户信息
     * @param int $identification 层数递减标识
     * Author:Faramita
     */
    public function OrePoolStatistics($user_info)
    {

        if (!$user_info) return false;

        //调用查找所有下级
        $this->usersTenGeneration($user_info, 10);

        //全局变量获取总数
        $amount = $GLOBALS['amount'];

        unset($GLOBALS['amount']);//避免资源浪费

        //无下级或者出错
        if (!$amount) return false;;
        //统计下级所拥有的矿机
        $where_m = array(
            'user_id' => array('in', $amount),
            'status' => 1,
            'source_type' => array('not in', [2, 4])
        );
        $miner_list = M('world_users_miner')->where($where_m)->select();

        $wait_income_dc = 0;//待收取DC
        foreach ($miner_list as $val) {
            $this_income_dc = $val['income_all'] * $val['release_rate'] / 100;
            $wait_income_dc += ($this_income_dc > $val['income_surplus']) ? $val['income_surplus'] : $this_income_dc;//剩余不够返等于剩余的
        }


        //统计下级日收益
        $today = strtotime('Today');
        $where = array(
            'user_id' => array('in', $amount),
            'type' => array('in', array(3, 6)),
            'change_time' => array('gt', $today)
        );
        $daily_earnings = M('world_dc_log')->field("sum(number) as number_all")->where($where)->find();

        $today_income = $wait_income_dc + $daily_earnings['number_all'];

        return array('miner_amount' => count($miner_list), 'daily_earnings' => sprintf("%.3f", $today_income));

    }

    /**
     * 矿池统计
     * @param array $user_info 用户信息
     * @param int $identifying 层数递减标识
     * Author:Faramita
     */
    public function usersTenGeneration($user_info, $identifying = 10)
    {

        //层数标识等于0时
        if ($identifying == 0) return true;

        //无下级的时候退出
        $son_data = M("member")->field('id')->where(array('parentId' => $user_info['id']))->select();

        if (!$son_data) return false;

        //层数递减
        $iden = $identifying - 1;

        foreach ($son_data as $k => $v) {
            $GLOBALS['amount'][] = $v['id'];
            $this->usersTenGeneration($v, $iden);
        }

    }

    /*************************************条件判断统计 END************************************/
    /**
     * 矿池统计
     * @param array $user_info 用户信息
     * @param int $identifying 层数递减标识
     * Author:Faramita
     */
    public function statisticsLowerMiners($up_conditions, $user)
    {
        //无升级条件时 默认升级
        if ($up_conditions['one']['switch'] == 0 && $up_conditions['two']['switch'] == 0 && $up_conditions['three']['switch'] == 0) {
            return true;
        }
        $cumulative_miner = '';//升级累计总数
        $Need_miner = '';//需求累计总数
        //条件一
        if ($up_conditions['one']['switch'] == 1) {
            $one_number = $this->UpgradeConditionOne($up_conditions['one'], $user, 1);
            if ($one_number <= $up_conditions['one']['miner_number']) {
                $statistics['one'] = $one_number;
            } else {
                $statistics['one'] = $up_conditions['one']['miner_number'];
            }
            $statistics['Need_miner'] += $statistics['one'];
            $statistics['cumulative_miner'] += $up_conditions['one']['miner_number'];
        }

        //条件二
        if ($up_conditions['two']['switch'] == 1) {
            $this->UpgradeConditionTwo($up_conditions['two'], $user, $up_conditions['two']['distribut_level']);

            //是否符合条件数量
            if ($this->number_of_miners <= $up_conditions['two']['miner_number']) {

                $statistics['two'] = $this->number_of_miners;
            } else {
                $statistics['two'] = $up_conditions['two']['miner_number'];
            }

            $statistics['Need_miner'] += $statistics['two'];
            $statistics['cumulative_miner'] += $up_conditions['two']['miner_number'];
            //属性重置
            $this->number_of_miners = 0;
        }

        //条件三
        if ($up_conditions['three']['switch'] == 1) {
            $this->UpgradeConditionTwo($up_conditions['three'], $user, $up_conditions['three']['distribut_level']);
            if ($this->number_of_miners <= $up_conditions['three']['miner_number']) {
                $statistics['three'] = $this->number_of_miners;
            } else {
                $statistics['three'] = $up_conditions['three']['miner_number'];
            }

            $statistics['Need_miner'] += $statistics['three'];
            $statistics['cumulative_miner'] += $up_conditions['three']['miner_number'];
            //属性重置
            $this->number_of_miners = 0;
        }

        if ($statistics) {
            return $statistics;
        }

        return false;
    }

    /*************************************下十代 && 下一代 各类型矿机统计END************************************/

    public function typeMinerStatistics($user_info)
    {
        if (!$user_info) return false;

        //调用查找所有下级
        $this->usersTenGeneration($user_info, 10);

        //全局变量获取总数
        $amount = $GLOBALS['amount'];
        unset($GLOBALS['amount']);//避免资源浪费

        //矿机数据
        $miner_list = M('world_miner')->field('miner_id,miner_name,short_name')->where(array('type' => 1))->select();
        $types_statistical = array();

        //统计下十代所有矿机
        $types_statistical['ten_amount_miner'] = M('world_users_miner')->where(array('user_id' => array('in', $amount), 'status' => 1, 'source_type' => array('in', array(1, 3))))->count();

        //统计类型矿机数
        foreach ($miner_list as $k => $v) {

            //统计下十代所有类型
            $ten_leader_miner = M('world_users_miner')->where(array('user_id' => array('in', $amount), 'status' => 1, 'miner_id' => $v['miner_id'], 'source_type' => array('in', array(1, 3))))->count();
            $types_statistical['ten_leader_miner'][] = array('short_name' => $v['short_name'], 'number' => $ten_leader_miner);

        }

        if ($types_statistical) {
            return $types_statistical;
        }

        return false;
    }

    /**
     * 取消过期交易订单
     */
    public function cancel_trade_order()
    {
        $cancel_time = worldCa('basic.cancel_time');
        $now_time = time();
        $where = array(
            'status' => 2,
        );
        $where['pid'] = array('gt', 0);
        $diff_time = time() - $cancel_time * 60;
        $where['update_time'] = array('lt', $diff_time);
        $trade_list = Db::name('world_dc_trade')->where($where)->order('id')->select();

        $ids = array();
        $pids = array();
        foreach ($trade_list as $val) {
            $ids[] = $val['id'];
            if ($val['type'] == 1) {
                $res1 = Db::name('member')->where(array('id' => $val['seller_user_id']))->setDec('frozen_dc', $val['number']);
                $res2 = accountDcLog($val['seller_user_id'], $val['number'], 'DC交易取消', $val['id'], $val['order_sn'], 8, 1);
            }
            $pids[] = $val['pid'];
        }

        //取消接单订单
        if ($ids) {
            $update_data = array(
                'status' => -1,
                'update_time' => $now_time,
            );
            $res = Db::name('world_dc_trade')->whereIn('id', $ids)->update($update_data);
        }
        //释放父级订单
        if ($pids) {
            $update_data = array(
                'status' => 1,
            );
            $res = Db::name('world_dc_trade')->whereIn('id', $pids)->update($update_data);
        }
    }

}