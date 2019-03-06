
/**
 * 模式枚举
 */
var EamEditMode = {
    add: "add",
    update:"update"
}

/**
 * 自动映射编辑管理
 */
var EamEditManage = {

    settings: {
        add: {
            exeSaveUrl: null,
            executeSaveEvent: null,
            executeSaveForwardEvent: null,
            executeSaveAfterEvent: null,
            returnListEvent:null
        },
        update: {
            exeSaveUrl: null,
            executeSaveEvent: null,
            executeSaveForwardEvent: null,
            executeSaveAfterEvent: null,
            returnListEvent:null
        },
        rendering: {
            afterEvent: null,
            forwardEvent: null
        }
    },
    sourceJson: null,
    view: null,
    mode: null,
    isCustomLoadScript: false,
    
    _customLoadScript: function () {

        var result = false;
        var isCustomLoadScript = $.getUrlParamters("isCustomLoadScript");
        var customLoadScript = $.getUrlParamters("assemblys").split(',')[1].split('.')[3];
        if (isCustomLoadScript == "1") {
            //var srcAttr = "/pageJs/" + customLoadScript + ".js?v=" + Math.random();
            var rootPath = "/PageResources/" + customLoadScript + "/";
            var scriptUrl = rootPath + customLoadScript + ".js?v=" + Math.random();
            var cssUrl = rootPath + customLoadScript + ".css?v=" + Math.random();

            var isLoadScript = false;
            var scripts = document.getElementsByTagName("script");
            $(scripts).each(function (index, element) {
                if ($(element).attr("src") == scriptUrl) {
                    isLoadScript = true;
                    return false;
                }
            });
            if (!isLoadScript) {
                var head = document.getElementsByTagName('head')[0];
                var script = document.createElement('script');
                script.type = 'text/javascript';
                script.src = scriptUrl;
                head.appendChild(script);
                result = true;
            }

            var isLoadCss = false;
            var cssList = document.getElementsByTagName("link");
            $(cssList).each(function (index, element) {
                if ($(element).attr("href") == cssUrl) {
                    isLoadCss = true;
                    return false;
                }
            });
            if (!isLoadCss) {
                var head = document.getElementsByTagName('head')[0];
                var css = document.createElement('link');
                css.rel = "stylesheet";
                css.href = cssUrl;
                head.appendChild(css);
                result = true;
            }
        }

        return result;
    },
    
    init: function (settings) {

        if (settings) {
            var pObj = null;
            var pChildObjValue = null;
            for (var p in settings) {

                pObj = eval("settings." + p);
                for (var pChild in pObj) {

                    pChildObjValue = eval("settings." + p + "." + pChild);
                    if (pChildObjValue)
                        eval("EamEditManage.settings." + p + "." + pChild + "=pChildObjValue");
                }
            }
        }

        if (EamEditManage.isCustomLoadScript)
            EamEditManage.view.rendering();
    },

    load: function (sourceJsonStr) {
        if (!sourceJsonStr || sourceJsonStr == "")
            return;

        EamEditManage.sourceJson = eval("[" + sourceJsonStr + "]")[0];
        mode = (EamEditManage.sourceJson.mainDataTable ? EamEditMode.update : EamEditMode.add);
        EamEditManage.view = new EamEditView(EamEditManage.settings, EamEditManage.sourceJson, mode);
        EamEditManage.isCustomLoadScript = EamEditManage._customLoadScript();

        if (!EamEditManage.isCustomLoadScript)
            EamEditManage.view.rendering();
    }
}

/**
 * 编辑显示对象
 */
var EamEditView = function (settings, sourceJson, mode) {

    this._settings = settings;
    this._sourceJson = sourceJson;
    this._mode = mode;
    this.controlViews = [];
}
/**
 * 渲染
 */
