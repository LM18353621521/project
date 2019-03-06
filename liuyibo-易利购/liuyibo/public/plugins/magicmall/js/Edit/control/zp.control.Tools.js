
//魔幻商城控件库
var ZpControls = ['Base', 'Search', 'Carousel'];//, 'Blank', 'BottomMenu', 'Kitchen', 'List', 'Partingline', 'PicNavigate', 'RichEdit'
var ErrMessager = null;
var SuccessMessage = null;
(function ($zp) {

    $zp.extend(
        {
            ShowErrMessage: function (message) {
                if (ErrMessager == null) {
                    ErrMessager = new $.zui.Messager('', {
                        type: 'danger', // 定义颜色主题
                        close: false,
                        time: 1500
                    });
                }
                ErrMessager.show(message);

            },
            ShowSuccessMessage: function (message,callback) {
                if (SuccessMessage == null) {
                    SuccessMessage = new $.zui.Messager('', {
                        type: 'success', // 定义颜色主题
                        close: false,
                        time: 1500
                    });
                }
                if (callback)
                {
                    SuccessMessage.show(message, callback);
                } else {
                    SuccessMessage.show(message);
                }
              
            },
            TestInput: function (str) {
                var regEn = /[`~!@#$%^&*()_+<>?:"{},.\/;'[\]]/im,
                 regCn = /[·！#￥（——）：；“”‘、，|《。》？、【】[\]]/im;

                if (regEn.test(str) || regCn.test(str)) {
                    alert("名称不能包含特殊字符.");
                    return false;
                }
            },
            TestUrl: function (str) {
                if (str.indexOf("http") > -1 || str == "") {

                    return true;

                }
                return false;
            },
            LoadControls: function (callback) {
                $.each(ZpControls, function (i, control) {
                    $.getScript($zp.ControlsPath + control + "/" + control + "Control.js");
                    $.getScript($zp.ControlsPath + control + "/" + control + "Item.js");
                });
                $.getScript($zp.ControlsPath + "/Page.js", callback);

            },
            ControlsPath: '/js/control/',
            JuicerHtml: function (template, model) {
                return juicer($("#" + template).html(), { Model: model });
            },
            ForEach: function (items, id) {
                var item = null;
                for (var element in items) {
                    if (items[element].ID == id) {
                        item = items[element];
                    }
                }

                return item;
            }, Encode: function (str) {
                return base64encode(utf16to8(str));
            }, Decode: function (str) {
                return utf8to16(base64decode(str));
            },
            CreateID: function () {
                var fmt = "";
                var d = new Date();
                var o = {
                    "M+": d.getMonth() + 1, //月份 
                    "d+": d.getDate(), //日 
                    "h+": d.getHours(), //小时 
                    "m+": d.getMinutes(), //分 
                    "s+": d.getSeconds(), //秒 
                    "q+": Math.floor((d.getMonth() + 3) / 3), //季度 
                    "S": d.getMilliseconds() //毫秒 
                };
                if (/(y+)/.test(fmt)) fmt = fmt.replace(RegExp.$1, (d.getFullYear() + "").substr(4 - RegExp.$1.length));
                for (var k in o)
                    if (new RegExp("(" + k + ")").test(fmt)) fmt = fmt.replace(RegExp.$1, (RegExp.$1.length == 1) ? (o[k]) : (("00" + o[k]).substr(("" + o[k]).length)));
                fmt += Math.random(1000, 9999);
                return fmt;
            }, BuildPage: function (pageJson) {

                var page = new Page();
                page.Item = new Array();
                if (pageJson != undefined && pageJson != null && pageJson != "") {
                    pageJson = JSON.parse(pageJson);
                    page.Title = pageJson.Title;
                    page.Remark = pageJson.Remark;
                    page.Logo = pageJson.Logo;
                    page.Url = pageJson.Url;
                    $.each(pageJson.Item, function (index, element) {
                        var obj = eval(element.Type + "JsonToObj(element)");
                        page.Item.push(obj);
                    });
                }
                return page;

            },
            UploadingControlItem: {
                UploadingControlItem: null,
                UploadingControl: null,
                li: null

            },
            UpFileReady: function () {
                var $ele = $(".upload-file"), imagetype = $ele.data('imagetype'), multiple = $ele.data('multiple') == 1, uploader;
                var loadPictureDomain = imageDomain;
                var postUrl = loadPictureDomain + '/index.php/Admin/Ueditor.Ueditor/imageUp/savepath/activity/pictitle/banner/dir/images';
                uploader = WebUploader.create({
                    auto: true,
                    duplicate:true,//允许上传名字相同的图片
                    paste: $ele.parents('div.form-group').get(0),//粘贴图片功能
                    server: postUrl,
                    formData: {
                        imageType: imagetype,
                        imageSize: 0,
                        customerid: CustID,
                        userid: 0,
                        isWeixin: 0

                    },
                    disableGlobalDnd: true,

                    chunked: true,
                    extensions: 'gif,jpg,jpeg,bmp,png',
                    mimeTypes: 'image/*',
                    pick: $ele.data("target"),
                    resize: false,
                    multiple: multiple,
                    accept: {
                        title: '选择图片上传',
                        extensions: 'gif,jpg,jpeg,bmp,png',
                        mimeTypes: 'image/gif,image/jpg,image/jpeg,image/bmp,image/png'
                    },
                    fileSingleSizeLimit: 5 * 1024 * 1024
                    //withCredentials: true,//跨域传递cookies

                });

                uploader.on('uploadStart', function (file) {
                    var $li = $.UploadingControlItem.li;
                    $li.find(".uploading").css("display", "");
                    $li.find(".image").css("display", "none");
                    $li.find(".addbtn").css("display", "none");
                });

                //文件上传成功
                uploader.on('uploadSuccess', function (file, data) {
                    var $li = $.UploadingControlItem.li;
                    $li.find(".uploading").css("display", "none");

                    $li.find(".image").find("img").attr("src", data.url);
                    $li.find(".image").css("display", "");
                    $(".webuploader-element-invisible").get(0).value = "";

                    //$('.webuploader-element-invisible').replaceWith('<input type="file" name="file" class="webuploader-element-invisible" multiple="multiple" accept="image/gif,image/jpg,image/jpeg,image/bmp,image/png">')

                    $.UploadingControlItem.UploadingControl.UploadFinish(data.url);

                });
                // 文件上传失败，显示上传出错。
                uploader.on('uploadError', function (file) {
                    var $li = $.UploadingControlItem.li;
                    $li.find(".uploading").css("display", "none");
                    $li.find(".image").css("display", "none");
                    $li.find(".addbtn").css("display", "");
                });

                uploader.on('error', function (code) {
                    if (code == "Q_TYPE_DENIED")
                    {
                        alert("只能上传图片类型的文件");
                    } else if (code == "F_DUPLICATE")
                    {
                        alert("不能上传同样的图片");
                    }
                   
                    
                 });


               
            }

        })

})(jQuery)


//function save()
//{
//    var page = page.html();
//}