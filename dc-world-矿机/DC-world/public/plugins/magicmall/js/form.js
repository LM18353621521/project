/// <reference path="jquery.min.js" />
/*
    * 表单提交
    * 它可以捕获 jq 使用的特性值 
    * data-form="key&type" => key&type(键&指定类型[val(可为空)/text/html/attr])
    * url ：ajax提交后台的地址
    * success 成功回调函数
    * opts:可选参数
    *    opts.dataType 可指定 json text ...请参考 JQuery ajax这个参数设置
    *    opts.data 自定义一组数据，必须json格式
    *    opts.checkboxOutType 可指定选中的多选输出格式，支持数组/拼接 默认 1,1格式
    */
$.fn.ajaxFormSubmit = function (url, success, opts) {
    /*
     * 获取对应的表单值
     */
    function getFormValues(obj, type) {
        if (type == "" || type == null || type === undefined) { type = "val"; }
        if (type == "val") { return $(obj).val(); }
        if (type == "text") { return $(obj).text(); }
        if (type == "html") { return $(obj).html(); }
        if (type == "src") {
            try {
                return $(obj).attr('src').replace(SERP_UPLOAD_CONFIG.UPLOAD_SERVER, '');
            }
            catch (e) {
                return $(obj).attr('src');
            }
        }
        return $(obj).attr(type);
    }
    var vjson = {};
    if (opts === undefined) { opts = {}; }
    //默认多选为数组
    if (!opts.hasOwnProperty("checkboxOutType"))
        opts.checkboxOutType = ",";
    this.find("*[data-form]").each(function (i, dom) {
        var ot = $(dom).attr("data-form");
        var keyName = ot.split("&")[0];
        var valType = ot.split("&")[1];
        if ($(dom).prop("tagName").toLocaleLowerCase() == "input") {
            var dom_type = $(dom).attr("type");
            if (dom_type == "radio") {
                vjson[keyName] = $("input[data-form='" + keyName + "']:checked").val();
            } else if (dom_type == "checkbox") {
                var ckarr = $("input[data-form='" + keyName + "']:checked").map(function ()
                { return $(this).val(); }).get();
                if (typeof (opts.checkboxOutType) == "string") {
                    vjson[keyName] = ckarr.join(opts.checkboxOutType);
                } else {
                    vjson[keyName] = ckarr;
                }
            } else {
                vjson[keyName] = getFormValues(this, valType);
            }
        }
        else if ($(dom).prop("tagName").toLocaleLowerCase() == "img") {
            vjson[keyName] = getFormValues(this, 'src');
        }
        else {
            vjson[keyName] = getFormValues(this, valType);
        }
    })

    if (opts === undefined) { opts.dataType = "text"; }
    else if (opts !== undefined) {
        if (!opts.hasOwnProperty("dataType"))
            opts.dataType = "text";
    }
    if (vjson.leng <= 0) { return; }
    vjson = $.extend({}, vjson, opts.data);
    var _layreIndex = 0;
    $.ajax({
        url: url,
        type: "post",
        dataType: opts.dataType,
        data: vjson,
        beforeSend: function () {
            _layreIndex = $.wLoading("保存中,请稍等..");
        },
        cache: false,
        success: success,
        error: function () {
            $.werror("操作失败",3);
        },
        complete: function (x) {
            //$.closelayre(_layreIndex);
            $.zui.modalTrigger.close(_layreIndex);
        }
    })
}
$(function () {
    $("*[data-btn='cancel']").click(function () {
        window.history.go(-1);
    })
})