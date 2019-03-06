<?php
namespace app\admin\controller\Supplier;

use app\admin\controller\Base;
use app\common\model\Engineer;
use think\Db;
use think\AjaxPage;
use app\admin\logic\UsersLogic;
use think\Page;

class Supplier extends Base
{

    /**
     * 门店列表
     */
    public function index()
    {
        $supplier_count = DB::name('suppliers')->count();
        $page = new Page($supplier_count, 10);
        $show = $page->show();
        $supplier_list = DB::name('suppliers')
            ->alias('s')
            ->field('s.*,a.role_id,a.role_name')
            ->join('admin_role a', 'a.role_id = s.role_id', 'LEFT')
            ->limit($page->firstRow, $page->listRows)
            ->select();
        $this->assign('list', $supplier_list);
        $this->assign('page', $show);
        // dump($show);exit;
        $this->assign('pager', $page);
        return $this->fetch();
    }

    /*
     * 门店配置
     * */
    public function supplier_config(){

        return $this->fetch();
    }
    /**
     * 门店资料
     */
    public function supplier_info()
    {
        $suppliers_id = I('get.suppliers_id/d', 0);
        if ($suppliers_id) {
            $info = DB::name('suppliers')
                ->alias('s')
                ->field('s.*,a.admin_id,a.user_name')
                ->join('__ADMIN__ a', 'a.suppliers_id = s.suppliers_id', 'LEFT')
                ->where(array('s.suppliers_id' => $suppliers_id))
                ->find();

            if($info['business_time']){
                $info['business_time'] = unserialize($info['business_time']);
                foreach($info['business_time'] as $k=>$v){
                    $info[$k] = $v;
                }
            }

            $this->assign('info', $info);

            //获取省份
            $c = M('region')->where(array('parent_id' => $info['province_id'], 'level' => 2))->select();
            $d = M('region')->where(array('parent_id' => $info['city_id'], 'level' => 3))->select();
            $this->assign('city', $c);
            $this->assign('district', $d);
        }
        $type_info = Db::name('suppliers_type')->where(['is_show' => 1])->select();
        $this->assign('type_info', $type_info);
        $act = empty($suppliers_id) ? 'add' : 'edit';
        $this->assign('act', $act);
        $role = D('admin_role')->select();
        $this->assign('role', $role);
        $p = M('region')->where(array('parent_id' => 0, 'level' => 1))->select();
        $this->assign('province', $p);
        return $this->fetch();
    }

    /**
     * 供应商增删改
     */
    public function supplierHandle()
    {
        $data = I('post.');
        $suppliers_model = M('suppliers');
        //增
        if ($data['act'] == 'add') {
            unset($data['suppliers_id']);
            $count = M('suppliers')
                ->whereor("suppliers_name", $data['suppliers_name'])
                ->whereor("account", $data['account'])
                ->count();
            if ($count) {
                $this->error("此门店名称或账号已被注册，请更换", U('Admin/Supplier.Supplier/supplier_info'));
            } else {
                $data['password'] = encrypt($data['password']);
                $p = M('region')->where(array('id' => $data['district_id'], 'level' => 3))->find();
                $tolatlag = $this->bmap($p['name'] . $data['address']);
                $data['lat'] = $tolatlag[1];
                $data['lon'] = $tolatlag[0];
                $data['add_time'] = time();

                $data['business_time'] = serialize($data['business_time']);
                $data['role_id'] = 9;
                $r = M('suppliers')->insertGetId($data);
            }
        }
        //改
        if ($data['act'] == 'edit' && $data['suppliers_id'] > 0) {
            if ($data['newpwd'] != $data['password']) {
                $data['password'] = encrypt($data['password']);
                unset($data['newpwd']);
            }
            $p = M('region')->where(array('id' => $data['district_id'], 'level' => 3))->find();
            $tolatlag = $this->bmap($p['name'] . $data['address']);
            $data['lat'] = $tolatlag[1];
            $data['lon'] = $tolatlag[0];
            if (!$data['suppliers_img']) $data['suppliers_img'] = '/template/mobile/new2/static/assets/images/dimg.jpg';
            $data['business_time'] = serialize($data['business_time']);
            $r = Db::name('suppliers')->where('suppliers_id', $data['suppliers_id'])->save($data);
        }
        //删
        if ($data['act'] == 'del' && $data['suppliers_id'] > 0) {
            $r = $suppliers_model->where('suppliers_id', $data['suppliers_id'])->delete();
        }

        if ($r !== false) {
            $this->success("操作成功", U('Admin/Supplier.Supplier/index'));
        } else {
            $this->error("操作失败", U('Admin/Supplier.Supplier/index'));
        }
    }


    public function bmap($address)
    {
        $url = 'http://api.map.baidu.com/geocoder/v2/?address=' . $address . '&output=json&ak=HyurfGTQ5p5WhtEsegrxisqer47BubTW';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        $output = curl_exec($ch);
        curl_close($ch);
        if ($output) {

            $res = explode(',"lat":', substr($output, 40, 36));

            return $res;

        }

    }