EamEditView.prototype.rendering = function () {

    
    var column = null;
    // 循环进行渲染组件
    for (var i = 0; i < this._sourceJson.columns.length; i++) {
        column = this._sourceJson.columns[i];
        var mainDataTableStr = "null";
        if (this._mode == EamEditMode.update)
            mainDataTableStr = "this._sourceJson.mainDataTable[0]";

        var childList = [];
        var childs = eval("this._sourceJson.childDataTables." + this._sourceJson.columns[i].name);
        var item = null;
        $(childs).each(function (i, e) {
            item = {};
            $(e).each(function (ii, ee) {
                eval("item." + ee.key + "='" + ee.value + "'");
            });
            childList.push(item);
        });
        if (childs) {
            if (EamEditManage.settings.rendering.forwardEvent) 
                eval("EamEditManage.settings.rendering.forwardEvent(this._sourceJson.columns[i], childList);");
        }
        var controlObj = eval("new " + column.controlType + "View(" + mainDataTableStr + ", this._sourceJson.columns[i], childList);");
        this.controlViews.push({ name: column.name, text: column.text, isEditDisplay: column.isEditDisplay, controlEditHTML: controlObj.edit(), controlViewHTML: controlObj.view(), controlView: controlObj, controlType: column.controlType });
    }

    //获取模板渲染数据
    var tpl = juicer($("#tmplate_edit_view").html(), { list: this.controlViews });
    $("#form-group-content").html(tpl); 
    var head = document.getElementsByTagName('head')[0];
    var script = document.createElement('script');
    script.type = 'text/javascript';
    script.src = "/js/drp/drp-inti.js";
    head.appendChild(script);

    if (EamEditManage.settings.rendering.afterEvent)
        EamEditManage.settings.rendering.afterEvent();
    IsLookChuLi();
}
/**
 * 保存
 */
EamEditView.prototype.save = function () {

    //if (EamEditManage.settings.add)
     
    if (!CheckMustNeedInput()) {
        return;
    }
    var dataJson = this.getValue();
    var saveUrl = this._settings.update.exeSaveUrl;
    var Result = true;
    if (this._mode == EamEditMode.add) {
        saveUrl = this._settings.add.exeSaveUrl;

        if (EamEditManage.settings.add.executeSaveForwardEvent)
            Result= EamEditManage.settings.add.executeSaveForwardEvent(dataJson, saveUrl);

        if (EamEditManage.settings.add.executeSaveEvent)
            Result =EamEditManage.settings.add.executeSaveEvent(dataJson, saveUrl);
    } else {
         
        saveUrl = this._settings.update.exeSaveUrl;
        if (EamEditManage.settings.update.executeSaveForwardEvent)
            Result =EamEditManage.settings.update.executeSaveForwardEvent(dataJson, saveUrl);

        if (EamEditManage.settings.update.executeSaveEvent)
            Result =EamEditManage.settings.update.executeSaveEvent(dataJson, saveUrl);
    }
    if (Result==false) {
        return ;
    }
    if (!EamEditManage.settings.add.executeSaveEvent && !EamEditManage.settings.update.executeSaveEvent) {

        var _this = this;
        //指向表单元素
        //添加 与 更新
        $("#form-body").ajaxFormSubmit(saveUrl,
            function (d) {
                
                if (_this._mode == EamEditMode.add) {

                    if (EamEditManage.settings.add.executeSaveAfterEvent)
                        EamEditManage.settings.add.executeSaveAfterEvent(d);
                } else {

                    if (EamEditManage.settings.update.executeSaveAfterEvent)
                        EamEditManage.settings.update.executeSaveAfterEvent(d);
                }
                
                if (d > 0) {
                    //$.wmessage("保存成功",1);
                    $.wconfirm("是否要返回列表数据页", function (a) {
                        //"assemblys=" + assemblys + "&separateTableIdentity=" + separateTableIdentity + "&isCustomLoadScript=" + $("#isCustomLoadScript").val()
                        EamEditManage.view.returnList();
                    }, "保存成功")
                } else {
                    console.log(d);
                    $(".layui-layer-shade").hide();
                    $(".layer-anim").hide();
                    $.wmessage("保存失败,"+d,3);
                }
            }, { data: dataJson });
    }
}

/**
 * 获取值
 */
EamEditView.prototype.getValue = function () {

    var opts = { Assemblys: $("#assemblys").val() };
    $(EamEditManage.view.controlViews).each(function (index, element) {

        eval("opts." + element.name + "='" + element.controlView.getValue() + "'");
    });

    return opts;
}

