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

class Miner extends Base
{

    public function index()
    {
        return $this->fetch();
    }

    /*
     * 矿机列表
     */
    public function mill_list()
    {
        $count = M('world_miner')
            ->alias('m')
            ->count();
        $this->assign('count',$count);
        return $this->fetch();
    }

    /**
     *  ajax矿机列表
     */
    public function ajaxMillList()
    {

        $where['is_del'] = 0; // 搜索条件
        (I('is_on_sale') !== '') && $where = "$where and is_on_sale = " . I('is_on_sale');
        $cat_id = I('cat_id');
        // 关键词搜索
        $key_word = I('key_word') ? trim(I('key_word')) : '';
        if ($key_word) {
            $where['miner_name']=  array('like','%'.$key_word.'%' );
        }


        $count = M('world_miner')
            ->alias('m')
            ->where($where)
            ->count();
        $Page = new AjaxPage($count, 20);
        /**  搜索条件下 分页赋值
         * foreach($condition as $key=>$val) {
         * $Page->parameter[$key]   =   urlencode($val);
         * }
         */
        $show = $Page->show();
        $order_str = "`{$_POST['orderby1']}` {$_POST['orderby2']}";
        $goodsList = M('world_miner')
            ->where($where)
            ->order("sort asc,miner_id desc")
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->select();
        // $catList = D('miner_category')->select();
        // $catList = convert_arr_key($catList, 'cat_id');

        // $this->assign('catList',$catList);
        $this->assign('goodsList', $goodsList);
        $this->assign('page', $show);// 赋值分页输出
        return $this->fetch();
    }


    /*
     * 添加矿机
     */
    public function addEditmill()
    {
        //$GoodsLogic = new GoodsLogic();
        $Goods = new \app\admin\model\WorldMiner();
        $goods_id = I('miner_id');
        $type = $goods_id > 0 ? 2 : 1; // 标识自动验证时的 场景 1 表示插入 2 表示更新

        if ((I('is_ajax') == 1) && IS_POST) {
            $is_distribut = input('is_distribut');

            $return_url = U('admin/World.Miner/mill_list');
            $data = input('post.');
            $validate = \think\Loader::validate('Miner');

            if (!$validate->batch()->check($data)) {
                $error = $validate->getError();
                $error_msg = array_values($error);
                $return_arr = array(
                    'status' => -1,
                    'msg' => $error_msg[0],
                    'data' => $error,
                );
                $this->ajaxReturn($return_arr);
            }

            $data['virtual_indate'] = !empty($virtual_indate) ? strtotime($virtual_indate) : 0;
            $data['exchange_integral'] = ($data['is_virtual'] == 1) ? 0 : $data['exchange_integral'];

            $Goods->data($data, true); // 收集数据
            $Goods->on_time = time();

            $price_ladder = array();

            if ($type == 2) {
                $Goods->miner_id = $goods_id;
                $Goods->isUpdate(true)->save(); // 写入数据到数据库
                // 修改商品后购物车的商品价格也修改一下
                //管理员日志
                adminLog('修改矿机(ID:'.$goods_id.')');

            } else {
                $Goods->save(); // 写入数据到数据库
                $goods_id = $insert_id = $Goods->getLastInsID();
                //管理员日志
                adminLog('添加矿机(ID:'.$goods_id.')');
            }


            $return_arr = array(
                'status' => 1,
                'msg' => '操作成功',
                'data' => array('url' => $return_url),
            );
            $this->ajaxReturn($return_arr);
        }


        $minerInfo = M('WorldMiner')->where('miner_id=' . I('GET.id', 0))->find();
        if ($minerInfo['price_ladder']) {
            $minerInfo['price_ladder'] = unserialize($minerInfo['price_ladder']);
        }
        $minerInfo['specGoodsPrice'] = M('specGoodsPrice')->where(array('item_id' => $minerInfo['item_id']))->find();
        $this->assign('minerInfo', $minerInfo);  // 商品详情
//        $cat_list = M('miner_category')->where("parent_id = 0")->select(); // 已经改成联动菜单
//        $this->assign('cat_list', $cat_list);
        return $this->fetch();
    }

