<?php
/**评论维修类
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/6/13
 * Time: 9:39
 */

namespace  app\admin\controller\Evaluate;

use  app\admin\controller\Base;
use think\Page;
use think\Verify;
use think\Db;
use think\Session;
use think\AjaxPage;

class Evaluate extends Base
{

      /**
       * 维修评论页头部
       */
      public function index()
      {
            return $this->fetch();
      }

      /**
       * 维修评论数据显示
       */
      public function ajaxindex()
      {
            $model = M('RepairEvaluate');
            $username = I('nickname', '', 'trim');
            $content = I('content', '', 'trim');
            $is_show = I('is_show', '', 'trim');
            $is_anonymous = I('is_anonymous', '', 'trim');

            if ($username) {
                  $where['username'] = $username;
            }

            if ($is_show != '2') {//echo 2222;die;
                 $where['is_show'] = $is_show;
            }

            if ($is_anonymous != '2') {//echo 2222;die;
                $where['is_anonymous'] = $is_anonymous;
            }
            if ($content) {
                  $where['content'] = ['like', '%' . $content . '%'];
            }
            $count = $model->where($where)->count();
            $Page = $pager = new AjaxPage($count, 16);
            $show = $Page->show();
            $comment_list = M('RepairEvaluate')
                ->field('e.*,o.order_sn')
                ->alias('e')
                ->join('__REPAIR_ORDER__ o', 'e.order_id =o.order_id')
                ->order('add_time DESC')
                ->limit($Page->firstRow . ',' . $Page->listRows)
                ->select();
            $this->assign('comment_list', $comment_list);
            $this->assign('page', $show);// 赋值分页输出
            $this->assign('pager', $pager);// 赋值分页输出
            return $this->fetch();
      }

      /***
       *评论操作函数
       */
       public function evaluateHandle()
       {

            $type = I('post.type');
            $ids = I('post.ids', '');
            if (!in_array($type, array('del', 'show', 'hide')) || empty($ids)) {
                  $this->ajaxReturn(['status' => -1, 'msg' => '非法操作！']);
            }
            $comment_ids = rtrim($ids, ",");
            $row = false;

             //删除内容
            if ($type == 'del') {
                 $row = $row = M('RepairEvaluate')->where('evaluate_id', 'IN', $comment_ids)->delete();
            }

             //显示隐藏控制
            if ($type == 'show') {
                  $row = M('RepairEvaluate')->where('evaluate_id', 'IN', $comment_ids)->save(['is_show' => 1]);
            }
            if ($type == 'hide') {
                  $row = M('RepairEvaluate')->where('evaluate_id', 'IN', $comment_ids)->save(['is_show' => 0]);
            }

             //提示刷新
            if ($row !== false) {
                  $this->ajaxReturn(['status' => 1, 'msg' => '操作完成', 'url' => U('Admin/Evaluate.Evaluate/index')]);
            } else {
                  $this->ajaxReturn(['status' => -1, 'msg' => '操作失败', 'url' => U('Admin/Evaluate.Evaluate/index')]);
            }
      }

      /***
      *评论详情
      */
      public function detail(){
            $id = I('get.id/d');
            $res = M('RepairEvaluate')
                ->field('e.*,o.order_sn')
                ->alias('e')
                ->join('__REPAIR_ORDER__ o','e.order_id= o.order_id')
                ->where(array('evaluate_id'=>$id))
                ->find();

            $showimg = unserialize($res['image']);



            if(!$res){
                  exit($this->error('不存在该评论'));
            }

            $res['user_rank']=$res['user_rank'] == 5 ? 5 : array('ev'=>$res['user_rank'],'ek'=>5-$res['user_rank']);
            $res['order_rank']=$res['order_rank'] == 5 ? 5 : array('ev'=>$res['order_rank'],'ek'=>5-$res['order_rank']);

//
//            if(IS_POST){
//                  $add['parent_id'] = $id;
//                  $add['content'] = trim(I('post.content'));
//                  $add['goods_id'] = $res['goods_id'];
//                  $add['add_time'] = time();
//                  $add['username'] = 'admin';
//
//
//                  $add['is_show'] = 1;
//                  empty($add['content']) && $this->error('请填写回复内容');
//                  $row =  M('comment')->add($add);
//                  if($row){
//                        $this->success('添加成功');
//                  }else{
//                        $this->error('添加失败');
//                  }
//                  exit;
//
//            }

           // $reply = M('comment')->where(array('parent_id'=>$id))->select(); // 评论回复列表
          // $this->assign('reply',$reply);
            $this->assign('images',$showimg);
            $this->assign('comment',$res);

            return $this->fetch();
      }

}
