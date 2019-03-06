<?php
namespace My;

class WxPay
{

	/**
	 * 申请退款    2018-06-19
	 */
	public function refund($param)
	{
		$url = "https://api.mch.weixin.qq.com/secapi/pay/refund";

		$data = array(
			'appid' => $param['appid'],
			'mch_id' => $param['mch_id'],
			'nonce_str' => get_rand_str(32,0,1),
			'out_trade_no' => $param['ordernumber'],
			'out_refund_no' => '@' . $param['ordernumber'],
			'total_fee' => $param['total_fee'] * 100,
			'refund_fee' => $param['refund_fee'] * 100
		);
		$data['sign'] = $this->getsign($data, $param);

		$xml = new \SimpleXMLElement('<xml></xml>');
		data2xml($xml, $data);
		$data_xml = $xml->asXML();


		$result = $this->curl_post_ssl($url, $data_xml, $second = 30, $aHeader = array(), $param);
		$result = xmlToArray($result);

		return $result;
	}
	/**
	* 微信企业付款接口	2018-06-19
	*/
	public function pay($parameter){
		$url = "https://api.mch.weixin.qq.com/mmpaymkttransfers/promotion/transfers";
		
		$data = array(
			'mch_appid'			=> $parameter['mch_appid'],	//公众账号ID
			'mchid'				=> $parameter['mchid'],			//商户号
			'nonce_str'			=> get_rand_str(32,0,0),				//随机字符串，不长于32位
			'partner_trade_no'  => $parameter['ordernumber'],			//商户订单号
			'openid'			=> $parameter['openid'],					//用户OPENID
			'check_name'		=> 'NO_CHECK',				//校验用户姓名选项，NO_CHECK：不校验真实姓名， FORCE_CHECK：强校验真实姓名OPTION_CHECK：针对已实名认证的用户才校验真实姓名		
			'amount'			=> $parameter['money'] * 100,			//企业付款金额，单位为分
			'desc'				=> $parameter['desc'],			//企业付款操作说明信息
			'spbill_create_ip'	=> serverIP(),			//接口的机器Ip地址 前：get_client_ip()
		);
		$data['sign'] = $this -> getsign($data,$parameter);//生成签名
		
		$xml = new \SimpleXMLElement('<xml></xml>');
		data2xml($xml, $data);
		$data_xml = $xml->asXML();
		$result = $this -> curl_post_ssl($url, $data_xml, $second=30,$aHeader=array(),$parameter);
		$result = xmlToArray($result);

		return $result;
	}
	/**
	 * 微信
	 */
	public function bankPay($parameter){
		$url = "https://api.mch.weixin.qq.com/mmpaysptrans/pay_bank";

		$data = array(
			'mch_id'				=> $parameter['mch_id'],			//商户号
			'partner_trade_no'  => $parameter['ordernumber'],			//商户订单号
			'nonce_str'			=> get_rand_str(32,0,0),				//随机字符串，不长于32位
			'enc_bank_no'			=> $parameter['enc_bank_no'],					//收款方银行卡号
			'enc_true_name'		=> $parameter['enc_true_name'],				//收款方用户名
			'bank_code'		=> $parameter['bank_code'],				//收款方开户行
			'amount'			=> $parameter['money'] * 100,			//企业付款金额，单位为分
			'desc'				=> $parameter['desc'],			//企业付款操作说明信息
		);
		$data['sign'] = $this -> getsign($data,$parameter);//生成签名

		$enc_bank_no =$this->get_enc_bank_no($data['mch_id'],$data['nonce_str'],$data['sign']);

		dump($enc_bank_no);





		$xml = new \SimpleXMLElement('<xml></xml>');
		data2xml($xml, $data);
		$data_xml = $xml->asXML();
		$result = $this -> curl_post_ssl($url, $data_xml, $second=30,$aHeader=array(),$parameter);
		$result = xmlToArray($result);

		return $result;
	}

