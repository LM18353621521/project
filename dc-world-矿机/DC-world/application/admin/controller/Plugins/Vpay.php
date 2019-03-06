<?php

namespace app\admin\controller\Plugins;

use app\admin\controller\Base;
use app\common\logic\OrderLogic;
use app\common\logic\TeamActivityLogic;
use app\common\model\Order;
use app\common\model\TeamActivity;
use app\common\model\TeamFollow;
use app\common\model\TeamFound;
use think\Loader;
use think\Db;
use think\Page;

class Vpay extends Base
{
    public function index()
    {
        /*配置列表*/
        $group_list = [
            'vpay_spstem' => '基本设置',
            //'prize'     => '奖项设置',
            'vpay_spstem_role'=> '买入转入释放',
            'vpay_spstem_sms'    => '短信设置',
            'vpay_spstem_currency'    => '通配设置',
            'vpay_spstem_share' => '推广中心',
        ];
        $this->assign('group_list',$group_list);
        $inc_type =  I('get.inc_type','vpay_spstem');
        $this->assign('inc_type',$inc_type);
        $config = tpCache($inc_type);
        if(I('get.inc_type') == 'vpay_spstem_role'){
            $config['pushs'] = unserialize($config['pushs']);
            $config['integral'] = unserialize($config['integral']);
        }
        if(I('get.inc_type') == 'vpay_spstem_currency'){
            $config['name'] = tpCache('vpay_spstem.name');
        }
        $this->assign('config',$config);//当前配置项
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
        // dump($param);
        //unset($param['__hash__']);
        if($inc_type == 'vpay_spstem_role'){
            if($param['push'] && $param['return_numbers']){
                foreach($param['push'] as $key=>$val){
                    $result['pushs'][$key][]  = $val;
                    $result['pushs'][$key][]  = $param['return_numbers'][$key];
                }
                $param['pushs'] = serialize($result['pushs']);
                $param['integral'] = serialize($param['integral']);
                unset($param['push']);
                unset($param['return_numbers']);
            }
        }
        if($inc_type == 'vpay_spstem_share'){
            $param['content'] = base64_encode($param['content']);
        }
        unset($param['inc_type']);
        tpCache($inc_type,$param);
        $this->success("操作成功",U('Plugins.Vpay/index',array('inc_type'=>$inc_type)));
    }
    /*
     * 转入转出
     * */
    public function transfer(){

        $model = M('transfer');
        $map = array();
        $mtype = I('mtype');

        $condition = I('condition');
        $search_key = I('search_key');
        switch ($condition){
            case 1: //手机
                $map['t.account'] =  $search_key;
                break;
            case 2: // ID
                $map['t.id'] = $search_key;
                break;
            case 3: //昵称
                $map['m.nickname'] =  array('like',"%$search_key%");
                break;
            case 4: //手机
                $map['t.toUserAccount'] =  $search_key;
                break;
            default:
                break;
        }

        if($mtype == 1){
            $map['stock'] = array('gt',0);
        }
        if($mtype == -1){
            $map['stock'] = array('lt',0);
        }
        $id = I('id');
        if($id){
            $map['id'] = array('like',"%$id%");
        }
        $ctime = urldecode(I('ctime'));
        if($ctime){
            $gap = explode(' - ', $ctime);
            $this->assign('start_time',$gap[0]);
            $this->assign('end_time',$gap[1]);
            $this->assign('ctime',$gap[0].' - '.$gap[1]);
            $map['t.createTime'] = array(array('gt',$gap[0]),array('lt',$gap[1]));
        }
        $count = $model->alias("t")
            ->join("member m","m.id=t.userId",'left')
            ->join("member tm","tm.id=t.toUserId",'left')
            ->field("t.*,m.nickname mname,tm.nickname tmname")
            ->where($map)
            ->count();
        $Page  = new Page($count,20);
        $show = $Page->show();
        $this->assign('pager',$Page);
        $this->assign('page',$show);// 赋值分页输出
        $list = M('transfer')
            ->alias("t")
            ->join("member m","m.id=t.userId",'left')
            ->join("member tm","tm.id=t.toUserId",'left')
            ->field("t.*,m.nickname mname,tm.nickname tmname")
            ->where($map)
            ->order('t.id DESC')
            ->limit($Page->firstRow.','.$Page->listRows)->select();
        $this->assign('list',$list);
        return $this->fetch();
    }


