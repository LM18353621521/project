<?php

namespace app\common\logic;

use app\common\util\WechatUtil;
use think\Image;
use think\Cache;
use app\common\model\WxTplMsg;
use think\Db;
use think\Validate;
use app\common\model\WxKeyword;
use app\common\model\WxNews;
use app\common\model\WxMaterial;
use app\common\model\WxReply;
class WechatLogic
{
    private $wx_user;
    private $wechatObj;

    public function __construct()
    {
        $this->wx_user = M('wx_user')->find();
        if ($this->wx_user['wait_access'] == 0) {
            //file_put_contents('./c.html',print_r($_GET,true),FILE_APPEND);
            ob_clean();
            exit($_GET["echostr"]);
        }
        $this->wechatObj = new WechatUtil($this->wx_user);
    }

    /**
     * 处理接收推送消息
     */
    public function handleMessage()
    {
        $wechatObj = $this->wechatObj;
        $msg = $wechatObj->handleMessage();
        if (!$msg) {
            exit($wechatObj->getError());
        }

        if ($msg['MsgType'] == 'event') {
            if ($msg['Event'] == 'subscribe') {
                $ret = $this->handleSubscribeEvent($msg);//关注事件
                $this->ajaxReturn($ret);
            } elseif ($msg['Event'] == 'SCAN') {
                //已关注事件
            } elseif ($msg['Event'] == 'CLICK') {
                $this->handleClickEvent($msg);//点击事件
            }
        } elseif ($msg['MsgType'] == 'text') {
            $this->handleTextMsg($msg);//用户输入文本
        }

        $this->replyDefault($msg);
    }

    /**
     * 处理关注事件
     * @param array $msg
     * @return type
     */
    private function handleSubscribeEvent($msg)
    {
        $openid = $msg['FromUserName'];
        if (!$openid) {
            return ['status' => -1, 'msg' => "openid无效"];
        }

        if ($msg['MsgType'] != 'event' || $msg['Event'] != 'subscribe') {
            return ['status' => 1, 'msg' => "不是关注事件"];
        }

        $wechatObj = $this->wechatObj;
        if (!($user = M('users')->where('openid', $openid)->find())) {
            if (false === ($wxdata = $wechatObj->getFanInfo($openid))) {
                return ['status' => -1, 'msg' => $wechatObj->getError()];
            }
            $user = [
                'openid'    => $openid,
                'head_pic'  => $wxdata['headimgurl'],
                'nickname'  => $wxdata['nickname'] ?: '微信用户',
                'sex'       => $wxdata['sex'] ?: 0,
                'subscribe' => $wxdata['subscribe'],
                'oauth'     => 'weixin',
                'reg_time'  => time(),
                'token'     => md5(time().mt_rand(1,99999)),
                'password'  => '',
                'is_distribut' => 1,
            ];
            isset($wxdata['unionid']) && $user['unionid'] = $wxdata['unionid'];

            // 由场景值获取分销一级id
            if (!empty($msg['EventKey'])) {
                $user['first_leader'] = substr($msg['EventKey'], strlen('qrscene_'));
                if ($user['first_leader']) {
                    $first_leader = M('users')->where('user_id', $user['first_leader'])->find();
                    if ($first_leader) {
                        $user['second_leader'] = $first_leader['first_leader']; //  第一级推荐人
                        $user['third_leader'] = $first_leader['second_leader']; // 第二级推荐人
                        //他上线分销的下线人数要加1
                        M('users')->where('user_id', $user['first_leader'])->setInc('underling_number');
                        M('users')->where('user_id', $user['second_leader'])->setInc('underling_number');
                        M('users')->where('user_id', $user['third_leader'])->setInc('underling_number');
                    }
                } else {
                    $user['first_leader'] = 0;
                }
            }
            $is_bind_account = tpCache('basic.is_bind_account');
            if($is_bind_account && $user['first_leader']){
                //如果是绑定账号, 把first_leader保存到cookie
                setcookie('user_id', $user['first_leader'] ,null,'/');
            }else{
                $user_id = Db::name('users')->insertGetId($user);
                Db::name('oauth_users')->insert([
                    'user_id' => $user_id,
                    'openid' => $openid,
                    'unionid' => isset($wxdata['unionid']) ? $wxdata['unionid'] : '',
                    'oauth' => 'weixin',
                    'oauth_child' => 'mp',
                ]);
            }
            //$ret = M('users')->insert($user);
            if (!$user_id) {
                return ['status' => -1, 'msg' => "保存数据出错"];
            }
        }

        $this->replySubscribe($msg['ToUserName'], $openid);
        //$wechatObj->sendMsg($openid, "欢迎来到商城! 商城入口：".$_SERVER['HTTP_HOST'].'/mobile');
        exit;
    }
    /**
     * 关注时回复消息
     */
    private function replySubscribe($from, $to)
    {
        $wechatObj = $this->wechatObj;
        $result_str = $this->createReplyMsg($from, $to, WxReply::TYPE_FOLLOW);
        if ( ! $result_str) {
            //没有设置关注回复，则默认回复如下：
            $store_name = tpCache("shop_info.store_name");
            $result_str = $wechatObj->createReplyMsgOfText($from, $to, "欢迎来到 $store_name !\n商城入口：".SITE_URL.'/mobile');
        }

        exit($result_str);
    }
    /**
     * 创建回复消息
     * @param $from string 发送方
     * @param $to string 被发送方
     * @param $type string WxReply的类型
     * @param array $data 附加数据
     * @return string
     */
    private function createReplyMsg($from, $to, $type, $data = [])
    {
        $wechatObj = $this->wechatObj;
        if ($type != WxReply::TYPE_KEYWORD) {
            $reply = WxReply::get(['type' => $type]);
        } else {
            $wx_keyword = WxKeyword::get(['keyword' => $data['keyword'], 'type' => WxKeyword::TYPE_AUTO_REPLY], 'wxReply');
            $wx_keyword && $reply = $wx_keyword->wx_reply;
        }

        if ($data['keyword'] == 'keyw') {
            $reply['data'] = 1;
        }

        if (empty($reply)) {
            return '';
        }

        $resultStr = '';
        if ($reply->msg_type == WxReply::MSG_TEXT && $reply['data']) {
            $resultStr = $wechatObj->createReplyMsgOfText($from, $to, $reply['data']);
        } elseif ($reply->msg_type == WxReply::MSG_NEWS) {
            $resultStr = $this->createNewsReplyMsg($from, $to, $reply->material_id);
        } else {
            // 回复二维码推广链接
            if ($data['keyword'] == 'keyw') {
                $user = Db::name('oauth_users')->alias('ou')->where(['ou.openid' => $to])->join('__USERS__ u', 'u.user_id = ou.user_id', 'LEFT')->find();
                $user_id = $user['user_id'];
                if($user) {
                    //  粉丝无推广权限
                    if ($user['level'] == 1) {
                        $reply['data'] = '暂无推广权限';
                    } else {

                        $url = '/mobile/User/contactleader/id/'.$user_id.'.html';
                        $after_path = 'public/qrcode/'.md5($url).'.png';
                        $path =  ROOT_PATH.$after_path;
                        if(is_file($path)){

                            $poster = Db::name('poster')->where(['type' => 1])->find();
                            // 二维码设置不为空
                            if ($poster['qr_width']){
                                $config['image'][] = [
                                    'url'=>$path,     //二维码资源
                                    'left'=>$poster['qr_x'],
                                    'top'=>$poster['qr_y'],
                                    'stream'=>0,             //图片资源是否是字符串图像流
                                    'right'=>0,
                                    'bottom'=>0,
                                    'width'=>$poster['qr_width'],
                                    'height'=>$poster['qr_height'],
                                    'opacity'=>100
                                ];
                            }
                            // 头像设置不为空
                            if ($poster['head_width']){
                                $is_wx_header = strstr($user['head_pic'] ,'http');
                                if ($is_wx_header) {
                                    // 请求微信头像回来本地
                                    $wx_pic_path = curl_wx_pic($user['head_pic'],$user['user_id']);
                                    if ($wx_pic_path){
                                        Db::name('users')->where(['user_id' => $user['user_id']])->save(['head_pic' => $wx_pic_path]);
                                    }
                                    $head_pic  = 'http://'.$_SERVER['SERVER_NAME'].$wx_pic_path;
                                } else {
                                    $head_pic  = $user['head_pic'] ? 'http://'.$_SERVER['SERVER_NAME'].$user['head_pic'] : 'http://'.$_SERVER['SERVER_NAME'].'/template/mobile/new2/static/images/user68.jpg';
                                }
                                $config['image'][] = [
                                    'url'=> $head_pic,     //二维码资源
                                    'left'=>$poster['head_x'],
                                    'top'=>$poster['head_y'],
                                    'stream'=>0,             //图片资源是否是字符串图像流
                                    'right'=>0,
                                    'bottom'=>0,
                                    'width'=>$poster['head_width'],
                                    'height'=>$poster['head_height'],
                                    'opacity'=>100
                                ]   ;
                            }
                            // 昵称设置不为空
                            if ($poster['nk_width']){
                                if (!$poster['nk_font']){
                                    // 为空默认16
                                    $poster['nk_font'] = 16;
                                }
                                $config['text'] = [[
                                    'text'=>$user['nickname'],
                                    'left'=>$poster['nk_x'],
                                    'top'=>$poster['nk_y'],
                                    'fontPath'=>'public/ttf/simhei.ttf',     //字体文件
                                    'fontSize'=>$poster['nk_font'],             //字号
                                    'fontColor'=>$poster['nk_color'],        //字体颜色
                                    'angle'=>0,
                                ]];
                            }

                            $config['background'] =  request()->domain(). $poster['img'] ;
                            $after_path = 'public/qrcode/'.md5($user['user_id']).'.png';
                            $path =  ROOT_PATH.$after_path;
                            createPoster($config,$path);


                            $userPost = Db::name('wx_poster')->where(['user_id' => $user['user_id']])->find();
                                if ($userPost['add_time'] > (3600 * 24 *3) || !$userPost){  // 临时素材过期
                                    if ($userPost['id']) {
                                        Db::name('wx_poster')->where(['id' => $userPost['id']])->delete();
                                    }
                            //         // 上传临时素材
                                    $userPost = $wechatObj->uploadTempMaterial($path);
                                    $data = [
                                        'user_id' =>$user_id,
                                        'media_id' =>$userPost['media_id'],
                                        'add_time' =>time()
                                    ];
                                    Db::name('wx_poster')->insert($data);
                                }



                            $reply = $wechatObj->createReplyMsgOfImage($from, $to, $userPost['media_id']);
                            exit($reply);

                        } else {
                            $reply['data'] = '请先进个人中心拥有自己专属推广码';
                        }
                    }
                }else {
                    $reply['data'] = '请加入我们，方可推广';
                }
                $resultStr = $wechatObj->createReplyMsgOfText($from, $to, $reply['data']);
            }

            //扩展其他类型，如image，voice等
        }
        return $resultStr;
    }

