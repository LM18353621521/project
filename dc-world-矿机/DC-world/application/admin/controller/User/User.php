<?php

namespace app\admin\controller\User;

use app\admin\logic\OrderLogic;
use think\AjaxPage;
use think\Page;
use think\Verify;
use think\Db;
use app\admin\logic\UsersLogic;
use \app\common\model\Users;
use think\Loader;
use app\admin\controller\Base;
use app\common\logic\QualificationLogic;

class User extends Base {

    public function index(){
        return $this->fetch();
    }

    /**
     * 会员列表
     */
    public function ajaxindex(){
        // 搜索条件
        $condition = array();
        I('mobile') ? $condition['mobile'] = I('mobile') : false;
        I('email') ? $condition['email'] = I('email') : false;
        I('id') ? $condition['user_id'] = I('id') : false;
        I('nickname') ? $condition['nickname'] = ['like','%'.I('nickname').'%'] : false;

        I('first_leader') && ($condition['first_leader'] = I('first_leader')); // 查看一级下线人有哪些
        I('second_leader') && ($condition['second_leader'] = I('second_leader')); // 查看二级下线人有哪些
        I('third_leader') && ($condition['third_leader'] = I('third_leader')); // 查看三级下线人有哪些
        $sort_order = I('order_by').' '.I('sort');
//        dump($condition);die;
        $model = M('users');
        $count = $model->where($condition)->count();
        $Page  = new AjaxPage($count,10);

        $userList = $model->where($condition)->order($sort_order)->limit($Page->firstRow.','.$Page->listRows)->select();
        //  搜索条件下 分页赋值
        foreach($condition as $key=>$val) {
            if ($key == 'nickname'){
                $Page->parameter[$key]   =  trim($val[1]);
                continue;
            }
            $Page->parameter[$key]   =   urlencode($val);

        }

        $user_id_arr = get_arr_column($userList, 'user_id');
        if(!empty($user_id_arr))
        {
            $first_leader = DB::query("select first_leader,count(1) as count  from __PREFIX__users where first_leader in(".  implode(',', $user_id_arr).")  group by first_leader");
            $first_leader = convert_arr_key($first_leader,'first_leader');

            $second_leader = DB::query("select second_leader,count(1) as count  from __PREFIX__users where second_leader in(".  implode(',', $user_id_arr).")  group by second_leader");
            $second_leader = convert_arr_key($second_leader,'second_leader');

            $third_leader = DB::query("select third_leader,count(1) as count  from __PREFIX__users where third_leader in(".  implode(',', $user_id_arr).")  group by third_leader");
            $third_leader = convert_arr_key($third_leader,'third_leader');
        }
        $this->assign('first_leader',$first_leader);
        $this->assign('second_leader',$second_leader);
        $this->assign('third_leader',$third_leader);
        $show = $Page->show();
        $this->assign('userList',$userList);
        $this->assign('level',M('user_level')->getField('level_id,level_name'));
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('pager',$Page);
        return $this->fetch();
    }

    /**
     * 更改会员等级  Lu
     */
    public function level_update(){
        $user_id = I('user_id');
        if(!$user_id > 0) $this->ajaxReturn(['status'=>0,'msg'=>"参数有误"]);
        $user = M('users')->field('user_id,nickname,user_money,level')->where('user_id',$user_id)->find();
        if(IS_POST){
            $level = I('post.level');
            $desc = I('post.desc');
            if(!$level)
                $this->ajaxReturn(['status'=>0,'msg'=>"请选择会员等级"]);

            if($user['level']==$level){
                $this->ajaxReturn(['status'=>-1,'msg'=>"操作失败，您没有对等级进行修改"]);
            }

            if(!$desc)
                $this->ajaxReturn(['status'=>0,'msg'=>"请填写操作说明"]);

            $data['level'] = $level;

            $res = M('users')->where('user_id',$user_id)->update($data);
            if($res)
            {
                adminLog("更改“". $user['nickname']."”等级 ：" . $user['level']."->".$level."，备注：".$desc);
                $this->ajaxReturn(['status'=>1,'msg'=>"操作成功",'url'=>U("Admin/User.User/index")]);
            }else{
                $this->ajaxReturn(['status'=>-1,'msg'=>"操作失败"]);
            }
            exit;
        }

        $level_list =  M('user_level')->order('level_id asc')->select();
        $this->assign('level_list',$level_list);
        $this->assign('user_id',$user_id);
        $this->assign('user',$user);
        return $this->fetch();
    }

