/// <reference path="jquery.min.js" />

/*封装常用的事件 包括 ajax*/
$.extend(
    {
        /*
         * 基础ajax封装
         */
        ajaxBase: function(_url, _data, _success, opts) {
            var o = $.extend({ url: _url, data: _data, success: _success, type: "post" }, opts);
            $.ajax(o);
        },

        /*
         * ajax 调用 返回json
         * url 请求地址
         * data 数据
         * sufu 成功回调
         * opts 可选参数 参考jquery ajax参数设置
         *  当使用 complete回调后，会多加一个close(index)回调方法 index=关闭弹层的索引
         */
        ajaxJson: function(url, data, sufu, opts) {
            var _layi = 0;
            opts = $.extend({ dataType: "json" }, opts);
            if (!opts.hasOwnProperty("msg")) {
                opts.msg = "提交中...";
            }
            //如果不关闭
            if (opts.msg) {
                opts.beforeSend = function() {
                    _layi = $.wLoading(opts.msg);
                }
            }
            //自动关闭
            if (!opts.hasOwnProperty("complete")) {
                //关闭
                opts.complete = function() {
                    $.zui.modalTrigger.close(_layi);
                }
            } else {
                opts.colse(_layi);
            }
            $.ajaxBase(url, data, sufu, opts);
        },

        /*
         * ajax 调用 返回text
         * url 请求地址
         * data 数据
         * sufu 成功回调
         * opts 可选参数 参考jquery ajax参数设置
         *  当使用 complete回调后，会多加一个close(index)回调方法 index=关闭弹层的索引
         */
        ajaxText: function(url, data, sufu, opts) {
            opts = $.extend({ dataType: "text" }, opts);
            $.ajaxJson(url, data, sufu, opts);
        },
        /*
         * 表单提交 使用解析 控制器
         * action 控制器
         * sl 如果不为空又不为0 会选择 action+route2
        */
        mvcParseFormController: function(action, route1, route2, sl) {
            if (sl === undefined || sl == null || sl == "" || sl == 0
            ) {
                return action + route1;
            }
            return action + route2;
        },

        mvcAjaxBaseDel: function (tbName, ids, sufu, opts, delKey) {

            if (tbName == null || tbName == "") { return; }
            if (ids == null || ids == "") { return; }
            if (opts === undefined) {
                opts = {};
            }

            if (!opts.hasOwnProperty("c"))
                opts.c = "确定要删除该项吗";
            if (!opts.hasOwnProperty("auto"))
                opts.auto = true;
            if (!opts.hasOwnProperty("url"))
                opts.url = "/Utils/Delete";
            $.wconfirm(opts.c, function (i) {
                var para = null;
                if (delKey == undefined || delKey == null || delKey == "") {
                    para = { tbName: tbName, ids: ids };
                } else {
                    para = { tbName: tbName, ids: ids, delKey: delKey };
                }
                $.ajaxText(opts.url, para, function (d) {
                    //if (opts.auto) { if (d > 0) { $.closelayre(i); } }
                    sufu(d, i);
                });
            });
        },
    }
);

/*
 * 封装弹窗
 * 依赖 zui.min.js or zui.js
 */
$.extend({
    /*
     * message方法 （漂浮消息）
     * msg:内容
     * type:消息类型 （成功/警告等）
     * opts: 自定义参数
     * callback：回调函数
     */
    wmessage: function (msg, type,opts,callBack) {
        var msgType = "default";
        var msgIcon = "info-sign";
        if (msg === undefined) {
            msg="操作成功!"
        }
        switch (type) {  //定义颜色主题
            case 1:
                msgType = "success"; 
                msgIcon = "ok-circle";
                break;
            case 2:
                msgType = "warning";
                msgIcon = "warning-sign";
                break;
            case 3:
                msgType = "danger";
                msgIcon = "exclamation-sign";
                break;
            case 4:
                msgType = "info";
                msgIcon = "info-sign";
                break;
            default:
                msgType = "default";
                break;
        }
        opts = $.extend({}, { type: msgType, close: false, icon: msgIcon }, opts);
        $.zui.messager.show(msg, opts, callBack);
    },
    /*
      * 基础提示信息
      *  c:内容
      *  t:消息类型
      *  e:回调函数
      */
    wmsgAlert: function (c,e) {
        var _text = $('#wInfoMsg .text');
        if (c && typeof (c) === 'string') {
            _text.html(c);
        }
        $('#wInfoMsg').modal(e);
    },
    /*
     *成功提示信息
     */
    wsuccess: function (c,e) {
        var iconCss = "icon icon-check text-success";
        $("#body-icon i").attr("class", iconCss);
        if (c === undefined)
        {
            c="操作成功"
        }
        $.wmsgAlert(c, e);
    },
    /*
     *失败提示信息
     */
    werror: function (c, e) {
        var iconCss = "icon icon-remove-circle text-danger";
        $("#body-icon i").attr("class", iconCss);
        if (c === undefined) {
            c = "操作失败"
        }
        $.wmsgAlert(c, e);
    },
    /*
     *警告提示信息
     */
    wmsgwarn: function (c,e) {
        var iconCss = "icon icon-warning-sign text-danger";
        $("#body-icon i").attr("class", iconCss);
        if (c === undefined) {
            c = "警告:操作存在异常"
        }
        $.wmsgAlert(c, e);
    },
    /*
     * text：内容
     * yes ：确定回调
     * cancel： 取消回调
     */
    wconfirm: function (text, yes, cancel) {
        var _yes = $('#wconfirm .yes');
        var _cancel = $('#wconfirm .cancel');
        var _text = $('#wconfirm .text');

        if (text && typeof (text) === 'string') {
            _text.html(text);
        }

        if (yes && typeof (yes) === 'function') {
            _yes.unbind("click");
            _yes.bind({ click: yes });
        }

        if (cancel && typeof (cancel) === 'function') {
            _cancel.unbind("click");
            _cancel.bind({ click: cancel });
        }
        $('#wconfirm').modal();
    },
   
    /*
     * 弹出ifram
     * src 路径 必填
    */
    wiframe: function (src,title,width,height,opts) {
        if (title === undefined) { title: "default"; }
        if (width === undefined) { width = "770px"; }
        if (height === undefined) { height = "565px"; }

        if (opts === undefined) {
            opts = $.extend({}, {
                title: title,
                iframe: src,
                width: width,
                height: height,
                moveable: true
            }, opts);
        }
        return $.zui.modalTrigger.show(opts);
    },

    /*
     * 预加载
     */
    wLoading: function (msg, opts, callBack) {
        if (msg === undefined) { msg = "拼命加载中..." }
        if (opts === undefined)
        {
            opts = $.extend({}, {
                showHeader: false,
                width: "80px",
                height: "40px",
                moveable: false,
                size: "",
                loadingIcon: 'icon-spinner-indicator',
                position: "center",
                type: "custom",
                backdrop: "static",
                keyboard: false,
                content: msg
            }, opts);
        }
        return $.zui.modalTrigger.show(opts);
    }
})

