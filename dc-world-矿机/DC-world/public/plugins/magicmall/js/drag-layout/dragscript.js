function supportstorage() {
	if (typeof window.localStorage=='object') 
		return true;
	else
		return false;		
}
var layouthistory; 

function randomNumber() {
	return randomFromInterval(1, 1e6)
}
function randomFromInterval(e, t) {
	return Math.floor(Math.random() * (t - e + 1) + e)
}

function removeElm() {
	$(".demo").delegate(".js-del", "click", function(e) {
		e.preventDefault();
		$(this).parents(".box").remove();
		$("#config_"+$(this).parents(".box").attr("data-id")).remove();
	})
}

function cleanHtml(e) {
	$(e).parent().append($(e).children().html())
}
function downloadLayoutSrc() {
	var e = "";
	$("#download-layout").children().html($(".demo").html());
	
	var t = $("#download-layout").children();
	t.find(".diy-conitem-action-btns, .drag, #abox").remove();
    t.find(".box-element").addClass("removeClean");
	
	t.find(".removeClean").each(function() {
		cleanHtml(this)
	});
	t.find(".removeClean").remove();

	formatSrc = $.htmlClean($("#download-layout").html(), {
		format: true,
		allowedAttributes: [
			["id"],
			["class"],
			["style"],
			["data-toggle"],
			["data-target"],
			["data-parent"],
			["role"],
			["data-dismiss"],
			["aria-labelledby"],
			["aria-hidden"],
			["data-slide-to"],
			["data-slide"]
		]
	});

    //保存并发布的html
	alert(formatSrc);

	
}

var currentDocument = null;
var timerSave = 1000;
var stopsave = 0;
var startdrag = 0;
var demoHtml = $(".demo").html();
var currenteditor = null;

function restoreData(){
	if (supportstorage()) {
		layouthistory = JSON.parse(localStorage.getItem("layoutdata"));
		if (!layouthistory) return false;
		window.demoHtml = layouthistory.list[layouthistory.count-1];
		if (window.demoHtml) $(".demo").html(window.demoHtml);
	}
}

function initContainer(){
	$(".demo").sortable({
		connectWith: ".column",
		opacity: .35,
		handle: ".drag",
		start: function(e,t) {
			if (!startdrag) stopsave++;
			startdrag = 1;
		},
		stop: function(e,t) {
			if(stopsave>0) stopsave--;
			startdrag = 0;
			$(t.item).trigger('click');
		}
	});
	
}


function initConfigurator(type,id){
	var diyCtrlBox = $(".diy-ctrl");
	var html = "";
	switch(type){
	   case 1:
	      html = $($("#tpl_01").html()).attr("id",'config_'+ id);
		  break;
	    case 2:
		  html = $($("#tpl_02").html()).attr("id",'config_'+ id);
		  break;
		case 3:
		  html = $($("#tpl_03").html()).attr("id",'config_'+ id);
		  break;
		case 4:
		  html = $($("#tpl_04").html()).attr("id",'config_'+ id);
		  break;
		case 5:
		  html = $($("#tpl_05").html()).attr("id",'config_'+ id);
		  break;
		case 6:
		  html = $($("#tpl_06").html()).attr("id",'config_'+ id);
		  break;
		case 7:
		  html = $($("#tpl_07").html()).attr("id",'config_'+ id);
		  break;
		case 8:
		  html = $($("#tpl_08").html()).attr("id",'config_'+ id);
		  break;
		case 9:
		  html = $($("#tpl_09").html()).attr("id",'config_'+ id);
		  break;
		case 10:
		  html = $($("#tpl_10").html()).attr("id",'config_'+ id);
		  break;
		case 11:
		  html = $($("#tpl_11").html()).attr("id",'config_'+ id);
		  break;
		default:
		  break; 
	}
   
	diyCtrlBox.append(html);
	
	initlib();
}
function retop(obj){
	var top = obj.offset().top - $(".diy-container-layout").offset().top;
	$(".diy-ctrl").css("top",top+'px');
}

