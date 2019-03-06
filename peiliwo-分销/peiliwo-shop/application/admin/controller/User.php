<?php

namespace app\admin\controller;

use app\admin\logic\OrderLogic;
use think\AjaxPage;
use think\Page;
use think\Verify;
use think\Db;
use app\admin\logic\UsersLogic;
use think\Loader;

class User extends Base
{
    /**
     * 更改会员等级  Lu
     */
    public function level_update()
    {
        $user_id = I('user_id');
        if (!$user_id > 0) $this->ajaxReturn(['status' => 0, 'msg' => "参数有误"]);
        $user = M('users')->field('user_id,nickname,user_money,level,agent_city')->where('user_id', $user_id)->find();
        if (IS_POST) {
            $level = I('post.level');
            $desc = I('post.desc');
            $agent_city = I('post.agent_city');
            if (!$level)
                $this->ajaxReturn(['status' => 0, 'msg' => "请选择会员等级"]);

            M('agency_area')->where(array('user_id' => $user_id))->delete();// 删掉之前的数据 
            // start
            if ($level == 5 || $level == 6) {
                $post = I('post.');
                $area_list = $post['area_list'];//代理地区
                $areaList = Db::name('region')->where('id', 'in', $area_list)->select(); // 代理地区的信息
                if (empty($areaList)) { // 未选择代理地区
                    $this->ajaxReturn(['status' => 0, 'msg' => '请选择该用户代理的地区']);
                }
                if ($level == 5) {
                    $regionLevel = 3;
                    $res = '区代只能代理区哦!'; // 选择的省市区不统一时提示文字
                    $gencyType = 'area_id'; // 数据库需要插入的字段
                } elseif ($level == 6) {
                    $regionLevel = 2;
                    $res = '市代只能代理市哦!'; // 选择的省市区不统一时提示文字
                    $gencyType = 'city_id'; // 数据库需要插入的字段
                }
                // 市代只能代理市 区代只能代理区
                foreach ($areaList as $key => $value) {
                    if ($value['level'] !== $regionLevel) {
                        $this->ajaxReturn(['status' => 0, 'msg' => $res]);
                    }
                    // 有人代理了该区域
                    $has_other_agent = M('agency_area')->where(array($gencyType => $value['id']))->find();
                    if ($has_other_agent && $has_other_agent['user_id'] != $user_id) {
                        $this->ajaxReturn(['status' => 0, 'msg' => '操作失败，' . $value['name'] . '已经有人代理!']);
                    }
                }
                // 要新增的数据
                $agencyArea = [
                    'user_id' => $user_id,
                    'level' => $level,
                    'add_time' => time()
                ];
                // 循环入库
                foreach ($area_list as $key => $value) {
                    $agencyArea[$gencyType] = $value;
                    M("agency_area")->insert($agencyArea);
                }
            }

            $data['level'] = $level;
            M('users')->where('user_id', $user_id)->update($data);
            adminLog("更改“" . $user['nickname'] . "”等级 ：" . $user['level'] . "->" . $level . "，备注：" . $desc);
            $this->ajaxReturn(['status' => 1, 'msg' => "操作成功", 'url' => U("Admin/User/index")]);
            exit;
        }
        $province = M('region')->where(array('parent_id' => 0))->select();
        $this->assign('province', $province);

        if ($user['level'] == 5 || $user['level'] == 6) {
            if ($user['level'] == 5) {
                $where = 'area_id'; // 查询不同的字段
            } elseif ($user['level'] == 6) {
                $where = 'city_id';
            }
            $area_list = M('agency_area')// 主表
            ->alias('t1')
                ->field('t2.name,t2.id')
                ->join('region t2 ', ' t1.' . $where . '=t2.id', 'LEFT')
                ->where('t1.user_id=' . $user_id . ' and t1.' . $where . ' is not null ')
                ->select();
            $this->assign('area_list', $area_list);  //代理的地区列表
        }
        $level_list = M('user_level')->order('deposit asc')->select();
        $this->assign('level_list', $level_list);
        $this->assign('user_id', $user_id);
        $this->assign('user', $user);
        return $this->fetch();
    }

    /**
     * 会员升级列表
     */
    public function user_level_up()
    {
        $create_time2 = date('Y-m-d', strtotime('-1 year')) . ' - ' . date('Y-m-d', strtotime('+1 day'));
        $create_time3 = explode(' - ', $create_time2);
        $this->assign('start_time', $create_time3[0]);
        $this->assign('end_time', $create_time3[1]);
        return $this->fetch();
    }

    public function ajax_user_level_up()
    {
        // 搜索条件
        $condition = array();
        I('nickname') ? $nickname = I('nickname') : false;
        I('status') ? $status = I('status') : false;
        $create_time = I('create_time');
        $create_time = str_replace("+", " ", $create_time);
        $create_time2 = $create_time ? $create_time : date('Y-m-d', strtotime('-1 year')) . ' - ' . date('Y-m-d', strtotime('+1 day'));
        $create_time3 = explode(' - ', $create_time2);
        $condition['a.create_time'] = array("between", array(strtotime($create_time3[0]), strtotime($create_time3[1])));
        $condition['a.admin_del'] = 0;
        if ($nickname) {
            $condition['u.nickname'] = array('like', '%' . $nickname . '%');
        }
        if (I('status') != '' && I('status') != null) {
            $condition['a.status'] = I('status');
        }

        $model = M('user_level_up');
        $count = $model->alias('a')
            ->join('users u', 'a.user_id=u.user_id', 'left')
            ->where($condition)->count();
        $Page = new AjaxPage($count, 10);

        $data_list = $model->alias('a')
            ->join('users u', 'a.user_id=u.user_id', 'left')
            ->field('a.*,u.nickname')
            ->where($condition)->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();

//        echo $model->getLastsql();
//        die;

        $level_list = M('user_level')->order('deposit asc')->column('level_id,level_name');

        $status_list = array('0' => '待审核', '1' => '审核通过', '2' => '审核不通过');

        $show = $Page->show();
        $this->assign('data_list', $data_list);
        $this->assign('level_list', $level_list);
        $this->assign('status_list', $status_list);
        $this->assign('page', $show);// 赋值分页输出
        $this->assign('pager', $Page);
        return $this->fetch();
    }