    /*
    * 交易明细
    * */
    public function transaction(){

        $model = M('transaction');
        $map = array();
        $mtype = I('mtype');

        $condition = I('condition');
        $search_key = I('search_key');
        switch ($condition){
            case 1: //手机
                $map['t.seller'] =  $search_key;
                break;
            case 2: // ID
                $map['t.id'] = $search_key;
                break;
            case 3: //昵称
                $map['m.nickname'] =  array('like',"%$search_key%");
                break;
            case 4: //手机
                $map['t.buyer'] =  $search_key;
                break;
            default:
                break;
        }

        if($mtype == 1){
            $map['stock'] = array('gt',0);
        }
        if($mtype == -1){
            $map['stock'] = array('lt',0);
        }
        $id = I('id');
        if($id){
            $map['id'] = array('like',"%$id%");
        }
        $ctime = urldecode(I('ctime'));
        if($ctime){
            $gap = explode(' - ', $ctime);
            $this->assign('start_time',$gap[0]);
            $this->assign('end_time',$gap[1]);
            $this->assign('ctime',$gap[0].' - '.$gap[1]);
            $map['t.createTime'] = array(array('gt',$gap[0]),array('lt',$gap[1]));
        }
        $count = $model->alias("t")
            ->join("member m","m.id=t.sellerId","LEFT")
            ->join("member tm","tm.id=t.buyerId","LEFT")
            ->field("t.*,m.nickname sname,tm.nickname bname")
            ->where($map)
            ->count();
        $Page  = new Page($count,20);
        $show = $Page->show();
        $this->assign('pager',$Page);
        $this->assign('page',$show);// 赋值分页输出
        $list = M('transaction')
            ->alias("t")
            ->join("member m","m.id=t.sellerId","LEFT")
            ->join("member tm","tm.id=t.buyerId","LEFT")
            ->field("t.*,m.nickname sname,tm.nickname bname")
            ->where($map)
            ->order('t.id DESC')
            ->limit($Page->firstRow.','.$Page->listRows)->select();
        $this->assign('list',$list);
        return $this->fetch();
    }
    /*
    * 数字资产明细
    * */
    public function vctransaction(){

        $model = M('vctransaction');
        $map = array();
        $mtype = I('mtype');

        $condition = I('condition');
        $search_key = I('search_key');
        switch ($condition){
            case 1: //手机
                $map['t.seller'] =  $search_key;
                break;
            case 2: // ID
                $map['t.id'] = $search_key;
                break;
            case 3: //昵称
//                $map['m.nickname'] =  array('like',"%$search_key%");
                break;
            case 4: //手机
                $map['t.buyer'] =  $search_key;
                break;
            default:
                break;
        }

        if($mtype == 1){
            $map['stock'] = array('gt',0);
        }
        if($mtype == -1){
            $map['stock'] = array('lt',0);
        }
        $id = I('id');
        if($id){
            $map['id'] = array('like',"%$id%");
        }
        $ctime = urldecode(I('ctime'));
        if($ctime){
            $gap = explode(' - ', $ctime);
            $this->assign('start_time',$gap[0]);
            $this->assign('end_time',$gap[1]);
            $this->assign('ctime',$gap[0].' - '.$gap[1]);
            $map['t.createTime'] = array(array('gt',$gap[0]),array('lt',$gap[1]));
        }
        $count = $model->alias("t")
            ->join("member m","m.id=t.sellerId",'left')
            ->join("member tm","tm.id=t.buyerId",'left')
            ->join("currency c","c.id=t.currency",'left')
            ->field("t.*,m.nickname sname,tm.nickname bname,c.china_name")
            ->where($map)
            ->count();
        $Page  = new Page($count,20);
        $show = $Page->show();
        $this->assign('pager',$Page);
        $this->assign('page',$show);// 赋值分页输出
        $list = M('vctransaction')
            ->alias("t")
            ->join("member m","m.id=t.sellerId",'left')
            ->join("member tm","tm.id=t.buyerId",'left')
            ->join("currency c","c.id=t.currency",'left')
            ->field("t.*,m.nickname sname,tm.nickname bname,c.china_name")
            ->where($map)
            ->order('t.id DESC')
            ->limit($Page->firstRow.','.$Page->listRows)->select();
        $this->assign('list',$list);
        return $this->fetch();
    }

