//商品搜索控件
function InitSearchControl() {
    return new SearchControl();

}

function SearchJsonToObj(element) {
    var obj = new SearchControl();
    obj.Item = new Array();
    return obj;

}

function SearchControl() {
    BaseControl.call(this);
    this.Type = 'Search';
    this.ID = 'Control' + $.CreateID();
    this.Item = new Array();
}

SearchControl.prototype = new BaseControl();

SearchControl.prototype.Html = function () {

    return $.JuicerHtml(this.Type + "Control", this);
};


//展示
SearchControl.prototype.Show = function () {
    var currentControl = this;
    var id = currentControl.ID;
    var targetView = $("#MyBox").find("div[data-id='" + id + "']");
    var top = targetView.offset().top - $(".diy-container-layout").offset().top;
    $(".diy-ctrl").css("top", top + 'px')
    $(".diy-ctrl").html($.JuicerHtml(this.Type + "Edit-template", this));
    
}

//操作监听
SearchControl.prototype.ReadyListen = function () {

 
};