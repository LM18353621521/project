
var SerachView = function (serachColumn) {

    this.getValue = function () {

        return $("#SERACH_CONTROL_" + serachColumn.name).val();
    }
    this.clear = function () {

        $("#SERACH_CONTROL_" + serachColumn.name).val("");
    }
}


/**
 * 等于搜索组件显示
 */
var EqualSerachView = function (serachColumn) {

    // 继承
    SerachView.apply(this, [serachColumn]);

    this.view = function () {
        return juicer($("#tmplate_serach_input").html(), { serachColumn: serachColumn });
        
        //return "<input id='SERACH_CONTROL_" + serachColumn.name + "' controlType='" + serachColumn.searchMode + "' type='text' class='form-control' placeholder='" + serachColumn.text + "' data-form='" + serachColumn.name + "' />";
    }
}

/**
 * 模糊搜索组件显示
 */
var LikeSerachView = function (serachColumn) {

    // 继承
    SerachView.apply(this, [serachColumn]);

    this.view = function () {

        return juicer($("#tmplate_serach_input").html(), { serachColumn: serachColumn });
        
        //return "<input id='SERACH_CONTROL_" + serachColumn.name + "' controlType='" + serachColumn.searchMode + "' type='text' class='form-control' placeholder='" + serachColumn.text + "' data-form='" + serachColumn.name + "' />";
    }
}

/**
 * 范围搜索组件显示
 */
var DateRangeSerachView = function (serachColumn) {

    // 继承
    SerachView.apply(this, [serachColumn]);

    this.view = function () {
        return juicer($("#tmplate_serach_calendar").html(), { serachColumn: serachColumn });
        //return "<input type='text' controlType='" + serachColumn.searchMode + "' id='SERACH_CONTROL_" + serachColumn.name + "' placeholder='" + serachColumn.text + "' data-form='" + serachColumn.name + "' data-drp='2' readonly='readonly' class='form-control calendar' />"
    }
}

/**
 * 下拉搜索组件显示
 */
var DropDownListSerachView = function (serachColumn, childDataTables) {

    // 继承
    SerachView.apply(this, [serachColumn]);

    this.view = function () {
        var table = "";
        var list = "";

        var dics = [];
        var dicItem = null;
        var value = null;
        var ii = 0;
        $(childDataTables).each(function (i, e) {
            dicItem = {};
            ii = 1;
            for (var ee in e) {

                var value = eval("e." + ee);
                if (ii == 1)
                    eval("dicItem.key='" + value + "'");
                else if (ii == 2)
                    eval("dicItem.value='" + value + "'");
                else
                    break;

                ii += 1;
            }
            dics.push(dicItem)
        });

        return juicer($("#tmplate_serach_select").html(), { serachColumn: serachColumn, list: dics});

        //var item = null;
        //list = "<select id='SERACH_CONTROL_" + serachColumn.name + "' controlType = '" + serachColumn.searchMode + "' class='form-control' placeholder='" + serachColumn.text + "' data-form='" + serachColumn.name + "'><option value=''>--请选择--</option>";
        //for (var i = 0; i < dics.length; i++)
        //    list += "<option value='" + dics[i].key + "'>" + dics[i].value + "</option>";
        //list += "</select>";

        //return list;
    }
}
