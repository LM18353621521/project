<?php

namespace app\admin\controller\Wechat;
use app\admin\controller\Base;
use think\Controller;

class Template extends Base {

    public  $type;

    public function _initialize() {
        parent::_initialize();

        // 短信使用场景
        $this->type = C('SEND_MSG');
        $this->assign('type', $this->type);

    }

    public function index(){

        $smsTpls = M('wx_template')->select();
		$this->assign('smsTplList',$smsTpls);

        return $this->fetch("template_list");

    }

    /**
     * 添加修改编辑  短信模板
     */
    public  function addEditMsgTemplate(){

        $id = I('id/d');
        $model = M("wx_template");

        if(IS_POST)
        {
            $data = I('post.');
            $data['add_time'] = time();
            //echo "add_time : ".$model->add_time;
            //exit;
            if($id){
                $model->update($data);
            }else{
                $id = $model->save($data);
            }
            $this->success("操作成功!!!",U('Admin/Wechat.Template/index'));
            exit;
        }

        if($id){
            //进入编辑页面
            $smsTemplate = $model->where("id" , $id)->find();
            $this->assign("smsTpl" , $smsTemplate );
            $sceneName = $this->type[$smsTemplate['type']][0];
            $sendscene = $smsTemplate['type'];
            $this->assign("send_name" , $sceneName );
            $this->assign("type_id" , $sendscene );
        }else{
            //进入添加页面
            //查找已经添加了的短信模板
            $scenes = $model->getField("type" , true);
            $filterSendscene = array();
            //过滤已经添加过滤的短信模板
            foreach ($this->type as $key => $value){
                if(!in_array($key, $scenes)){
                    $filterSendscene[$key] = $value;
                }
            }
        }


        $this->assign("type" , $filterSendscene );
        return $this->fetch("_msg_template");
    }

    /**
     * 删除订单
     */
   public function delTemplate(){

       $model = M("wx_template");
       $row = $model->where('id ='.$_GET['id'])->delete();
       $return_arr = array();
       if ($row){
           $return_arr = array('status' => 1,'msg' => '删除成功','data'  =>'',);   //$return_arr = array('status' => -1,'msg' => '删除失败','data'  =>'',);
       }else{
           $return_arr = array('status' => -1,'msg' => '删除失败','data'  =>'',);
       }
       return $this->ajaxReturn($return_arr);

   }

}