    /*
    * 数字资产
    * */
    public function virtualcurrency(){

        $model = M('virtualcurrency');
        $map = array();
        $mtype = I('mtype');

        $condition = I('condition');
        $search_key = I('search_key');
        switch ($condition){
            case 1: //手机
                $map['t.account'] =  $search_key;
                break;
            case 2: // ID
                $map['t.id'] = $search_key;
                break;
            case 3: //昵称
                $map['m.nickname'] =  array('like',"%$search_key%");
                break;
            case 4: //手机
//                $map['t.buyer'] =  $search_key;
                break;
            default:
                break;
        }

        if($mtype == 1){
            $map['stock'] = array('gt',0);
        }
        if($mtype == -1){
            $map['stock'] = array('lt',0);
        }
        $id = I('id');
        if($id){
            $map['id'] = array('like',"%$id%");
        }
        $ctime = urldecode(I('ctime'));
        if($ctime){
            $gap = explode(' - ', $ctime);
            $this->assign('start_time',$gap[0]);
            $this->assign('end_time',$gap[1]);
            $this->assign('ctime',$gap[0].' - '.$gap[1]);
            $map['t.createTime'] = array(array('gt',$gap[0]),array('lt',$gap[1]));
        }
        $count = $model->alias("t")
            ->alias("t")
            ->join("member m","m.id=t.userId","left")
            ->field("t.*,m.nickname username")
            ->where($map)
            ->count();
        $Page  = new Page($count,20);
        $show = $Page->show();
        $this->assign('pager',$Page);
        $this->assign('page',$show);// 赋值分页输出
        $list = M('virtualcurrency')
            ->alias("t")
            ->alias("t")
            ->join("member m","m.id=t.userId","left")
            ->field("t.*,m.nickname username")
            ->where($map)
            ->order('t.id DESC')
            ->limit($Page->firstRow.','.$Page->listRows)->select();

        $this->assign('list',$list);
        return $this->fetch();
    }
    /*
      * 积分明细
      * */
    public function exchange(){

        $model = M('exchange');
        $map = array();
        $mtype = I('mtype');

        $condition = I('condition');
        $search_key = I('search_key');
        switch ($condition){
            case 1: //手机
                $map['m.account'] =  $search_key;
                break;
            case 2: // ID
                $map['t.id'] = $search_key;
                break;
            case 3: //昵称
                $map['m.nickname'] =  array('like',"%$search_key%");
                break;
            default:
                break;
        }

        if($mtype == 1){
            $map['stock'] = array('gt',0);
        }
        if($mtype == -1){
            $map['stock'] = array('lt',0);
        }
        $id = I('id');
        if($id){
            $map['id'] = array('like',"%$id%");
        }
        $ctime = urldecode(I('ctime'));
        if($ctime){
            $gap = explode(' - ', $ctime);
            $this->assign('start_time',$gap[0]);
            $this->assign('end_time',$gap[1]);
            $this->assign('ctime',$gap[0].' - '.$gap[1]);
            $map['create_time'] = array(array('gt',$gap[0]),array('lt',$gap[1]));
        }
        $count = $model
            ->alias("t")
            ->join("member m","m.id=t.user_id",'LEFT')
            ->field("t.*,m.nickname,m.account")
            ->where($map)
            ->count();
        $Page  = new Page($count,20);
        $show = $Page->show();
        $this->assign('pager',$Page);
        $this->assign('page',$show);// 赋值分页输出
        $list = Db::name('exchange')
            ->alias("t")
            ->join("member m","m.id=t.user_id",'LEFT')
            ->field("t.*,m.nickname,m.account")
            ->where($map)
            ->order('t.id DESC')
            ->limit($Page->firstRow.','.$Page->listRows)->select();
//        dump(Db::name('exchange')->getLastSql());
//        die;
        $this->assign('list',$list);
        return $this->fetch();
    }
    /*
    * 会员等级
    * */
    public function level(){

        //delFile(RUNTIME_PATH.'html'); // 先清除缓存, 否则不好预览


        $Ad =  Db::name('vpay_level');
        $pid = I('pid',0);
        if($pid){
            $where['pid'] = $pid;
            $this->assign('pid',I('pid'));
        }
        $keywords = I('keywords/s',false,'trim');
        if($keywords){
            $where['title'] = array('like','%'.$keywords.'%');
        }
        $count = $Ad->where($where)->count();// 查询满足要求的总记录数
        $Page = $pager = new Page($count,10);// 实例化分页类 传入总记录数和每页显示的记录数
        $res = $Ad->where($where)->order('level_id asc')->limit($Page->firstRow.','.$Page->listRows)->select();
//        dump($Ad->getLastSql());
//        die;
//        dump($where);
        $list = array();
        $show = $Page->show();// 分页显示输出
        $this->assign('list',$res);// 赋值数据集
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('pager',$pager);

        //判断API模块存在
//        if(is_dir(APP_PATH."/api")) $this->assign('is_exists_api',1);
//

        return $this->fetch();
    }
    /*
     * 添加会员
     * */
    public function add_level(){
        if(IS_POST){
            $data = $_POST;
            if($data['level_id']){
                $save_item = Db::name('vpay_level')->where(array('level_id'=>$data['level_id']))->save($data);
            }else{
                $save_item = Db::name('vpay_level')->save($data);
            }
            if($save_item){
                $this->success("添加成功！", url('Plugins.Vpay/level'));
            }else{
                $this->error("添加失败！", url('Plugins.Vpay/add_level'));
            }
        }
        $id = I('id');
        if($id){
            $data = Db::name('vpay_level')->where('level_id',$id)->find();
            $this->assign('info',$data);
        }
        return $this->fetch();
    }
    /*
     * 轮播图
     * */
    public function show(){

        //delFile(RUNTIME_PATH.'html'); // 先清除缓存, 否则不好预览


        $Ad =  Db::name('show');
        $pid = I('pid',0);
        if($pid){
            $where['pid'] = $pid;
            $this->assign('pid',I('pid'));
        }
        $keywords = I('keywords/s',false,'trim');
        if($keywords){
            $where['title'] = array('like','%'.$keywords.'%');
        }
        $count = $Ad->where($where)->count();// 查询满足要求的总记录数
        $Page = $pager = new Page($count,10);// 实例化分页类 传入总记录数和每页显示的记录数
        $res = $Ad->where($where)->order('id desc')->limit($Page->firstRow.','.$Page->listRows)->select();
//        dump($Ad->getLastSql());
//        die;
//        dump($where);
        $list = array();
        $show = $Page->show();// 分页显示输出
        $this->assign('list',$res);// 赋值数据集
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('pager',$pager);

        //判断API模块存在
//        if(is_dir(APP_PATH."/api")) $this->assign('is_exists_api',1);
//

        return $this->fetch();
    }
    /*
     * 添加轮播图
     * */
    public function ad(){
        if (IS_POST){
            $data = I('post.');
//            dump($data);die;
            if($data['act'] == 'add'){
                if (empty($data['title']) || empty($data['url'])){
                    $this->error("请填写完整信息");
                }
                $r = D('show')->add($data);
            }
            if($data['act'] == 'edit'){
                $r = D('show')->where('id', $data['id'])->save($data);
            }

            if($data['act'] == 'del'){
                $r = D('show')->where('id', $data['del_id'])->delete();
                if($r){
                    $this->ajaxReturn(['status'=>1,'msg'=>"操作成功",'url'=>U('Admin/Plugins.Vpay/show')]);
                }else{
                    $this->ajaxReturn(['status'=>-1,'msg'=>"操作失败"]);
                }
            }
            $referurl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : U('Admin/Plugins.Vpay/show');
            // 不管是添加还是修改广告 都清除一下缓存
            delFile(RUNTIME_PATH.'html'); // 先清除缓存, 否则不好预览
            \think\Cache::clear();
            if($r){
                $this->success("操作成功",U('Admin/Plugins.Vpay/show'));
            }else{
                $this->error("操作失败",$referurl);
            }
        }
        $act = I('get.act','add');
        $ad_id = I('get.id/d');

        $ad_info = array();
        if($ad_id){
            $ad_info = D('show')->where('id',$ad_id)->find();
        }
        if($act == 'add')
        $ad_info['pid'] = $this->request->param('pid');

        $this->assign('info',$ad_info);
        $this->assign('act',$act);
        return $this->fetch();
    }

