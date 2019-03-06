; (function ($) {
    if (window.Beautifier || !$) {
        return;
    }
    window.Beautifier = function (ele, opt) {
        this.$element = ele;
        this.defaults = {
            method: function (ele, data) { },
            boxtitle: '请选择',
            dataurl: null,
            trigger: 'click'
        };
        this.options = $.extend({}, this.defaults, opt);
        this.init();
    }
    Beautifier.prototype = {
        init: function () {
            var me = this;
            me.$element.on(me.options.trigger, function () {
                var dialog = bootbox.dialog({
                    title: me.options.boxtitle,
                    size: 'large',
                    message: '<p><i class="fa fa-spin fa-spinner"></i> 正在加载...</p>',
                    buttons: {
                        cancel: {
                            label: "取消",
                            className: 'btn-default',
                            callback: function () {

                            }
                        },
                        ok: {
                            label: "确定",
                            className: 'btn-info',
                            callback: function () {
                                var data = [];
                                $("input[name='cb']:checked").each(function () {
                                    data.push($(this).data("datainfo"));
                                });
                                me.options.method(me.$element, data);
                            }
                        }
                    }
                });
                dialog.init(function () {
                    setTimeout(function () {
                        if (typeof (me.options.dataurl) == "string") {
                            $.ajax({
                                url: me.options.dataurl,
                                type: 'post',
                                dataType: 'html',
                                success: function (data) {
                                    dialog.find('.bootbox-body').html(data);
                                }, error: function (data) {

                                }
                            });
                        } else {
                            throw new Error('请填写dataurl参数.')
                        }
                    }, 300);
                });
            });
        }
    };


    $.fn.openbootbox = function (option) {
        var args = Array.apply(null, arguments);
        args.shift();
        var internalReturn;
        this.each(function () {
            var $this = $(this), data = $this.data("Beautifier"), options = typeof option === "object" && option;
            options = $.extend(true, {}, data, options);
            if (!data) {
                data = new Beautifier($this, options);
                $this.data("Beautifier", data);
            }
            if (typeof option === "string" && typeof data[option] === "function") {
                internalReturn = data[option].apply(data, args);
                if (internalReturn !== undefined) {
                    return internalReturn;
                }
            }
            return $this;
        });
    };
    //////////////
    // Data API //
    //////////////
    $("[data-beautifier='true']").each(function () {
        var $this = $(this);
        $this.dataTable($this.data());
    });
})(jQuery);