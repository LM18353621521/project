var timestart = "1900-01-01 14:52:00";
var timeout = "1900-01-01 14:52:00";
var isSchedule = false;
var currentSchedule = "";
$(document).ready(function () {
    var template = new Template();
    template.LoadDataFromServer(template.BindPage);
    template.BindDefault();
    template.MakeQRCode(link);
    template.Enable();
    template.Delete();
   
    
});

function Template()
{
}



Template.prototype.LoadDataFromServer = function (callBack) {
    var template = this;
    $.get('/index.php?m=Admin&c=Plugins.Plugins&a=ajaxRenovationList', { Platform: Platform, type: Type }, function (data) {
        callBack(data);
    },'json');
}


//模板列表
Template.prototype.BindPage=function(list)
{
    template = this;
    if(list != null  && list.length>0)
    {
        $.each(list,function(index,element){
            element.data=utf8to16(base64decode(element.data));

        });
    }
    var pageHtml = juicer($("#tmplate-table").html(), { Model: list });
    $("#PageList").html(pageHtml);
    $("#PageList").find(".phone-body").find("a").attr("href", "javascript:void(0)");
    InitSwiper();
    SecondKillExcuteTime();
    $("#linkAdd").attr("href", "/index.php?m=Admin&c=Plugins.Plugins&a=renovation&Platform=" + Platform + "&type=" + Type);
}
//默认选中模板
Template.prototype.BindDefault = function (list) {
    template = this;
    $.get('/index.php?m=Admin&c=Plugins.Plugins&a=ajaxRenovation', { Platform: Platform, type: Type }, function (data) {
        var m = data;
        if (m != null && m != undefined && m != "") {
            var html = m.data;
            $("#EditTemplate").html(utf8to16(base64decode(html)));

            linkUrl="/index.php?m=Admin&c=Plugins.Plugins&a=renovation&id=" + m.id ;
            $("#currentEditTemplate").css("display", "");
            $("#currentEditTemplate").find(".phone-body").find("a").attr("href","javascript:void(0)");
            InitSwiper();
            SecondKillExcuteTime();
        }
    }, 'json');
    
}


var linkUrl = "";
function LinkTo(ev)
{
    var oEvent = ev || event;
    oEvent.cancelBubble = true;
    oEvent.stopPropagation();
    if (linkUrl == null || linkUrl == undefined || linkUrl.trim() == "")
    {
        
        var myMessager = new $.zui.Messager({ type: 'warning' });

        myMessager.show('请稍后')
        return;
    }
    window.location = linkUrl;
}

Template.prototype.MakeQRCode = function (link) {
    //if (Platform == "WxAPP") {
        //$("#address").css("display", "none");
        //$.get('/Template/CreateCode', { link:link }, function (data) {
        //    $("#code").html("<img src='" + data + "' width='120' height='120'/>");
         
        //}, 'text');
       
    //} else {
        $("#address").html(link);
        var q = document.getElementById("code");
        qrcode = new QRCode(q, {
            width: 120,
            height: 120
        });
        qrcode.makeCode(link);
    //}
   
   
}

Template.prototype.Enable = function () {

    $("#PageList").delegate(".SetDefault", "click", function (e) {
        var templateID = $(this).parents(".pageInfo").attr("data-TemplateID");
        var html = $(this).parents(".pageInfo").find(".content").html();
        $.post('/Template/Enable', { templateID: templateID, Platform: Platform, type: Type }, function (data) {
            //$("#EditTemplate").html(html);
            //$("#link").attr("href", "/Template/Edit/" + templateID);
          
            var myMessager = new $.zui.Messager({ type: 'success' });

            myMessager.show('应用成功')
            window.location = "/Template/index?Platform=" + Platform + "&type=" + Type;
        }, 'json');
    });

}

Template.prototype.Delete = function () {

    $("#PageList").delegate(".deleteBtn", "click", function (e) {
        if(confirm("确定删除吗"))
        {
            //var templateID = $(this).parents(".pageInfo").attr("data-TemplateID");
            var id = $(this).parents(".pageInfo").attr("data-id");
            $.post('/index.php?m=Admin&c=Plugins.Plugins&a=delete', { id: id }, function (data) {
                window.location = "/index.php?m=Admin&c=Plugins.Plugins&a=renovationIndex";
            }, 'json');
        }
    });

}


function InitSwiper() {
    var mySwiper = new Swiper('.swiper-container', {
        loop: true,
        pagination: '.swiper-pagination',
        autoplay: 4000
    })

    var swiper1 = new Swiper('.swiper-containerSecondKill', {
        slidesPerView: 'auto',
        spaceBetween: 0,
        pagination: {
            el: '.swiper-pagination',
            clickable: true,
        },
    });

}

function SecondKillExcuteTime() {
   
    $("[timer]").each(function () {
        $(this).StartTimer({ timestart: timestart, timeout: timeout });
    });
    //if(isSchedule)
    //{
    //    $("[nextschedule]").html(currentSchedule + "点档");
    //    $("[nextschedule]").css("display", "");
    //}

}