    private function uploadImg($imgUrl){
        $wechatObj = $this->wechatObj;
        $access_token = $wechatObj->getAccessToken();
        $wx_url="https://api.weixin.qq.com/cgi-bin/material/add_material?access_token={$access_token}&type=image";
        $img_data = array('media'=>'@'.$imgUrl);
        $result = httpRequest($wx_url,'POST', $img_data);
        $data = @json_decode($result,true);

        return $data['media_id'];
    }

    /**
     * 创建图文回复消息
     */
    private function createNewsReplyMsg($fromUser, $toUser, $material_id)
    {
        $wechatObj = $this->wechatObj;
        $material = WxMaterial::get(['id' => $material_id, 'type' => WxMaterial::TYPE_NEWS], 'wxNews');
        if (!$material || !$material->wx_news) {
            return '';
        }

        $articles = [];
        foreach ($material->wx_news as $news) {
            $articles[] = [
                'title'       => $news->title,
                'description' => $news->digest ?: $news->content_digest,
                'picurl'      => SITE_URL . $news->thumb_url,
                'url'         => SITE_URL . url('/mobile/article/news', ['id' => $news->id])
            ];
        }

        return $wechatObj->createReplyMsgOfNews($fromUser, $toUser, $articles);
    }
    /**
     * 处理点击事件
     * @param type $msg
     */
    private function handleClickEvent($msg)
    {
        $eventKey = $msg['EventKey'];
        $distribut = tpCache('distribut');

        //分销二维码图片
        if ($distribut['qrcode_menu_word'] && $eventKey == $distribut['qrcode_menu_word']) {
            $this->replyMyQrcode($msg);
        }

        //其他处理
        $this->handleTextMsg($msg);
    }

    /**
     * 回复我的二维码
     */
    private function replyMyQrcode($msg)
    {
        $fromUsername = $msg['FromUserName'];
        $toUsername   = $msg['ToUserName'];
        $wechatObj = $this->wechatObj;

        if (!($user = M('users')->where('openid', $fromUsername)->find())) {
            $content = '请进入商城: '.SITE_URL.' , 再获取二维码哦';
            $reply = $wechatObj->createReplyMsgOfText($toUsername, $fromUsername, $content);
            exit($reply);
        }

        //获取缓存的图片id
        $distribut = tpCache('distribut');
        $mediaId = $this->getCacheQrcodeMedia($user['user_id'], $user['head_pic'], $distribut['qr_big_back']);
        if (!$mediaId) {
            $mediaId = $this->createQrcodeMedia($msg, $user['user_id'], $user['head_pic'], $distribut['qr_big_back']);
        }

        //回复图片消息
        $reply = $wechatObj->createReplyMsgOfImage($toUsername, $fromUsername, $mediaId);
        exit($reply);
    }

    private function createQrcodeMedia($msg, $userId, $headPic, $qrBackImg)
    {
        $wechatObj = $this->wechatObj;

        //创建二维码关注url
        $qrCode = $wechatObj->createTempQrcode(2592000, $userId);
        if (!(is_array($qrCode) && $qrCode['url'])) {
            $this->replyError($msg, '创建二维码失败');
        }

        //创建分销二维码图片
        $shareImg = $this->createShareQrCode('.'.$qrBackImg, $qrCode['url'], $headPic);
        if (!$shareImg) {
            $this->replyError($msg, '生成图片失败');
        }

        //上传二维码图片
        if (!($mediaInfo = $wechatObj->uploadTempMaterial($shareImg, 'image'))) {
            @unlink($shareImg);
            $this->replyError($msg, '上传图片失败');
        }
        @unlink($shareImg);

        $this->setCacheQrcodeMedia($userId, $headPic, $qrBackImg, $mediaInfo);

        return $mediaInfo['media_id'];
    }

    private function getCacheQrcodeMedia($userId, $headPic, $qrBackImg)
    {
        $symbol = md5("{$headPic}:{$qrBackImg}");
        $mediaIdCache = "distributQrCode:{$userId}:{$symbol}";
        $config = cache($mediaIdCache);
        if (!$config) {
            return false;
        }

        //$config = json_decode($config);
        //有效期3天（259200s）,提前5小时(18000s)过期
        if (!(is_array($config) && $config['media_id'] && ($config['created_at'] + 259200 - 18000) > time())) {
            return false;
        }

        return $config['media_id'];
    }

    private function setCacheQrcodeMedia($userId, $headPic, $qrBackImg, $mediaInfo)
    {
        $symbol = md5("{$headPic}:{$qrBackImg}");
        $mediaIdCache = "distributQrCode:{$userId}:{$symbol}";
        cache($mediaIdCache, $mediaInfo);
    }

    /**
     * 处理点击推送事件
     * @param array $msg
     */
    private function handleTextMsg($msg)
    {
        $fromUsername = $msg['FromUserName'];
        $toUsername   = $msg['ToUserName'];
        $keyword      = trim($msg['Content']);

        //点击菜单拉取消息时的事件推送
        /*
         * 1、click：点击推事件
         * 用户点击click类型按钮后，微信服务器会通过消息接口推送消息类型为event的结构给开发者（参考消息接口指南）
         * 并且带上按钮中开发者填写的key值，开发者可以通过自定义的key值与用户进行交互；
         */
        if ($msg['MsgType'] == 'event' && $msg['Event'] == 'CLICK') {
            $keyword = trim($msg['EventKey']);
        }

        if (empty($keyword)) {
            return false;
        }

        //分销二维码图片
        $distribut = tpCache('distribut');
        if ($distribut['qrcode_input_word'] && $distribut['qrcode_input_word'] == $msg['Content']) {
            $this->replyMyQrcode($msg);
        }
        // 关键字自动回复
        $this->replyKeyword($toUsername, $fromUsername, $keyword);
    }
    /**
     * 关键字自动回复
     * @param $from
     * @param $to
     * @param $keyword
     */
    private function replyKeyword($from, $to, $keyword)
    {
        $msg = array();
        $msg['FromUserName'] = $to;//用户openid
        $msg['ToUserName']   = $from;
        if (!$keyword) {
            $this->replyDefault($msg);
        }

        $resultStr = $this->createReplyMsg($from, $to, WxReply::TYPE_KEYWORD, ['keyword' => $keyword]);
        //  file_put_contents("./wechat.log", date('Y-m-d H:i:s').' -- '.$from."\n".$to, FILE_APPEND);
        if ($resultStr) {
            exit($resultStr);
        } else {
            $this->replyDefault($msg);
        }
    }
    /**
     * 默认回复
     * @param type $msg
     */
    private function replyDefault($msg)
    {
        $fromUsername = $msg['FromUserName'];
        $toUsername   = $msg['ToUserName'];
//        $content = '欢迎来到商城 !';
//        $resultStr = $this->wechatObj->createReplyMsgOfText($toUsername, $fromUsername, $content);
//        exit($resultStr);
        $resultStr = $this->createReplyMsg($toUsername, $fromUsername, WxReply::TYPE_DEFAULT);
        if ( ! $resultStr) {
            //没有设置默认回复，则默认回复如下：
            $store_name = tpCache("shop_info.store_name");
            $resultStr = $this->wechatObj->createReplyMsgOfText($toUsername, $fromUsername, "欢迎来到 $store_name !");
        }

        exit($resultStr);
    }

    private function replyError($msg, $extraMsg = '')
    {
        $fromUsername = $msg['FromUserName'];
        $toUsername   = $msg['ToUserName'];
        $wechatObj = $this->wechatObj;

        if ($wechatObj->isDedug()) {
            $content = '错误信息：';
            $content .= $wechatObj->getError() ?: '';
            $content .= $extraMsg ?: '';
        } elseif ($extraMsg) {
            $content = '系统信息：'.$extraMsg;
        } else {
            $content = '系统正在处理...';
        }

        $resultStr = $wechatObj->createReplyMsgOfText($toUsername, $fromUsername, $content);
        exit($resultStr);
    }

