<?php
function dataall(){
    ini_set('memory_limit', '-1');
    set_time_limit(0);
    $date = file_get_contents('http://www.luyn.mobi/mobile/test/test_header.html');
    sleep(1);
    print_R($date);
    if($date=1){
        dataall();
    }

    //print_r($date);die;
}
dataall();
