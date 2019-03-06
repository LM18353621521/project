<?php

namespace app\common\logic;
use think\Model;
use think\Db;

class QualificationLogic extends Model{
	protected $user_id = 0;//user_id

	//角色条件类别
	public $PUBLIC_INC_TYPE = [
		self::CATE_BUY,
		self::CATE_OPERATE,
		self::CATE_AMOUNT,
		self::CATE_RECOMMEND
	];
	//购买类
	const CATE_BUY = 'condition_buy';
	//操作类
	const CATE_OPERATE = 'condition_operate';
	//金额类
	const CATE_AMOUNT = 'condition_amount';
	//推荐类
	const CATE_RECOMMEND = 'condition_recommend';
	//购买类的判定时机
	const UPDATE_POINT = 'condition_update_point';

	/**
	 * 设置用户ID
	 * @param $user_id
	 */
	public function setUserId($user_id){
		$this->user_id = $user_id;
	}

	/**
	 * 准备验证角色升级和角色升级后的相关奖励操作
	 * @param $uid
	 * @param $data
	 * Author:Faramita
	 */
	public function prepare_update_level($uid = 0,$data = []){
		return false;
		if(empty($uid)){
			$uid = $this->user_id;
		}else{
			self::setUserId($uid);
		}
		//验证并升级角色
		if($data['apply_level']){
			$user_update_level = self::validate_user_update($uid,$data['apply_level']);
		}else{
			$user_update_level = self::validate_user_update($uid);
		}
		if($user_update_level){
			//更新用户区域代理信息
			if($data['region_code']){
				//判断当前升级角色是否开启区域代理
				$check_level = db('user_level')->where(['level_id'=>$data['apply_level'],'is_region_agent'=>1])->find();
				if($check_level){
					db('users')->where(['user_id'=>$uid])->update(['region_code'=>$data['region_code']]);
				}else{
					self::error_log($uid,$data['apply_level'],2,'prepare_update_level:region_code','角色没有开启区域代理');
				}
			}

			//更新session
			$session_user = session('user');
			$session_user['level'] = $user_update_level;
			session('user',$session_user);

            //开启奖励分配----------标记<QualificationLogic>
            $Distribut = new \app\common\logic\DistributPrizeLogic;
            $Distribut->setUserId($uid);
            $wechat = new \app\common\logic\WechatLogic;
            $wechat->sendTemplateUpdateLevel($uid,$user_update_level);
            if($user_update_level == 2){
                // 推送消息 成为会员

                //直推奖
                $Distribut->setConfigCache('first_prize');
                $Distribut->first_prize();
                //团队奖
                $Distribut->setConfigCache('team_prize');
                $Distribut->team_prize();
                //市场补助奖
                $Distribut->setConfigCache('market_prize');
                $Distribut->market_prize();
            }elseif ($user_update_level == 4 || $user_update_level == 5 || $user_update_level == 6) {
                // 升级成为 体验店 市代代理 合伙人  ----》推荐奖
                $Distribut->setConfigCache('recommended_prize');
                $Distribut->recommended_prize();

                // 更新冻结金额时间
                $Distribut->frozen_money($session_user['user_id'],'change_time');
            }
		}
	}

	/**
	 * 记录错误日志
	 * @param $uid			//出现错误验证时的用户id
	 * @param $level		//出现错误验证时用户要升级/验证的等级id
	 * @param $type			//错误类型
	 * @param $function		//出现错误的函数位置
	 * @param string $desc	//备注补充
	 * Author:Faramita
	 */
	public function error_log($uid,$level,$type,$function,$desc = ''){
		$data['user_id'] = $uid;
		$data['level'] = $level;
		switch($type){
			case $type == 1 : $data['type'] = '数据库错误';break;
			case $type == 2 : $data['type'] = '缺少必要参数';break;
			case $type == 3 : $data['type'] = '不符合使用当前函数的条件，错误使用此函数';break;
			default:$data['type'] = '未知错误';break;
		}
		$data['function'] = $function;
		$data['desc'] = $desc;
		$data['add_time'] = time();
		db('qualification_log')->insert($data);
	}

	/*************************************角色验证升级处理 START**********************************/
	/**
	 * 验证角色条件操作
	 * @param $level
	 * @param $uid
	 * @param array $inc_type
	 * @return boolean
	 * Author:Faramita
	 */
	public function validate_qualification($level,$uid = 0,$inc_type = []){
		if(empty($uid)){
			$uid = $this->user_id;
		}else{
			self::setUserId($uid);
		}
		//无特殊指定则默认全类型判定
		if(empty($inc_type)){
			$inc_type = $this->PUBLIC_INC_TYPE;
		}
		//初始化
		$primary_point = 0;
		$supplement_point = 0;
		//先判断条件一
		$validate_primary = self::get_qualification_handle($level,$inc_type,1);

		if(in_array(self::CATE_BUY,$inc_type)){
			//获取购买类条件判定时机的配置
			$primary_point = self::get_update_point($level,1);
		}
		if($validate_primary){
			//开始在逻辑层判定条件一
			$result = self::judge_validate($uid,$validate_primary,[self::UPDATE_POINT=>$primary_point,'level'=>$level]);
			if($result){
				//条件一验证通过，无需验证条件二
				return true;
			}else{
				//条件一验证不通过，判断条件二
				$validate_supplement = self::get_qualification_handle($level,$inc_type,2);
				if(in_array(self::CATE_BUY,$inc_type)) {
					//获取条件判定时机的配置
					$supplement_point = self::get_update_point($level, 2);
				}
				if($validate_supplement){
					//开始在逻辑层判定条件二
					$result = self::judge_validate($uid,$validate_supplement,[self::UPDATE_POINT=>$supplement_point,'level'=>$level]);
					if($result){
						//条件二验证通过
						return true;
					}
				}
				//没有条件二或条件二验证不通过
				return false;
			}
		}else{
			//条件一不存在则说明无条件，不用论证条件二
			return true;
		}
	}

