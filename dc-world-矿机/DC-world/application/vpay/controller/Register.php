<?php
namespace app\vpay\controller;

use think\Db;
class Register extends Base
{
    /**
     * 验证form信息是否正确
     */
    public function validaform()
    {
        //邀请码
        $invitecode = trim(I("invitecode"));
        //昵称
        $nickname = trim(I("nickname"));
        //账户（手机号）
        $account = trim(I("account"));
        //验证码
        $code = trim(I("code"));
        //密码
        $password = trim(I("password"));
        //支付密码
        $paypassword = trim(I("paypassword"));

        //判断推荐人
        if (empty($invitecode)) {
            $this->ajaxError("请填写您的邀请码！");
        }

        //昵称
        if (empty($nickname)) {
            $this->ajaxError("请填写昵称！");
        }

        //账户
        if (empty($account)) {
            $this->ajaxError("请填写您的手机号！");
        } else {
            $account_occupy = M("member")->where(array("account"=>$account))->find();
            if ($account_occupy) $this->ajaxError("手机号已存在！");
        }
        $regis_sms_enable = tpCache('vpay_spstem_sms.regis_sms_enable');
        if($regis_sms_enable){
        //验证码
        if (empty($code)) $this->ajaxError("请填写手机验证码！");
        }
        //登录密码
        if (empty($password)) {
            $this->ajaxError("请填写登录密码！");
        }

        //支付密码
        if (empty($paypassword)) {
            $this->ajaxError("请填写支付密码！");
        }

        $this->ajaxSuccess("验证通过");
    }

    /**
     * 注册会员
     */
    public function register()
    {
        if (IS_POST) {
            //邀请码
            $invitecode = trim(I("invitecode"));
            //昵称
            $nickname = trim(I("nickname"));
            //账户（手机号）
            $account = trim(I("account"));
            //验证码
            $code = trim(I("code"));
            //密码
            $password = trim(I("password"));
            //支付密码
            $paypassword = trim(I("paypassword"));

            //判断推荐人
            if (empty($invitecode)) {
                $this->ajaxError("请填写您的邀请码！");
            } else {
                //判断推荐人账户是否存在
                $parent = Db::name("member")
                    ->where('invitecode',$invitecode)
                    ->whereOr('account','=',$invitecode)
                    ->find();
                //填写的推荐人不存在
                if (empty($parent)) {
                    $this->ajaxError("推荐人不存在！");
                }
            }
            $data['parentId'] = $parent['id'];
            $data['parentAccount'] = $parent['account'];

            //昵称
            if (empty($nickname)) {
                $this->ajaxError("请填写昵称！");
            } else {
                $nick_occupy = M("member")->where(array("nickname"=>$nickname))->find();
                if ($nick_occupy) $this->ajaxError("昵称已被使用！");
            }
            $data['nickname'] = $nickname;

            //账户
            if (empty($account)) {
                $this->ajaxError("请填写您的手机号！");
            } else {
                if (!isPhone($account)) {
                    $this->ajaxError("请填写正确格式的手机号！");
                }
                //是否存在
                $account_occupy = M("member")->where(array("account"=>$account))->find();
                if ($account_occupy) $this->ajaxError("手机号已存在！");
            }
            $data['account'] = $account;
            $regis_sms_enable = tpCache('vpay_spstem_sms.regis_sms_enable');
            if($regis_sms_enable){
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
            }
            //登录密码
            if (empty($password)) {
                $this->ajaxError("请填写登录密码！");
            } else {
                if (strlen($password) < 6) {
                    $this->ajaxError("登录密码长度最少为6位！");
                }
                if (!isNumAndLetter($password)) {
                    $this->ajaxError("登录密码必须同时包含数字和英文！");
                }
            }
            $data['password'] = md5($password);

            //支付密码
            if (empty($paypassword)) {
                $this->ajaxError("请填写支付密码！");
            } else {
                if (strlen($paypassword) != 6) {
                    $this->ajaxError("请输入6位数字支付密码！");
                }
                if (!is6Num($paypassword)) {
                    $this->ajaxError("请输入6位数字支付密码！");
                }
            }
            $data['paypassword'] = md5($paypassword);

            //invitecode邀请码
            $data['invitecode'] = $this->generate_invitecode();

            //创建时间
            $data["createTime"] = date("Y-m-d H:i:s");
            //更新时间
            $data["updateTime"] = date("Y-m-d H:i:s");
            //禁用
            $data["isDisable"] = 2; //正常
            //删除
            $data["isDelete"] = 2; //正常

            //钱包地址
            $data['wallet'] = md5($account);
            $data['other_balance'] = 0;

            //注册增加注册积分
            $system=tpCache("vpay_spstem");
            $data['integral'] = $system['regIntegral'] ? $system['regIntegral'] : 0;
            //创建用户表member数据
            Db::startTrans();
            $result = M("member")->add($data);

            if ($result) {
                //使用id，也就是uid，做邀请码
                $mem_save=M("member")->where(array("id"=>$result))->save(array("invitecode"=>$result));
                if(empty($mem_save)){
                    Db::rollback();
                    $this->ajaxError("注册失败！");
                }
                $data2['account'] = $account;
                $data2['userId'] = $result;
                $data2["createTime"] = date("Y-m-d H:i:s");
                $data2["updateTime"] = date("Y-m-d H:i:s");
                $result2 = M("virtualcurrency")->add($data2);
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
                Db::rollback();
                $this->ajaxError("注册失败！");
            }
        } else {
            $config = tpCache('vpay_spstem_sms.regis_sms_enable');
            $invitecode=I("invitecode");
            $this->assign("invitecode",$invitecode);
            $this->assign("regis_sms_enable",$config);
            return $this->fetch();
        }
    }

