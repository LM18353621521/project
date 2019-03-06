/*
 * 封装上传
 * 依赖 webuploader.js
 */
var obj = { ImageMaxSize: 3145728, imageSize: 0, customerid: CustID, userid: UserID };
$(function () {
    $("div.upload-file").each(function (i, _this) {
        var server = "";
        if (obj.ImageMaxSize === undefined)
            obj.ImageMaxSize = "3145728";   //图片最大字节
        if (obj.imageSize === undefined)
            obj.imageSize = 20;
        if (server === undefined || server === "") {
            server = "http://img4test.winmobi.cn/Baidu/Upload";
        }
        var $this = $(_this);
        var _pick = $this.data("target"), _imageType = $this.data('imagetype'), _multiple = $this.data('multiple') == 1, $list = $($this.data("target")).prev(".uploader-list"), _paste = $this.parents('div.form-group').get(0);
        var isWeixin = $this.data('isweixin');
        if (isWeixin === undefined || isWeixin === "") {
            isWeixin = 0;
        }
        var uploader = WebUploader.create({
            auto: true,
            paste: _paste,//粘贴图片功能
            server: server,
            formData: {
                imageType: _imageType,
                imageSize: obj.imageSize,
                customerId: obj.customerid,
                userId: obj.userid,
                isWeixin: isWeixin
            },
            pick: _pick,
            resize: false,
            multiple: _multiple,
            accept: {
                title: '选择图片上传',
                extensions: 'gif,jpg,jpeg,bmp,png',
                mimeTypes: 'image/gif,image/jpg,image/jpeg,image/bmp,image/png'
            },
            fileSingleSizeLimit: obj.ImageMaxSize,
            //withCredentials: true,//跨域传递cookies
            compress: false
        });

        //当文件被加入队列之前触发
        uploader.on('beforeFileQueued', function (file) {
            debugger;
            if (file.size > obj.ImageMaxSize) {
                var size = Math.round(obj.ImageMaxSize/1024,2);
                $.wmessage("单张图片不能超过【" + size + "】KB", 2);
                return false;
            }
            if (file.ext != "gif" && file.ext != "jpg" && file.ext != "jpeg" && file.ext != "bmp" &&  file.ext != "png") {
                $.wmessage("上传文件格式不正确", 2);
                return false;
            }
            return true;
        });

        // 当有文件添加进来的时候
        uploader.on('fileQueued', function (file) {
            //单文件上传应该删除以前上传的内容
            if (!_multiple) {
                $list.parent('.upload-file').prev('ul.upload-image').empty();
                $list.find('div:first').remove();
            }
            var $li = $('<div id="' + file.id + '" class="file-item pull-left"><img></div>'), $img = $li.find('img');
            $list.append($li);
            uploader.makeThumb(file, function (error, src) {
                if (error) {
                    $img.replaceWith('<span>不能预览</span>');
                    return;
                }
                $img.attr('src', src);
            }, 110, 110);
        });

        // 文件上传过程中创建进度条实时显示。
        uploader.on('uploadProgress', function (file, percentage) {
            var $li = $('#' + file.id),
                $percent = $li.find('.progress div');
            // 避免重复创建
            if (!$percent.length) {
                $percent = $('<div class="image-delete"><span class="delete" onclick="$.webupremove(this);">&times;</span></div><div class="progress"><div class="progress-bar progress-bar-warning progress-bar-striped active" role="progressbar"></div></div>').appendTo($li).find('div.progress-bar');
            }
            $percent.css('width', percentage * 100 + '%').text(percentage * 100 + '%');
        });
        //文件上传成功
        uploader.on('uploadSuccess', function (file, response) {
            var $li = $('#' + file.id);
            $li.find('.progress-bar').css('width', '100%').text('上传成功').removeClass('progress-bar-warning').addClass('progress-bar-success');
            $li.addClass('upload-state-done').append('<input id="CONTROL_' + $this.data("name") + '" type="hidden" name="' + $this.data("name") + '"  data-form="' + $this.data("name") + '" value="' + response.dataUrl + '">');
            if (!_multiple) {
                $("#webupmbut .wupoadbtn").html("编辑上传图片");
            }
        });
        // 文件上传失败，显示上传出错。
        uploader.on('uploadError', function (file) {
            var $li = $('#' + file.id);
            $li.find('.progress-bar').css('width', '100%').text('上传失败').removeClass('progress-bar-warning').addClass('progress-bar-danger');
           
        });
    });
});

/*
*Id: 标签Id
*callback:成功回掉函数返回url
*callBackErr:错误回掉函数
*/
$.extend({
    uploader: function (Id, callback, callBackErr) {
        $("." + Id).click(function () {
            var evt = document.createEvent("MouseEvents");
            evt.initEvent("click", false, false);// 或用initMouseEvent()，不过需要更多参数 
            $(".webuploader-element-invisible").get(0).dispatchEvent(evt);
        });
        var path="";
        var $this = $(".upload-file");
        var _pick = $this.data("target"), _imageType = $this.data('imagetype'), _multiple = $this.data('multiple') == 1, _paste = $this.parents('div.form-group').get(0);
        var isWeixin = $this.data('isweixin');
        if (isWeixin === undefined || isWeixin === "") {
            isWeixin = 0;
        }
        var server = "http://img4test.winmobi.cn/Baidu/Upload";
        var uploader = WebUploader.create({
            auto: true,
            paste: _paste,//粘贴图片功能
            server: server,
            formData: {
                imageType: _imageType,
                imageSize: obj.imageSize,
                customerid: obj.customerid,
                userid: obj.userid,
                isWeixin: isWeixin
            },
            pick: _pick,
            resize: false,
            multiple: _multiple,
            accept: {
                title: '选择图片上传',
                extensions: 'gif,jpg,jpeg,bmp,png',
                mimeTypes: 'image/gif,image/jpg,image/jpeg,image/bmp,image/png'
            },
            fileSingleSizeLimit: obj.ImageMaxSize,
            //withCredentials: true,//跨域传递cookies
            compress: false
        });
        //文件上传成功
        uploader.on('uploadSuccess', function (file, response) {
            if (Id !== null && Id !== undefined && Id !== "")
                $("#" + Id).attr("src",UPLOAD_SERVER+response.dataUrl);
            if (typeof (eval(callback)) === "function")
                callback(response.dataUrl);
            path = response.dataUrl;
        });
        // 文件上传失败，显示上传出错。
        uploader.on('uploadError', function (file) {
            if (typeof (eval(callBackErr)) === "function")
                callBackErr(file);
        });
        return path;
    }
});

$.extend({
    /*删除上传照片*/
    webupremove: function (_this) {
        $.wconfirm('确定删除此图片？', function () {
            $(_this).parents('div.file-item').remove();
            $('body > div.tooltip.fade.top.in').remove();
            if ($(".uploader-list").html().trim() == "") {
                $("#webupmbut .wupoadbtn").html("点击上传图片");
            }
        });
    }
});
    