$(document).ready(function() {
	
	$(".demo").css("min-height", $("#phone-body").height());
	var dataId = 0;
	
	$(".module-list .box").draggable({
		connectToSortable: ".demo",
		appendTo: "#abox",
		helper: "clone",
		handle: ".drag",
		start: function(e,t) {
			dataId += 1;
			if (!startdrag) stopsave++;
			startdrag = 1;
			$(this).attr('data-id',dataId);
		},
		drag: function(e, t) {
			t.helper.width(300);
		},
		stop: function(event, ui) {
			if(stopsave>0) stopsave--;
			startdrag = 0;
			var type = parseInt(ui.helper.attr("data-type"));
            alert(type)
			initConfigurator(type,dataId);
		}
	});
	
	initContainer();
	
	//编辑
	$('.demo').on("click",".box-element", function(e){
	   var id = $(this).attr("data-id");
	   $(".demo .box-element").removeClass('active');
	   $(this).addClass('active');
	   $(".diy-ctrl").find(".configurator").addClass('hide');
	   var $configDiv = $("#config_"+id);
	   $configDiv.removeClass('hide');
	   $configDiv.configEdit();
	   retop($(this));
	});
	

    removeElm();
	
	$("#save").click(function(e) {
		e.preventDefault();
		downloadLayoutSrc();
	});
	
	//编辑标题
	$('.phone-title').on('click',function(){ 
	  $(".diy-ctrl").css("top","");
	  $(".configurator").addClass('hide');
	  $("#tpl_00").find('.configurator').toggleClass('hide');
	});
	$("#pagetit").on('blur',function(){ 
	   $('.phone-title').html($(this).val());
	});


})