    /*删除矿机*/
    public function delminer()
    {
        $ids = I('post.ids', '');
        empty($ids) && $this->ajaxReturn(['status' => -1, 'msg' => "非法操作！", 'data' => '']);
        $miner_id = rtrim($ids, ",");
        // 判断此商品是否有订单
//        $ordergoods_count = Db::name('MiningOrder')->whereIn('goods_id',$miner_id)->group('goods_id')->getField('goods_id',true);
//        if($ordergoods_count)
//        {
//            $goods_count_ids = implode(',',$ordergoods_count);
//            $this->ajaxReturn(['status' => -1,'msg' =>"ID为【{$goods_count_ids}】的商品有订单,不得删除!",'data'  =>'']);
//        }


        $user_miner_count = Db::name('world_users_miner')->whereIn('miner_id', $miner_id)->where(array('status'=>1))->group('miner_id')->getField('miner_id', true);
        if ($user_miner_count) {
            $miner_count_ids = implode(',', $user_miner_count);
            $this->ajaxReturn(['status' => -1, 'msg' => "ID为【{$miner_count_ids}】的矿机有在运行中,不得删除!", 'data' => '']);
        }

        // 删除此商品

        M("WorldMiner")->whereIn('miner_id', $miner_id)->save(['is_del' => 1]);  //商品表

        adminLog('删除矿机(ID:'.$miner_id.')');
        $this->ajaxReturn(['status' => 1, 'msg' => '操作成功', 'url' => U("Admin/World.Miner/mill_list")]);
    }

    /**
     * 会员列表
     */
    public function member()
    {
        if(IS_POST){
            $model = M('member');
            $map = array();
            $condition = I('condition');
            $search_key = I('search_key');
            $map['isDelete'] = 2;
            $search = input("");

            switch ($condition) {
                case 1: //手机
                    $map['account'] = $search_key;
                    break;
                case 2: // 昵称
                    $map['nickname'] = array('like', "%$search_key%");
                    break;
                case 3: // ID
                    $map['id'] = $search_key;
                    break;
                case 4: // 邀请码
                    $map['invitecode'] = $search_key;
                    break;
                default:
                    break;
            }

            $id = I('id');
            if ($id) {
                $map['id'] = array('like', "%$id%");
            }
            if ($search['start_time']&&$search['end_time']) {
                $map['t.createTime'] = array(array('gt', $search['start_time']), array('lt', $search['end_time']));
            }

            $count = $model
                ->alias("t")
                ->join("world_level b", "t.level=b.level_id", 'LEFT')
                ->where($map)->count();
            $Page = new AjaxPage($count, 20);
            $show = $Page->show();
            $this->assign('pager', $Page);
            $this->assign('page', $show);// 赋值分页输出

            $list = $model
                ->alias("t")
                ->field("t.*,b.*")
                ->join("world_level b", "t.level=b.level_id", 'LEFT')
                ->where($map)
                ->order('t.id DESC')
                ->limit($Page->firstRow . ',' . $Page->listRows)
                ->select();
            foreach ($list as $key => $val) {
                $dh = M("balancelog")->where(array("userId" => $val['id'], 'type' => 8))->sum('num');
                $list[$key]['dh'] = abs($dh);

            }

            $this->assign('list', $list);
            $this->assign('page', $show);// 赋值分页输出
            $this->assign('pager', $Page);
            return $this->fetch('ajax_member');
        }else{
            return $this->fetch();
        }

    }

