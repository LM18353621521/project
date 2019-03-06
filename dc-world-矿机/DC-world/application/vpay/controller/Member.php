<?php

namespace app\vpay\controller;
use think\Db;
class Member extends Sign
{
    /**
     * 个人中心 index
     */
    public function index()
    {
        $uid = $this->getAccountId();
        $uinfo = M('member')
            ->alias("t")
            ->field("t.*,b.*")
            ->join("vpay_level b","t.level=b.level_id",'LEFT')
            ->where(array("id"=>$uid))
            ->find();
        $this->assign('uinfo', $uinfo);
        $system=tpCache("vpay_spstem");
        $this->assign('appVersion', $system['appVersion']);
        return $this->fetch();
    }

    /**
     * 昵称修改
     */
    public function setNickName()
    {
        $member= parent::get('account');
        if (IS_POST) {
            $id = (int)I('memberId');
            $nickname = trim(I('nickname'));
            if (empty($id)) $this->ajaxError('未知的用户id！');
            if (empty($nickname)) $this->ajaxError('请输入昵称！');
            if ($member['id'] != $id) $this->ajaxError('非法操作：登录用户与操作账户不一致！');

            $res = M('member')->where(array("id"=>$id))->save(array("nickname"=>$nickname));
            if (false !== $res) {
                $this->ajaxSuccess("重置成功！");
            } else {
                $this->ajaxError("操作失败！");
            }
        } else {
            $this->assign('memberId', $member['id']);
            $this->assign('nickname', $member['nickname']);
            return $this->fetch('member/nickname');
        }
    }

    /**
     * 我的银行卡
     */
    public function bankList()
    {
        if (IS_POST) {
            $memberId = parent::get('account')['id'];
            $bankCardList = M('bankcard')
                ->alias("b")
                ->join("bank k","k.bankId=b.bankId",'LEFT')
                ->where(array("b.memberId"=>$memberId, "b.isDelete"=>0))
                ->select();
            $this->ajaxSuccess($bankCardList);
        } else {
            return $this->fetch('member/bank_list');
        }
    }

    /**
     * 设置默认银行卡
     */
    public function setDefaultCard()
    {
        $memberId = parent::get('account')['id'];
        if (IS_POST) {
            $id = (int)I('id');
            if (empty($id)) $this->ajaxError("参数错误：未知的id！");
            $info = M("bankcard")->where(array("id"=>$id, "isDelete"=>0))->find();
            if (empty($info)) $this->ajaxError("银行卡不存在或已删除！");

            if ($memberId != $info['memberId']) $this->ajaxError("非法操作！");
            Db::startTrans();
            $res1 = M("bankcard")->where(array("memberId"=>$memberId))->save(array("isDefault" =>0));
            if (false !== $res1) {
                $res2 = M("bankcard")->where(array("id"=>$id, "memberId"=>$memberId))->save(array("isDefault" =>1));
                if (false !== $res2) {
                    Db::commit();
                    $this->ajaxSuccess("默认银行卡设置成功！");
                } else {
                    Db::rollback();
                    $this->ajaxError("操作失败！");
                }
            } else {
                Db::rollback();
                $this->ajaxError("操作失败！");
            }
        } else {
            $this->ajaxError("请求方式错误！");
        }
    }

