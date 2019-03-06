
//--------------------------------------------必须重写的原型----------------------------------------------------------------------------
//辅助空白控件
function InitBlankControl() {
    return new BlankControl();

}

function BlankJsonToObj(element) {
    var obj = new BlankControl();
    obj.Height = element.Height;
    obj.BackgroundColor = element.BackgroundColor;
    obj.Item = new Array();
    return obj;

}

function BlankControl() {
    this.Type = 'Blank';
    this.ID = 'Control' + $.CreateID();
    this.Item = new Array();
    this.Height = 20;
    this.BackgroundColor = "rgb(255, 255, 255)";
}

BlankControl.prototype = new BaseControl();

BlankControl.prototype.Html = function () {

    return $.JuicerHtml(this.Type + "Control", this);
};



//展示
BlankControl.prototype.Show = function () {
    var currentControl = this;
    var id = currentControl.ID;
    var targetView = $("#MyBox").find("div[data-id='" + id + "']");
    var top = targetView.offset().top - $(".diy-container-layout").offset().top;
    $(".diy-ctrl").css("top", top + 'px')
    $(".diy-ctrl").html($.JuicerHtml(this.Type + "Edit-template", this));

}

//操作监听
BlankControl.prototype.ReadyListen = function () {

    var currentControl = this;
    var self = $("#" + currentControl.Type + "ItemPanel");
    var id = currentControl.ID;
    var targetView = $("#MyBox").find("div[data-id='" + id + "']");
    targetView.find('hr').css("margin", currentControl.Height + 'px' + ' ' + 'auto');
    targetView.find('hr').css("border-style", currentControl.LineType);
    targetView.find('hr').css("border-color", currentControl.BorderColor);
    self.find('input[name="hrange"]').unbind("change");
    self.find('[name="hrange"]').on("change", function () {
        $(this).next().html($(this).val());
        targetView.find('.diy-space').css("height", $(this).val() + 'px');
        currentControl.Height = $(this).val();
        page.Set(currentControl);
    });
    
    $('.colorpicker-default').colorpicker({
        format: 'hex'
    });

    self.find('.add-on').on('click', function () {
       
        self.find('[name="color"]').focus();
    });
    self.find('[name="color"]').on("blur", function () {
        currentControl.BackgroundColor = $(this).val();
        page.Set(currentControl);
        targetView.find('.diy-space').css("background-color", $(this).val());
    });

    self.find('input[name="colorInput"]').on("blur", function () {
        currentControl.BackgroundColor = $(this).val();
        targetView.find('.diy-space').css("background-color", $(this).val());
        $(this).parents(".input-append").find("i").css("background-color", $(this).val());
        page.Set(currentControl);
    });
};

