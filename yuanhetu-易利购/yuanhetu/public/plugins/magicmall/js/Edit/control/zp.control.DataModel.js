var DataModelWx = {

    PageNavigate: [
        {
            DisplayText: "促销活动",
            NavigateUrl: "#"
        },
        {
            DisplayText: "分类主页",
            NavigateUrl: "/Views/Product/ProductClassify.html"
        },
        {
            DisplayText: "全部商品",
            NavigateUrl: "/Views/Product/ProductList.html"
        }
        ,
        {
            DisplayText: "营销应用",
            NavigateUrl: "#"
        }
          ,
        {
            DisplayText: "商城首页",
            NavigateUrl: "/Views/Home/Index.html"
        }
          ,
        {
            DisplayText: "购物车",
            NavigateUrl: "/Views/ShopCart/Index.html"
        }
         ,
        {
            DisplayText: "个人中心",
            NavigateUrl: "/Views/Member/MyCenter.html"
        }
          ,
        {
            DisplayText: "订单中心",
            NavigateUrl: "/Views/Order/OrderList.html"
        }
    ],
    Goods: [],
    TeamBuyingGoods: [],
    SecondKillGoods: [],
    DIYPages: []
}

var DataModelWxAPP = {

    PageNavigate: [
        {
            DisplayText: "促销活动",
            NavigateUrl: "#"
        },
        {
            DisplayText: "分类主页",
            NavigateUrl: "/pages/category/category"
        },
        {
            DisplayText: "全部商品",
            NavigateUrl: "/pages/goodsList/goodsList"
        }
        ,
        {
            DisplayText: "营销应用",
            NavigateUrl: "#"
        }
          ,
        {
            DisplayText: "商城首页",
            NavigateUrl: "/pages/customIndex/index"
        }
          ,
        {
            DisplayText: "购物车",
            NavigateUrl: "/pages/cart/cart"
        }
         ,
        {
            DisplayText: "个人中心",
            NavigateUrl: "/pages/my/my"
        }
          ,
        {
            DisplayText: "订单中心",
            NavigateUrl: "/pages/myorders/myorders"
        }
    ],
    Goods: [],
    TeamBuyingGoods: [],
    SecondKillGoods: [],
    DIYPages: []
}

var DataModelPC = {

    PageNavigate: [
        {
            DisplayText: "促销活动",
            NavigateUrl: "#"
        },
        {
            DisplayText: "分类主页",
            NavigateUrl: "/views/product/ProductClassify.html"
        },
        {
            DisplayText: "全部商品",
            NavigateUrl: "/views/product/ProductList.html"
        }
        ,
        {
            DisplayText: "营销应用",
            NavigateUrl: "#"
        }
          ,
        {
            DisplayText: "商城首页",
            NavigateUrl: "/Views/Home/Index.html"
        }
          ,
        {
            DisplayText: "购物车",
            NavigateUrl: "/views/shopcart/index.html"
        }
         ,
        {
            DisplayText: "个人中心",
            NavigateUrl: "/views/member/mycenter.html"
        }
          ,
        {
            DisplayText: "订单中心",
            NavigateUrl: "/views/order/orderList.html"
        }
    ],
    Goods: [],
    TeamBuyingGoods: [],
    SecondKillGoods: [],
    DIYPages: []
}

var DataModelH5 = {

    PageNavigate: [
        {
            DisplayText: "促销活动",
            NavigateUrl: "#"
        },
        {
            DisplayText: "分类主页",
            NavigateUrl: "/views/product/ProductClassify.html"
        },
        {
            DisplayText: "全部商品",
            NavigateUrl: "/views/product/ProductList.html"
        }
        ,
        {
            DisplayText: "营销应用",
            NavigateUrl: "#"
        }
          ,
        {
            DisplayText: "商城首页",
            NavigateUrl: "/Views/Home/Index.html"
        }
          ,
        {
            DisplayText: "购物车",
            NavigateUrl: "/views/shopcart/index.html"
        }
         ,
        {
            DisplayText: "个人中心",
            NavigateUrl: "/views/member/mycenter.html"
        }
          ,
        {
            DisplayText: "订单中心",
            NavigateUrl: "/views/order/orderList.html"
        }
    ],
    Goods: [],
    TeamBuyingGoods: [],
    SecondKillGoods: [],
    DIYPages: []
}

function GetDataModel(platform) {
    var CurrentModel = null;
    if (platform == 'Wx') {

        CurrentModel = DataModelWx;
    } else if (platform == 'APP') {
        CurrentModel = DataModelWx;
       

    } else if (platform == 'WxAPP') {
        CurrentModel = DataModelWxAPP;
    }
    else if (platform == 'H5') {
        CurrentModel = DataModelH5;
    }
    else if (platform == 'PC') {
        CurrentModel = DataModelPC;
    }


    $.each(CurrentModel.PageNavigate, function (i, itemInfo) {
            if (itemInfo.NavigateUrl == "/Views/Home/Index.html") {
                itemInfo.NavigateUrl = "/Views/Home/Index.html?Platform=" + platform + "&ObjectType=" + objectType + "&ObjectID=" + objectID + "&CustID=" + CustID + "&shopID=" + objectID;
            }
    });



    return CurrentModel;
}