	/**
	 * 验证升级角色
	 * @param int $uid
	 * @param int $level
	 * @return bool
	 * Author:Faramita
	 */
	public function validate_user_update($uid = 0,$level = 0){
		if(empty($uid)){
			$uid = $this->user_id;
		}
		if($level){
			//验证条件
			$user_level = db('users')->where(['user_id'=>$uid])->find()['level'];
			if($user_level >= $level){
				return false;
			}
			$validate_result = self::validate_qualification($level,$uid);
			if($validate_result){
				//升级角色
				$status_validate_result = self::update_user_level($uid,$level);
				return $status_validate_result;
			}
		}else{
			//升级的目标等级不能是初始角色，也不能是小于当前id的角色
			$user_level = db('users')->where(['user_id'=>$uid])->find()['level'];
			$level_list = db('user_level')->where(['level_id'=>['GT',$user_level]])->order('level_id ASC')->column('level_id');
			//最后升级的角色id
			$status_validate_result = false;
			//副本
			$status_validate_result_temp = false;
			//开始循环验证升级角色，一但失败则停止验证
			foreach($level_list as $k => $val){
				//初始化变量
				unset($validate_result,$status_validate_result);
				//开始验证当前角色
				$validate_result = self::validate_qualification($val,$uid);
				if(!$validate_result){
					//验证不通过，停止此处验证循环，返回最后一次验证通过的数据
					return $status_validate_result_temp;
				}
				//验证通过，升级角色，继续下个角色验证
				$status_validate_result = self::update_user_level($uid,$val);
				if(!$status_validate_result){
					//升级失败，停止此处升级，返回上次成功的数据
					return $status_validate_result_temp;
				}
				//升级成功，保留当前成功的角色数据，继续循环验证下个角色
				$status_validate_result_temp = $status_validate_result;
			}
			return $status_validate_result;
		}
		return false;
	}

	/**
	 * 验证当前用户的所有上级是否符合升级角色
	 * @param $uid		//当前用户id
	 * @return boolean
	 * Author:Faramita
	 */
	public function validate_parent_update($uid){
		//获取所有有推荐条件的角色id
		$role = db('distribut_system')
			->where(['inc_type'=>self::CATE_RECOMMEND,'value'=>['NEQ',0]])
			->order('level_id ASC')
			->group('level_id')->column('level_id');
		if(!$role){
			return true;
		}
		//获取用户上级
		$first_leader = db('users')->where(['user_id'=>$uid,'first_leader'=>['NEQ',0]])->column('first_leader')[0];
		if(!$first_leader){
			return true;
		}
		//总集
		$all_arr = db('users')->select();
		//user_id=>first_leader
		$user_arr = [];
		//user_id=>level
		$user_level = [];

		//处理用于递归的数组
		foreach($all_arr as $k => $val){
			$user_arr[$val['user_id']] = $val['first_leader'];//用户id=>上级id
			$user_level[$val['user_id']] = $val['level'];//用户id=>用户等级
		}
		//分角色验证当前用户的上级和上N级
		foreach($role as $k => $level){
			//获取当前角色推荐类条件的配置
			$qual_arr1 = self::get_qualification_handle($level,[self::CATE_RECOMMEND],1);
			$qual_arr2 = self::get_qualification_handle($level,[self::CATE_RECOMMEND],2);

			//验证的上级等级必须小于当前验证的角色等级,如果该角色有直推条件的限制，上级触发此等级的验证升级
			if(($user_level[$first_leader] < $level) && ($qual_arr1[self::CATE_RECOMMEND]['direct'] || $qual_arr2[self::CATE_RECOMMEND]['direct'])){
				//上级试图升级
				self::validate_user_update($first_leader,$level);
			}

			//如果该角色有非直推条件的限制，获取需要验证的上级的级数
			$level_arr = [];
			if($qual_arr1[self::CATE_RECOMMEND]['recommend']){
				foreach($qual_arr1[self::CATE_RECOMMEND]['recommend'] as $rek => $reval){
					$level_arr[] = $reval['recommendLevel'];
				}
			}
			if($qual_arr2[self::CATE_RECOMMEND]['recommend']){
				foreach($qual_arr2[self::CATE_RECOMMEND]['recommend'] as $rek => $reval){
					$level_arr[] = $reval['recommendLevel'];
				}
			}
			//去重，里面包含了跟当前用户有关的上级级数
			$level_arr = array_unique($level_arr);
			if($level_arr){
				foreach($level_arr as $rek => $reval){
					//寻找上N级
					$pid = self::get_position_parent_id($uid,$user_arr,$reval);
					//上N级的上级存在，且该上级等级小于当前验证的角色等级
					if($pid && ($user_level[$pid] < $level)){
						//上N级试图升级
						self::validate_user_update($pid,$level);
					}
				}
				unset($level_arr);
			}
		}
		//当前用户的上级升级是否成功，不影响当前用户的升级，无论结果如何都返回true
		return true;
	}