    /**
     * 添加会员
     */
    public function add_member()
    {
        if (IS_POST) {
            $data = I('post.');
            //设置初始角色
            $data['level'] = 1;

            //昵称
            if (empty($data['nickname'])) {
                $this->error("请填写昵称！");
            } else {
                $nick_occupy = M("member")->where(array("nickname" => $data['nickname']))->find();
                if ($nick_occupy) $this->error("昵称已被使用！");
            }
            if (!empty($data['parentAccount'])) {
                //判断推荐人账户是否存在
                $parent = M("member")
                    ->where(array("account" => $data['parentAccount']))
                    ->find();
                //填写的推荐人不存在
                if (empty($parent)) {
                    $this->error("推荐人不存在！");
                }
            }

            //账户
            if (empty($data['account'])) {
                $this->error("请填写您的手机号！");
            } else {
                //是否存在
                $account_occupy = M("member")->where(array("account" => $data['account']))->find();
                if ($account_occupy) $this->error("手机号已存在！");
            }

            //登录密码
            if (empty($data['password'])) {
                $this->error("请填写登录密码！");
            } else {
                if (strlen($data['password']) < 6) {
                    $this->error("登录密码长度最少为6位！");
                }
            }

            //支付密码
            if (!empty($data['paypassword'])&&(strlen($data['paypassword'])<6||strlen($data['paypassword'])>16)) {
                $this->error("支付密码长度不正确！");
            }else{
                // 支付密码
                $user_data['paypassword'] = md5($data['paypassword']);
            }

            // 昵称
            $user_data['nickname'] = $data['nickname'];
            //  上级ID
            $user_data['parentId'] = $parent['id'];
            // 上级账号
            $user_data['parentAccount'] = $parent['account'];
            //  登录密码
            $user_data['password'] = md5($data['password']);
            // 账号
            $user_data['account'] = $data['account'];

            // 注册时间
            $user_data['reg_time'] = time();
            //创建时间
            $user_data["createTime"] = date("Y-m-d H:i:s");
            //更新时间
            $user_data["updateTime"] = date("Y-m-d H:i:s");
            //禁用
            $user_data["isDisable"] = 2; //正常
            //删除
            $user_data["isDelete"] = 2; //正常
            //钱包地址
            $user_data['wallet'] = wallet_address_generation($data['account']);
            $user_data['other_balance'] = 0;

            $user_data['invitecode'] = randomkeys(6);

            //注册增加注册积分
            $system = tpCache("vpay_spstem");
            $user_data['integral'] = $system['regIntegral'] ? $system['regIntegral'] : 0;
//            dump($user_data);
            //创建用户表member数据
            Db::startTrans();
            $result = M("member")->add($user_data);
            if ($result) {
                //使用id，也就是uid，做邀请码
//                $mem_save=M("member")->where(array("id"=>$result))->save(array("invitecode"=>$result));
//                if(empty($mem_save)){
//                    Db::rollback();
//                    $this->error("注册失败！");
//                }


                $give_dc_reg=worldCa("shell_set.give_dc_reg");
                if ($give_dc_reg>0) {
                    // DC币 - 贝壳汇率
                    $dc_shell_rate = worldCa("basic.dc_shell_rate");
                    $give_shell_data = [
                        'user_id' => $result,
                        'dc_num' => $give_dc_reg,
                        'dc_shell_rate' => $dc_shell_rate,
                        'shell_num' => $give_dc_reg * $dc_shell_rate,
                        'shell_surplus' => $give_dc_reg * $dc_shell_rate,
                        'type' => 1,
                        'create_time' => time()
                    ];
                    $result3 = M("world_giveshell")->add($give_shell_data);
                }


                $data2['account'] = $data['account'];
                $data2['userId'] = $result;
                $data2["createTime"] = date("Y-m-d H:i:s");
                $data2["updateTime"] = date("Y-m-d H:i:s");
                $result2 = M("virtualcurrency")->add($data2);
                if ($result2) {
                    Db::commit();
                    //管理员日志
                    adminLog('添加会员(ID:'.$result.')');
                    $this->success("添加成功！", url('World.Miner/member'));
                    exit;
                } else {
                    Db::rollback();
                    $this->error("注册失败！");
                }

            } else {
                Db::rollback();
                $this->error("注册失败！");
            }

        }
        return $this->fetch();
    }

    /**
     * 会员修改
     */
    public function detail()
    {
        $uid = input('get.id');
        $user_model = Db::name('member');
        $user = $user_model->where(['id' => $uid])->find();

        if (!$user)
            exit($this->error('会员不存在'));
        if ($this->request->method() == 'POST') {
            $data = input('post.');
            //  会员信息编辑
            if ($data['password'] != '' && $data['password'] != $data['password2']) {
                exit($this->error('两次输入密码不同'));
            }
            if ($data['password'] == '' && $data['password2'] == '') {
                unset($data['password']);
            } else {
                $data['password'] = md5($data['password']);
            }

            if ($data['paypassword'] == '' && $data['paypassword2'] == '') {
                unset($data['paypassword']);
            } else {
                $data['paypassword'] = md5($data['paypassword']);
            }
            //更新分销关系
            if ($user['parentId'] != $data['parentId']) {
                $result = $this->change_distribution($uid, $data['parentId']);
                if ($result['status'] == 0) {
                    exit($this->error($result['status']));
                }
            }
            $row = $user_model->where(['id' => $uid])->save($data);

            if ($user['level'] != $data['level']) {
                $logRes = memberLog($uid, $user['level'], $data['level'], $desc = '后台更改等级信息');
            }

            if ($row)
                //管理员日志
                adminLog('修改会员(ID:'.$uid.')');
                exit($this->success('修改成功'));

            if ($result['status'] == 1) {
                //管理员日志
                adminLog('修改会员(ID:'.$uid.')');
                exit($this->success('修改成功'));
            }
            exit($this->error('未作内容修改或修改失败'));
        }


        //下级信息
        $user['first_lower'] = $user_model->where("parentId = {$user['id']}")->count();
        //上级信息
        $first_leader = $user_model->where(['id' => $user['parentId']])->find();

        $levelList = Db::name('world_level')->select();
        $this->assign('level', $levelList);

        $this->assign('user', $user);
        $this->assign('first_leader', $first_leader);
        return $this->fetch();
    }