    /**
     * 会员详细信息查看
     */
    public function detail(){
        $uid = input('get.id');
        $user_model = new Users();
        $user = $user_model->where(['user_id'=>$uid])->find();
        $level_info = db('user_level')->where(['level_id'=>$user['level']])->find();

        if(!$user)
            exit($this->error('会员不存在'));
        if($this->request->method() == 'POST'){
            $data = input('post.');
            //  会员信息编辑
            if($data['password'] != '' && $data['password'] != $data['password2']){
                exit($this->error('两次输入密码不同'));
            }
            if($data['password'] == '' && $data['password2'] == ''){
                unset($data['password']);
            }else{
                $data['password'] = encrypt($data['password']);
            }

            if(!empty($data['email']))
            {   $email = trim($data['email']);
                $c = $user_model->where("user_id != $uid and email = '$email'")->count();
                $c && exit($this->error('邮箱不得和已有用户重复'));
            }

            if(!empty($data['mobile']))
            {   $mobile = trim($data['mobile']);
                $c = $user_model->where("user_id != $uid and mobile = '$mobile'")->count();
                $c && exit($this->error('手机号不得和已有用户重复'));
            }

            //更新分销关系
            if($user['first_leader'] != $data['first_leader']){
                $result = $this->change_distribution($uid,$data['first_leader']);
                if($result['status'] == 0){
                    exit($this->error($result['status']));
                }
            }

            $row = $user_model->where(['user_id'=>$uid])->save($data);
            if($row)
                exit($this->success('修改成功'));

            if($result['status'] == 1){
                exit($this->success('修改成功'));
            }
            exit($this->error('未作内容修改或修改失败'));
        }
        //下级信息
        $user['first_lower'] = $user_model->where("first_leader = {$user['user_id']}")->count();
        $user['second_lower'] = $user_model->where("second_leader = {$user['user_id']}")->count();
        $user['third_lower'] = $user_model->where("third_leader = {$user['user_id']}")->count();
        //上级信息
        $first_leader = $user_model->where(['user_id'=>$user['first_leader']])->find();

        $this->assign('user',$user);
        $this->assign('first_leader',$first_leader);
        $this->assign('level_info',$level_info);
        return $this->fetch();
    }

    /**
     * 更改会员的上级   Lu
     * @param int $user_id   被改用户
     * @param int $first_leader 上级用户
     * @return array
     */
    public function change_distribution($user_id=0,$first_leader=0){

        $user = D('users')->where(array('user_id'=>$user_id))->find();

        if($user_id==$first_leader){
            return array('status'=>0,'msg'=>'不能把自己设为上级');
        }

        $my_distribtion = M('users')->whereOr(array('first_leader'=>$user_id))->whereOr(array('second_leader'=>$user_id))->whereOr(array('third_leader'=>$user_id))->column('user_id');
        $first_leader_users =  D('users')->where(array('user_id'=>$first_leader))->find();

        if($my_distribtion){
            if(in_array($first_leader,$my_distribtion)){
                return array('status'=>0,'msg'=>'不能把自己的下级设为上级');
            }
        }

        $new_leader['first_leader'] = $first_leader;
        $new_leader['second_leader'] = $first_leader_users['first_leader']?$first_leader_users['first_leader']:0;
        $new_leader['third_leader'] = $first_leader_users['second_leader']?$first_leader_users['second_leader']:0;

        //我的一级下级
        $my_first_distribution = M('users')->where(array('first_leader'=>$user_id))->column('user_id');
        //我的二级下级
        $my_second_distribution = M('users')->where(array('second_leader'=>$user_id))->column('user_id');
        //我的三级下级
        $my_third_distribution = M('users')->where(array('third_leader'=>$user_id))->column('user_id');

        //更改我的一级下级
        if($my_first_distribution){
            $data_first = array(
                'second_leader'=>$new_leader['first_leader'],
                'third_leader'=>$new_leader['second_leader'],
            );
            $res_first =M('users')->where(array('user_id'=>array('in',$my_first_distribution)))->save($data_first);
        }

        //更改我的二级下级
        if($my_second_distribution){
            $data_second = array(
                'third_leader'=>$new_leader['first_leader'],
            );
            $res_second =M('users')->where(array('user_id'=>array('in',$my_second_distribution)))->save($data_second);
        }

        $res1 = M('users')->where(array('user_id'=>$user_id))->update($new_leader);

        return array('status'=>1,'msg'=>'修改成功');
    }

    // 查找用户信息
    public function search_users()
    {
        $user_id = input('user_id');
        $tpl = input('tpl', 'search_users');
        $id = input('id', 0);
        $where = array();
        $where1 = array();

        $user = M('users')->where(array('user_id'=>$user_id))->find();
        $my_distribtion = M('users')->whereOr(array('first_leader'=>$user_id))->whereOr(array('second_leader'=>$user_id))->whereOr(array('third_leader'=>$user_id))->column('user_id');
        array_push($my_distribtion,$user['user_id']);

        $where['user_id'] = array('not in',$my_distribtion);

        if($id){
            $where1['user_id']=$id;
        }


        $model = M('users');
        $count = $model->where($where)->where($where1)->count();
        $Page  = new Page($count,5);
        //  搜索条件下 分页赋值
        $userList = $model->where($where)->where($where1)->order('user_id')->limit($Page->firstRow.','.$Page->listRows)->select();
//        $show = $Page->show();
        $this->assign('page', $Page);
        $this->assign('goodsList', $userList);
        return $this->fetch($tpl);
    }