    /*
     * 投诉建议
     * */
    public function complaint(){
        $model = M('complaint');
        $map = array();
        $mtype = I('mtype');
        $condition = I('condition');
        $search_key = I('search_key');

        switch ($condition){
            case 1: //手机
                $map['t.account'] = $search_key;
                break;
            case 2: // ID
                $map['t.id'] = $search_key;
                break;
            case 3: // ID
                $map['m.nickname'] = array('LIKE', '%'.$search_key.'%');
                break;
            default:
                break;
        }

        if($mtype == 1){
            $map['stock'] = array('gt',0);
        }
        if($mtype == -1){
            $map['stock'] = array('lt',0);
        }
        $id = I('id');
        if($id){
            $map['id'] = array('like',"%$id%");
        }
        $ctime = urldecode(I('ctime'));
        if($ctime){
            $gap = explode(' - ', $ctime);
            $this->assign('start_time',$gap[0]);
            $this->assign('end_time',$gap[1]);
            $this->assign('ctime',$gap[0].' - '.$gap[1]);
            $map['ctime'] = array(array('gt',strtotime($gap[0])),array('lt',strtotime($gap[1])));
        }
        $count = $model ->alias("t")
            ->join("member m","t.account=m.account",'left')
            ->field("t.*,m.nickname name")
            ->where($map)
            ->count();
//        die;
        $Page  = new Page($count,20);
        $show = $Page->show();
        $this->assign('pager',$Page);
        $this->assign('page',$show);// 赋值分页输出
        $list = M('complaint')
            ->alias("t")
            ->join("member m","t.account=m.account",'left')
            ->field("t.*,m.nickname name")
            ->where($map)
            ->order('t.id DESC')
            ->limit($Page->firstRow.','.$Page->listRows)->select();
        $this->assign('list',$list);
        return $this->fetch();
    }
    /*
     * 会员列表
     * */
    public function member(){
        $model = M('member');
        $map = array();
        $condition = I('condition');
        $search_key = I('search_key');

        switch ($condition){
            case 1: //手机
                $map['account'] = $search_key;
                break;
            case 2: // ID
                $map['id'] = $search_key;
                break;
            case 3: // ID
                $map['id'] = $search_key;
                break;
            default:
                break;
        }

        $id = I('id');
        if($id){
            $map['id'] = array('like',"%$id%");
        }
        $ctime = urldecode(I('ctime'));
        if($ctime){
            $gap = explode(' - ', $ctime);
            $this->assign('start_time',$gap[0]);
            $this->assign('end_time',$gap[1]);
            $this->assign('ctime',$gap[0].' - '.$gap[1]);
            $map['ctime'] = array(array('gt',strtotime($gap[0])),array('lt',strtotime($gap[1])));
        }
        $count = $model->where($map)->count();
        // dump($map);
        // dump($count);
        $Page  = new Page($count,20);
        $show = $Page->show();
        $this->assign('pager',$Page);
        $this->assign('page',$show);// 赋值分页输出

        $map['isDelete'] = 2;
        $list = $model
            ->alias("t")
            ->field("t.*,b.*")
            ->join("vpay_level b","t.level=b.level_id",'LEFT')
            ->where($map)
            ->order('t.id DESC')
            ->limit($Page->firstRow.','.$Page->listRows)->select();
        foreach($list as $key=>$val){
            $dh =M("balancelog")->where(array("userId"=>$val['id'],'type'=>8))->sum('num');
            $list[$key]['dh'] =abs($dh);

        }

        $this->assign('list',$list);
        return $this->fetch();
    }

