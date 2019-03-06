$('#myTab a').click(function (e) {
	e.preventDefault();
	$('#tab').val($(this).attr('href'));
	$(this).tab('show');
})

$('form').submit(function () {
	
	
	var data = [];
	$('.drag').each(function () {
		var obj = $(this);
		var type = obj.attr('type');
		var left = obj.css('left'),
			top = obj.css('top');
		var d = {
			left: left,
			top: top,
			type: obj.attr('type'),
			width: obj.css('width'),
			height: obj.css('height')
		};
		if (type == 'nickname' || type == 'title' || type == 'marketprice' || type ==
			'productprice') {
			d.size = obj.attr('size');
			d.color = obj.attr('color');
		} else if (type == 'qr') {
			d.size = obj.attr('size');
		} else if (type == 'img') {
			d.src = obj.attr('src');
		}
		data.push(d);
	});
	$(':input[name=data]').val(JSON.stringify(data));
	$('form').removeAttr('stop');
	return true;
});

function bindEvents(obj) {
	var index = obj.attr('index');

	var rs = new Resize(obj, {
		Max: true,
		mxContainer: "#poster"
	});
	rs.Set($(".rRightDown", obj), "right-down");
	rs.Set($(".rLeftDown", obj), "left-down");
	rs.Set($(".rRightUp", obj), "right-up");
	rs.Set($(".rLeftUp", obj), "left-up");
	rs.Set($(".rRight", obj), "right");
	rs.Set($(".rLeft", obj), "left");
	rs.Set($(".rUp", obj), "up");
	rs.Set($(".rDown", obj), "down");
	rs.Scale = true;
	var type = obj.attr('type');
	if (type == 'nickname' || type == 'img' || type == 'title' || type == 'marketprice' || type ==
		'productprice') {
		rs.Scale = false;
	}
	new Drag(obj, {
		Limit: true,
		mxContainer: "#poster"
	});
	$('.drag .remove').unbind('click').click(function () {
		$(this).parent().remove();
	})



	$.contextMenu({
		selector: '.drag[index=' + index + ']',
		callback: function (key, options) {
			var index = parseInt($(this).attr('zindex'));
			if (key == 'next') {
				var nextdiv = $(this).next('.drag');
				if (nextdiv.length > 0) {
					nextdiv.insertBefore($(this));
				}
			} else if (key == 'prev') {
				var prevdiv = $(this).prev('.drag');
				if (prevdiv.length > 0) {
					$(this).insertBefore(prevdiv);
				}
			} else if (key == 'last') {
				var len = $('.drag').length;
				if (index >= len - 1) {
					return;
				}
				var last = $('#poster .drag:last');
				if (last.length > 0) {
					$(this).insertAfter(last);
				}
			} else if (key == 'first') {
				var index = $(this).index();
				if (index <= 1) {
					return;
				}
				var first = $('#poster .drag:first');
				if (first.length > 0) {
					$(this).insertBefore(first);
				}
			} else if (key == 'delete') {
				$(this).remove();
			}
			var n = 1;
			$('.drag').each(function () {
				$(this).css("z-index", n);
				n++;
			})
		},
		items: {
			"next": {
				name: "调整到上层"
			},
			"prev": {
				name: "调整到下层"
			},
			"last": {
				name: "调整到最顶层"
			},
			"first": {
				name: "调整到最低层"
			},
			"delete": {
				name: "删除元素"
			}
		}
	});
	obj.unbind('click').click(function () {
		bind($(this));
	})


}
var imgsettimer = 0;
var nametimer = 0;
var bgtimer = 0;

function bindType(type) {
	$("#goodsparams").hide();
	$(".type4").hide();
	if (type == '4') {
		$(".type4").show();
	} else if (type == '3') {
		$("#goodsparams").show();
	}
}

function clearTimers() {
	clearInterval(imgsettimer);
	clearInterval(nametimer);
	clearInterval(bgtimer);

}

function getImgUrl(val) {
	if (val.indexOf('http://') == -1) {
		val = "./" + val;
	}
	return val;
}

function bind(obj) {
	var imgset = $('#imgset'),
		nameset = $("#nameset"),
		qrset = $('#qrset');
	imgset.hide(), nameset.hide(), qrset.hide();
	clearTimers();
	var type = obj.attr('type');
	if (type == 'img') {
		imgset.show();
		var src = obj.attr('src');
		var input = imgset.find('input');
		var img = imgset.find('img');
		if (typeof (src) != 'undefined' && src != '') {
			input.val(src);
			img.attr('src', getImgUrl(src));
		}

		imgsettimer = setInterval(function () {
			if (input.val() != src && input.val() != '') {
				var url = getImgUrl(input.val());

				// obj.attr('src', input.val()).find('img').attr('src', url);
			}
		}, 10);

	} else if (type == 'nickname' || type == 'title' || type == 'marketprice' || type == 'productprice') {

		nameset.show();
		var color = obj.attr('color') || "#000";
		var size = obj.attr('size') || "";
		var input = nameset.find('input:first');
		var namesize = nameset.find('#namesize');
		var picker = nameset.find('.sp-preview-inner');
		input.val(color);
		namesize.val(size.replace("px", ""));
		picker.css({
			'background-color': color,
			'font-size': size
		});

		nametimer = setInterval(function () {
			obj.attr('color', input.val()).find('.text').css('color', input.val());
			obj.attr('size', namesize.val() + "px").find('.text').css('font-size', namesize.val() +
				"px");
		}, 10);

	} else if (type == 'qr') {
		qrset.show();
		var size = obj.attr('size') || "3";
		var sel = qrset.find('#qrsize');
		sel.val(size);
		sel.unbind('change').change(function () {
			obj.attr('size', sel.val())
		});
	}
}