    /**
     * 创建分享二维码图片
     * @param type $backImg 背景大图片
     * @param type $qrText  二维码文本:关注入口
     * @param type $headPic 头像路径
     * @return 图片路径
     */
    private function createShareQrCode($backImg, $qrText, $headPic)
    {
        if (!is_file($backImg) || !$headPic || !$qrText) {
            return false;
        }

        vendor('phpqrcode.phpqrcode');
        vendor('topthink.think-image.src.Image');

        $qr_code_path = './public/upload/qr_code/';
        !file_exists($qr_code_path) && mkdir($qr_code_path, 0777, true);

        /* 生成二维码 */
        $qr_code_file = $qr_code_path.time().rand(1, 10000).'.png';
        \QRcode::png($qrText, $qr_code_file, QR_ECLEVEL_M);

        $QR = Image::open($qr_code_file);
        $QR_width = $QR->width();
        $QR_height = $QR->height();


        /* 添加背景图 */
        if ($backImg && is_file($backImg)) {
            $back =Image::open($backImg);
            $backWidth = $back->width();
            $backHeight = $back->height();

            //生成的图片大小以540*960为准
            if ($backWidth <= $backHeight) {
                $refWidth = 540;
                $refHeight = 960;
                if (($backWidth / $backHeight) > ($refWidth / $refHeight)) {
                    $backRatio = $refWidth / $backWidth;
                    $backWidth = $refWidth;
                    $backHeight = $backHeight * $backRatio;
                } else {
                    $backRatio = $refHeight / $backHeight;
                    $backHeight = $refHeight;
                    $backWidth = $backWidth * $backRatio;
                }
            } else {
                $refWidth = 960;
                $refHeight = 540;
                if (($backWidth / $backHeight) > ($refWidth / $refHeight)) {
                    $backRatio = $refHeight / $backHeight;
                    $backHeight = $refHeight;
                    $backWidth = $backWidth * $backRatio;
                } else {
                    $backRatio = $refWidth / $backWidth;
                    $backWidth = $refWidth;
                    $backHeight = $backHeight * $backRatio;
                }
            }

            $shortSize = $backWidth > $backHeight ? $backHeight : $backWidth;
            $QR_width = $shortSize / 2;
            $QR_height = $QR_width;
            $QR->thumb($QR_width, $QR_height, \think\Image::THUMB_CENTER)->save($qr_code_file, null, 100);
            $back->thumb($backWidth, $backHeight, \think\Image::THUMB_CENTER)
                ->water($qr_code_file, \think\Image::WATER_CENTER, 90)->save($qr_code_file, null, 100);
            $QR = $back;
        }

        /* 添加头像 */
        if ($headPic) {
            //如果是网络头像
            if (strpos($headPic, 'http') === 0) {
                //下载头像
                $ch = curl_init();
                curl_setopt($ch,CURLOPT_URL, $headPic);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                $file_content = curl_exec($ch);
                curl_close($ch);
                //保存头像
                if ($file_content) {
                    $head_pic_path = $qr_code_path.time().rand(1, 10000).'.png';
                    file_put_contents($head_pic_path, $file_content);
                    $headPic = $head_pic_path;
                }
            }
            //如果是本地头像
            if (file_exists($headPic)) {
                $logo = Image::open($headPic);
                $logo_width = $logo->height();
                $logo_height = $logo->width();
                $logo_qr_width = $QR_width / 5;
                $scale = $logo_width / $logo_qr_width;
                $logo_qr_height = $logo_height / $scale;
                $logo_file = $qr_code_path.time().rand(1, 10000);
                $logo->thumb($logo_qr_width, $logo_qr_height)->save($logo_file, null, 100);
                $QR = $QR->water($logo_file, \think\Image::WATER_CENTER);
                unlink($logo_file);
            }
            if ($head_pic_path) {
                unlink($head_pic_path);
            }
        }

        //加上有效时间
        $valid_date = date('Y.m.d', strtotime('+30 days'));
        $QR = $QR->text('有效时间 '.$valid_date, "./vendor/topthink/think-captcha/assets/zhttfs/1.ttf", 16, '#FFFFFF', Image::WATER_SOUTH)->save($qr_code_file);

        return $qr_code_file;
    }
    /**
     * 获取粉丝列表
     */
    public function getFanList($p, $num = 10)
    {
        $wechatObj = $this->wechatObj;
        if (!$access_token = $wechatObj->getAccessToken()) {
            return ['status' => -1, 'msg' => $wechatObj->getError()];
        }
        $p = intval($p) > 0 ? intval($p) : 1;
        $offset = ($p - 1) * $num;
        $max = 10000; //粉丝列表每次只能拉取的数量

        /* 获取所有粉丝列表openid并缓存 */
        $fans_key = 'wechat.fan_list';
        if (!$fans = Cache::get($fans_key)) {
            $next_openid = '';
            $fans = [];
            do {
                $ids = $wechatObj->getFanIdList($next_openid);
                if ($ids === false) {
                    return ['status' => -1, 'msg' => $wechatObj->getError()];
                }
                $fans = array_merge($fans, $ids['data']['openid']);
                $next_openid = $ids['next_openid'];
            } while ($ids['total'] > $max && $ids['count'] == $max);
            Cache::set($fans_key, $fans, 3600); //缓存列表一个小时
        }

        /* 获取指定粉丝，并获取详细信息 */
        $part_fans = array_slice($fans, $offset, $num);
        $user_list = [];
        $fan_key = 'wechat.fan_info';
        foreach ($part_fans as $openid) {
            if (!$fan = Cache::get($fan_key.'.'.$openid)) {
                $fan = $wechatObj->getFanInfo($openid, $access_token);
                if ($fan === false) {
                    continue;//不要因为一个粉丝的离开而影响整个列表
                }
                $fan['tags'] = $wechatObj->getFanTagNames($fan['tagid_list']);
                if ($fan['tags'] === false) {
                    continue;//不要因为一个粉丝的离开而影响整个列表
                }
                Cache::set($fan_key.'.'.$openid, $fan, 3600); //缓存粉丝一个小时
            }
            $user_list[$openid] = $fan;
        }

        return ['status' => 1, 'msg' => '获取成功', 'result' => [
            'total' => count($fans),
            'list' => $user_list
        ]];
    }

