(function ($zp) {
    $zp.fn.extend({
        //时间计算 距离结束 多少天 多少时 多少分 多少秒
        StartTimer: function (option) {
            var _this = this;
            var NowTime = new Date();
            var timestart = _this.ChansferDate(option.timestart);
            var timeout = _this.ChansferDate(option.timeout);
           
            var endDiscribr = "";
            if ((NowTime - timestart) > 0 && (timeout - NowTime) > 0) {

                endDiscribr = "距活动结束";
            } else if ((NowTime - timeout) >= 0) {

                endDiscribr = "距活动结束";
            } else if ((timestart - NowTime) > 0) {
                endDiscribr = "距活动开始";
            }
            $(_this).find("[discribe]").html(endDiscribr);


      

            setInterval(function () {
                var nowTime = new Date();

                var t = 0;
                //已经开始
                if ((timeout - nowTime) > 0 && (nowTime - timestart) > 0) {

                    t = timeout - nowTime;
                }
                //还没开始
                if ((timestart - nowTime) > 0) {
                    t = timestart - nowTime;

                }
                if (t > 0) {
                    var d = Math.floor(t / 1000 / 60 / 60 / 24);
                    var h = Math.floor(t % (24 * 3600 * 1000) / 1000 / 60 / 60);
                    var m = Math.floor(t / 1000 / 60 % 60);
                    var s = Math.floor(t / 1000 % 60);
                   
                    if (h.toString().length <= 1) {
                        h = "0" + h;
                    }
                    if (m.toString().length <= 1) {
                        m = "0" + m;
                    }
                    if (s.toString().length <= 1) {
                        s = "0" + s;
                    }

                    $(_this).find("[data-date]").html( d+"天");
                    $(_this).find("[data-hour]").html(h);
                    $(_this).find("[data-minute]").html(m);
                    $(_this).find("[data-second]").html(s);
                }
            },1000);
           
        },
        //日期转换,兼容苹果安卓
        ChansferDate: function(strdate) {
            var arr = strdate.split(/[- : \/]/);
            var date = new Date(arr[0], arr[1] - 1, arr[2], arr[3], arr[4], arr[5]);
            return date;
        }
    })

})(jQuery)