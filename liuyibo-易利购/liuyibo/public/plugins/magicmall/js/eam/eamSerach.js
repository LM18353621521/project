

var EAMSerach = function (settings, serachSourceJson, columnsSourceJson) {

    this._settings = settings;
    this._serachSourceJson = serachSourceJson;
    this._columnsSourceJson = columnsSourceJson;
    this.serachs = [];
}
EAMSerach.prototype.rendering = function () {

    var serachColumn = null;
    //var column = null;
    // 循环进行渲染组件
    for (var i = 0; i < this._serachSourceJson.columns.length; i++) {

        serachColumn = this._serachSourceJson.columns[i];
        var childList = [];
        var childs = eval("this._serachSourceJson.childDataTables." + serachColumn.name);
        var item = null;
        $(childs).each(function (i, e) {
            item = {};
            $(e).each(function (ii, ee) {
                eval("item." + ee.key + "='" + ee.value + "'");
            });
            childList.push(item);
        });
        if (childs) {
            if (EamListManage.settings.serach.renderingForwardEvent)
                eval("EamListManage.settings.serach.renderingForwardEvent(serachColumn, childList);");
        }
        var serachObj = eval("new " + serachColumn.searchMode + "SerachView(serachColumn, childList);");
        this.serachs.push({ name: serachColumn.name, text: serachColumn.text, searchMode: serachColumn.searchMode, serachObjHTML: serachObj.view(), serachObj: serachObj, childDataTables: this._serachSourceJson.childDataTables });
    }

    //获取模板渲染数据
    if (this._serachSourceJson.columns.length > 0) {
        var tpl = juicer($("#tmplate_serach_column").html(), { list: this.serachs });
        $("#serachs").html(tpl);
        $("#serach_collapse").css("display", "black");
        $("#collapseFiltrate").addClass("in");
        $("#collapseFiltrate").removeClass("collapse");

        var head = document.getElementsByTagName('head')[0];
        var script = document.createElement('script');
        script.type = 'text/javascript';
        script.src = "/js/drp/drp-inti.js";
        head.appendChild(script);

        if (EamListManage.settings.serach.renderingAfterEvent)
            EamListManage.settings.serach.renderingAfterEvent();
    } else {

        $("#serach_collapse").css("display", "none");
        $("#collapseFiltrate").addClass("collapse");
        $("#collapseFiltrate").removeClass("in");
    }
}
EAMSerach.prototype.start = function () {

    var serachItems = [];
    var params = window.location.href.substring(window.location.href.indexOf('?') + 1).split('&');
    $(params).each(function (index, element) {
        if (element.split('=')[0] != "assemblys" && element.split('=')[0] != "isCustomLoadScript" && element.split('=')[0] != "separateTableIdentity") {
            if (element.split('=')[1])
                serachItems.push({ name: element.split('=')[0], value: element.split('=')[1], serachMode: "Equal" });
        }
    });

    var value = null;
    $(this.serachs).each(function (index, element) {

        serachItems.push({ name: element.name, value: element.serachObj.getValue(), serachMode: element.searchMode });
    });

    if (this._settings.serach.actionForwardEvent) {
        if (!this._settings.serach.actionForwardEvent(serachItems)) return;
    }

    EamListManage.list.loadData(serachItems);
}
EAMSerach.prototype.clear = function () {

    var serachItems = [];
    $(this.serachs).each(function (index, element) {

        element.serachObj.clear();
        serachItems.push({ name: element.name, value: element.serachObj.getValue(), serachMode: element.searchMode });
    });

    if (this._settings.serach.actionForwardEvent) {
        if (!this._settings.serach.actionForwardEvent(serachItems)) return;
    }

    EamListManage.list.loadData(serachItems);
}