    /**
     * 商城用户里的粉丝列表
     */
    public function getUserFanList($p, $num = 10, $keyword= '')
    {
        $wechatObj = $this->wechatObj;
        if (!$access_token = $wechatObj->getAccessToken()) {
            return ['status' => -1, 'msg' => $wechatObj->getError()];
        }

        $p = intval($p) > 0 ? intval($p) : 1;
        $condition = ['o.openid' => ['<>', ''], 'o.oauth' => 'weixin', 'o.oauth_child' => 'mp'];
        $keyword = trim($keyword);
        $keyword && $condition['o.openid|u.nickname'] = ['like', "%$keyword%"];

        $query = Db::name('oauth_users')->field('o.*')->alias('o')->join('__USERS__ u', 'u.user_id = o.user_id')->where($condition);
        $copyQuery = clone $query;
        $users = $query->page($p, $num)->select();
        $user_num = $copyQuery->count();

        $fan_key = 'wechat.user_fan_info';
        foreach ($users as &$user) {
            if (!$fan = Cache::get($fan_key.'.'.$user['openid'])) {
                $fan = $wechatObj->getFanInfo($user['openid'], $access_token);
                if ($fan === false) {
                    continue;//不要因为一个粉丝的离开而影响整个列表
                }
                Cache::set($fan_key.'.'.$user['openid'], $fan, 3600); //缓存粉丝一个小时
            }
            $user['weixin'] = $fan;
        }

        return ['status' => 1, 'msg' => '获取成功', 'result' => [
            'total' => $user_num,
            'list' => $users
        ]];
    }
    /**
     * 系统默认模板消息
     * @return array
     */
    public function getDefaultTemplateMsg($template_sn = null)
    {
        $templates = [
            [
                "template_sn" => "OPENTM202425185",
                "title" => "订单提交成功通知",
                "content" =>
                    "{{first.DATA}}\n\n"
                    ."订单编号：{{keyword1.DATA}}\n"
                    ."下单时间：{{keyword2.DATA}}\n"
                    ."订单金额：{{keyword3.DATA}}\n"
                    ."优惠金额：{{keyword4.DATA}}\n"
                    ."{{remark.DATA}}",
            ], [
                "template_sn" => "OPENTM200444326",
                "title" => "订单付款成功通知",
                "content" =>
                    "{{first.DATA}}\n"
                    ."订单：{{keyword1.DATA}}\n"
                    ."支付状态：{{keyword2.DATA}}\n"
                    ."支付日期：{{keyword3.DATA}}\n"
                    ."商户：{{keyword4.DATA}}\n"
                    ."金额：{{keyword5.DATA}}\n"
                    ."{{remark.DATA}}",
            ], [
                "template_sn" => "OPENTM201541214",
                "title" => "订单发货通知",
                "content" =>
                    "{{first.DATA}}\n"
                    ."订单内容：{{keyword1.DATA}}\n"
                    ."物流服务：{{keyword2.DATA}}\n"
                    ."快递单号：{{keyword3.DATA}}\n"
                    ."收货信息：{{keyword4.DATA}}\n"
                    ."{{remark.DATA}}",
            ], [
                "template_sn" => "OPENTM207665174",
                "title" => "成为会员通知",
                "content" =>
                    "{{first.DATA}}\n"
                    ."微信昵称：{{keyword1.DATA}}\n"
                    ."会员等级：{{keyword2.DATA}}\n"
                    ."时间：{{keyword3.DATA}}\n"
                    ."{{remark.DATA}}",
            ], [
                "template_sn" => "TM00756",
                "title" => "服务预约派单通知",
                "content" =>
                    "{{first.DATA}}\n"
                    ."订单内容：{{keyword1.DATA}}\n"
                    ."物流服务：{{keyword2.DATA}}\n"
                    ."快递单号：{{keyword3.DATA}}\n"
                    ."收货信息：{{keyword4.DATA}}\n"
                    ."{{remark.DATA}}",
            ], [
                "template_sn" => "OPENTM206960204",
                "title" => "加盟受理通知",
                "content" =>
                    "{{first.DATA}}\n"
                    ."订单内容：{{keyword1.DATA}}\n"
                    ."物流服务：{{keyword2.DATA}}\n"
                    ."快递单号：{{keyword3.DATA}}\n"
                    ."收货信息：{{keyword4.DATA}}\n"
                    ."{{remark.DATA}}",
            ], [
                "template_sn" => "OPENTM412076205",
                "title" => "加盟成功通知",
                "content" =>
                    "{{first.DATA}}\n"
                    ."订单内容：{{keyword1.DATA}}\n"
                    ."物流服务：{{keyword2.DATA}}\n"
                    ."快递单号：{{keyword3.DATA}}\n"
                    ."收货信息：{{keyword4.DATA}}\n"
                    ."{{remark.DATA}}",
            ], [
                "template_sn" => "OPENTM401833445",
                "title" => "余额变动提示",
                "content" =>
                    "{{first.DATA}}\n"
                    ."变动时间：{{keyword1.DATA}}\n"
                    ."变动类型：{{keyword2.DATA}}\n"
                    ."变动金额：{{keyword3.DATA}}\n"
                    ."当前余额：{{keyword4.DATA}}\n"
                    ."{{remark.DATA}}",
            ], [
                "template_sn" => "OPENTM207126233",
                "title" => "分销商申请成功",
                "content" =>
                    "{{first.DATA}}\n"
                    ."分销商名称：{{keyword1.DATA}}\n"
                    ."分销商电话：{{keyword2.DATA}}\n"
                    ."申请时间：{{keyword3.DATA}}\n"
                    ."{{remark.DATA}}",
            ], [
                "template_sn" => "OPENTM201812627",
                "title" => "佣金提醒",
                "content" =>
                    "{{first.DATA}}\n"
                    ."佣金金额：{{keyword1.DATA}}\n"
                    ."时间：{{keyword2.DATA}}\n"
                    ."{{remark.DATA}}",
            ], [
                "template_sn" => "OPENTM407307456",
                "title" => "开团成功通知",
                "content" =>
                    "{{first.DATA}}\n"
                    ."商品名称：{{keyword1.DATA}}\n"
                    ."商品价格：{{keyword2.DATA}}\n"
                    ."组团人数：{{keyword3.DATA}}\n"
                    ."拼团类型：{{keyword4.DATA}}\n"
                    ."组团时间：{{keyword5.DATA}}\n"
                    ."{{remark.DATA}}",
            ], [
                "template_sn" => "OPENTM400048581",
                "title" => "参团成功通知",
                "content" =>
                    "{{first.DATA}}\n"
                    ."拼团名：{{keyword1.DATA}}\n"
                    ."拼团价：{{keyword2.DATA}}\n"
                    ."有效期：{{keyword3.DATA}}\n"
                    ."{{remark.DATA}}",
            ], [
                "template_sn" => "OPENTM407456411",
                "title" => "拼团成功通知",
                "content" =>
                    "{{first.DATA}}\n"
                    ."订单编号：{{keyword1.DATA}}\n"
                    ."团购商品：{{keyword2.DATA}}\n"
                    ."{{remark.DATA}}",
            ], [
                "template_sn" => "OPENTM400940587",
                "title" => "拼团退款提醒",
                "content" =>
                    "{{first.DATA}}\n"
                    ."单号：{{keyword1.DATA}}\n"
                    ."商品：{{keyword2.DATA}}\n"
                    ."原因：{{keyword3.DATA}}\n"
                    ."退款：{{keyword4.DATA}}\n"
                    ."{{remark.DATA}}",
            ]
        ];

        $templates = convert_arr_key($templates, 'template_sn');

        $valid_sns = [
            'OPENTM202425185',
            'OPENTM200444326',
            'OPENTM201541214',
            'OPENTM207665174',
            'TM00756',
            'OPENTM206960204',
            'OPENTM412076205',
        ]; //目前支持的模板
        $valid_templates = [];
        foreach ($valid_sns as $sn) {
            if (isset($templates[$sn])) {
                $valid_templates[$sn] = $templates[$sn];
            }
        }

        if ($template_sn) {
            return $valid_templates[$template_sn];
        }
        return $valid_templates;
    }
    /**
     * 新建和更新文本素材
     * （图文素材只需保存在本地，微信不存储文本素材）
     */
    public function createOrUpdateText($material_id, $data)
    {
        $validate = new Validate([
            ['title','require|max:64','标题必填|标题最多64字'],
            ['content','require|max:600','内容必填|内容最多600字'],
        ]);
        if (!$validate->check($data)) {
            return ['status' => -1, 'msg' => $validate->getError()];
        }

        $text = [
            'type' => 'text',
            'update_time' => time(),
            'data' => [
                'title' => $data['title'],
                'content' => $data['content'],
            ]
        ];

        if ($material_id) {
            if (!$material = WxMaterial::get(['id' => $material_id, 'type' => WxMaterial::TYPE_TEXT])) {
                return ['status' => -1, 'msg' => '文本素材不存在'];
            }
            $material->save($text);
        } else {
            $material = WxMaterial::create($text);
        }

        return ['status' => 1, 'msg' => '素材提交成功！', 'result' => $material->id];
    }

    /**
     * 删除文本素材
     */
    public function deleteText($material_id)
    {
        if (!$material_id || !$material = WxMaterial::get(['id' => $material_id, 'type' => WxMaterial::TYPE_TEXT])) {
            return ['status' => -1, 'msg' => '文本素材不存在'];
        }

        $material->delete();

        return ['status' => 1, 'msg' => '删除文本素材成功'];
    }

    /**
     * 新建和更新图文素材
     */
    public function createOrUpdateNews($material_id, $news_id, $data)
    {
        $article = [
            "title"             => $data['title'],
            //"thumb_media_id"    => $data['thumb_media_id'],
            "thumb_url"         => $data['thumb_url'],
            "author"            => $data['author'],
            "digest"            => $data['digest'],
            "show_cover_pic"    => $data['show_cover_pic'] ? 1 : 0,
            "content"           => $data['content'],
            "content_source_url" => $data['content_source_url'],
            "material_id"       => $material_id,
            "update_time"       => time(),
        ];

        if ($material_id) {
            if (!$material = WxMaterial::get(['id' => $material_id, 'type' => WxMaterial::TYPE_NEWS])) {
                return ['status' => -1, 'msg' => '图文素材不存在'];
            }

            if ($news_id) {
                //更新单图文
                if (!$news = WxNews::get(['id' => $news_id, 'material_id' => $material_id])) {
                    return ['status' => -1, 'msg' => '单图文素材不存在'];
                }
                $news->save($article);
                if ($material->media_id) {
                    $material->save(['media_id' => 0]); // 需要重新上传
                }

            } else {
                //新增单图文
                $all_news = WxNews::all(['material_id' => $material_id]);
                $max_news_per_material = 8;
                if (count($all_news) >= $max_news_per_material) {
                    return ['status' => -1, 'msg' => "一个图文素材中的文章最多 $max_news_per_material 篇"];
                }
                WxNews::create($article);
            }
            $material->save([
                'update_time' => time(),
                'media_id' => 0 // 需要重新上传
            ]);

        } else {
            //新增多图文
            $material = WxMaterial::create([
                'type' => WxMaterial::TYPE_NEWS,
                'update_time' => time(),
            ]);
            $article['material_id'] = $material->id;
            WxNews::create($article);
        }

        //先不用上传到微信服务器，等实际使用的时候再上传

        return ['status' => 1, 'msg' => '素材提交成功！'];
    }

    /**
     * 删除图文素材
     * @param $material_id int 素材id
     * @return array
     */
    public function deleteNews($material_id)
    {
        if (!$material_id || !$material = WxMaterial::get(['id' => $material_id, 'type' => WxMaterial::TYPE_NEWS], 'wxNews')) {
            return ['status' => -1, 'msg' => '素材不存在'];
        }

        if (WxReply::get(['material_id' => $material_id, 'msg_type' => WxReply::MSG_NEWS])) {
            return ['status' => -1, 'msg' => '该素材正被自动回复使用，无法删除'];
        }

        if ($material->media_id) {
            $this->wechatObj->delMaterial($material->media_id);
        }

        if (is_array($material->wx_news)) {
            foreach ($material->wx_news as $news) {
                $news->delete();
            }
        }
        $material->delete();

        return ['status' => 1, 'msg' => '删除图文成功'];
    }

