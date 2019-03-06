<?php
namespace app\common\util;


class Log
{
    // 打印log
    function  log_result($file,$content)
    {
//        $fp = fopen($file,"a");
//        flock($fp, LOCK_EX) ;
//        fwrite($fp,"执行日期：".strftime("%Y-%m-%d-%H：%M：%S",time())."\n".$word."\n\n");
//        flock($fp, LOCK_UN);
//        fclose($fp);

        file_put_contents($file, date('Y-m-d H:i:s').' -- '.$content."\r\n", FILE_APPEND);
    }
}

?>