var pn = 0;
var d = 1;
window.touchEvent = false;
$('#page0').removeClass('status2');
$('#page0').addClass('status1');

function MotionEventRegister() {
	$(window).resize(function() {
		$('body').css('width',$(window).width()+'px').css('height',$(window).height()+'px');
	});
	$('#page0').removeClass('status2').addClass('status1');
	$(window).mousewheel(function(event, delta, deltaX, deltaY) {
		status = $('body').attr('class');
		if (status != 'playing') {
			if(deltaY<0){
				motion(1);
				$('#navigation li').removeClass('page_on');
				$('#navigation li:nth-of-type('+(pn+1)+')').addClass('page_on');
			}else{
				motion(-1);
				$('#navigation li').removeClass('page_on');
				$('#navigation li:nth-of-type('+(pn+1)+')').addClass('page_on');
			}
		$('body').addClass('playing');
		setTimeout(function() {$('body').removeClass('playing')}, 1300);
		}
	});

}
function motion(d){
	pn = pn + d;

	if (pn==-1) {
		pn = 0;
	}else if (pn==5) {
		$('#page4').removeClass('status1').addClass('status0');
		$('#page0').css({'display':'block','opacity':'0'});
		setTimeout(function(){$('#page0').css('opacity','1').removeClass('status0').addClass('status1');}, 100);
		pn = 0;
        motion(0);
	}
	if (d==1) {
		$('#bg'+(pn-1)).css('opacity','0');
		$('#bg'+(pn)).css('opacity','1');
	}else if (d==0) {
		$('#bg div').css('opacity','0');
		$('#bg'+(pn)).css('opacity','1');
	}else{
		$('#bg'+(pn+1)).css('opacity','0');
		$('#bg'+(pn)).css('opacity','1');
	};
	$('#pn').text(pn);
	$('#page'+pn).removeClass('status2').removeClass('status0');
	$('#page'+pn).addClass('status1');
	$('#page'+(pn-1)).removeClass('slow');
	$('#page'+(pn+1)).removeClass('slow');
	$('#page'+(pn-1)).removeClass('status1');
	$('#page'+(pn-1)).addClass('status0');
	$('#page'+(pn+1)).removeClass('status1');
	$('#page'+(pn+1)).addClass('status2');

	// Energy saver
	var lens = 3, keys = 1;
	for (var i = 0; i < lens; i++) {
		if (i <= keys) {
			$('#page'+(pn+i)).css('display','block');
			$('#page'+(pn-i)).css('display','block');
			$('#bg'+(pn+i)).css('display','block');
			$('#bg'+(pn-i)).css('display','block');
		} else {
			$('#page'+(pn-i)).css('display','none');
			$('#page'+(pn+i)).css('display','none');
			$('#bg'+(pn+i)).css('display','none');
			$('#bg'+(pn-i)).css('display','none');
		}
	}

}