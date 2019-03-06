<?php

namespace app\dcworld\controller;

use think\Db;
use think\Request;
use think\AjaxPage;
use app\common\logic\UsersLogic;

class Member extends Sign
{
    public $user = array();
    public $user_id = 0;

    public function _initialize()
    {
        parent::_initialize();
        $user = $this->getAccount();

        $user_info = M('member')->where(array('id' => $user['id'], 'isDelete' => 2))->find();
        if (empty($user_info)) {
            $this->redirect(U('Login/exitLogin'));
        }

        $level = db('world_level')->where(array('level_id' => $user['level']))->find();
        $user['level_name'] = $level['level_name'];
        $this->user = M('member')->where(array('id' => $user['id']))->find();
        $this->user_id = $user['id'];
    }

    /**
     * 个人中心 index
     */
    public function index()
    {

        $uid = $this->getAccountId();
        $uinfo = M('member')
            ->alias("t")
            ->field("t.*,b.*")
            ->join("world_level b", "t.level=b.level_id", 'LEFT')
            ->where(array("id" => $uid))
            ->find();

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
            M('member')->where(array('id' => $uinfo['id']))->update($post);

            //修改头像
            $uinfo['profilePhoto'] = '/' . $dir . $parentDir . '/' . $info->getFilename();

        }
        $this->assign('uinfo', $uinfo);
        $system = tpCache("vpay_spstem");
        $this->assign('appVersion', $system['appVersion']);
        // dump($uinfo);die;
        return $this->fetch();
    }

    /**
     * 钱包
     */
    public function my_wallet()
    {
        $user = $this->user;
        $user = Db::name('member')->where(array('id' => $user['id']))->find();
        if ($user['wallet_status'] == 0) {
            $this->redirect(U('add_wallet'));
        }
        $dc_wallet = Db::name('world_wallet')->where(array('id' => 1))->find();
        $dc_wallet['dc_coin'] = $user['dc_coin'];
        $wallet_list = Db::name('world_wallet')->where(array('status' => 1, 'id' => array('neq', 1)))->order('sort asc')->select();

        $this->assign('dc_wallet', $dc_wallet);
        $this->assign('wallet_list', $wallet_list);

        $dc_cny_rate = worldCa('basic.dc_cny_rate');

        $cny_all = ($user['dc_coin'] + $user['frozen_dc']) * $dc_cny_rate;
        $cny_all = floor($cny_all * 1000) / 1000;
        $user['cny_all'] = sprintf("%.3f", $cny_all);


        $this->assign('user', $user);
        return $this->fetch();
    }

    /**
     * 新增钱包
     */
    public function add_wallet()
    {
        $user_id = $this->user_id;

        $user = Db::name('member')->where(array('id' => $user_id))->find();
        if ($user['wallet_status'] != 0) {
            $this->redirect(U('my_wallet'));
        }

        if (IS_POST) {
            $pdata = input('post.');
            $paypassword = trim($pdata['paypassword']);
            $repaypassword = $pdata['repaypassword'];

            if ($paypassword != $repaypassword) {
                $this->ajaxError('！两次密码输入不一样，请重新输入');
            }

            $data['wallet_name'] = $pdata['wallet_name'];
            $data['paypassword'] = md5($pdata['paypassword']);
            $data['wallet_status'] = 1;

            $wallet_ids = Db::name('world_wallet')->where(array('status' => 1))->order('sort asc')->column('id');
            $data['wallet_ids'] = implode(",", $wallet_ids);

            $res = M('member')->where(array('id' => $user_id))->update($data);

            if (!$res) {
                $this->ajaxError('添加钱包失败，请稍后重试');
            }
            $this->ajaxSuccess();
        }
        $article = Db::name('article')->where("article_id", 42)->find();
        $add_wallet_tips = worldCa("tips.add_wallet_tips");

        $this->assign('article', $article);
        $this->assign('add_wallet_tips', $add_wallet_tips);
        return $this->fetch();
    }

    /**
     * 新增钱包
     */
    public function memorizing_word()
    {
        $user_id = $this->user_id;
        $user = Db::name('member')->where(array('id' => $user_id))->find();

        if (!$user['memorizing_word']) {
            $words = create_memorizing_word(12);
            $data['memorizing_word'] = $words;
            $res = M('member')->where(array('id' => $user_id))->update($data);
            if ($res) {
                $user['memorizing_word'] = $words;
            }
        }

        $memorizing_word_tips = worldCa("tips.memorizing_word_tips");

        $user['memorizing_word'] = mb_str_split($user['memorizing_word']);
        $this->assign('user', $user);
        $this->assign('memorizing_word_tips', $memorizing_word_tips);
        return $this->fetch();
    }


    /**
     * 分享好友
     */
    public function share()
    {
//        $code = randomkeys(6);
//        dump($code);die;
        $user = $this->user;
        if (IS_POST) {

            die;
        }
        //加载第三方类库
        vendor('phpqrcode.phpqrcode');

        //获取个人
        // $url = request()->domain().U('reg',['id'=>$this->user_id, 'act' => 1]);
        $url = request()->domain() . U('Dcworld/Login/register', ['id' => $this->user_id, 'act' => 1]);


        $after_path = 'public/qrcode/' . md5($url) . '.png';

        //保存路径
        $path = ROOT_PATH . $after_path;

        //判断是该文件是否存在
        if (!is_file($path)) {
            //实例化
            $qr = new \QRcode();
            //1:url,3: 容错级别：L、M、Q、H,4:点的大小：1到10
            $qr::png($url, './' . $after_path, "M", 6, TRUE);

        }

        $poster_bg = worldCa('qr_poster.poster_bg');
        $this->assign('qrcodeImg', request()->domain() . '/' . $after_path);
        $this->assign('user', $user);
        $this->assign('poster_bg', $poster_bg);
        return $this->fetch();

    }

    /**
     * 收款码
     */
    public function receivables_code()
    {
        $user = $this->user;
        if (IS_POST) {

        } else {
            //加载第三方类库
            vendor('phpqrcode.phpqrcode');

            //获取个人
            // $url = request()->domain().U('reg',['id'=>$this->user_id, 'act' => 1]);
            $url = request()->domain() . U('Dcworld/Dcworld/transfer_accounts_dc', ['wallet' => $user['wallet'], 'act' => 1]);

            $after_path = 'public/qrcode/' . md5($url) . '.png';

            //保存路径
            $path = ROOT_PATH . $after_path;

            //判断是该文件是否存在
            if (!is_file($path)) {
                //实例化
                $qr = new \QRcode();
                //1:url,3: 容错级别：L、M、Q、H,4:点的大小：1到10
                $qr::png($url, './' . $after_path, "M", 6, TRUE);

            }
            $this->assign('wallet_path', request()->domain() . '/' . $after_path);
            $this->assign('user', $user);
            return $this->fetch();
        }
    }

    /**
     * 我的资产
     */
    public function user_assets()
    {
        $user = session('account');
        $user_info = M('member')->where(array('id' => $user['id']))->find();
        $general_income = M('world_dc_log')->where(array('user_id' => $user_info['id'], 'number' => array('gt', 0)))->sum('number');
        $total_expenditure = M('world_dc_log')->where(array('user_id' => $user_info['id'], 'number' => array('lt', 0)))->sum('number');
        //支出
        $type_expend_list = array(
//            '1' => '节点调配',
            '2' => '激活矿机',
            '4' => '卖出DC',
            '5' => '转出矿机',
            '12' => '转给他人',
        );
        //收入
        $type_income_list = array(
//            '1' => '节点调配',
            '3' => '激励产出',
            '7' => '买入DC',
//            '8' => 'DC交易取消',
            '9' => '矿机产出',
            '10' => '贝壳兑换DC',
            '11' => '他人转入',
        );

        $notice_id = I('notice_id');
        if(isset($notice_id)&&$notice_id>0){
            sign_read($notice_id,$user['id']);
        }


        $this->assign('type_expend_list', $type_expend_list);
        $this->assign('type_income_list', $type_income_list);
        $this->assign('cny_proportion', worldCa('basic.dc_cny_rate'));
        $this->assign('user_info', $user_info);
        $this->assign('general_income', $general_income);
        $this->assign('total_expenditure', $total_expenditure);
        return $this->fetch();
    }

    public function ajax_user_assets()
    {
        //数据
        $user_info = session('account');
        $state = I('state');
        $type = I('type');
        //查询条件
        $where = array();
        $where['user_id'] = $user_info['id'];
        $where['type'] = ['neq', 8];
        if ($state == 'income' || empty($state)) {
            $where['number'] = array('gt', 0);
        } elseif ($state == 'expend') {
            $where['number'] = array('lt', 0);
        }

        if ($type) {
            $where['type'] = $type;
        }

        //分页数据
        $count = db('world_dc_log')->where($where)->count();
        $Page = new AjaxPage($count, 5);
        $date = db('world_dc_log')->where($where)->limit($Page->firstRow, $Page->listRows)->order('log_id desc')->select();
        //$show = $Page->show(); //暂时无用

        $this->assign('date', $date);
        return $this->fetch();
    }

    /**
     * 添加资产
     */
    public function add_assets()
    {
        $user = $this->user;
        $user = Db::name('member')->where(array('id' => $user['id']))->find();
        $user['wallet_ids'] = explode(',', $user['wallet_ids']);

        if (IS_POST) {
            $pdata = input('post.');
            $id = $pdata['id'];
            $has = $pdata['has'];
            $wallet_ids = $user['wallet_ids'];
            if ($has == 'true') {
                array_push($wallet_ids, $id);
            } else {
                foreach ($wallet_ids as $key => $val) {
                    if ($id == $val) {
                        unset($wallet_ids[$key]);
                    }
                }
            }
            $data['wallet_ids'] = implode(",", $wallet_ids);

            $res = M('member')->where(array('id' => $user['id']))->update($data);

            if (!$res) {
                $this->ajaxError('更改失败，请稍后重试');
            }
            $this->ajaxSuccess();
        }

        if ($user['wallet_status'] == 0) {
            $this->redirect(U('add_wallet'));
        }
        $wallet_list = Db::name('world_wallet')->where(array('status' => 1, 'type' => array('neq', 'dc')))->order('sort asc')->select();

        $this->assign('wallet_list', $wallet_list);
        $this->assign('user', $user);
        return $this->fetch();
    }


    /**
     * 安全设置
     */
    public function security_settings()
    {
        return $this->fetch();
    }

    /**
     * 支付管理 账户列表
     */
    public function account_list()
    {
        $uid = $this->getAccountId();

        $alipay = M('world_user_account')->where(['user_id' => $uid, 'type' => 1])->order('id desc')->find();
        $wechat = M('world_user_account')->where(['user_id' => $uid, 'type' => 2])->order('id desc')->find();
        $bank_card = M('world_user_account')->where(['user_id' => $uid, 'type' => 3])->order('id desc')->find();

        // 账号中间四位隐藏
        $alipay['account'] = $alipay['account'] ? substr_replace($alipay['account'], '****', 3, 4) : '';
        $wechat['account'] = $wechat['account'] ? substr_replace($wechat['account'], '****', 3, 4) : '';
        $bank_card['account'] = $bank_card['account'] ? substr_replace($bank_card['account'], '******', 3, 6) : '';

        $this->assign('alipay', $alipay);
        $this->assign('wechat', $wechat);
        $this->assign('bank_card', $bank_card);

        return $this->fetch();
    }

    /**
     * 支付宝账户管理
     */
    public function alipay_edit()
    {

        $uid = $this->getAccountId();
        $alipay = M('world_user_account')->where(['user_id' => $uid, 'type' => 1])->order('id desc')->find();

        if (IS_POST) {
            $post = I('post.');
            $result = account_edit($post['id'], $uid, 1, $post['account'], $post['account_name'], $post['account_code']);

            if ($result) {
                $this->ajaxSuccess(array("status" => 1, "msg" => '操作成功!'));
            } else {
                $this->ajaxSuccess(array("status" => 0, "msg" => '操作失败!'));
            }
        } else {
            $this->assign('alipay', $alipay);
            return $this->fetch();
        }
    }

    /**
     * 微信账户管理
     */
    public function wechat_edit()
    {
        $uid = $this->getAccountId();
        $wechat = M('world_user_account')->where(['user_id' => $uid, 'type' => 2])->order('id desc')->find();
        if (IS_POST) {
            $post = I('post.');
            $result = account_edit($post['id'], $uid, 2, $post['account'], $post['account_name'], $post['account_code']);

            if ($result) {
                $this->ajaxSuccess(array("status" => 1, "msg" => '操作成功!'));
            } else {
                $this->ajaxSuccess(array("status" => 0, "msg" => '操作失败!'));
            }
        } else {
            $this->assign('wechat', $wechat);
            return $this->fetch();
        }
        return $this->fetch();
    }

    /**
     * 银行卡账户管理
     */
    public function bank_card_edit()
    {
        $uid = $this->getAccountId();
        $bank_card = M('world_user_account')->where(['user_id' => $uid, 'type' => 3])->order('id desc')->find();
        $bank_info = bank_information();//获取银行信息
        $bank_info = array_column($bank_info, 'name');
        $bank_info = json_encode($bank_info);

        if (IS_POST) {
            $post = I('post.');
            $result = account_edit($post['id'], $uid, 3, $post['account'], $post['account_name'], $post['account_code'], $post['bank_name'], $post['bank_branch']);

            if ($result) {
                $this->ajaxSuccess(array("status" => 1, "msg" => '操作成功!'));
            } else {
                $this->ajaxSuccess(array("status" => 0, "msg" => '操作失败!'));
            }
        } else {
            $this->assign('bank_card', $bank_card);
            $this->assign('bank_info', $bank_info);

            return $this->fetch();
        }
        return $this->fetch();
    }

    /**
     * 修改支付密码
     * @return mixed
     */
    public function edit_payword()
    {
        //检查是否第三方登录用户
        $user = M('member')->where('id', $this->user_id)->find();
        if ($user['account'] == '') $this->error('没有绑定手机号');

        if (IS_POST) {
            //验证手机验证码
            $account = $user['account'];
            $code = I('mobile_code');
            if (empty(parent::get("REG_MOBILE"))) $this->ajaxError("请发送手机验证码！");
            if ($account != parent::get("REG_MOBILE")) $this->ajaxError("手机号与接收验证码手机号不一致！");
            if (empty($code)) {
                $this->ajaxError("请填写手机验证码！");
            } else {
                if ($code != parent::get("REG_CODE")) {
                    $this->ajaxError("验证码错误！");
                }
            }
            //通过，修改支付密码
            $new_password = trim(I('new_password'));
            $confirm_password = trim(I('confirm_password'));

            if (strlen($new_password) < 6 || strlen($new_password) > 16) $this->ajaxError('密码长度不符');

            if ($new_password != $confirm_password) $this->ajaxError('两次密码输入不一致');

            $row = M('member')->where("id", $this->user_id)->update(array('paypassword' => md5($new_password)));

            if (!$row) {
                $this->ajaxError('修改失败');
            } else {
                $this->ajaxSuccess('修改成功');
            }
        }
        $this->assign('user', $user);
        return $this->fetch();
    }

    /**
     * 修改登录密码
     */
    public function edit_password()
    {
        $user = M('member')->where('id', $this->user_id)->find();
        if (IS_POST) {
            $old_password = I('post.old_password');
            $new_password = I('post.new_password');
            $old_password_md = md5($old_password);

            if ($user['password'] !== $old_password_md) {
                $this->ajaxError('原密码错误');
            }
            if (strlen($new_password) < 6 || strlen($new_password) > 16)
                $this->ajaxError('密码长度不符');
            if (!isNumAndLetter($new_password))
                $this->ajaxError("登录密码必须同时包含数字和英文！");

            $row = M('member')->where("id", $this->user_id)->update(array('password' => md5($new_password)));
            if (!$row) {
                $this->ajaxError('修改失败');

            } else {
                $this->ajaxSuccess('修改成功');
            }
        }
        return $this->fetch();
    }

    /**
     * 帮助中心
     */
    public function help_center()
    {
        $articlecat = db('article_cat')->where(array('cat_id' => 12))->find();
        $articleList = db('article')->where(array('cat_id' => 12))->order('article_id')->select();
        $this->assign('articlecat', $articlecat);
        $this->assign('articleList', $articleList);
        return $this->fetch();
    }

    /**
     * 帮助中心详情
     */
    public function article_detail()
    {
        $article_id = I('article_id');
        $news = db('article')->where(array('article_id' => $article_id))->find();
        $news['content'] = htmlspecialchars_decode($news['content']);
        $this->assign('news', $news);
        return $this->fetch();
    }

    public function baseUploadImg()
    {
        $user_id = $this->user_id;
        $data = I("post.");
        //头像更改
        $base64 = $data['file_img'];
        $dir = 'public/upload/' . $data['file_name'] . '/';

        apilog($data, $user_id, '上传头像');
        $result = base64_image_content($base64, $dir);

        $post['profilePhoto'] = $result;
        $result = ['status' => 1, 'data' => $result];
        $this->ajaxSuccess($result);

    }

    //上传图片
    public function uploadImg()
    {
        $file_name = input('file_name');
        $uploadRoot = 'public/upload/' . $file_name . '/';

        // 没有则新增文件夹
        if (!($_exists = file_exists($uploadRoot))) {
            $isMk = mkdir($uploadRoot, 0777, true);
        }
        $image_upload_limit_size = config('image_upload_limit_size');
        $validate = ['size' => $image_upload_limit_size, 'ext' => 'jpg,png,gif,jpeg'];

        //判断是否对上传目录拥有写权限
        if (!is_writable($uploadRoot)) {
            $result = ['status' => -1, 'date' => '没有权限']; // 上传错误提示错误信息
            $this->ajaxError($result);
        }
        $file = $this->request->file($file_name);
        $parentDir = date('Ymd');

        $info = $file->validate($validate)->move($uploadRoot, true);

        if ($info) {
            $account_code = '/' . $uploadRoot . $parentDir . '/' . $info->getFilename();
            $result = ['status' => 1, 'data' => $account_code];
            $this->ajaxSuccess($result);
        } else {
            $result = ['status' => -1, 'data' => $file->getError()];
            $this->ajaxError($result);
        }
    }

    /**
     * 发送短信验证码 用于设置修改支付密码
     */
    public function sendCode()
    {
        $account = trim(I('post.account'));
        $type = (int)I('post.type'); // 类型 1：注册 2：找回密码 3：找回支付密码
        if (empty($account)) $this->ajaxError("请输入手机号！");
        // 账号错误
        if (!isPhone($account)) return $this->ajaxError("手机号格式不正确！");
        // 如果已经
        if (!empty(parent::get("REG_CODE_TIME")) && time() - parent::get("REG_CODE_TIME") < 60) return $this->ajaxError("请求发送短信间隔太短！");
        if (!empty(parent::get("FORGET_CODE_TIME")) && time() - parent::get("FORGET_CODE_TIME") < 60) return $this->ajaxError("请求发送短信间隔太短！");
        if (!empty(parent::get("FORGET_PAYCODE_TIME")) && time() - parent::get("FORGET_PAYCODE_TIME") < 60) return $this->ajaxError("请求发送短信间隔太短！");
        // 获取配置
        $sms = worldCa('spstem_sms');
        if (!$sms) return $this->ajaxError("没有配置短信");

        //24小时内允许发三条
        $where = array(
            'mobile'=>$account,
            'add_time'=>array('gt',time()-86400),
        );
        $send_num =  M('sms_log')->where($where)->count();
        if($send_num>=3){
            return $this->ajaxError("发送失败，24小时内允许发送3条信息！");
        }

        $url = $sms['url'];
        $key = $sms['key'];
        $tplId = $sms['tplId'];

        $code = rand(100000, 999999);

        parent::set("REG_CODE", $code);
        parent::set("REG_MOBILE", $account);
        parent::set("REG_CODE_TIME", time());

        $smsCfg = array(
            'key' => $key,
            'mobile' => $account,
            'tpl_id' => $tplId,
            'tpl_value' => '#code#=' . $code
        );

        //请求发送短信
        $content = juhecurl($url, $smsCfg, 1);
        if ($content) {
            $result = json_decode($content, true);
            $error_code = $result['error_code'];
            if ($error_code == 0) {
                $sms_data =array(
                    'mobile'=>$account,
                    'add_time'=>time(),
                    'code'=>$code,
                    'status'=>1,
                    'scene'=>$type,
                );
                $res = M('sms_log')->add($sms_data);
                return $this->ajaxSuccess("发送成功！");
            } else {
                //状态非0，说明失败
                return $this->ajaxError("请求发送短信失败:" . $error_code);
            }
        } else {
            //返回内容异常，以下可根据业务逻辑自行修改
            return $this->ajaxError("请求发送短信失败！");
        }
    }

    /**
     * 修改用户昵称
     */
    public function edit_nickname()
    {

        if (IS_POST) {
            $nickname = input('post.nickname');
            if ($nickname == $this->user['nickname']) {
                $this->ajaxError('修改的昵称与原昵称一致');
            }
            $res = M('member')->where("id", $this->user_id)->update(array('nickname' => trim($nickname)));
            if ($res) {
                $this->ajaxSuccess('修改成功!');
            } else {
                $this->ajaxError('修改失败!');
            }
        }
        $this->assign('user', $this->user);
        return $this->fetch();
    }

    /**
     * 修改钱包名称
     */
    public function edit_walletname()
    {

        if (IS_POST) {
            $wallet_name = input('post.wallet_name');
            if ($wallet_name == $this->user['wallet_name']) {
                $this->ajaxError('修改的名称与原名称一致');
            }
            $res = M('member')->where("id", $this->user_id)->update(array('wallet_name' => trim($wallet_name)));
            if ($res) {
                $this->ajaxSuccess('修改成功!');
            } else {
                $this->ajaxError('修改失败!');
            }
        }
        $this->assign('user', $this->user);
        return $this->fetch();
    }

    /**
     * 消息通知
     */
    public function message_notification()
    {
        $user_id = $this->user_id;
        $data = I('get.');

        if ($data['is_ajax'] == 1) {
            //昨日时间区间
            $where['user_id'] = $user_id;
            $where['is_read'] = $data['is_read'];


            $count = Db::name('user_notice')->where($where)->count();

            $Page = new AjaxPage($count, 7);
            $list = Db::name('user_notice')->where($where)->limit($Page->firstRow . ',' . $Page->listRows)->order('create_time desc,id')->select();

            if ($list) {
                if ($data['is_read'] == 0) {
                    //标位已提示
                    $where = array(
                        'user_id' => $user_id,
                        'is_tips' => 0,
                        'id' => array('elt', $list[0]['id']),
                    );
                    $res1 = M('user_notice')->where($where)->update(array('is_tips' => 1));
                }
            }

            $this->assign('list', $list);
            return $this->fetch('ajax_message_notification');
        }


        $is_read = $data['is_read'] == 2 ? 0 : 1;

        $this->assign('is_read', $is_read);
        return $this->fetch();
    }


}