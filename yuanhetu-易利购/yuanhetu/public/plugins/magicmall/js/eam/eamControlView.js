
var ControlView = function (dataJson, columnItem, childDataTables) {

    this.getValue = function () {
        if (columnItem.controlType == 'BaiduEditor') {
            return eval("CONTROL_" + columnItem.name + ".getContent()");
        }
        else if (columnItem.controlType == 'Image') {
            //不保存域名
            try {
                return $("#CONTROL_" + columnItem.name).val().replace(UPLOAD_SERVER, '');
            }
            catch (e) { }
        }
        else if (columnItem.controlType == 'Region') {
            return $("#CONTROL_" + columnItem.name).attr('data-area-lastcode');
        }
        return $("#CONTROL_" + columnItem.name).val();
    }
}

/**
  * 文本框组件显示
  */
var TextBoxView = function (dataJson, columnItem, childDataTables) {

    // 继承
    ControlView.apply(this, [dataJson, columnItem, childDataTables]);

    this.edit = function () {
        var value = "";
        if (dataJson)
            value = eval("dataJson." + columnItem.name);
        
        return juicer($("#tmplate_control_edit_input").html(), { columnItem: columnItem, value: value, mode:"text" });
        //return "<input id='CONTROL_" + columnItem.name + "' type='text' class='form-control " + (columnItem.isNeed == 1 ? "IsNeedInput" : "") + "' placeholder='" + columnItem.text + "' data-form='" + columnItem.name + "' title='" + (value == null ? "" : value) + "' value='" + (value == null ? "" : value) + "' />";
    }
    this.view = function () {

        var value = "";
        if (dataJson)
            value = eval("dataJson." + columnItem.name);
        
        return juicer($("#tmplate_control_input").html(), { columnItem: columnItem, value: value, mode: "text" });
        //return "<input id='CONTROL_" + columnItem.name + "' type='text' class='form-control " + (columnItem.isNeed == 1 ? "IsNeedInput" : "") + "' placeholder='" + columnItem.text + "' data-form='" + columnItem.name + "' value='" + (value == null ? "" : value) + "' />";
    }
}
/**
  * 日期
  */
var DateTimeView = function (dataJson, columnItem, childDataTables) {

    // 继承
    ControlView.apply(this, [dataJson, columnItem, childDataTables]);

    this.edit = function () {
        var value = "";
        if (dataJson)
            value = eval("dataJson." + columnItem.name);
        
        //return "<input id='CONTROL_" + columnItem.name + "' type='text' class='form-control " + (columnItem.isNeed == 1 ? "IsNeedInput" : "") + "' placeholder='" + columnItem.text + "' data-form='" + columnItem.name + "' value='" + (value == null ? "" : value) + "' />";
 
        return juicer($("#tmplate_control_calendar").html(), { columnItem: columnItem, value: value });
        //return '<input type="text"  id="CONTROL_' + columnItem.name + '" placeholder="' + columnItem.text + '" data-opens="right" data-form="' + columnItem.name + '" data-drp="1" readonly="readonly" class="form-control calendar" value="' + (value == null ? "" : value.split(' ')[0]) + '">';
        //return "<input id='CONTROL_" + columnItem.name + "' ApplyFormatInEditMode=true type='Date' class='form-control " + (columnItem.isNeed == 1 ? "IsNeedInput" : "") + "' placeholder='" + columnItem.text + "' data-form='" + columnItem.name + "' value='" + (value == null ? "" : value) + "' />";
    }
    this.view = function () {
        var value = "";
        if (dataJson)
            value = eval("dataJson." + columnItem.name);
        
        //return "<input id='CONTROL_" + columnItem.name + "' type='text' class='form-control " + (columnItem.isNeed == 1 ? "IsNeedInput" : "") + "' placeholder='" + columnItem.text + "' data-form='" + columnItem.name + "' value='" + (value == null ? "" : value) + "' />";

        return juicer($("#tmplate_control_calendar").html(), { columnItem: columnItem, value: value });
        //return '<input type="text"  id="CONTROL_' + columnItem.name + '" placeholder="' + columnItem.text + '" data-opens="right" data-form="' + columnItem.name + '" data-drp="1" readonly="readonly" class="form-control calendar" value="' + (value == null ? "" : value.split(' ')[0]) + '">';
        //return "<input id='CONTROL_" + columnItem.name + "' ApplyFormatInEditMode=true type='Date' class='form-control " + (columnItem.isNeed == 1 ? "IsNeedInput" : "") + "' placeholder='" + columnItem.text + "' data-form='" + columnItem.name + "' value='" + (value == null ? "" : value) + "' />";
    }
}

