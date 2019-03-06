

//--------------------------------------------必须重写的原型----------------------------------------------------------------------------
//秒杀控件
function InitSecondKillControl() {
    return new SecondKillControl();

}

function SecondKillJsonToObj(element) {
    var obj = new SecondKillControl();
    obj.Item = new Array();
    obj.ActivityName = element.ActivityName;
    obj.ShowNum = element.ShowNum;
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

function SecondKillControl() {
    this.Type = 'SecondKill';
    this.ID = 'Control' + $.CreateID();
    this.Item = new Array();
    this.ActivityName = "秒杀商品";
    this.ShowNum =10;
  
}

SecondKillControl.prototype = new BaseControl();

SecondKillControl.prototype.Html = function () {
    return juicer($("#" + this.Type + "Control").html(), { Model: this });
  
};



//展示
SecondKillControl.prototype.Show = function () {
    var currentControl = this;
    var id = currentControl.ID;
    var targetView = $("#MyBox").find("div[data-id='" + id + "']");
    var top = targetView.offset().top - $(".diy-container-layout").offset().top;
    $(".diy-ctrl").css("top", top + 'px');
   
    if (this.Item.length <= 0) {
        var loop = 10;
        if (loop > DataModel.SecondKillGoods.length)
        {
            loop = DataModel.SecondKillGoods.length;
        }
        var html = "";
        for (var i = 0; i < loop; i++) {
            var m = new ListItem();
            var element = DataModel.SecondKillGoods[i];
            m.Img = element.Img;
            m.ProductName = element.ProductName;
            m.Price = element.Price;
            m.Code = element.Code;
            m.Platform = element.Platform;
            m.ProductID = element.ProductID;
            m.OldPrice = element.OldPrice;

            currentControl.Add(m);
            
            html += juicer($("#SecondKillListItem").html(), { Model: m, Control: currentControl });
         
        }
        targetView.find("[productlist]").html(html);
        currentControl.ShowNum = 10;
       
        page.Set(currentControl);
    }
 

    $(".diy-ctrl").html(juicer($("#" + this.Type + "Edit-template").html(), { Model: this, Control: currentControl }));

    $(".diy-ctrl").find("[shownum]").val(currentControl.ShowNum);

}

//操作监听
SecondKillControl.prototype.ReadyListen = function () {

    this.ExtendToShowWay();//监听显示个数和活动标题的变化

};


//--------------------------------------------控件扩展----------------------------------------------------------------------------

SecondKillControl.prototype.ExtendToShowWay = function () {

    var currentControl = this;
    var self = $("#" + currentControl.Type + "ItemPanel");
    var id = currentControl.ID;
    var targetView = $("#MyBox").find("div[data-id='" + id + "']");

    self.find('[name="activityName"]').unbind("change");
    self.find('[name="activityName"]').on("change", function () {
        var activityName = $(this).val();
        targetView.find("[activityname]").html(activityName);
        currentControl.ActivityName = activityName;
        page.Set(currentControl);

    });

    self.find('[shownum]').unbind("change");
    self.find('[shownum]').on("change", function () {
        var shownum = $(this).val();
        var loop = shownum;
        if (loop > DataModel.SecondKillGoods.length) {
            loop = DataModel.SecondKillGoods.length;
        }
        var listHtml = "";
        currentControl.Item = new Array();
        for (var i = 0; i < loop; i++) {
            var m = new ListItem();
            var element = DataModel.SecondKillGoods[i];
            m.Img = element.Img;
            m.ProductName = element.ProductName;
            m.Price = element.Price;
            m.Code = element.Code;
            m.Platform = element.Platform;
            m.ProductID = element.ProductID;
            m.OldPrice = element.OldPrice;

            currentControl.Add(m);

            listHtml += juicer($("#SecondKillListItem").html(), { Model: m, Control: currentControl });

        }
       
        targetView.find("[productlist]").html(listHtml);
        currentControl.ShowNum = shownum;
        page.Set(currentControl);

    });

}