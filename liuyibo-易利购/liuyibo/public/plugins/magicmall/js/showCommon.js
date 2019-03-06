


    $.fn.prototype = {
    _init: function () { },
    select2: function () {
        var me = this;
        var $formEditor = me.dataWrapper.find(me.options.formEditor);
        if ($.fn.select2) {
            $formEditor.find(".select2").select2({ language: "zh-CN" });
        }
    }