/*
    * 绑定 document 的值 多用于表单绑定
    * 目前用于建设简单的数据绑定
    * data ={} 对象
    * 它需要你指向 dom的指令为 {key}
    */
$.fn.bindDocumentData = function (data) {
    if (data === undefined) { return; }
    var _htmlChange = this.prop("outerHTML");
    $.each(data, function (d_key, d_v) {
        _htmlChange = _htmlChange.replace("{" + d_key + "}", d_v);
    });
    this.replaceWith(_htmlChange);
}

/*
 * 工具js封装
 */
$.extend(
    {
        getUrlParamters: function (key) {
            var reg = new RegExp("(^|&)" + key + "=([^&]*)(&|$)", "i");
            var r = window.location.search.substr(1).match(reg);
            if (r != null && r[2] != "undefined") return unescape(r[2]);
            return null;
        }
    }
);

/*
 * 扩展 类似于CS里面的占位方法
*/
String.prototype.Format = function () {
    if (arguments.length == 0) return this;
    for (var s = this, i = 0; i < arguments.length; i++)
        s = s.replace(new RegExp("\\{" + i + "\\}", "g"), arguments[i] == null ? "" : arguments[i]);
    return s;
};

/*
    * 格式化时间 JS JSON格式
    * 参数 格式化类型
    * 依赖 moment.js
   */
Date.prototype.Dateformat = function (o) {
    if (o === undefined) { o = 'YYYY年MM月DD日 HH:mm:ss a' }
    return moment(this.slice()).format(o);
}
/*
 * 格式化时间 JS JSON格式
 * 参数 格式化类型
 * 依赖 moment.js
*/
String.prototype.Dateformat = function (o) {
    if (o === undefined) { o = 'YYYY年MM月DD日 HH:mm:ss a' }
    return moment(this.slice()).format(o);
}



/*lazyLoadImg*/

function lazyLoadImg() {
    var lazyLoadImg = new LazyLoadImg({
        el: document.querySelector('body'),
        mode: 'default', //默认模式，将显示原图，diy模式，将自定义剪切，默认剪切居中部分
        time: 300, // 设置一个检测时间间隔
        complete: true, //页面内所有数据图片加载完成后，是否自己销毁程序，true默认销毁，false不销毁
        position: { // 只要其中一个位置符合条件，都会触发加载机制
            top: 0, // 元素距离顶部
            right: 0, // 元素距离右边
            bottom: 0, // 元素距离下面
            left: 0 // 元素距离左边
        },
        before: function () { // 图片加载之前执行方法

        },
        success: function (el) { // 图片加载成功执行方法
            el.classList.add('success')
        },
        error: function (el) { // 图片加载失败执行方法
            el.src = '/images/default.png'
        }
    });
}

$.extend({
    /*
    *type:    1.单图  2.多图
    *columns: 列集合 {"name":""}
    *urls:    全路径集合  ["/11/22.png","/11/23.png"]
    *files:   文件路径  ["/img/22.png", "img//23.png"]
    *sort :   查看还是编辑 true:编辑 新增  false  查看
    */
    BingHtmlImg: function (type, columns, urls, files, sort) {
        var htmlImg;
        var scriptStr = '<script type="text/javascript" src="/js/uploader.js"></script>';
        if (type == 1) {
            var _imgObj = { url: urls[0], index: 0, imgfileName: files[0], IsShow: sort };
            htmlImg = juicer($("#upload_single_ImgTemplate").html(), { columnItem: columns, ImgObj: _imgObj, isLook: !sort, scriptStr: scriptStr }); 
        }
        else {
            htmlImg = juicer($("#upload_multi_ImgTemplate").html(), { columnItem: columns, urlStrs: urls, flieName: files, IsShow: sort, scriptStr: scriptStr });
        }
        return htmlImg;
    }
})
/*lazyLoadImg*/

/* 动态加载html页面 */
$.fn.extend({
    /*
    url: html页面路径
    method:加载页面后执行的方法
    */
    LoadScriptTemplate: function (url, method) {
        if (url == "" || typeof (method) != "function") {
            throw "参数不合法";
        }
        $(this).load(url, function () {
            method();
        });
    }
})
/* 动态加载html页面 */