    /**
     * 会员分销区域代理配置
     * Author:Faramita
     */
    public function detail_distribution(){
        $uid = input('get.id');
        if($this->request->method() == 'POST'){
            $row = db('users')->where(['user_id'=>$uid])->update(['region_code'=>input('post.update_region_code')]);
            if($row !== false)
                exit($this->success('修改成功'));
            exit($this->error('修改失败'));
        }
        //用户信息
        $user_model = new Users();
        $user = $user_model->where(['user_id'=>$uid])->find();
        //查找代理信息
        $level = db('user_level')->where(['is_region_agent'=>1,'level_id'=>$user['level']])->find()['region_code'];
        //存在一人代理多区域，用逗号分隔开存储
        $user_region = explode(',',$user['region_code']);
        //获取省市区数组
        $region = db('region')->select();
        $region_info = [];
        foreach($region as $k => $val){
            $region_info[$val['id']] = $val['parent_id'];
        }
        //省
        $region_content = '';
        $last_region_code = '';
        foreach($user_region as $k => $val){
            //判断代理级数
            if($level == 1){
                $region_code_province[$k] = $val ?: 1;
            }elseif($level == 2){
                $region_code_province[$k] = $region_info[$val] ?: 1;
                $region_code_city[$k] = $val ?: 2;
            }elseif($level == 3){
                $region_code_province[$k] = $region_info[$region_info[$val]] ?: 1;
                $region_code_city[$k] = $region_info[$val] ?: 2;
                $region_code_district[$k] = $val ?: 3;
            }
            //省代理
            $region_content .= "<div><select name='province[]' onchange='get_city_proxy(this,".$k.")' id='province".$k."'>";
            foreach($region as $ks => $vals){
                if($vals['parent_id'] == 0 && $vals['level'] == 1) {
                    if ($vals['id'] == $region_code_province[$k]) {
                        $region_content .= "<option selected value='" . $vals['id'] . "'>" . $vals['name'] . "</option>";
                    } else {
                        $region_content .= "<option value='" . $vals['id'] . "'>" . $vals['name'] . "</option>";
                    }
                }
            }
            $region_content .= "</select>";
            if($level >= 2){
                //市代理
                $region_content .= "<select name='city[]' onchange='get_area_proxy(this,".$k.")' id='city".$k."'>";
                foreach($region as $ks => $vals){
                    if($vals['parent_id'] == $region_code_province[$k]){
                        if($vals['id'] == $region_code_city[$k]){
                            $region_content .= "<option selected value='".$vals['id']."'>".$vals['name']."</option>";
                        }else{
                            $region_content .= "<option value='".$vals['id']."'>".$vals['name']."</option>";
                        }
                    }
                }
                $region_content .= "</select>";
            }
            if($level == 3){
                //区代理
                $region_content .= "<select name='district[]' id='district".$k."'>";
                foreach($region as $ks => $vals){
                    if($vals['parent_id'] == $region_code_city[$k]){
                        if($vals['id'] == $region_code_district[$k]){
                            $region_content .= "<option selected value='".$vals['id']."'>".$vals['name']."</option>";
                        }else{
                            $region_content .= "<option value='".$vals['id']."'>".$vals['name']."</option>";
                        }
                    }
                }
                $region_content .= "</select>";
            }
            $region_content .= '</div>';
        }
        //当前用户自身代理的区域不参与验证区域代理唯一性，所以需要将当前用户代理的区域配置为忽略区域
        if($user['region_code']){
            if($level == 1){
                $last_region_code = implode(',',$region_code_province);
            }elseif($level == 2){
                $last_region_code = implode(',',$region_code_city);
            }elseif($level == 3){
                $last_region_code = implode(',',$region_code_district);
            }
        }

        $this->assign('region_content', $region_content);
        $this->assign('last_region_code',$last_region_code);
        $this->assign('user',$user);
        $this->assign('level',$level);
        return $this->fetch();
    }

    public function add_user(){
        if(IS_POST){
            $data = I('post.');
            //设置初始角色
            $data['level'] = 1;
            $user_obj = new UsersLogic();
            $res = $user_obj->addUser($data);
            if($res['status'] == 1){
                $this->success('添加成功',U('User.User/index'));exit;
            }else{
                $this->error('添加失败,'.$res['msg'],U('User.User/index'));
            }
        }
        return $this->fetch();
    }

    public function export_user(){
    	$strTable ='<table width="500" border="1">';
    	$strTable .= '<tr>';
    	$strTable .= '<td style="text-align:center;font-size:12px;width:120px;">会员ID</td>';
    	$strTable .= '<td style="text-align:center;font-size:12px;" width="100">会员昵称</td>';
    	$strTable .= '<td style="text-align:center;font-size:12px;" width="*">会员等级</td>';
    	$strTable .= '<td style="text-align:center;font-size:12px;" width="*">手机号</td>';
    	$strTable .= '<td style="text-align:center;font-size:12px;" width="*">邮箱</td>';
    	$strTable .= '<td style="text-align:center;font-size:12px;" width="*">注册时间</td>';
    	$strTable .= '<td style="text-align:center;font-size:12px;" width="*">最后登陆</td>';
    	$strTable .= '<td style="text-align:center;font-size:12px;" width="*">余额</td>';
    	$strTable .= '<td style="text-align:center;font-size:12px;" width="*">积分</td>';
    	$strTable .= '<td style="text-align:center;font-size:12px;" width="*">累计消费</td>';
    	$strTable .= '</tr>';
    	$count = M('users')->count();
    	$p = ceil($count/5000);
    	for($i=0;$i<$p;$i++){
    		$start = $i*5000;
    		$end = ($i+1)*5000;
    		$userList = M('users')->order('user_id')->limit($start.','.$end)->select();
    		if(is_array($userList)){
    			foreach($userList as $k=>$val){
    				$strTable .= '<tr>';
    				$strTable .= '<td style="text-align:center;font-size:12px;">'.$val['user_id'].'</td>';
    				$strTable .= '<td style="text-align:left;font-size:12px;">'.$val['nickname'].' </td>';
    				$strTable .= '<td style="text-align:left;font-size:12px;">'.$val['level'].'</td>';
    				$strTable .= '<td style="text-align:left;font-size:12px;">'.$val['mobile'].'</td>';
    				$strTable .= '<td style="text-align:left;font-size:12px;">'.$val['email'].'</td>';
    				$strTable .= '<td style="text-align:left;font-size:12px;">'.date('Y-m-d H:i',$val['reg_time']).'</td>';
    				$strTable .= '<td style="text-align:left;font-size:12px;">'.date('Y-m-d H:i',$val['last_login']).'</td>';
    				$strTable .= '<td style="text-align:left;font-size:12px;">'.$val['user_money'].'</td>';
    				$strTable .= '<td style="text-align:left;font-size:12px;">'.$val['pay_points'].' </td>';
    				$strTable .= '<td style="text-align:left;font-size:12px;">'.$val['total_amount'].' </td>';
    				$strTable .= '</tr>';
    			}
    			unset($userList);
    		}
    	}
    	$strTable .='</table>';
    	downloadExcel($strTable,'users_'.$i);
    	exit();
    }

