//消息类 ajax 请求方法
function ajax_post(url, data, redurl) {
    $.ajax({
        type: "POST",
        url: url,
        data: data,// 你的formid
        dataType: 'JSON',
        error: function (result) {
            layer.msg("请求错误！");
        },
        success: function (data) {
            resultcheck(data, redurl);
        }
    });
}

function resultcheck(result, redurl) {
    if (result) {
        var code = result.code;
        var msg = result.msg;
        var res = result.data;
        if (code == 0) {
            layer.msg(msg, {time: 1000})
            if (redurl) {
                setTimeout(function () {
                    window.location.href = redurl;
                }, 1500)
            }
        } else {
            layer.msg(msg);
        }
    } else {
        layer.msg("返回为空");
    }
}

//公共提示框
function msg_alert(message,url){

    if(url){
        layer.msg(message,{time:1000},function(){
            window.location.href=url;
        });
    }else{
        layer.msg(message,{time:1500});
    }

}