	/**
	 * 升级用户角色
	 * @param $uid
	 * @param $level
	 * @param $data //其他要同步更新的东西
	 * @return boolean
	 * Author:Faramita
	 */
	public function update_user_level($uid,$level,$data = []){
		if(empty($uid) || empty($level)){
			self::error_log($uid,$level,2,'update_user_level');
			return false;
		}
		$update = db('users')->where(['user_id'=>$uid])->update(['level'=>$level]);
		if($update){
			//角色升级成功，开始验证当前用户的所有上级是否符合升级角色的条件
			self::validate_parent_update($uid);
			return $level;
		}
		//存入日志
		self::error_log($uid,$level,1,'update_user_level');
		return false;
	}

	/**
	 * 同一用户可以同时代理多个区域，特殊的同级升级验证
	 * @param $uid
	 * @param $level
	 * @param $region_code
	 * @return boolean
	 * Author:Faramita
	 */
	public function update_special_proxy($uid,$level,$region_code){
		//验证当前区域是否没有代理
		$status = self::validate_region_proxy($region_code);
		if($status){
			$old_region_code = db('users')->where(['user_id'=>$uid,'level'=>$level])->find()['region_code'];
			if($old_region_code){
				//插入新的代理区域
				$new_region_code = $old_region_code.','.$region_code;
				$result = db('users')->where(['user_id'=>$uid,'level'=>$level])->update(['region_code'=>$new_region_code]);
				if($result){
					return true;
				}else{
					self::error_log($uid,$level,1,'update_special_proxy');
				}
			}else{
				//存入日志
				self::error_log($uid,$level,3,'update_special_proxy');
			}
		}
		return false;
	}
	/*************************************角色验证升级处理 START**********************************/
	/*************************************角色条件配置处理 START**********************************/
	/**
	 * 身份条件添加处理
	 * @param $level_id
	 * @param data
	 * Author:Faramita
	 */
	public function qualification_handle($level_id,$data){
		$is_checked = 'on';
		/*购买类条件*/
		$inc_type = self::CATE_BUY.'-'.$level_id;
		//购买指定商品
		if($data['IsBuyAppointProductEnable'][0] == $is_checked){
			$condition_buy_switch['is_buy_appoint_product_enable'] = $data['IsBuyAppointProductEnable'][1];
		}else{
			$condition_buy_none['is_buy_appoint_product_enable'] = 0;
		}
		//购买任意商品
		if($data['buyAny'] == $is_checked){
			$condition_buy_switch['buy_any'] = 1;
		}else{
			$condition_buy_none['buy_any'] = 0;
		}
		//一次性消费满特定数值
		if($data['oneConsumption'][0] == $is_checked && $data['oneConsumption'][1]){
			$condition_buy_switch['one_consumption'] = $data['oneConsumption'][1];
		}else{
			$condition_buy_none['one_consumption'] = 0;
		}
		//累计消费满特定数值
		if($data['allConsumption'][0] == $is_checked && $data['allConsumption'][1]){
			$condition_buy_switch['all_consumption'] = $data['allConsumption'][1];
		}else{
			$condition_buy_none['all_consumption'] = 0;
		}
		//累计购买满特定次数
		if($data['cumulativePurchases'][0] == $is_checked && $data['cumulativePurchases'][1]){
			$condition_buy_switch['cumulative_purchases'] = $data['cumulativePurchases'][1];
		}else{
			$condition_buy_none['cumulative_purchases'] = 0;
		}
		//存储购买类条件配置
		if($condition_buy_switch)distributCache($inc_type,$condition_buy_switch,1,['switch'=>1]);
		if($condition_buy_none)distributCache($inc_type,$condition_buy_none,1,['switch'=>0]);

		/*操作类条件*/
		$inc_type = self::CATE_OPERATE.'-'.$level_id;
		//关注公众号获得
		if($data['followOfficialAccounts'] == $is_checked){
			$condition_operate_switch['follow_official_accounts'] = 1;
		}else{
			$condition_operate_none['follow_official_accounts'] = 0;
		}
		//注册获得
		if($data['register'] == $is_checked){
			$condition_operate_switch['register'] = 1;
		}else{
			$condition_operate_none['register'] = 0;
		}
		//申请获得
		if($data['application'] == $is_checked){
			$condition_operate_switch['application'] = 1;
		}else{
			$condition_operate_none['application'] = 0;
		}
		//存储操作类条件配置
		if($condition_operate_switch)distributCache($inc_type,$condition_operate_switch,1,['switch'=>1]);
		if($condition_operate_none)distributCache($inc_type,$condition_operate_none,1,['switch'=>0]);
		/*金额类条件*/
		$inc_type = self::CATE_AMOUNT.'-'.$level_id;
		//累计满特定货币特定数值
		if($data['accumulative'] == $is_checked && $data['accumulativeCurrency'] && $data['accumulativeMoney']){
			foreach($data['accumulativeCurrency'] as $k => $val){
				if($val && $data['accumulativeMoney'][$k]){
					$accumulative_value[] = ['accumulativeCurrency'=>$val,'accumulativeMoney'=>$data['accumulativeMoney'][$k]];
				}
			}
		}
		//存储金额类条件配置
		if($accumulative_value){
			distributCache($inc_type,['accumulative' => serialize($accumulative_value)],1,['switch'=>1]);
		}else{
			distributCache($inc_type,['accumulative' => 0],1,['switch'=>0]);
		}
		/*推荐类条件*/
		$inc_type = self::CATE_RECOMMEND.'-'.$level_id;
		//直推会员级别达特定人数
		if($data['direct'] == $is_checked && $data['directGrade'] && $data['directNumber']){
			foreach($data['directGrade'] as $k => $val){
				if($val && $data['directNumber'][$k]){
					$direct_value[] = ['directGrade'=>$val,'directNumber'=>$data['directNumber'][$k]];
				}
			}
		}
		if($direct_value){
			$condition_recommend_switch['direct'] = serialize($direct_value);
		}else{
			$condition_recommend_none['direct'] = 0;
		}
		//非直推会员级别达特定人数
		if($data['recommend'] == $is_checked && $data['recommendLevel'] && $data['recommendGrade'] && $data['recommendNumber']){
			foreach($data['recommendLevel'] as $k => $val){
				if($val && $data['recommendGrade'][$k] && $data['recommendNumber'][$k]){
					$recommend_value[] = ['recommendLevel'=>$val,'recommendGrade'=>$data['recommendGrade'][$k],'recommendNumber'=>$data['recommendNumber'][$k]];
				}
			}
		}
		if($recommend_value){
			$condition_recommend_switch['recommend'] = serialize($recommend_value);
		}else{
			$condition_recommend_none['recommend'] = 0;
		}
		//存储推荐类条件配置
		if($condition_recommend_switch)distributCache($inc_type,$condition_recommend_switch,1,['switch'=>1]);
		if($condition_recommend_none)distributCache($inc_type,$condition_recommend_none,1,['switch'=>0]);

		unset($inc_type);

		//是否有条件二
		if($data['IsQualificationTab2']){
			/*购买类条件*/
			$inc_type = self::CATE_BUY . '-' . $level_id;
			//购买指定商品
			if ($data['IsBuyAppointProductEnable2'][0] == $is_checked) {
				$condition_buy_switch2['is_buy_appoint_product_enable'] = $data['IsBuyAppointProductEnable2'][1];
			} else {
				$condition_buy_none2['is_buy_appoint_product_enable'] = 0;
			}
			//购买任意商品
			if ($data['buyAny2'] == $is_checked) {
				$condition_buy_switch2['buy_any'] = 1;
			} else {
				$condition_buy_none2['buy_any'] = 0;
			}
			//一次性消费满特定数值
			if ($data['oneConsumption2'][0] == $is_checked && $data['oneConsumption2'][1]) {
				$condition_buy_switch2['one_consumption'] = $data['oneConsumption2'][1];
			} else {
				$condition_buy_none2['one_consumption'] = 0;
			}
			//累计消费满特定数值
			if ($data['allConsumption2'][0] == $is_checked && $data['allConsumption2'][1]) {
				$condition_buy_switch2['all_consumption'] = $data['allConsumption2'][1];
			} else {
				$condition_buy_none2['all_consumption'] = 0;
			}
			//累计购买满特定次数
			if($data['cumulativePurchases2'][0] == $is_checked && $data['cumulativePurchases2'][1]){
				$condition_buy_switch2['cumulative_purchases'] = $data['cumulativePurchases2'][1];
			}else{
				$condition_buy_none2['cumulative_purchases'] = 0;
			}
			//存储购买类条件配置
			if ($condition_buy_switch2) distributCache($inc_type, $condition_buy_switch2, 2, ['switch' => 1]);
			if ($condition_buy_none2) distributCache($inc_type, $condition_buy_none2, 2, ['switch' => 0]);

			/*操作类条件*/
			$inc_type = self::CATE_OPERATE . '-' . $level_id;
			//关注公众号获得
			if ($data['followOfficialAccounts2'] == $is_checked) {
				$condition_operate_switch2['follow_official_accounts'] = 1;
			} else {
				$condition_operate_none2['follow_official_accounts'] = 0;
			}
			//注册获得
			if ($data['register2'] == $is_checked) {
				$condition_operate_switch2['register'] = 1;
			} else {
				$condition_operate_none2['register'] = 0;
			}
			//申请获得
			if ($data['application2'] == $is_checked) {
				$condition_operate_switch2['application'] = 1;
			} else {
				$condition_operate_none2['application'] = 0;
			}
			//存储操作类条件配置
			if ($condition_operate_switch2) distributCache($inc_type, $condition_operate_switch2, 2, ['switch' => 1]);
			if ($condition_operate_none2) distributCache($inc_type, $condition_operate_none2, 2, ['switch' => 0]);

			/*金额类条件*/
			$inc_type = self::CATE_AMOUNT . '-' . $level_id;
			//累计满特定货币特定数值
			if ($data['accumulative2'] == $is_checked && $data['accumulativeCurrency2'] && $data['accumulativeMoney2']) {
				foreach ($data['accumulativeCurrency2'] as $k => $val) {
					if ($val && $data['accumulativeMoney2'][$k]) {
						$accumulative_value2[] = ['accumulativeCurrency' => $val, 'accumulativeMoney' => $data['accumulativeMoney2'][$k]];
					}
				}
			}
			//存储金额类条件配置
			if ($accumulative_value2) {
				distributCache($inc_type, ['accumulative' => serialize($accumulative_value2)], 2, ['switch' => 1]);
			} else {
				distributCache($inc_type, ['accumulative' => 0], 2, ['switch' => 0]);
			}

			/*推荐类条件*/
			$inc_type = self::CATE_RECOMMEND . '-' . $level_id;
			//直推会员级别达特定人数
			if ($data['direct2'] == $is_checked && $data['directGrade2'] && $data['directNumber2']) {
				foreach ($data['directGrade2'] as $k => $val) {
					if ($val && $data['directNumber2'][$k]) {
						$direct_value2[] = ['directGrade' => $val, 'directNumber' => $data['directNumber2'][$k]];
					}
				}
			}
			if ($direct_value2) {
				$condition_recommend_switch2['direct'] = serialize($direct_value2);
			} else {
				$condition_recommend_none2['direct'] = 0;
			}
			//非直推会员级别达特定人数
			if ($data['recommend2'] == $is_checked && $data['recommendLevel2'] && $data['recommendGrade2'] && $data['recommendNumber2']) {
				foreach ($data['recommendLevel2'] as $k => $val) {
					if ($val && $data['recommendGrade2'][$k] && $data['recommendNumber2'][$k]) {
						$recommend_value2[] = ['recommendLevel' => $val, 'recommendGrade' => $data['recommendGrade2'][$k], 'recommendNumber' => $data['recommendNumber2'][$k]];
					}
				}
			}
			if ($recommend_value2) {
				$condition_recommend_switch2['recommend'] = serialize($recommend_value2);
			} else {
				$condition_recommend_none2['recommend'] = 0;
			}
			//存储推荐类条件配置
			if ($condition_recommend_switch2) distributCache($inc_type, $condition_recommend_switch2, 2, ['switch' => 1]);
			if ($condition_recommend_none2) distributCache($inc_type, $condition_recommend_none2, 2, ['switch' => 0]);
		}else{
			//取消了条件二，删除当前角色条件二的所有配置
			self::del_qualification_handle($level_id,[],2);
		}
	}

