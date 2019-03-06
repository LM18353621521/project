<?php
namespace app\dcworld\controller;

use Think\Controller;
use think\Db;

class Login extends Base
{
    /**
     * 自动执行-每天凌晨后执行
     * @return mixed
     */
    public function dc_static_release()
    {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');
        $time = strtotime(date("Y-m-d", time()));

        $WorldLogic = new \app\common\logic\WorldLogic();
        //查找释放会员、矿机
        $WorldLogic->get_release_miner($time);
        //开始进行释放
        $result = $WorldLogic->start_release_miner();
        if ($result) {
            //继续执行
            header('Refresh: 1; url=' . U('Dcworld/Login/dc_static_release'));//1秒以后跳转
        }

    }

    // 会员登录
    public function login()
    {
        if (IS_POST) {
            $account = trim(I("post.account"));
            $password = trim(I("post.password"));
            // 账号为空
            if (empty($account)) $this->ajaxError("请填写手机号！");


            // 密码为空
            if (empty($password)) $this->ajaxError("请填写登录密码！");

            //账号是否存在
            // $member = M("member")->where("(account='".$account."' or id='".$account."') AND isDelete=2")->find();
            $member = M("member")->where(['account' => $account, 'isDelete' => 2])->find();
            if (!$member) {
                $this->ajaxError("账号不存在！");
            }
            //密码不一致
            if (md5($password) != $member['password']) $this->ajaxError("密码错误！");

            // 账号被禁用
            if ($member["isDisable"] == 1) $this->ajaxError("该帐号已被禁用！");
            parent::setAccount($member);
            session('account', $member);

            $this->ajaxSuccess(array('msg' => "登录成功！", "member" => $member));

        } else {
            $config = tpCache('vpay_spstem_share');
            $this->assign('config', $config);
            $account = parent::get("account");
            if ($account) {
                $this->redirect("Dcworld/Index/index");
            }
            return $this->fetch();
        }
    }

    // 微信登录-获取会员信息
    public function wxLogin()
    {
        $code = I("code");
        $openid = parent::getWxUser($code);
        if (empty($openid)) return $this->ajaxError(array('msg' => 'openId为空！'));
        $member = M("member")
            ->where(array("openId" => $openid))
            ->find();

        if (empty($member)) {
            //未绑定先注册或登录绑定
            parent::set("openid", $openid);
            return $this->ajaxError(array('msg' => "您尚未绑定请先注册/登录！", 'openid' => $openid));
        } else {
            //已注册
            if ($member["isdisable"] == 1) {
                return $this->ajaxError(array('msg' => "该帐号已被禁用！"));
            }

            if ($member["isdelete"] == 1) {
                return $this->ajaxError(array('msg' => "该帐号不存在！"));
            }

            if ($member) {
                $member = M("member")
                    ->alias("m")
                    ->join("LEFT JOIN agen a ON m.belongAgenId=a.id")
                    ->join("LEFT JOIN (SELECT * FROM memberaddress WHERE isDefault = 1 AND isDelete = 2) ma ON ma.memberId = m.id")
                    ->join("LEFT JOIN (SELECT * FROM onlineorder WHERE orderType = 1 AND payType >= 1 AND `status` in (2,3,4)) o ON o.SourceId = m.id")
                    ->join("LEFT JOIN region r ON r.id = ma.regionId")
                    ->where(array("m.openId" => $openid))
                    ->field("m.*,a.account agen,ma.id addressid,ma.regionId,ma.address,ma.name receivename,ma.phone,r.name_path,IFNULL(sum(o.total),0) sumall")
                    ->find();
                parent::setAccount($member);
                $this->loginlog($member['id'], '1');
                return $this->ajaxSuccess(array('msg' => '登录成功！', 'member' => $member, 'openid' => $openid));
            } else {
                return $this->ajaxError(array('msg' => '错误！'));
            }
        }
    }

    //上传身份证图片
    public function upidcard()
    {
        //获取待上传文件
        $file = current($_FILES);

        //上传文件
        $result = upload_file($file);

        if ($result) {
            $this->ajaxSuccess(array("status" => 0, "url" => $result["url"]));
        } else {
            $this->ajaxError(array("error" => "图片上传失败！"));
        }
    }

    //退出登录
    public function exitLogin()
    {
        session_unset();
        // session_destroy();
        parent::remove('account');
        parent::remove('token');
        parent::remove('code');
        parent::remove("openid");
        setcookie('account', '', time() - 3600, '/');
        header("Location:" . U('Dcworld/Index/index'));
        exit;
        // $this->ajaxSuccess("退出成功");
    }