    /**
     * 工程师申请列表 Lu  2018-06
     */

    public function engineer_list()
    {
        $begin = date('Y-m-d', strtotime("-1 year"));//30天前
        $end = date('Y/m/d', strtotime('+1 days'));
        $this->assign('timegap', $begin . '-' . $end);
        return $this->fetch();
    }

    public function ajaxengineer_list()
    {
        $model = M('users');
        $username = I('username', '', 'trim');
        $keyword = I('keyword', '', 'trim');
        $status = I('status', '', 'trim');
        $where['is_engineer'] = 1;
        //是否门店登陆
        $suppliers_id = session('suppliers_id');
        if (isset($suppliers_id)) {
            $where['a.suppliers_id'] = session('suppliers_id');
        }

        if ($status != '' && $status != null) {
            $where['a.engineer_status'] = $status;
        }

        if ($username) {
            $where['a.nickname'] = ['like', '%' . $username . '%'];
        }
        if ($keyword) {
            $where['a.mobile|r.name'] = ['like', '%' . $keyword . '%'];
        }
        $count = $model
            ->alias('a')
            ->join('suppliers s', 'a.suppliers_id = s.suppliers_id', 'LEFT')
            ->join('repair_join r', 'a.user_id = r.user_id', 'LEFT')
            ->where($where)
            ->count();
        $Page = $pager = new AjaxPage($count, 10);
        $show = $Page->show();

        $supplier_list = $model
            ->alias('a')
            ->field('a.*,s.suppliers_name,r.name')
            ->join('suppliers s', 'a.suppliers_id = s.suppliers_id', 'LEFT')
            ->join('repair_join r', 'a.user_id = r.user_id', 'LEFT')
            ->where($where)
            ->order('a.engineer_status desc,r.add_time DESC')
            ->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $this->assign('datalist', $supplier_list);
        $this->assign('page', $show);// 赋值分页输出
        $this->assign('pager', $pager);// 赋值分页输出
        return $this->fetch();
    }

    /**
     * 工程师信息
     * @return mixed
     */
    public function engineer_info()
    {
        $id = I('get.id/d');
        $user = M('users')->where(['user_id' => $id])->find();
        if (!$user) {
            exit($this->error('不存在该工程师'));
        }
        if (IS_POST) {
            $add['parent_id'] = $id;
            $add['content'] = trim(I('post.content'));
            $add['goods_id'] = $user['goods_id'];
            $add['add_time'] = time();
            $add['username'] = 'admin';
            $add['is_show'] = 1;
            empty($add['content']) && $this->error('请填写回复内容');
            $row = M('comment')->add($add);
            if ($row) {
                $this->success('添加成功');
            } else {
                $this->error('添加失败');
            }
            exit;
        }
        $engineer = new Engineer();
        $base_info = $engineer->getEngineerBaseInfo($user['user_id']);//基本信息
        $extend_info = $engineer->getEngineerExtendInfo($user['user_id']);//扩展信息
//        dump($extend_info);

        $where_know = C('WHERE_KNOW');

        $this->assign('where_know', $where_know);
        $this->assign('user', $user);
        $this->assign('base_info', $base_info);
        $this->assign('extend_info', $extend_info);
        return $this->fetch();
    }

    /**
     * 添加工程师
     */
    public function engineer_add()
    {
        if (IS_POST) {
            $user_obj = new UsersLogic();
            $data = I('post.');
            $data['is_engineer'] = 1;
            if ($data['step'] == 0) {
                $res = $user_obj->addUser($data);
                if ($res['status'] == 1) {
                    $this->success('添加成功', U('User/index'));
                    exit;
                } else {
                    $this->error('添加失败,' . $res['msg'], U('User/index'));
                }
            } else {
                if ($data['password'] != '') {
                    $data['password'] = encrypt($data['password']);
                }
                $res = M('users')->where(array('mobile' => $data['mobile']))->save($data);
                if ($res) {
                    $this->success('添加成功', U('User/index'));
                    exit;
                } else {
                    $this->error('添加失败,' . $res['msg'], U('User/index'));
                }
            }

        }

        //获取门店
        $suppliers = M('suppliers')->field('suppliers_id,suppliers_name')->order('suppliers_id')->select();
        //是否门店登陆
        $suppliers_id = session('suppliers_id');
        $suppliers_id = $suppliers_id ? $suppliers_id : 0;

        $mysuppliers = M('suppliers')->where(['suppliers_id' => $suppliers_id])->find();

        $this->assign('suppliers', $suppliers);
        $this->assign('mysuppliers', $mysuppliers);
        $this->assign('suppliers_id', $suppliers_id);
        return $this->fetch();
    }