    /**
     * 用户收货地址查看
     */
    public function address(){
        $uid = I('get.id');
        $user_address_model = new \app\common\model\UserAddress();
        $lists = $user_address_model->where(array('user_id'=>$uid))->select();
        $regionList = get_region_list();
        $this->assign('regionList',$regionList);
        $this->assign('lists',$lists);
        return $this->fetch();
    }

    /**
     * 删除会员
     */
    public function delete(){
        $uid = I('get.id');
        $row = M('users')->where(array('user_id'=>$uid))->delete();
        if($row){
            $this->success('成功删除会员');
        }else{
            $this->error('操作失败');
        }
    }
    /**
     * 删除会员
     */
    public function ajax_delete(){
        $uid = I('id');
        if($uid){
            $row = M('users')->where(array('user_id'=>$uid))->delete();
            // 检查自动登录表是否含有该用户的信息
            $res = Db::name('oauth_users')->where(['user_id'=>$uid])->count();
            if ($res)
                $oauth_row = Db::name('oauth_users')->where(['user_id'=>$uid])->delete();
            if($row !== false){
                if ($res && $oauth_row) {
                    $this->ajaxReturn(array('status' => 1, 'msg' => '删除成功', 'data' => ''));
                    exit;
                }
                $this->ajaxReturn(array('status' => 1, 'msg' => '删除成功', 'data' => ''));
            }else{
                $this->ajaxReturn(array('status' => 0, 'msg' => '删除失败', 'data' => ''));
            }
        }else{
            $this->ajaxReturn(array('status' => 0, 'msg' => '参数错误', 'data' => ''));
        }
    }

    /**
     * 账户资金记录
     */
    public function account_log(){
        $user_id = I('get.id');
        //获取类型
        $type = I('get.type');
        //获取记录总数
        $count = M('account_log')->where(array('user_id'=>$user_id))->count();
        $page = new Page($count);
        $lists  = M('account_log')->where(array('user_id'=>$user_id))->order('change_time desc')->limit($page->firstRow.','.$page->listRows)->select();

        $this->assign('user_id',$user_id);
        $this->assign('page',$page->show());
        $this->assign('lists',$lists);
        return $this->fetch();
    }

    /**
     * 账户资金调节
     */
    public function account_edit(){
        $user_id = I('user_id');
        if(!$user_id > 0) $this->ajaxReturn(['status'=>0,'msg'=>"参数有误"]);
        $user = M('users')->field('user_id,user_money,frozen_money,pay_points,is_lock')->where('user_id',$user_id)->find();
        if(IS_POST){
            $desc = I('post.desc');
            if(!$desc)
                $this->ajaxReturn(['status'=>0,'msg'=>"请填写操作说明"]);
            //加减用户资金
            $m_op_type = I('post.money_act_type');
            $user_money = I('post.user_money/f');
            $user_money =  $m_op_type ? $user_money : 0-$user_money;
            //加减用户积分
            $p_op_type = I('post.point_act_type');
            $pay_points = I('post.pay_points/d');
            $pay_points =  $p_op_type ? $pay_points : 0-$pay_points;
            //加减冻结资金
            $f_op_type = I('post.frozen_act_type');
            $revision_frozen_money = I('post.frozen_money/f');
            if( $revision_frozen_money != 0){    //有加减冻结资金的时候
                $frozen_money =  $f_op_type ? $revision_frozen_money : 0-$revision_frozen_money;
                $frozen_money = $user['frozen_money']+$frozen_money;    //计算用户被冻结的资金
                if($f_op_type==1 and $revision_frozen_money > $user['user_money'])
                {
                    $this->ajaxReturn(['status'=>0,'msg'=>"用户剩余资金不足！！"]);
                }
                if($f_op_type==0 and $revision_frozen_money > $user['frozen_money'])
                {
                    $this->ajaxReturn(['status'=>0,'msg'=>"冻结的资金不足！！"]);
                }
                $user_money = $f_op_type ? 0-$revision_frozen_money : $revision_frozen_money ;    //计算用户剩余资金
                M('users')->where('user_id',$user_id)->update(['frozen_money' => $frozen_money]);
            }
            if(accountLog($user_id,$user_money,$pay_points,$desc,0))
            {
                $this->ajaxReturn(['status'=>1,'msg'=>"操作成功",'url'=>U("Admin/User.User/account_log",array('id'=>$user_id))]);
            }else{
                $this->ajaxReturn(['status'=>-1,'msg'=>"操作失败"]);
            }
            exit;
        }
        $this->assign('user_id',$user_id);
        $this->assign('user',$user);
        return $this->fetch();
    }