    /**
     * 等级升级操作管理
     * @return mixed
     */
    public function level_up_handle()
    {
        $data = I('post.');

        $level_up = M('user_level_up')->where(array('id' => $data['id']))->find();
        $user = M('users')->where(array('user_id' => $level_up['user_id']))->find();
        Db::startTrans();
        if ($data['type'] == 'status') {
            if ($data['status'] == 1) {
                if ($level_up['level_id'] == $user['level']) {
                    Db::rollback();
                    $return = ['status' => 0, 'msg' => '操作失败，该会员已达到当前等级', 'result' => ''];
                    $this->ajaxReturn($return);
                }
            }

            $res = M('user_level_up')->where(array('id' => $data['id']))->update(array('status' => $data['status'], 'update_time' => time()));
            if (!$res) {
                Db::rollback();
                $return = ['status' => 0, 'msg' => '操作失败，请稍后重试', 'result' => ''];
                $this->ajaxReturn($return);
            }

            if ($data['status'] == 1) {
                $res1 = M('users')->where(array('user_id' => $level_up['user_id']))->update(array('level' => $level_up['level_id']));
                if (!$res1) {
                    Db::rollback();
                    $return = ['status' => 0, 'msg' => '操作失败，请稍后重试', 'result' => ''];
                    $this->ajaxReturn($return);
                }
            }
        }
        if ($data['type'] == 'del') {
            $res = M('user_level_up')->where(array('user_id' => $level_up['user_id']))->update(array('admin_del' => 1));
            if (!$res) {
                Db::rollback();
                $return = ['status' => 0, 'msg' => '操作失败，请稍后重试', 'result' => ''];
                $this->ajaxReturn($return);
            }
        }
        Db::commit();
        $return = ['status' => 1, 'msg' => '操作成功！', 'result' => ''];
        $this->ajaxReturn($return);

    }


    public function search_users()
    {
        $user_id = input('user_id');
        $tpl = input('tpl', 'search_users');
        $id = input('id', 0);
        $where = array();
        $where1 = array();

        $user = M('users')->where(array('user_id' => $user_id))->find();
        $my_distribtion = M('users')->whereOr(array('first_leader' => $user_id))->whereOr(array('second_leader' => $user_id))->whereOr(array('third_leader' => $user_id))->column('user_id');
        array_push($my_distribtion, $user['user_id']);

        $where['user_id'] = array('not in', $my_distribtion);

        if ($id) {
            $where1['user_id'] = $id;
        }


        $model = M('users');
        $count = $model->where($where)->where($where1)->count();
        $Page = new Page($count, 5);
        //  搜索条件下 分页赋值
        $userList = $model->where($where)->where($where1)->order('user_id')->limit($Page->firstRow . ',' . $Page->listRows)->select();
//        $show = $Page->show();
        $this->assign('page', $Page);
        $this->assign('goodsList', $userList);
        return $this->fetch($tpl);
    }


    public function index()
    {
        return $this->fetch();
    }

    /**
     * 会员列表
     */
    public function ajaxindex()
    {
        // 搜索条件
        $condition = array();
//        I('mobile') ? $condition['mobile'] = I('mobile') : false;
//        I('email') ? $condition['email'] = I('email') : false;
        $keywords = trim(I('keywords'));

        I('first_leader') && ($condition['first_leader'] = I('first_leader')); // 查看一级下线人有哪些
        I('second_leader') && ($condition['second_leader'] = I('second_leader')); // 查看二级下线人有哪些
        I('third_leader') && ($condition['third_leader'] = I('third_leader')); // 查看三级下线人有哪些
        $sort_order = I('order_by') . ' ' . I('sort');
        session('sort_order', $sort_order);


        if (I('keytype') != "" && $keywords) {
            switch (I('keytype')) {
                case 'user_id':
                    $condition['user_id'] = $keywords;
                    break;
                case 'nickname':
                    $condition['nickname'] = array('like', '%' . $keywords . '%');
                    break;
                case 'mobile':
                    $condition['mobile'] = array('like', '%' . $keywords . '%');
                    break;
                case 'emial':
                    $condition['email'] = array('like', '%' . $keywords . '%');
                    break;
            }
        }

        $model = M('users');
        $count = $model->where($condition)->count();
        $Page = new AjaxPage($count, 15);


        $userList = $model->where($condition)->order($sort_order)->limit($Page->firstRow . ',' . $Page->listRows)->select();

        if (I('keytype') != "" && $keywords) {
            switch (I('keytype')) {
                case 'user_id':
                    $condition['user_id'] = $keywords;
                    break;
                case 'nickname':
                    $condition['nickname'] = $keywords;
                    break;
                case 'mobile':
                    $condition['mobile'] = $keywords;
                    break;
                case 'emial':
                    $condition['email'] = $keywords;
                    break;
            }
        }
        //  搜索条件下 分页赋值
        foreach ($condition as $key => $val) {
            $Page->parameter[$key] = urlencode($val);
        }

        $user_id_arr = get_arr_column($userList, 'user_id');
        if (!empty($user_id_arr)) {
            $first_leader = DB::query("select first_leader,count(1) as count  from __PREFIX__users where first_leader in(" . implode(',', $user_id_arr) . ")  group by first_leader");
            $first_leader = convert_arr_key($first_leader, 'first_leader');

            $second_leader = DB::query("select second_leader,count(1) as count  from __PREFIX__users where second_leader in(" . implode(',', $user_id_arr) . ")  group by second_leader");
            $second_leader = convert_arr_key($second_leader, 'second_leader');

            $third_leader = DB::query("select third_leader,count(1) as count  from __PREFIX__users where third_leader in(" . implode(',', $user_id_arr) . ")  group by third_leader");
            $third_leader = convert_arr_key($third_leader, 'third_leader');
        }
        $this->assign('first_leader', $first_leader);
        $this->assign('second_leader', $second_leader);
        $this->assign('third_leader', $third_leader);
        $show = $Page->show();
        $this->assign('userList', $userList);
        $this->assign('level', M('user_level')->getField('level_id,level_name'));
        $this->assign('page', $show);// 赋值分页输出
        $this->assign('pager', $Page);
        return $this->fetch();
    }