    public function check_engineer_add()
    {
        $mobile = I('post.mobile');
        $row = M('users')->where(array('mobile' => $mobile))->find();
        if ($row) {
            if ($row['is_engineer'] == 1) {
                $this->ajaxReturn(['status' => 1, 'msg' => '已存在此账号，并且已经是工程师', 'data' => $row, 'url' => U('Admin/Supplier.Supplier/engineer_list')]);
            } else {
                $this->ajaxReturn(['status' => 2, 'msg' => '已存在此账号，是否继续设置为工程师', 'data' => $row, 'url' => U('Admin/Supplier.Supplier/engineer_list')]);
            }
        } else {
            $this->ajaxReturn(['status' => 0, 'msg' => '操作失败', 'url' => U('Admin/Supplier.Supplier/engineer_list')]);
        }
    }

    /**
     * 工程师增删改
     */
    public function engineerHandle()
    {
        $type = I('post.type');
        $ids = I('post.ids', '');
        if (!in_array($type, array('del', 'show', 'hide')) || empty($ids)) {
            $this->ajaxReturn(['status' => -1, 'msg' => '非法操作！']);
        }
        $user_ids = rtrim($ids, ",");
        $row = false;
        if ($type == 'del') {
            //删除咨询
            $row = $row = M('users')->where('user_id', 'IN', $user_ids)->whereOr('user_id', 'IN', $user_ids)->save(array('engineer_status' => 0, 'is_engineer' => 0, 'suppliers_id' => 0));
        }
        if ($type == 'status') {
            $row = M('repair_join')->where('user_id', 'IN', $user_ids)->save(['is_show' => 1]);
        }
        if ($type == 'hide') {
            $row = M('repair_join')->where('user_id', 'IN', $user_ids)->save(['is_show' => 0]);
        }
        if ($row !== false) {
            $this->ajaxReturn(['status' => 1, 'msg' => '操作完成', 'url' => U('Admin/Supplier.Supplier/engineer_list')]);
        } else {
            $this->ajaxReturn(['status' => -1, 'msg' => '操作失败', 'url' => U('Admin/Supplier.Supplier/engineer_list')]);
        }
    }


    /**
     * 门店申请列表 Lu  2018-06
     */

    public function supplier_join_list()
    {
        $begin = date('Y-m-d', strtotime("-1 year"));//30天前
        $end = date('Y/m/d', strtotime('+1 days'));
        $this->assign('timegap', $begin . '-' . $end);
        return $this->fetch();
    }

    public function ajaxsupplier_join_list()
    {
        $model = M('repair_join');
        $username = I('username', '', 'trim');
        $keyword = I('keyword', '', 'trim');
        $status = I('status', '', 'trim');

        $where['is_show'] = 1;
        $where['type'] = 1;


        if ($status != '' && $status != null) {
            $where['a.status'] = $status;
        }

        if ($username) {
            $where['u.nickname'] = ['like', '%' . $username . '%'];
        }
        if ($keyword) {
            $where['a.name|a.mobile'] = ['like', '%' . $keyword . '%'];
        }
        $count = $model
            ->alias('a')
            ->join('users u', 'a.user_id = u.user_id', 'LEFT')
            ->where($where)
            ->count();
        $Page = $pager = new AjaxPage($count, 10);
        $show = $Page->show();
        $where['status'] = array('neq',2);
        $supplier_list = $model
            ->alias('a')
            ->field('a.*,u.nickname')
            ->join('users u', 'a.user_id = u.user_id', 'LEFT')
            ->where($where)
            ->order('add_time DESC')
            ->limit($Page->firstRow . ',' . $Page->listRows)->select();
//        dump($where);
//        exit;
        $this->assign('datalist', $supplier_list);
        $this->assign('page', $show);// 赋值分页输出
        $this->assign('pager', $pager);// 赋值分页输出
        return $this->fetch();
    }

    /***
     *删除门店申请 Lu
     */
    public function supplier_join_del()
    {
        $type = I('post.type');
        $ids = I('post.ids', '');
        if (!in_array($type, array('del', 'show', 'hide')) || empty($ids)) {
            $this->ajaxReturn(['status' => -1, 'msg' => '非法操作！']);
        }
        $join_ids = rtrim($ids, ",");
        $row = false;
        if ($type == 'del') {
            //删除咨询
            $row = $row = M('repair_join')->where('join_id', 'IN', $join_ids)->whereOr('join_id', 'IN', $join_ids)->save(array('is_show' => 0));
        }
        if ($type == 'show') {
            $row = M('repair_join')->where('join_id', 'IN', $join_ids)->save(['is_show' => 1]);
        }
        if ($type == 'hide') {
            $row = M('repair_join')->where('join_id', 'IN', $join_ids)->save(['is_show' => 0]);
        }
        if ($row !== false) {
            $this->ajaxReturn(['status' => 1, 'msg' => '操作完成', 'url' => U('Admin/Supplier.Supplier/supplier_join_list')]);
        } else {
            $this->ajaxReturn(['status' => -1, 'msg' => '操作失败', 'url' => U('Admin/Supplier.Supplier/supplier_join_list')]);
        }
    }