    /**
     * 删除单图文
     * @param $news_id int 单图文的id
     * @return array
     */
    public function deleteSingleNews($news_id)
    {
        if (!$news_id || !$news = WxNews::get($news_id, 'wxMaterial')) {
            return ['status' => -1, 'msg' => '单图文素材不存在'];
        }

        if (!$news->wx_material) {
            return ['status' => -1, 'msg' => '该单图文所属素材不存在'];
        }

        if (count($news->wx_material->wx_news) == 1) {
            return $this->deleteNews($news->material_id);
        } else {
            if ($news->wx_material->media_id) {
                $news->wx_material->save(['media_id' => 0]); // 需要重新上传
            }
            $news->delete();
        }

        return ['status' => 1, 'msg' => '删除单图文成功'];
    }

    /**
     * 上传图文
     * @param $material WxMaterial
     * @return array
     */
    private function uploadNews($material)
    {
        $articles = [];
        foreach ($material->wx_news as $news) {
            // 1.获取或上传封面
            if ($thumb = WxMaterial::get(['type' => WxMaterial::TYPE_IMAGE, 'key' => md5($news['thumb_url'])])) {
                $thumb_media_id = $thumb->media_id;
            } else {
                $thumb = $this->wechatObj->uploadMaterial('.'.$news['thumb_url'], 'image');
                if ($thumb ===  false) {
                    return ['status' => -1, 'msg' => $this->wechatObj->getError()];
                }
                $thumb_media_id = $thumb['media_id'];
                WxMaterial::create([
                    'type' => WxMaterial::TYPE_IMAGE,
                    'key'  => md5($news['thumb_url']),
                    'media_id' => $thumb_media_id,
                    'update_time' => time(),
                    'data' => [
                        'url' => $news['thumb_url'],
                        'mp_url' => $thumb['url'],
                    ]
                ]);
            }

            // 2.将文章中的图片上传
            $news['content'] = htmlspecialchars_decode($news['content']);
            if (preg_match_all('#<img .*?src="(.*?)".*?/>#i', $news['content'], $matches)) {
                $imgs = array_unique($matches[1]);
                foreach ($imgs as $img) {
                    if (stripos($img, 'http') === 0) {
                        continue;
                    }

                    // 3.获取或上传文章中图片
                    if ($news_image = WxMaterial::get(['type' => WxMaterial::TYPE_NEWS_IMAGE, 'key' => md5($img)])) {
                        $news_image_url = $news_image->data['mp_url'];
                    } else {
                        $news_image_url = $this->wechatObj->uploadNewsImage('.'.$img);
                        if ($news_image_url ===  false) {
                            return ['status' => -1, 'msg' => $this->wechatObj->getError()];
                        }
                        WxMaterial::create([
                            'type' => WxMaterial::TYPE_NEWS_IMAGE,
                            'key'  => md5($img),
                            'update_time' => time(),
                            'data' => [
                                'url' => $news['thumb_url'],
                                'mp_url' => $news_image_url,
                            ]
                        ]);
                    }

                    $news['content'] = str_replace($img, $news_image_url, $news['content']);
                }
            }

            $articles[] = [
                "title"             => $news['title'],
                "thumb_media_id"    => $thumb_media_id,
                "author"            => $news['author'] ?: '',
                "digest"            => $news['digest'] ?: '',
                "show_cover_pic"    => $news['show_cover_pic'] ? 1 : 0,
                "content"           => $news['content'],
                "content_source_url" => $news['content_source_url'],
            ];
        }

        $news_media_id = $this->wechatObj->uploadNews($articles);
        if ($news_media_id ===  false) {
            return ['status' => -1, 'msg' => $this->wechatObj->getError()];
        }
        $material->save(['media_id' => $news_media_id]);

        return ['status' => 1, 'msg' => '上传成功', 'result' => $news_media_id];
    }

    /**
     * 发送图文消息
     * @param $material_id int 素材id
     * @param $openids array|string 可多个用户openid
     * @param int $to_all 0由openids决定，1所有粉丝
     * @return array
     */
    public function sendNewsMsg($material_id, $openids, $to_all = 0)
    {
        $material = WxMaterial::get(['id' => $material_id, 'type' => WxMaterial::TYPE_NEWS], 'wxNews');
        if (!$material || !$material->wx_news) {
            return ['status' => -1, 'msg' => '该素材不存在'];
        }

        if ($material->media_id) {
            $news_media_id = $material->media_id;
            if (false === $this->wechatObj->getMaterial($material->media_id)) {
                $news_media_id = 0; //获取失败，可能被手动删了，需要重新上传
            }
        }
        if (empty($news_media_id)) {
            $return = $this->uploadNews($material);
            if ($return['status'] != 1) {
                return $return;
            }
            $news_media_id = $return['result'];
        }

        // 5.发送消息
        if ($to_all) {
            $result = $this->wechatObj->sendMsgToAll(0, 'mpnews', $news_media_id);
        } else {
            $result = $this->wechatObj->sendMsg($openids, 'mpnews', $news_media_id);
        }
        if ($result === false) {
            return ['status' => -1, 'msg' => $this->wechatObj->getError()];
        }

        return ['status' => 1, 'msg' => '发送成功'];
    }
    /**
     * 配置模板
     * @param $data array 配置
     */
    public function setTemplateMsg($template_sn, $data)
    {
        if (!isset($data['is_use']) && !isset($data['remark'])) {
            return ['status' => -1, 'msg' => '参数为空'];
        }

        $tpls = $this->getDefaultTemplateMsg();
        if (!key_exists($template_sn, $tpls)) {
            return ['status' => -1, 'msg' => "模板消息的编号[$template_sn]不存在"];
        }

        if ($tpl_msg = WxTplMsg::get(['template_sn' => $template_sn])) {
            $tpl_msg->save($data);
        } else {
            if (!$template_id = $this->wechatObj->addTemplateMsg($template_sn)) {
                return ['status' => -1, 'msg' => $this->wechatObj->getError()];
            }
            WxTplMsg::create([
                'template_id' => $template_id,
                'template_sn' => $template_sn,
                'title' => $tpls[$template_sn]['title'],
                'is_use' => isset($data['is_use']) ? $data['is_use'] : 0,
                'remark' => isset($data['remark']) ? $data['remark'] : '',
                'add_time' => time(),
            ]);
        }

        return ['status' => 1, 'msg' => '操作成功'];
    }

    /**
     * 重置模板消息
     */
    public function resetTemplateMsg($template_sn)
    {
        if (!$template_sn) {
            return ['status' => -1, 'msg' => '参数不为空'];
        }

        if ($tpl_msg = WxTplMsg::get(['template_sn' => $template_sn])) {
            if ($tpl_msg->template_id) {
                $this->wechatObj->delTemplateMsg($tpl_msg->template_id);
            }
            $tpl_msg->delete();
        }

        return ['status' => 1, 'msg' => '操作成功'];
    }
    /**
     * 发送模板消息（创建订单通知）
     * @param $order array 订单数据
     */
    public function sendTemplateMsgOnPayPlace($order)
    {
        if ( ! $order) {
            return ['status' => -1, 'msg' => '订单不存在'];
        }

        $template_sn = 'OPENTM202425185';
        if ( ! $this->getDefaultTemplateMsg($template_sn)) {
            return ['status' => -1, 'msg' => '消息模板不存在'];
        }

        $tpl_msg = WxTplMsg::get(['template_sn' => $template_sn, 'is_use' => 1]);
        if ( ! $tpl_msg || ! $tpl_msg->template_id) {
            return ['status' => -1, 'msg' => '消息模板未开启'];
        }

        $user = Db::name('oauth_users')->where(['user_id' => $order['user_id'], 'oauth' => 'weixin', 'oauth_child' => 'mp'])->find();
        if ( ! $user || ! $user['openid']) {
            return ['status' => -1, 'msg' => '用户不存在或不是微信用户'];
        }

        $store_name = tpCache('shop_info.store_name');
        $data = [
            'first' => ['value' => '你刚刚下了一笔订单！'],
            'keyword1' => ['value' => $order['order_sn']],
            'keyword2' => ['value' => date('Y-m-d H:i', $order['add_time'])],
            'keyword3' => ['value' => $order['order_amount']."元"],
            'keyword4' => ['value' => ($order['total_amount']-$order['order_amount'])],
            'remark' => ['value' => $tpl_msg->remark ? $tpl_msg->remark : ''],
        ];

        $url = SITE_URL.url('/mobile/order/order_detail?id='.$order['order_id']);
        $return = $this->wechatObj->sendTemplateMsg($user['openid'], $tpl_msg->template_id, $url, $data);
        if ($return === false) {
            return ['status' => -1, 'msg' => $this->wechatObj->getError()];
        }

        return ['status' => 1, 'msg' => '发送模板消息成功'];
    }

    /**
     * 发送推送消息（下级关注通知）
     * @param $order array 订单数据
     */
    public function sendTemplateMsgBindLeader($parent_id,$user_id)
    {
        if ( ! $parent_id || !$user_id) {
            return ['status' => -1, 'msg' => '参数异常'];
        }

        $user = Db::name('oauth_users')->where(['user_id' => $parent_id, 'oauth' => 'weixin', 'oauth_child' => 'mp'])->find();
        if ( ! $user || ! $user['openid']) {
            return ['status' => -1, 'msg' => '用户不存在或不是微信用户'];
        }

        $nickname = Db::name('users')->where(['user_id' => $user_id])->value('nickname');
        $content = Db::name('wx_template')->where(['type' => 1])->value('content');
        $content = explode('$nickname',$content);
        $content = $content[0].$nickname.$content[1];
        $content = explode('$rn',$content);
        $content = $content[0]."\n\r".$content[1];
        $content = explode('$add_time',$content);
        $content = $content[0].date('Y-m-d H:i:s',time());

        $return = $this->wechatObj->sendMsgToOne($user['openid'], 'text', $content);
        if ($return === false) {
            return ['status' => -1, 'msg' => $this->wechatObj->getError()];
        }

        return ['status' => 1, 'msg' => '发送消息成功'];
    }