    /**
     * 添加银行卡
     */
    public function addBankCard()
    {
        $memberId = parent::get("account")['id'];
        if (IS_POST) {
            $userId = (int)I('memberid');
            $realname = trim(I('realname'));
            $no  = trim(I('no'));
            $mobile  = trim(I('mobile'));
            $bankid = (int)I('bankid');
            $branch = trim(I('branch'));
            $isdefault = trim(I('isdefault')) == 'true' ? 1 : 0;

            if ($memberId != $userId) $this->ajaxError("非法操作！");
            if (empty($realname)) $this->ajaxError("请填写持卡人姓名！");
            if (empty($no)) $this->ajaxError("请填写银行卡账号！");
            if (empty($mobile)) $this->ajaxError("请填写手机号码！");
            if (!isBankCard($no)) $this->ajaxError("请填写正确格式的银行卡号！");
            if (empty($bankid)) $this->ajaxError("请选择开户行！");
            if (empty($branch)) $this->ajaxError("请填写支行信息！");


            $data = array();
            $data['memberId'] = $userId;
            $data['mobile'] = $mobile;
            $data['bankId'] = $bankid;
            $data['branch'] = $branch;
            $data['realName'] = $realname;
            $data['no'] = $no;
            Db::startTrans();
            if (1 == $isdefault) {
                $data['isDefault'] = 1;
                $step1 = M("bankcard")->where(array("memberId"=>$userId, "isDelete"=>0))->save(array("isDefault"=>0));
                if (false !== $step1) {
                    $step2 = M("bankcard")->add($data);
                    if ($step2) {
                        Db::commit();
                        $this->ajaxSuccess("添加成功！");
                    } else {
                        Db::rollback();
                        $this->ajaxError("添加失败！");
                    }
                } else {
                    Db::rollback();
                    $this->ajaxError("添加失败！");
                }
            } else if (0 == $isdefault) {
                $step = M("bankcard")->add($data);
                if ($step) {
                    Db::commit();
                    $this->ajaxSuccess("添加成功！");
                } else {
                    Db::rollback();
                    $this->ajaxError("添加失败！");
                }
            }
        } else {
            //银行列表
            $banklist = M('bank')->select();
            $this->assign('banklist', $banklist);

            $this->assign('memberId', $memberId);
            return $this->fetch('member/add_bankcard');
        }
    }

    /**
     * 删除银行卡
     */
    public function delBankCard()
    {
        if (IS_POST) {
            $memberId = parent::get('account')['id'];
            $id = (int)I('id');
            $res = M('bankcard')->where(array("id"=>$id, "memberId"=>$memberId))->save(array("isDelete"=>1));
            if ($res) {
                $this->ajaxSuccess('删除成功！');
            } else {
                $this->ajaxError("删除失败！");
            }
        } else {
            $this->ajaxError("请求方式错误！");
        }
    }

    /**
     * 我的资产
     */
    public function user_assets()
    {

            $memberId = parent::get("account")['id'];
            $user = M("member")
                ->alias("m")
                ->field("m.account,m.nickname,m.balance,m.integral,m.integralSum,v.vpay,v.bitcoin,v.litecoin,v.ethereum,v.dogecoin,v.vcoin")
                ->join("__VIRTUALCURRENCY__ v","v.userId=m.id",'LEFT ')
                ->where(array("m.id"=>$memberId))
                ->find();
            $this->assign('user',$user);
            return $this->fetch();

    }

    /**
     * 登录密码
     */
    public function revisePassword()
    {
        if (IS_POST) {
            $memberId = parent::get("account")['id'];
            $oldPasswd = trim(I('oldPasswd'));
            $newPasswd = trim(I('newPasswd'));

            if (empty($oldPasswd)) $this->ajaxError("请输入原密码！");
            if (empty($newPasswd)) $this->ajaxError("请输入新密码！");
            $memberInfo = M('member')->field("password")->where(array("id"=>$memberId))->find();
            if ($memberInfo['password'] != md5($oldPasswd)) $this->ajaxError("原密码错误！");

            if (strlen($newPasswd) < 6) {
                $this->ajaxError('密码长度必须大于6位');
            }
            if (!isNumAndLetter($newPasswd)) {
                $this->ajaxError("密码必须同时包含数字和英文！");
            }

            $res = M('member')->where(array("id"=>$memberId))->save(array("password"=>md5($newPasswd)));
            if (false !== $res) {
                $this->ajaxSuccess("操作成功");
            } else {
                $this->ajaxError("操作失败！");
            }
        } else {
            return $this->fetch('member/revise_password');
        }
    }

    /**
     * 支付密码
     */
    public function revisePayment()
    {
        if (IS_POST) {
            $memberId = parent::get("account")['id'];
            $oldPasswd = trim(I('oldPasswd'));
            $newPasswd = trim(I('newPasswd'));

            if (empty($oldPasswd)) $this->ajaxError("请输入原支付密码！");
            if (empty($newPasswd)) $this->ajaxError("请输入新支付密码！");
            $memberInfo = M('member')->field("paypassword")->where(array("id"=>$memberId))->find();
            if ($memberInfo['paypassword'] != md5($oldPasswd)) $this->ajaxError("原支付密码输入错误！");

            if (!preg_match("/^\d{6}$/",$newPasswd)) {
                $this->ajaxError("密码须为6位纯数字！");
            }

            $res = M('member')->where(array("id"=>$memberId))->save(array("paypassword"=>md5($newPasswd)));
            if (false !== $res) {
                $this->ajaxSuccess("操作成功");
            } else {
                $this->ajaxError("操作失败！");
            }
        } else {
            return $this->fetch('member/revise_payment');
        }
    }