    // 查找用户信息
    public function search_users()
    {
        $user_id = input('id');
        $tpl = input('tpl', 'search_users');
        $where = array();
        $model = Db::name('member');


        $user = $model->where(array('id' => $user_id))->find();
        $my_distribtion = $model->whereOr(array('parentId' => $user_id))->column('id');
        array_push($my_distribtion, $user['id']);

        $where['id'] = array('not in', $my_distribtion);

        $count = $model->where($where)->count();
        $Page = new Page($count, 5);
        //  搜索条件下 分页赋值
        $userList = $model->where($where)->order('id')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $this->assign('page', $Page);
        $this->assign('goodsList', $userList);
        return $this->fetch($tpl);
    }

    /**
     * 更改会员的上级   Lu
     * @param int $user_id 被改用户
     * @param int $first_leader 上级用户
     * @return array
     */
    public function change_distribution($user_id = 0, $first_leader = 0)
    {

        $model = Db::name('member');
        $user = $model->where(array('id' => $user_id))->find();
        $first_leader_info = $model->where(array('id' => $first_leader))->find();

        if ($user_id == $first_leader) {
            return array('status' => 0, 'msg' => '不能把自己设为上级');
        }

        $my_distribtion = $model->whereOr(array('parentId' => $user_id))->column('id');

        $first_leader_users = $model->where(array('id' => $first_leader))->find();

        if ($my_distribtion) {
            if (in_array($first_leader, $my_distribtion)) {
                return array('status' => 0, 'msg' => '不能把自己的下级设为上级');
            }
        }

        $new_leader['parentId'] = $first_leader_info['id'];
        $new_leader['parentAccount'] = $first_leader_info['account'];

        //我的一级下级
        $my_first_distribution = $model->where(array('parentId' => $user_id))->column('id');

        //更改我的一级下级
        if ($my_first_distribution) {
            $data_first = array(
                $new_leader
            );
            $res_first = $model->where(array('id' => array('in', $my_first_distribution)))->save($data_first);
        }

        $res1 = $model->where(array('id' => $user_id))->update($new_leader);

        //管理员日志
        adminLog('变更会员(ID:'.$user_id.')上级(ID:'.$first_leader.')');

        return array('status' => 1, 'msg' => '修改成功');
    }

    public function updateBalanceIntegral()
    {
        if (IS_POST) {
            $balance = I("balance");
            $uid = (int)I('id');
            $integral = I("integral");
            $dc_coin = I("dc_coin");
            $shell = I("shell");
            $dc_type = I('dc_type');
            $shell_type = I('shell_type');
            if($dc_type == 1)
                $dc_coin = $dc_coin*-1;

            if($shell_type == 1)
                $shell = $shell_type*-1;

            if (empty($uid)) {
                exit(json_encode(['code' => -1, 'msg' => 'id不能为空']));
            }
            $info = M("member")->where(array("id" => $uid))->find();
            if (empty($info)) {
                exit(json_encode(['code' => -1, 'msg' => '用户不存在']));
            }
            if (($info['balance'] + $balance) < 0 || ($info['integral'] + $integral) < 0 || ($info['dc_coin'] + $dc_coin) < 0 || ($info['shell'] + $shell) < 0) {
                exit(json_encode(['code' => -1, 'msg' => 'DC币或贝壳不足']));
            }

            Db::startTrans();
            $res = M("member")
                ->where(array("id" => $info['id']))
                ->save(array(
                    "balance" => ($info['balance'] + $balance),
                    "integral" => ($info['integral'] + $integral),
                ));
            if ($balance == 0) {
                $save_log = 1;//如果只添加积分，余额变更为0则不添加log
            } else {
                $save_log = balancelog($res, $info['id'], $balance, $type = 16, $before = $info['balance'], $after = $info['balance'] + $balance);
            }

            if ($dc_coin == 0) {
                $dc_log = 1;
            } else {
                $dc_log = accountDcLog($uid, $dc_coin, '节点调配', 0, '', 1,1,0);
                //管理员日志
                adminLog('调整会员DC(ID:'.$uid.')，数量：'.$dc_coin);
            }

            if ($shell == 0) {
                $shell_log = 1;
            } else {
                $shell_log = accountShellLog($uid, $shell, '节点调配', 0, '', 1,1,0);
                //管理员日志
                adminLog('调整会员贝壳(ID:'.$uid.')，数量：'.$shell);
            }

            if ($integral == 0) {
                $integrallog = 1;//如果只添加余额，积分变更为0则不添加log
            } else {
                $integrallog = integrallog($res, $info['id'], $integral, 5, $info['integral'], ($info['integral'] + $integral));//转出积分log
            }

            if (empty($save_log) || empty($integrallog) || empty($dc_log) || empty($shell_log)) {
                Db::rollback();
                exit(json_encode(['code' => -1, 'msg' => '更改用户余额积分DC币贝壳错误']));
            }
            Db::commit();
            exit(json_encode(['code' => 1, 'msg' => '成功']));
        } else {
            $uid = (int)I('get.id');
            $info = M("member")->where(array("id" => $uid))->find();
            $this->assign("data", $info);
            return $this->fetch();
        }
    }