	/**
	 * 获取身份条件配置处理
	 * @param $level_id
	 * @param $inc_type //要获取的身份配置类型
	 * @param $state
	 * @return array
	 * Author:Faramita
	 */
	public function get_qualification_handle($level_id,$inc_type,$state){
		//获取条件配置，处理
		$result = [];
		foreach($inc_type as $k => $val){
			$inc_info = distributCache($val.'-'.$level_id,[],$state);
			if($inc_info){
				$result[$val] = $inc_info;
			}
			unset($inc_info);
		}
		if($result[self::CATE_BUY]['is_buy_appoint_product_enable']){
			$temp_appoint = explode(',',$result[self::CATE_BUY]['is_buy_appoint_product_enable']);
			$GoodsLogic = new \app\admin\logic\GoodsLogic();
			$type_identity_list = $GoodsLogic->type_identity_list();
			$temp_type_identity_list = [];
			foreach($type_identity_list as $k => $val){
				$temp_type_identity_list[$val['type_id']] = $val['name'];
			}
			//二维数组化
			$temp_appoint_arr = [];
			foreach($temp_appoint as $k => $val){
				if($temp_type_identity_list[$val]){
					$temp_appoint_arr[$k]['type_id'] = $val;
					$temp_appoint_arr[$k]['name'] = $temp_type_identity_list[$val];
				}
			}
			$result[self::CATE_BUY]['is_buy_appoint_product_enable_arr'] = $temp_appoint_arr;
		}
		if($result[self::CATE_AMOUNT]['accumulative']){
			$temp_accumulative = unserialize($result[self::CATE_AMOUNT]['accumulative']);
			$result[self::CATE_AMOUNT]['accumulative'] = $temp_accumulative;
		}
		if($result[self::CATE_RECOMMEND]['direct']){
			$temp_direct = unserialize($result[self::CATE_RECOMMEND]['direct']);
			$result[self::CATE_RECOMMEND]['direct'] = $temp_direct;
		}
		if($result[self::CATE_RECOMMEND]['recommend']){
			$temp_recommend = unserialize($result[self::CATE_RECOMMEND]['recommend']);
			$result[self::CATE_RECOMMEND]['recommend'] = $temp_recommend;
		}
		return $result;
	}