    /**
     * 发送推送消息（升级身份发送）
     * @param $order array 订单数据
     */
    public function sendTemplateUpdateLevel($uid,$update_level)
    {
        if ( ! $uid || !$update_level) {
            return ['status' => -1, 'msg' => '参数异常'];
        }
        $userInfo = Db::name('users')->field('nickname,first_leader')->where(['user_id' => $uid])->find();

        if ( ! $userInfo) {
            return ['status' => -1, 'msg' => '用户参数异常'];
        }
        $level_name = Db::name('user_level')->where(['level_id' => $update_level])->value('level_name');


        $first_leader_openid = Db::name('oauth_users')->where(['user_id' => $userInfo['first_leader'], 'oauth' => 'weixin', 'oauth_child' => 'mp'])->find();
        $user_openid= Db::name('oauth_users')->where(['user_id' => $uid, 'oauth' => 'weixin', 'oauth_child' => 'mp'])->find();

        if ($user_openid) { // 如果有自己的Openid
            $content = Db::name('wx_template')->where(['type' => 3])->value('content');

            if (!$content){
                return ['status' => -1, 'msg' => '模板消息异常'];
            }

            $content = str_replace('$rn', "\n", $content);
            $content = str_replace('$level', $level_name, $content);
            $content = str_replace('$add_time', date('Y-m-d H:i:s',time()), $content);

            $return1 = $this->wechatObj->sendMsgToOne($user_openid['openid'], 'text', $content);
        }

        if ($first_leader_openid) { // 如果有上级的Openid
            $content = Db::name('wx_template')->where(['type' => 4])->value('content');

            if (!$content){
                return ['status' => -1, 'msg' => '模板消息异常'];
            }

            $content = str_replace('$rn', "\n", $content);
            $content = str_replace('$nickname', $userInfo['nickname'], $content);
            $content = str_replace('$level', $level_name, $content);
            $content = str_replace('$add_time', date('Y-m-d H:i:s',time()), $content);

            $return2 = $this->wechatObj->sendMsgToOne($first_leader_openid['openid'], 'text', $content);
        }


        if ($return1 === false || $return2) {
            return ['status' => -1, 'msg' => $this->wechatObj->getError()];
        }

        return ['status' => 1, 'msg' => '发送消息成功'];
    }

    /**
     * 发送推送消息（下级购买通知）
     * @param $order array 订单数据
     */
    public function sendTemplateMsgBuyOrder($order)
    {
        if ( ! $order) {
            return ['status' => -1, 'msg' => '参数异常'];
        }
        $userInfo = Db::name('users')->field('nickname,first_leader')->where(['user_id' => $order['user_id']])->find();

        if ( ! $userInfo || ! $userInfo['first_leader']) {
            return ['status' => -1, 'msg' => '用户参数异常'];
        }


        $user = Db::name('oauth_users')->where(['user_id' => $userInfo['first_leader'], 'oauth' => 'weixin', 'oauth_child' => 'mp'])->find();
        if ( ! $user || ! $user['openid']) {
            return ['status' => -1, 'msg' => '用户不存在或不是微信用户'];
        }

        $content = Db::name('wx_template')->where(['type' => 2])->value('content');
        $goods_name = Db::name('order_goods')->where(['goods_id' => $order['order_id']])->value('goods_name');
        $content = explode('$nickname',$content);
        $content = $content[0].$userInfo['nickname'].$content[1];

        $content = explode('$goods',$content);
        $content = $content[0].$goods_name.$content[1];

        $content = explode('$rn',$content);
        $content = $content[0]."\n\r".$content[1];

        $content = explode('$order_sn',$content);
        $content = $content[0].$order['order_sn'].$content[1];
        $content = explode('$distribut_money',$content);
        $distribut_money = Db::name('rebate_log')->where(['order_id'=>$order['order_id'],'user_id'=>$userInfo['first_leader']])->value('money');
        if (!$distribut_money) {
            return  ['status' => -1, 'msg' => '无分佣金额记录'];
        }

        $content = $content[0].$distribut_money.$content[1];

        $content = explode('$add_time',$content);
        $content = $content[0].date('Y-m-d H:i:s',time());

        $return = $this->wechatObj->sendMsgToOne($user['openid'], 'text', $content);
        if ($return === false) {
            return ['status' => -1, 'msg' => $this->wechatObj->getError()];
        }

        return ['status' => 1, 'msg' => '发送消息成功'];
    }

    /**
     * 发送推送消息（下级购买通知）
     * @param $order array 订单数据
     */
    public function sendTemplateMsgPrize($uid,$prize,$money)
    {
        if ( ! $uid) {
            return ['status' => -1, 'msg' => '参数异常'];
        }
        $userInfo = Db::name('users')->field('nickname,first_leader')->where(['user_id' => $uid])->find();

        if ( ! $userInfo) {
            return ['status' => -1, 'msg' => '用户参数异常'];
        }


        $user = Db::name('oauth_users')->where(['user_id' => $uid, 'oauth' => 'weixin', 'oauth_child' => 'mp'])->find();
        if ( ! $user || ! $user['openid']) {
            return ['status' => -1, 'msg' => '用户不存在或不是微信用户'];
        }

        $content = Db::name('wx_template')->where(['type' => 5])->value('content');
        if (!$content){
            return ['status' => -1, 'msg' => '模板消息异常'];
        }

        $content = str_replace('$rn', "\n", $content);
        $content = str_replace('$prize', $prize, $content);
        $content = str_replace('$money', $money, $content);
        $content = str_replace('$add_time', date('Y-m-d H:i:s',time()), $content);

        $return = $this->wechatObj->sendMsgToOne($user['openid'], 'text', $content);
        if ($return === false) {
            return ['status' => -1, 'msg' => $this->wechatObj->getError()];
        }

        return ['status' => 1, 'msg' => '发送消息成功'];
    }

    /**
     * 发送模板消息（订单支付成功通知）
     * @param $order array 订单数据
     */
    public function sendTemplateMsgOnPaySuccess($order)
    {
        if ( ! $order) {
            return ['status' => -1, 'msg' => '订单不存在'];
        }

        $template_sn = 'OPENTM204987032';
        if ( ! $this->getDefaultTemplateMsg($template_sn)) {
            return ['status' => -1, 'msg' => '消息模板不存在'];
        }

        $tpl_msg = WxTplMsg::get(['template_sn' => $template_sn, 'is_use' => 1]);
        if ( ! $tpl_msg || ! $tpl_msg->template_id) {
            return ['status' => -1, 'msg' => '消息模板未开启'];
        }

        $user = Db::name('oauth_users')->where(['user_id' => $order['user_id'], 'oauth' => 'weixin', 'oauth_child' => 'mp'])->find();
        if ( ! $user || ! $user['openid']) {
            return ['status' => -1, 'msg' => '用户不存在或不是微信用户'];
        }

        $store_name = tpCache('shop_info.store_name');
        $data = [
            'first' => ['value' => '订单支付成功！'],
            'keyword1' => ['value' => $order['order_sn']],
            'keyword2' => ['value' => '已支付'],
            'keyword3' => ['value' => date('Y-m-d H:i', $order['pay_time'])],
            'keyword4' => ['value' => $store_name],
            'keyword5' => ['value' => $order['order_amount']."元"],
            'remark' => ['value' => $tpl_msg->remark ? $tpl_msg->remark : ''],
        ];

        $url = SITE_URL.url('/mobile/order/order_detail?id='.$order['order_id']);
        $return = $this->wechatObj->sendTemplateMsg($user['openid'], $tpl_msg->template_id, $url, $data);
        if ($return === false) {
            return ['status' => -1, 'msg' => $this->wechatObj->getError()];
        }

        return ['status' => 1, 'msg' => '发送模板消息成功'];
    }

    /**
     * 发送模板消息（订单发货通知）
     * @param $deliver array 物流信息
     */
    public function sendTemplateMsgOnDeliver($deliver)
    {
        if ( ! $deliver) {
            return ['status' => -1, 'msg' => '订单物流不存在'];
        }

        $template_sn = 'OPENTM202243318';
        if ( ! $this->getDefaultTemplateMsg($template_sn)) {
            return ['status' => -1, 'msg' => '消息模板不存在'];
        }

        $tpl_msg = WxTplMsg::get(['template_sn' => $template_sn, 'is_use' => 1]);
        if ( ! $tpl_msg || ! $tpl_msg->template_id) {
            return ['status' => -1, 'msg' => '消息模板未开启'];
        }

        $user = Db::name('oauth_users')->where(['user_id' => $deliver['user_id'], 'oauth' => 'weixin', 'oauth_child' => 'mp'])->find();
        if ( ! $user || ! $user['openid']) {
            return ['status' => -1, 'msg' => '用户不存在或不是微信用户'];
        }

        // 收货地址
        $province = getRegionName($deliver['province']);
        $city = getRegionName($deliver['city']);
        $district = getRegionName($deliver['district']);
        $full_address = $province.' '.$city.' '.$district.' '. $deliver['address'];

        $order_goods = Db::name('order_goods')->where('order_id', $deliver['order_id'])->find();
        $data = [
            'first' => ['value' => "订单{$deliver['order_sn']}发货成功！"],
            'keyword1' => ['value' => $order_goods['goods_name']],
            'keyword2' => ['value' => $deliver['shipping_name']],
            'keyword3' => ['value' => $deliver['delivery_sn']],
            'keyword4' => ['value' => $full_address],
            'remark' => ['value' => $tpl_msg->remark ?: ''],
        ];

        $url = SITE_URL.url('/mobile/order/order_detail?id='.$deliver['order_id']);
        $return = $this->wechatObj->sendTemplateMsg($user['openid'], $tpl_msg->template_id, $url, $data);
        if ($return === false) {
            return ['status' => -1, 'msg' => $this->wechatObj->getError()];
        }

        return ['status' => 1, 'msg' => '发送模板消息成功'];
    }