    /**
     * 查看明细
     *$id 用户id
     *$type 类型 1：余额明细；2积分明细；3：释放明细
     */
    public function view_details()
    {
        $id = $this->request->param('id');
        $type = $this->request->param('type');
        // 分页输入
        if (empty($pageSize)) {
            $pageSize = 10;
        }
        if ($type == 2) {
            $where['m.id'] = $id;
            // 总条数
            $count = M('integrallog')
                ->alias("t")
                ->join("__MEMBER__ m", " m.id=t.userId", 'LEFT')
                ->field("t.*,m.nickname,m.account")
                ->where($where)
                ->count();
            $page = new Page($count, $pageSize);
            $show = $page->show();

            // 进行分页数据查询
            $list = M('integrallog')
                ->alias("t")
                ->join("__MEMBER__ m", " m.id=t.userId", 'LEFT')
                ->field("t.*,m.nickname,m.account")
                ->where($where)
                ->limit($page->firstRow . ',' . $page->listRows)
                ->order('t.id DESC')
                ->select();
            if (!empty($list)) {
                foreach ($list as $k => $v) {
                    //转入转出人
                    $list[$k]['operator'] = "";
                    if ($v['type'] == 3) {
                        $operator = M('transfer')
                            ->alias("t")
                            ->join("__MEMBER__ m", "m.id=t.userId", "LEFT")
                            ->where("t.id=" . $v['reflectId'])
                            ->field("m.nickname,m.id")->find();
                        if (!empty($operator)) {
                            $list[$k]['operator'] = $operator['nickname'] . '|' . $operator['id'];
                        }
                    }
                    if ($v['type'] == 2) {
                        $operator = M('transfer')
                            ->alias("t")
                            ->join("__MEMBER__ m", " m.id=t.toUserId", "LEFT")
                            ->where("t.id=" . $v['reflectId'])
                            ->field("m.nickname,m.id")->find();
                        if (!empty($operator)) {
                            $list[$k]['operator'] = $operator['nickname'] . '|' . $operator['id'];
                        }
                    }
                    switch ($v['type']) {
                        case 1 :
                            $list[$k]['type_str'] = "金币兑换";
                            break;
                        case 2 :
                            $list[$k]['type_str'] = "转出";
                            //$list[$k]['type_str'] = "转出余额获得";
                            break;
                        case 3 :
                            $list[$k]['type_str'] = "转入";
                            //$list[$k]['type_str'] = "转入余额获得";
                            break;
                        case 4 :
                            $list[$k]['type_str'] = "签到";
                            break;
                        case 5 :
                            $list[$k]['type_str'] = "后台操作";
                            //$list[$k]['type_str'] = "后台手动操作";
                            break;
                        case 6 :
                            $list[$k]['type_str'] = "获得";
                            //$list[$k]['type_str'] = "动态返佣扣除积分";
                            break;
                        case 7 :
                            $list[$k]['type_str'] = "获得";
                            //$list[$k]['type_str'] = "兑换积分返佣扣除积分";
                            break;
                        case 9 :
                            $list[$k]['type_str'] = "团队奖收入";
                            //$list[$k]['type_str'] = "团队奖收入";
                            break;
                        case 10 :
                            $list[$k]['type_str'] = "买币转换积分";
                            //$list[$k]['type_str'] = "买币兑换积分";
                            break;
                        case 11 :
                            $list[$k]['type_str'] = "卖币收入积分";
                            //$list[$k]['type_str'] = "卖币收入积分";
                            break;
                        case 12 :
                            $list[$k]['type_str'] = "买币转换积分";
                            //$list[$k]['type_str'] = "买币兑换积分";
                            break;
                        case 13 :
                            $list[$k]['type_str'] = "卖币收入积分";
                            //$list[$k]['type_str'] = "卖币收入积分";
                            break;
                        default:
                            $list[$k]['type_str'] = "收入";
                            break;
                    }
                }
            }

            // 统计
            $sum = M('integrallog')
                ->alias("t")->join("__MEMBER__ m", " m.id=t.userId", 'LEFT')
                ->field("count(1) as countNum")
                ->where($where)
                ->order('t.id DESC')
                ->find();
        } elseif ($type == 3) {
            $where['m.id'] = $id;
            $model = M('releaselog');
            $count = $model->alias("t")
                ->join("member m", "m.id=t.userId", "LEFT")
                ->field("t.*,m.nickname sname")
                ->where($where)
                ->count();
            $Page = new Page($count, 20);
            $show = $Page->show();
            $list = M('releaselog')
                ->alias("t")
                ->join("member m", "m.id=t.userId", "LEFT")
                ->field("t.*,m.nickname sname,m.account saccount")
                ->where($where)
                ->order('t.id DESC')
                ->limit($Page->firstRow . ',' . $Page->listRows)->select();
            if (!empty($list)) {
                foreach ($list as $k => $v) {
                    switch ($v['type']) {
                        case 1 :
                            $list[$k]['detail'] = Db::name('transfer')
                                ->alias('t')
                                ->field('t.*,b.type as btype')
                                ->join('__BALANCELOG__ b', 't.id = b.reflectId')
                                ->where(['t.id' => $v['reflectId']])
                                ->find();
                            if ($list[$k]['detail']['btype'] == 1) {
                                $list[$k]['type_str'] = "转入";
                            } elseif ($list[$k]['detail']['btype'] == 2) {
                                $list[$k]['type_str'] = "转出";
                            }

                            break;
                        case 2 :
                            $list[$k]['type_str'] = "兑换";
                            $list[$k]['detail'] = Db::name('exchange')
                                ->alias('t')
                                ->field('t.*,m.account as m_account')
                                ->join('__MEMBER__ m', 'm.id = t.user_id', 'LEFT')
                                ->where(['t.id' => $v['reflectId']])
                                ->find();
                            $list[$k]['type_str'] = "转出余额获得";
                            break;
                        case 3 :
                            $list[$k]['type_str'] = "买入";
                            $list[$k]['detail'] = Db::name('transaction')->where(['id' => $v['reflectId']])->find();
                            //$list[$k]['type_str'] = "转入余额获得";
                            break;
                        case 4 :
                            $list[$k]['type_str'] = "卖出";
                            $list[$k]['detail'] = Db::name('transaction')->where(['id' => $v['reflectId']])->find();
                            break;
                        default:
                            $list[$k]['type_str'] = "未知";
                            break;
                    }
                }
            }
        } else {
            $where['m.id'] = $id;
            // 总条数
            $count = Db::name('balancelog')
                ->alias("t")->join("__MEMBER__ m ", " m.id=t.userId", 'LEFT')
                ->where($where)
                ->count();
            $page = new Page($count, $pageSize);
            $show = $page->show();

            // 进行分页数据查询
            $list = M('balancelog')
                ->alias("t")
                ->join("__MEMBER__ m ", "m.id=t.userId", 'LEFT')
                ->field("t.*,m.nickname,m.account")
                ->where($where)
                ->limit($page->firstRow . ',' . $page->listRows)
                ->order('t.id DESC')
                ->select();
            if (!empty($list)) {
                foreach ($list as $k => $v) {
                    //转入转出人
                    $list[$k]['operator'] = "";
                    if ($v['type'] == 1) {
                        $operator = M('transfer')
                            ->alias("t")
                            ->join("__MEMBER__ m ", " m.id=t.userId", 'LEFT')
                            ->where("t.id=" . $v['reflectId'])
                            ->field("m.nickname,m.id")->find();
                        $list[$k]['operator'] = $operator['nickname'] . '|' . $operator['id'];
                    }
                    if ($v['type'] == 2) {
                        $operator = M('transfer')
                            ->alias("t")
                            ->join("__MEMBER__ m", "m.id = t.toUserId", 'LEFT')
                            ->where("t.id=" . $v['reflectId'])
                            ->field("m.nickname,m.id")
                            ->find();
                        $list[$k]['operator'] = $operator['nickname'] . '|' . $operator['id'];
                    }

                    switch ($v['type']) {
                        case 1 :
                            $list[$k]['type_str'] = "转入";
                            break;
                        case 2 :
                            $list[$k]['type_str'] = "转出";
                            break;
                        case 3 :
                            $list[$k]['type_str'] = "买入";
                            break;
                        case 4 :
                            $list[$k]['type_str'] = "卖出";
                            break;
                        case 5 :
                            $list[$k]['type_str'] = "签到";
                            break;
                        case 6 :
                            $list[$k]['type_str'] = "保证金";
                            //$logs[$k]['type_str'] = "扣除保证金（交易买入）";
                            break;
                        case 7 :
                            $list[$k]['type_str'] = "保证金";
                            //$logs[$k]['type_str'] = "回加保证金（交易确认）";
                            break;
                        case 8 :
                            $list[$k]['type_str'] = "兑换积分";
                            break;
                        case 9 :
                            $list[$k]['type_str'] = "保证金";
                            //$logs[$k]['type_str'] = "扣除保证金（数字资产）";
                            break;
                        case 10 :
                            $list[$k]['type_str'] = "保证金";
                            //$logs[$k]['type_str'] = "回加保证金（数字资产）";
                            break;
                        case 11 :
                            $list[$k]['type_str'] = "交易支出";
                            //$logs[$k]['type_str'] = "交易支出（数字资产）";
                            break;
                        case 12 :
                            $list[$k]['type_str'] = "交易收入";
                            //$logs[$k]['type_str'] = "交易收入（数字资产）";
                            break;
                        case 13 :
                            $list[$k]['type_str'] = "取消订单";
                            //$logs[$k]['type_str'] = "取消买入订单（交易买入）";
                            break;
                        case 14:
                            $list[$k]['type_str'] = "获得";
                            //$logs[$k]['type_str'] = "余额变动返佣";
                            break;
                        case 15:
                            $list[$k]['type_str'] = "取消订单";
                            //$logs[$k]['type_str'] = "回加交易金";
                            break;
                        case 16:
                            $list[$k]['type_str'] = "后台操作";
                            //$logs[$k]['type_str'] = "后台手动操作";
                            break;
                        case 17:
                            $list[$k]['type_str'] = "获得";
                            //$logs[$k]['type_str'] = "兑换积分返佣";
                            break;
                        case 18:
                            $list[$k]['type_str'] = "手续费";
                            //$logs[$k]['type_str'] = "兑换积分返佣";
                            break;
                        case 19:
                            $list[$k]['type_str'] = "手续费";
                            //$logs[$k]['type_str'] = "兑换积分返佣";
                            break;
                        default:
                            $list[$k]['type_str'] = "收入";
                            break;
                    }
                }
            }
            // 统计
            $sum = M('balancelog')
                ->alias("t")->join("__MEMBER__ m", " m.id=t.userId", 'LEFT')
                ->field("count(1) as countNum")
                ->where($where)
                ->order('t.id DESC')
                ->find();
        }

        // 输出数据
        $this->assign('list', $list);
        $this->assign('count', $count);
        $this->assign('page', $show);
        $this->assign('id', $id);
        $this->assign('type', $type);
        return $this->fetch();
    }

