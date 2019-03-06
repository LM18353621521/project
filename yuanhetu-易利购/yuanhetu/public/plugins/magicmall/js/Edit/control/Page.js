//页面
function Page() {
    this.Name = 'Page';
    this.Type = 'Page';
    this.Title = "";
    this.Remark = "";
    this.Logo = "";
    this.Url = "";
}

Page.prototype = new BaseControl();

//渲染HTML
Page.prototype.Html = function () {
   
    var pageHtml = "";
    for (var index in this.Item) {
        if (this.Item[index].Type != 'BottomMenu') {
            pageHtml += this.Item[index].Html();
        }
       
    }
    for (var index in this.Item) {
        if (this.Item[index].Type == 'BottomMenu') {
            pageHtml += this.Item[index].Html();
        }
      
    }
    return pageHtml;
};

//渲染手机端HTML
Page.prototype.BuildPhoneHtml = function () {

    var pageHtml = "";
    for (var index in this.Item) {
        if (this.Item[index].Type != 'BottomMenu')
        {
            pageHtml += this.Item[index].BuildPhoneHtml();
        }
    }

   
    return pageHtml;
};

Page.prototype.BuildBottomMenuHtml = function () {

    var pageHtml = "";
    for (var index in this.Item) {
        if (this.Item[index].Type == 'BottomMenu') {
            pageHtml += this.Item[index].BuildPhoneHtml();
        }
    }
    return pageHtml;
};

//拖拽控件
Page.prototype.Draggable = function () {
    var dataId = 0;
    var timerSave = 1000;
    var stopsave = 0;
    var startdrag = 0;
    var page = this;
    var control = null;
    $(".module-list .box").draggable({
        connectToSortable: ".demo",
        appendTo: "#abox",
        helper: "clone",
        handle: ".drag",
        start: function (e, t) {
            var controlType = t.helper.attr("data-type");
            control=eval("Init" + controlType + "()");
            if (!startdrag) stopsave++;
            startdrag = 1;
            $(this).attr('data-id', control.ID);

        },
        drag: function (e, t) {
            t.helper.width(300);
        },
        stop: function (event, ui) {
            if (stopsave > 0) stopsave--;
            startdrag = 0;
            $(this).attr('data-id', control.ID);

            $.each(page.Item, function (index, controlInfo) {
                if ((controlInfo.Type == "TeamBuying" && control.Type == "TeamBuying") || (controlInfo.Type == "SecondKill" && control.Type == "SecondKill"))
                {
                    $("#MyBox").find("div[data-id='" + control.ID + "']").remove();
                    return;
                }
            });
            page.Add(control);
            page.Sort();
        }
    });


    $(".demo").sortable({
        connectWith: ".column",
        opacity: .35,
        handle: ".drag",
        start: function (e, t) {
            if (!startdrag) stopsave++;
            startdrag = 1;
        },
        stop: function (e, t) {
            if (stopsave > 0) stopsave--;
            startdrag = 0;
            $(t.item).trigger('click');

            page.Sort();

        }
    });
};

//对象准备好后进行操作监听
Page.prototype.ReadyListen = function () {

    this.Draggable();//监听拖拽
    this.Delete(); //监听删除
    this.Click(); //监听选中
  
};

//移除某一个项
Page.prototype.Delete = function () {
    var page = this;
    $(".demo").delegate(".js-del", "click", function (e) {
        e.preventDefault();

        $(this).parents(".box").remove();
        page.Remove($(this).parents(".box").attr("data-id"));
        $(".diy-ctrl").html("");

    })
}

//排序
Page.prototype.Sort = function () {
    var page = this;
    if (page.Count() > 0) {
        var newArray = new Array();
        $("#MyBox").find(".box-element").each(function (index, element) {
            var id = $(element).attr("data-id");
            var control = page.Get(id);
            if (control != undefined && control!=null)
            {
                newArray.push(control);
            }
        });

        page.Item = newArray;

       
    }
   
    return page;
}

//点击选中
Page.prototype.Click = function () {
    var page = this;
    $('#MyBox').delegate(".diy-edit", "click", function (e) {
        e.preventDefault();
        var id = $(this).parents(".box-element").attr("data-id");
      
        var operateControl = page.Get(id);//操作的某个控件
      
        if (operateControl)
        {
             operateControl.Show(); //展示控件编辑面板
             operateControl.ReadyListen();
        }
       
    });


    $('.phone-box').delegate(".phone-title", "click", function (e) {
        e.preventDefault();
        $(".diy-ctrl").css("top", '0px')
        $(".diy-ctrl").html(juicer($("#share-Template").html(), { Model: page }));
        $("#phonetitle").html(page.Title);
        page.InputChange();
        page.Upload();
    });
   
    $(".phone-title").click();
  
}

Page.prototype.Upload = function () {
    var page = this;
    $(".configurator").delegate(".openFile", "click", function (e) {
        var evt = document.createEvent("MouseEvents");
        evt.initEvent("click", false, false);// 或用initMouseEvent()，不过需要更多参数 
        $(".webuploader-element-invisible").get(0).dispatchEvent(evt);
        $li = $(this).parents(".configurator");
        $.UploadingControlItem.UploadingControl = page;
        $.UploadingControlItem.li = $li;

    })

}
Page.prototype.UploadFinish = function (imgUrl) {
    var page = this;
    page.Logo = imgUrl;
 
}

Page.prototype.InputChange = function () {
    var page = this;
    $("input[name='pageTitle']").unbind("blur");
    $("input[name='pageTitle']").bind("blur", function (e) {
        e.preventDefault();
        if ($(this).val().length>30)
        {
        
            $.ShowErrMessage("请输入30个字以内的标题");
            return;
        }
        page.Title = $(this).val().trim();
        $("#phonetitle").html(page.Title);
      
    });

    $("input[name='pageRemark']").unbind("blur");
    $("input[name='pageRemark']").bind("blur", function (e) {
        e.preventDefault();
        if ($(this).val().length > 100) {
           
            $.ShowErrMessage("请输入100个字以内的描述");
            return;
        }
        page.Remark = $(this).val().trim();
      
    });

}








