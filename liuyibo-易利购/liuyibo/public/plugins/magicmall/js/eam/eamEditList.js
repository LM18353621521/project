

var EamEditList = function (settings, sourceJson) {

    this._settings = settings;
    this._sourceJson = sourceJson;

    this._dataSort = function (data, columns) {

        var listItem = null;
        var list = []
        $(data).each(function (index, element) {

            listItem = [];
            $(columns).each(function (cindex, celement) {
                listItem.push(eval("element." + celement.name))
            });
            list.push(listItem);
        });

        return list;
    }
}
EamEditList.prototype.rendering = function () {

    var columns = this._sourceJson.columns;
    var tplc = juicer($("#tmplate_table_column").html(), { list: columns }); //获取模板渲染数据
    $("#table").append(tplc);
}
EamEditList.prototype.loadData = function (serachItems) {

    if (!serachItems) {
        EamListManage.serach.start();
        return;
    }

    if (this._settings.load.dataForwardEvent) {
        if (!this._settings.load.dataForwardEvent()) return;
    }

    var _this = this;


    $("#table").ajaxPageTable(1, this._settings.load.dataUrl, {
        assemblys: $("#assemblys").val().trim(),
        separateTableIdentity: $("#separateTableIdentity").val().trim(),
        serachItems: serachItems
    },

        function (data) {
            if (_this._settings.load.dataAfterEvent)
                data = _this._settings.load.dataAfterEvent(data);
            console.log(data);
            var list = _this._dataSort(data, _this._sourceJson.columns);
            console.log(list);

            var tpl = juicer($("#tmplate_table_data").html(), { list: list, columns: _this._sourceJson.columns, isAllowEdit: EamListManage.settings.edit.isAllowEdit, isAllowDel: EamListManage.settings.delete.isAllowDel }); //获取模板渲染数据
            //console.log(tpl);
            var $table = $("#table");
            $table.append(tpl);
            //数据绑定后
            if (_this._settings.load.dataBindAfterEvent)
                _this._settings.load.dataBindAfterEvent($table, data);
            ////猪场没有修改和删除的权限
            // var Type = getCookie("yz.wisdom.trace.basicsmodels.yz_user_info_dto.pigfarm_type");
            // if (Type == "1") {
            //     //猪场登录
            //     $(".Edit").hide();
            //     $(".Delte").hide();
            // }
        });
}
EamEditList.prototype.actionEdit = function (id) {

    if (this._settings.edit.actionForwardEvent) {
        if (!this._settings.edit.actionForwardEvent(id)) return;
    }

    var customLoadScript = "";
    if (EamListManage.isCustomLoadScript)
        customLoadScript = "&isCustomLoadScript=1";
    window.location.href = this._settings.edit.actionUrl + (this._settings.edit.actionUrl.indexOf("?") >= 0 ? "&" : "?") + "id=" + id + "&assemblys=" + $("#assemblys").val() + "&separateTableIdentity=" + $("#separateTableIdentity").val().trim() + customLoadScript;
}
EamEditList.prototype.actionLook = function (id, value) {

    if (this._settings.edit.actionForwardEvent) {
        if (!this._settings.edit.actionForwardEvent(id)) return;
    }
    if (value == undefined || value == null) {
        value = 1;
    }
    var customLoadScript = "";
    if (EamListManage.isCustomLoadScript)
        customLoadScript = "&isCustomLoadScript=1";

    window.location.href = this._settings.edit.actionUrl + (this._settings.edit.actionUrl.indexOf("?") >= 0 ? "&" : "?") + "IsLook=" + value + "&id=" + id + "&assemblys=" + $("#assemblys").val() + "&separateTableIdentity=" + $("#separateTableIdentity").val().trim() + customLoadScript;
}
EamEditList.prototype.actionDel = function (ids) {

    if (this._settings.delete.actionForwardEvent) {
        if (!this._settings.delete.actionForwardEvent(ids)) return;
    }

    $.wconfirm("是否确定删除选中数据项？", function () {
        $.post("/EAM/Delete", { ids: ids, assemblys: $("#assemblys").val(), separateTableIdentity: $("#separateTableIdentity").val() }, function (obj) {
            if (parseInt(obj) > 0) {
                $.wmessage("删除成功！",1);
                window.location.reload(true);
            }
        }, "text");
    })

    if (this._settings.delete.actionAfterEvent) {
        this._settings.delete.actionAfterEvent(ids);
    }
}
EamEditList.prototype.actionAdd = function () {

    if (this._settings.add.actionForwardEvent) {
        if (!this._settings.add.actionForwardEvent()) return;
    }

    var customLoadScript = "";
    if (EamListManage.isCustomLoadScript)
        customLoadScript = "&isCustomLoadScript=1";
    window.location.href = this._settings.add.actionUrl + (this._settings.add.actionUrl.indexOf("?") >= 0 ? "&" : "?") + "assemblys=" + $("#assemblys").val() + "&separateTableIdentity=" + $("#separateTableIdentity").val().trim() + customLoadScript;
}