    public function supplier_join_info()
    {
        $id = I('get.id/d');
        $join = M('repair_join')->where(['join_id' => $id])->find();

        if (!$join) {
            exit($this->error('不存在该申请记录'));
        }
        if (IS_POST) {
            $id = trim(I('post.id'));
            $repairjoin = M('repair_join')->where(array('join_id' => $id))->find();
            $data['status'] = trim(I('post.status'));
            $row = M('repair_join')->where(array('join_id' => $id))->save($data);
            if ($row) {
                if ($data['status'] == 1 && $repairjoin['status'] == 0) {
                    $user_update['is_engineer'] = 1;
                    $user_update['engineer_status'] = 1;
                    $user_update['suppliers_id'] = $repairjoin['suppliers_id'];
                    $res1 = M('users')->where(array('user_id' => $repairjoin['user_id']))->save($user_update);
                }
                $repair_join_info = M('repair_joininfo')->where(array('join_id' => $id))->find();
                //  生成门店表数据
                $suppliers_data['role_id'] = 9;
                $suppliers_data['account'] = $repairjoin['mobile']; //  账户
                $suppliers_data['password'] = encrypt($repairjoin['mobile']); //密码
                $suppliers_data['suppliers_name'] = $repair_join_info['store_name']; //  门店名称
                $suppliers_data['suppliers_desc'] = $repair_join_info['store_desc']; // 门店描述
                $suppliers_data['is_directly'] = 0; // 非直营门店
                $suppliers_data['is_check'] = 1; //  门店状态
                $suppliers_data['suppliers_contacts'] = $repairjoin['name']; // 门店联系人
                $suppliers_data['suppliers_img'] = '/template/mobile/new2/static/assets/images/dimg.jpg';  //  门店照片
                $suppliers_data['suppliers_phone'] = $repairjoin['mobile']; // 门店联系方式
                $suppliers_data['province_id'] = $repair_join_info['province']; // 省
                $suppliers_data['city_id'] = $repair_join_info['city']; //市
                $suppliers_data['district_id'] = $repair_join_info['district']; //区
                // 查询门店地址经纬度
                $address = $this->bmap($repair_join_info['store_address']);
                $suppliers_data['lon'] = $address[0]; // 经度
                $suppliers_data['lat'] = $address[1]; // 纬度
                $suppliers_data['address'] = $repair_join_info['store_address'];// 门店地址
                $suppliers_data['add_time'] = time(); // 添加时间
                $res = Db::name('suppliers')->insertGetId($suppliers_data);
                if ($res) exit(json_encode(array('status' => 1, 'msg' => '操作成功', 'data' => array('url' => U('admin/Supplier.Supplier/supplier_join_list')))));
                exit(json_encode(array('status' => 0, 'msg' => '操作失败')));
            } else {
                exit(json_encode(array('status' => 0, 'msg' => '操作失败')));
            }
            exit;
        }

        $engineer = new Engineer();
        $base_info = $engineer->getEngineerBaseInfo($join['user_id']);//基本信息
        $extend_info = $engineer->getEngineerExtendInfo($join['user_id']);//扩展信息
        $where_know = C('WHERE_KNOW');

        $user = M('users')->where(['user_id' => $join['user_id']])->find();
        $user['address'] = getTotalAddress($user['province'], $user['city'], $user['district'],0);
        $this->assign('where_know', $where_know);
        $this->assign('user', $user);
        $this->assign('base_info', $base_info);
        $this->assign('extend_info', $extend_info);
        return $this->fetch();
    }


    /**
     * 工程师申请列表 Lu  2018-06
     */

    public function engineer_join_list()
    {
        $begin = date('Y-m-d', strtotime("-1 year"));//30天前
        $end = date('Y/m/d', strtotime('+1 days'));
        $this->assign('timegap', $begin . '-' . $end);
        return $this->fetch();
    }

    public function ajaxengineer_join_list()
    {
        $model = M('repair_join');
        $username = I('username', '', 'trim');
        $keyword = I('keyword', '', 'trim');
        $status = I('status', '', 'trim');
        $where['is_show'] = 1;
        $where['type'] = 0;

        //是否门店登陆
        $suppliers_id = session('suppliers_id');
        if (isset($suppliers_id)) {
            $where['a.suppliers_id'] = session('suppliers_id');
        }

        if ($status != '' && $status != null) {
            $where['a.status'] = $status;
        }

        if ($username) {
            $where['u.nickname'] = ['like', '%' . $username . '%'];
        }
        if ($keyword) {
            $where['a.name|a.mobile'] = ['like', '%' . $keyword . '%'];
        }
        $where['status'] = array('neq',2);
        $count = $model
            ->alias('a')
            ->join('users u', 'a.user_id = u.user_id', 'LEFT')
            ->join('suppliers s', 'a.suppliers_id = s.suppliers_id', 'LEFT')
            ->where($where)
            ->count();
        $Page = $pager = new AjaxPage($count, 10);
        $show = $Page->show();

        $supplier_list = $model
            ->alias('a')
            ->field('a.*,u.nickname,s.suppliers_name')
            ->join('users u', 'a.user_id = u.user_id', 'LEFT')
            ->join('suppliers s', 'a.suppliers_id = s.suppliers_id', 'LEFT')
            ->where($where)
            ->order('add_time DESC')
            ->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $this->assign('datalist', $supplier_list);
        $this->assign('page', $show);// 赋值分页输出
        $this->assign('pager', $pager);// 赋值分页输出
        return $this->fetch();
    }