    /**
     * 注册会员
     */
    public function register()
    {
        $pid = $_GET['id'];
        if ($pid) {
            $member_data = M('member')->field('invitecode')->where(['id' => $pid])->find();
        }
        // 扫码进入自动获取推荐码
        $invitecode = $member_data['invitecode'] ? $member_data['invitecode'] : '';
        $act = $_GET['act'] ? $_GET['act'] : 0;
        if (IS_POST) {
            //邀请码
            $invitecode = trim(I("invitecode"));
            //账户（手机号）
            $account = trim(I("account"));
            //验证码
            $code = trim(I("code"));
            //密码
            $password = trim(I("password"));
            // 昵称
            $nickname = trim(I("nickname"));

            if (empty($nickname)) {
                $this->ajaxError("请填写您的昵称！");
            }
            $data['nickname'] = $nickname;
            //判断推荐人
            if (empty($invitecode)) {
                $this->ajaxError("请填写您的邀请码！");
            } else {
                //判断推荐人账户是否存在
                $parent = M("member")
                    ->where('invitecode', $invitecode)
                    ->find();
                //填写的推荐人不存在
                if (empty($parent)) {
                    $this->ajaxError("推荐人不存在！");
                }
            }
            $data['parentId'] = $parent['id'];
            $data['parentAccount'] = $parent['account'];

            //账户
            if (empty($account)) {
                $this->ajaxError("请填写您的手机号！");
            } else {
                if (!isPhone($account)) {
                    $this->ajaxError("请填写正确格式的手机号！");
                }
                //是否存在
                $account_occupy = M("member")->where(array("account" => $account))->find();
                if ($account_occupy) $this->ajaxError("手机号已存在！");
            }
            $data['account'] = $account;
            //验证码
            if (empty(parent::get("REG_MOBILE"))) $this->ajaxError("请发送手机验证码！");
            if ($account != parent::get("REG_MOBILE")) $this->ajaxError("手机号与接收验证码手机号不一致！");
            if (empty($code)) {
                $this->ajaxError("请填写手机验证码！");
            } else {
                if ($code != parent::get("REG_CODE")) {
                    $this->ajaxError("验证码错误！");
                }
            }
            //登录密码
            if (empty($password)) {
                $this->ajaxError("请填写登录密码！");
            } else {
                if (strlen($password) < 6 || strlen($password) > 16) {
                    $this->ajaxError("登录密码长度不符!");
                }
                if (!isNumAndLetter($password)) {
                    $this->ajaxError("登录密码必须同时包含数字和英文！");
                }
            }
            $data['password'] = md5($password);
            //invitecode邀请码
            $data['invitecode'] = randomkeys(6);
            //创建时间
            $data["createTime"] = date("Y-m-d H:i:s");
            //更新时间
            $data["updateTime"] = date("Y-m-d H:i:s");
            //禁用
            $data["isDisable"] = 2; //正常
            //删除
            $data["isDelete"] = 2; //正常
            //钱包地址
            $data['wallet'] = wallet_address_generation($account);
            $data['other_balance'] = 0;

            //创建用户表member数据
            Db::startTrans();
            $result = M("member")->add($data);

            if ($result) {
                # 注册赠送DC 用于捡贝壳
                $give_dc_reg = worldCa("shell_set.give_dc_reg");
                if ($give_dc_reg > 0) {
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
                    $result2 = M("world_giveshell")->add($give_shell_data);

                    if ($result2) {
                        Db::commit();
                        //清空
                        parent::remove("REG_MOBILE");
                        parent::remove("REG_CODE");
                        $this->ajaxSuccess("注册成功！");
                    } else {
                        Db::rollback();
                        $this->ajaxError("注册失败！");
                    }
                } else {
                    // 不赠送D 直接注册成功
                    Db::commit();
                    //清空
                    parent::remove("REG_MOBILE");
                    parent::remove("REG_CODE");
                    $this->ajaxSuccess("注册成功！");
                }
            } else {
                Db::rollback();
                $this->ajaxError("注册失败！");
            }
        } else {
            $config = tpCache('vpay_spstem_sms.regis_sms_enable');

            $poster = worldCa('poster');

            // $invitecode=I("invitecode");
            $this->assign("invitecode", $invitecode);
            $this->assign("act", $act);
            $this->assign("regis_sms_enable", $config);
            $this->assign("poster", $poster);
            return $this->fetch();
        }
    }