/**
 * 文本域组件显示
 */
var TextAreaView = function (dataJson, columnItem, childDataTables) {

    // 继承
    //ControlView.apply(this, [dataJson, columnItem, childDataTables]);
    this.getValue = function () {

        return $("#CONTROL_" + columnItem.name).val().replace(/\n/g, '\\n');
    }

    this.edit = function () {
        var value = "";
        if (dataJson)
            value = eval("dataJson." + columnItem.name);

        if (IsLook == "1") {
            if (value == null)
                value = "";
            if (value.length > 12)
                value = value.substring(0, 12) + "...";
        }
        
        return juicer($("#tmplate_control_edit_textArea").html(), { columnItem: columnItem, value: value, isLook: IsLook});
        //if (IsLook=="1") {

        //    return '<input type="text"  id="SERACH_' + columnItem.name + '" placeholder="' + columnItem.text + '" data-opens="right" data-form="' + columnItem.name + '" data-drp="1" readonly="readonly" class="form-control calendar" title="' + (value == null ? "" : value) + '" value="' + (value == null ? "" : (value.length > 12 ? value.substring(0, 12) + "..." : value)) + '" title="' + (value == null ? "" : value)+'">';
        //}

        //return "<textarea id='CONTROL_" + columnItem.name + "' type='text' class='form-control " + (columnItem.isNeed == 1 ? "IsNeedInput" : "") + "' style='height:100px;' placeholder='" + columnItem.text + "' data-form='" + columnItem.name + "'>" + (value == null ? "" : value) + "</textarea>";
    }
    this.view = function () {
        var value = "";
        if (dataJson)
            value = eval("dataJson." + columnItem.name);
        
        return juicer($("#tmplate_control_textArea").html(), { columnItem: columnItem, value: value });
        //return '<input type="text"  id="SERACH_' + columnItem.name + '" placeholder="' + columnItem.text + '" data-opens="right" data-form="' + columnItem.name + '" data-drp="1" readonly="readonly" class="form-control calendar" title="' + (value == null ? "" : value) + '" value="' + (value == null ? "" : value) + '">';
    }
}

/**
 * 数字框组件显示
 */
var NumberView = function (dataJson, columnItem, childDataTables) {

    // 继承
    ControlView.apply(this, [dataJson, columnItem, childDataTables]);

    this.edit = function () {
        var value = "";
        if (dataJson)
            value = eval("dataJson." + columnItem.name);

        return juicer($("#tmplate_control_input").html(), { columnItem: columnItem, value: value, mode: "number" });
        //return "<input id='CONTROL_" + columnItem.name + "' type='number' class='form-control " + (columnItem.isNeed == 1 ? "IsNeedInput" : "") + "' placeholder='" + columnItem.text + "' data-form='" + columnItem.name + "' value='" + (value == null ? "" : value) + "' />";
    }
    this.view = function () {
        var value = "";
        if (dataJson)
            value = eval("dataJson." + columnItem.name);

        return juicer($("#tmplate_control_input").html(), { columnItem: columnItem, value: value, mode: "number" });
        //return "<input id='CONTROL_" + columnItem.name + "' type='number' class='form-control " + (columnItem.isNeed == 1 ? "IsNeedInput" : "") + "' placeholder='" + columnItem.text + "' data-form='" + columnItem.name + "' value='" + (value == null ? "" : value) + "' />";
    }
}
var decimalsView = function (dataJson, columnItem, childDataTables) {

    // 继承
    ControlView.apply(this, [dataJson, columnItem, childDataTables]);

    this.view = function () {
        var value = "";
        if (dataJson)
            value = eval("dataJson." + columnItem.name);

        return juicer($("#tmplate_control_input").html(), { columnItem: columnItem, value: value, mode: "number" });
        //return "<input id='CONTROL_" + columnItem.name + "' type='number' class='form-control " + (columnItem.isNeed == 1 ? "IsNeedInput" : "") + "' placeholder='" + columnItem.text + "' data-form='" + columnItem.name + "' value='" + (value == null ? "" : value) + "' />";
    }
    this.edit = function () {
        var value = "";
        if (dataJson)
            value = eval("dataJson." + columnItem.name);

        return juicer($("#tmplate_control_input").html(), { columnItem: columnItem, value: value, mode: "number" });
        //return "<input id='CONTROL_" + columnItem.name + "' type='number' class='form-control " + (columnItem.isNeed == 1 ? "IsNeedInput" : "") + "' placeholder='" + columnItem.text + "' data-form='" + columnItem.name + "' value='" + (value == null ? "" : value) + "' />";
    }
}