    public function add_member(){
        if(IS_POST){
            $data = I('post.');
            //设置初始角色
            $data['level'] = 1;

            //昵称
            if (empty($data['nickname'])) {
                $this->error("请填写昵称！");
            } else {
                $nick_occupy = M("member")->where(array("nickname"=>$data['nickname']))->find();
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
                $account_occupy = M("member")->where(array("account"=>$data['account']))->find();
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
            if (empty($data['paypassword'])) {
                $this->error("请填写支付密码！");
            } else {
                if (strlen($data['paypassword']) != 6) {
                    $this->error("请输入6位数字支付密码！");
                }
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
            // 支付密码
            $user_data['paypassword'] = md5($data['paypassword']);
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
            $user_data['wallet'] = md5($data['account']);
            $user_data['other_balance'] = 0;

            //注册增加注册积分
            $system=tpCache("vpay_spstem");
            $user_data['integral'] = $system['regIntegral'] ? $system['regIntegral'] : 0;
//            dump($user_data);
            //创建用户表member数据
            Db::startTrans();
            $result = M("member")->add($user_data);
            if ($result) {
                //使用id，也就是uid，做邀请码
                $mem_save=M("member")->where(array("id"=>$result))->save(array("invitecode"=>$result));
                if(empty($mem_save)){
                    Db::rollback();
                    $this->error("注册失败！");
                }
                $data2['account'] = $data['account'];
                $data2['userId'] = $result;
                $data2["createTime"] = date("Y-m-d H:i:s");
                $data2["updateTime"] = date("Y-m-d H:i:s");
                $result2 = M("virtualcurrency")->add($data2);
                if ($result2) {
                    Db::commit();
                    $this->success("添加成功！", url('Plugins.Vpay/member'));
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

    public function detail(){
        $uid = input('get.id');
        $user_model = Db::name('member');
        $user = $user_model->where(['id'=>$uid])->find();

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
                $data['password'] = md5($data['password']);
            }

            if($data['paypassword'] == '' && $data['paypassword2'] == ''){
                unset($data['paypassword']);
            }else{
                $data['paypassword'] = md5($data['paypassword']);
            }
            //更新分销关系
            if($user['parentId'] != $data['parentId']){
                $result = $this->change_distribution($uid,$data['parentId']);
                if($result['status'] == 0){
                    exit($this->error($result['status']));
                }
            }
            $row = $user_model->where(['id'=>$uid])->save($data);

            if ($user['level'] != $data['level']){
                $logRes = memberLog($uid, $user['level'], $data['level'], $desc = '后台更改等级信息');
            }
            
            if($row)
                exit($this->success('修改成功'));

            if($result['status'] == 1){
                exit($this->success('修改成功'));
            }
            exit($this->error('未作内容修改或修改失败'));
        }


        //下级信息
        $user['first_lower'] = $user_model->where("parentId = {$user['id']}")->count();
        //上级信息
        $first_leader = $user_model->where(['id'=>$user['parentId']])->find();

        $levelList = Db::name('vpay_level')->select();
        $this->assign('level',$levelList);

        $this->assign('user',$user);
        $this->assign('first_leader',$first_leader);
        return $this->fetch();
    }

    public function updateBalanceIntegral(){
        if(IS_POST){
            $balance=I("balance");
            $uid = (int)I('id');
            $integral=I("integral");
            if(empty($uid)){
                exit(json_encode(['code' => -1, 'msg' => 'id不能为空']));
            }
            $info = M("member")->where(array("id"=>$uid))->find();
            if(empty($info)){
                exit(json_encode(['code' => -1, 'msg' => '用户不存在']));
            }
            if(($info['balance']+$balance)<0 || ($info['integral']+$integral)<0){
                exit(json_encode(['code' => -1, 'msg' => '用户余额或积分不足']));
            }
            Db::startTrans();
            $res=M("member")->where(array("id"=>$info['id']))->save(array("balance"=>($info['balance']+$balance),"integral"=>($info['integral']+$integral)));
            if($balance==0){
                $save_log=1;//如果只添加积分，余额变更为0则不添加log
            }else{
                $save_log = balancelog($res, $info['id'],$balance, $type=16, $before=$info['balance'], $after=$info['balance']+$balance);
            }
            if($integral==0){
                $integrallog=1;//如果只添加余额，积分变更为0则不添加log
            }else{
                $integrallog=integrallog($res, $info['id'],$integral,5,$info['integral'],($info['integral']+$integral));//转出积分log
            }
            if(empty($res) || empty($save_log) || empty($integrallog)){
                Db::rollback();
                exit(json_encode(['code' => -1, 'msg' => '更改用户余额积分错误']));
            }
            Db::commit();
            exit(json_encode(['code' => 1, 'msg' => '成功']));
        }else{
            $uid = (int)I('get.id');
            $info = M("member")->where(array("id"=>$uid))->find();
            $this->assign("data",$info);
            return $this->fetch();
        }
    }

    /**
     * 更改会员的上级   Lu
     * @param int $user_id   被改用户
     * @param int $first_leader 上级用户
     * @return array
     */
    public function change_distribution($user_id=0,$first_leader=0){

        $model = Db::name('member');
        $user = $model->where(array('id'=>$user_id))->find();
        $first_leader_info = $model->where(array('id'=>$first_leader))->find();

        if($user_id==$first_leader){
            return array('status'=>0,'msg'=>'不能把自己设为上级');
        }

        $my_distribtion = $model->whereOr(array('parentId'=>$user_id))->column('id');

        $first_leader_users = $model->where(array('id'=>$first_leader))->find();

        if($my_distribtion){
            if(in_array($first_leader,$my_distribtion)){
                return array('status'=>0,'msg'=>'不能把自己的下级设为上级');
            }
        }

        $new_leader['parentId'] = $first_leader_info['id'];
        $new_leader['parentAccount'] = $first_leader_info['account'];

        //我的一级下级
        $my_first_distribution = $model->where(array('parentId'=>$user_id))->column('id');

        //更改我的一级下级
        if($my_first_distribution){
            $data_first = array(
                $new_leader
            );
            $res_first = $model->where(array('id'=>array('in',$my_first_distribution)))->save($data_first);
        }

        $res1 = $model->where(array('id'=>$user_id))->update($new_leader);

        return array('status'=>1,'msg'=>'修改成功');
    }

    // 查找用户信息
    public function search_users()
    {
        $user_id = input('id');
        $tpl = input('tpl', 'search_users');
        $where = array();
        $model = Db::name('member');


        $user = $model->where(array('id'=>$user_id))->find();
        $my_distribtion = $model->whereOr(array('parentId'=>$user_id))->column('id');
        array_push($my_distribtion,$user['id']);

        $where['id'] = array('not in',$my_distribtion);

        $count = $model->where($where)->count();
        $Page  = new Page($count,5);
        //  搜索条件下 分页赋值
        $userList = $model->where($where)->order('id')->limit($Page->firstRow.','.$Page->listRows)->select();
        $this->assign('page', $Page);
        $this->assign('goodsList', $userList);
        return $this->fetch($tpl);
    }
    /*
     * 释放明细
     * */
    public function release(){
        $model = M('releaselog');
        $map = array();
        $mtype = I('mtype');

        $condition = I('condition');
        $search_key = I('search_key');
        switch ($condition){
            case 1: //手机
                $map['t.seller'] =  $search_key;
                break;
            case 2: // ID
                $map['t.id'] = $search_key;
                break;
            case 3: //昵称
                $map['m.nickname'] =  array('like',"%$search_key%");
                break;
            case 4: //手机
                $map['t.buyer'] =  $search_key;
                break;
            default:
                break;
        }

        if($mtype == 1){
            $map['stock'] = array('gt',0);
        }
        if($mtype == -1){
            $map['stock'] = array('lt',0);
        }
        $id = I('id');
        if($id){
            $map['id'] = array('like',"%$id%");
        }
        $ctime = urldecode(I('ctime'));
        if($ctime){
            $gap = explode(' - ', $ctime);
            $this->assign('start_time',$gap[0]);
            $this->assign('end_time',$gap[1]);
            $this->assign('ctime',$gap[0].' - '.$gap[1]);
            $map['t.createTime'] = array(array('gt',$gap[0]),array('lt',$gap[1]));
        }
        $count = $model->alias("t")
            ->join("member m","m.id=t.userId","LEFT")
            ->field("t.*,m.nickname sname")
            ->where($map)
            ->count();
        $Page  = new Page($count,20);
        $show = $Page->show();
        $this->assign('pager',$Page);
        $this->assign('page',$show);// 赋值分页输出
        $list = M('releaselog')
            ->alias("t")
            ->join("member m","m.id=t.userId","LEFT")
            ->field("t.*,m.nickname sname,m.account saccount")
            ->where($map)
            ->order('t.id DESC')
            ->limit($Page->firstRow.','.$Page->listRows)->select();
        if (!empty($list)) {
            foreach ($list as $k=>$v) {
                switch ($v['type']) {
                    case 1 :
                        $list[$k]['detail'] =  Db::name('transfer')
                                                ->alias('t')
                                                ->field('t.*,b.type as btype')
                                                ->join('__BALANCELOG__ b', 't.id = b.reflectId')
                                                ->where(['t.id' => $v['reflectId']])
                                                ->find();
                        if ($list[$k]['detail']['btype'] == 1) {
                            $list[$k]['type_str'] = "转入";
                        } elseif($list[$k]['detail']['btype'] == 2){
                            $list[$k]['type_str'] = "转出";
                        }
//                        dump($list);
//                        die;
                        break;
                    case 2 :
                        $list[$k]['type_str'] = "兑换";
                        $list[$k]['detail'] =   Db::name('exchange')
                            ->alias('t')
                            ->field('t.*,m.account as m_account')
                            ->join('__MEMBER__ m', 'm.id = t.user_id', 'LEFT')
                            ->where(['t.id' => $v['reflectId']])
                            ->find();
                        $list[$k]['type_str'] = "转出余额获得";
                        break;
                    case 3 :
                        $list[$k]['type_str'] = "买入";
                        $list[$k]['detail'] =  Db::name('transaction')->where(['id' => $v['reflectId']])->find();
                        //$list[$k]['type_str'] = "转入余额获得";
                        break;
                    case 4 :
                        $list[$k]['type_str'] = "卖出";
                        $list[$k]['detail'] =  Db::name('transaction')->where(['id' => $v['reflectId']])->find();
                        break;
                    default:
                        $list[$k]['type_str'] = "未知";
                        break;
                }
            }
        }
        $this->assign('list',$list);
        return $this->fetch();
    }
    /*
     * 余额明细
     * */
    public function balance()    {

        //判断分页时搜索条件

        $condition = I('condition');
        $search_key = I('search_key');

        switch ($condition){
            case 1: //手机
                $where['m.account'] = $search_key;
                break;
            case 2: // ID
                $where['m.id'] = $search_key;
                break;
            case 3: // ID
                $where['m.id'] = $search_key;
                break;
            default:
                break;
        }

        $ctime = urldecode(I('ctime'));
        if($ctime){
            $gap = explode(' - ', $ctime);
            $this->assign('start_time',$gap[0]);
            $this->assign('end_time',$gap[1]);
            $this->assign('ctime',$gap[0].' - '.$gap[1]);
            $where['t.createTime'] = array(array('gt',$gap[0]),array('lt',$gap[1]));
        }

        // 分页输入
        if(empty($pageSize)){
            $pageSize = 10;
        }

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
            ->join("__MEMBER__ m ","m.id=t.userId", 'LEFT')
            ->field("t.*,m.nickname,m.account")
            ->where($where)
            ->limit($page->firstRow . ',' . $page->listRows)
            ->order('t.id DESC')
            ->select();
        if (!empty($list)) {
            foreach ($list as $k=>$v) {
                //转入转出人
                $list[$k]['operator'] = "";
                if($v['type']==1){
                    $operator = M('transfer')
                        ->alias("t")
                        ->join("__MEMBER__ m "," m.id=t.userId", 'LEFT')
                        ->where("t.id=".$v['reflectId'])
                        ->field("m.nickname,m.id")->find();
                    $list[$k]['operator'] = $operator['nickname'].'|'.$operator['id'];
                }
                if($v['type']==2){
                    $operator = M('transfer')
                        ->alias("t")
                        ->join("__MEMBER__ m", "m.id = t.toUserId", 'LEFT')
                        ->where("t.id=".$v['reflectId'])
                        ->field("m.nickname,m.id")
                        ->find();
                    $list[$k]['operator'] = $operator['nickname'].'|'.$operator['id'];
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
                        $list[$k]['type_str']="保证金";
                        //$logs[$k]['type_str'] = "回加保证金（数字资产）";
                        break;
                    case 11 :
                        $list[$k]['type_str']="交易支出";
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

//        echo "<pre>";
//        print_r($list);exit;
        // 统计
        $sum = M('balancelog')
            ->alias("t")->join("__MEMBER__ m", " m.id=t.userId", 'LEFT')
            ->field("count(1) as countNum")
            ->where($where)
            ->order('t.id DESC')
            ->find();

        // 输出数据
        $this->assign('list', $list);
        $this->assign('sum', $sum);

        $this->assign('page', $show);
        return $this->fetch();
    }

    // 加载初始化内容页,并检索
    public function integral()
    {

        //判断分页时搜索条件
        $condition = I('condition');
        $search_key = I('search_key');

        switch ($condition){
            case 1: //手机
                $where['m.account'] = $search_key;
                break;
            case 2: // ID
                $where['m.nickname'] = array('LIKE', '%'.$search_key.'%');
                break;
            case 3: // ID
                $where['m.id'] = $search_key;
                break;
            default:
                break;
        }

        $ctime = urldecode(I('ctime'));
        if($ctime){
            $gap = explode(' - ', $ctime);
            $this->assign('start_time',$gap[0]);
            $this->assign('end_time',$gap[1]);
            $this->assign('ctime',$gap[0].' - '.$gap[1]);
            $where['t.createTime'] = array(array('gt',$gap[0]),array('lt',$gap[1]));
        }

        // 分页输入
        if(empty($pageSize)){
            $pageSize = 10;
        }
        // 总条数
        $count = M('integrallog')
            ->alias("t")
            ->join("__MEMBER__ m"," m.id=t.userId", 'LEFT')
            ->field("t.*,m.nickname,m.account")
            ->where($where)
            ->count();
        $page = new Page($count, $pageSize);
        $show = $page->show();

        // 进行分页数据查询
        $list = M('integrallog')
            ->alias("t")
            ->join("__MEMBER__ m"," m.id=t.userId", 'LEFT')
            ->field("t.*,m.nickname,m.account")
            ->where($where)
            ->limit($page->firstRow . ',' . $page->listRows)
            ->order('t.id DESC')
            ->select();
        if (!empty($list)) {
            foreach ($list as $k=>$v) {
                //转入转出人
                $list[$k]['operator'] = "";
                if($v['type']==3){
                    $operator = M('transfer')
                        ->alias("t")
                        ->join("__MEMBER__ m", "m.id=t.userId", "LEFT")
                        ->where("t.id=".$v['reflectId'])
                        ->field("m.nickname,m.id")->find();
                    if(!empty($operator )){
                        $list[$k]['operator'] = $operator['nickname'].'|'.$operator['id'];
                    }
                }
                if($v['type']==2){
                    $operator = M('transfer')
                        ->alias("t")
                        ->join("__MEMBER__ m", " m.id=t.toUserId", "LEFT")
                        ->where("t.id=".$v['reflectId'])
                        ->field("m.nickname,m.id")->find();
                    if(!empty($operator )){
                        $list[$k]['operator'] = $operator['nickname'].'|'.$operator['id'];
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

        // 输出数据
        $this->assign('list', $list);
        $this->assign('sum', $sum);
        $this->assign('page', $show);
        return $this->fetch();
    }
    // 会员明细
    public function user_details(){
        $where = [];
        if(isset($_GET['condition']) && isset($_GET['ctime']) && isset($_GET['search_key'])){
            $where['change_time'] = array('gt',strtotime($_GET['ctime']));
            $this->assign('start_time',$_GET['ctime']);
            if($_GET['condition'] == '' ){
                unset($_GET['condition']);
            }
            switch($_GET['condition']){
                // 账号
                case '1' :
                    $where['account'] =  $_GET['search_key'];
                break;
                // ID
                case '2' :
                    $where['id'] =  $_GET['search_key'];
                break;

            }
        }
            $data = Db::name('vpay_level_log')->alias('g')
            ->join('vpay_level l','l.level_id = g.before_level','LEFT')
            ->field('g.*,l.level_name')
            ->where($where)
            ->select();
        $this->assign('data', $data);
        return $this->fetch();
    }
    /*
     * 团队关系
     * */
    public function group(){
        $where = 'isDisable = 2';
        if ($this->request->param('id')) $where = "id = '{$this->request->param('id')}'";
        $list = M('member')->where($where)->select();
        $this->assign('list', $list);
        return $this->fetch();
    }
    /**
     * 获取某个人下级元素
     */
    public function ajax_lower()
    {
        $id = $this->request->param('id');
        $userlevel = $this->request->param('userlevel');
        $userlevel_field = 'parentId';
        $where = '';
        if ($userlevel == 'parentId') $where .= "parentId =" . $id;
        $list = M('member')->where($where)->select();
        $_list = array();
        foreach ($list as $key => $val) {
            $_t = $val;
            $_t['user_level'] = $userlevel_field;
            $_list[] = $_t;
        }
        $this->assign('list', $_list);
        return $this->fetch();
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
        if(empty($pageSize)){
            $pageSize = 10;
        }
        if($type==2){
            $where['m.id'] = $id;
            // 总条数
            $count = M('integrallog')
                ->alias("t")
                ->join("__MEMBER__ m"," m.id=t.userId", 'LEFT')
                ->field("t.*,m.nickname,m.account")
                ->where($where)
                ->count();
            $page = new Page($count, $pageSize);
            $show = $page->show();

            // 进行分页数据查询
            $list = M('integrallog')
                ->alias("t")
                ->join("__MEMBER__ m"," m.id=t.userId", 'LEFT')
                ->field("t.*,m.nickname,m.account")
                ->where($where)
                ->limit($page->firstRow . ',' . $page->listRows)
                ->order('t.id DESC')
                ->select();
            if (!empty($list)) {
                foreach ($list as $k=>$v) {
                    //转入转出人
                    $list[$k]['operator'] = "";
                    if($v['type']==3){
                        $operator = M('transfer')
                            ->alias("t")
                            ->join("__MEMBER__ m", "m.id=t.userId", "LEFT")
                            ->where("t.id=".$v['reflectId'])
                            ->field("m.nickname,m.id")->find();
                        if(!empty($operator )){
                            $list[$k]['operator'] = $operator['nickname'].'|'.$operator['id'];
                        }
                    }
                    if($v['type']==2){
                        $operator = M('transfer')
                            ->alias("t")
                            ->join("__MEMBER__ m", " m.id=t.toUserId", "LEFT")
                            ->where("t.id=".$v['reflectId'])
                            ->field("m.nickname,m.id")->find();
                        if(!empty($operator )){
                            $list[$k]['operator'] = $operator['nickname'].'|'.$operator['id'];
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
        }elseif($type==3){
            $where['m.id'] = $id;
            $model = M('releaselog');
            $count = $model->alias("t")
                ->join("member m","m.id=t.userId","LEFT")
                ->field("t.*,m.nickname sname")
                ->where($where)
                ->count();
            $Page  = new Page($count,20);
            $show = $Page->show();
            $list = M('releaselog')
                ->alias("t")
                ->join("member m","m.id=t.userId","LEFT")
                ->field("t.*,m.nickname sname,m.account saccount")
                ->where($where)
                ->order('t.id DESC')
                ->limit($Page->firstRow.','.$Page->listRows)->select();
            if (!empty($list)) {
                foreach ($list as $k=>$v) {
                    switch ($v['type']) {
                        case 1 :
                            $list[$k]['detail'] =  Db::name('transfer')
                                                    ->alias('t')
                                                    ->field('t.*,b.type as btype')
                                                    ->join('__BALANCELOG__ b', 't.id = b.reflectId')
                                                    ->where(['t.id' => $v['reflectId']])
                                                    ->find();
                            if ($list[$k]['detail']['btype'] == 1) {
                                $list[$k]['type_str'] = "转入";
                            } elseif($list[$k]['detail']['btype'] == 2){
                                $list[$k]['type_str'] = "转出";
                            }

                            break;
                        case 2 :
                            $list[$k]['type_str'] = "兑换";
                            $list[$k]['detail'] =   Db::name('exchange')
                                ->alias('t')
                                ->field('t.*,m.account as m_account')
                                ->join('__MEMBER__ m', 'm.id = t.user_id', 'LEFT')
                                ->where(['t.id' => $v['reflectId']])
                                ->find();
                            $list[$k]['type_str'] = "转出余额获得";
                            break;
                        case 3 :
                            $list[$k]['type_str'] = "买入";
                            $list[$k]['detail'] =  Db::name('transaction')->where(['id' => $v['reflectId']])->find();
                            //$list[$k]['type_str'] = "转入余额获得";
                            break;
                        case 4 :
                            $list[$k]['type_str'] = "卖出";
                            $list[$k]['detail'] =  Db::name('transaction')->where(['id' => $v['reflectId']])->find();
                            break;
                        default:
                            $list[$k]['type_str'] = "未知";
                            break;
                    }
                }
            }
        }else{
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
                ->join("__MEMBER__ m ","m.id=t.userId", 'LEFT')
                ->field("t.*,m.nickname,m.account")
                ->where($where)
                ->limit($page->firstRow . ',' . $page->listRows)
                ->order('t.id DESC')
                ->select();
            if (!empty($list)) {
                foreach ($list as $k=>$v) {
                    //转入转出人
                    $list[$k]['operator'] = "";
                    if($v['type']==1){
                        $operator = M('transfer')
                            ->alias("t")
                            ->join("__MEMBER__ m "," m.id=t.userId", 'LEFT')
                            ->where("t.id=".$v['reflectId'])
                            ->field("m.nickname,m.id")->find();
                        $list[$k]['operator'] = $operator['nickname'].'|'.$operator['id'];
                    }
                    if($v['type']==2){
                        $operator = M('transfer')
                            ->alias("t")
                            ->join("__MEMBER__ m", "m.id = t.toUserId", 'LEFT')
                            ->where("t.id=".$v['reflectId'])
                            ->field("m.nickname,m.id")
                            ->find();
                        $list[$k]['operator'] = $operator['nickname'].'|'.$operator['id'];
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
                            $list[$k]['type_str']="保证金";
                            //$logs[$k]['type_str'] = "回加保证金（数字资产）";
                            break;
                        case 11 :
                            $list[$k]['type_str']="交易支出";
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
}