    /**
     * @return mixed工程师申请详情
     */
    public function engineer_join_info()
    {
        $id = I('get.id/d');
        $join = M('repair_join')->where(['join_id' => $id])->find();
        if (!$join) {
            exit($this->error('不存在该申请记录'));
        }
        if (IS_POST) {
            $id = trim(I('post.id'));
            $repairjoin = M('repair_join')->where(array('join_id' => $id))->find();
            $data['status'] = trim(I('post.status'));
            $row = M('repair_join')->where(array('join_id' => $id))->save($data);
            if ($row) {
                if ($data['status'] == 1 && $repairjoin['status'] == 0) {
                    $user_update['is_engineer'] = 1;
                    $user_update['engineer_status'] = 1;
                    $user_update['suppliers_id'] = $repairjoin['suppliers_id'];
                    $res1 = M('users')->where(array('user_id' => $repairjoin['user_id']))->save($user_update);
                }

                exit(json_encode(array('status' => 1, 'msg' => '操作成功', 'data' => array('url' => U('Admin/Supplier.Supplier/engineer_join_list')))));
            } else {
                exit(json_encode(array('status' => 0, 'msg' => '操作失败')));
            }
            exit;
        }

        $engineer = new Engineer();
        $base_info = $engineer->getEngineerBaseInfo($join['user_id'],0);//基本信息
        $extend_info = $engineer->getEngineerExtendInfo($join['user_id'],0);//扩展信息
        $where_know = C('WHERE_KNOW');
        $user = M('users')->where(['user_id' => $join['user_id']])->find();


        $this->assign('where_know', $where_know);
        $this->assign('user', $user);
        $this->assign('base_info', $base_info);
        $this->assign('extend_info', $extend_info);
        return $this->fetch();
    }

    /***
     *删除工程师申请 Lu
     */
    public function engineer_join_del()
    {
        $type = I('post.type');
        $ids = I('post.ids', '');
        if (!in_array($type, array('del', 'show', 'hide')) || empty($ids)) {
            $this->ajaxReturn(['status' => -1, 'msg' => '非法操作！']);
        }
        $join_ids = rtrim($ids, ",");
        $row = false;
        if ($type == 'del') {
            //删除咨询
            $row = $row = M('repair_join')->where('join_id', 'IN', $join_ids)->whereOr('join_id', 'IN', $join_ids)->save(array('is_show' => 0));
        }
        if ($type == 'show') {
            $row = M('repair_join')->where('join_id', 'IN', $join_ids)->save(['is_show' => 1]);
        }
        if ($type == 'hide') {
            $row = M('repair_join')->where('join_id', 'IN', $join_ids)->save(['is_show' => 0]);
        }
        if ($row !== false) {
            $this->ajaxReturn(['status' => 1, 'msg' => '操作完成', 'url' => U('Admin/Supplier.Supplier/engineer_join_list')]);
        } else {
            $this->ajaxReturn(['status' => -1, 'msg' => '操作失败', 'url' => U('Admin/Supplier.Supplier/engineer_join_list')]);
        }
    }
    /**
     * 工程师提现列表 Lu
     */
    public function withdraws_list(){
        $begin = date('Y-m-d', strtotime("-1 year"));//30天前
        $end = date('Y-m-d', strtotime('+1 days'));
        $this->assign('start_time',$begin);
        $this->assign('end_time',$end);
        return $this->fetch();
    }
    public function ajaxwithdraws_list()
    {
        $model = M('withdrawals');
        $user_id = I('user_id/d');
        $realname = I('realname');
        $bank_card = I('bank_card');
        $create_time = I('create_time');
        $create_time = str_replace("+"," ",$create_time);
        $create_time2 = $create_time  ? $create_time  : date('Y-m-d',strtotime('-1 year')).' - '.date('Y-m-d',strtotime('+1 day'));
        $create_time3 = explode(' - ',$create_time2);
        $where['w.create_time'] = array('between',array(strtotime($create_time3[0]),strtotime($create_time3[1])));

        $status = empty($status) ? I('status') : $status;
        if($status === '0' || $status > 0) {
            $where['w.status'] = $status;
        }
        $user_id && $where['u.user_id'] = $user_id;
        $realname && $where['w.realname'] = array('like','%'.$realname.'%');
        $bank_card && $where['w.bank_card'] = array('like','%'.$bank_card.'%');

        $where['is_engineer']=1;

        //是否门店登陆
        $suppliers_id = session('suppliers_id');
        if (isset($suppliers_id)) {
            $where['u.suppliers_id'] = $suppliers_id;
        }
        $count = $model->alias('w')
            ->field('w.*,u.nickname,bc.bank_name,bc.bank_card,bc.realname')
            ->join('users u', 'u.user_id = w.user_id', 'INNER')
            ->join('bank_card bc', 'w.bank_id = bc.id', 'LEFT')
            ->where($where)
            ->count();

        $Page = $pager = new AjaxPage($count, 10);
        $show = $Page->show();

        $remittanceList =$model->alias('w')
            ->field('w.*,u.nickname,bc.bank_name,bc.bank_card,bc.realname')
            ->join('users u', 'u.user_id = w.user_id', 'INNER')
            ->join('bank_card bc', 'w.bank_id = bc.id', 'LEFT')
            ->where($where)->order("w.id desc")
            ->limit($Page->firstRow . ',' . $Page->listRows)->select();

//        echo $model->getLastsql();
//        dump($remittanceList);

        $this->assign('datalist', $remittanceList);
        $this->assign('page', $show);// 赋值分页输出
        $this->assign('pager', $pager);// 赋值分页输出
        return $this->fetch();
    }

