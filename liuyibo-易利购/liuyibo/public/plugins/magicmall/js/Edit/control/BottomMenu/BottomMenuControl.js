//底部菜单控件

//--------------------------------------------必须重写的原型----------------------------------------------------------------------------

//创建一个新实例
function InitBottomMenuControl() {
    return new BottomMenuControl();

}

function BottomMenuJsonToObj(element) {
    var obj = new BottomMenuControl();
    obj.ShowWay = element.ShowWay;
    obj.Fixed = element.Fixed;
    obj.Item = new Array();
    if (element.Item != null && element.Item.length > 0) {
        for (var i = 0; i < element.Item.length; i++) {
            var m = new BottomMenuItem();
            m.Link = element.Item[i].Link;
            m.Img = element.Item[i].Img;
            m.DisplayText = element.Item[i].DisplayText;
            m.FontColor = element.Item[i].FontColor;
            m.BackgroudColor = element.Item[i].BackgroudColor;
            m.DIVLink = element.Item[i].DIVLink;
            m.LinkText = element.Item[i].LinkText;
            obj.Item.push(m);
        }
    }
    return obj;

}

function BottomMenuControl() {
    this.Type = 'BottomMenu';
    this.ShowWay = 'both';
    this.Fixed = 'yes';
    this.Item = new Array();
    this.ID = 'Control' + $.CreateID();

}

BottomMenuControl.prototype = new BaseControl();

//渲染HTML
BottomMenuControl.prototype.Html = function () {
    return juicer($("#" + this.Type + "Control").html(), { Model: this })

};



//展示
BottomMenuControl.prototype.Show = function () {
    var currentControl = this;
    var id = currentControl.ID;
    var targetView = $("#MyBox").find("div[data-id='" + id + "']");

    var top = targetView.offset().top - $(".diy-container-layout").offset().top;
    $(".diy-ctrl").css("top", top + 'px')

    if (this.Item.length <= 0) {
        var controlItem1 = new BottomMenuItem();
        controlItem1.DisplayText = "商城首页";
      
       controlItem1.Link = "/Views/Home/Index.html?Platform=" + Platform + "&ObjectType=" + objectType + "&ObjectID=" + objectID + "&CustID=" + CustID + "&shopID=" + objectID;
       

        controlItem1.LinkText = "商城首页";
        controlItem1.Img = "http://custui.winmobi.cn/images/tabbar_icon01_lh.png";
        currentControl.Add(controlItem1);
        targetView.find("li:eq(0)").attr("data-id", controlItem1.ID);
        targetView.find("li[data-id='" + controlItem1.ID + "']").find(".diy-nav__label").html(controlItem1.DisplayText);
        targetView.find("li[data-id='" + controlItem1.ID + "']").find(".diy-nav__icon").find("img").attr("src", controlItem1.Img);

        var controlItem2 = new BottomMenuItem();
        controlItem2.DisplayText = "购物车";
        controlItem2.LinkText = "购物车";
        controlItem2.Link = "/views/shopcart/index.html";
        controlItem2.Img = "http://custui.winmobi.cn/images/tabbar_icon02_lh.png";
        currentControl.Add(controlItem2);
        targetView.find("li:eq(1)").attr("data-id", controlItem2.ID);
        targetView.find("li[data-id='" + controlItem2.ID + "']").find(".diy-nav__label").html(controlItem2.DisplayText);
        targetView.find("li[data-id='" + controlItem2.ID + "']").find(".diy-nav__icon").find("img").attr("src", controlItem2.Img);

        var controlItem3 = new BottomMenuItem();
        controlItem3.DisplayText = "订单中心";
        controlItem3.LinkText = "订单中心";
        controlItem3.Link = "/views/order/orderList.html";
        controlItem3.Img = "http://custui.winmobi.cn/images/tabbar_icon03_lh.png";
        currentControl.Add(controlItem3);
        targetView.find("li:eq(2)").attr("data-id", controlItem3.ID);
        targetView.find("li[data-id='" + controlItem3.ID + "']").find(".diy-nav__label").html(controlItem3.DisplayText);
        targetView.find("li[data-id='" + controlItem3.ID + "']").find(".diy-nav__icon").find("img").attr("src", controlItem3.Img);

        var controlItem4 = new BottomMenuItem();
        controlItem4.DisplayText = "个人中心";
        controlItem4.LinkText = "个人中心";
        controlItem4.Link = "/views/member/mycenter.html";
        controlItem4.Img = "http://custui.winmobi.cn/images/tabbar_icon04_lh.png";
        currentControl.Add(controlItem4);
        targetView.find("li:eq(3)").attr("data-id", controlItem4.ID);
        targetView.find("li[data-id='" + controlItem4.ID + "']").find(".diy-nav__label").html(controlItem4.DisplayText);
        targetView.find("li[data-id='" + controlItem4.ID + "']").find(".diy-nav__icon").find("img").attr("src", controlItem4.Img);

        page.Set(currentControl);
    }
    $(".diy-ctrl").html(juicer($("#" + this.Type + "Edit-template").html(), { Model: this, DataModel: DataModel }));

}

