//--------------------------------------------必须重写的原型----------------------------------------------------------------------------

//创建一个新实例
function InitCarouselControl()
{
  return new CarouselControl();

}

function CarouselJsonToObj(element) {
    var obj = new CarouselControl();
    obj.ShowWay = element.ShowWay;
    obj.Height = element.Height;
    obj.Item = new Array();
    if (element.Item != null && element.Item.length > 0) {
        for (var i = 0; i < element.Item.length; i++) {
            var m = new CarouselItem();
            m.Link = element.Item[i].Link;
            m.Img = element.Item[i].Img;
            m.DisplayText = element.Item[i].DisplayText;
            m.DIVLink = element.Item[i].DIVLink;
            obj.Item.push(m);
        }
    }
    return obj;

}
//图片广告控件
function CarouselControl() {
    this.Type = 'Carousel';
    this.ShowWay = 'Carousel';
    this.Height =12;
    this.Item = new Array();
   
    this.ID='Control' + $.CreateID();
   
}

CarouselControl.prototype = new BaseControl();

//渲染HTML
CarouselControl.prototype.Html = function () {
    return juicer($("#" + this.Type+"Control").html(), { Model: this})
   
};


//展示
CarouselControl.prototype.Show = function () {
    var currentControl = this;
    var targetView = $("#MyBox").find("div[data-id='" + currentControl.ID + "']");
    var top = targetView.offset().top - $(".diy-container-layout").offset().top;
    $(".diy-ctrl").css("top", top + 'px')
    if (this.Item.length <= 0) {
        var controlItem = new CarouselItem();
        currentControl.Add(controlItem);
        page.Set(currentControl);
        targetView.find(".draggable1").attr("data-id", controlItem.ID);
    }
    $(".diy-ctrl").html(juicer($("#" + this.Type + "Edit-template").html(), { Model: currentControl, DataModel: DataModel }));
   
}

//操作监听
CarouselControl.prototype.ReadyListen = function () {

    this.ExtendToShowWay();//监听显示方式动作
   // this.ExtendToSetHeight();//监听高度调整
    this.ExtendToAddItem();//添加控件项
    this.Delete();
    this.Draggable();
    this.Upload();
    this.InputChange();
    this.NavigateChange();
};

//--------------------------------------------必须重写的原型----------------------------------------------------------------------------

//--------------------------------------------控件扩展----------------------------------------------------------------------------
//显示方式动作
CarouselControl.prototype.ExtendToShowWay = function ()
{
   // this.Height = 100;
    var currentControl = this;
    var self = $("#" + currentControl.Type + "ItemPanel");
    var id = currentControl.ID;
    var targetView = $("#MyBox").find("div[data-id='" + id + "']");
 
    self.find('[name="showWay"]').unbind("change");
    self.find('[name="showWay"]').on("change", function () {
     
       // currentControl.Height = 10;
        if ($(this).val() == 'Tile') { //平铺
            currentControl.ShowWay = 'Tile';
            targetView.find(".diy-swipe_time").hide();
            targetView.find(".diy-swipe").removeClass("swipe-df");
        }
        else {//轮播
            currentControl.ShowWay = 'Carousel';
            targetView.find(".diy-swipe_time").show();
            targetView.find(".diy-swipe").addClass("swipe-df");
        }
       
        page.Set(currentControl);

    });
 
}




//显示编辑面板
CarouselControl.prototype.ExtendToAddItem = function () {
    
    var currentControl = this;
  
    $('.diy-ctrl').find(".addbtn-block").unbind("click")
    $('.diy-ctrl').find(".addbtn-block").bind("click",function (e) {
        var controlItem = new CarouselItem();
       
        currentControl.Add(controlItem);
     
        page.Set(currentControl);
        var html = juicer($("#" + currentControl.Type + "Item-template").html(), { Model: controlItem, DataModel: DataModel });
      
        $("#ItemList").append(html);
        var targetView = $("#MyBox").find("div[data-id='" + currentControl.ID + "']");
        if (currentControl.Item.length > 1)
        {
            targetView.find(".diy-swipe_time").append("<span ></span>");
            var htmlLi = juicer($("#" + currentControl.Type + "ListControl").html(), { Model: controlItem });
            targetView.find(".picList").append(htmlLi);
        } else {
            targetView.find(".picList").find("li:first").attr("data-id", controlItem.ID);
            
        }
        
        currentControl.InputChange();
        currentControl.NavigateChange();
    });

   
  
};

//移除某一个项
CarouselControl.prototype.Delete = function () {
    var currentControl = this;
    $("#ItemList").delegate(".icon-trash","click",function (e) {
        e.preventDefault();
        var id = $(this).parents("li").attr("data-id");
        $(this).parents("li").remove();
        currentControl.Remove(id);
        page.Set(currentControl);
        var targetView = $("#MyBox").find("div[data-id='" + currentControl.ID + "']");
        if (currentControl.Item.length>0)
        {
            targetView.find(".diy-swipe_time").find("span:last").remove();
            targetView.find(".picList").find("li[data-id='" + id + "']").remove();
        } else {
            targetView.find(".picList").find("li[data-id='" + id + "']").find("img").attr("src", "/images/zhuangxiu/imgad.jpg");
        }
      
      

    })
}

//子项拖拽
CarouselControl.prototype.Draggable = function () {
    var currentControl = this;
    $(".diy-ctrl").find(".diy-media-list").sortable({
        selector: ".acts-header .icon-expand-full",
        revert: true,
        stop: function (e,t) {
            currentControl.Sort();
        }
    });
}


