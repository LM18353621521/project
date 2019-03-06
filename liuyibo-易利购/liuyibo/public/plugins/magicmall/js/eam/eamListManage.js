
/**
 * 自动映射列表管理
 */
var EamListManage = {

    settings: {
        load: {
            title:"列表",
            dataUrl: null,
            dataForwardEvent: null,
            dataAfterEvent: null
        },
        edit: {
            isAllowEdit: true,
            actionUrl: null,
            actionForwardEvent: null
        },
        delete: {
            isAllowDel: true,
            actionUrl: null,
            actionForwardEvent: null,
            actionAfterEvent: null
        },
        add: {
            title:"新增",
            isAllowAdd: true,
            actionUrl: null,
            actionForwardEvent: null
        },
        serach: {
            actionUrl: null,
            actionForwardEvent: null,// 等于 Equal,// 模糊Like
            renderingAfterEvent: null,
            renderingForwardEvent: null
        },
        batchs: { // 批量操作

            isAllowBatch: true,
            batchsEvent: function (batchsItems) { return batchsItems; }
        }
    },
    sourceJson: null,
    list: null,
    serach: null,
    isCustomLoadScript: false,
    batchsItems: [
        {
            key: "batchDel", text: "批量删除" , event: function () {

                var idDom = $("#table [type=checkbox]:checked");
                var ids = "";
                for (var i = 0; i < idDom.length; i++) {
                    if ($(idDom[i]).val() == "on") {
                        continue;
                    }
                    ids += $(idDom[i]).val() + ",";
                }
                if (ids == undefined || ids == "") {
                    
                    $.wmessage("请选择要删除的选项",2);
                    return;
                }

                // 调用批量删除
                EamListManage.list.actionDel(ids);
            }
        }
    ],

    _customLoadScript: function () {

        var result = false;
        var isCustomLoadScript = $.getUrlParamters("isCustomLoadScript");
        var customLoadScript = $("#assemblys").val().split(',')[1].split('.')[3];
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
    _viewInit: function () {

        if (EamListManage.settings.batchs.batchsEvent)
            EamListManage.batchsItems = EamListManage.settings.batchs.batchsEvent(EamListManage.batchsItems);

        $(EamListManage.batchsItems).each(function (index, element) {

            if ($("#batchsSel").find("option[value='" + element.key + "']").length == 0)
                $("#batchsSel").append("<option value=\"" + element.key + "\" onclick=\"alert('ggg')\">" + element.text + "</option>"); 
        });

        $("#batchsSel").unbind("change");
        $("#batchsSel").bind("change", function () {
            if ($(this).val() == "-1")
                return false;

            var option = $(this).find("option[value='" + $(this).val() + "']");
            $(EamListManage.batchsItems).each(function (index, element) {
                if (element.key == option.val()) {
                    element.event();
                    return false;
                }
            });
        });

        $("#listTitle").text(EamListManage.settings.load.title);
        $("#addTitle").text(EamListManage.settings.add.title);
        if (!EamListManage.settings.add.isAllowAdd)
            $("#addTitle").css("display", "none");
    },

    init: function (settings) {
        if (settings) {
            var pObj = null;
            var pChildObjValue = null;
            for (var p in settings) {

                pObj = eval("settings." + p);
                for (var pChild in pObj) {

                    pChildObjValue = eval("settings." + p + "." + pChild);
                    if (pChildObjValue != undefined)
                        eval("EamListManage.settings." + p + "." + pChild + "=pChildObjValue");
                }
            }
        }

        if (EamListManage.isCustomLoadScript) {

            EamListManage.serach.rendering();
            EamListManage.list.loadData();
        }

        // 初始化
        EamListManage._viewInit();   
    },

    load: function (sourceJson) {
        
       if (!sourceJson)
            return;

        var columnsSourceJson = eval("[" + sourceJson.columnsStr + "]")[0];
        var serachColumnsJson = eval("[" + sourceJson.serachColumnsStr + "]")[0];
        EamListManage.sourceJson = sourceJson;
        EamListManage.list = new EamEditList(EamListManage.settings, columnsSourceJson);
        EamListManage.list.rendering();

        EamListManage.serach = new EAMSerach(EamListManage.settings, serachColumnsJson, columnsSourceJson);
        
        EamListManage.isCustomLoadScript = EamListManage._customLoadScript();
        if (!EamListManage.isCustomLoadScript) {

            EamListManage.serach.rendering();
            EamListManage.list.loadData();
        }
        
    }
}

//var EamEditList = function (settings, sourceJson) {

//    this._settings = settings;
//    this._sourceJson = sourceJson;

//    this._dataSort = function (data, columns) {

//        var listItem = null;
//        var list = []
//        $(data).each(function (index, element) {

//            listItem = [];
//            $(columns).each(function (cindex, celement) {
//                listItem.push(eval("element." + celement.name))
//            });
//            list.push(listItem);
//        });

//        return list;
//    }
//}
//EamEditList.prototype.rendering = function () {

//    var columns = this._sourceJson.columns;
//    var tplc = juicer($("#tmplate_table_column").html(), { list: columns }); //获取模板渲染数据
//    $("#table").append(tplc);    
//}
//EamEditList.prototype.loadData = function (serachItems) {

//    if (!serachItems) {
//        EamListManage.serach.start();
//        return;
//    }

//    if (this._settings.load.dataForwardEvent) {
//        if (!this._settings.load.dataForwardEvent()) return;
//    }

//    var _this = this;

  
//    $("#table").ajaxPageTable(1, this._settings.load.dataUrl, {
//        assemblys: $("#assemblys").val().trim(),
//        separateTableIdentity: $("#separateTableIdentity").val().trim(),
//        serachItems: serachItems
//    },

//        function (data) {
//            if (_this._settings.load.dataAfterEvent)
//                data = _this._settings.load.dataAfterEvent(data);
//            console.log(data);
//            var list = _this._dataSort(data, _this._sourceJson.columns);
//            console.log(list);

//            var tpl = juicer($("#tmplate_table_data").html(), { list: list, columns: _this._sourceJson.columns, isAllowEdit: EamListManage.settings.edit.isAllowEdit, isAllowDel: EamListManage.settings.delete.isAllowDel }); //获取模板渲染数据
//            //console.log(tpl);
//            var $table = $("#table");
//            $table.append(tpl);
//            //数据绑定后
//            if (_this._settings.load.dataBindAfterEvent)
//                _this._settings.load.dataBindAfterEvent($table, data);
//           ////猪场没有修改和删除的权限
//           // var Type = getCookie("yz.wisdom.trace.basicsmodels.yz_user_info_dto.pigfarm_type");
//           // if (Type == "1") {
//           //     //猪场登录
//           //     $(".Edit").hide();
//           //     $(".Delte").hide();
//           // }
//        });
//}
//EamEditList.prototype.actionEdit = function (id) {

//    if (this._settings.edit.actionForwardEvent) {
//        if (!this._settings.edit.actionForwardEvent(id)) return;
//    }

//    var customLoadScript = "";
//    if (EamListManage.isCustomLoadScript)
//        customLoadScript = "&isCustomLoadScript=1";
//   window.location.href = this._settings.edit.actionUrl + (this._settings.edit.actionUrl.indexOf("?") >= 0 ? "&" : "?") + "id=" + id + "&assemblys=" + $("#assemblys").val() + "&separateTableIdentity=" + $("#separateTableIdentity").val().trim() + customLoadScript;
//}
//EamEditList.prototype.actionLook = function (id, value) {

//    if (this._settings.edit.actionForwardEvent) {
//        if (!this._settings.edit.actionForwardEvent(id)) return;
//    }
//    if (value == undefined || value == null) {
//        value = 1;
//    }
//    var customLoadScript = "";
//    if (EamListManage.isCustomLoadScript)
//        customLoadScript = "&isCustomLoadScript=1";

//    window.location.href = this._settings.edit.actionUrl + (this._settings.edit.actionUrl.indexOf("?") >= 0 ? "&" : "?") + "IsLook=" + value + "&id=" + id + "&assemblys=" + $("#assemblys").val() + "&separateTableIdentity=" + $("#separateTableIdentity").val().trim() + customLoadScript;
//}
//EamEditList.prototype.actionDel = function (ids) {

//    if (this._settings.delete.actionForwardEvent) {
//        if (!this._settings.delete.actionForwardEvent(ids)) return;
//    }

//$.wconfirm("是否确定删除选中数据项？", function () {
//        $.post("/EAM/Delete", { ids: ids, assemblys: $("#assemblys").val(), separateTableIdentity: $("#separateTableIdentity").val() }, function (obj) {
//            if (parseInt(obj) > 0) {
//                $.wmessage("删除成功！",1);
//                window.location.reload(true);
//            }
//        }, "text");
//    })

//    if (this._settings.delete.actionAfterEvent) {
//        this._settings.delete.actionAfterEvent(ids);
//    }
//}
//EamEditList.prototype.actionAdd = function () {

//    if (this._settings.add.actionForwardEvent) {
//        if (!this._settings.add.actionForwardEvent()) return;
//    }
   
//    var customLoadScript = "";
//    if (EamListManage.isCustomLoadScript)
//        customLoadScript = "&isCustomLoadScript=1";
//    window.location.href = this._settings.add.actionUrl + (this._settings.add.actionUrl.indexOf("?") >= 0 ? "&" : "?") + "assemblys=" + $("#assemblys").val() + "&separateTableIdentity=" + $("#separateTableIdentity").val().trim() + customLoadScript;
//}

//var EAMSerach = function (settings, serachSourceJson, columnsSourceJson) {
    
//    this._settings = settings;
//    this._serachSourceJson = serachSourceJson;
//    this._columnsSourceJson = columnsSourceJson;
//    this.serachs = [];
//}
//EAMSerach.prototype.rendering = function () {

//    var serachColumn = null;
//    //var column = null;
//    // 循环进行渲染组件
//    for (var i = 0; i < this._serachSourceJson.columns.length; i++) {

//        serachColumn = this._serachSourceJson.columns[i];
//        var childList = [];
//        var childs = eval("this._serachSourceJson.childDataTables." + serachColumn.name);
//        var item = null;
//        $(childs).each(function (i, e) {
//            item = {};
//            $(e).each(function (ii, ee) {
//                eval("item." + ee.key + "='" + ee.value + "'");
//            });
//            childList.push(item);
//        });
//        if (childs) {
//            if (EamListManage.settings.serach.renderingForwardEvent)
//                eval("EamListManage.settings.serach.renderingForwardEvent(serachColumn, childList);");
//        }
//        var serachObj = eval("new " + serachColumn.searchMode + "SerachView(serachColumn, childList);");
//        this.serachs.push({ name: serachColumn.name, text: serachColumn.text, searchMode: serachColumn.searchMode, serachObjHTML: serachObj.view(), serachObj: serachObj, childDataTables: this._serachSourceJson.childDataTables });
//    }

//    //获取模板渲染数据
//    if (this._serachSourceJson.columns.length > 0) {
//        var tpl = juicer($("#tmplate_serach_column").html(), { list: this.serachs });
//        $("#serachs").html(tpl);
//        $("#serach_collapse").css("display", "black");
//        $("#collapseFiltrate").addClass("in");
//        $("#collapseFiltrate").removeClass("collapse");

//        var head = document.getElementsByTagName('head')[0];
//        var script = document.createElement('script');
//        script.type = 'text/javascript';
//        script.src = "/js/drp/drp-inti.js";
//        head.appendChild(script);

//        if (EamListManage.settings.serach.renderingAfterEvent)
//            EamListManage.settings.serach.renderingAfterEvent();
//    } else {

//        $("#serach_collapse").css("display", "none");
//        $("#collapseFiltrate").addClass("collapse");
//        $("#collapseFiltrate").removeClass("in");
//    }
//}
//EAMSerach.prototype.start = function () {

//    var serachItems = [];
//    var params = window.location.href.substring(window.location.href.indexOf('?') + 1).split('&');
//    $(params).each(function (index, element) {
//        if (element.split('=')[0] != "assemblys" && element.split('=')[0] != "isCustomLoadScript" && element.split('=')[0] != "separateTableIdentity") {
//            if (element.split('=')[1])
//                serachItems.push({ name: element.split('=')[0], value: element.split('=')[1], serachMode: "Equal" });
//        }
//    });

//    var value = null;
//    $(this.serachs).each(function (index, element) {

//        serachItems.push({ name: element.name, value: element.serachObj.getValue(), serachMode: element.searchMode });
//    });

//    if (this._settings.serach.actionForwardEvent) {
//        if (!this._settings.serach.actionForwardEvent(serachItems)) return;
//    }

//    EamListManage.list.loadData(serachItems);
//}
//EAMSerach.prototype.clear = function () {

//    var serachItems = [];
//    $(this.serachs).each(function (index, element) {

//        element.serachObj.clear();
//        serachItems.push({ name: element.name, value: element.serachObj.getValue(), serachMode: element.searchMode });
//    });

//    if (this._settings.serach.actionForwardEvent) {
//        if (!this._settings.serach.actionForwardEvent(serachItems)) return;
//    }

//    EamListManage.list.loadData(serachItems);
//}

function ChangeColumnSort(NeedChangeDomName, TargetDomName) {
    var NeedChangeDomNameDom = $("[data_name=" + NeedChangeDomName + "]");
    for (var i = 0; i < NeedChangeDomNameDom.length; i++) {
        var Item = NeedChangeDomNameDom[i];
        var NeedStr = Item.outerHTML;
        $(Item).parent().children("[data_name=" + TargetDomName + "]").after(NeedStr);
        $(Item).remove();
    }
}

//$("#all-del").click(function () {
//    var ids = $("#table").getTableckattr();
//    if (ids == undefined || ids == "") {
//        $.wmessage("请选择要删除的选项",2);
//        return;
//    }
//    delData(ids);
//})

//function delData(ids) {
//    $.mvcAjaxBaseDel("Menu", ids, function (d) {
//        if (d > 0) {
//            $.wmessage("删除成功",1);
//            LoadList();

//        } else {
//            $.wmessage("删除失败",3);
//        }
//    })
//}

//function IsEnable(ids) {
//    $.mvcAjaxBaseIsEnable("Menu", ids, function (d) {
//        if (d > 0) {
//            var statushtml = $("#tdStatus_" + ids).html() == "启用" ? "禁用" : "启用";
//            $("#tdStatus_" + ids).html(statushtml);
//            $("#Status_" + ids).html(statushtml);

//        }
//    })
//}