    /**
     * 生成邀请码
     * @return string
     */
    public function generate_invitecode()
    {
        $rand = rand(10,100).date("mdis").rand(10,100);
        $code_occupy = M("member")->where(array("invitecode"=>$rand))->find();
        if ($code_occupy) {
            $this->generate_invitecode();
        }
        return $rand;
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

//        $code = rand(100000, 999999);
//        $smsParams = array(
//            1 => "{\"code\":\"$code\"}",                                                                                                          //1. 用户注册 (验证码类型短信只能有一个变量)
//        );
//        sendSmsByAliyun(13428854912,'BMI钱包',$smsParams[1],'SMS_143719111');
//        exit;
        // 获取配置
        $sms = tpCache('vpay_spstem_sms');
        if(!$sms) return $this->ajaxError("没有配置短信");

        $url = $sms['url'];
        $key = $sms['key'];
        $tplId = $sms['tplId'];

        $code = rand(100000, 999999);

        if (1 == $type) {
            $account_occupy = M("member")->where(array("account"=>$account))->find();
            if ($account_occupy) $this->ajaxError("手机号已被使用！");
            parent::set("REG_CODE", $code);
            parent::set("REG_MOBILE", $account);
            parent::set("REG_CODE_TIME", time());
        } else if (2 == $type) {
            parent::set("FORGET_CODE", $code);
            parent::set("FORGET_MOBILE", $account);
            parent::set("FORGET_CODE_TIME", time());
        } else if (3 == $type) {
            parent::set("FORGET_PAY_CODE", $code);
            parent::set("FORGET_PAY_MOBILE", $account);
            parent::set("FORGET_PAYCODE_TIME", time());
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
    public function forgetPasswd()
    {
        if (IS_POST) {
            $account = trim(I('post.account')); //手机号
            $newpwd = trim(I('post.password')); //新密码
            $mobileCode = trim(I("post.code")); //验证码

            if (empty($account)) {
                $this->ajaxError("手机号不能为空！");
            }
            if (empty(parent::get("FORGET_MOBILE"))) {
                $this->ajaxError('请点击获取验证码！');
            }
            if (empty($newpwd)) {
                $this->ajaxError("密码不能为空！");
            }

            if ($account != parent::get("FORGET_MOBILE")) {
                $this->ajaxError('手机号与发送验证码手机号不一致！');
            }

            if ($mobileCode != parent::get("FORGET_CODE")) {
                $this->ajaxError('手机验证码错误');
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

    public function downapp(){
        $invitecode=I("invitecode");
        $this->assign("invitecode",$invitecode);
        return $this->fetch();
    }
}