    /**
     * 公告
     */
    public function notice()
    {
        if (IS_POST) {
            $page = (int)I('p') ? (int)I('p') : 0;
            $list = 10;
            $notices = M('article')->limit($page*$list, $list)->order("createTime desc")->select();
            $this->ajaxSuccess ($notices);
        } else {
            return $this->fetch('member/notice');
        }
    }

    /**
     * 公告详情
     */
    public function notice_detail()
    {
        if (IS_POST) {
            $id = (int)I('id');
            if (empty($id)) $this->ajaxError("参数错误：未知的id！");

            $detail = M("article")->find($id);
            if (empty($detail)) $this->ajaxError("公告不存在或已删除！");
            $this->ajaxSuccess($detail);
        } else {
            return $this->fetch('member/notice_detail');
        }
    }

    /**
     * 忘记支付密码
     */
    public function forget_pay()
    {
        if (IS_POST) {
            $member = parent::get("account");

            $account = trim(I('post.account')); //手机号

            $newpwd = trim(I('post.password')); //新密码
            $mobileCode = trim(I("post.code")); //验证码

            if (empty($account)) {
                $this->ajaxError("手机号不能为空！");
            }
            if ($member['account'] != $account) $this->ajaxError("输入手机号与账号手机号不一致！");
            if (empty(parent::get("FORGET_PAY_MOBILE"))) {
                $this->ajaxError('请点击获取验证码！');
            }
            if (empty($newpwd)) {
                $this->ajaxError("密码不能为空！");
            }

            if ($account != parent::get("FORGET_PAY_MOBILE")) {
                $this->ajaxError('手机号与发送验证码手机号不一致！');
            }

            if ($mobileCode != parent::get("FORGET_PAY_CODE")) {
                $this->ajaxError('手机验证码错误');
            }

            if (!preg_match("/^\d{6}$/", $newpwd)) {
                $this->ajaxError("密码须为6位纯数字！");
            }

            $res = M('member')->where(array("id"=>$member['id']))->save(array("paypassword"=>md5($newpwd)));

            if (false !== $res) {
                parent::remove("FORGET_PAY_MOBILE");
                parent::remove("FORGET_PAY_CODE");
                $this->ajaxSuccess("密码重置成功！");
            } else {
                $this->ajaxError('密码重置失败！');
            }
        } else {
            return $this->fetch('member/forget_pay');
        }
    }

    /**
     * 个人消息
     */
    public function personal_news()
    {
        if (IS_POST) {
            $page = (int)I('p') ? (int)I('p') : 0;
            $list = 10;
            $member = parent::get("account");
            $notices = M('notice')->where(array("toUser"=>$member['id']))->limit($page*$list, $list)->order("createTime desc")->select();
            $this->ajaxSuccess($notices);
        } else {
            return $this->fetch('member/personal_news');
        }
    }

    /**
     * 个人消息 详情
     */
    public function personal_news_detail()
    {
        if (IS_POST) {
            $memberid = parent::get("account")['id'];
            $id = (int)I('id');
            if (empty($id)) $this->ajaxError("参数错误：未知的id！");

            $detail = M("notice")->where(array("id"=>$id, "toUser"=>$memberid))->find();
            if (empty($detail)) $this->ajaxError("消息不存在或已删除！");
            $detail['content']=html_entity_decode($detail['content']);
            $this->ajaxSuccess($detail);
        } else {
            return $this->fetch();
        }
    }

    // 上传头像图片
    public function uploadFaceImage()
    {
        $memberId = parent::get("account")['id'];
        if (IS_AJAX)
        {
            //获取待上传文件
            $file = current($_FILES);
            //上传文件
            $result = upload_file($file);
            if ($result)
            {
                $res = M('member')->where(array("id"=>$memberId))->save(array("profilePhoto"=>$result['url']));
                if ($res) {
                    $this->ajaxReturn(array("status"=>0,"url"=>$result["url"]));
                } else {
                    $this->ajaxReturn(array("error"=>"图片保存失败！"));
                }
            }
            else
            {
                $this->ajaxReturn(array("error"=>"图片上传失败！"));
            }
        }
    }

