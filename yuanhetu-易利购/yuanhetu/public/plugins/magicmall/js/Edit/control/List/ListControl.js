//商品列表组件
//--------------------------------------------必须重写的原型----------------------------------------------------------------------------

//创建一个新实例
function InitListControl() {
    return new ListControl();

}

function ListJsonToObj(element) {
    var obj = new ListControl();
    obj.ShowWay = element.ShowWay;
    obj.ShowProductName = element.ShowProductName;
    obj.ShowPrice = element.ShowPrice;
    obj.ShowOldPrice = element.ShowOldPrice;
   
    obj.Item = new Array();
    if (element.Item != null && element.Item.length > 0) {
        for (var i = 0; i < element.Item.length; i++) {
            var m = new ListItem();
            m.Img = element.Item[i].Img;
            m.ProductName = element.Item[i].ProductName;
            m.Price = element.Item[i].Price;
            m.Code = element.Item[i].Code;
            m.Platform = element.Item[i].Platform;
            m.ProductID = element.Item[i].ProductID;
            m.OldPrice = element.Item[i].OldPrice;
           
            obj.Item.push(m);
        }
    }
    return obj;

}

function ListControl() {
    this.Type = 'List';
    this.ShowWay = '4';
    this.Parameter = '';
    this.ShowProductName = 1;
    this.ShowPrice = 1;
    this.ShowOldPrice = 1;
    this.Item = new Array();
    this.ID = 'Control' + $.CreateID();
   
   
}

ListControl.prototype = new BaseControl();

//渲染HTML
ListControl.prototype.Html = function () {
    return juicer($("#" + this.Type + "Control").html(), { Model: this })

};


//展示
ListControl.prototype.Show = function () {
    var currentControl = this;
    var id = currentControl.ID;
    var targetView = $("#MyBox").find("div[data-id='" + id + "']");
    var top = targetView.offset().top - $(".diy-container-layout").offset().top;
    $(".diy-ctrl").css("top", top + 'px')
    if (this.Item.length <= 0) {
        var loop = DataModel.Goods.length;
        if (loop>4)
        {
            loop = 4;
        }
        var html = "";
      
        for (var i = 0; i < loop; i++) {
            var m = new ListItem();
            var element = DataModel.Goods[i];
            m.Img = element.Img;
            m.ProductName = element.ProductName;
            m.Price = element.Price;
            m.Code = element.Code;
            m.Platform = element.Platform;
            m.ProductID = element.ProductID;
            m.OldPrice = element.OldPrice;
           
            currentControl.Add(m);
            html += juicer($("#ListListControl").html(), { Model: m, Control: currentControl });
            targetView.find(".picList").html(html);
        }
        currentControl.ShowProductName = 1;
        currentControl.ShowPrice = 1;
        currentControl.ShowOldPrice = 1;
        page.Set(currentControl);
    }
    $(".diy-ctrl").html(juicer($("#" + this.Type + "Edit-template").html(), { Model: this }));

   
  
   

}

//操作监听
ListControl.prototype.ReadyListen = function () {

    this.ExtendToShowWay();//监听显示方式动作
    this.ExtendToSetParameter();//监听显示参数
    this.ExtendToAddItem();//添加控件项
    this.Delete();
    this.Draggable();


};

//--------------------------------------------必须重写的原型----------------------------------------------------------------------------

//--------------------------------------------控件扩展----------------------------------------------------------------------------
//展示类型
ListControl.prototype.ExtendToShowWay = function () {

    var currentControl = this;
    var self = $("#" + currentControl.Type + "ItemPanel");
    var id = currentControl.ID;
    var targetView = $("#MyBox").find("div[data-id='" + id + "']");
   
    self.find('[name="ShowWay"]').unbind("change");
    self.find('[name="ShowWay"]').on("change", function () {
        var type = $(this).val();
       
       
        targetView.find(".diy-goods ul").attr("class", 'goods-list-type' + type);
        currentControl.ShowWay = type;
        page.Set(currentControl);

    });

}


//显示参数
ListControl.prototype.ExtendToSetParameter = function () {
    var currentControl = this;
    var self = $("#" + currentControl.Type + "ItemPanel");
    var id = currentControl.ID;
    var targetView = $("#MyBox").find("div[data-id='" + id + "']");
    self.find('[name="ShowProductName"]').unbind("click");
    self.find('[name="ShowProductName"]').on("click", function () {
     
        if ($(this).is(":checked"))
        {
            currentControl.ShowProductName = 1;
            targetView.find(".goods-name").show()
        } else {
            currentControl.ShowProductName = 0;
            targetView.find(".goods-name").hide()
        }
        page.Set(currentControl);
    });


    self.find('input[name="ShowPrice"]').unbind("click");
    self.find('input[name="ShowPrice"]').on("click", function () {
        if ($(this).is(":checked")) {
            currentControl.ShowPrice = 1;
            targetView.find(".now-price").show()
        } else {
            currentControl.ShowPrice = 0;
            targetView.find(".now-price").hide()
        }
        page.Set(currentControl);
    });

    self.find('input[name="ShowOldPrice"]').unbind("click");
    self.find('input[name="ShowOldPrice"]').on("click", function () {
        if ($(this).is(":checked")) {
            currentControl.ShowOldPrice = 1;
            targetView.find(".old-price").show()
        } else {
            currentControl.ShowOldPrice = 0;
            targetView.find(".old-price").hide()
        }
        page.Set(currentControl);
    });


}

//显示编辑面板
ListControl.prototype.ExtendToAddItem = function () {

    var currentControl = this;

    $('[goodList]').undelegate(".AddGoods", "click")
    $('[goodList]').delegate(".AddGoods", "click", function (e) {
     
        var goods = null;
        var productID = $(this).attr("data-ProductID");
        for (var i = 0; i < currentControl.Item.length; i++) {
            if (currentControl.Item[i].ProductID == productID) {
            
                $.ShowErrMessage("该商品已存在于列表");
                return;
            }
        }
        for (var i = 0; i < DataModel.Goods.length; i++)
        {
            if (DataModel.Goods[i].ProductID == productID)
            {
                goods = DataModel.Goods[i];
            }
        }
        var controlItem = new ListItem();
        controlItem.Img = goods.Img;
        controlItem.ProductName = goods.ProductName;
        controlItem.Price = goods.Price;
        controlItem.Code = goods.Code;
        controlItem.Platform = goods.Platform;
        controlItem.ProductID = goods.ProductID;
        controlItem.OldPrice = goods.OldPrice;
        currentControl.Add(controlItem);

        page.Set(currentControl);
        var html = juicer($("#" + currentControl.Type + "Item-template").html(), { Model: controlItem });

        $("#ItemList").append(html);
        var targetView = $("#MyBox").find("div[data-id='" + currentControl.ID + "']");

        var htmlLi = juicer($("#" + currentControl.Type + "ListControl").html(), { Model: controlItem, Control: currentControl });
        targetView.find(".picList").append(htmlLi);
      
        $.ShowSuccessMessage("添加成功");
    });

};

//移除某一个项
ListControl.prototype.Delete = function () {
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
ListControl.prototype.Draggable = function () {
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
ListControl.prototype.Sort = function () {
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





//--------------------------------------------控件扩展----------------------------------------------------------------------------