    /**
     * 删除申请记录
     */
    public function delWithdrawals()
    {
        $model = M("withdrawals");
        $model->where('id ='.$_GET['id'])->delete();
        $return_arr = array('status' => 1,'msg' => '操作成功','data'  =>'',);   //$return_arr = array('status' => -1,'msg' => '删除失败','data'  =>'',);
        $this->ajaxReturn($return_arr);
    }

    /**
     * 修改编辑 申请提现
     */
    public  function editWithdrawals(){
        $id = I('id');
        $model = M("withdrawals");
        $withdrawals = $model->field('w.*,bc.bank_name,bc.bank_card as account_bank,bc.realname as account_name')->alias('w')->join('bank_card bc','w.bank_id=bc.id','LEFT')->where(array('w.id'=>$id))->find();

        $user = M('users')->where("user_id = {$withdrawals[user_id]}")->find();
        if($user['nickname'])
            $withdrawals['user_name'] = $user['nickname'];
        elseif($user['email'])
            $withdrawals['user_name'] = $user['email'];
        elseif($user['mobile'])
            $withdrawals['user_name'] = $user['mobile'];

        $this->assign('user',$user);
        $this->assign('data',$withdrawals);
        return $this->fetch();
    }

    /**
     *  处理会员提现申请
     */
    public function withdrawals_update(){
        $id = I('id/a');
        $data['status']=$status = I('status');
        $data['remark'] = I('remark');
        if($status == 1) $data['check_time'] = time();
        if($status != 1) $data['refuse_time'] = time();
        $r = M('withdrawals')->where('id in ('.implode(',', $id).')')->update($data);
        if($r){
            $this->ajaxReturn(array('status'=>1,'msg'=>"操作成功"),'JSON');
        }else{
            $this->ajaxReturn(array('status'=>0,'msg'=>"操作失败"),'JSON');
        }
    }
    // 用户申请提现
    public function transfer(){
        $id = I('selected/a');
        if(empty($id))$this->error('请至少选择一条记录');
        $atype = I('atype');
        if(is_array($id)){
            $withdrawals = M('withdrawals')->where('id in ('.implode(',', $id).')')->select();
        }else{
            $withdrawals = M('withdrawals')->where(array('id'=>$id))->select();
        }
        $alipay['batch_num'] = 0;
        $alipay['batch_fee'] = 0;
        foreach($withdrawals as $val){
            $user = M('users')->where(array('user_id'=>$val['user_id']))->find();
            if($user['user_money'] < $val['money'])
            {
                $data = array('status'=>-2,'remark'=>'账户余额不足');
                M('withdrawals')->where(array('id'=>$val['id']))->save($data);
                $this->error('账户余额不足');
            }else{
                $rdata = array('type'=>1,'money'=>$val['money'],'log_type_id'=>$val['id'],'user_id'=>$val['user_id']);
                if($atype == 'online'){
                    header("Content-type: text/html; charset=utf-8");
                    exit("功能正在开发中。。。");
                }else{
                    accountLog($val['user_id'], ($val['money'] * -1), 0,"管理员处理用户提现申请");//手动转账，默认视为已通过线下转方式处理了该笔提现申请
                    $r = M('withdrawals')->where(array('id'=>$val['id']))->save(array('status'=>2,'pay_time'=>time()));
                    expenseLog($rdata);//支出记录日志
                }
            }
        }
        if($alipay['batch_num']>0){
            //支付宝在线批量付款
            include_once  PLUGIN_PATH."payment/alipay/alipay.class.php";
            $alipay_obj = new \alipay();
            $alipay_obj->transfer($alipay);
        }
        $this->success("操作成功!",U('remittance'),3);
    }

