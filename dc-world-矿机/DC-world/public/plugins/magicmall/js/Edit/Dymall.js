//全局page对象
var page = null;
var DataModel = GetDataModel(Platform);
$(document).ready(function () {
    $.UpFileReady();
    var magicMall = new MagicMall();
    //初始化页面 例如加载json绑定数据
  //var pageJson=$.cookie("magicmall");//从缓存读
    page = $.BuildPage($.Decode(PageJson));
    page.ReadyListen();
    $("#MyBox").append(page.Html());
    //加载产品 数据

    //加载拼团数据


    magicMall.Ready();
   
    $("#searchKeybtn").click(function () {
        magicMall.InitProduct(1);
    });
    $("#searchDIY").click(function () {
        magicMall.InitDIYPage(1);
    });
    if(type=="Home")
    {
        $("#cancleLink").attr("href", "/Template/Index?Platform=" + Platform + "&type=" + type);
    } else if (type == "DIY") {
        $("#cancleLink").attr("href", "/Template/DIYPageList?Platform=Mobile");
    }
   
});

function MagicMall() {

}

MagicMall.prototype.LoadData = function (url,dataPara,callBack) {
    $.get(url,dataPara, function (data) {
        callBack(data);
    }, 'json');
}

MagicMall.prototype.PostData = function (url, dataPara, callBack, dataType) {
    if (dataType == null || dataType==undefined)
    {
        dataType = "json";
    }
    $.ajax({
        type: "post",
        url: url,
        timeout : 900000,
        data: dataPara,
        dataType: dataType,
        success: callBack,
        error: function (XMLHttpRequest, textStatus, errorThrown) {
            var i = 0;
        }
    });

}

MagicMall.prototype.LoadData = function (url,dataPara,callBack) {
    $.get(url,dataPara, function (data) {
        callBack(data);
    }, 'json');
}

MagicMall.prototype.PostData = function (url, dataPara, callBack, dataType) {
    if (dataType == null || dataType==undefined)
    {
        dataType = "json";
    }
    $.ajax({
        type: "post",
        url: url,
        timeout : 900000,
        data: dataPara,
        dataType: dataType,
        success: callBack,
        error: function (XMLHttpRequest, textStatus, errorThrown) {
            var i = 0;
        }
    });
   
}




//保存
MagicMall.prototype.Ready = function () {
    var magic = this;
    $(".diy-action-bar").delegate("#save", "click", function (e) {
        var url = "/index.php?m=Admin&c=Plugins.Plugins&a=save";
        var linkTo = "";
        if (templateID>0)
        {
            url = "/Template/EditTemplate";
            linkTo = page.Url;
        } else {
            if (Platform=="WxAPP")
            {
                if (type == "Home") {
                    linkTo = "";
                } else if (type == "DIY") {
                    linkTo = "/pages/DIY/index?t={0}";
                }
            } else
            {
                if (type == "Home") {
                    linkTo = "";
                } else if (type == "DIY") {
                    linkTo = "/Views/DIY/index.html?t={0}";
                }
            }
           
        }
        if (page.Item.length<=0)
        {
            $.ShowErrMessage("请至少拖拽一个组件装修");
          
            return;
        }
        if (page.Title == "" || page.Title == null)
        {
            $.ShowErrMessage("请输入页面标题");
          
            return;
        }
        var pageJson = JSON.stringify(page);
        pageJson =$.Encode(pageJson.trim());
        var pageHtml = $.Encode(page.BuildPhoneHtml());
        var bottomMenu = page.BuildBottomMenuHtml();
        if (bottomMenu != null && bottomMenu!="")
        {
            bottomMenu = $.Encode(bottomMenu);
        }

        var pageList=new Array();

        var dataPara = {};

        //dataPara["templateID"] = templateID;
        dataPara["id"] = id;
        dataPara["data"] = pageHtml;
        dataPara["json"] = pageJson;
        //dataPara["BottomMenu"] = bottomMenu;
        dataPara["code"] = Platform;
        dataPara["name"] = type;
        dataPara["title"] = page.Title;
        dataPara["desc"] = page.Remark;
        dataPara["logo"] = page.Logo;
        dataPara["url"] = linkTo;
      //$.cookie("magicmall",pageJson);
        magic.PostData(url, dataPara, function (data) {

            if (data>0)
            {
                if (type == "Home") {
                    window.location = '/index.php?m=Admin&c=Plugins.Plugins&a=renovation';
                }
            }

        },"text");
       //写cookie
    });


    $(".diy-action-bar").delegate("#Enable", "click", function (e) {

        var url = "/index.php?m=Admin&c=Plugins.Plugins&a=save";
            var linkTo = "";
           
                if (Platform == "WxAPP") {
                    if (type == "Home") {
                        linkTo = "";
                    } else if (type == "DIY") {
                        linkTo = "/pages/DIY/index?t={0}";
                    }
                } else {
                    if (type == "Home") {
                        linkTo = "";
                    } else if (type == "DIY") {
                        linkTo = "/Views/DIY/index.html?t={0}";
                    }
                }

           
            if (page.Item.length <= 0) {
            
                $.ShowErrMessage("请至少拖拽一个组件装修");
                return;
            }
            if (page.Title == "" || page.Title == null) {
              
                $.ShowErrMessage("请输入页面标题");
                return;
            }
            var pageJson = JSON.stringify(page);
            //pageJson = pageJson.trim();
            //var pageHtml = page.BuildPhoneHtml();
            pageJson =$.Encode(pageJson.trim());
            var pageHtml = $.Encode(page.BuildPhoneHtml());
            var bottomMenu = page.BuildBottomMenuHtml();
         
            var dataPara = {};
            //dataPara["templateID"] = templateID;
            dataPara["id"] = id;
            dataPara["data"] = pageHtml;
            dataPara["json"] = pageJson;
            //dataPara["BottomMenu"] = bottomMenu;
            dataPara["code"] = Platform;
            dataPara["name"] = type;
            dataPara["title"] = page.Title;
            dataPara["desc"] = page.Remark;
            dataPara["logo"] = page.Logo;
            dataPara["url"] = linkTo;
            dataPara["status"] = 1;
            //$.cookie("magicmall",pageJson);
            magic.PostData(url, dataPara, function (data) {

                if (data > 0) {
                    window.location = '/index.php?m=Admin&c=Plugins.Plugins&a=renovationIndex';

                }

            },"text");
       
    });

}