
String.prototype.length = function(){ return this.match(/[^ -~]/g) == null ? this.length : this.length + this.match(/[^ -~]/g).length ; }
var format_check = {
	focus_obj:function(o){ scrollTo(0,0); },
	empty: function (p) {
		var o = p[0]; var min_len = p[1]; var max_len = p[2];
		var v = $.trim($(o).val()); 
		var msg = '';
		    var ret = true;
		    min_len = parseInt(min_len); max_len = parseInt(max_len);
		    if (v == '') { msg = '不能为空'; }
		    else if (min_len && v.length < min_len) { msg = '不能少于' + min_len + '个字'; }
		    else if (max_len && v.length > max_len) { msg = '不能多于' + max_len + '个字'; }
		    if (msg != '') {
		        seterr(o, msg);
		   
		        return false;
		    }
		   
		return true;
	},
	select: function (p) {
	    var o = p[0]; var NotValue = p[1];//不能选这个值
	    var v = $.trim($(o).val());	   
	    var msg = '';
	    if (o.style.display=="")
	    {
	        if (v == NotValue && o.length>1)
	        { msg = "必须选择"; }
	        if (msg != '') {
	            seterr(o, msg);
	            return false;
	        }
	    }
	    return true;
	},
	datetime: function (p) {
	    var o = p[0];
	    var v = $.trim($(o).val());
	    if (v == '') return true;
	    var msg = '';
	    if (!/^((\d{4})|(\d{2}))[\-\.\/](\d{1,2})[\-\.\/](\d{1,2})/.test(v))
	    { msg = '时间格式不正确'; }
	    if (msg != '') {
	        seterr(o, msg);
	        return false;
	    }
	    return true;
	},
    setpassword:function(p)//设置密码
    {
     var o = p[0]; 
     var obj=p[1];
     var v = $.trim($(o).val());
     var msg = '';
	 var ret = true; 
     var v2=document.getElementById(obj).value;

     if(v2!=v)
     {
     msg="两次密码输入不一致!";
     }

     if(msg!=''){
			seterr(o,msg);
			return false;
		}
		return true;
    },
	allowempty:function(p){
		var o = p[0]; var min_len = p[1]; var max_len = p[2];
		var v = $.trim($(o).val());
		var msg = '';
		var ret = true; 
		min_len = parseInt(min_len); max_len = parseInt(max_len);
		if(v==''){ return true;}
		else if(min_len && v.length<min_len){ msg = '不能少于'+min_len+'个字'; }
		else if(max_len && v.length>max_len){ msg = '不能多于'+max_len+'个字'; }
		if(msg!=''){
			seterr(o,msg);
			return false;
		}
		return true;
	},
	integer:function(p){
		var o = p[0]; var min_v = parseInt(p[1]); var max_v = parseInt(p[2]);
		var v = $.trim($(o).val());
		if(v=='') return true;
		var msg = '';
		if(!/^[0-9]+$/.test(v)){ msg = '只能填写数字(整数)'; }
		else if(min_v && parseInt(v)<min_v){ msg = '数值不能小于 `'+min_v+'`'; }
		else if(max_v && parseInt(v)>max_v){ msg = '数值不能大于 `'+max_v+'`'; }
		if(msg!=''){
			seterr(o,msg);
			return false;
		}
		return true;
	},
	number:function(p){
		var o = p[0]; var min_v = parseInt(p[1]); var max_v = parseInt(p[2]);
		var v = $.trim($(o).val());
		if(v=='') return true;
		var msg = '';
		if(!/^[0-9]*(\.)?[0-9]*$/.test(v)){ msg = '该项只能填写数字'; }
		else if(min_v && parseInt(v)<min_v){ msg = '该项数值不能小于 `'+min_v+'`'; }
		else if(max_v && parseInt(v)>max_v){ msg = '该项数值不能大于 `'+max_v+'`'; }
		if(msg!=''){
			seterr(o,msg);
			return false;
		}
		return true;
	},
	word_num:function(p){
		var o = p[0]; var min_v = parseInt(p[1]); var max_v = parseInt(p[2]);
		var v = $.trim($(o).val());
		if(v=='') return true;
		var msg = '';
		if(!/^(([A-Za-z]+[0-9]+)|([0-9]+[A-Za-z]+))[A-Za-z0-9]*$/.test(v)){ msg = '必须填写数字和字母'; }
		else if(min_v && parseInt(v)<min_v){ msg = '数值不能小于 `'+min_v+'`'; }
		else if(max_v && parseInt(v)>max_v){ msg = '数值不能大于 `'+max_v+'`'; }
		if(msg!=''){
			seterr(o,msg);
			return false;
		}
		return true;
	},
	email:function(p){
		var o = p[0]; var v = $.trim($(o).val());
		if(v=='') return true;
		if(!/^([a-z0-9]*[-_\.]?[a-z0-9]+)*@([a-z0-9]*[-_]?[a-z0-9]+)+[\.][a-z]{2,3}([\.][a-z]{2})?$/.test(v)){
			msg = '邮箱地址不正确';
			seterr(o,msg);
			return false;
		}
		return true;
	},
	mobile: function (p) {
	    var o = p[0]; var v = $.trim($(o).val());
	    if (v == '') return true;
	    if (!/^(\d){11,11}$/.test(v)) {
	        msg = '必须为11位数字';
	        seterr(o, msg);
	        return false;
	    }
	    return true;
	},
	email_mobile: function (p) {
	    var o = p[0]; var v = $.trim($(o).val());
	    if (v == '') return true;

	    if (/^(\d){11,11}$/.test(v) || /^([a-z0-9]*[-_\.]?[a-z0-9]+)*@([a-z0-9]*[-_]?[a-z0-9]+)+[\.][a-z]{2,3}([\.][a-z]{2})?$/.test(v)) {
	        return true;
	    }
	    else {
	        msg = '手机号码或邮箱格式不正确';
	        seterr(o, msg);
	        return false;
	    }

	},
	strip_tag:function(p){
		var msg ='';
		var o = p[0];
		var v = $.trim($(o).val());
		var reg=/(http|ftp|https):\/\/[\w]+(.[\w]+)([\w\-\.,@?^=%&:/~\+#]*[\w\-\@?^=%&/~\+#])/;
		var regs=/(http|ftp|https):/;
		if(reg.test(v)||regs.test(v)){
			msg = '禁止输入链接';
		}else{
			msg = '';
		}
		if(msg!=''){
			seterr(o,msg);
			return false;
		}
		return true;
	}
};

function check_input(this_) {
   
    var ret = true;
    if (this_.offsetHeight > 0) { //新增，必须显示才检测
        var format = $(this_).attr('format');
        if (format && format.indexOf('|')) {
            format = format.split('|');
            for (var i = 0; i < format.length; i++) {
                var p = format[i].split(',');
                var func = format_check[p[0]];
                if (format[i] != '' && func) {
                    p.shift(); p.unshift(this_);
                    ret = func(p);
                    if (!ret) { $(this_).addClass('txtInput_focus'); return false; }
                }
            }
        }
        else {
            if ($(this_).attr('format'))
                ret = format_check[$(this_).attr('format')](this_);
        }
    }

        return ret;
}

function check_num(str){

	if($.trim(str)==""){
		return false;
	}
	if(/^[0-9]+$/.test(str)){
		return true;
	}
	return false;
}

//初始化
$(function()
{
    var input_arr = $("[format]");

	$(input_arr).each
    ( 
    function()
    { 
        
     var info=$(this).attr("info");

      if(info==undefined)
      {
        info="_info_"+$(this).attr('id');
      }

    if ( $("#"+info).length == 0)
    {
        $(this.parentNode).after("<span onclick=\"javascript:this.style.display='none'\" id=\"" + info + "\"></span>");
        
     }


		$(this).blur(function()
		{
		    
			if(check_input(this))
			{

			    $(this).removeClass('txtInput_focus').addClass('txtInput');
			    

				$('#'+info).html('').removeClass('tips_error');
				if($(this).val()!='')
				    $('#' + info).html("<img src='/JS/Check/ok.png' />").addClass('tips_ok');
			}
			$("#" + info).show();
			//document.getElementById("_info_" + this.id).style.display = "";//新增

		});
    }); 
});

  //检查窗体
   function CheckForm()
   {
     return CheckStep("form1");
   }

     //检查某一步
   function CheckStep(obj) {
    
       this.IsFocus = true; 
       var form_ = $("#" + obj);
    
       var input_arr = $(form_).find('[format]');

                var ret = true;
              
                if (!ret) { return false; }
                $(input_arr).each(function () {
                    
                        ret = check_input(this);
                        if (!ret) return false;              
                });

                return ret;
        }

   
   var IsFocus = false;
        function seterr(obj,msg)
        {

            var info=$(obj).attr("info");

              if(info==undefined)
              {
                info="_info_"+$(obj).attr('id');
              }

              $('#' + info).html(msg).removeClass('tips_ok').addClass('tips_error');

              if (this.IsFocus)
              {
                  $(obj).focus();
                  this.IsFocus = false;
              }     

        }