//排序
CarouselControl.prototype.Sort = function () {
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
CarouselControl.prototype.Upload = function () {
  
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
CarouselControl.prototype.UploadFinish = function (imgUrl) {
    currentControl = this;
    var item = $.UploadingControlItem.UploadingControlItem;
    item.Img = imgUrl;
    currentControl.Set(item);
    page.Set(currentControl);
    var targetView = $("#MyBox").find("div[data-id='" + currentControl.ID + "']");
    targetView.find("li[data-id='" + item.ID + "']").find("img").attr("src", imgUrl);
}

//跳转链接改变
CarouselControl.prototype.NavigateChange = function () {
    var currentControl = this;
    $("#ItemList").find(".dropdown-menu").find("a").unbind("click");
    $("#ItemList").find(".dropdown-menu").find("a").bind("click", function (e) {
        e.preventDefault();
        var opt = this;
        var itemID = $(this).parents(".Item").attr("data-id");
        var item = currentControl.Get(itemID);
        if ($(this).attr("data-Url")=="-1")
        {
            $(this).parents(".form-horizontal").find(".inputInfo").find("input[name='Link']").val("");
            $(this).parents(".form-horizontal").find(".selectInfo").css("display", "none");
            $(this).parents(".form-horizontal").find(".inputInfo").css("display", "");
            item.DIVLink = "1";
          
        } else if ($(this).attr("data-Url") == "-2") {

            $("#ModalDIVPage").addClass("in");
            $('.addDIVPage').unbind("click")
            $('#DIYPageList').delegate(".addDIVPage", "click", function (e) {
                var tem = null;
                var tid = $(this).attr("data-TemplateID");
                if (DataModel.DIYPages != null) {
                    for (var i = 0; i < DataModel.DIYPages.length; i++) {
                        if (DataModel.DIYPages[i].TemplateID == tid) {
                            tem = DataModel.DIYPages[i];
                            item.DisplayText = tem.Title;
                            item.Link = tem.Url;
                            item.LinkText = tem.Title;
                            var targetView = $("#MyBox").find("div[data-id='" + currentControl.ID + "']");
                            targetView.find("li[data-id='" + item.ID + "']").find(".diy-nav__label").html(tem.Title);
                            $(opt).parents(".form-horizontal").find(".selectInfo").find(".text-info").html(tem.Title);
                            $(opt).parents(".media-body").find("input[name='DisplayText']").val(tem.Title);
                        }
                    }
                }

            });
        } else if ($(this).attr("data-Url") == "-3") {
        
            $('[goodList]').undelegate(".AddGoods", "click")
            $('.AddGoods').bind("click", function (e) {
                var goods = null;
                var productID = $(this).attr("data-ProductID");
                for (var i = 0; i < DataModel.Goods.length; i++) {
                    if (DataModel.Goods[i].ProductID == productID) {
                        goods = DataModel.Goods[i];
                    }
                }

                item.DisplayText = goods.ProductName;
                item.DIVLink = "0";
                if (objectType==2)
                {
                    if (Platform == "WxAPP") {
                        item.Link = "/pages/details/details?productId=" + productID + "&shopID=" + objectID;
                    } else {
                        item.Link = "/Views/Product/ProductInfo.html?productId=" + productID + "&shopID=" + objectID;
                    }
                    
                } else {
                    if (Platform == "WxAPP") {
                        item.Link = "/pages/details/details?productId=" + productID;
                    } else {
                        item.Link = "/Views/Product/ProductInfo.html?productId=" + productID;;
                    }
                    
                }
               
             
                $(opt).parents(".form-horizontal").find(".selectInfo").css("display", "");
                $(opt).parents(".form-horizontal").find(".inputInfo").css("display", "none");
               
                $(opt).parents(".form-horizontal").find(".selectInfo").find(".text-info").html(item.DisplayText);
               

            });
        } else if ($(this).attr("data-Url") == "-4") {
            
            $('#ModalCategory').undelegate(".setcategory", "click")
           
            $('#ModalCategory').delegate(".setcategory", "click", function (e) {
                var goods = null;
                var catalogid = $(this).attr("data-catalogid");
                var catalogtitle = $(this).attr("data-title");

                item.DisplayText = catalogtitle;
                item.DIVLink = "0";
                if (objectType == 2) {
                    if (Platform == "WxAPP")
                    {
                        item.Link = "/pages/goodsList/goodsList?CatalogId=" + catalogid + "&shopID=" + objectID;
                    } else {
                        item.Link = "/Views/Product/ProductList.html?CatalogId=" + catalogid + "&shopID=" + objectID;
                    }
                    
                } else {
                    if (Platform == "WxAPP") {
                        item.Link = "/pages/goodsList/goodsList?CatalogId=" + catalogid + "&shopID=" + objectID;
                    } else {
                        item.Link = "/Views/Product/ProductList.html?CatalogId=" + catalogid;;
                    }
                  
                }
               

                $(opt).parents(".form-horizontal").find(".selectInfo").css("display", "");
                $(opt).parents(".form-horizontal").find(".inputInfo").css("display", "none");

                $(opt).parents(".form-horizontal").find(".selectInfo").find(".text-info").html(item.DisplayText);
              

            });
        } else {
            var displayText = $(this).html();
            var navigateUrl = $(this).attr("data-Url");

            $(this).parents(".form-horizontal").find(".selectInfo").css("display", "");
            $(this).parents(".form-horizontal").find(".inputInfo").css("display", "none");
            $(this).parents(".form-horizontal").find(".selectInfo").find(".text-info").html(displayText);

            item.DisplayText = displayText;
            item.DIVLink = "0";
            item.Link = navigateUrl;


        }

        currentControl.Set(item);
        page.Set(currentControl);
      

    })
}

//子项输入改变
CarouselControl.prototype.InputChange = function () {
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
}
//--------------------------------------------控件扩展----------------------------------------------------------------------------