    /**
     * 会员详细信息查看
     */
    public function detail()
    {
        $uid = I('get.id');
        $user = D('users')->where(array('user_id' => $uid))->find();
        if (!$user)
            exit($this->error('会员不存在'));
        if (IS_POST) {
            //  会员信息编辑
            $password = I('post.password');
            $password2 = I('post.password2');
            $first_leader = I('post.first_leader');

            if ($password != '' && $password != $password2) {
                exit($this->error('两次输入密码不同'));
            }
            if ($password == '' && $password2 == '') {
                unset($_POST['password']);
            } else {
                $_POST['password'] = encrypt($_POST['password']);
            }

            if (!empty($_POST['email'])) {
                $email = trim($_POST['email']);
                $c = M('users')->where("user_id != $uid and email = '$email'")->count();
                $c && exit($this->error('邮箱不得和已有用户重复'));
            }

            if (!empty($_POST['mobile'])) {
                $mobile = trim($_POST['mobile']);
                $c = M('users')->where("user_id != $uid and mobile = '$mobile'")->count();
                $c && exit($this->error('手机号不得和已有用户重复'));
            }

            //更新分销关系
            if ($user['first_leader'] != $first_leader) {
                $result = $this->change_distribution($uid, $first_leader);
                if ($result['status'] == 0) {
                    exit($this->error($result['msg']));
                }
            }


            $row = M('users')->where(array('user_id' => $uid))->save($_POST);
            if ($row)
                exit($this->success('修改成功'));

            if ($result['status'] == 1) {
                exit($this->success('修改成功'));
            }
            exit($this->error('未作内容修改或修改失败'));
        }

        $user['first_lower'] = M('users')->where("first_leader = {$user['user_id']}")->count();
        $user['second_lower'] = M('users')->where("second_leader = {$user['user_id']}")->count();
        $user['third_lower'] = M('users')->where("third_leader = {$user['user_id']}")->count();


        $agent_city = M('region')->where(array('id' => $user['agent_city']))->find();
        $agent_city['province'] = M('region')->where(array('id' => $agent_city['parent_id']))->getField('name');

        $first_leader = M("users")->where(array('user_id' => $user['first_leader']))->find();
        $this->assign('first_leader', $first_leader);


        $this->assign('user', $user);
        $this->assign('level', M('user_level')->getField('level_id,level_name'));
        $this->assign('agent_city', $agent_city);
        return $this->fetch();
    }


    /**
     * 更改会员的上级   Lu
     * @param int $user_id 被改用户
     * @param int $first_leader 上级用户
     * @return array
     */
    public function change_distribution($user_id = 0, $first_leader = 0)
    {

        $user = D('users')->where(array('user_id' => $user_id))->find();

        if ($user_id == $first_leader) {
            return array('status' => 0, 'msg' => '不能把自己设为上级');
        }

        $my_distribtion = M('users')->whereOr(array('first_leader' => $user_id))->whereOr(array('second_leader' => $user_id))->whereOr(array('third_leader' => $user_id))->column('user_id');
        $first_leader_users = D('users')->where(array('user_id' => $first_leader))->find();


        $check_leader = check_leader($user_id, $first_leader);
        if ($check_leader) {
            return array('status' => 0, 'msg' => '不能把自己的下级设为上级');
        }

//        if ($my_distribtion) {
//            if (in_array($first_leader, $my_distribtion)) {
//                return array('status' => 0, 'msg' => '不能把自己的下级设为上级');
//            }
//        }

        $new_leader['first_leader'] = $first_leader;
        $new_leader['second_leader'] = $first_leader_users['first_leader'] ? $first_leader_users['first_leader'] : 0;
        $new_leader['third_leader'] = $first_leader_users['second_leader'] ? $first_leader_users['second_leader'] : 0;

        //我的一级下级
        $my_first_distribution = M('users')->where(array('first_leader' => $user_id))->column('user_id');
        //我的二级下级
        $my_second_distribution = M('users')->where(array('second_leader' => $user_id))->column('user_id');
        //我的三级下级
        $my_third_distribution = M('users')->where(array('third_leader' => $user_id))->column('user_id');

        //更改我的一级下级
        if ($my_first_distribution) {
            $data_first = array(
                'second_leader' => $new_leader['first_leader'],
                'third_leader' => $new_leader['second_leader'],
            );
            $res_first = M('users')->where(array('user_id' => array('in', $my_first_distribution)))->save($data_first);
        }

        //更改我的二级下级
        if ($my_second_distribution) {
            $data_second = array(
                'third_leader' => $new_leader['first_leader'],
            );
            $res_second = M('users')->where(array('user_id' => array('in', $my_second_distribution)))->save($data_second);
        }

        $res1 = M('users')->where(array('user_id' => $user_id))->update($new_leader);

        return array('status' => 1, 'msg' => '修改成功');
    }