//操作监听
BottomMenuControl.prototype.ReadyListen = function () {

    this.ExtendToShowWay();//监听显示方式动作
    this.ExtendToSetFixed();//监听高度调整
    this.ExtendToAddItem();//添加控件项
    this.Delete();
    this.Draggable();
    this.Upload();
    this.InputChange();
    this.NavigateChange();
};

//--------------------------------------------必须重写的原型----------------------------------------------------------------------------

//--------------------------------------------控件扩展----------------------------------------------------------------------------
//导航类型
BottomMenuControl.prototype.ExtendToShowWay = function () {

    var currentControl = this;
    var self = $("#" + currentControl.Type + "ItemPanel");
    var id = currentControl.ID;
    var targetView = $("#MyBox").find("div[data-id='" + id + "']");

    self.find('[name="showWay"]').unbind("change");
    self.find('[name="showWay"]').on("change", function () {
        var type = $(this).val();
        switch (type) {
            case 'text':
                targetView.find('.diy-nav__icon').removeClass('inline_show');
                targetView.find('.diy-nav__label').show();
                break;
            case 'ico':
                targetView.find('.diy-nav__icon').addClass('inline_show');
                targetView.find('.diy-nav__label').hide();
                break;
            case 'both':
                targetView.find('.diy-nav__icon').addClass('inline_show');
                targetView.find('.diy-nav__label').show();
                break;
        }
        currentControl.ShowWay = type;
        page.Set(currentControl);

    });

}


//是否固定
BottomMenuControl.prototype.ExtendToSetFixed = function () {
    var currentControl = this;
    var self = $("#" + currentControl.Type + "ItemPanel");
    var id = currentControl.ID;
    var targetView = $("#MyBox").find("div[data-id='" + id + "']");
    self.find('input[name="rdfix"]').unbind("change");
    self.find('input[name="rdfix"]').on("change", function () {
        var fixed = $(this).val();
        switch (fixed) {
            case 'yes':
                targetView.addClass("bottom-nav");
                targetView.attr("style", " ");
                break;
            case 'no':
                targetView.removeClass("bottom-nav");
                break;

        }

        currentControl.Fixed = fixed;
        page.Set(currentControl);
    });


}

//显示编辑面板
BottomMenuControl.prototype.ExtendToAddItem = function () {

    var currentControl = this;

    $('.diy-ctrl').find(".addbtn-block").unbind("click")
    $('.diy-ctrl').find(".addbtn-block").bind("click", function (e) {
        var controlItem = new BottomMenuItem();
        if (currentControl.Item != null && currentControl.Item.length >= 4) {
            $.ShowErrMessage("菜单项最多只能设置四个");
            return;
        }
        controlItem.Img = '/images/zhuangxiu/placeholder.png';
        currentControl.Add(controlItem);

        page.Set(currentControl);
        var html = juicer($("#" + currentControl.Type + "Item-template").html(), { Model: controlItem, DataModel: DataModel });

        $("#ItemList").append(html);
        var targetView = $("#MyBox").find("div[data-id='" + currentControl.ID + "']");

        var htmlLi = juicer($("#" + currentControl.Type + "ListControl").html(), { Model: controlItem, Control: currentControl });
        targetView.find(".picList").append(htmlLi);

        currentControl.InputChange();
        currentControl.NavigateChange();
    });

};

//移除某一个项
BottomMenuControl.prototype.Delete = function () {
    var currentControl = this;
    $("#ItemList").delegate(".icon-trash", "click", function (e) {
        e.preventDefault();
        var id = $(this).parents("li").attr("data-id");
        $(this).parents("li").remove();
        currentControl.Remove(id);
        page.Set(currentControl);
        var targetView = $("#MyBox").find("div[data-id='" + currentControl.ID + "']");
        targetView.find(".picList").find("li[data-id='" + id + "']").remove();

    })
}

