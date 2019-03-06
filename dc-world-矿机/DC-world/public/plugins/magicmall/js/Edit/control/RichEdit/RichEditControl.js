

//--------------------------------------------必须重写的原型----------------------------------------------------------------------------
//富文本编辑控件
function InitRichEditControl() {
    return new RichEditControl();

}

function RichEditJsonToObj(element) {
    var obj = new RichEditControl();
    obj.Text = element.Text;
   
    obj.Item = new Array();
    return obj;

}

function RichEditControl() {
    this.Type = 'RichEdit';
    this.ID = 'Control' + $.CreateID();
    this.Item = new Array();
    this.Text = "";
  
}

RichEditControl.prototype = new BaseControl();

RichEditControl.prototype.Html = function () {
    var displayText = "";
    if (this.Text != null && this.Text != "" ) {
        displayText = $.Decode(this.Text);
    }
    return juicer($("#" + this.Type + "Control").html(), { Model: this, DisplayText: displayText });
  
};

RichEditControl.prototype.BuildPhoneHtml = function () {
    var displayText = "";
    if (this.Text != null && this.Text != "") {
        displayText = $.Decode(this.Text);
    }
    return juicer($("#" + this.Type + "PhoneControl").html(), { Model: this, DisplayText: displayText });
   
};

//展示
RichEditControl.prototype.Show = function () {
    var currentControl = this;
    var id = currentControl.ID;
    var targetView = $("#MyBox").find("div[data-id='" + id + "']");
    var top = targetView.offset().top - $(".diy-container-layout").offset().top;
    $(".diy-ctrl").css("top", top + 'px')
    $(".diy-ctrl").html(juicer($("#" + this.Type + "Edit-template").html(), { Model: this }));
  

}

//操作监听
RichEditControl.prototype.ReadyListen = function () {
   
    var currentControl = this;
    var self = $("#" + currentControl.Type + "ItemPanel");
    var id = currentControl.ID;
    var targetView = $("#MyBox").find("div[data-id='" + id + "']");
   


    var textarea = self.find('textarea.kindeditor');
    
    var editor = "editor-" + id;
    editor = KindEditor.create(textarea, {
        basePath: 'zui/lib/kindeditor/',
        bodyClass: 'article-content',
        resizeType: 0,
        allowPreviewEmoticons: false,
        allowImageUpload: false,
        items: [
                'bold', 'italic', 'underline', 'fontname', 'fontsize', 'forecolor', 'hilitecolor', 'justifyleft', 'justifycenter', 'justifyright', 'link'
        ],
        afterChange: function () { var htmlText = this.html(); targetView.find(".diy-fulltext").html(htmlText); }
    });
    var displayText = "";
  
    if (this.Text != null && this.Text != "") {
        displayText = $.Decode(this.Text);
    }
   
    editor.html(displayText);
    $(".ke-container").css("width", 'auto');
    self.find('#gethtml').on("click", function () {
        
        var htmlText = editor.html().trim()
        if (htmlText.length > 30 && htmlText.indexOf("span")==-1) {
            $.ShowErrMessage("请输入1-30字以内的文字");
            return;
        };
        targetView.find(".diy-fulltext").html(htmlText);
        currentControl.Text = $.Encode(htmlText);
        page.Set(currentControl);
      
        $.ShowSuccessMessage("已保存");
    });

   
};