	/**
	 * 删除身份条件配置处理
	 * @param $level_id
	 * @param $data //删除的配置
	 * @param $state
	 * @return array
	 * Author:Faramita
	 */
	public function del_qualification_handle($level_id,$data = array(),$state){
		//data存在则删除当前角色data里设定的部分配置，否则删除当前角色全部配置
		if(empty($data)){
			$del_distribute = db('distribut_system')->where(['level_id'=>$level_id,'state'=>$state])->select();
			//需要删除的配置
			foreach($del_distribute as $k => $val){
				$data[$val['name']] = $val['inc_type'];
			}
		}
		//删除条件配置
		$result = delDistributCache($level_id,$data,$state);
		if($result !== false){
			return ['status' => 1, 'msg' => '删除成功', 'result' => ''];
		}else{
			return ['status' => 0, 'msg' => '删除失败，数据库未响应', 'result' => ''];
		}
	}

	/**
	 * 获取购买类条件计算时机配置
	 * @param $level_id
	 * @param $state
	 * @return array
	 * Author:Faramita
	 */
	public function get_update_point($level_id,$state){
		$get_update_point = distributCache(self::UPDATE_POINT.'-'.$level_id,[],$state);
		if($get_update_point){
			return $get_update_point[self::UPDATE_POINT];
		}else{
			self::error_log(0,$level_id,2,'get_update_point');
			return false;
		}
	}