/**
 * 小数框组件显示
 */
var decimalsView = function (dataJson, columnItem, childDataTables) {

    // 继承
    ControlView.apply(this, [dataJson, columnItem, childDataTables]);

    this.edit = function () {
        var value = "";
        if (dataJson)
            value = eval("dataJson." + columnItem.name);

        return juicer($("#tmplate_control_input").html(), { columnItem: columnItem, value: value, mode: "text" });
        //return "<input id='CONTROL_" + columnItem.name + "' type='text' class='form-control " + (columnItem.isNeed == 1 ? "IsNeedInput" : "") + "' placeholder='" + columnItem.text + "' data-form='" + columnItem.name + "' value='" + (value == null ? "" : value) + "' />";
    }
    this.view = function () {
        var value = "";
        if (dataJson)
            value = eval("dataJson." + columnItem.name);

        return juicer($("#tmplate_control_input").html(), { columnItem: columnItem, value: value, mode: "text" });
        //return "<input id='CONTROL_" + columnItem.name + "' type='text' class='form-control " + (columnItem.isNeed == 1 ? "IsNeedInput" : "") + "' placeholder='" + columnItem.text + "' data-form='" + columnItem.name + "' value='" + (value == null ? "" : value) + "' />";
    }
}

/**
 * 下拉框组件显示
 */
var DropDownListView = function (dataJson, columnItem, childDataTables) {

    // 继承
    ControlView.apply(this, [dataJson, columnItem, childDataTables]);

    this.edit = function () {

        var value = "";
        var table = "";
        var list = "";
         
        if (dataJson)
            value = eval("dataJson." + columnItem.name);

        var item = null;
        //if (IsLook=='1') {
        //    list = "<select id='CONTROL_" + columnItem.name + "' class='form-control " + (columnItem.isNeed == 1 ? "IsNeedInput" : "") + "' placeholder='" + columnItem.text + "' data-form='" + columnItem.name + "' data-value='" + value + "'><option value='-1'></option>";
        //} else {
        //    list = "<select id='CONTROL_" + columnItem.name + "' class='form-control " + (columnItem.isNeed == 1 ? "IsNeedInput" : "") + "' placeholder='" + columnItem.text + "' data-form='" + columnItem.name + "' data-value='" + value + "'><option value='-1'>--请选择--</option>";
        //}

        var dics = [];
        var dicItem = null;
        var objValue = null;
        var ii = 0;
        $(childDataTables).each(function (i, e) {
            dicItem = {};
            ii = 1;
            for (var ee in e) {

                var objValue = eval("e." + ee);
                if (ii == 1)
                    eval("dicItem.key='" + objValue + "'");
                else if (ii == 2)
                    eval("dicItem.value='" + objValue + "'");
                else {
                    if (dicItem.TempStr==undefined) {
                        eval('dicItem.TempStr="' + ('  data-' + ee + '=\'' + objValue) + '\'"');
                    } else {
                        eval('dicItem.TempStr+="' + ('  data-' + ee + '=\'' + objValue) + '\'"');
                    }
                  
                }
                   
                    //break;

                ii += 1;
            }
            dics.push(dicItem)
        });

        //for (var i = 0; i < dics.length; i++) {

        //    item = dics[i];
        //    var chk = "";
        //    if (value == item.key) {
        //        chk = "selected='selected'";
        //    }
        //    list += "<option value='" + item.key + "' " + chk + "  " + (item.TempStr != undefined ? item.TempStr:"") + ">" + item.value + "</option>";
        //}
        //list += "</select>";

        //return list;
        return juicer($("#tmplate_control_edit_select").html(), { columnItem: columnItem,dics: dics, value: value, IsLook: IsLook }); 
    }
    this.view = function () {

        var value = "";
        var table = "";
        var list = "";
         
        if (dataJson)
            value = eval("dataJson." + columnItem.name);

        var dics = [];
        var dicItem = null;
        var objValue = null;
        var ii = 0;
        $(childDataTables).each(function (i, e) {
            dicItem = {};
            ii = 1;
            for (var ee in e) {

                var objValue = eval("e." + ee);
                if (ii == 1)
                    eval("dicItem.key='" + objValue + "'");
                else if (ii == 2)
                    eval("dicItem.value='" + objValue + "'");
                else
                    break;

                ii += 1;
            }
            dics.push(dicItem)
        });

        //var item = null;
        //list = "<select id='CONTROL_" + columnItem.name + "' class='form-control " + (columnItem.isNeed == 1 ? "IsNeedInput" : "") + "' placeholder='" + columnItem.text + "' data-form='" + columnItem.name + "' data-value='" + value + "'><option value='-1'></option>";
        //for (var i = 0; i < dics.length; i++) {

        //    item = dics[i];
        //    var chk = "";
        //    if (value == item.key) {
        //        chk = "selected='selected'";
        //    }
        //    list += "<option value='" + item.value + "' " + chk + ">" + item.value + "</option>";
        //}
        //list += "</select>";

        //return list;
        return juicer($("#tmplate_control_select").html(), { columnItem: columnItem, dics: dics, value: value }); 
    }
}