    /**
     * 发送模板消息（服务订单下单成功通知）
     * @param $deliver array 物流信息
     */
    public function sendTemplateMsgServiceOrderAdd($deliver)
    {
        if ( ! $deliver) {
            return ['status' => -1, 'msg' => '订单物流不存在'];
        }

        $template_sn = 'OPENTM202425185';
        if ( ! $this->getDefaultTemplateMsg($template_sn)) {
            return ['status' => -1, 'msg' => '消息模板不存在'];
        }

        $tpl_msg = WxTplMsg::get(['template_sn' => $template_sn, 'is_use' => 1]);
        if ( ! $tpl_msg || ! $tpl_msg->template_id) {
            return ['status' => -1, 'msg' => '消息模板未开启'];
        }

        $user = Db::name('oauth_users')->where(['user_id' => $deliver['user_id'], 'oauth' => 'weixin', 'oauth_child' => 'mp'])->find();
        if ( ! $user || ! $user['openid']) {
            return ['status' => -1, 'msg' => '用户不存在或不是微信用户'];
        }


        $order_goods = Db::name('repair_order')->where('order_id', $deliver['order_id'])->find();
        $data = [
            'first' => ['value' => "您刚下了一笔维修服务订单"],
            'keyword1' => ['value' => $order_goods['order_sn']],
            'keyword2' => ['value' => date('Y-m-d H:i', $order_goods['add_time'])],
            'keyword3' => ['value' => $order_goods['paid_price']."元"],
            'keyword4' => ['value' => 0],
            'remark' => ['value' => $tpl_msg->remark ? $tpl_msg->remark : ''],
        ];

        $url = SITE_URL.url('/mobile/order/server_order_detail?order_id='.$deliver['order_id']);
        $return = $this->wechatObj->sendTemplateMsg($user['openid'], $tpl_msg->template_id, $url, $data);
        if ($return === false) {
            return ['status' => -1, 'msg' => $this->wechatObj->getError()];
        }

        return ['status' => 1, 'msg' => '发送模板消息成功'];
    }

    /**
     * 发送模板消息（服务订单付款成功通知）
     * @param $deliver array 物流信息
     */
    public function sendTemplateMsgServiceOrderPay($order)
    {
        if ( ! $order) {
            return ['status' => -1, 'msg' => '订单物流不存在'];
        }

        $template_sn = 'OPENTM200444326';
        if ( ! $this->getDefaultTemplateMsg($template_sn)) {
            return ['status' => -1, 'msg' => '消息模板不存在'];
        }

        $tpl_msg = WxTplMsg::get(['template_sn' => $template_sn, 'is_use' => 1]);
        if ( ! $tpl_msg || ! $tpl_msg->template_id) {
            return ['status' => -1, 'msg' => '消息模板未开启'];
        }

        $user = Db::name('oauth_users')->where(['user_id' => $order['user_id'], 'oauth' => 'weixin', 'oauth_child' => 'mp'])->find();
        if ( ! $user || ! $user['openid']) {
            return ['status' => -1, 'msg' => '用户不存在或不是微信用户'];
        }

        $data = [
            'first' => ['value' => "订单{$order['order_sn']}付款成功！"],
            'keyword1' => ['value' => $order['order_sn']],
            'keyword2' => ['value' =>  date('Y-m-d H:i', $order['add_time'])],
            'keyword3' => ['value' => $order['paid_price']."元"],
            'keyword4' => ['value' => $order['pay_name']],
            'remark' => ['value' => $tpl_msg->remark ? $tpl_msg->remark : ''],
        ];

        $url = SITE_URL.url('/mobile/order/server_order_detail?order_id='.$order['order_id']);
        $return = $this->wechatObj->sendTemplateMsg($user['openid'], $tpl_msg->template_id, $url, $data);
        if ($return === false) {
            return ['status' => -1, 'msg' => $this->wechatObj->getError()];
        }

        return ['status' => 1, 'msg' => '发送模板消息成功'];
    }

    /**
     * 图片插件中展示的列表
     * @param $size int 拉取多少
     * @param $start int 开始位置
     * @return string
     */
    public function getPluginImages($size, $start = 0)
    {
        $data = $this->wechatObj->getMaterialList('image', $size * $start, $size);
        if ($data === false) {
            return json_encode([
                "state" => $this->wechatObj->getError(),
                "list" => [],
                "start" => $start,
                "total" => 0
            ]);
        }

        $list = [];
        foreach ($data['item'] as $item) {
            $list[] = [
                'url' => $item['url'],
                'mtime' => $item['update_time'],
                'name' => $item['name'],
            ];
        }

        return json_encode([
            "state" => "no match file",
            "list" => $list,
            "start" => $start,
            "total" => $data['total_count']
        ]);
    }

    /**
     * 修正关键字
     * @param $keywords
     * @return array
     */
    private function trimKeywords($keywords)
    {
        $keywords = explode(',', $keywords);
        $keywords = array_map('trim', $keywords);
        $keywords = array_unique($keywords);
        foreach ($keywords as $k => $keyword) {
            if (!$keyword) {
                unset($keywords[$k]);
            }
        }

        return array_values($keywords);
    }

    /**
     * 更新关键字
     * @param $reply_id int 回复id
     * @param $wx_keywords WxKeyword[]
     * @param $keywords array 关键字数组
     */
    private function updateKeywords($reply_id, $wx_keywords, $keywords)
    {
        $wx_keywords = convert_arr_key($wx_keywords, 'keyword');

        //先删除不存在的keyword
        foreach ($wx_keywords as $key => $word) {
            if (!in_array($key, $keywords)) {
                $word->delete();
                unset($wx_keywords[$key]);
            }
        }
        //创建要设置的keyword
        foreach ($keywords as $keyword) {
            if (!isset($wx_keywords[$keyword])) {
                WxKeyword::create([
                    'keyword' => $keyword,
                    'pid' => $reply_id,
                    'type' => WxKeyword::TYPE_AUTO_REPLY
                ]);
            }
        }
    }

    /**
     * 检查文本自动回复表单
     */
    private function checkTextAutoReplyForm(&$data)
    {
        if ($data['type'] == WxReply::TYPE_KEYWORD) {
            $rules = [
                ['type', 'require', '回复类型必需'],
                ['keywords','require','关键词必填'],
                ['rule','require|max:32','规则名必填|规则名最多32字'],
                ['content','require|max:600','文本内容必填|文本内容最多600字'],
            ];
        } else {
            $rules = [
                ['type', 'require', '回复类型必需'],
                ['content','max:600','文本内容最多600字'],
            ];
        }
        $validate = new Validate($rules);
        if (!$validate->check($data)) {
            return ['status' => -1, 'msg' => $validate->getError()];
        }

        if ( ! key_exists($data['type'], WxReply::getAllType())) {
            return ['status' => -1, 'msg' => '回复类型不存在'];
        }

        if ($data['type'] == WxReply::TYPE_KEYWORD) {
            if (!$data['keywords'] = $this->trimKeywords($data['keywords'])) {
                return ['status' => -1, 'msg' => '关键字不存在'];
            }
        }

        return ['status' => 1, 'msg' => '检查成功'];
    }

    /**
     * 添加文本自动回复
     */
    public function addTextAutoReply($data)
    {
        $return = $this->checkTextAutoReplyForm($data);
        if ($return['status'] != 1) {
            return $return;
        }

        if ($data['type'] == WxReply::TYPE_KEYWORD) {
            if (WxKeyword::get(['keyword' => ['in', $data['keywords']], 'type' => WxKeyword::TYPE_AUTO_REPLY])) {
                return ['status' => -1, 'msg' => '有关键字被其他规则使用'];
            }
        }

        $reply = WxReply::create([
            'rule' => $data['rule'],
            'update_time' => time(),
            'type' => $data['type'],
            'msg_type' => WxReply::MSG_TEXT,
            'data' => $data['content'],
        ]);

        if ($data['type'] == WxReply::TYPE_KEYWORD) {
            foreach ($data['keywords'] as $keyword) {
                WxKeyword::create([
                    'keyword' => $keyword,
                    'pid' => $reply->id,
                    'type' => WxKeyword::TYPE_AUTO_REPLY
                ]);
            }
        }

        return ['status' => 1, 'msg' => '添加成功'];
    }