// 编辑页面
$.fn.configEdit = function(option){
          var defaultSetting = { colorStr:"green",fontSize:12};
          var setting = $.extend(defaultSetting,option);
		  var self = this;
		  var selftid = self.attr("id") || "";
		  var num =  selftid.substring(7) || 1;
		  var targetView = $('.demo [data-id="'+num+'"]');
		  var type = targetView.attr("data-type") || 1;
		  
		  
		  var sortable = self.find(".diy-media-list").sortable({
				selector: ".acts-header .icon-expand-full",
				revert: true,
				stop: function(e) {
					var arr = self.find(".diy-media-list").sortable('toArray');
					
					var resUl = targetView.find('ul');
					//li 数组
					for(var i = 0;i < arr.length;i++){
						resUl.append($("." + arr[i],resUl));
					}
				}
			});
				  
		  if(type == 3){
			  // 分割线
		     self.find('[name="rdline"]').on("change",function(){
				 targetView.find('hr').css("border-style",$(this).val());
		     });
			 self.find('.add-on').on('click',function(){
				 self.find('[name="color"]').focus();
			 });
			 self.find('[name="color"]').on("blur",function(){
				 targetView.find('hr').css("border-color",$(this).val());
		     });
			 self.find('[name="hrange"]').on("change",function(){
				 $(this).next().html($(this).val());
				 targetView.find('hr').css("margin",$(this).val()+'px'+ ' '+ 'auto');
		     });
		  }
		  else if(type == 2){
			   // 图片广告
			   var rd5val;
		     self.find('[name="rd5"]').on("change",function(){
				 if($(this).val() == 2){
				   targetView.find(".diy-swipe_time").hide();
				   targetView.find(".diy-swipe").removeClass("swipe-df");
				 }
				 else{
					 targetView.find(".diy-swipe_time").show();
					 targetView.find(".diy-swipe").addClass("swipe-df");
				 }

		     });
			 
			 self.find('[name="custom"]').on("blur",function(){ 
			   targetView.find("img").css("height",$(this).val());
			  
		     });
			 self.find('[name="rd6"]').on("change",function(){
				 
				 if($(this).val() == 1){
				   targetView.find("img").css("height","120px");
				   self.find('[name="custom"]').css("opacity",0);
				 }
				 else{
					 self.find('[name="custom"]').css("opacity",1);
				 }
				 
		     });
		  }
		  
		  else if(type == 9){
			  // 分割线
		    
			 self.find('.add-on').on('click',function(){
				 self.find('[name="color"]').focus();
			 });
			 self.find('[name="color"]').on("blur",function(){
				 targetView.find('.diy-space').css("background-color",$(this).val());
		     });
			 self.find('[name="hrange"]').on("change",function(){
				 $(this).next().html($(this).val());
				 targetView.find('.diy-space').css("height",$(this).val()+'px');
		     });
		  }
		  
		  else if(type == 6 || type == 7){
			  // 橱窗1
			 self.find('[name="rd1"]').on('click',function(){
				 if($(this).val() == 1){
				    targetView.find('.diy-showcase').removeClass('row-reverse');
				 }
				 else if($(this).val() == 2){
					 targetView.find('.diy-showcase').addClass('row-reverse');
				 }
			 });
			
		  }
		  else if(type == 5){
			// 富文本
			var textarea = self.find('textarea.kindeditor');
			var t = randomNumber();
			var editor = "editor-"+ t;
		    editor = KindEditor.create(textarea, {
			basePath: 'zui/lib/kindeditor/',
			bodyClass : 'article-content',
			resizeType : 0,
			allowPreviewEmoticons : false,
			allowImageUpload : false,
			items : [
					'bold', 'italic', 'underline','fontname', 'fontsize', 'forecolor', 'hilitecolor', 'justifyleft', 'justifycenter', 'justifyright','link'
				]
			});
			$(".ke-container").css("width",'auto');
			self.find('#gethtml').on("click",function(){
				 targetView.find(".diy-fulltext").html(editor.html());
		    });
		  
		  }
		  else if(type == 4){
			  //图片导航
			  self.find('[name="rd5"]').on("change",function(){
				 targetView.find('.diy-nav li').css("width",$(this).val());
		     });
		  }
		  else if(type == 11){
			  //11 底部菜单

			  self.find('[name="rd2"]').on("change",function(){
				  if($(this).val() ==1){
					  targetView.find('.diy-nav__icon').removeClass('inline_show');
					  targetView.find('.diy-nav__label').show();
				  }
				  else if(($(this).val() == 2)){
					  targetView.find('.diy-nav__icon').addClass('inline_show');
					  targetView.find('.diy-nav__label').hide();
				  }
				  else if(($(this).val() == 3)){
					  targetView.find('.diy-nav__icon').addClass('inline_show');
					  targetView.find('.diy-nav__label').show();
				  }
				 
		     });
			  self.find('[name="rdfix"]').on("change",function(){
				 if($(this).val() == 1){
					 targetView.addClass("bottom-nav");
					 targetView.attr("style"," ");
				 }
				 else if($(this).val() == 2){ 
				      targetView.removeClass("bottom-nav");
				 }
		     });
		  }
		  else if(type == 10){
			  //10 商品列表
			  self.find('[name="rd4"]').on("change",function(){
				  targetView.find(".diy-goods ul").attr("class",'goods-list-type'+$(this).val());
			  });
			  self.find('[name="proname"]').on("change",function(){
				  ($(this).is(':checked'))? targetView.find(".goods-name").show() : targetView.find(".goods-name").hide();				  
			  });
			  self.find('[name="nowprice"]').on("change",function(){
				  ($(this).is(':checked'))? targetView.find(".now-price").show() : targetView.find(".now-price").hide();				  
			  });
			  self.find('[name="oldprice"]').on("change",function(){
				  ($(this).is(':checked'))? targetView.find(".old-price").show() : targetView.find(".old-price").hide();				  
			  });
		  }
}

//初始化颜色插件
function initlib(){
    $('.colorpicker-default').colorpicker({
			format: 'hex'
	});
}