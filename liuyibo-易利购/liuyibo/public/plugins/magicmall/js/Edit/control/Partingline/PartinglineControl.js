//--------------------------------------------必须重写的原型----------------------------------------------------------------------------
//分割线
function InitPartinglineControl() {
    return new PartinglineControl();

}

function PartinglineJsonToObj(element) {
    var obj = new PartinglineControl();
    obj.Height = element.Height;
    obj.LineType = element.LineType;
    obj.BorderColor = element.BorderColor;
    obj.Item = new Array();
    return obj;

}

function PartinglineControl() {
    this.Type = 'Partingline';
    this.ID = 'Control' + $.CreateID();
    this.Item = new Array();
    this.Height = 12;
    this.LineType = "dotted";
    this.BorderColor = "#9f8f8f";
}

PartinglineControl.prototype = new BaseControl();

PartinglineControl.prototype.Html = function () {

    return $.JuicerHtml(this.Type + "Control", this);
};


//展示
PartinglineControl.prototype.Show = function () {
    var currentControl = this;
    var id = currentControl.ID;
    var targetView = $("#MyBox").find("div[data-id='" + id + "']");
    var top = targetView.offset().top - $(".diy-container-layout").offset().top;
    $(".diy-ctrl").css("top", top + 'px')
    $(".diy-ctrl").html($.JuicerHtml(this.Type + "Edit-template", this));

}

//操作监听
PartinglineControl.prototype.ReadyListen = function () {

    var currentControl = this;
    var self = $("#" + currentControl.Type + "ItemPanel");
    var id = currentControl.ID;
    var targetView = $("#MyBox").find("div[data-id='" + id + "']");
    targetView.find('hr').css("margin", currentControl.Height + 'px' + ' ' + 'auto');
    targetView.find('hr').css("border-style", currentControl.LineType);
    targetView.find('hr').css("border-color", currentControl.BorderColor);
    self.find('input[name="hrange"]').unbind("change");
    self.find('input[name="hrange"]').on("change", function () {
       
        $(this).next().html($(this).val());
        targetView.find('hr').css("margin", $(this).val() + 'px' + ' ' + 'auto');
        currentControl.Height = $(this).val();
        page.Set(currentControl);

    });


    self.find('input[name="rdline"]').unbind("change");
    self.find('input[name="rdline"]').on("change", function () {

        targetView.find('hr').css("border-style", $(this).val());
        currentControl.LineType = $(this).val();
        page.Set(currentControl);

    });

    $('.colorpicker-default').colorpicker({
        format: 'hex'
    });

    self.find('.add-on').on('click', function () {
        self.find('[name="color"]').focus();
    });

    self.find('input[name="color"]').on("blur", function () {
        currentControl.BorderColor = $(this).val();
        targetView.find('hr').css("border-color", $(this).val());
        page.Set(currentControl);
    });

    self.find('input[name="colorInput"]').on("blur", function () {
        currentControl.BorderColor = $(this).val();
        targetView.find('hr').css("border-color", $(this).val());
        $(this).parents(".input-append").find("i").css("background-color", $(this).val());
        page.Set(currentControl);
    });
};