$(function () {

	$(':radio[name=type]').click(function () {
		var type = $(this).val();
		bindType(type);
	})

	$(':radio[name=resptype]').click(function () {
		var type = $(this).val();
		if (type == 1) {
			$(".resptype1").show();
			$(".resptype0").hide();
		} else {
			$(".resptype0").show();
			$(".resptype1").hide();
		}
	})
	//改变背景
	$('#bgset').find('button:first').click(function () {
		var oldbg = $(':input[name=bg]').val();
		bgtimer = setInterval(function () {
			var bg = $(':input[name=bg]').val();
			if (oldbg != bg) {
				bg = getImgUrl(bg);

				$('#poster .bg').remove();
				var bgh = $("<img src='" + bg + "' class='bg' />");
				var first = $('#poster .drag:first');
				if (first.length > 0) {
					bgh.insertBefore(first);
				} else {
					$('#poster').append(bgh);
				}

				oldbg = bg;
			}
		}, 10);
	})

	$('.btn-com').click(function () {
		var imgset = $('#imgset'),
			nameset = $("#nameset"),
			qrset = $('#qrset');
		imgset.hide(), nameset.hide(), qrset.hide();
		clearTimers();

		if ($('#poster img').length <= 0) {
			//alert('请选择背景图片!');
			//return;
		}
		var type = $(this).data('type');
		var img = "";
		if (type == 'qr') {
			img = '<img src="data:image/jpeg;base64,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" />';
		} else if (type == 'head') {
			img =
				'<img src="data:image/jpeg;base64,/9j/4QAYRXhpZgAASUkqAAgAAAAAAAAAAAAAAP/sABFEdWNreQABAAQAAABkAAD/4QONaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wLwA8P3hwYWNrZXQgYmVnaW49Iu+7vyIgaWQ9Ilc1TTBNcENlaGlIenJlU3pOVGN6a2M5ZCI/PiA8eDp4bXBtZXRhIHhtbG5zOng9ImFkb2JlOm5zOm1ldGEvIiB4OnhtcHRrPSJBZG9iZSBYTVAgQ29yZSA1LjUtYzAyMSA3OS4xNTQ5MTEsIDIwMTMvMTAvMjktMTE6NDc6MTYgICAgICAgICI+IDxyZGY6UkRGIHhtbG5zOnJkZj0iaHR0cDovL3d3dy53My5vcmcvMTk5OS8wMi8yMi1yZGYtc3ludGF4LW5zIyI+IDxyZGY6RGVzY3JpcHRpb24gcmRmOmFib3V0PSIiIHhtbG5zOnhtcE1NPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvbW0vIiB4bWxuczpzdFJlZj0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wL3NUeXBlL1Jlc291cmNlUmVmIyIgeG1sbnM6eG1wPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvIiB4bXBNTTpPcmlnaW5hbERvY3VtZW50SUQ9InhtcC5kaWQ6M2MxNTEwMjgtZDZkMC04ODRjLWEyZDYtMTkwNGRhZmJlOTRlIiB4bXBNTTpEb2N1bWVudElEPSJ4bXAuZGlkOjcwODkzNjczRjQzRjExRTdCRDExRkQ1NzY4OTY1NTA4IiB4bXBNTTpJbnN0YW5jZUlEPSJ4bXAuaWlkOjcwODkzNjcyRjQzRjExRTdCRDExRkQ1NzY4OTY1NTA4IiB4bXA6Q3JlYXRvclRvb2w9IkFkb2JlIFBob3Rvc2hvcCBDQyAyMDE3IChXaW5kb3dzKSI+IDx4bXBNTTpEZXJpdmVkRnJvbSBzdFJlZjppbnN0YW5jZUlEPSJ4bXAuaWlkOjg3NDNlYmIyLTIzZWQtMjE0NC1hMTdlLTNiYjQxZTMyOWNhOCIgc3RSZWY6ZG9jdW1lbnRJRD0iYWRvYmU6ZG9jaWQ6cGhvdG9zaG9wOjkwYTZlMjRhLWYwNmEtMTFlNy1iM2QxLTkyNjE1YzkwNDg0MCIvPiA8L3JkZjpEZXNjcmlwdGlvbj4gPC9yZGY6UkRGPiA8L3g6eG1wbWV0YT4gPD94cGFja2V0IGVuZD0iciI/Pv/uAA5BZG9iZQBkwAAAAAH/2wCEAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQECAgICAgICAgICAgMDAwMDAwMDAwMBAQEBAQEBAgEBAgICAQICAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDA//AABEIAFAAUAMBEQACEQEDEQH/xAClAAACAgMBAQEAAAAAAAAAAAAICQcKAAUGCwIDAQACAgMBAQAAAAAAAAAAAAAGBwAFAgMIBAEQAAAGAgAFAgMGBQUBAAAAAAECAwQFBgcIABESEwkhFDEiFUFhcTIjF1GBkSQW8MFCNAolEQACAQMDAgQEBAMGBgMAAAABAgMRBAUAIRIxBkFRIhNhcTIHgUIjFGIzFfCRobHBgtHxUnJDJKJTFv/aAAwDAQACEQMRAD8Av8cTU1nE1Nat1KoNxEif6ygeggUeRCj/AAMf15iA/YHP+XE1NL73Q8jGDdNGddhb9Zmcll3Iq54zEuGImQbNLhkCXEpukrIq/UZlDNRDqdvDAoCKYCJSKGDoEZ7h7px/b0DtMHlulTlwQE0UmgZ2+mNK7VY7nZQx20X9r9mZTueZfY4xWJfiZHI3YCpWNa8pHoCeKigG7FRvqp3vF5PvIrl2tZKtKdPybr9iWCRlm+OZap2aMhT3VzEkXRk3MxDmZycw0YODB3I1Vwc5HyBD9ApKCACmrnu3IZy6gNzd0tpX4tFEHSOMHYVeoMrdQ1dhsR46f+P7HxPb1nO1vZh7yKPksspV5HIFSApFIwNiKUJ8fDVdOW3N2+v0ewujHPOW6Rca7HKxaElTMm3itqSLcqCEzHTbX6FLxrWPdtXYLIu2xW5AWKUp/mKBzCS21pZ4e89uN3kjcggkmq7kFOtaUoykGgNa700MXWQvc5Ye4Yo4ZUBBXipD1AKv0p1BVgV32ptXTk/HH54PIFXU2NMuebHORmy6x2lSeZ6ZmvEG6tzMqYLY2v8AcF1GuQ4ck3830iSSlwEF10gVIsgHSllks5nMBOHsrhp4loWik/U5IfplVj+pt0dQwApXVbY9vYDuOAi8tVhuXaiyQj2irj6o2Qfp1r6kJSpB67atwaO+ZfCWzxG9GzJCn1rzqysIUmaqFqlAd053bzHTRYRsPcXDaPSYO7IKhDx7aRTQK5Muk3auXqxi9RThO/sRlJEtrki3uZAOBY/pvXb0t4GtRxahrtUnbQbnvtzmcVE95Zf+1ZJXlxB9xAPFk8VpvyWu1SQBvpyvB3peaziams4mprnJKSEwmbtzcihzKooUfU4/ASEEP+P8R+38PjNTS4959qpnBNJs8Zj+Rhoa2xFHkb5cb5YWCsrXsQ0FsZw3Nbn8SkdMbJaZVw0XbVyAIcq83JE7ZRKim4VTBe8O4rzHqcdiCq35i5ySsKrBGTxViDszuQREh+ogk+lWIY/YvatllpBksyGawEvCOJTRp5AORWvVY0FDI/5VPmVBEPQHRSFxnXDbLbGwiuV93M9KK3++ZXzI2ZXPJmKKhYiC8ouAqg/kWgMqJH0eruUSzqMMgyTeT7h4Uf7dFBMo3bYyE2ka3tZpyObGSjEu3q5HqC3SpO60CDiqAaM7nMXDX0jWDG3x0RKRRxVRQq+gk0oxB3Cr9BA9yjM9VlnfvV+Bz5rDlips4qMTnUqi+mINum1TYpSEjXW6siiwU9gmmch3bdEyaZ+Q9J+n1D0EBzu7EIcY97ZgJdQDmoAA5cPURsKVIBp18jos7P7kePJJY3lZLS4bg5JJoH9Nd9+pFRWu9R0152RMQnoFijGU+1dtYOXJ9IfKIdBHacjEmfuFXrhICiVB+dmku4IoIARyZBQpg6zjzoo88cpaGSAg3A9QB8m40A+ANFI6rUU2Grqft5MVfGOQAWx9NR1BWpqfmPEdSD4nf4r9XeYmRtEo4EHsRfrO5x08IzbkMwVfRBQlGr6UjXwGbkUTYCg77pwADodwOopjF5+x8n/U5ok6GCES79SrbFQRufVVaeDU1VDGjGRSVBLTXHtjjtxIqQ7A9PTufNa/DRv4/wAtL5I19LcbWKBcn0qFXqsy8dKnetbdVoZ0VjAxVtT6XL2adQj46CbSRVFVR3DLpKkU76JwPRZO0gt8u1vGP/XciqjbZhUsvQBuvJRtUH5avcPPcSYuOcmkoDUJ3IKmlD8OlGqTQjzB1ek8Qu1sjn3W2HrFwkZOQt2NoqoN05Cfdg+n5Ci2yvozNHdTb8yyyslIR6CbmLVdKCKzksem4WEVFzGFz/bbuCXK4yXGXjl76wk4cj1eI19pjXqaKyHx9IJ3NdIf7pduw4jLxZWyQJY36F+I6JKtPdUU6KeSuB/EQAAKabfwyNK/WrlXQt0O2QeSi3MoCHxKQPzmD7QEefIPx+7iamuT/wBfx4mpqp5szvDpw43ab6wZe2CduMhpbfRN6tsBK4zkYnXHIN1qD6JgcKazXzOy824fVeOprmOi2pp89fWpxbOq9RdrFTM5dJq3Idt5DJST31tJHc3Yu2mMDAxxzlKJHD79SR7ca8VPD2zIWLChJ05sZ3VY4ezt7O4gktrJ7IQi4VlklhD1aW4FuQFIkkJYgyCRowoU1CjT3rjs3h7H2KZLPOXb3WcP0GIWO1uk7mGwxWP2VLthpV3CyNOtj+VWFFC3MrMydMRZsyPXDtduc7ZNZHkpx8xt7Jm4vdtIZP3BPrjkpG0bVIZZGbZWDBloAxNCQCu+sshDaYUe3cTJ+0/8TxAy+6gUMrxKu7KUKn1FQnIKxDbai2ubqa0ZlxrLZPxRlGl5fxMzIMJa8n4htMRk2j0F7IAKJWeSVINRG1UJJwkJhK6kops2FMBMCglARCq7qafFWRfK28sdoylWmRlmhjrQfqsnqQb/AFMgA89WPav7XNX0ceLuY2vRIGWCVWgml41P6QescjbGiLIWPlqnnuvA0HHVlyIxmo5vKHsgLEx7daw8jZiqWlqWVWlYSzQ8iyVKQrZ7EPRSXX6+wKTo6pTKCBgIm8FEwkECMCInZTQ+lhWqEEVryWjLTxFBp55z3GDswZZSqkq60kjqPUrA+AaoNd6HQaBV63d8BT8+3kxfnZyFfm637hwKLV88qUEvHKOHC4nEUlnVQMRgILFKks/QHkJjCcoW8rXFlmI1K8aqyMBvQSMGp8w/rqPymmqKOOO6sWepK1DCviY1K9fJl9O/Qj46hfTqVRYmmqdYEF3NkrFsksdpx5kCLkkcZ3apJ2KLrThRVIyTqZQn2CDeE7hSlK9XTRD0FIwXnc8Hv+1f2pHtzxByAaHnGxUuBuFUipfqeO56nVB21cft2lsLuolhlZFNPCQKwRjtUiqqp6V23oNWW/CHlh3iHYim4zlFkFK9cMeTOOEZdEV27OejE5VtfcV2huks6VaiLiNll0FCgbrb9xwQodKPLjPsPMCw7yVmJEF+GjYGmzUBXpStHUivTi1da/uNhDlOy3MYBubFllFOhG6vv8VatP8ArAXV0eKdC4Q7Zx5qI8iiI/ExB/IYftEQ5ch/D7+Oltcp60UmsKzxX1+VMe0X7gJ6G/qfnxNTUTZiyLH4lxhdciyZiFa1aEXfF7ogCZ3qp02cakp8xTCRaRcpEEC8ziA8igJuQDWZrIJisVPkJCAsUZO/SvRa/iR8fLfVx2/jJMzmrbGRfVLKB+A3b/4g/wCuvKd33oN2s2+VwxAVJ1YMiXDZE2PIxnHJmWkJew37LK7CFUYthHv998exov0CfnFFYh/QpgHiswYX9inun0blj5gkkn8RUj5jRH3DzadGgFH9tFUeRAC8f9rDiadKHT8//Ui+zHB07Sal5Curi6wDW5bK2pR0kDJtCwd4Mvj6IqVKYnj2jD/IE8c4xWcN2UjIFcyq31N4oqsJVCgHlw19e3RMeRdHvUMteAAUBpSyJUfWY4+C8juxBPTc7M1jrSwpNjYmjs5YYCvIks1IwJJCDugkl5ngNl2GlB+BfOuTcM+RzL95p7xw7q1d8fWxdkv9LWeGb1y8RlLxsMvQoOwIgb2q4O8uKQyCC5i91JR0YhTAVU5Te7P3keN7Zvb9zvFbvQEV5M9I0UjxBZlqPLw1S4Wyky/c9ji4xtPcRJVTTioPJm36FVDHw01jfSMyRurqzW2M3qJj/FF2w97aYgsgYDnZNtG0MGkK4cTMLa6w7YpV+xw1oXVVK6KiqCjaQMUxQKksoPHOM/c9lYSQvHZ28awrHFJKlVR4lASjIqgK7GkpkJLK4O9GOurrPsq7mN0LjK3kxm5SRpNR3EpNVKOzFyqrWP2yOLxmgoyrqv3pxl1xMR09gyefAZS02Ocp1ceuk3K6BH0mEZPMWT5usJQRZKSUa0coAf8A6ZxcgAmExw4L+9ccsMEeZt1AVYEkcbAlQSDuK1bdgG8QV0F9lZH3bifEXBLOty6J1IrQHodwvEBiv5eLddELd6lN4lzIIwkWmhO5Fp+Lcuw6cgqsRFex4tI3CWhZUxl0+gZtSDcIHROPfKuoAEHqDpML4e9S/wANEzkmO3mmhI2rwnLFSv8A2chXzFQdEuZsja5a5VAoeeOKYEj087cKCCQa0crUU8dx0Oni6eWauJTmumXoZJJm0xrlqAiwSae3MvIYwvblwzrT5yYCppmYRid2WIsTkQzcpjikAFMHIasribH5+2kuSecV1Hy8KEkIVHmN/wAF8tXt/Cl7h7mC3A9q4s3Kn5DlXbxNKjwJ1eVq8oi7IycorJqgqmkg57YhyIuoikc6RgAx+g6Zzl5hzEQ+HHZMMgljDjxA/vpriW4haCZo2BFCafEVIrr9Dm6znMPxMYxh/mIj/vxt1o0FO7VZfZCqWLsXsnazNO/5Xj42QMQf0FmkXVLZOINnhBMQi6LiWj2pCpmMUoqmIb4kABBO/LWXI2tliY2Ki5vVU+VFjkeh+ZAAHnQ+GmL9ubqPGXt9mZFDftbFmHnVpI1qPIhSxqN6Ajx0qJtr5i5r5P8ACebT6xYhkLLFY1UsbzYyw1iUJY6FCDiNvVcLyMnJr21hTmt0j8iwjysFsMhEvJoETtmpXCYopqpibZ7K4/JjFx+0mH9hZOTLxorRhYVaQkU/WUxkkVqApI0dr23hcri3yjrNNmBcOntIxcMyyl7mRYgpLUtj7oRdqcnCnfUj7jeN5fyM4tUwvs80Y4yjY6YlrxUMh4qtSVqnsZZVYlShahZKG1tENDi/irNV3shF3aAl0hYSjUW5m7pJds1VTxwVzmbTJC5eNVsXU+4OYI51HDgDTrvzLeAUg8gBqx7vtu1MxhwmKmkkzcUi+zI0Rjb2iGMqz05hgCVaAxkmpcMvFm0vnTzwKWDToMhRETZYy2z+Uwg61kfYW9vqrHyamJ6hZW91gMWYoxdSVbCvDxdkukdHy1lkpyZM7kBiWTBJs3aFcHcenu67yuctI8YsK2uMWUSSM0iu0jJvGqha0RW9RBNXYLUAA119g4/tDtbIHuW+vJMjnViZILeO1khihLijySyzU9xwtVT214ryY8mPGh9bDnrGrev9jr1Ui/q0VC1C0zFnfO0EjFmXKscq3WO//TU7ysrJu000m5AMY5zkKHygPCg7q9mwsxj7UAmQHkTsK9N/767eWmxgbqTL5H+qXR4NzTgq9VANRT5DqfD501QT1NxY5S3Qt0M9VbwSdMyPGrmlGphfQ0bZIdxb3rpi4cqdtPqTTakKRv8AN3CtjBz/AEzABt3NfN/+KxyJ65ZYQCGIBK0jANPHfqdh6q+Ol723aRt3rmbrpBHICtKmrM0pO/h6dxWpotNHR5B8qQ8ZJauZkhiAVORkL0g/dNCMXDutXdqDK1V4ZBE5FGRo5nORheaSvMFRarEH8/VwN9m40XMV/joz+uESTiagMvMoQPGvBjUj+E6u+6r/APZS2ORk3tzJJEWArxPAMCfL1LtX+IaITTfNlQuMKrDV5RZsSVbtz/RDqIgpGTcg3cTMOzZqh6f4jFzjpePA4lAqJkE0QMUTEHgT7otrnGXXKdat6gSNiSh2Y+TMAGPjQ16aMMDJb39mHtz+mgVlHUBHG6/7d1r401f31UuKl51/xPanKxFpJ/VYw8schBTEJZuQEXoHKPMwnBZP8w+pg5D9vHWfZ19/Uu2bO8Jq7QivzGx/y1x73zY/07uu9tVFIxLVf+1gGH+f4dNECcolOco/EpjFH8QEQ4JtCeoJy5VJCyLN1mvZ99Xm8XdKeooBjGRtlKmDSDhmBU1EjinYoCQXYKcxEoEUMYA6iFHgaz9rPcLzgCmeJRLFXf8AUibkV6j+YhZOu1a+Gi3tq9gtT7ctfZmkMM1P/qmXiG3B/lyKr+ZpToTqDrrTsfZBusZWpBjHP6dmHCeUzi1XUWSO+p9xjoBG5wopoHKq2aGkHTOWROAHO0klV1EuhQwiIT3PFHcSxvahDBc2crqGUGqOgLoQQQBz4SrX6XLEUOjztbJ5LDwtcxSSx5bH5K3HJDQ+7DITE4NRvxDwtuA0XFW20AXjDyZshedZK/E55yN+5Nkx5JWbENtcTOO3leyBEXbH1leRn02w3FnJjUrmyj6wVh0vWbdw4dHU7i7rumOimKY/MNeW4SF2ax4qAGUkqQBX9Q0Zt60DAsBsT0Onv92sR2hju51m7dDm7lhWeeYhIIJpJxzcwWacvYVWYxkK4hcpzjRQxAZsZRNdPoUXOY4FAFOoRAQ5evwEeX9PjxZM6uAHap+J0o+LI1QBxOlDeVOWSYYci4WHRYO1nt3g1JBu9UE7ZZpHoSE03VdNClOaRQTmoxuYETAJDHIXmA8uQqzvQx+/FFGTQFiab9QQPwqeumn2ZyMMkk2yFVFdxQBhWlPh18+mqZ2iuK8i279+G7x4mOSsf5giLMu5ke5ylXKVCsEk2kZV+KRjIQL81mWXOqTqUUF2c5/0yKANx3nkbeO4wssC1sJrNlI6lR7ihqAdWXgvXbYU66r+3LMpBnLeYn94l6tG6ByI2aOnwbm1PKpGxGhxzANhtGvOQIOwkVlGdUrtftlWaOCm90iwq2QRrUsm8dNi9hyLysWZ01IqmBgcItQP1ABCmEiwE9rb90QSWx4Gd3jc125NH7imhO1GQGn5a0pvqn7itLm67OuVuF5LCUdKihKhxGykjqSHpt9dK1FNcfprLZBxncMI2YPclWvsbfEY4omUVLIw9cn25OahAIdEvceNzFOCgmJ0j68xLxt+4kNhe2F+IxQW0kDV22Z03p5gA/idavtfPeW13YW943JLmCdWXrRUc8S3gDXcVOwrvr1JvG7MIzerGP8AsiJRTM8bC259YM1EjoEO0EwHOCYp9YCCfp0kEOQfYB99prgT9k2p/MAwPjQg0O/+NPDSk+7tu0Pec5I2ZVIPmDWh/wBK/DR+yaIovFfT5VB7pfvA/qb+h+fDK0sNQvkmVlXXs6hVJD6NZJJdkCtqMi3cpUxjLKOYtOTRZuyKtpWwv0/ckjWihFEBUSOsuHZSEio9mrm6Z48fjmCXbuvKQ7iJGJXkAdnkYchGnTYu/pWjEmBtrZFkyORX3LNEekQJBmZAH4kjdI0PEyPWu4RPU1VEDHdWtRMwZLv04zZVbGlDxvU8IYIi3IruJVnV4ZNOfuVqknq7lcDJvnqTFl3AEFHAs1gMJyplOdU5aOX+o3Nyx4W1tarBBHuSsaiu5ruzbVPU1I6KKta2eI4y0s0Bkvrm7e5uXFAJJZPSFAoKBfUQOi7HYttXwoXnYR151w2CkrhphmRWtaz7HMsRSjlO4UevyMlY8z2fIripEWhJZmjJRRy/4Us4fprJiq3JIN+Qqh1KBV9rwS3GMjx9ncWskXBpA1HXhVeRVyEPJgQ6gqaEoQQDo778hEWY/qt9DcxTSCKLhRH9wIwi9yOsi0jIaInkPSHqOW4G70z80eQ/IyxysGAcUM8aWfGE/BsLLQrVYELhaT1m1FdIQl6j5JhGw8L9KLNMHDB0QE1RbL9j5zg4L00neGO7rwE8At2gnt7lCQ6KwowpVSGJNaEMDsCK7Cm+7sm47O7iiuHvRcW91ZyBWjdkJYEGjgr4VVlIBahHUjoY1i0Iz3sxVbW9ydlwKTOylcmiU4WyRXnsrWdsDusvJJ8cgpsodrPNkFFCNyFEqZREREA4BYu2c7kbg3+Sm3QEqrbBjT6CfAN0J/KN/DRnfd6dvYeMWGPtjJHyUsR1Cg0JUfmZQSQD9R2HXVMw2yGbNFdr8osM01SRqdrBqri3LMBIo9tNnMik7rMPawQOmALxcjDv3CCj9ITldxx2ayfV2hExtb9uJ3NgY4sa5GRtzIUDfXQge9C3XcEBo6bVDUPFhSkzGYs8HkTe3IBwF4IqyJ/L6kwzL4hWqUlruNqjkprwOZLkhHYHzJCN1oMz5hEXmJUkGywe2m4K4NWuRce2GAM1E6TZu/jUHSKiCf8AaC8Zc0+kFDE4+9u2bv3PjLllkEbunJSN0dCYJUbp9LEGp3owr0rrDui8jXs7LQhl99IiUYE0KkCZGHUetRTbqVNOtNTtqqwRlbHg9y4QKSm1TClKJHcilXixPESatkvUNHJHKQEn9ndIpmUKIKCZM4AJgERLxWdz3HE3tuzcrmXKSFgeoqgWP/aAKeAr4eOvT29b+zBa3KrxgjxEdCp9O7lnO3j6qkVJ4n8NXivCZn6qZJreRcWRq6BJaqSbC+otElSGUPWbFFRAMlTJlE4lTbqrIoqAcQUTXKPUHzBzYn2UyiJb3fbko43MMhlX+KNiEJ+IVxSviTUbHSn+9+HkBsu4U3gkX2W+Dirj5ErXby+WnxyrUXCHcIHNRHmYAD4mIP5yh9oiHLmH4ffw+Nc/agOwfT4mRfvXpQdu3c3Dy8e0KIgr/wDKiCtWomMIm6GyL9M5+ZQ9THMHIfmHgVzeRtMTylmHuXLyq6J4kqlASfBQwqT8wATotwlhdZRVihPC2WJ0kcjYcnqQPNypFB5U3GhkzpYk6dgrL04DlhGO4fHF2m41Z4p7eKZyjOHfOoJq4MJhOVktMdlIS9QmOCghz5jwn8tdmLGXU9weE0is9fAcj5fDoo8qAnTgwFn+4z9jDErPbCaJCPFlBAbfz41JNOu9NUlNscysM760ihKErVYzVtVsDUcvXurREOizbp3LVvB19fOV5aDOuZ4ItXNhKVU6gdo4tgKJjgUeYJ27k8jZxXsMKyPDM6e4UFFUSPw9AH0mdpGIC0Cnmdia66V+4HZdjZZDE3aktZQ28627ysGLhAJSWOw9qBVTlXc+gU3oUs+G/aculW9tCzBZ1vb4ulZ6LwHmMFQFop+3+ZbGlAKWVwk67Qe5oE9HMLEimqUogiyUJyA48gf3ckC5DHQ2UYrMytOo609qOpAP8QbhXxah6b64+wry2N7e5VmpFG6wf9PP3pwF5GlaApz26IWHU69UhJ8mkBmKqiThy2KLb2rVUgpHMiUxe8gYfQzdQodRFB+QSiBvUOFTJfwgCrBmIoFBFa08f+P46MEtZWbnxZIzuWZSCB5EHofAjrXbSXvJX4/taN4qHOR+X66k5yYiC7im5lrzVJndKU7bIe2jINo9IQxZ2htEEylUi35F0lz9a5RTVMBgEEvczgL9srh5OF+5BYGpjcCoCMtfpA2DCjgksDvTTCgOPyVimFyUHuYZQR7dQpBahMqtSolLeo/lOylSBXVCDa/XLM2qq9gwdcGbiShywUmnj26lEwBbK5AOFJVrCprCItnD+DTcO0lmgiJ/YOkjpcykWIDT7VzuJ7kvI80yiDJrMv7iDqEdwEMqnrwkojKwGzqytuVJBO58PlsFiJcFbM1xhzbsbacikhRKv+3lFSOaAupFfVGUZNgyhm2h1vx1K6EYnOLlinmHFl7ubaXrrhNBMkpjWXYpMrJWpVZUyYl/cGvKN1miHcTEr9h1lOk47YmXX3CgGO7zvYmJMN+8ciNSqhlApv1BSSu4HQg9K6OPtzLNlez7GYr67GBoXUGjU5MAaDbeOho3y+V6bwxaJp6lYMm8iWZm6RyTnw0DNHaybb2srVsYQjVz+3NXkWqiKarKdMxk1XkkUf1BOqgiqAHa8ge/227ekxWHGSv4+GVuwGYHqiblV+BYkuw23YAgFdc+/c/uf+s5j+lWchfFWRKqQTxeTYOw+AoEXc7AkGjacxwx9LHUF5Hp7tFR3Z4lJV6BkwF4zKBlVWoEJ0iu3L8xhZgUvM6ZA+QRE3qUR6V93Zhrnm2WtQ0vp9S9StBSo/hHUqOhqehND3tjNQiJMTcFYqMeLdAxJrRv4vAMeooOoFUceQOFylsDT5fC1RQmq9jhzBP5vI2S4m7xlEfxshEuWjqnxMc+flXWVim82kV/KnRTMcrdqQnSoU5y8ILuC7vLhjDxBtkozEleLUOysDtxPUgCo10t9vBj8FkI8tfqss/LjHEUZzRgQzgCnr47R1NKmvgNJ9yB49dNsrWrFdAvORcn5/3Mr6ExJwZ8DyMLjGpW179EjrFYaxcGKMVIFpeE7WpB+6sFjlXEaxj0FXzlmuY6xWxt/aUmQa7mxWJgedbx1LcY+UKFKgMkknGhjD0aUEhRQcWbjoo71ydhk1j7hy8kGLscXA6xCWZjcyJK6sYZFjD82cRlooRGvNlo0iJycC3qx4mNY8J+LrLflc3yGIz2ezsrTsrgvWYryw0bX6ZuVlmZWhYAa3mTajD5OyJB5Guku1cRcSg6bojXnoHFo+dLHQ46ZtsUsM8dwxIeKD2VAP5Aw3J68jxFaU2FK64zyXcS3VpJZ2yH2p7n9w7P1LlWAVUB4BE5tSoJ5GtAaafRpfnzYiP1Cwpg3Eegu522DqsY7jovJG0+091oOmVAyXfptNSUttoxzJ59OrsO8xkSddnaVgjeks/pVaZskG6BiIF6pLgsJLamxmtbf9owYceCj6q1owFQ38SsDUV1Wx5vMx3Yv4rq4/eKwPLkzV49AVJ4kHfYqRQ00auF8J7YOahOzW1hNUqW594u5rsfhefzJZ4SsQSogJIK95Nyy0r8JZ5mIJ+krKx7Vg3dqlE4IEL6iu8h9rrV1piboxqSaCZS9PL1qQT8yPnvvphY/wC592hP9WthISAawsqbjqQjA0r5V2PSg20m7yWYl0hy5QbHSrFunoZXLyxesnsbFze1uKYywspSLcmcqopxbSbk5uOdvGQrNVR7aYiiuJTCIenAbN9se7sVeJlMK9pJeR1/8hUMpBqrBkIPmK9GAOj2x+6naV7ZNi82l5HYyih/TDFTtQgo4J8jQbgkeOuM8GH/AJ/Jui3CX2A2qiov9k4nIkZkLXHEgTpLGvc1I1m6Sh7VeJFsmmzmcYs1V03kM0VMoM6CaCq5CsEilkWRg8FcdwyWncHc8Bjv4IjG0TqAHkV/5vGpAU8agbc/q+hgCA5/uqDtmG87b7PuQ+PuZvcWWNj+nC6fyASASw5EE9U+j61JF2jhmaUOs4mprOJqaCvZnTSCztXpxGqW6TxfaphNqRy8YM0JWqTKQS0e8l21hrKh2ii31uIbLsTrNHTU6XuhXMVY5AKYA7l+3uIz5M8RNvdkgniAY39QLc023ZaryUrQnkQ1KFj9qfcrL9uOkVwourJKgcjSVPSQvCTenEkMAysNuI4g1CpqJ47rjpVizzb5No2P3UrbM5YmscZgZ/VU1brk3J9fo2psz9HRbwUAeVm2Ek6zZbJOJiIFFAzsGUWzEBcnVBQ13gO3bft2wSzgZ5nQEc2A2DGpVFGyJULsCSxALFiBSn7p7uv+7L83l0scEXppGldyooHkc7ySEFvUaBQSqKoJqSqk7rNodoNqVP7ZSVMoWPtR8Ha71Wjyd5g1Z2cYZWrOIK9Xo4uMKIDJ5YbbmZcpXTeLaxjFxKthM5UT9vzVVLdcXJCjYAf8z8/8vhoZJTq27E/4aHWOyT5ZN6hLOYSp1b8XGuk+UjiuZW2XozfPvkAyJBLos3EXZa9rWd8XEuvwPUTuEUUbe+k5hBRNBbsmIYSj84xodwWbx/t/kdvHfWXJ3Hp9K/2/5Hr8tSXX/BvrRe5RpctvJnYTyC31N79VLad6s5XK61Fi4VFIF0Krr1RHNKwvVI5AUulBmpCOATS9O6cxzGHMB23Ap8/7V1rJjAoWJ+A6f8P8NMjw5otqBgb2imKNX9bsdOY9VNaNcY8wHiqiqxyqJ+4iu0eV6sNpb3RDevdVdKKCb15gPGxY6D1nkdYNJX6RxHzOi042a16ziamv/9k=" />';
				// '<img src="./static/images/head.jpg" />';
		} else if (type == 'img' || type == 'thumb') {
			img = '<img src="./static/images/default-pic.jpg" data-imgname=""/>';
		} else if (type == 'nickname') {
			img = '<div class=text>昵称</div>';
		} else if (type == 'title') {
			img = '<div class=text>商品名称</div>';
		} else if (type == 'marketprice') {
			img = '<div class=text>商品现价</div>';
		} else if (type == 'productprice') {
			img = '<div class=text>商品原价</div>';
		}
		var index = $('#poster .drag').length + 1;
		var obj = $('<div class="drag" type="' + type + '" id="'+type+'" index="' + index +
			'" style="z-index:' + index + '">' + img +
			'<div class="rRightDown"> </div><div class="rLeftDown"> </div><div class="rRightUp"> </div><div class="rLeftUp"> </div><div class="rRight"> </div><div class="rLeft"> </div><div class="rUp"> </div><div class="rDown"></div></div>'
		);

		$('#poster').append(obj);

		bindEvents(obj);

	});

	$('.drag').click(function () {
		bind($(this));
	})

})


function showImageDialog(elm, opts, options) {
	var btn = $(elm);
	var ipt = btn.parent().prev();
	var val = ipt.val();
	var img = ipt.parent().next().children();
	options = {
			'global': false,
			'class_extra': '',
			'direct': true,
			'multiple': false,
			'fileSizeLimit': 5120000
	};
}

function deleteImage(elm) {
	$(elm).prev().attr("src",
			"./static/images/default-pic.jpg"
	);
	if ($(elm).prev().attr('id','minBgimg')) {
		var imgBghtml= document.getElementsByClassName('imgBg')[0];
		var TelBox=document.getElementById("poster");
		if (imgBghtml) {TelBox.removeChild(imgBghtml)}
	}
	$(elm).parent().prev().find("input").val("");
}
$(".colorclean").click(function () {
	$(this).parent().prev().prev()
			.val("");
	$(this).parent().prev().css(
			"background-color",
			"#FFF");
});