    public function recharge(){
    	$timegap = urldecode(I('timegap'));
    	$nickname = I('nickname');
    	$map = array();
    	if($timegap){
    		$gap = explode(',', $timegap);
    		$begin = $gap[0];
    		$end = $gap[1];
    		$map['ctime'] = array('between',array(strtotime($begin),strtotime($end)));
    	}
    	if($nickname){
    		$map['nickname'] = array('like',"%$nickname%");
    	}
    	$count = M('recharge')->where($map)->count();
    	$page = new Page($count);
    	$lists  = M('recharge')->where($map)->order('ctime desc')->limit($page->firstRow.','.$page->listRows)->select();
    	$this->assign('page',$page->show());
        $this->assign('pager',$page);
    	$this->assign('lists',$lists);
    	return $this->fetch();
    }

    /**
     * 角色详情
     * @return mixed
     * Author:Faramita
     */
    public function level(){
        $act = I('get.act','add');
        $this->assign('act',$act);
        $level_id = I('get.level_id');
        if($level_id){
            //获取处理好的配置数组
            $QualificationLogic = new QualificationLogic();
            //需要获取的配置类型
            $inc_type = $QualificationLogic->PUBLIC_INC_TYPE;

            $inc_info = $QualificationLogic->get_qualification_handle($level_id,$inc_type,1);
            $inc_info2 = $QualificationLogic->get_qualification_handle($level_id,$inc_type,2);
            //获取购买条件计算时机配置
            $update_point = $QualificationLogic->get_update_point($level_id,1);
            $update_point2 = $QualificationLogic->get_update_point($level_id,2);
            //获取角色信息
            $level_info = db('user_level')->where('level_id='.$level_id)->find();

            $this->assign('inc_info',$inc_info);
            $this->assign('inc_info2',$inc_info2);
            $this->assign('info',$level_info);
        }
        //购买条件计算时机无配置则默认是下单时
        $update_point = $update_point ?: 1;
        $update_point2 = $update_point2 ?: 1;
        //获取分类商品信息
        $GoodsLogic = new \app\admin\logic\GoodsLogic();
        $identity_list = $GoodsLogic->type_identity_list();

        $check_role = db('user_level')->field('level_id,level_name')->select();

        $this->assign('update_point',$update_point);
        $this->assign('update_point2',$update_point2);
        $this->assign('check_role',$check_role);
        $this->assign('identity_list',$identity_list);
        return $this->fetch();
    }