    public function add_user()
    {
        if (IS_POST) {
            $data = I('post.');
            $user_obj = new UsersLogic();
            $res = $user_obj->addUser($data);
            if ($res['status'] == 1) {
                $this->success('添加成功', U('User/index'));
                exit;
            } else {
                $this->error('添加失败,' . $res['msg'], U('User/index'));
            }
        }
        return $this->fetch();
    }

    public function export_user()
    {
        $keywords = trim(I('keywords'));
        $sort_order = session('sort_order');
        $strTable = '<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">会员ID</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="100">会员昵称</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">会员等级</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">手机号</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">邮箱</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">注册时间</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">最后登陆</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">余额</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">佣金</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">积分</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">累计消费</td>';
        $strTable .= '</tr>';
        $count = M('users')->count();
        $p = ceil($count / 5000);
        for ($i = 0; $i < $p; $i++) {
            $start = $i * 5000;
            $end = ($i + 1) * 5000;
            if (I('keytype') != "" && $keywords) {
                switch (I('keytype')) {
                    case 'user_id':
                        $condition['user_id'] = $keywords;
                        break;
                    case 'nickname':
                        $condition['nickname'] = array('like', '%' . $keywords . '%');
                        break;
                    case 'mobile':
                        $condition['mobile'] = array('like', '%' . $keywords . '%');
                        break;
                    case 'emial':
                        $condition['email'] = array('like', '%' . $keywords . '%');
                        break;
                }
            }
            $sort_order = $sort_order ? $sort_order : 'user_id';

            $userList = M('users')->where($condition)->order($sort_order)->limit($start . ',' . $end)->select();
            if (is_array($userList)) {
                foreach ($userList as $k => $val) {
                    $strTable .= '<tr>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['user_id'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['nickname'] . ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['level'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['mobile'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['email'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . date('Y-m-d H:i', $val['reg_time']) . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . date('Y-m-d H:i', $val['last_login']) . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['user_money'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['distribut_money'] . ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['pay_points'] . ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['total_amount'] . ' </td>';
                    $strTable .= '</tr>';
                }
                unset($userList);
            }
        }
        $strTable .= '</table>';
        downloadExcel($strTable, 'users_' . $i);
        exit();
    }

    /**
     * 用户收货地址查看
     */
    public function address()
    {
        $uid = I('get.id');
        $lists = D('user_address')->where(array('user_id' => $uid))->select();
        $regionList = get_region_list();
        $this->assign('regionList', $regionList);
        $this->assign('lists', $lists);
        return $this->fetch();
    }

    /**
     * 删除会员
     */
    public function delete()
    {
        $uid = I('get.id');
        $row = M('users')->where(array('user_id' => $uid))->delete();
        if ($row) {
            $this->success('成功删除会员');
        } else {
            $this->error('操作失败');
        }
    }

    /**
     * 删除会员
     */
    public function ajax_delete()
    {
        $uid = I('id');
        if ($uid) {
            $row = M('users')->where(array('user_id' => $uid))->delete();
            if ($row !== false) {
                $res = M('agency_area')->where(array('user_id' => $uid))->delete();
                $this->ajaxReturn(array('status' => 1, 'msg' => '删除成功', 'data' => ''));

            } else {
                $this->ajaxReturn(array('status' => 0, 'msg' => '删除失败', 'data' => ''));
            }
        } else {
            $this->ajaxReturn(array('status' => 0, 'msg' => '参数错误', 'data' => ''));
        }
    }

    /**
     * 账户资金记录
     */
    public function account_log()
    {
        $user_id = I('get.id');
        //获取类型
        $type = I('get.type');
        //获取记录总数
        $count = M('account_log')->alias('a')
            ->join('order o', 'a.order_sn=o.order_sn', 'left')
            ->where(array('a.user_id' => $user_id))->count();
        $page = new Page($count);
        $lists = M('account_log')->alias('a')
            ->join('order o', 'a.order_sn=o.order_sn', 'left')
            ->field('a.*,o.order_id')
            ->where(array('a.user_id' => $user_id))->order('change_time desc')->limit($page->firstRow . ',' . $page->listRows)->select();

        $this->assign('user_id', $user_id);
        $this->assign('page', $page->show());
        $this->assign('lists', $lists);
        return $this->fetch();
    }

    /**
     * 账户资金调节
     */
    public function account_edit()
    {
        $user_id = I('user_id');
        if (!$user_id > 0) $this->ajaxReturn(['status' => 0, 'msg' => "参数有误"]);
        $user = M('users')->field('user_id,user_money,frozen_money,pay_points,is_lock')->where('user_id', $user_id)->find();
        if (IS_POST) {
            $desc = I('post.desc');
            if (!$desc)
                $this->ajaxReturn(['status' => 0, 'msg' => "请填写操作说明"]);
            //加减用户资金
            $m_op_type = I('post.money_act_type');
            $user_money = I('post.user_money/f');
            $user_money = $m_op_type ? $user_money : 0 - $user_money;
            //加减用户积分
            $p_op_type = I('post.point_act_type');
            $pay_points = I('post.pay_points/d');
            $pay_points = $p_op_type ? $pay_points : 0 - $pay_points;
            //加减冻结资金
            $f_op_type = I('post.frozen_act_type');
            $revision_frozen_money = I('post.frozen_money/f');
            if ($revision_frozen_money != 0) {    //有加减冻结资金的时候
                $frozen_money = $f_op_type ? $revision_frozen_money : 0 - $revision_frozen_money;
                $frozen_money = $user['frozen_money'] + $frozen_money;    //计算用户被冻结的资金
                if ($f_op_type == 1 and $revision_frozen_money > $user['user_money']) {
                    $this->ajaxReturn(['status' => 0, 'msg' => "用户剩余资金不足！！"]);
                }
                if ($f_op_type == 0 and $revision_frozen_money > $user['frozen_money']) {
                    $this->ajaxReturn(['status' => 0, 'msg' => "冻结的资金不足！！"]);
                }
                $user_money = $f_op_type ? 0 - $revision_frozen_money : $revision_frozen_money;    //计算用户剩余资金
                M('users')->where('user_id', $user_id)->update(['frozen_money' => $frozen_money]);
            }
            if (accountLog($user_id, $user_money, $pay_points, $desc, 0)) {
                $this->ajaxReturn(['status' => 1, 'msg' => "操作成功", 'url' => U("Admin/User/account_log", array('id' => $user_id))]);
            } else {
                $this->ajaxReturn(['status' => -1, 'msg' => "操作失败"]);
            }
            exit;
        }
        $this->assign('user_id', $user_id);
        $this->assign('user', $user);
        return $this->fetch();
    }

