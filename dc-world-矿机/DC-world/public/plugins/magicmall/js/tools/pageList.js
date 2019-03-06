/// <reference path="jquery.min.js" />
String.prototype.Format = function () {
    if (arguments.length == 0) return this;
    for (var s = this, i = 0; i < arguments.length; i++)
        s = s.replace(new RegExp("\\{" + i + "\\}", "g"), arguments[i] == null ? "" : arguments[i]);
    return s;
};

/*封装常用分页~组件*/
$.fn.controlPager = function (opts) {

    if (opts == undefined) { return; }

    //总数量必须传
    if (!opts.hasOwnProperty("pageCount")) {
        return;
    }
    if (!opts.hasOwnProperty("pageNow"))
        opts.pageNow = 1;
    if (!opts.hasOwnProperty("pageSize"))
        opts.pageSize = 10;
    if (!opts.hasOwnProperty("pageNum")) {
        opts.pageNum = 5;
    }
    //onSearch
    if (!opts.hasOwnProperty("onPage")) {
        opts.onPage = function () { };
    }
    if (!opts.hasOwnProperty("onSearch")) {
        opts.onSearch = function () { };
    }
    opts.pageStart = 1; //每一页的页码起始数，由pageNow和pageNum动态计算
    opts.pageEnd = 0;  //每一页的页码结尾数 ，由pageNow和pageNum动态计算
    var isOdd = true; // 是否是奇数，用于页码偶数跟奇数不同算法 默认5页 是奇数
    if (parseInt(opts.pageNum) % 2 == 0) {
        isOdd = false;
    }

    //更改描述显示
    if (!opts.hasOwnProperty("pagerdesr")) {
        opts.pagerdesr = false;
    }
    //总共分多少页
    var totalpageCount = parseInt((opts.pageCount % opts.pageSize) == 0 ? opts.pageCount / opts.pageSize : opts.pageCount / opts.pageSize + 1);
    if (parseInt(opts.pageNow) <= parseInt(opts.pageNum / 2 + 1)) {
        opts.pageStart = 1;
        opts.pageEnd = opts.pageNum;
    } else if (parseInt(opts.pageNow) > parseInt(opts.pageNum / 2 + 1)) {
        opts.pageStart = parseInt(opts.pageNow) - parseInt(opts.pageNum / 2);
        if (isOdd) {
            opts.pageEnd = parseInt(opts.pageNow) + parseInt(opts.pageNum / 2);
        } else {
            opts.pageEnd = parseInt(opts.pageNow) + parseInt(opts.pageNum / 2 - 1);
        }
    }
    // 对pageEnd 进行校验，并重新赋值
    if (opts.pageEnd > opts.pageCount) {
        opts.pageEnd = opts.pageCount;
    }
    if (opts.pageEnd <= opts.pageNum) {// 当不足pageNum数目时，要全部显示，所以pageStart要始终置为1
        opts.pageStart = 1;
    }
    var pageHtmlData = {
        pagerdesr: '<div class="page-desc">总数据<span>{0}</span>条-共分<span>{1}</span>页</div>'.Format(opts.pageCount, totalpageCount),
        pageUL: ' <ul class="pager">',
        home: '<li><a href="javascript:;"  data-pn="1">首页</a></li>',
        previous: '<li class="previous" ><a href="javascript:;" data-pn="{0}">«</a></li>'.Format((opts.pageNow - 1)),
        btn: '<li class="{1}"><a href="javascript:;"  data-pn="{0}">{0}</a></li>',
        next: '<li class="next"><a href="javascript:;" data-pn="{0}" >»</a></li>'.Format((opts.pageNow + 1)),
        end: '<li><a href="javascript:;" data-pn="{0}">尾页</a></li>'.Format(totalpageCount),
        pageEndUL: ' </ul>',
        pagerSearch: '<div class="page-search-p">' +
                        '<div class="input-group">' +
                        '        <input type="text" class="form-control" />' +
                        '        <span class="input-group-addon" data-page-search="btn"><i class="fa fa-search"></i></span>' +
                        '    </div>' +
                        '</div>'
    };

    var pageHtml = '';
    for (var i = opts.pageStart; i <= (opts.pageEnd >= totalpageCount ? totalpageCount : opts.pageEnd) ; i++) {
        if (opts.pageNow == i) {
            pageHtml += pageHtmlData.btn.Format(i, "active");
        } else {
            pageHtml += pageHtmlData.btn.Format(i, "");
        }
    }

    //自定义
    if (opts.pagerdesr) {
        pageHtmlData.pagerdesr = opts.pagerdesr.Format(opts.pageCount, totalpageCount);
    }

    //删掉上一页跟首页
    if (opts.pageNow <= 1) {
        pageHtmlData.home = "";
        pageHtmlData.previous = "";
    }
    //结尾删掉下一页跟尾页
    if (opts.pageNow >= totalpageCount) {
        pageHtmlData.next = "";
    }
    if (opts.pageCount == 0) {
        pageHtmlData.pagerSearch = "";
        pageHtmlData.end = "";
    }

    //追加分页
    this.html(
        pageHtmlData.pagerdesr +
        pageHtmlData.pageUL +
        pageHtmlData.home +
        pageHtmlData.previous +
        pageHtml +
        pageHtmlData.next +
        pageHtmlData.end +
        pageHtmlData.pageEndUL +
        pageHtmlData.pagerSearch
        );

    //绑定事件
    //页码点击 返回一个页面数
    this.find(".pager >li >a").click(function () {
        opts.onPage($(this).attr("data-pn"));
    })

    //搜索按钮点击
    this.find("*[data-page-search='btn']").click(function () {
        var txt = $(this).prev().val();
        if (!/^\d+$/.test(txt)) { txt = 1; }
        opts.onSearch(txt);
    })
}