/**
 * 图片组件显示
 */
var ImageView = function (dataJson, columnItem, childDataTables) {

    // 继承
    ControlView.apply(this, [dataJson, columnItem, childDataTables]);

    this.edit = function () {
        var value = "";
        var _IsShow = true;
        var flieName = "";
        if (dataJson)
            value = eval("dataJson." + columnItem.name);
        if (value != null && value.length > 0) {
            value = value.replace("@*@", "");
            _IsShow = true;
            flieName = value.split('/')[value.split('/').length-1];
        }
        var scriptStr = '<script type="text/javascript" src="/js/uploader.js"></script >';
        var _imgObj = { url: value, index: 0, imgfileName: flieName, IsShow: _IsShow };
        return juicer($("#upload_single_ImgTemplate").html(), { columnItem: columnItem, ImgObj: _imgObj, isLook: false, scriptStr: scriptStr }); 

        //return juicer($("#tmplate_control_image").html(), { columnItem: columnItem, value: value });
        //return "<input id=\"CONTROL_" + columnItem.name + "\" type='hidden'  class='form-control image_url " + (columnItem.isNeed == 1 ? "IsNeedInput" : "") + "' placeholder='" + columnItem.text + "' data-form='" + columnItem.name + "' value='" + value + "' /><ul class='file-list'><li> <img src='" + value + "' sthle='width:55px;height:55px' id='image_url'></li></ul> <span onclick=\"upImage('image_url', 1, 'yz_animals');\" class='img_add_btn_box'><span class='img_add_btn_span'>+</span></span>";
    }
    this.view = function () {
        var value = "";
        if (dataJson)
            value = eval("dataJson." + columnItem.name);
        if (value != null && value.length > 0) {
            value = value.replace("@*@", "");
        } 
        var scriptStr = "";
        var _imgObj = { url: value, index: 0, imgfileName: "", IsShow: false };
        return juicer($("#upload_single_ImgTemplate").html(), { columnItem: columnItem, ImgObj: _imgObj, isLook: true, scriptStr: scriptStr }); 
        //return juicer($("#tmplate_control_image").html(), { columnItem: columnItem, value: value }); 
        //return "<input id=\"CONTROL_" + columnItem.name + "\" type='hidden'  class='form-control image_url " + (columnItem.isNeed == 1 ? "IsNeedInput" : "") + "' placeholder='" + columnItem.text + "' data-form='" + columnItem.name + "' value='" + value + "' /><ul class='file-list'><li> <img src='" + value + "' sthle='width:55px;height:55px' id='image_url'></li></ul> <span onclick=\"upImage('image_url', 1, 'yz_animals');\" class='img_add_btn_box'><span class='img_add_btn_span'>+</span></span>";
    }
}

