<?php
namespace app\vpay\controller;

use Think\Controller;

class Login extends Base
{
    // 会员登录
    public function login()
    {
        if (IS_POST) {
            $account = trim(I("post.account"));
            $password = trim(I("post.password"));
            // 账号为空
            if (empty($account)) $this->ajaxError("请填写手机号/UID！");

            // 密码为空
            if (empty($password)) $this->ajaxError("请填写登录密码！");

            //账号是否存在
            $member = M("member")->where("(account='".$account."' or id='".$account."') AND isDelete=2")->find();
            if (!$member) {
                $this->ajaxError("账号不存在！");
            }

            //密码不一致
            if (md5($password) != $member['password']) $this->ajaxError("密码错误！");

            // 账号被禁用
            if ($member["isDisable"] == 1) $this->ajaxError("该帐号已被禁用！");
            parent::setAccount($member);
            $this->ajaxSuccess(array('msg' => "登录成功！", "member" => $member));

        } else {
            $config = tpCache('vpay_spstem_share');
            $this->assign('config',$config);
            $account=parent::get("account");
            if($account){
                $this->redirect("Vpay/Index/index");
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
        parent::remove('account');
        parent::remove('token');
        parent::remove('code');
        parent::remove("openid");
        setcookie('account','',time()-3600,'/');
        $this->ajaxSuccess("退出成功");
    }
}