    public function get_withdrawals_list($status=''){
        $user_id = I('user_id/d');
        $realname = I('realname');
        $bank_card = I('bank_card');
        $create_time = I('create_time');
        $create_time = str_replace("+"," ",$create_time);
        $create_time2 = $create_time  ? $create_time  : date('Y-m-d',strtotime('-1 year')).' - '.date('Y-m-d',strtotime('+1 day'));
        $create_time3 = explode(' - ',$create_time2);
        $this->assign('start_time',$create_time3[0]);
        $this->assign('end_time',$create_time3[1]);
        $where['w.create_time'] =  array(array('gt', strtotime(strtotime($create_time3[0])), array('lt', strtotime($create_time3[1]))));
        $status = empty($status) ? I('status') : $status;
        if(empty($status) || $status === '0'){
            $where['w.status'] =  array('lt',1);
        }
        if($status === '0' || $status > 0) {
            $where['w.status'] = $status;
        }

        $where['is_engineer']=1;

        //是否门店登陆
        $suppliers_id = session('suppliers_id');
        if (isset($suppliers_id)) {
            $where['u.suppliers_id'] = $suppliers_id;
        }


        $user_id && $where['u.user_id'] = $user_id;
        $realname && $where['w.realname'] = array('like','%'.$realname.'%');
        $bank_card && $where['w.bank_card'] = array('like','%'.$bank_card.'%');
        $export = I('export');
        if($export == 1){
            $strTable ='<table width="500" border="1">';
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
            if(is_array($remittanceList)){
                foreach($remittanceList as $k=>$val){
                    $strTable .= '<tr>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">'.$val['nickname'].'</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['money'].' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['bank_name'].'</td>';
                    $strTable .= '<td style="vnd.ms-excel.numberformat:@">'.$val['bank_card'].'</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['realname'].'</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.date('Y-m-d H:i:s',$val['create_time']).'</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['remark'].'</td>';
                    $strTable .= '</tr>';
                }
            }
            $strTable .='</table>';
            unset($remittanceList);
            downloadExcel($strTable,'remittance');
            exit();
        }
        $count = Db::name('withdrawals')->alias('w')->join('__USERS__ u', 'u.user_id = w.user_id', 'INNER')->where($where)->count();
        $Page  = new Page($count,20);
        $list = Db::name('withdrawals')->alias('w')->field('w.*,u.nickname')->join('__USERS__ u', 'u.user_id = w.user_id', 'INNER')->where($where)->order("w.id desc")->limit($Page->firstRow.','.$Page->listRows)->select();
        $this->assign('create_time',$create_time2);
        $show  = $Page->show();
        $this->assign('show',$show);
        $this->assign('list',$list);
        $this->assign('pager',$Page);
        C('TOKEN_ON',false);
    }

    /**
     *  转账汇款记录
     */
    public function remittance(){
        $status = I('status',1);
        $this->assign('status',$status);
        $this->get_withdrawals_list($status);
        return $this->fetch();
    }