	function  get_enc_bank_no($mchId,$nonce_str,$sign){
		$url = "https://fraud.mch.weixin.qq.com/risk/getpublickey";
		$params = [
			'mch_id'    => $mchId,
			'nonce_str' => strtoupper(md5(time())),
			'sign_type' => 'MD5',
			'sign'=>$sign
		];
		$xml = new \SimpleXMLElement('<xml></xml>');
		data2xml($xml, $params);
		$data_xml = $xml->asXML();
		$result = $this -> curl_post_ssl($url, $data_xml, $second=30,$aHeader=array(),$params);
		$result = xmlToArray($result);
		return $result;
	}

	
	//普通现金红包   2017-08-22
	public function redbagpay($parameter){
		$url = "https://api.mch.weixin.qq.com/mmpaymkttransfers/sendredpack";
	
		$data = array(
			'nonce_str'			=> createnoncestr(32),				//随机字符串，不长于32位
			'mch_billno'        => $parameter['mch_billno'],			//商户订单号
			'mch_id'			=> $parameter['mchid'],			//商户号
			'wxappid'			=> $parameter['mch_appid'],	//公众账号ID
			'send_name'         => $parameter['send_name'],	//商户名称
			're_openid'			=> $parameter['openid'],					//用户OPENID
			'total_amount'      => $parameter['money'] * 100,   //付款金额，单位为分
			'total_num'         => 1,   //红包发放总人数
			'wishing'           => $parameter['wishing'],   //红包祝福语
			'client_ip'	   		=> get_client_ip(),			//接口的机器Ip地址
			'act_name'		    => $parameter['act_name'],				//活动名称
			'remark'			=> $parameter['remark'],    //备注
		);
		$data['sign'] = $this -> getsign($data,$parameter);//生成签名
		$xml = new SimpleXMLElement('<xml></xml>');
		data2xml($xml, $data);
		$data_xml = $xml->asXML();
		
		$result = $this -> curl_post_ssl($url, $data_xml, $second=30,$aHeader=array(),$parameter);

		$result = xmltoarray($result);
		apilog('','wechat','redbagpay',$url, $data,$result);
		return $result;
	}
	
	
	/**
	* 微信企业付款接口-格式化参数，签名过程需要使用
	*/
	public function curl_post_ssl($url, $vars, $second=30,$aHeader=array(),$parameter){
		$ch = curl_init();
		//超时时间
		curl_setopt($ch,CURLOPT_TIMEOUT,$second);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1);
		//这里设置代理，如果有的话
		//curl_setopt($ch,CURLOPT_PROXY, '10.206.30.98');
		//curl_setopt($ch,CURLOPT_PROXYPORT, 8080);
		curl_setopt($ch,CURLOPT_URL,$url);
		curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);
		curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,false);
			
		//以下两种方式需选择一种
			
		//第一种方法，cert 与 key 分别属于两个.pem文件
		//默认格式为PEM，可以注释
		curl_setopt($ch,CURLOPT_SSLCERTTYPE,'PEM');

		curl_setopt($ch,CURLOPT_SSLCERT,getcwd().$parameter['path1']);
		//默认格式为PEM，可以注释
		curl_setopt($ch,CURLOPT_SSLKEYTYPE,'PEM');
		curl_setopt($ch,CURLOPT_SSLKEY,getcwd().$parameter['path2']);
		
			
		if( count($aHeader) >= 1 ){
			curl_setopt($ch, CURLOPT_HTTPHEADER, $aHeader);
		}
		curl_setopt($ch,CURLOPT_POST, 1);
		curl_setopt($ch,CURLOPT_POSTFIELDS,$vars);
		$data = curl_exec($ch);

		if($data){
			curl_close($ch);
			return $data;
		}else{
			$error = curl_errno($ch);
			echo "call faild, errorCode:$error\n";
			curl_close($ch);
			return false;
		}
	}
	
	public function getsign($data,$param){
		foreach($data as $k => $v){
			if($v){
				$Parameters[$k] = $v;
			}
		}
		ksort($Parameters);
		$String = '';
		foreach ($Parameters as $k => $v){
			$String .= $k . "=" . $v . "&";
		}
		$String = $String."key=".$param['partnerkey'];
		echo $String;
		$String = md5($String);
		$result = strtoupper($String);
			
		return $result;
	}
	

}