	/**
	 * 设置购买类条件计算时机配置
	 * @param $level_id
	 * @param $update_point
	 * @param $state
	 * @return array
	 * Author:Faramita
	 */
	public function set_update_point($level_id,$update_point,$state){
		$set_update_point = distributCache(self::UPDATE_POINT.'-'.$level_id,[self::UPDATE_POINT=>$update_point],$state);
		if($set_update_point){
			return $set_update_point;
		}else{
			self::error_log(0,$level_id,1,'set_update_point');
			return false;
		}
	}
	/*************************************角色条件配置处理 END************************************/

	/*************************************角色条件配置验证 START**********************************/
	/**
	 * 判定使用哪类身份条件验证
	 * @param int $uid
	 * @param array $inc_type
	 * @param array $data
	 * @return boolean
	 * Author:Faramita
	 */
	public function judge_validate($uid,$inc_type,$data = []){
		//购买类
		if($inc_type[self::CATE_BUY]){
			$status_buy = self::validate_buy_qualification($uid,$inc_type[self::CATE_BUY],$data[self::UPDATE_POINT]);
			if(!$status_buy){
				return false;
			}
		}
		//操作类
		if($inc_type[self::CATE_OPERATE]){
			$status_operate = self::validate_operate_qualification($uid,$inc_type[self::CATE_OPERATE],['level'=>$data['level']]);
			if(!$status_operate){
				return false;
			}
		}
		//金额类
		if($inc_type[self::CATE_AMOUNT]){
			$status_amount = self::validate_amount_qualification($uid,$inc_type[self::CATE_AMOUNT]);
			if(!$status_amount){
				return false;
			}
		}
		//推荐类
		if($inc_type[self::CATE_RECOMMEND]){
			$status_recommend = self::validate_recommend_qualification($uid,$inc_type[self::CATE_RECOMMEND]);
			if(!$status_recommend){
				return false;
			}
		}
		//所有传过来的需要验证的条件都满足
		return true;
	}

