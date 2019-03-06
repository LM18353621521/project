<?php
$home_config = [
    // +----------------------------------------------------------------------
    // | 模板设置
    // +----------------------------------------------------------------------
	//默认错误跳转对应的模板文件
	'dispatch_error_tmpl' => 'public:dispatch_jump',
	//默认成功跳转对应的模板文件
	'dispatch_success_tmpl' => 'public:dispatch_jump',
    'TRANSACTION_NUM_BUY' => array(500, 1000, 3000, 5000, 10000, 30000), //交易金额
    'TRANSACTION_BOND' => 100, //保证金
    'TRANSACTION_NUM_SELL' => array(500, 1000, 3000, 5000, 10000, 30000), //交易金额
    'VIRTUALCURRENCY_BOND' => 100, //保证金
];

$html_config = include_once 'html.php';
return array_merge($home_config,$html_config);
?>