    //扫码支付
    /*public function qrcode_pay()
    {
        $memberId = parent::get("account")['id'];
        if (IS_AJAX)
        {
            set_time_limit(0);
            //获取待上传文件
            $file = current($_FILES);
            //上传文件
            $result = upload_file($file);
            if ($result)
            {
                vendor('Qrreader.lib.QrReader');
//                $dir = scandir('qrcodes');
                $ignoredFiles = array(
                    '.',
                    '..',
                    '.DS_Store'
                );
//                foreach($dir as $file) {
//                    if(in_array($file, $ignoredFiles)){
//                        $this->ajaxError(array("error"=>"扫码失败，请重新扫码！"));
//                    }
//                }
                $qrcode = new \QrReader($result["url"]);
                $text = $qrcode->text();
//                print_r($text);die;
                $this->ajaxSuccess(array("url"=>$text));
            }
            else
            {
                $this->ajaxError(array("error"=>"扫码失败，请重新扫码！"));
            }
        }
    }*/
    
    /**
     * 余额记录列表
     */
    public function balanceRecord()
    {
        if (IS_POST) {
            $page = (int)I('p') ? (int)I('p') : 0;
            $list = 20;
            $memberId = parent::get("account")['id'];
            $logs = M('balancelog')->where(array("userId"=>$memberId))->limit($page*$list, $list)->order("id desc")->select();
            if (!empty($logs)) {
                foreach ($logs as $k=>$v) {
                    switch ($v['type']) {
                        case 1 :
                            $logs[$k]['type_str'] = "转入";
                            break;
                        case 2 :
                            $logs[$k]['type_str'] = "转出";
                            break;
                        case 3 :
                            $logs[$k]['type_str'] = "买入";
                            break;
                        case 4 :
                            $logs[$k]['type_str'] = "卖出";
                            break;
                        case 5 :
                            $logs[$k]['type_str'] = "签到";
                            break;
                        case 6 :
                            $logs[$k]['type_str'] = "保证金";
                            //$logs[$k]['type_str'] = "扣除保证金（交易买入）";
                            break;
                        case 7 :
                            $logs[$k]['type_str'] = "保证金";
                            //$logs[$k]['type_str'] = "回加保证金（交易确认）";
                            break;
                        case 8 :
                            $logs[$k]['type_str'] = "兑换积分";
                            break;
                        case 9 :
                            $logs[$k]['type_str'] = "保证金";
                            //$logs[$k]['type_str'] = "扣除保证金（数字资产）";
                            break;
                        case 10 :
                            $logs[$k]['type_str']="保证金";
                            //$logs[$k]['type_str'] = "回加保证金（数字资产）";
                            break;
                        case 11 :
                            $logs[$k]['type_str']="交易支出";
                            //$logs[$k]['type_str'] = "交易支出（数字资产）";
                            break;
                        case 12 :
                            $logs[$k]['type_str'] = "交易收入";
                            //$logs[$k]['type_str'] = "交易收入（数字资产）";
                            break;
                        case 13 :
                            $logs[$k]['type_str'] = "取消订单";
                            //$logs[$k]['type_str'] = "取消买入订单（交易买入）";
                            break;
                        case 14:
                            $logs[$k]['type_str'] = "获得";
                            //$logs[$k]['type_str'] = "余额变动返佣";
                            break;
                        case 15:
                            $logs[$k]['type_str'] = "取消订单";
                            //$logs[$k]['type_str'] = "回加交易金";
                            break;
                        case 16:
                            $logs[$k]['type_str'] = "后台操作";
                            //$logs[$k]['type_str'] = "后台手动操作";
                            break;
                        case 17:
                            $logs[$k]['type_str'] = "获得";
                            //$logs[$k]['type_str'] = "兑换积分返佣";
                            break;
                        case 18:
                            $logs[$k]['type_str'] = "手续费";
                            //$logs[$k]['type_str'] = "兑换积分返佣";
                            break;
                        case 19:
                            $logs[$k]['type_str'] = "手续费";
                            //$logs[$k]['type_str'] = "兑换积分返佣";
                            break;
                        default:
                            $logs[$k]['type_str'] = "收入";
                            break;
                    }
                }
            }
            $this->ajaxSuccess($logs);
        } else {
            return $this->fetch('ba_record');
        }
    }