    public function levelList(){
    	$Ad =  db('user_level');
        $p = $this->request->param('p');
    	$res = $Ad->order('level_id')->page($p.',10')->select();
    	if($res){
    		foreach ($res as $val){
    			$list[] = $val;
    		}
    	}
    	$this->assign('list',$list);
    	$count = $Ad->count();
    	$Page = new Page($count,10);
    	$show = $Page->show();
    	$this->assign('page',$show);
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
                $r = db('user_level')->insert($data);
                if ($r !== false) {
                    //存储条件配置
                    $QualificationLogic = new QualificationLogic();
                    $QualificationLogic->qualification_handle($r,$data);
                    $QualificationLogic->set_update_point($r,$data['update_point'],1);
                    $QualificationLogic->set_update_point($r,$data['update_point2'],2);
                    $return = ['status' => 1, 'msg' => '添加成功', 'result' => $userLevelValidate->getError()];
                } else {
                    $return = ['status' => 0, 'msg' => '添加失败，数据库未响应', 'result' => ''];
                }
            }
        }
        if ($data['act'] == 'edit') {
            if (!$userLevelValidate->scene('edit')->batch()->check($data)) {
                $return = ['status' => 0, 'msg' => '编辑失败', 'result' => $userLevelValidate->getError()];
            } else {
                $r = db('user_level')->where('level_id=' . $data['level_id'])->update($data);
                if ($r !== false) {
                    //存储条件配置
                    $QualificationLogic = new QualificationLogic();
                    $QualificationLogic->qualification_handle($data['level_id'],$data);
                    $QualificationLogic->set_update_point($data['level_id'],$data['update_point'],1);
                    $QualificationLogic->set_update_point($data['level_id'],$data['update_point2'],2);
                    $return = ['status' => 1, 'msg' => '编辑成功', 'result' => $userLevelValidate->getError()];
                } else {
                    $return = ['status' => 0, 'msg' => '编辑失败，数据库未响应', 'result' => ''];
                }
            }
        }
        if ($data['act'] == 'del') {
            //检测是否有属于该角色的用户，且不是初始角色
            $check_role_del = db('users')->where(['level'=>$data['level_id']])->select();
            if(empty($check_role_del) && $data['level_id'] != '1'){
                //删除角色
                $r = db('user_level')->where('level_id=' . $data['level_id'])->delete();
                //删除当前角色所有条件配置
                $QualificationLogic = new QualificationLogic();
                $del_one = $QualificationLogic->del_qualification_handle($data['level_id'],[],1);
                $del_two = $QualificationLogic->del_qualification_handle($data['level_id'],[],2);
                //删除当前角色其他配置
                $del_other = $QualificationLogic->del_qualification_handle($data['level_id'],[],0);

                if ($r !== false && $del_one['status'] && $del_two['status'] && $del_other['status']) {
                    $return = ['status' => 1, 'msg' => '删除成功', 'result' => ''];
                } else {
                    $return = ['status' => 0, 'msg' => '删除失败，数据库未响应', 'result' => ''];
                }
            }else{
                $return = ['status' => 0, 'msg' => '删除失败，当前还有属于该角色的用户', 'result' => ''];
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
        if(strstr($search_key,'@'))
        {
            $list = M('users')->where(" email like '%$search_key%' ")->select();
            foreach($list as $key => $val)
            {
                echo "<option value='{$val['user_id']}'>{$val['email']}</option>";
            }
        }
        else
        {
            $list = M('users')->where(" mobile like '%$search_key%' ")->select();
            foreach($list as $key => $val)
            {
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
        $this->assign('users',$users);
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
        $email= I('post.email');
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
        if($email){
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
        
        //  添加奖项信息
        $prizeLogic = new  \app\common\logic\DistributPrizeLogic();
        foreach ($list as $key => $val) {

            $prizeLogic->setUserId($val['user_id']);
            $prizeInfo = ['integral_prize'];
            $prize_res = $prizeLogic->getIntegralPrizeInfo($val['money']);
            if (empty($prize_res)){ // 奖项未开启 组装
                $list[$key]['is_prize'] = '未开启';
                $list[$key]['integral'] = 0;
            } else {
                $list[$key]['is_prize'] = $prize_res['is_prize'];
                $list[$key]['integral'] = empty($prize_res['integral']) ? 0 : $prize_res['integral'];
            }
        }


        $this->assign('create_time',$create_time2);
        $show  = $Page->show();
        $this->assign('show',$show);
        $this->assign('list',$list);
        $this->assign('pager',$Page);
        C('TOKEN_ON',false);
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
        $withdrawals = $model->find($id);
        $user = M('users')->where("user_id = {$withdrawals[user_id]}")->find();
        if($user['nickname'])
            $withdrawals['user_name'] = $user['nickname'];
        elseif($user['email'])
            $withdrawals['user_name'] = $user['email'];
        elseif($user['mobile'])
            $withdrawals['user_name'] = $user['mobile'];

        // lzz 查询奖项情况
        $prizeLogic = new  \app\common\logic\DistributPrizeLogic();
        $prizeLogic->setUserId($withdrawals['user_id']);
        $prize_res = $prizeLogic->getIntegralPrizeInfo($withdrawals['money']);
        if (empty($prize_res)){ // 奖项未开启 组装
            $withdrawals['is_prize'] = '未开启';
            $withdrawals['integral'] = 0;
        } else {
            $withdrawals['is_prize'] = $prize_res['is_prize'];
            $withdrawals['integral'] = empty($prize_res['integral']) ? 0 : $prize_res['integral'];
        }

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
        $prizeLogic = new  \app\common\logic\DistributPrizeLogic();
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
                    $prizeLogic->setUserId($val['user_id']);
                    $prizeInfo = ['integral_prize'];
                    $prize_res = $prizeLogic->distribut(['integral_prize'], $val['money']);

    				accountLog($val['user_id'], (($val['taxfee']+$val['money']) * -1), 0,"管理员处理用户提现申请");//手动转账，默认视为已通过线下转方式处理了该笔提现申请
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

    /**
     * 用户升级等级申请列表
     * Author:Faramita
     */
    public function applyList(){
        $user_id = input('user_id');
        $create_time = input('create_time');
        $create_time = str_replace("+"," ",$create_time);
        $create_time2 = $create_time ? $create_time  : date('Y-m-d',strtotime('-30 day')).' - '.date('Y-m-d',strtotime('+1 day'));
        $create_time3 = explode(' - ',$create_time2);

        $where['apply_time'] = ['gt',strtotime($create_time3[0]),'lt', strtotime($create_time3[1])];
        $status = empty($status) ? input('status') : $status;
        if(empty($status) || $status === '0'){
            $where['status'] = ['lt',1];
        }
        if($status === '0' || $status > 0) {
            $where['status'] = $status;
        }
        if($user_id){
            $where['user_id'] = $user_id;
        }
        $count = Db::name('user_apply')->where($where)->count();
        $Page  = new Page($count,20);
        $list = Db::name('user_apply')->where($where)->order("apply_id desc")->limit($Page->firstRow.','.$Page->listRows)->select();
        //地址列表
        $region = db('region')->select();
        foreach($region as $k => $val){
            $region_arr[$val['id']] = $val['name'];
        }
        //角色列表
        $role = db('user_level')->select();
        foreach($role as $k => $val){
            $role_arr[$val['level_id']] = $val['level_name'];
        }
        foreach($list as $k => $val){
            //地址拼接
            $list[$k]['address'] = $region_arr[$val['province']].$region_arr[$val['city']].$region_arr[$val['district']].$val['address'];
            if($val['region_code']){
                $list[$k]['region_code'] = $region_arr[$val['region_code']];
            }else{
                $list[$k]['region_code'] = '';
            }
            //申请的角色名称
            $list[$k]['role_name'] = $role_arr[$val['level']];
            //申请人当前角色
            $list[$k]['user_role'] = db('users')->where(['user_id'=>$val['user_id']])->find()['level'];
            $list[$k]['user_role'] = $role_arr[$list[$k]['user_role']];
        }

        $show = $Page->show();
        $this->assign('create_time',$create_time2);
        $this->assign('start_time',$create_time3[0]);
        $this->assign('end_time',$create_time3[1]);
        $this->assign('show',$show);
        $this->assign('list',$list);
        $this->assign('pager',$Page);
        return $this->fetch();
    }

    /**
     * 申请详情
     * Author:Faramita
     */
    public function apply_detail(){
        $apply_id = input('get.apply_id');
        $data = db('user_apply')->where(['apply_id'=>$apply_id])->find();
        $data['region_name'] = db('region')->where(['id'=>$data['region_code']])->find()['name'];
        $data['level_name'] = db('user_level')->where(['level_id'=>$data['level']])->find()['level_name'];
        $this->assign('data',$data);
        return $this->fetch();
    }

    /**
     * 处理用户升级等级申请
     * Author:Faramita
     */
    public function applyList_update(){
        $str_apply_id = input('post.apply_id');
        $apply_id = explode(',',$str_apply_id);
        $data['status'] = input('post.status');
        $data['remark'] = input('post.remark');
        $data['handle_time'] = time();
        //申请通过
        if($data['status'] == 1){
            //先初始化验证状态，3=验证中
            $valid = db('user_apply')->where('apply_id in ('.$str_apply_id.')')->update(['validate_status'=>3]);
            if($valid === false){
                $this->ajaxReturn(['status'=>-1,'msg'=>'网络错误，请重新再试']);exit();
            }
            //角色列表信息获取
            $role = db('user_level')->select();
            foreach($role as $k => $val){
                $role_arr[$val['level_id']] = $val['region_code'];
            }
            //准备验证升级角色,此处是申请的地方----------标记<QualificationLogic>
            $qualificationLogic = new \app\common\logic\QualificationLogic();
            foreach($apply_id as $k => $val){
                //连表获取用户信息
                $user_info = DB::name('user_apply')->alias('ua')
                    ->field('ua.*,u.level as u_level')
                    ->join('__USERS__ u', 'u.user_id = ua.user_id', 'INNER')
                    ->where(['ua.apply_id'=>$val])->find();
                //判断等级
                if(($user_info['u_level'] == $user_info['level']) && $role_arr[$user_info['level']]){
                    //用户与申请等级相同，且申请的身份已开启了区域代理，判断为一人代理多区域，直接走特殊验证同级升级
                    $proxy = $qualificationLogic->update_special_proxy($user_info['user_id'],$user_info['level'],$user_info['region_code']);
                    if($proxy){
                        //验证通过，更新数据
                        $data['validate_status'] = 1;
                        $result = db('user_apply')->where(['apply_id'=>$val])->update($data);
                    }else{
                        //验证失败，更新数据
                        $result = db('user_apply')->where(['apply_id'=>$val])->update(['validate_status'=>2,'status'=>2]);
                        $error[$k] = '用户与申请等级相同，且申请的身份已开启了区域代理，判断为一人代理多区域，但验证失败';
                    }
                }elseif($user_info['u_level'] >= $user_info['level']){
                    //用户当前等级大于申请的等级，则验证失败，更新数据
                    $result = db('user_apply')->where(['apply_id'=>$val])->update(['validate_status'=>2,'status'=>2]);
                    $error[$k] = '用户当前等级大于申请的等级，判定验证失败';
                }else{
                    //验证用户是否符合条件,尝试升级等级
                    $qualificationLogic->prepare_update_level($user_info['user_id'],['apply_level'=>$user_info['level'],'region_code'=>$user_info['region_code']]);
                    //尝试升级后，判断用户是否升级成功
                    $check_update = db('users')->where(['user_id'=>$user_info['user_id'],'level'=>$user_info['level']])->find();
                    if($check_update){
                        //申请通过，更新数据
                        $data['validate_status'] = 1;
                        $result = db('user_apply')->where(['apply_id'=>$val])->update($data);
                        //申请的是体验店，需要生成门店
                        if($user_info['level'] == 4){
                            $suppliers_data['role_id'] = 9;//门店
                            $suppliers_data['account'] = $user_info['phone']; //  账户
                            $suppliers_data['password'] = encrypt($user_info['phone']); //密码
                            $suppliers_data['suppliers_name'] = $user_info['suppliers_name']; //  门店名称
                            $suppliers_data['suppliers_desc'] = $user_info['suppliers_desc']; // 门店描述
                            $suppliers_data['is_directly'] = 0; // 非直营门店
                            $suppliers_data['is_check'] = 1; //  门店状态
                            $suppliers_data['suppliers_contacts'] = $user_info['user_name']; // 门店联系人
                            $suppliers_data['suppliers_img'] = $user_info['store_img'];  //  门店照片
                            $suppliers_data['suppliers_phone'] = $user_info['phone']; // 门店联系方式
                            $suppliers_data['province_id'] = $user_info['province']; // 省
                            $suppliers_data['city_id'] = $user_info['city']; //市
                            $suppliers_data['district_id'] = $user_info['district']; //区
                            // 查询门店地址经纬度
                            $Supplier = new \app\admin\controller\Supplier\Supplier();
                            $suppliers_address = $Supplier->bmap($user_info['store_address']);
                            $suppliers_data['lon'] = $suppliers_address[0]; // 经度
                            $suppliers_data['lat'] = $suppliers_address[1]; // 纬度
                            $suppliers_data['address'] = $user_info['store_address'];// 门店地址
                            $suppliers_data['add_time'] = time(); // 添加时间

                            $suppliers_result = db('suppliers')->insert($suppliers_data);
                            if($suppliers_result == false){
                                $error[$k] = '体验店申请成功，生成门店失败';
                            }
                        }
                    }else{
                        //验证失败，更新数据
                        $result = db('user_apply')->where(['apply_id'=>$val])->update(['validate_status'=>2,'status'=>2]);
                        $error[$k] = '不符合申请身份条件，验证失败';
                    }
                }
                unset($user_info);
            }
            //验证失败或者数据库操作失败
            if($error || $result === false){
                $this->ajaxReturn(['status'=>-1,'msg'=>'操作失败或部分申请不符合条件','data'=>$error]);
            }else{
                $this->ajaxReturn(['status'=>1,'msg'=>'操作成功']);
            }
        }else{
            $result = db('user_apply')->where('apply_id in ('.$str_apply_id.')')->update($data);
            if($result !== false){
                $this->ajaxReturn(['status'=>1,'msg'=>'操作成功']);
            }else{
                $this->ajaxReturn(['status'=>-1,'msg'=>'操作失败']);
            }
        }
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
     * 海报列表
     */
    public function poster(){
        return $this->fetch();
    }

    /**
     * ajax海报列表
     */
    public function ajax_poster(){
        $count =  M('poster')->count();
        $Page = new AjaxPage($count, 12);
        $posterList = M('poster')->order('type desc,id asc')->limit($Page->firstRow.','.$Page->listRows)->select();
        $show = $Page->show();
        $this->assign('list', $posterList);
        $this->assign('page',$show);
        return $this->fetch();
    }

    /*
     * 添加海报
     * */
    public function addPoster(){
        if (IS_POST){
            $base64_img = trim($_POST['img']);
            $up_dir = './public/poster/';//存放在当前目录的upload文件夹下
            if(!file_exists($up_dir)){
                mkdir($up_dir,0777);
            }
            if(preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64_img, $result)){
                $type = $result[2];
                if(in_array($type,array('pjpeg','jpeg','jpg','gif','bmp','png'))){
                    $new_file = $up_dir.date('YmdHis_').'.'.$type;
                    if(file_put_contents($new_file, base64_decode(str_replace($result[1], '', $base64_img)))){
                        $img_path = str_replace('../../..', '', $new_file);

                        //  存入数据库
                        $data = $_POST;
                        if ($data['nk_color']){
                            $data['nk_color'] = explode('rgb(',$data['nk_color']);
                            $data['nk_color'] = explode(')',$data['nk_color'][1])[0];
                        }
                        if ($data['nk_font']){
                            $data['nk_font'] = explode('px',$data['nk_font'])[0];
                        }
                        $data['img'] = str_replace('../../..', '', '/public/poster/'.date('YmdHis_').'.'.$type);
                        $data['add_time'] = time();
                        $type = M('poster')->where(['type'=> 1])->count();
                        if (!$type){
                            $data['type'] = 1;
                        }
                        M('poster')->insertGetId($data);
                        exit(json_encode(['code' => 1, 'data' =>$img_path]));
//                         echo '图片上传成功</br><img src="' .$img_path. '">';
                    }else{
                        exit(json_encode(['code' => -1, 'data' =>'', 'msg' => '图片上传失败']));
                        $this->error('图片上传失败');
                    }
                }else{
                    exit(json_encode(['code' => -1, 'data' =>'', 'msg' => '图片上传类型错误']));
                }

            }else{
                exit(json_encode(['code' => -1, 'data' =>'', 'msg' => '文件错误']));
            }
            exit;
        }
        return $this->fetch();
    }

    public function delPoster(){
        $ids = I('post.ids','');
        empty($ids) &&  $this->ajaxReturn(['status' => -1,'msg' =>"非法操作！",'data'  =>'']);
        $id = rtrim($ids,",");

        // 删除此商品
        M("poster")->whereIn('id',$id)->delete();  //商品表

        $this->ajaxReturn(['status' => 1,'msg' => '操作成功','url'=>url("Admin/User.User/poster")]);
    }

    /**
     * 删除海报
     */
    public function ajax_poster_delete(){
        $id = I('id');
        if($id){
            $row = M('poster')->where(array('id'=>$id))->delete();
            if($row !== false){
                $this->ajaxReturn(array('status' => 1, 'msg' => '删除成功', 'data' => ''));
            }else{
                $this->ajaxReturn(array('status' => 0, 'msg' => '删除失败', 'data' => ''));
            }
        }else{
            $this->ajaxReturn(array('status' => 0, 'msg' => '参数错误', 'data' => ''));
        }
    }

    public function setDefault(){
        $id = I('id');
        if($id){
            $general = M('poster')->where(['type' => 1])->save(['type' => 0]);
            $default = M('poster')->where(['id' => $id])->save(['type' => 1]);
            if($default){
                $this->success('操作成功');
            }else{
                $this->error('操作失败');
            }
        }else{
            $this->error('参数错误');
        }
    }

        /**
     * 签到列表
     * @date 2017/09/28
     */
    public function signList() {
    header("Content-type: text/html; charset=utf-8");
exit("功能正在开发中。。。");
    }


    /**
     * 会员签到 ajax
     * @date 2017/09/28
     */
    public function ajaxsignList() {
    header("Content-type: text/html; charset=utf-8");
exit("功能正在开发中。。。");
    }

    /**
     * 签到规则设置
     * @date 2017/09/28
     */
    public function signRule() {
    header("Content-type: text/html; charset=utf-8");
exit("功能正在开发中。。。");
    }
}