    public function recharge()
    {
        $timegap = urldecode(I('timegap'));
        $nickname = I('nickname');
        $start_time = I('start_time') ? I('start_time') : date('Y-m-d 0:0:0', strtotime('-7 day', time()));
        $end_time = I('end_time') ? I('end_time') : date('Y-m-d 0:0:0', time());
        if ($timegap) {

            $begin = strtotime($start_time);
            $end = strtotime($end_time);
            $map['ctime'] = array('between', array(strtotime($begin), strtotime($end)));
        }
        if ($nickname) {
            $map['nickname'] = array('like', "%$nickname%");
        }
        $count = M('recharge')->where($map)->count();
        $page = new Page($count);
        $lists = M('recharge')->where($map)->order('ctime desc')->limit($page->firstRow . ',' . $page->listRows)->select();
        $this->assign('page', $page->show());
        $this->assign('pager', $page);
        $this->assign('lists', $lists);

        if ($timegap) {
            $map = explode(',', $timegap);
        } else {
            $map[0] = date('Y-m-d 0:0:0', strtotime('-7 day', time()));
            $map[1] = date('Y-m-d 0:0:0', time());
        }

        $this->assign('timegap', $map);
        $this->assign('nickname', $nickname);
        return $this->fetch();
    }

    public function level()
    {
        $act = I('get.act', 'add');
        $this->assign('act', $act);
        $level_id = I('get.level_id');
        if ($level_id) {
            $level_info = D('user_level')->where('level_id=' . $level_id)->find();
            $this->assign('info', $level_info);
        }
        //获取分类商品信息
        $GoodsLogic = new \app\admin\logic\GoodsLogic();
        $identity_list = $GoodsLogic->type_identity_list();

        $level_list = M('user_level')->column('level_id,identity_id');

        //vip1系列产品
        $vip1_goods_list = M('goods')->where(array('identity_id' => $level_list['3']))->order('goods_id')->select();
//        dump($vip1_goods_list);
        $vip2_goods_list = M('goods')->where(array('identity_id' => $level_list['4']))->order('goods_id')->select();
//        dump($vip2_goods_list);
        $vip3_goods_list = M('goods')->where(array('identity_id' => $level_list['5']))->order('goods_id')->select();
//        dump($vip3_goods_list);

        $this->assign('identity_list', $identity_list);
        $this->assign('vip1_goods_list', $vip1_goods_list);
        $this->assign('vip2_goods_list', $vip2_goods_list);
        $this->assign('vip3_goods_list', $vip3_goods_list);
        return $this->fetch();
    }

    public function levelList()
    {
        $Ad = M('user_level');
        $p = $this->request->param('p');
        $res = $Ad->order('level_id')->page($p . ',10')->select();
        if ($res) {
            foreach ($res as $val) {
                $list[] = $val;
            }
        }
        $this->assign('list', $list);
        $count = $Ad->count();
        $Page = new Page($count, 10);
        $show = $Page->show();
        $this->assign('page', $show);
        return $this->fetch();
    }

    /**
     * 会员等级添加编辑删除
     */
    public function levelHandle()
    {
        $data = I('post.');
        $userLevelValidate = Loader::validate('UserLevel');
        $return = ['status' => 0, 'msg' => '参数错误', 'result' => ''];//初始化返回信息
        if ($data['act'] == 'add') {
            if (!$userLevelValidate->batch()->check($data)) {
                $return = ['status' => 0, 'msg' => '添加失败', 'result' => $userLevelValidate->getError()];
            } else {
                if ($data['level_id'] > 2) {
                    $has_identity = M('user_level')->where(array('identity_id' => $data['identity_id'], 'level_id' => array('neq', $data['level_id'])))->find();
                    if ($has_identity) {
                        $return = ['status' => 0, 'msg' => '添加失败，所选升级条件已被其他等级关联，不可重复设置', 'result' => ''];
                        $this->ajaxReturn($return);
                    }
                }
                $r = D('user_level')->add($data);
                if ($r !== false) {
                    $return = ['status' => 1, 'msg' => '添加成功', 'result' => $userLevelValidate->getError()];
                } else {
                    $return = ['status' => 0, 'msg' => '添加失败，数据库未响应', 'result' => ''];
                }
            }
        }
        if ($data['act'] == 'edit') {
            if ($data['level_id'] > 2) {
                $has_identity = M('user_level')->where(array('identity_id' => $data['identity_id'], 'level_id' => array('neq', $data['level_id'])))->find();
                if ($has_identity) {
                    $return = ['status' => 0, 'msg' => '添加失败，所选升级条件已被其他等级关联，不可重复设置', 'result' => ''];
                    $this->ajaxReturn($return);
                }
            }
            if (!$userLevelValidate->scene('edit')->batch()->check($data)) {
                $return = ['status' => 0, 'msg' => '编辑失败', 'result' => $userLevelValidate->getError()];
            } else {
                $r = D('user_level')->where('level_id=' . $data['level_id'])->save($data);
                if ($r !== false) {
                    $return = ['status' => 1, 'msg' => '编辑成功', 'result' => $userLevelValidate->getError()];
                } else {
                    $return = ['status' => 0, 'msg' => '编辑失败，数据库未响应', 'result' => ''];
                }
            }
        }
        if ($data['act'] == 'del') {

            //是否有该等级的会员  Lu
            $level_count = M('users')->where(array('level' => $data['level_id']))->find();
            if (!empty($level_count)) {
                $return = ['status' => 0, 'msg' => '删除失败，存在该等级的会员，不允许删除', 'result' => ''];
                $this->ajaxReturn($return);
            }

            $r = D('user_level')->where('level_id=' . $data['level_id'])->delete();

            if ($r !== false) {
                $return = ['status' => 1, 'msg' => '删除成功', 'result' => ''];
            } else {
                $return = ['status' => 0, 'msg' => '删除失败，数据库未响应', 'result' => ''];
            }
        }
        $this->ajaxReturn($return);
    }