    /**
     * 积分记录
     */
    public function integral_record()
    {
        if (IS_POST) {
            $page = (int)I('p') ? (int)I('p') : 0;
            $list = 20;
            $memberId = parent::get("account")['id'];
            $logs = M('integrallog')->where(array("userId"=>$memberId))->limit($page*$list, $list)->order("id desc")->select();
            if (!empty($logs)) {
                foreach ($logs as $k=>$v) {
                    switch ($v['type']) {
                        case 1 :
                            $logs[$k]['type_str'] = "金币兑换";
                            break;
                        case 2 :
                            $logs[$k]['type_str'] = "转出";
                            //$logs[$k]['type_str'] = "转出余额获得";
                            break;
                        case 3 :
                            $logs[$k]['type_str'] = "转入";
                            //$logs[$k]['type_str'] = "转入余额获得";
                            break;
                        case 4 :
                            $logs[$k]['type_str'] = "签到";
                            break;
                        case 5 :
                            $logs[$k]['type_str'] = "后台操作";
                            //$logs[$k]['type_str'] = "后台手动操作";
                            break;
                        case 6 :
                            $logs[$k]['type_str'] = "获得";
                            //$logs[$k]['type_str'] = "动态返佣扣除积分";
                            break;
                        case 7 :
                            $logs[$k]['type_str'] = "获得";
                            //$logs[$k]['type_str'] = "兑换积分返佣扣除积分";
                            break;
                        case 9 :
                            $logs[$k]['type_str'] = "团队奖收入";
                            //$logs[$k]['type_str'] = "兑换积分返佣扣除积分";
                            break;
                        default:
                            $logs[$k]['type_str'] = "收入";
                            break;
                    }
                }
            }
            $this->ajaxSuccess($logs);
        } else {
            return $this->fetch('integral_record');

        }
    }


    /*资金记录*/
    public function moneyLog()
    {
        $id = $this->getAccountId();

        $page = I('page');
        $list = 20;
        $data = M('moneylog')
            ->alias('m')
            ->join('LEFT JOIN onlineorder o ON o.id = m.sourceId')
            ->join('LEFT JOIN member a ON a.id = o.SourceId')
            ->where(array('m.accountId' => $id, "m.accountType" => 1))
            ->limit($page * $list, $list)
            ->field('m.*,o.sn,a.account,a.name,CASE m.`sourceType` WHEN 1 THEN "会员订单" WHEN 2 THEN "会员返佣" WHEN 3 THEN "代理提现" ELSE "未知" END stname')
            ->order("m.createtime desc")
            ->select();
        return $this->ajaxSuccess($data);
    }

    /*修改密码*/
    public function editpwd()
    {
        if (IS_POST) {
            $id = $this->getAccountId();

            $oldpwd = I('oldpwd');
            $newpwd1 = I('newpwd1');
            $newpwd2 = I('newpwd2');

            if (strlen($newpwd1) < 6) {
                return $this->ajaxError('密码长度必须大于6位');
            }
            if (!isNumAndLetter($newpwd1)) {
                $this->ajaxError("密码必须同时包含数字和英文！");
            }
            if ($newpwd1 != $newpwd2) {
                return $this->ajaxError("两次密码输入不一致！");
            }
            $result = M('member')
                ->where(array('id' => $id, 'password' => md5($oldpwd)))
                ->find();
            if ($result) {
                $res = M('member')
                    ->where(array('id' => $id))
                    ->save(array('password' => md5($newpwd1)));
                if ($res) {
                    return $this->ajaxSuccess("修改密码成功");
                } else {
                    return $this->ajaxError('修改密码失败');
                }
            } else {
                return $this->ajaxError('原密码错误');
            }
        }
    }

    /*个人中心*/
    public function usermessage()
    {
        $id = $this->getAccountId();

        $member = M("member")
            ->field("id,nickname,account,parentId,parentAccount,balance,integral,invitecode,profilePhoto")
            ->where("id='".$id."'")
            ->find();

        if ($member) {
            return $this->ajaxSuccess($member);
        } else {
            return $this->ajaxError('请重新登录！');
        }
    }

    // 展示二维码
    public function code()
    {
        $id = $this->getAccountId();

        $userinfo = M("member")->where(array('id' => $id))->find();
        return $this->ajaxSuccess($userinfo);
    }