    //订单列表
    public function miner_order_list()
    {
        return $this->fetch();
    }

    public function ajax_miner_order_list()
    {
        $begin = strtotime(I('add_time_begin'));
        $end = strtotime(I('add_time_end'));
        $pay_status = I('pay_status');
        $keyType = I("keytype");
        $keywords = I('keywords', '', 'trim');

        //搜索功能参数
        $where = array();
        if ($begin && $end) {
            $where['wo.add_time'] = array('between', "$begin,$end");;
        } elseif ($begin && !$end) {
            $where['wo.add_time'] = array('gt', $begin);
        } elseif (!$begin && $end) {
            $where['wo.add_time'] = array('lt', $end);
        }

        if ($pay_status!=null&&$pay_status!="") {
            $where['wo.pay_status'] = array('eq', $pay_status);
        }

        if($keyType=="nickname"){
            $where[$keyType] = array('like','%'.$keywords.'%');
        }elseif($keyType=="order_sn"){
            $where[$keyType] = array('like','%'.$keywords.'%');
        }
        elseif($keyType=="account"){
            $where[$keyType] = array('like','%'.$keywords.'%');
        }

        //订单数据
        $count = M('world_order')
            ->alias('wo')
            ->field('wo.*,m.nickname')
            ->join('member m', 'wo.user_id = m.id', 'LEFT')
            ->where($where)
            ->order('add_time desc')
            ->count();
        $Page = new AjaxPage($count, 12);
        $show = $Page->show();
        $orderList = M('world_order')
            ->alias('wo')
            ->field('wo.*,m.nickname,m.account')
            ->join('member m', 'wo.user_id = m.id', 'LEFT')
            ->where($where)
            ->order('add_time desc')
            ->limit($Page->firstRow, $Page->listRows)
            ->select();
        $order_status = array('待支付', '已完成', '已取消');

        $this->assign('pager',$Page);
        $this->assign('orderList', $orderList);
        $this->assign('order_status', $order_status);
        $this->assign('page', $show);// 赋值分页输出
        return $this->fetch();
    }

