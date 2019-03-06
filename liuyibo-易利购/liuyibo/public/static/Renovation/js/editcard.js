function limit(l,t,obj){
        var maxl = $(".demo").width() - obj.width();
		var maxt = $(".demo").height() - obj.height();
		if(l<0){
			obj.css('left',0);
		}
		if(t<0){
			obj.css('top',0);
		}
		if(l> maxl){
		   obj.css('left',maxl);
		}
		if(t> maxt){
		   obj.css('top',maxt);
		}
}

//拖动二维码
$('.rwm-box').draggable({
    container: '.demo',
    before: function() {
        //console.log('拖动开始...');
    },
	drag: function(e) {
		limit(e.pos.left,e.pos.top,$(e.element));
    },
	finish: function(e) {
        limit(e.pos.left,e.pos.top,$(e.element));
    }
})

//拖动头像
$('.user-avatar').draggable({
    container: '.demo',
    before: function() {
        //console.log('拖动开始...');
    },
	drag: function(e) {
		limit(e.pos.left,e.pos.top,$(e.element));
    },
	finish: function(e) {
        limit(e.pos.left,e.pos.top,$(e.element));
    }
})

var li1 = $(".hor-list li").eq(0);
var li2 = $(".hor-list li").eq(1);
var li3 = $(".hor-list li").eq(2);
li2.click(function(){
	$(".hor-list li").removeClass('active');
	$(this).addClass('active');
	$(".configurator").hide();
	$("#configurator2").show();
	$('.user-avatar').show().css("border","1px dashed #ff5606");;
	$('.rwm-box').show().css("border","");
});
li3.click(function(){
	$(".hor-list li").removeClass('active');
	$(this).addClass('active');
	$(".configurator").hide();
	$("#configurator3").show();
	$('.rwm-box').show().css("border","1px dashed #ff5606");
	$('.user-avatar').show().css("border","");
});

li1.click(function(){
	$(".hor-list li").removeClass('active');
	$(this).addClass('active');
	$(".configurator").hide();
	$("#configurator1").show();
	$('.user-avatar').css("border","");
	$('.rwm-box').css("border","");
});

$('.user-avatar').click(function(){
	$(".hor-list li").removeClass('active');
	li2.addClass('active');
	$('.user-avatar').show().css("border","1px dashed #ff5606");;
	$('.rwm-box').show().css("border","");
	$(".configurator").hide();
	$("#configurator2").show();
});

$('.rwm-box').click(function(){
	$(".hor-list li").removeClass('active');
	li3.addClass('active');
	$('.rwm-box').show().css("border","1px dashed #ff5606");;
	$('.user-avatar').show().css("border","");
	$(".configurator").hide();
	$("#configurator3").show();
});

$('.colorpicker-default').colorpicker({
	format: 'hex'
});
var $configurator2 = $("#configurator2");
$configurator2.on('change','input[name=rd1]',function(){ 
   if($(this).val() == 1){
	   $("#avatar").attr("class","img-circle");
   }else{
	   $("#avatar").attr("class","img-rounded");
   }
});

$configurator2.on('change','input[name=rd2]',function(){ 
   if($(this).val() == 1){
	   $("#username-span").show();
   }else{
	   $("#username-span").hide();
   }
});

$configurator2.on('change','input[name=whrange]',function(){ 
   var val = $(this).val();
   $("#avatar").css({
	  'width':val,
	  'height':val
   });
   $(".rangeval").html(val);
});

$configurator2.on('blur','input[name=fontsize]',function(){ 
   var val =  parseInt($(this).val());
   if(val> 24){
      val = 24;
	  $(this).val(24);
   }
   else if(val<10){
      val = 10;
	  $(this).val(10);
   }
   $("#username-span").css('font-size',val+'px');
});

$configurator2.find('.add-on').on('click',function(){
   $configurator2.find('[name="color"]').focus();
});
$configurator2.on('blur','input[name=color]',function(){ 
   $("#username-span").css("color",$(this).val());
});


var $configurator3 = $("#configurator3");
$configurator3.on('change','input[name=rwmrange]',function(){ 
   var val = $(this).val();
   $(".rwm-box").css({
	  'width':val,
	  'height':val
   });
   $(".rwm-rangeval").html(val);
   
});