    public function fandsecurity()
    {
        $sn = I('sn');
        $res = M('security')->where(array('sn' => $sn))->find();
        if ($res) {
            return $this->ajaxSuccess('此产品为本公司合格产品，请放心使用！');
        } else {
            return $this->ajaxError('防伪码未找到！');
        }
    }

    /*会员推广二维码*/
    public function getMemberTuiGuangUrl()
    {
        $member = parent::getAccount();
        $this->ajaxSuccess(array('url' => $member['code']));
    }

    public function my_activity()
    {
        return $this->fetch();
    }

    public function my_custom()
    {
        return $this->fetch();
    }

    public function my_repayment()
    {
        return $this->fetch();
    }


    /**
     * 判断身份信息是否审核通过
     */
    public function satisfyInformation()
    {
        $memberId = parent::getAccountId();

        $memberInfo = M("member")->where(array("id"=>$memberId))->find();

        if (0 == $memberInfo['identitychk']) {
            $this->response(-200, "请完善个人信息！", null);
        } else if (3 == $memberInfo['identitychk']) {
            $this->response(-201, "审核未通过，请重新上传！", null);
        } else if (2 == $memberInfo['identitychk']) {
            $this->response(-202, "个人信息审核中，暂不可进行此操作！", null);
        }
    }

    /**
     * 完善用户信息
     */
    public function completeInfo()
    {
        $memberId = parent::getAccountId();

        $realName = trim(I('post.realName')); //真实姓名
        $idCard = trim(I('post.idCard')); //身份证号
        $idCardFrontImg = trim(I('post.idCardFrontImg')); //身份证正面
        $idCardBackImg = trim(I('post.idCardBackImg')); //身份证反面
        $idCardHold = trim(I('post.idCardHold')); //身份证手持

        if (empty($realName) || 'undefined' == $realName) $this->ajaxError("请填写真实姓名！");
        if (empty($idCard) || 'undefined' == $idCard) $this->ajaxError("请填写身份证号！");
        if (!isIdentityCard($idCard)) $this->ajaxError("身份证号格式错误！");
        if (empty($idCardFrontImg) || 'undefined' == $idCardFrontImg) $this->ajaxError("请上传身份证正面照！");
        if (empty($idCardBackImg) || 'undefined' == $idCardBackImg) $this->ajaxError("请上传身份证反面照！");
        if (empty($idCardHold) || 'undefined' == $idCardHold) $this->ajaxError("请上传身份证手持照！");

        $res = M('member')->where(array("id"=>$memberId))->save(array(
            "realName" => $realName,
            "idCard" => $idCard,
            "idCardFrontImg" => $idCardFrontImg,
            "idCardBackImg" => $idCardBackImg,
            "idCardHold" => $idCardHold,
            "identityChk" => 2,
        ));

        if ($res) {
            $this->ajaxSuccess("上传成功，等待审核");
        } else {
            $this->ajaxError("上传失败！");
        }
    }

    /**
     * 签到
     */
    public function sign()
    {
        $memberId = parent::getAccountId();

        $begin = strtotime(date("Y-m-d 0:0:0", time()));
        $end = strtotime(date("Y-m-d 23:59:59", time()));

        //是否已签到
        $check = M('sign')->query("SELECT * FROM t_sign WHERE memberId='".$memberId."' AND unix_timestamp(`signDate`) > '".$begin."' AND unix_timestamp(`signDate`) <= '".$end."'");
        if ($check) {
            $this->ajaxError("您今天已经签到了！");
        }

        $res = M('sign')->add(array("memberId"=>$memberId, "signDate"=>now_datetime()));
        if ($res) {
            $this->ajaxSuccess("签到成功！");
        } else {
            $this->ajaxError("签到失败！");
        }
    }