    /**
     * 搜索用户名
     */
    public function search_user()
    {
        $search_key = trim(I('search_key'));
        if (strstr($search_key, '@')) {
            $list = M('users')->where(" email like '%$search_key%' ")->select();
            foreach ($list as $key => $val) {
                echo "<option value='{$val['user_id']}'>{$val['email']}</option>";
            }
        } else {
            $list = M('users')->where(" mobile like '%$search_key%' ")->select();
            foreach ($list as $key => $val) {
                echo "<option value='{$val['user_id']}'>{$val['mobile']}</option>";
            }
        }
        exit;
    }

    /**
     * 分销树状关系
     */
    public function ajax_distribut_tree()
    {
        $list = M('users')->where("first_leader = 1")->select();
        return $this->fetch();
    }

    /**
     *
     * @time 2016/08/31
     * @author dyr
     * 发送站内信
     */
    public function sendMessage()
    {
        $user_id_array = I('get.user_id_array');
        $users = array();
        if (!empty($user_id_array)) {
            $users = M('users')->field('user_id,nickname')->where(array('user_id' => array('IN', $user_id_array)))->select();
        }
        $this->assign('users', $users);
        return $this->fetch();
    }

    /**
     * 发送系统消息
     * @author dyr
     * @time  2016/09/01
     */
    public function doSendMessage()
    {
        $call_back = I('call_back');//回调方法
        $text = I('post.text');//内容
        $type = I('post.type', 0);//个体or全体
        $admin_id = session('admin_id');
        $users = I('post.user/a');//个体id
        $message = array(
            'admin_id' => $admin_id,
            'message' => $text,
            'category' => 0,
            'send_time' => time()
        );

        if ($type == 1) {
            //全体用户系统消息
            $message['type'] = 1;
            M('Message')->add($message);
        } else {
            //个体消息
            $message['type'] = 0;
            if (!empty($users)) {
                $create_message_id = M('Message')->add($message);
                foreach ($users as $key) {
                    M('user_message')->add(array('user_id' => $key, 'message_id' => $create_message_id, 'status' => 0, 'category' => 0));
                }
            }
        }
        echo "<script>parent.{$call_back}(1);</script>";
        exit();
    }

    /**
     *
     * @time 2016/09/03
     * @author dyr
     * 发送邮件
     */
    public function sendMail()
    {
        $user_id_array = I('get.user_id_array');
        $users = array();
        if (!empty($user_id_array)) {
            $user_where = array(
                'user_id' => array('IN', $user_id_array),
                'email' => array('neq', '')
            );
            $users = M('users')->field('user_id,nickname,email')->where($user_where)->select();
        }
        $this->assign('smtp', tpCache('smtp'));
        $this->assign('users', $users);
        return $this->fetch();
    }

    /**
     * 发送邮箱
     * @author dyr
     * @time  2016/09/03
     */
    public function doSendMail()
    {
        $call_back = I('call_back');//回调方法
        $message = I('post.text');//内容
        $title = I('post.title');//标题
        $users = I('post.user/a');
        $email = I('post.email');
        if (!empty($users)) {
            $user_id_array = implode(',', $users);
            $users = M('users')->field('email')->where(array('user_id' => array('IN', $user_id_array)))->select();
            $to = array();
            foreach ($users as $user) {
                if (check_email($user['email'])) {
                    $to[] = $user['email'];
                }
            }
            $res = send_email($to, $title, $message);
            echo "<script>parent.{$call_back}({$res['status']});</script>";
            exit();
        }
        if ($email) {
            $res = send_email($email, $title, $message);
            echo "<script>parent.{$call_back}({$res['status']});</script>";
            exit();
        }
    }

    /**
     * 提现申请记录
     */
    public function withdrawals()
    {
        $this->get_withdrawals_list();
        return $this->fetch();
    }

