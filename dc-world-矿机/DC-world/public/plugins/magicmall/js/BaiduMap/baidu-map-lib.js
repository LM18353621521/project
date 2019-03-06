  //调用
/* var BaiDuMap = new initBaiDuMap({
          defaultCountry: "广州市", //默认给出显示地区
          point:{ lng: 113.270793, lat: 23.135308 },初始化经纬度
          map: "baidumap", //指定元素显示地址 ID 使用DIV元素
          autoSearch: false, //是否使用自动完成地址搜索 默认 默认选择文本ID baidu-auto-txt
          pointOut: function (p) { //返回地图的经度跟纬度JSON
              console.log(p);
          },
          addrOut: function (ad) { //返回地理位置
              //streetNumber: "114号", street: "连新路", district: "越秀区", city: "广州市", province: "广东省"
              console.log(ad);
          },
          openInfo: false //是否打开地图提示信息框 默认true
      });

      //自带方法 用于点击某个按钮触发该地理位置转换
      //如果不使用 实例方法可以不用 new
       BaiDuMap.autoComplete("湖南省怀化市");
  
  */
  
  // X 113.270793 Y 23.135308
function initBaiDuMap(options) {

    if (!options.hasOwnProperty("map"))
        options.map = "baidu-map";

    //
    if (!options.hasOwnProperty("defaultCountry"))
        options.defaultCountry = "广州市";

    //初始经度纬度
    if (!options.hasOwnProperty("point"))
        options.point = { lng: 113.270793, lat: 23.135308 };

    //openInfo
    if (!options.hasOwnProperty("openInfo"))
        options.openInfo = true;

    //自动完成 autoSearch
    if (!options.hasOwnProperty("autoSearch")) {
        options.autoSearch = "baidu-auto-txt";
    } else {
        if (!options.autoSearch || options.autoSearch == "" || options.autoSearch == null) {
            options.autoSearch = false;
        }
    }

    this.baidu_Map;
    this.autoComplete = function (addrName) {
        SearchAddres(addrName);

    }

    // 编写自定义函数,创建标注
    function addMarker(point) {
        Remove_Overlay();
        var marker = new BMap.Marker(point);
        this.baidu_Map.addOverlay(marker);
        marker.enableDragging();
        MarKerEve(marker);
        var point2 = marker.getPosition();
        options.pointOut({ lng: point2.lng, lat: point2.lat });
        GetAddres(point, marker);
    }

    //标注移动事件
    function MarKerEve(target) {
        target.addEventListener("dragend", function (e) {
            addMarker(e.point);
        });
    }

    //向地图添加控件
    function addMapControl() {
        var scaleControl = new BMap.ScaleControl({ anchor: BMAP_ANCHOR_BOTTOM_LEFT });
        scaleControl.setUnit(BMAP_UNIT_METRIC);
        this.baidu_Map.addControl(scaleControl);
        var navControl = new BMap.NavigationControl({ anchor: BMAP_ANCHOR_TOP_LEFT, type: 0 });
        this.baidu_Map.addControl(navControl);
        var overviewControl = new BMap.OverviewMapControl({ anchor: BMAP_ANCHOR_BOTTOM_RIGHT, isOpen: false });
        this.baidu_Map.addControl(overviewControl);
    }

    //清除覆盖物
    function Remove_Overlay() {
        this.baidu_Map.clearOverlays();
    }


    //根据城市获取地图地址
    function SearchAddres(addrName) {
        // 创建地址解析器实例
        var myGeo = new BMap.Geocoder();
        // 将地址解析结果显示在地图上,并调整地图视野
        myGeo.getPoint(addrName, function (point) {
            if (point) {
                this.baidu_Map.centerAndZoom(point, 16);
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
            var addrVale = addComp.province + addComp.city + addComp.district + addComp.street + addComp.streetNumber;
            options.addrOut(addComp);
            if (!options.openInfo) return;
            target.openInfoWindow(CreateInfoWindow(addrVale));
        });
    }

    function autoCompleteSS(val) {
        var ac = new BMap.Autocomplete(    //建立一个自动完成的对象
            {
                "input": options.autoSearch, "location": this.baidu_Map
            });
        function G(id) {
            return document.getElementById(options.autoSearch);
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
            this.baidu_Map.clearOverlays();    //清除地图上所有覆盖物
            function myFun() {
                var pp = local.getResults().getPoi(0).point;    //获取第一个智能搜索的结果
                this.baidu_Map.centerAndZoom(pp, 18);
                addMarker(pp);
            }
            var local = new BMap.LocalSearch(this.baidu_Map, { //智能搜索
                onSearchComplete: myFun
            });
            local.search(myValue);
        }
    }

    //创建窗口信息
    function CreateInfoWindow(addresName) {

        var opts = {
            width: 200,     // 信息窗口宽度
            title: "详细位置", // 信息窗口标题
            enableMessage: true,//设置允许信息窗发送短息
        }
        var infoWindow = new BMap.InfoWindow(addresName, opts);  // 创建信息窗口对象
        return infoWindow;
    }

    //创建地图
    function createMap() {
        this.baidu_Map = new BMap.Map(options.map);
        var point = new BMap.Point(options.point.lng, options.point.lat);
        this.baidu_Map.centerAndZoom(point, 18);
        var baidu_marker = new BMap.Marker(point);
        this.baidu_Map.addOverlay(baidu_marker);
        baidu_marker.enableDragging();
        MarKerEve(baidu_marker);

        this.baidu_Map.addEventListener("click", function (e) {
            addMarker(e.point);
        })
        //地图移动事件
        this.baidu_Map.addEventListener("dragend", function (e) {
            addMarker(e.point);
        });
        addMapControl();
        if (!options.autoSearch) {
            autoCompleteSS(options.defaultCountry);
        } else if(options.autoSearch!="") {
            autoCompleteSS(options.defaultCountry);
        }
        if (!options.openInfo) return;
        baidu_marker.openInfoWindow(CreateInfoWindow(options.defaultCountry));
    }
    createMap();
}