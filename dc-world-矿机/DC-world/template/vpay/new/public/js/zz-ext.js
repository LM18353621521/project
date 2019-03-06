//适配不同尺寸
(function(doc, win) {
    var docEl = doc.documentElement,
        resizeEvt = 'orientationchange' in window ? 'orientationchange' : 'resize',
        recalc = function() {
            var clientWidth = docEl.clientWidth;
            if (!clientWidth) return;
            docEl.style.fontSize = 100 * (clientWidth / 375) + 'px';
        };
    if (!doc.addEventListener) return;
    win.addEventListener(resizeEvt, recalc, false);
    doc.addEventListener('DOMContentLoaded', recalc, false);
})(document, window);

$(function(){
    $(".cd_nav li").click(function(){
        $(this).addClass("active").siblings().removeClass("active");
    });
    $(".login_btn").click(function(){
        $('.masks,.pwd-box').show();
    });
    $(".masks").click(function(){
        $('.masks,.pwd-box').hide();
        $('.masks,.masks-remark,.pwd-box-remark').hide();
    });
     $(".xpay_m li").click(function(){
        $(this).addClass("active8").siblings().removeClass("active8");
    });
    $(".dcent_list li").click(function(){
        $(this).addClass("active7").siblings().removeClass("active7");
    });
     $(".top_nav a").click(function(){
        $(this).addClass("active").siblings().removeClass("active");
    });
     $(".tra_list li").click(function(){
        $(this).addClass("active6").siblings().removeClass("active6");
    });
    $(".nav_m ul li").click(function(){
        $(this).addClass("active2").siblings().removeClass("active2");
        /*var index=$(this).index();
        $(contWrap).children().eq(index).show().siblings().hide();*/
    });
    $(".foot_nav ul li").click(function(){
        $(this).addClass("cur").siblings().removeClass("cur");
    });
    $(".hy_list ul li").click(function(){
        $(this).addClass("active1").siblings().removeClass("active1");
    });
    $(".zs_list ul li").click(function(){
        $(this).addClass("active1").siblings().removeClass("active1");
    });
    $(".cz_list ul li").click(function(){
        $(this).addClass("active5").siblings().removeClass("active5");
    });
    $(".nav_b ul li").click(function(){
        $(this).addClass("active").siblings().removeClass("active");
         var index=$(this).index();
         $(".canvbox").children().eq(index).show().siblings().hide();
    });
    $(".cancel").click(function(){
        $(".masks,.tip_s_z,.tip_s_d").hide()
        $(".cart_product_num").val(1);
    });
    $(".go_zz").click(function(){
        $(".masks,.tip_s_z").show()
    });
    $(".go_dd").click(function(){
        $(".masks,.tip_s_d").show()
    });
    $(".go_desc").click(function(){
        if ($(this).hasClass("active0")){
            $(this).removeClass('active0')
        }
        else {
            $(this).addClass('active0')
        }
        $(this).parent().parent().find(".jy_xq").toggle()
    });

    $(document).ready(function(){
        $("[quantity]").click(function(){
            var quantity = parseInt($(".cart_product_num").val(), 10);
            if($(this).attr("quantity") == '+'){
                quantity += 1;
            }else{
                quantity -= 1;
            }
            if(quantity < 1){
                quantity = 1;
            }
            $(".cart_product_num").val(quantity);
        });
    });

});