    /**
     * 更新文本自动回复
     * @param $reply_id int 回复id
     * @param $data array
     * @return array
     */
    public function updateTextAutoReply($reply_id, $data)
    {
        $return = $this->checkTextAutoReplyForm($data);
        if ($return['status'] != 1) {
            return $return;
        }

        $with = ($data['type'] == WxReply::TYPE_KEYWORD) ? 'wxKeywords' : [];
        if (!$reply = WxReply::get(['id' => $reply_id], $with)) {
            return ['status' => -1, 'msg' => '该自动回复不存在'];
        }

        if ($data['type'] == WxReply::TYPE_KEYWORD) {
            $keyword_ids = get_arr_column($reply->wx_keywords, 'id');
            if (WxKeyword::all(['keyword' => ['in', $data['keywords']], 'type' => WxKeyword::TYPE_AUTO_REPLY, 'id' => ['not in', $keyword_ids]])) {
                return ['status' => -1, 'msg' => '有关键字被其他规则使用'];
            }

            $this->updateKeywords($reply_id, $reply->wx_keywords, $data['keywords']);
        }

        $reply->save([
            'rule' => $data['rule'],
            'update_time' => time(),
            'data' => $data['content'],
            'material_id' => 0,
            'msg_type' => WxReply::MSG_TEXT
        ]);

        return ['status' => 1, 'msg' => '更新成功'];
    }

    /**
     * 检查文本自动回复表单
     */
    private function checkNewsAutoReplyForm(&$data)
    {
        if ($data['type'] == WxReply::TYPE_KEYWORD) {
            $rules = [
                ['keywords','require','关键词必填'],
                ['rule','require|max:32','规则名必填|规则名最多32字'],
                ['type', 'require', '回复类型必需'],
                ['material_id','require','关联素材id必需'],
            ];
        } else {
            $rules = [
                ['type', 'require', '回复类型必需'],
                ['material_id','require','关联素材id必需'],
            ];
        }
        $validate = new Validate($rules);
        if (!$validate->check($data)) {
            return ['status' => -1, 'msg' => $validate->getError()];
        }

        if ($data['type'] == WxReply::TYPE_KEYWORD) {
            if (!$data['keywords'] = $this->trimKeywords($data['keywords'])) {
                return ['status' => -1, 'msg' => '关键字不存在'];
            }
        }

        if (!WxMaterial::get(['id' => $data['material_id'], 'type' => WxMaterial::TYPE_NEWS])) {
            return ['status' => -1, 'msg' => '关联图文素材不存在'];
        }

        return ['status' => 1, 'msg' => '检查成功'];
    }

    /**
     * 新增图文自动回复
     */
    public function addNewsAutoReply($data)
    {
        $return = $this->checkNewsAutoReplyForm($data);
        if ($return['status'] != 1) {
            return $return;
        }

        if ($data['type'] == WxReply::TYPE_KEYWORD) {
            if (WxKeyword::get(['keyword' => ['in', $data['keywords']], 'type' => WxKeyword::TYPE_AUTO_REPLY])) {
                return ['status' => -1, 'msg' => '有关键字被其他规则使用'];
            }
        }

        $reply = WxReply::create([
            'rule' => $data['rule'],
            'update_time' => time(),
            'type' => $data['type'],
            'msg_type' => WxReply::MSG_NEWS,
            'material_id' => $data['material_id'],
        ]);

        if ($data['type'] == WxReply::TYPE_KEYWORD) {
            foreach ($data['keywords'] as $keyword) {
                WxKeyword::create([
                    'keyword' => $keyword,
                    'pid' => $reply->id,
                    'type' => WxKeyword::TYPE_AUTO_REPLY
                ]);
            }
        }

        return ['status' => 1, 'msg' => '添加成功'];
    }

    /**
     * 更新图文自动回复
     * @param $reply_id int 回复id
     * @param $data array
     * @return array
     */
    public function updateNewsAutoReply($reply_id, $data)
    {
        $return = $this->checkNewsAutoReplyForm($data);
        if ($return['status'] != 1) {
            return $return;
        }

        $with = ($data['type'] == WxReply::TYPE_KEYWORD) ? 'wxKeywords' : [];
        if (!$reply = WxReply::get(['id' => $reply_id], $with)) {
            return ['status' => -1, 'msg' => '该自动回复不存在'];
        }

        if ($data['type'] == WxReply::TYPE_KEYWORD) {
            $keyword_ids = get_arr_column($reply->wx_keywords, 'id');
            if (WxKeyword::all(['keyword' => ['in', $data['keywords']], 'type' => WxKeyword::TYPE_AUTO_REPLY, 'id' => ['not in', $keyword_ids]])) {
                return ['status' => -1, 'msg' => '有关键字被其他规则使用'];
            }

            $this->updateKeywords($reply_id, $reply->wx_keywords, $data['keywords']);
        }

        $reply->save([
            'rule' => $data['rule'],
            'update_time' => time(),
            'material_id' => $data['material_id'],
            'msg_type' => WxReply::MSG_NEWS,
            'data' => '',
        ]);

        return ['status' => 1, 'msg' => '更新成功'];
    }

    /**
     * 添加自动回复
     */
    public function addAutoReply($type, $data)
    {
        if ($type == 'text') {
            return $this->addTextAutoReply($data);
        } elseif ($type == 'news') {
            return $this->addNewsAutoReply($data);
        } else {
            return ['status' => -1, 'msg' => '自动回复类型不存在'];
        }
    }

    /**
     * 更新自动回复
     */
    public function updateAutoReply($type, $reply_id, $data)
    {
        if ($type == 'text') {
            return $this->updateTextAutoReply($reply_id, $data);
        } elseif ($type == 'news') {
            return $this->updateNewsAutoReply($reply_id, $data);
        } else {
            return ['status' => -1, 'msg' => '自动回复类型不存在'];
        }
    }

    /**
     * 删除自动回复
     */
    public function deleteAutoReply($reply_id)
    {
        if (!$reply = WxReply::get(['id' => $reply_id])) {
            return ['status' => -1, 'msg' => '该自动回复不存在'];
        }

        if ($reply->type == WxReply::TYPE_KEYWORD) {
            WxKeyword::where(['pid' => $reply_id])->delete();
        }

        $reply->delete();

        return ['status' => 1, 'msg' => '删除成功'];
    }

    /**
     * 企业付款到零钱
     * @return [type] [description]
     */
    public function sendMoney($money, $opend_id, $no)
    {
        $data=array(
            'mch_appid'=>'wxbf3d7f838d6a1921',//商户账号appid
            'mchid'=> 1509756351,//商户号
            'nonce_str'=>createnoncestr(),//随机字符串
            'partner_trade_no'=> $no,//商户订单号
            'openid'=> $opend_id,//用户openid
            'check_name'=>'NO_CHECK',//校验用户姓名选项,
            // 're_user_name'=> $check_name,//收款用户姓名
            'amount'=>$money,//金额
            'desc'=> '樊澳媞提现',//企业付款描述信息
            'spbill_create_ip'=> '113.108.141.170',//Ip地址
        );

        //生成签名算法
        $secrect_key='wxbf3d7f838d6a1921wxbf3d7f838d6a';///这个就是个API密码。MD5 32位。
        $data=array_filter($data);
        ksort($data);
        $str='';
        foreach($data as $k=>$v) {
            $str.=$k.'='.$v.'&';
        }

        $str.='key='.$secrect_key;
        $data['sign']=md5($str);

        $xml=arraytoxml($data);
        $url='https://api.mch.weixin.qq.com/mmpaymkttransfers/promotion/transfers'; //调用接口

        $res = $this->curl_post_ssl($url,$xml);
        $return = $this->xmltoarray($res);
        $responseObj = simplexml_load_string($res, 'SimpleXMLElement', LIBXML_NOCDATA);
        $jsonStr = json_encode($responseObj);
        $resArr = json_decode($jsonStr,true);
//        dump($resArr);die;
        return $resArr;
    }

    /**
     * [curl_post_ssl 发送curl_post数据]
     * @param [type] $url  [发送地址]
     * @param [type] $xmldata [发送文件格式]
     * @param [type] $second [设置执行最长秒数]
     * @param [type] $aHeader [设置头部]
     * @return [type]   [description]
     */
     function curl_post_ssl($url, $xmldata, $second = 30, $aHeader = array()){
        $isdir =  $_SERVER['DOCUMENT_ROOT']."/public/cert/";//证书位置;绝对路径


        $ch = curl_init();//初始化curl

        curl_setopt($ch, CURLOPT_TIMEOUT, $second);//设置执行最长秒数
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_URL, $url);//抓取指定网页
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);// 终止从服务端进行验证
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);//
        curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');//证书类型
        curl_setopt($ch, CURLOPT_SSLCERT, $isdir . 'apiclient_cert.pem');//证书位置
        curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');//CURLOPT_SSLKEY中规定的私钥的加密类型
        curl_setopt($ch, CURLOPT_SSLKEY, $isdir . 'apiclient_key.pem');//证书位置
        curl_setopt($ch, CURLOPT_CAINFO, 'PEM');
        curl_setopt($ch, CURLOPT_CAINFO, $isdir . 'rootca.pem');
        if (count($aHeader) >= 1) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $aHeader);//设置头部
        }
        curl_setopt($ch, CURLOPT_POST, 1);//post提交方式
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xmldata);//全部数据使用HTTP协议中的"POST"操作来发送

        $data = curl_exec($ch);//执行回话
        if ($data) {
            curl_close($ch);
            return $data;
        } else {
            $error = curl_errno($ch);
            echo "call faild, errorCode:$error\n";
            curl_close($ch);
            return false;
        }
     }

        /**
         * [xmltoarray xml格式转换为数组]
         * @param [type] $xml [xml]
         * @return [type]  [xml 转化为array]
         */
         function xmltoarray($xml) { 
          //禁止引用外部xml实体 
          libxml_disable_entity_loader(true); 
          $xmlstring = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA); 
          $val = json_decode(json_encode($xmlstring),true); 
          return $val;
         }
}