    public function get_withdrawals_list($status = '')
    {
        $user_id = I('user_id/d');
        $realname = I('realname');
        $bank_card = I('bank_card');
        $create_time = I('create_time');
        $create_time = str_replace("+", " ", $create_time);
        $create_time2 = $create_time ? $create_time : date('Y-m-d', strtotime('-1 year')) . ' - ' . date('Y-m-d', strtotime('+1 day'));
        $create_time3 = explode(' - ', $create_time2);
        $this->assign('start_time', $create_time3[0]);
        $this->assign('end_time', $create_time3[1]);

        $where['w.create_time'] = array(array('gt', strtotime($create_time3[0]), array('lt', strtotime($create_time3[1]))));
        $status = empty($status) ? I('status') : $status;
        if (empty($status) || $status === '0') {
            $where['w.status'] = array('lt', 1);
        }
        if ($status === '0' || $status > 0) {
            $where['w.status'] = $status;
        }
        $user_id && $where['u.user_id'] = $user_id;
        $realname && $where['w.realname'] = array('like', '%' . $realname . '%');
        $bank_card && $where['w.bank_card'] = array('like', '%' . $bank_card . '%');
        $export = I('export');
        if ($export == 1) {
            $strTable = '<table width="500" border="1">';
            $strTable .= '<tr>';
            $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">申请人</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="100">提现金额</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">银行名称</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">银行账号</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">开户人姓名</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">申请时间</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">提现备注</td>';
            $strTable .= '</tr>';
            $remittanceList = Db::name('withdrawals')->alias('w')->field('w.*,u.nickname')->join('__USERS__ u', 'u.user_id = w.user_id', 'INNER')->where($where)->order("w.id desc")->select();
            if (is_array($remittanceList)) {
                foreach ($remittanceList as $k => $val) {
                    $strTable .= '<tr>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">' . $val['nickname'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['money'] . ' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['bank_name'] . '</td>';
                    $strTable .= '<td style="vnd.ms-excel.numberformat:@">' . $val['bank_card'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['realname'] . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . date('Y-m-d H:i:s', $val['create_time']) . '</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">' . $val['remark'] . '</td>';
                    $strTable .= '</tr>';
                }
            }
            $strTable .= '</table>';
            unset($remittanceList);
            downloadExcel($strTable, 'remittance');
            exit();
        }
        $count = Db::name('withdrawals')->alias('w')->join('__USERS__ u', 'u.user_id = w.user_id', 'INNER')->where($where)->count();
        $Page = new Page($count, 20);
        $list = Db::name('withdrawals')->alias('w')->field('w.*,u.nickname')->join('__USERS__ u', 'u.user_id = w.user_id', 'INNER')->where($where)->order("w.id desc")->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $this->assign('create_time', $create_time2);
        $show = $Page->show();
        $this->assign('show', $show);
        $this->assign('list', $list);
        $this->assign('pager', $Page);
        C('TOKEN_ON', false);
    }

    /**
     * 删除申请记录
     */
    public function delWithdrawals()
    {
        $model = M("withdrawals");
        $res = $model->where('id =' . $_GET['id'])->delete();
        if ($res) {
            $return_arr = array('status' => 1, 'msg' => '操作成功', 'data' => '',);
        } else {
            $return_arr = array('status' => -1, 'msg' => '删除失败', 'data' => '',);
        }
        $this->ajaxReturn($return_arr);
    }

    /**
     * 修改编辑 申请提现
     */
    public function editWithdrawals()
    {
        $id = I('id');
        $model = M("withdrawals");
        $withdrawals = $model->find($id);
        $user = M('users')->where("user_id = {$withdrawals[user_id]}")->find();
        if ($user['nickname'])
            $withdrawals['user_name'] = $user['nickname'];
        elseif ($user['email'])
            $withdrawals['user_name'] = $user['email'];
        elseif ($user['mobile'])
            $withdrawals['user_name'] = $user['mobile'];

        $this->assign('user', $user);
        $this->assign('data', $withdrawals);
        return $this->fetch();
    }

    /**
     * /**
     *  处理会员提现申请
     */
    public function withdrawals_update()
    {
        $id = I('id/a');
        $data['status'] = $status = I('status');
        $data['remark'] = I('remark');
        if ($status == 1) $data['check_time'] = time();
        if ($status != 1) $data['refuse_time'] = time();
        $r = M('withdrawals')->where('id in (' . implode(',', $id) . ')')->update($data);
        if ($r) {
            $this->ajaxReturn(array('status' => 1, 'msg' => "操作成功"), 'JSON');
        } else {
            $this->ajaxReturn(array('status' => 0, 'msg' => "操作失败"), 'JSON');
        }
    }

    // 用户申请提现
    public function transfer()
    {
        $id = I('selected/a');
        if (empty($id)) $this->error('请至少选择一条记录');
        $atype = I('atype');
        if (is_array($id)) {
            $withdrawals = M('withdrawals')->where('id in (' . implode(',', $id) . ')')->select();
        } else {
            $withdrawals = M('withdrawals')->where(array('id' => $id))->select();
        }
        $alipay['batch_num'] = 0;
        $alipay['batch_fee'] = 0;
        foreach ($withdrawals as $val) {
            $user = M('users')->where(array('user_id' => $val['user_id']))->find();
            if ($user['user_money'] < $val['money']) {
                $data = array('status' => -2, 'remark' => '账户余额不足');
                M('withdrawals')->where(array('id' => $val['id']))->save($data);
                $this->error('账户余额不足');
            } else {
                $rdata = array('type' => 1, 'money' => $val['money'], 'log_type_id' => $val['id'], 'user_id' => $val['user_id']);
                if ($atype == 'online') {
                    header("Content-type: text/html; charset=utf-8");
                    exit("功能正在开发中。。。");
                } else {
                    accountLog($val['user_id'], ($val['money'] * -1), 0, "管理员处理用户提现申请");//手动转账，默认视为已通过线下转方式处理了该笔提现申请
                    $r = M('withdrawals')->where(array('id' => $val['id']))->save(array('status' => 2, 'pay_time' => time()));
                    expenseLog($rdata);//支出记录日志
                }
            }
        }
        if ($alipay['batch_num'] > 0) {
            //支付宝在线批量付款
            include_once PLUGIN_PATH . "payment/alipay/alipay.class.php";
            $alipay_obj = new \alipay();
            $alipay_obj->transfer($alipay);
        }
        $this->success("操作成功!", U('remittance'), 3);
    }

    /**
     *  转账汇款记录
     */
    public function remittance()
    {
        $status = I('status', 1);
        $this->assign('status', $status);
        $this->get_withdrawals_list($status);
        return $this->fetch();
    }

    /**
     *  赠送名额管理
     */
    public function quota_management()
    {

        return $this->fetch();
    }

    /**
     *  赠送名额信息
     */
    public function ajax_quota_management()
    {
        $nickname = input('nickname');

        if ($nickname) {
            $where['nickname'] = array('like', '%' . $nickname . '%');
        }
        $model = M('user_give_vip');
        $count = $model
            ->alias('a')
            ->join('users u', 'a.user_id=u.user_id', 'left')
            ->where($where)
            ->count();
        $Page = new AjaxPage($count, 10);
        $data_list = $model->alias('a')
            ->field('a.*,u.nickname')
            ->join('users u', 'a.user_id=u.user_id', 'left')
            ->where($where)
            ->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();

        $show = $Page->show();
        $this->assign('data_list', $data_list);
        $this->assign('page', $show);// 赋值分页输出
        $this->assign('pager', $Page);
        return $this->fetch();
    }

    /**
     *  赠送名额信息
     */
    public function card_operation()
    {
        $id = input('id');
        $type = input('type');
        $status = input('status');
        if ($type == 'status') {
            $judge = M('user_give_vip')->where(array('id' => $id))->save(array('status' => $status));
            if (!$judge) {
                $return = ['status' => 0, 'msg' => '操作失败', 'result' => ''];
            } else {
                $return = ['status' => 1, 'msg' => '操作成功！', 'result' => ''];
            }
        }


        $this->ajaxReturn($return);
    }

    /**
     * 海报列表
     */
    public function poster()
    {
        return $this->fetch();
    }

    /**
     * ajax海报列表
     */
    public function ajax_poster()
    {
        $count = M('poster')->count();
        $Page = new AjaxPage($count, 12);
        $posterList = M('poster')->order('type desc,id asc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $show = $Page->show();
        $this->assign('list', $posterList);
        $this->assign('page', $show);
        return $this->fetch();
    }

    /*
     * 添加海报
     * */
    public function addPoster()
    {
        if (IS_POST) {
            $base64_img = trim($_POST['img']);
            $up_dir = './public/poster/';//存放在当前目录的upload文件夹下
            if (!file_exists($up_dir)) {
                mkdir($up_dir, 0777);
            }
            if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64_img, $result)) {
                $type = $result[2];
                if (in_array($type, array('pjpeg', 'jpeg', 'jpg', 'gif', 'bmp', 'png'))) {
                    $new_file = $up_dir . date('YmdHis_') . '.' . $type;
                    if (file_put_contents($new_file, base64_decode(str_replace($result[1], '', $base64_img)))) {
                        $img_path = str_replace('../../..', '', $new_file);

                        //  存入数据库
                        $data = $_POST;
                        foreach ($data as $k => $v) {
                            $data[$k] = str_replace('px', "", $v);
                        }

                        if ($data['nk_color']) {
                            $data['nk_color'] = explode('rgb(', $data['nk_color']);
                            $data['nk_color'] = explode(')', $data['nk_color'][1])[0];
                        }
                        if ($data['nk_font']) {
                            $data['nk_font'] = explode('px', $data['nk_font'])[0];
                        }
                        $data['img'] = str_replace('../../..', '', '/public/poster/' . date('YmdHis_') . '.' . $type);
                        $data['add_time'] = time();
                        $type = M('poster')->where(['type' => 1])->count();
                        if (!$type) {
                            $data['type'] = 1;
                        }

                        M('poster')->insertGetId($data);
                        exit(json_encode(['code' => 1, 'data' => $img_path]));
//                         echo '图片上传成功</br><img src="' .$img_path. '">';
                    } else {
                        exit(json_encode(['code' => -1, 'data' => '', 'msg' => '图片上传失败']));
                        $this->error('图片上传失败');
                    }
                } else {
                    exit(json_encode(['code' => -1, 'data' => '', 'msg' => '图片上传类型错误']));
                }

            } else {
                exit(json_encode(['code' => -1, 'data' => '', 'msg' => '文件错误']));
            }
            exit;
        }
        return $this->fetch();
    }

    public function delPoster()
    {
        $ids = I('post.ids', '');
        empty($ids) && $this->ajaxReturn(['status' => -1, 'msg' => "非法操作！", 'data' => '']);
        $id = rtrim($ids, ",");

        // 删除此商品
        M("poster")->whereIn('id', $id)->delete();  //商品表

        $this->ajaxReturn(['status' => 1, 'msg' => '操作成功', 'url' => url("Admin/User.User/poster")]);
    }

    public function setDefault()
    {
        $id = I('id');
        if ($id) {
            $general = M('poster')->where(['type' => 1])->save(['type' => 0]);
            $default = M('poster')->where(['id' => $id])->save(['type' => 1]);
            if ($default) {
                $this->success('操作成功');
            } else {
                $this->error('操作失败');
            }
        } else {
            $this->error('参数错误');
        }
    }

    /**
     * 删除海报
     */
    public function ajax_poster_delete()
    {
        $id = I('id');
        if ($id) {
            $row = M('poster')->where(array('id' => $id))->delete();
            if ($row !== false) {
                $this->ajaxReturn(array('status' => 1, 'msg' => '删除成功', 'data' => ''));
            } else {
                $this->ajaxReturn(array('status' => 0, 'msg' => '删除失败', 'data' => ''));
            }
        } else {
            $this->ajaxReturn(array('status' => 0, 'msg' => '参数错误', 'data' => ''));
        }
    }


    /**
     * 签到列表
     * @date 2017/09/28
     */
    public function signList()
    {
        header("Content-type: text/html; charset=utf-8");
        exit("功能正在开发中。。。");
    }


    /**
     * 会员签到 ajax
     * @date 2017/09/28
     */
    public function ajaxsignList()
    {
        header("Content-type: text/html; charset=utf-8");
        exit("功能正在开发中。。。");
    }

    /**
     * 签到规则设置
     * @date 2017/09/28
     */
    public function signRule()
    {
        header("Content-type: text/html; charset=utf-8");
        exit("功能正在开发中。。。");
    }
}