    /**
     *
     * 发送站内信
     */
    public function sendMessage()
    {
        $user_id_array = I('get.user_id_array');
        $users = array();
        if (!empty($user_id_array)) {
            $users = M('users')->field('user_id,nickname')->where(array('user_id' => array('IN', $user_id_array)))->select();
        }
        $Article =  M('Article');
        $where = " article_type = 3 AND is_open = 1";
        $res = $Article->where($where)->order('article_id desc')->select();
        $this->assign('users',$users);
        $this->assign('article',$res);
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
        $text= I('post.text');//内容
        $article_id= I('post.article_id', 0);//公告
        $type = I('post.type', 0);//个体or全体
        $admin_id = session('admin_id');
        $users = I('post.user/a');//个体id
        $category = I('post.message_type') ? 2 : 0;
        $message = array(
            'admin_id' => $admin_id,
            'message' => $text,
            'category' => $category,
            'state' => 1,
            'common_id' => $article_id,
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
                    M('user_message')->add(array('user_id' => $key, 'message_id' => $create_message_id, 'status' => 0, 'category' => $category));
                }
            }
        }
        echo "<script>parent.{$call_back}(1);</script>";
        exit();
    }

    /**
     * 门店分类
     */
    public function type_list(){
        $type_count = DB::name('suppliers_type')->count();
        $page = new Page($type_count, 10);
        $show = $page->show();
        $type_list = DB::name('suppliers_type')
            ->limit($page->firstRow, $page->listRows)
            ->select();
        $this->assign('list', $type_list);
        $this->assign('page', $show);
        $this->assign('pager', $page);
        return $this->fetch('type_list');
    }

    /**
     * 门店分类详情页
     * @return [type] [description]
     */
    public function supplier_type_info(){
        $type_id = I('get.type_id/d', 0);
        if ($type_id) {
            $info = DB::name('suppliers_type')
                ->where(array('type_id' => $type_id))
                ->find();

            $this->assign('info', $info);
        }

        $act = empty($type_id) ? 'add' : 'edit';
        $this->assign('act', $act);
        return $this->fetch();
    }

    public function supplierTypeHandle(){
        $data = I('post.');
        $type_model = Db::name('suppliers_type');
        //增
        if ($data['act'] == 'add') {
            unset($data['type_id']);
            unset($data['act']);
            $count = $type_model
                ->where("name", $data['name'])
                ->count();
            if ($count) {
                $this->error("分类名称已存在");
            } else {
                $data['add_time'] = time();
                $r = $type_model->insertGetId($data);
            }
        }
        //改
        if ($data['act'] == 'edit' && $data['type_id'] > 0) {
            unset($data['act']);
            $r = $type_model->where('type_id', $data['type_id'])->save($data);
        }

        //删
        if ($data['act'] == 'del' && $data['type_id'] > 0) {
            $r = $type_model->where('type_id', $data['type_id'])->delete();
        }
        if ($r) {
            $this->success("操作成功", U('Admin/Supplier.Supplier/type_list'));
        } else {
            $this->error("操作失败", U('Admin/Supplier.Supplier/type_list'));
        }
    }

    /**
     * 体验店申请详情
     */
    public function door_join_info(){
        $id = I('get.id/d');
        $apply = M('user_apply')->where(['apply_id' => $id])->find();

        if (!$apply) {
            exit($this->error('不存在该申请记录'));
        }
        if (IS_POST) {
            $id = trim(I('post.id'));

            Db::startTrans();

            $apply = M('user_apply')->where(array('apply_id' => $id))->find();
            $data['status'] = trim(I('post.status'));
            $row = M('user_apply')->where(array('apply_id' => $id))->save($data);
            if ($row) {

                //  生成门店表数据
                $suppliers_data['role_id'] = 9;
                $suppliers_data['account'] = $apply['phone']; //  账户
                $suppliers_data['password'] = encrypt($apply['phone']); //密码
                $suppliers_data['suppliers_name'] = $apply['suppliers_name']; //  门店名称
                $suppliers_data['suppliers_desc'] = $apply['suppliers_desc']; // 门店描述
                $suppliers_data['is_directly'] = 0; // 非直营门店
                $suppliers_data['is_check'] = 1; //  门店状态
                $suppliers_data['suppliers_contacts'] = $apply['user_name']; // 门店联系人
                $suppliers_data['suppliers_img'] = '/template/mobile/new2/static/assets/images/dimg.jpg';  //  门店照片
                $suppliers_data['suppliers_phone'] = $apply['phone']; // 门店联系方式
                $suppliers_data['province_id'] = $apply['province']; // 省
                $suppliers_data['city_id'] = $apply['city']; //市
                $suppliers_data['district_id'] = $apply['district']; //区
                // 查询门店地址经纬度
                $address = $this->bmap($apply['store_address']);
                $suppliers_data['lon'] = $address[0]; // 经度
                $suppliers_data['lat'] = $address[1]; // 纬度
                $suppliers_data['address'] = $apply['store_address'];// 门店地址
                $suppliers_data['add_time'] = time(); // 添加时间
                $res = Db::name('suppliers')->insertGetId($suppliers_data);
                
                if ($res){
                    Db::commit();
                    exit(json_encode(array('status' => 1, 'msg' => '操作成功', 'data' => array('url' => U('admin/Supplier.Supplier/door_join_list')))));
                }
                Db::rollback();
                exit(json_encode(array('status' => 0, 'msg' => '操作失败')));
            } else {
                Db::rollback();
                exit(json_encode(array('status' => 0, 'msg' => '操作失败')));
            }
            exit;
        }
        $apply['p_c_d'] = getTotalAddress($apply['province'], $apply['city'], $apply['district'], 0);
        $user = M('users')->where(['user_id' => $apply['user_id']])->find();
        $user['address'] = getTotalAddress($user['province'], $user['city'], $user['district'],0);
        $this->assign('user', $user);
        $this->assign('apply', $apply);
        return $this->fetch();
    }

    // 门店申请2
    public function door_join_list(){
        $begin = date('Y-m-d', strtotime("-1 year"));//30天前
        $end = date('Y/m/d', strtotime('+1 days'));
        $this->assign('timegap', $begin . '-' . $end);
        return $this->fetch();        
    }

    public function ajaxdoor_join_list()
    {
        $model = Db::name('user_apply');
        $username = I('username', '', 'trim');
        $keyword = I('keyword', '', 'trim');
        $status = I('status', '', 'trim');

        $where['a.status'] = 0;
        $where['a.level'] = 4;


        if ($status != '' && $status != null) {
            $where['a.status'] = $status;
        }

        if ($username) {
            $where['u.nickname'] = ['like', '%' . $username . '%'];
        }
        if ($keyword) {
            $where['a.name|a.mobile'] = ['like', '%' . $keyword . '%'];
        }
        $count = $model
            ->alias('a')
            ->join('users u', 'a.user_id = u.user_id', 'LEFT')
            ->where($where)
            ->count();
        $Page = $pager = new AjaxPage($count, 10);
        $show = $Page->show();
        $where['status'] = array('neq',2);
        $supplier_list = $model
            ->alias('a')
            ->field('a.*,u.nickname')
            ->join('users u', 'a.user_id = u.user_id', 'LEFT')
            ->where($where)
            ->order('apply_time DESC')
            ->limit($Page->firstRow . ',' . $Page->listRows)->select();
//        dump($where);
//        exit;
        $this->assign('datalist', $supplier_list);
        $this->assign('page', $show);// 赋值分页输出
        $this->assign('pager', $pager);// 赋值分页输出
        return $this->fetch();
    }

}