EamEditView.prototype.returnList = function () {

    var url = "#";
    if (this._mode == EamEditMode.add && EamEditManage.settings.add.returnListEvent)
        url = EamEditManage.settings.add.returnListEvent(document.referrer);
    if (this._mode == EamEditMode.update && EamEditManage.settings.update.returnListEvent)
        url = EamEditManage.settings.update.returnListEvent(document.referrer);
    else
        url = document.referrer;
    window.location.href = url;
    //window.location.href = '/EAM/index' + (param ? "?" + param : "");
}


//window.UploadFileBackFun = function (urlStr) {
//    var TempStr = "<li> <img src='" + urlStr + "' sthle='width:55px;height:55px'> <span class='del' onclick='DelFile(this)'></span></li>";
//    $("#file_tag_li").before(TempStr);
//    $(".image_url").val($(".image_url").val() + "@*@" + urlStr);
//}
//window.DelFile = function (obj) {
   
//    var UrlStr = $(obj).parent().children("img").attr("src");
//    $(".image_url").val($(".image_url").val().replace("@*@" + UrlStr, "").replace(UrlStr, ""));
//    $(obj).parent().remove();
//    //alert(UrlStr);
//}

var CurrentUploadId = "";
window.UploadFileBackFun = function (urlStr) {
    var TempStr = "<li> <img src='" + urlStr + "' sthle='width:55px;height:55px'> <span class='del' onclick='DelFile(this)'></span></li>";
    $("." + CurrentUploadId).find("#file_tag_li").before(TempStr);
    if ($("." + CurrentUploadId).find(".image_url").val().trim() == "") {
        $("." + CurrentUploadId).find(".image_url").val(urlStr);
    } else {
        $("." + CurrentUploadId).find(".image_url").val($("." + CurrentUploadId).find(".image_url").val() + "@*@" + urlStr);
    }



}
window.DelFile = function (obj) {
    debugger
    var UrlStr = $(obj).parent().children("img").attr("src");
    $(obj).parents(".form-group").find(".image_url").val($(obj).parents(".form-group").find(".image_url").val().replace("@*@" + UrlStr, "").replace(UrlStr, ""));
    $(obj).parent().remove();
    //alert(UrlStr);
}

function CheckMustNeedInput() {
    var NeedInputDom = $(".IsNeedInput");
    for (var i = 0; i < NeedInputDom.length; i++) {
        if ($(NeedInputDom[i]).val().trim() == "" || ($(NeedInputDom[i]).val().trim() <= 0 && $(NeedInputDom[i]).html().indexOf("option")>=0)) {
            $.wmessage("请填写" + $(NeedInputDom[i]).attr("placeholder") + "，该项不能为空！",3);
            $(NeedInputDom[i]).focus();
            return false;
        }
    }
    return true;
}
function GetNowDateTime() {
     
    var date = new window.Date();
    var Year = date.getFullYear();
    var Month = date.getMonth() + 1;
    var Date = date.getDate()
    var Hour = date.getHours();
    var Minute = date.getMinutes();
    var Second = date.getSeconds();
    return Year + "-" + Month + "-" + Date + " " + Hour + ":" + Minute + ":" + Second;
}

function IsLookChangeLength() {

    var CurrentDom = $('input');
    for (var i = 0; i < CurrentDom.length; i++) {

        var Item = CurrentDom[i]

        var text_length = $(Item).val().length;//获取当前文本框的长度
        var current_width = parseInt(text_length) * 16;//该16是改变前的宽度除以当前字符串的长度,算出每个字符的长度
        console.log(current_width)
        $(Item).css("width", current_width + "px");
    }

    CurrentDom = $(".form-group").find('select');
    if (CurrentDom.length>1) {
        for (var i = 0; i < CurrentDom.length; i++) {

            var Item = CurrentDom[i]

            var text_length = $(Item).val().length;//获取当前文本框的长度
            var current_width = parseInt(text_length) * 18;//该16是改变前的宽度除以当前字符串的长度,算出每个字符的长度
            console.log(current_width)
            $(Item).css("width", current_width + "px");
        }
    }
  
}

function ChangeEditItemSort(NeedChangeItemId, TargetItemId) {
    var NeedHtmlStr = $("#" + NeedChangeItemId).parents(".form-group")[0].outerHTML;
    $("#" + NeedChangeItemId).parents(".form-group").remove();
    $("#" + TargetItemId).parents(".form-group").after(NeedHtmlStr);
}