	/**
	 * 购买类验证
	 * @param int $uid
	 * @param array $inc_type
	 * @param int $update_point
	 * @param array $data //补充参数
	 * @return boolean
	 * Author:Faramita
	 */
	public function validate_buy_qualification($uid,$inc_type,$update_point,$data = []){
		//购买指定商品
		if($inc_type['is_buy_appoint_product_enable']){
			if($update_point == 1){
				//支付后
				$where['order.pay_status'] = 1;
			}elseif($update_point == 2){
				//发货后
				$where['order.shipping_status'] = 1;

			}elseif($update_point == 3){
				//确认收货后
				$where['order.pay_status'] = 1;
				$where['order.shipping_status'] = 1;
				$where['order.order_status'] = ['IN','2,4'];
			}else{
				return false;
			}
			/*
			 * 筛选出指定订单编号(order)
			 * 通过订单编号查找相应的goods_id(order_goods)
			 * 再通过goods_id查找到type_id(goods)
			 */
			$where['order.user_id'] = $uid;
			$where['goods.type_id'] = ['IN',$inc_type['is_buy_appoint_product_enable']];
			$status_buy_appoint =
				Db::view('order','order_id,user_id,pay_status,shipping_status,shipping_status,order_status')
				->view('order_goods','order_id,goods_id','order_goods.order_id=order.order_id')
				->view('goods','goods_id,type_id','goods.goods_id=order_goods.goods_id')
				->where($where)
				->select();
			if(!$status_buy_appoint){
				return false;
			}
			unset($where);
		}
		//购买任意商品
		if($inc_type['buy_any']){
			if($update_point == 1){
				//支付后
				$where['order.pay_status'] = 1;
			}elseif($update_point == 2){
				//发货后
				$where['order.shipping_status'] = 1;

			}elseif($update_point == 3){
				//确认收货后
				$where['order.pay_status'] = 1;
				$where['order.shipping_status'] = 1;
				$where['order.order_status'] = ['IN','2,4'];
			}else{
				return false;
			}
			/*
			 * 筛选出指定订单编号(order)
			 * 通过订单编号查找相应的goods_id(order_goods)
			 * 再通过goods_id查找到type_id=0的普通商品(goods)
			 */
			$where['order.user_id'] = $uid;
			$where['goods.type_id'] = 0;
			$status_buy_any =
				Db::view('order','order_id,user_id,pay_status,shipping_status,shipping_status,order_status')
					->view('order_goods','order_id,goods_id','order_goods.order_id=order.order_id')
					->view('goods','goods_id,type_id','goods.goods_id=order_goods.goods_id')
					->where($where)
					->select();
			if(!$status_buy_any){
				return false;
			}
			unset($where);
		}
		//一次性消费金额
		if($inc_type['one_consumption']){
			$where['user_id'] = $uid;
			$where['total_amount'] = ['EGT',$inc_type['one_consumption']];
			if($update_point == 1){
				//支付后
				$where['pay_status'] = 1;
			}elseif($update_point == 2){
				//发货后
				$where['shipping_status'] = 1;
			}elseif($update_point == 3){
				//确认收货时
				$where['pay_status'] = 1;
				$where['shipping_status'] = 1;
				$where['order_status'] = ['IN','2,4'];
			}else{
				return false;
			}
			$status_one_consumption = db('order')->where($where)->select();
			if(!$status_one_consumption){
				return false;
			}
			unset($where);
		}
		//累计消费金额
		if($inc_type['all_consumption']){
			$where['user_id'] = $uid;
			if($update_point == 1){
				//支付后
				$where['pay_status'] = 1;
			}elseif($update_point == 2){
				//发货后
				$where['shipping_status'] = 1;
			}elseif($update_point == 3){
				//确认收货时
				$where['pay_status'] = 1;
				$where['shipping_status'] = 1;
				$where['order_status'] = ['IN','2,4'];
			}else{
				return false;
			}
			$status_all_consumption = db('order')->where($where)->sum('total_amount');
			if($status_all_consumption < $inc_type['all_consumption']){
				return false;
			}
			unset($where);
		}
		//累计购买次数
		if($inc_type['cumulative_purchases']){
			$where['user_id'] = $uid;
			if($update_point == 1){
				//支付后
				$where['pay_status'] = 1;
			}elseif($update_point == 2){
				//发货后
				$where['shipping_status'] = 1;
			}elseif($update_point == 3){
				//确认收货时
				$where['pay_status'] = 1;
				$where['shipping_status'] = 1;
				$where['order_status'] = ['IN','2,4'];
			}else{
				return false;
			}
			$status_cumulative_purchases = db('order')->where($where)->count();
			if($status_cumulative_purchases < $inc_type['cumulative_purchases']){
				return false;
			}
			unset($where);
		}
		//所有传过来的需要验证的条件都满足
		return true;
	}

	/**
	 * 操作类验证
	 * @param int $uid
	 * @param array $inc_type
	 * @param array $data //补充参数
	 * @return boolean
	 * Author:Faramita
	 */
	public function validate_operate_qualification($uid,$inc_type,$data = []){
		//关注公众号
		if($inc_type['follow_official_accounts']){
			if(session('?subscribe') == null){
				self::error_log($uid,0,2,'validate_operate_qualification:follow_official_accounts','没有相关session');
				return false;
			}
			if(session('subscribe') == 0){
				return false;
			}
		}
		//注册
		if($inc_type['register']){
			$status_register = db('users')->where(['user_id'=>$uid])->find();
			if(!$status_register){
				return false;
			}
		}
		//申请
		if($inc_type['application'] && $data['level']){
			//只有在验证中的申请才算当前申请
			$status_application = db('user_apply')->where(['user_id'=>$uid,'level'=>$data['level'],'status'=>0,'validate_status'=>3,'handle_time'=>0])->order('apply_time DESC')->find();
			if(!$status_application){
				return false;
			}
		}
		//所有传过来的需要验证的条件都满足
		return true;
	}

	/**
	 * 金额类验证
	 * @param int $uid
	 * @param array $inc_type
	 * @param array $data //补充参数
	 * @return boolean
	 * Author:Faramita
	 */
	public function validate_amount_qualification($uid,$inc_type,$data = []){
		$user = db('users')->where(['user_id'=>$uid])->find();
		//金额条件
		if($inc_type['accumulative'] && $user){
			foreach($inc_type['accumulative'] as $k => $val){
				//佣金
				if($val['accumulativeCurrency'] == 1){
					//有小数点，比较时要去掉小数点
					if($user['distribut_money']*100 < $val['accumulativeMoney']*100){
						return false;
					}
				}
				//余额
				if($val['accumulativeCurrency'] == 6){
					//有小数点，比较时要去掉小数点
					if($user['user_money']*100 < $val['accumulativeMoney']*100){
						return false;
					}
				}
			}
		}
		//所有传过来的需要验证的条件都满足
		return true;
	}