//子项拖拽
BottomMenuControl.prototype.Draggable = function () {
    var currentControl = this;
    $(".diy-ctrl").find(".diy-media-list").sortable({
        selector: ".acts-header .icon-expand-full",
        revert: true,
        stop: function (e, t) {
            currentControl.Sort();
        }
    });
}


//排序
BottomMenuControl.prototype.Sort = function () {
    var currentControl = this;


    if (currentControl.Item.length > 0) {
        var newArray = new Array();

        $("#ItemList").children("li").each(function (index, element) {
            var id = $(element).attr("data-id");
            var controlItem = currentControl.Get(id);


            if (controlItem != undefined && controlItem != null) {
                newArray.push(controlItem);
            }
        });

        currentControl.Item = newArray;

        page.Set(currentControl);

    }

    return page;
}

//
BottomMenuControl.prototype.Upload = function () {

    var currentControl = this;
    var self = $("#" + currentControl.Type + "ItemPanel");
    var id = currentControl.ID;
    var targetView = $("#MyBox").find("div[data-id='" + id + "']");
    var $li = null;
    $(".configurator").delegate(".openFile", "click", function (e) {
        var evt = document.createEvent("MouseEvents");
        evt.initEvent("click", false, false);// 或用initMouseEvent()，不过需要更多参数 
        $(".webuploader-element-invisible").get(0).dispatchEvent(evt);
        $li = $(this).parents("li");
        var itemID = $li.attr("data-id");
        var item = currentControl.Get(itemID);
        $.UploadingControlItem.UploadingControlItem = item;
        $.UploadingControlItem.UploadingControl = currentControl;
        $.UploadingControlItem.li = $li;

    })

}
BottomMenuControl.prototype.UploadFinish = function (imgUrl) {
    currentControl = this;
    var item = $.UploadingControlItem.UploadingControlItem;
    item.Img = imgUrl;
    currentControl.Set(item);
    page.Set(currentControl);
    var targetView = $("#MyBox").find("div[data-id='" + currentControl.ID + "']");
    targetView.find("li[data-id='" + item.ID + "']").find("img").attr("src", imgUrl);
}



//子项输入改变
BottomMenuControl.prototype.InputChange = function () {

    var currentControl = this;
    $("#ItemList").find("input[name='Link']").unbind("blur");
    $("#ItemList").find("input[name='Link']").bind("blur", function (e) {
        e.preventDefault();

        var navigateUrl = $(this).val();
        if (!$.TestUrl(navigateUrl)) {

            $.ShowErrMessage("请输入http开头的链接");
            return;
        }
        var itemID = $(this).parents(".Item").attr("data-id");
        var item = currentControl.Get(itemID);
        item.Link = navigateUrl;
        currentControl.Set(item);
        page.Set(currentControl);
    })



    $("#ItemList").find("input[name='DisplayText']").unbind("blur");
    $("#ItemList").find("input[name='DisplayText']").bind("blur", function (e) {
        e.preventDefault();

        var displayText = $(this).val();

        var itemID = $(this).parents(".Item").attr("data-id");
        var item = currentControl.Get(itemID);
        item.DisplayText = displayText;
        currentControl.Set(item);
        page.Set(currentControl);

        var targetView = $("#MyBox").find("div[data-id='" + currentControl.ID + "']");
        targetView.find("li[data-id='" + item.ID + "']").find(".diy-nav__label").html(displayText);

    })


    $('.colorpicker-default').colorpicker({
        format: 'hex'
    });

    var self = $("#ItemList").children("li");
    self.find('.add-on-FontColor').on('click', function () {
        $(this).parents(".colorpicker-default").find('[name="FontColor"]').focus();

    });

    self.find('input[name="FontColor"]').on("blur", function () {
        var fontColor = $(this).val();
        var itemID = $(this).parents(".Item").attr("data-id");
        var item = currentControl.Get(itemID);
        item.FontColor = fontColor;
        currentControl.Set(item);
        page.Set(currentControl);

        var targetView = $("#MyBox").find("div[data-id='" + currentControl.ID + "']");
        targetView.find("li[data-id='" + item.ID + "']").find(".diy-nav__label").css("color", fontColor);
    });

    self.find('input[name="FontColorInput"]').on("blur", function () {
        var fontColor = $(this).val();
        var itemID = $(this).parents(".Item").attr("data-id");
        var item = currentControl.Get(itemID);
        item.FontColor = fontColor;
        currentControl.Set(item);
        page.Set(currentControl);

        var targetView = $("#MyBox").find("div[data-id='" + currentControl.ID + "']");
        targetView.find("li[data-id='" + item.ID + "']").find(".diy-nav__label").css("color", fontColor);
        $(this).parents(".input-append").find("i").css("background-color", $(this).val());
    });

    self.find('.add-on-BackgroudColor').on('click', function () {
        $(this).parents(".colorpicker-default").find('[name="BackgroudColor"]').focus();

    });


    self.find('input[name="BackgroudColor"]').on("blur", function () {
        var backgroundcolor = $(this).val();
        var itemID = $(this).parents(".Item").attr("data-id");
        var item = currentControl.Get(itemID);
        item.BackgroudColor = backgroundcolor;
        currentControl.Set(item);
        page.Set(currentControl);

        var targetView = $("#MyBox").find("div[data-id='" + currentControl.ID + "']");
        targetView.find("li[data-id='" + item.ID + "']").css("background-color", backgroundcolor);
    });

    self.find('input[name="BackgroudColorInput"]').on("blur", function () {
        var backgroundcolor = $(this).val();
        var itemID = $(this).parents(".Item").attr("data-id");
        var item = currentControl.Get(itemID);
        item.BackgroudColor = backgroundcolor;
        currentControl.Set(item);
        page.Set(currentControl);
        $(this).parents(".input-append").find("i").css("background-color", $(this).val());
        var targetView = $("#MyBox").find("div[data-id='" + currentControl.ID + "']");
        targetView.find("li[data-id='" + item.ID + "']").css("background-color", backgroundcolor);
    });

}