    /**
     * 修改密码
     */
    public function changePasswd()
    {
        $memberId = parent::getAccountId();

        $memberInfo = M("member")->where(array("id"=>$memberId))->find();

        $old = trim(I('post.old'));
        $new1 = trim(I('post.new1'));
        $new2 = trim(I('post.new2'));

        if (empty($old)) $this->ajaxError("请输入原密码！");
        if (empty($new1)) $this->ajaxError("请输入新密码！");
        if (empty($new2)) $this->ajaxError("请输入确认密码！");
        if (strlen($new1) < 6) $this->ajaxError("密码长度最少为6位！");
        if (!isNumAndLetter($new1)) $this->ajaxError("请输入数字和字母的组合！");
        if($new1 != $new2) $this->ajaxError("密码跟确认密码不一致！");
        if (md5($old) != $memberInfo['password']) $this->ajaxError("原密码错误！");
        if (md5($new1) == $memberInfo['password']) $this->ajaxError("新旧密码一致！");

        $res = M("member")->where(array("id"=>$memberId))->save(array("password"=>md5($new1)));
        if ($res) {
            parent::set("account", null);
            $this->ajaxSuccess("密码重置成功！");
        } else {
            $this->ajaxError("密码重置失败！");
        }
    }

    /**
     * @return 关于
     */
    public function about()
    {
        $system=tpCache("vpay_spstem_share");
        $this->assign("system",htmlspecialchars_decode(base64_decode($system['content'])));
        return $this->fetch();
    }


    /**
     * 分享
     */
    public function share()
    {
        $memberId = parent::getAccountId();
        $member = M("member")->where(array("id"=>$memberId))->find();
//        $code = "http://" . $_SERVER['HTTP_HOST'] . "/index.php/Vpay/Register/register?invitecode=" . $member['invitecode'];
//        $erweimaurl = "http://qr.topscan.com/api.php?bg=ffffff&fg=000000&el=l&w=350&m=10&text=".$code;
//        $erweimaurl = imagecreatefromstring(file_get_contents($erweimaurl));
//        //$image_3 = imageCreatetruecolor(imagesx($beijing),imagesy($beijing));
//        //$color = imagecolorallocate($image_3, 255, 255, 255);
//        //imagefill($image_3, 0, 0, $color);
//        //imagecopyresampled($image_3,$beijing,0,0,0,0,imagesx($beijing),imagesy($beijing),imagesx($beijing),imagesy($beijing));
//        //imagecopyresampled($image_3,$erweimaurl,230,80,0,0,350,350,350,350);// 二维码位置
//        //加载第三方类库
        vendor('phpqrcode.phpqrcode');
        $url = "http://" . $_SERVER['HTTP_HOST'] . "/index.php/Vpay/Register/register?invitecode=" . $member['invitecode'];

        $after_path = 'public/qrcode/'.md5($url).'.png';
        //保存路径
        $path =  ROOT_PATH.$after_path;

        //判断是该文件是否存在
        if(!is_file($path))
        {
            //实例化
            $qr = new \QRcode();
            //1:url,3: 容错级别：L、M、Q、H,4:点的大小：1到10
            $qr::png($url,'./'.$after_path, "M", 6,TRUE);
        }

        $this->assign('qrcodeImg',request()->domain().'/'.$after_path);
        $system=tpCache("vpay_spstem");
        $this->assign("system",$system);
        $this->assign("code",$url."&share=1");
        return $this->fetch();
    }

    /**
     * 分享记录
     */
    public function share_detail()
    {
        if(IS_POST){
            $memberId = parent::getAccountId();
            $page = (int)I('p') ? (int)I('p') : 0;
            $list = 10;
            if(empty($memberId)){
                $this->ajaxError("未登录！");
            }
            $members = M('member')
                ->alias("t")
                ->field("t.*,b.*")
                ->join("vpay_level b","t.level=b.level_id",'LEFT')
                ->where(array("t.parentId"=>$memberId))
                ->limit($page * $list, $list)
                ->order("t.id desc")
                ->select();
//            foreach ($members as $k=>$v){
//                $members[$k]['account']=substr($v['account'], -6);
//            }
            $this->ajaxSuccess($members);
        }else{
            return $this->fetch();
        }
    }
    public function complaint(){
        return $this->fetch();
    }
    /**
     * 投诉建议
     */
    public function complaint_add()
    {
        $member= parent::getAccount();
        if(empty($member)){
            $this->redirect("Vpay/Login/login","未登录");
        }
        $message=I("post.message");
        $data=array(
            "account"=>$member['account'],
            "message"=>$message,
            "create_time"=>now_datetime()
        );
        $result=M("complaint")->add($data);
        if ($result) {
            $this->ajaxSuccess("保存成功！");
        } else {
            $this->ajaxError("保存失败！");
        }
    }
}