    /**
     * 发送短信验证码
     */
    public function sendCode()
    {
        $account = trim(I('post.account'));
        $type = (int)I('post.type'); // 类型 1：注册 2：找回密码 3：找回支付密码
        if (empty($account)) $this->ajaxError("请输入手机号！");
        // 账号错误
        if (!isPhone($account)) return $this->ajaxError("请输入正确格式的手机号！");
        // 如果已经
        if (!empty(parent::get("REG_CODE_TIME")) && time() - parent::get("REG_CODE_TIME") < 60) return $this->ajaxError("请求发送短信间隔太短！");
        if (!empty(parent::get("FORGET_CODE_TIME")) && time() - parent::get("FORGET_CODE_TIME") < 60) return $this->ajaxError("请求发送短信间隔太短！");
        if (!empty(parent::get("FORGET_PAYCODE_TIME")) && time() - parent::get("FORGET_PAYCODE_TIME") < 60) return $this->ajaxError("请求发送短信间隔太短！");
        // 获取配置
        $sms = worldCa('spstem_sms');
        if (!$sms) return $this->ajaxError("没有配置短信");

        $url = $sms['url'];
        $key = $sms['key'];
        $tplId = $sms['tplId'];

        $code = rand(100000, 999999);

        if (1 == $type) {
            $account_occupy = M("member")->where(array("account" => $account))->find();
            if ($account_occupy) $this->ajaxError("手机号已被使用！");
            parent::set("REG_CODE", $code);
            parent::set("REG_MOBILE", $account);
            parent::set("REG_CODE_TIME", time());
        } else if (2 == $type) {
            $account_occupy = M("member")->where(array("account" => $account))->find();
            if (empty($account_occupy)) $this->ajaxError("不存在该手机号，请重新输入！");
            parent::set("FORGET_CODE", $code);
            parent::set("FORGET_MOBILE", $account);
            parent::set("FORGET_CODE_TIME", time());
        } else if (3 == $type) {
            parent::set("FORGET_PAY_CODE", $code);
            parent::set("FORGET_PAY_MOBILE", $account);
            parent::set("FORGET_PAYCODE_TIME", time());
        }



        //24小时内允许发三条
        $where = array(
            'mobile'=>$account,
            'add_time'=>array('gt',time()-86400),
        );
        $send_num =  M('sms_log')->where($where)->count();
        if($send_num>=3){
            return $this->ajaxError("发送失败，24小时内允许发送3条信息！");
        }

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
     * 忘记密码
     */
    public function forget()
    {

        $act = I('act', 1);
        $this->assign('act', $act);

        if (IS_POST) {
            $act = trim(I('post.act')); //验证方式
            $account = trim(I('post.account')); //手机号
            $memorizing_word = trim(I('post.memorizing_word')); //助记词
            $newpwd = trim(I('post.password')); //新密码
            $mobileCode = trim(I("post.code")); //验证码

            if (empty($account)) {
                $this->ajaxError("手机号不能为空！");
            }
            // 账号错误
            if (!isPhone($account)) return $this->ajaxError("请输入正确格式的手机号！");

//            if (empty(parent::get("FORGET_MOBILE"))) {
//                $this->ajaxError('请点击获取验证码！');
//            }

            if ($account != parent::get("FORGET_MOBILE")) {
                $this->ajaxError('手机号与发送验证码手机号不一致！');
            }

            if (empty($mobileCode)) {
                $this->ajaxError("请填写手机验证码！");
            } else {
                if ($mobileCode != parent::get("FORGET_CODE")) {
                    $this->ajaxError("验证码错误！");
                }
            }

            if (empty($memorizing_word)) {
                $this->ajaxError("助记词不能为空！");
            }

            $member = M('member')
                ->where(array('account' => $account, 'memorizing_word' => $memorizing_word))
                ->find();

            if (empty($member)) {
                $this->ajaxError("账号不存在，账号或助记词不正确！");
            }


            if (empty($newpwd)) {
                $this->ajaxError("密码不能为空！");
            }

            if (strlen($newpwd) < 6) {
                $this->ajaxError('密码长度必须大于6位');
            }
            if (!isNumAndLetter($newpwd)) {
                $this->ajaxError("密码必须同时包含数字和英文！");
            }


            $res = M('member')
                ->where(array('account' => $account))
                ->save(array('password' => md5($newpwd)));


            if (false !== $res) {
                parent::remove("FORGET_MOBILE");
                parent::remove("FORGET_CODE");
                $this->ajaxSuccess("密码重置成功！");
            } else {
                $this->ajaxError('密码重置失败！');
            }
        } else {
            return $this->fetch();
        }
    }


    /**
     * 验证form信息是否正确
     */
    public function validaform()
    {
        //邀请码
        $invitecode = trim(I("invitecode"));
        //账户（手机号）
        $account = trim(I("account"));
        //验证码
        $code = trim(I("code"));
        //密码
        $password = trim(I("password"));
        // 昵称
        $nickname = trim(I("nickname"));

        if (empty($nickname)) {
            $this->ajaxError("请填写您的昵称！");
        }
        //判断推荐人
        if (empty($invitecode)) {
            $this->ajaxError("请填写您的邀请码！");
        }
        //账户
        if (empty($account)) {
            $this->ajaxError("请填写您的手机号！");
        } else {
            $account_occupy = M("member")->where(array("account" => $account))->find();
            if ($account_occupy) $this->ajaxError("手机号已存在！");
        }
        $regis_sms_enable = tpCache('vpay_spstem_sms.regis_sms_enable');
        if ($regis_sms_enable) {
            //验证码
            if (empty($code)) $this->ajaxError("请填写手机验证码！");
        }
        //登录密码
        if (empty($password)) {
            $this->ajaxError("请填写登录密码！");
        }

        $this->ajaxSuccess("验证通过");
    }

    /**
     * APP下载
     */
    public function app_download()
    {
        $poster = worldCa("poster");
        $this->assign('poster', $poster);
        return $this->fetch();
    }

}