//跳转链接改变
BottomMenuControl.prototype.NavigateChange = function () {
    var currentControl = this;
    $("#ItemList").find(".dropdown-menu").find("a").unbind("click");
    $("#ItemList").find(".dropdown-menu").find("a").bind("click", function (e) {
        e.preventDefault();
        var opt = this;
        var itemID = $(this).parents(".Item").attr("data-id");
        var item = currentControl.Get(itemID);
        if ($(this).attr("data-Url") == "-1") {
            $(this).parents(".form-horizontal").find(".selectInfo").css("display", "none");
            $(this).parents(".form-horizontal").find(".inputInfo").css("display", "");
            item.DIVLink = "1";
            $(this).parents(".form-horizontal").find(".inputInfo").find("input[name='Link']").val("");
            $(this).parents(".media-body").find("input[name='DisplayText']").val("");

        } else if ($(this).attr("data-Url") == "-2") {

            $("#ModalDIVPage").addClass("in");
            $('.addDIVPage').unbind("click")
            $('.addDIVPage').bind("click", function (e) {
                var tem = null;
                var tid = $(this).attr("data-TemplateID");
                if (DataModel.DIYPages != null) {
                    for (var i = 0; i < DataModel.DIYPages.length; i++) {
                        if (DataModel.DIYPages[i].TemplateID == tid) {
                            tem = DataModel.DIYPages[i];
                           
                            item.DIVLink = "0";
                            item.Link = tem.Url;
                            item.LinkText = tem.Title;
                            var targetView = $("#MyBox").find("div[data-id='" + currentControl.ID + "']");
                            targetView.find("li[data-id='" + item.ID + "']").find(".diy-nav__label").html(tem.Title);
                            $(opt).parents(".Item").find(".selectInfo").find(".text-info").html(tem.Title);
                            if ($(this).parents(".media-body").find("input[name='DisplayText']").val() == "") {

                                item.DisplayText = tem.Title;
                                $(opt).parents(".Item").find("input[name='DisplayText']").val(tem.Title);
                            }
                           
                        }
                    }
                }

            });
        } else {
            var displayText = $(this).html();
            var navigateUrl = $(this).attr("data-Url");
          
            $(this).parents(".form-horizontal").find(".selectInfo").css("display", "");
            $(this).parents(".form-horizontal").find(".inputInfo").css("display", "none");
            $(this).parents(".form-horizontal").find(".selectInfo").find(".text-info").html(displayText);
         
            if ($(this).parents(".media-body").find("input[name='DisplayText']").val() == "") {
                $(this).parents(".media-body").find("input[name='DisplayText']").val(displayText);
                item.DisplayText = displayText;
            }
         
            item.Link = navigateUrl;
            item.LinkText = displayText;
            item.DIVLink = "0";
            var targetView = $("#MyBox").find("div[data-id='" + currentControl.ID + "']");
            targetView.find("li[data-id='" + item.ID + "']").find(".diy-nav__label").html(displayText);

        }

        currentControl.Set(item);
        page.Set(currentControl);

    });


}
//--------------------------------------------控件扩展----------------------------------------------------------------------------