	/**
	 * 推荐类验证
	 * @param int $uid
	 * @param array $inc_type
	 * @param array $data //补充参数
	 * @return boolean
	 * Author:Faramita
	 */
	public function validate_recommend_qualification($uid,$inc_type,$data = []){
		//直推
		if($inc_type['direct']){
			foreach($inc_type['direct'] as $k => $val){
				//查找符合要求的下线数量
				$direct = self::get_qualification_child_sum($uid,$val['directGrade'],1);
				if($direct < $val['directNumber']){
					return false;
				}
				unset($direct);
			}
		}
		//推荐层
		if($inc_type['recommend']){
			foreach($inc_type['recommend'] as $k => $val){
				//查找符合要求的下线数量
				$recommend = self::get_qualification_child_sum($uid,$val['recommendGrade'],$val['recommendLevel']);
				if($recommend < $val['recommendNumber']){
					return false;
				}
				unset($recommend);
			}
		}
		//所有传过来的需要验证的条件都满足
		return true;
	}
	/*************************************角色条件配置验证 END************************************/
	/***************************************推荐关系函数 START************************************/
	/**
	 * 获取符合等级的下线数量
	 * @param int $uid
	 * @param int $level	//要求下线身份等级
	 * @param int $position //第几层下线
	 * @return int
	 * Author:Faramita
	 */
	public function get_qualification_child_sum($uid,$level,$position = 1){
		$where['level'] = $level;
		if($position == 1){
			$where['first_leader'] = $uid;
		}elseif($position == 2){
			$where['second_leader'] = $uid;
		}elseif($position == 3){
			$where['third_leader'] = $uid;
		}else{
			//总集
			$all_arr = db('users')->select();
			$user_arr = [];
			//目标集
			$order_arr = [];
			//处理用于递归的数组
			foreach($all_arr as $k => $val){
				$user_arr[$val['user_id']] = $val['first_leader'];
				if($val['level'] = $level && $val['first_leader'] != 0 && $val['second_leader'] != 0 && $val['third_leader'] != 0){
					$order_arr[] = $val['user_id'];
				}
			}
			//迭代查找所有属于当前用户第$position层的用户
			$child_sum = self::get_position_all_sum($uid,$position,$user_arr,$order_arr);
			return $child_sum;
		}
		$child_sum = db('users')->where($where)->count();
		unset($where);
		return $child_sum;
	}

	/**
	 * 获取超出三层的符合等级的下线数量
	 * @param $uid			//目标用户
	 * @param $position		//要求第几层下线
	 * @param $user_arr		//用户[id]=>[pid]用于递归的数组
	 * @param $order_arr	//用来筛选的目标集，提高效率
	 * @return int
	 * Author:Faramita
	 */
	public function get_position_all_sum($uid,$position,$user_arr,$order_arr){
		$count = 0;
		foreach($order_arr as $k => $val){
			//获取相应层数的上级id
			$pid = self::get_position_parent_id($val,$user_arr,$position);
			//相应层数的上级为目标id
			if($pid == $uid){
				$count++;
			}
		}
		return $count;
	}

	/**
	 * 获取相应层数的上级id
	 * @param $uid			//需要获取相应层数上级的用户id
	 * @param $user_arr		//用户[id]=>[pid]用于递归的数组
	 * @param $position		//要求第几层上线
	 * @return bool
	 * Author:Faramita
	 */
	public function get_position_parent_id($uid,$user_arr,$position){
		$parent_id = $user_arr[$uid];
		if($parent_id){
			if($position == 1){
				return $parent_id;
			}else{
				//继续迭代
				$position--;
				return self::get_position_parent_id($parent_id,$user_arr,$position);
			}
		}
		return false;
	}
	/***************************************推荐关系函数 END**************************************/
	/***************************************特殊验证函数 START************************************/
	/**
	 * 验证当前选中的所有区域是否有代理
	 * @param $region_code
	 * @param $ignore_code //忽略的区域编号(格式是逗号分隔的字符串)
	 * @return boolean
	 * Author:Faramita
	 */
	public function validate_region_proxy($region_code,$ignore_code = 0){
		//当前有代理的区域
		$now_region = db('users')->where(['region_code'=>['NEQ',0]])->column('region_code');
		if(!$now_region){
			return true;
		}
		if($ignore_code){
			$ignore_code_arr = explode(',',$ignore_code);
		}
		//因为数据有可能是带‘,’的，所以要处理出没有逗号的验证数组
		$all_region = [];
		foreach($now_region as $k => $val){
			//带逗号的
			if(strpos($val,',') !== false){
				$arr = explode(',',$val);
				foreach($arr as $ks => $vals){
					if(!isset($ignore_code_arr) || in_array($vals,$ignore_code_arr) == false) {//忽略的区域不参与验证
						$all_region[] = $vals;
					}
				}
				unset($arr);
			}else{
				if(!isset($ignore_code_arr) || in_array($val,$ignore_code_arr) == false) {//忽略的区域不参与验证
					$all_region[] = $val;
				}
			}
		}
		//数据可能是带逗号的字符串，需要处理成数组
		$region_code_arr = explode(',',$region_code);
		//判断两个数组是否有交集
		if(array_intersect($region_code_arr,$all_region)){
			return false;
		}else{
			return true;
		}
	}
	/***************************************特殊验证函数 END**************************************/
}