//多图
var ImageListView = function (dataJson, columnItem, childDataTables) {
    // 继承
    ControlView.apply(this, [dataJson, columnItem, childDataTables]);

    this.edit = function () {
        var value = "";
        if (dataJson)
            value = eval("dataJson." + columnItem.name);

        if (value == null)
            value = "";

        if (value.indexOf(',') < 0 && value.indexOf('@*@')<0) {
            value = value.replace(/http:/g, "@*@http:");
            if (value.indexOf('@*@')==0) {
                value = value.replace("@*@", "");
            }
           
        }
        value= value.replace(/,/g, "@*@");
        var urlStrs = value.split("@*@");

        //var NeedLiHtmlStr = "";//"<li><img src='" + urlStr + "' sthle='width:55px;height:55px'> <span class='del'></span></li>";
        var flies = new Array();
        for (var i = 0; i < urlStrs.length; i++) {
            if (urlStrs[i] != "") {
                flies[i] = urlStrs[i].split('/')[urlStrs[i].split('/').length - 1];
            }
        }
        //return "<input id=\"CONTROL_" + columnItem.name + "\" type='hidden'  class='form-control image_url " + (columnItem.isNeed == 1 ? "IsNeedInput" : "") + "' placeholder='" + columnItem.text + "' data-form='" + columnItem.name + "' value='" + value + "' />  <ul class='file-list'>" + NeedLiHtmlStr + "<li id='file_tag_li' style='display:none;'></li></ul><span onclick=\"window.CurrentUploadId='" + columnItem.name+"';upImage('', 10, 'yz_animals','UploadFileBackFun');\" class='img_add_btn_box'><span class='img_add_btn_span'>+</span></span>";
        //return juicer($("#tmplate_control_edit_imageList").html(), { columnItem: columnItem, urlStrs: urlStrs });
        var scriptStr = '<script type="text/javascript" src="/js/uploader.js"></script >';
        return juicer($("#upload_multi_ImgTemplate").html(), { columnItem: columnItem, urlStrs: urlStrs, flieName: flies, IsShow: true, scriptStr: scriptStr });

    }
    this.view = function () {
        var value = "";
        if (dataJson)
            value = eval("dataJson." + columnItem.name);

        if (value == null)
            value = "";

        var urlStrs = value.split("@*@");
        var flies = new Array();
        for (var i = 0; i < urlStrs.length; i++) {
            if (urlStrs[i] != "") {
                flies[i] = urlStrs[i].split('/')[urlStrs[i].split('/').length - 1];
            }
        }
        //var NeedLiHtmlStr = "";//"<li><img src='" + urlStr + "' sthle='width:55px;height:55px'> <span class='del'></span></li>";

        //for (var i = 0; i < urlStrs.length; i++) {
        //    if (urlStrs[i] != "") {
        //        NeedLiHtmlStr += "<li><img src='" + urlStrs[i] + "' sthle='width:55px;height:55px'> <span class='del' onclick='DelFile(this)'></span></li>";
        //    }
        //}
        //return "<input id=\"CONTROL_" + columnItem.name + "\" type='hidden'  class='form-control image_url " + (columnItem.isNeed == 1 ? "IsNeedInput" : "") + "' placeholder='" + columnItem.text + "' data-form='" + columnItem.name + "' value='" + value + "' />  <ul class='file-list'>" + NeedLiHtmlStr + "<li id='file_tag_li' style='display:none;'></li></ul><span onclick=\"upImage('image_url', 10, 'yz_animals','UploadFileBackFun');\" class='img_add_btn_box'><span class='img_add_btn_span'>+</span></span>";
        //return juicer($("#tmplate_control_imageList").html(), { columnItem: columnItem, urlStrs: urlStrs });
        var scriptStr = '';
        return juicer($("#upload_multi_ImgTemplate").html(), { columnItem: columnItem, urlStrs: urlStrs, flieName: flies, IsShow: false, scriptStr: scriptStr });
    }
}



/**
 * 百度编辑器
 */