/*
 * pindex 开始页
 * url 请求的地址
 * params 参数 json / 字符串
 * success 返回数据
 * opts 可扩展参数
*/
$.fn.ajaxPageTable = function (pIndex, url, params, success, opts) {
    if (opts === undefined) {
        opts = {};
    }
    if (!opts.hasOwnProperty("load")) {
        opts.load = true;
    }
    if (!opts.hasOwnProperty("error")) {
        opts.error = function () { };
    }
    if (!opts.hasOwnProperty("pageEl")) {
        opts.pageEl = "#list-pagination-page";
    }
    var $t = this;
    var _data = $.extend({ pIndex: pIndex }, params);
    if (!opts.hasOwnProperty("beforeSend")) {
        opts.beforeSend = function () {
            if (opts.load === undefined || opts.load) { opts.load = "加载中..."; }
            var cont = $t.find("tr").eq(0).children("th").length;
            var vrtd = '<tr><td colspan="' + cont + '"><div class="table-data-load">' +
                    '    <i class="fa fa-spinner fa-pulse"></i>' +
                    '    <span>' + opts.load + '</span>' +
                    '</div></td></tr>';
            $t.find("tr:gt(0)").remove();
            $t.append(vrtd);
        };
    }
    $.ajax({
        url: url,
        type: "post",
        dataType: 'json',
        data: _data,
        beforeSend: opts.beforeSend,
        cache: false,
        success: function (d) {
            if (!opts.hasOwnProperty("doNotClear")) {
                $t.find("tr:gt(0)").remove();
            }
            success(d);
            if (d.content == null || d.content.length <= 0 || d.content.Data.length<=0) {
                var cont = $t.find("tr").eq(0).children("th").length;
                //var vrtd = '<tr><td colspan="' + cont + '"><div class="table-data-null">' +
                //        '    <i class="fa fa-info-circle"></i>' +
                //        '    <span>无数据</span>' +
                //        '</div></td></tr>';
                var vrtd = '<tr><td colspan="' + cont + '">\
                                <div class="nodata">\
                                    <i></i>\
                                    <div>抱歉，当前页面暂无数据</div>\
                                </div>\
                           </td></tr>';
                if (opts.hasOwnProperty("noData")) {
                    vrtd = opts.noData;
                }
                $t.find("tr:gt(0)").remove();
                $t.append(vrtd);
            }

            $(opts.pageEl).controlPager(
               {
                   pageCount: d.content.DataCount,
                   pageNow: d.content.PageIndex,
                   pageSize: d.content.PageSize,
                   pageNum: d.content.PageNum,
                   onPage: function (a) {
                       $($t).ajaxPageTable(a, url, params, success, opts);
                   },
                   onSearch: function (a) {
                       $($t).ajaxPageTable(a, url, params, success, opts);
                   }
               }
            );
        },
        error: opts.error,
        complete: function (XMLHttpRequest, textStatus) {
            XMLHttpRequest = null;
        }
    })
}

/*
 * 获取当前table中选中的多选按钮
 * opts 可以选 
     默认选中 data-id 属性值
     返回格式 v,v,v 字符串
     设置 object类型 将返回 数组
 */
$.fn.getTableckattr = function (opts) {
    if (opts === undefined || opts == null) { opts = { at: "data-id", type: "," } };
    if (typeof (opts.type) === "object") {
        return this.find("input[type='checkbox'][data-checked='table']:checked")
         .map(function () {
             return $(this).attr(opts.at);
         }).get();
    }
    return this.find("input[type='checkbox'][data-checked='table']:checked")
         .map(function () {
             return $(this).attr(opts.at);
         }).get().join(opts.type);
}

/*
 * 绑定全选按钮
 */
$(function () {
    if ($('[data-toggle="tooltip"]').length > 0) {
        //$('[data-toggle="tooltip"]').tooltip({ placement: 'bottom' });
    }
    $(document.body).on("click", "input[type='checkbox'][data-checked-all='table']", function () {
        $("input[type='checkbox'][data-checked='table']").prop("checked", $(this).is(":checked"));
        $.allDelBtn($(this).is(":checked"));
        //if ($(this).is(":checked")) {
        //    $("input[type='checkbox'][data-checked='table']").prop("checked", true);
        //    $.allDelBtn('s');
        //    return;
        //}
        //$("input[type='checkbox'][data-checked='table']").prop("checked", false);
        //$.allDelBtn();
    });
    $(document.body).on("click", "input[type='checkbox'][data-checked='table']", function () {
        if ($("input[type='checkbox'][data-checked='table']:checked").length > 0) {
            $.allDelBtn(true);
            return;
        }

    });

    //刷新当前页面
    $(document.body).on("click", "*[data-refresh-this-page='yes']", function () {
        window.location.reload();
    });

    //清空时间内容
    $(document.body).on("click", "*[data-empty-drtp]", function () {
        $(this).prev("input[type='text']").val("");
    });

});
$.extend({
    allDelBtn: function (o) {
        var obj = $('a[data-del-all-checked="true"]');
        if (o) {
            obj.css({ display: "inline-block" }).addClass("alldel-btn-in");
            return;
        }
        obj.removeClass("alldel-btn-in").addClass("alldel-btn-out");
        setTimeout(function () {
            obj.css({ display: "none" });
        }, 330);
    }
})