    /**
     * 订单详情
     * @param int $order_id 订单id
     * @return mixed
     */
    public function order_detail()
    {
        $order_id = I('order_id');
        $order_status = array('待支付', '已完成', '已取消');
        $pay_status = array('待支付', '已支付');

        $order = M('world_order')
            ->alias('wo')
            ->field('wo.*,m.nickname,og.miner_id')
            ->join('member m', 'wo.user_id = m.id', 'LEFT')
            ->join('world_order_goods og', 'wo.order_id = og.order_id', 'LEFT')
            ->where(array('wo.order_id' => $order_id))
            ->find();

        $order_goods = M('world_order_goods')->where(array('order_id' => $order['order_id']))->find();

        //获赠人信息
        $to_user = M('member')->where(array('id' => $order['to_user_id']))->find();

        $this->assign('order_goods', $order_goods);
        $this->assign('pay_status', $pay_status);
        $this->assign('order_status', $order_status);
        $this->assign('order', $order);
        $this->assign('to_user', $to_user);
        return $this->fetch();
    }

    /**
     * 用户矿机
     */
    public function user_miner()
    {

        return $this->fetch();
    }

    public function ajax_user_miner()
    {
        $begin = strtotime(I('add_time_begin'));
        $end = strtotime(I('add_time_end'));
        $pay_status = I('pay_status');
        $keyType = I("keytype");
        $keywords = I('keywords', '', 'trim');

        //搜索功能参数
        $where = array();
        if ($begin && $end) {
            $where['wu.add_time'] = array('between', "$begin,$end");;
        } elseif ($begin && !$end) {
            $where['wu.add_time'] = array('gt', $begin);
        } elseif (!$begin && $end) {
            $where['wu.add_time'] = array('lt', $end);
        }

        if ($pay_status) {
            $where['wu.pay_status'] = array('eq', $pay_status);
        }

        if ($keyType == 'nickname' && $keywords) {
            $where[$keyType] = array('like', '%' . $keywords . '%');
        }elseif($keyType == 'order_sn'){
            $where['order_sn'] = array('like', '%' . $keywords . '%');
        }
        elseif($keyType == 'account'){
            $where['m.account'] = array('like', '%' . $keywords . '%');
        }

        $count = M('world_users_miner')
            ->alias('wu')
            ->join('member m', 'wu.user_id = m.id', 'LEFT')
            ->join('world_order wo', 'wu.order_id = wo.order_id', 'LEFT')
            ->join('world_miner wm', 'wu.miner_id = wm.miner_id', 'LEFT')
            ->where($where)
            ->count();

        $Page = new AjaxPage($count, 12);
        $show = $Page->show();

        $user_miner_info = M('world_users_miner')
            ->alias('wu')
            ->field('wu.*,m.nickname,m.account,wo.order_sn,wm.miner_name')
            ->join('member m', 'wu.user_id = m.id', 'LEFT')
            ->join('world_order wo', 'wu.order_id = wo.order_id', 'LEFT')
            ->join('world_miner wm', 'wu.miner_id = wm.miner_id', 'LEFT')
            ->where($where)
            ->limit($Page->firstRow, $Page->listRows)
            ->order('order_id desc,id asc')
            ->select();

        $source_type = array(
            '1'=>"自己购买",
            '2'=>"转赠获赠",
            '3'=>"他人转赠",
            '4'=>"激活获赠",
        );
        $this->assign('source_type', $source_type);
        $this->assign('user_miner_info', $user_miner_info);
        $this->assign('page', $show);
        $this->assign('pager',$Page);
        return $this->fetch();
    }


}