var BaiduEditorView = function (dataJson, columnItem, childDataTables) {

    // 继承
    ControlView.apply(this, [dataJson, columnItem, childDataTables]);
    // 
    this.edit = function () {
        var value = "";
        if (dataJson)
            value = eval("dataJson." + columnItem.name);

        if (value != '' && value != null) {
            //给图片加域名
            var imgReg = /<img.*?(?:>|\/>)/gi;
            //匹配src属性
            var srcReg = /src=[\'\"]?([^\'\"]*)[\'\"]?/i;
            var arr = value.match(imgReg);
            if (arr != null) {
                for (var i = 0; i < arr.length; i++) {
                    var src = arr[i].match(srcReg);
                    //if (src[1]) {
                        //value = value.replace(src[1], SERP_UPLOAD_CONFIG.UPLOAD_SERVER + src[1]);
                    //}
                }
            }

        }
        
        var scriptStr = "<script>eval(CONTROL_" + columnItem.name + " = UE.getEditor('CONTROL_" + columnItem.name + "',{initialFrameHeight:300,scaleEnabled:true}))</script>";
        return juicer($("#tmplate_control_baiduEditor").html(), { columnItem: columnItem, value: value, scriptStr: scriptStr });        
        //return "<textarea id='CONTROL_" + columnItem.name + "' " + (columnItem.isNeed == 1 ? "IsNeedInput" : "") + "' data-form='" + columnItem.name + "'>" + (value == null ? "" : value) + "</textarea>  <script> eval(CONTROL_" + columnItem.name + " = UE.getEditor('CONTROL_" + columnItem.name + "',{initialFrameHeight:300,scaleEnabled:true}))  </script>";
    }
    this.view = function () {
        var value = "";
        if (dataJson)
            value = eval("dataJson." + columnItem.name);

        var scriptStr = "<script>eval(CONTROL_" + columnItem.name + " = UE.getEditor('CONTROL_" + columnItem.name + "',{initialFrameHeight:300,scaleEnabled:true}))</script>";
        return juicer($("#tmplate_control_baiduEditor").html(), { columnItem: columnItem, value: value, scriptStr: scriptStr });        
        //return "<textarea id='CONTROL_" + columnItem.name + "' " + (columnItem.isNeed == 1 ? "IsNeedInput" : "") + "' data-form='" + columnItem.name + "'>" + (value == null ? "" : value) + "</textarea>  <script> eval(CONTROL_" + columnItem.name + " = UE.getEditor('CONTROL_" + columnItem.name + "',{initialFrameHeight:300,scaleEnabled:true}))  </script>";
    }
}


/**
  * 区域组件显示
  */
var RegionView = function (dataJson, columnItem, childDataTables) {

    // 继承
    ControlView.apply(this, [dataJson, columnItem, childDataTables]);

    this.edit = function () {
        var value = "";
        if (dataJson)
            value = eval("dataJson." + columnItem.name);

        var scriptStr = "<script src='/Plugins/area/areajson.js'></script><script src= '/Plugins/area/area.js' ></script><Script>$(document).on('click', '#CONTROL_" + columnItem.name + "', function () { $(this).areaPick(); }) " + (value == "" ? "" : " \r\n $(document).ready(function () {  $('#CONTROL_" + columnItem.name + "').areaPickInit({ lastcode: " + value + " }) })") + "</Script>";
        return juicer($("#tmplate_control_region").html(), { columnItem: columnItem, value: value, scriptStr: scriptStr });        
        //return "<input id='CONTROL_" + columnItem.name + "' type='text' class='form-control " + (columnItem.isNeed == 1 ? "IsNeedInput" : "") + "' placeholder='" + columnItem.text + "' data-form='" + columnItem.name + "' value='" + (value == null ? "" : value) + "'  readonly='readonly' />      <link href='/Plugins/area/css/area.css' rel='stylesheet' /><script src='/Plugins/area/areajson.js'></script> <script src='/Plugins/area/area.js'></script> <Script> $(document).on('click', '#CONTROL_" + columnItem.name + "', function () { $(this).areaPick(); }) " + (value == "" ? "" : " \r\n $(document).ready(function () {  $('#CONTROL_" + columnItem.name + "').areaPickInit({ lastcode: " + value + " }) })") + " </Script>";
    }
    this.view = function () {

        var value = "";
        if (dataJson)
            value = eval("dataJson." + columnItem.name);

        var scriptStr = "<script src='/Plugins/area/areajson.js'></script><script src= '/Plugins/area/area.js' ></script><Script>$(document).on('click', '#CONTROL_" + columnItem.name + "', function () { $(this).areaPick(); }) " + (value == "" ? "" : " \r\n $(document).ready(function () {  $('#CONTROL_" + columnItem.name + "').areaPickInit({ lastcode: " + value + " }) })") + "</Script>";
        return juicer($("#tmplate_control_region").html(), { columnItem: columnItem, value: value, scriptStr: scriptStr });
        //return "<input id='CONTROL_" + columnItem.name + "' type='text' class='form-control " + (columnItem.isNeed == 1 ? "IsNeedInput" : "") + "' placeholder='" + columnItem.text + "' data-form='" + columnItem.name + "' value='" + (value == null ? "" : value) + "'  readonly='readonly' />      <link href='/Plugins/area/css/area.css' rel='stylesheet' /><script src='/Plugins/area/areajson.js'></script> <script src='/Plugins/area/area.js'></script> <Script> $(document).on('click', '#CONTROL_" + columnItem.name + "', function () { $(this).areaPick(); }) " + (value == "" ? "" : " \r\n $(document).ready(function () {  $('#CONTROL_" + columnItem.name + "').areaPickInit({ lastcode: " + value + " }) })") + " </Script>";
    }
}