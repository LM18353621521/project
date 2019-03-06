var baidu_Map;
var baidu_MapOverlay;
var baidu_marker;
var baidu_Point = { lng: 0, lat: 0 };
var inputAddrID = ""; //地址文本
var baiduMapID = ""; //渲染地图元素ID
var ipt_lngID = ""; ipt_latID = ""; // lng经度 lat纬度
var default_Vale = ""; //默认地址

//创建和初始化地图函数：
function initMap(bJson) {
    baidu_Point.lng = bJson.lng;
    baidu_Point.lat = bJson.lat;
    inputAddrID = bJson.inputAddrID;
    baiduMapID = bJson.baiduMapID;
    ipt_lngID = bJson.ipt_lngID;
    ipt_latID = bJson.ipt_latID;
    default_Vale = bJson.default_Vale;
    if (bJson.isDefault_V_Show == 0 || (!bJson.isDefault_V_Show)) {
        $("#" + ipt_lngID).val(bJson.lng); $("#" + ipt_latID).val(bJson.lat);
        $("#" + bJson.default_Vale_ID).val(bJson.default_Vale);
    }
    createMap();//创建地图
    AddAc(bJson.default_Vale); //智能搜索
    setMapEvent();//设置地图事件
    addMapControl();//向地图添加控件
}
function createMap() {
    baidu_Map = new BMap.Map(baiduMapID);
    var point = new BMap.Point(baidu_Point.lng, baidu_Point.lat);
    baidu_Map.centerAndZoom(point, 20);
    baidu_marker = new BMap.Marker(point);
    baidu_Map.addOverlay(baidu_marker);
    baidu_marker.enableDragging();
    MarKerEve(baidu_marker);
    //地图添加单击事件
    baidu_Map.addEventListener("click", function (e) {
        addMarker(e.point);
    })
    //地图移动事件
    baidu_Map.addEventListener("dragend", function (e) {
        addMarker(e.point);
    });

    baidu_marker.openInfoWindow(CreateInfoWindow(default_Vale));

}

function setMapEvent() {
    baidu_Map.enableScrollWheelZoom();
    baidu_Map.enableKeyboard();
    baidu_Map.enableDoubleClickZoom()
}
//向地图添加控件
function addMapControl() {
    var scaleControl = new BMap.ScaleControl({ anchor: BMAP_ANCHOR_BOTTOM_LEFT });
    scaleControl.setUnit(BMAP_UNIT_METRIC);
    baidu_Map.addControl(scaleControl);
    var navControl = new BMap.NavigationControl({ anchor: BMAP_ANCHOR_TOP_LEFT, type: 0 });
    baidu_Map.addControl(navControl);
    var overviewControl = new BMap.OverviewMapControl({ anchor: BMAP_ANCHOR_BOTTOM_RIGHT, isOpen: false });
    baidu_Map.addControl(overviewControl);
}
// 编写自定义函数,创建标注
function addMarker(point) {
    Remove_Overlay();
    var marker = new BMap.Marker(point);
    baidu_Map.addOverlay(marker);
    marker.enableDragging();
    MarKerEve(marker);
    var point2 = marker.getPosition();
    $("#" + ipt_lngID).val(point2.lng); $("#" + ipt_latID).val(point2.lat);
    //获取地址
    GetAddres(point, marker);
}


//标注移动事件
function MarKerEve(target) {
    target.addEventListener("dragend", function (e) {
        addMarker(e.point);
    });
}


//根据城市获取地图地址
function SearchAddres(addrName) {

    // 创建地址解析器实例
    var myGeo = new BMap.Geocoder();
    // 将地址解析结果显示在地图上,并调整地图视野
    myGeo.getPoint(addrName, function (point) {
        if (point) {
            baidu_Map.centerAndZoom(point, 18);
            addMarker(point);
        } else {
            alert("无法找到该地区");
        }
    }, "全国");
}

//根据经度纬度获取地址
function GetAddres(point, target) {
    var geoc = new BMap.Geocoder();
    geoc.getLocation(point, function (rs) {
        var addComp = rs.addressComponents;
        var addrVale = addComp.province + " " + addComp.city + " " + addComp.district + " " + addComp.street + " " + addComp.streetNumber;
        $("#" + inputAddrID).val(addrVale);
        target.openInfoWindow(CreateInfoWindow(addrVale));
    });
}

//清除覆盖物
function Remove_Overlay() {
    baidu_Map.clearOverlays();
}



function AddAc(val) {

    var ac = new BMap.Autocomplete(    //建立一个自动完成的对象
        {
            "input": inputAddrID, "location": baidu_Map
        });


    function G(id) {
        return document.getElementById(inputAddrID);
    }

    ac.setInputValue(val);
    ac.addEventListener("onhighlight", function (e) {  //鼠标放在下拉列表上的事件
        var str = "";
        var _value = e.fromitem.value;
        var value = "";
        if (e.fromitem.index > -1) {
            value = _value.province + _value.city + _value.district + _value.street + _value.business;
        }
        str = "FromItem<br />index = " + e.fromitem.index + "<br />value = " + value;

        value = "";
        if (e.toitem.index > -1) {
            _value = e.toitem.value;
            value = _value.province + _value.city + _value.district + _value.street + _value.business;
        }
        str += "<br />ToItem<br />index = " + e.toitem.index + "<br />value = " + value;
        G("searchResultPanel").innerHTML = str;
    });

    

    var myValue;
    ac.addEventListener("onconfirm", function (e) {    //鼠标点击下拉列表后的事件
        var _value = e.item.value;
        myValue = _value.province + _value.city + _value.district + _value.street + _value.business;
        G("searchResultPanel").innerHTML = "onconfirm<br />index = " + e.item.index + "<br />myValue = " + myValue;
        setPlace();
    });

    function setPlace() {
        baidu_Map.clearOverlays();    //清除地图上所有覆盖物
        function myFun() {
            var pp = local.getResults().getPoi(0).point;    //获取第一个智能搜索的结果
            baidu_Map.centerAndZoom(pp, 18);
            addMarker(pp);
        }
        var local = new BMap.LocalSearch(baidu_Map, { //智能搜索
            onSearchComplete: myFun
        });
        local.search(myValue);
    }
}
//创建窗口信息

function CreateInfoWindow(addresName) {
    var opts = {
        width: 200,     // 信息窗口宽度
        //height: 100,     // 信息窗口高度
        title: "当前位置", // 信息窗口标题
        enableMessage: true,//设置允许信息窗发送短息
    }
    var infoWindow = new BMap.InfoWindow(addresName, opts);  // 创建信息窗口对象 
    return infoWindow;
}