(function () {
    "use strict";
    //$(".L1-menu,.L2-menu").niceScroll({ styler: "fb", cursorcolor: "#464541", cursorwidth: '3', cursorborderradius: '0px', background: '#424f63', spacebarenabled: false, cursorborder: '0' });

    var $body = $('body');
    $('.toggle-btn').click(function () {
        if (!$body.hasClass('small-left-side')) {
            $body.addClass('small-left-side');
            $(this).find('.icon').addClass('icon-rotate-90');
        }
        else {
            $body.removeClass('small-left-side');
            $(this).find('.icon').removeClass('icon-rotate-90');
        }
    });
    $('[data-tab]').on('shown.zui.tab', function (e) {
        $body.removeClass('small-left-side');
    });
    // 点击高亮菜单项
    $('.L2-menu').on('click', 'a', function () {
        $('.L2-menu li.active').removeClass('active');
        $(this).closest('li').addClass('active');
    });
    // 点击收縮菜单
    $('.L2-menu').on('click', '.has-list>a', function () {
        $('.L2-menu li.open').removeClass('open');
        $(this).closest('li').addClass('open');
    });
    //初始化工具提示
    $('[data-toggle="tooltip"]').tooltip();
    //筛选
    $('#collapseFiltrate').on('hidden.zui.collapse', function () {
        $('.btn-more-filtr').html('<span>更多筛选</span> <i class="icon icon-double-angle-down"></i>');
    })
    $('#collapseFiltrate').on('show.zui.collapse', function () {
        $('.btn-more-filtr').html('<span>收起筛选</span> <i class="icon icon-double-angle-up"></i>');
    })
    // 你需要手动进行初始化
    /*$('#settingPop').popover({
	  trigger: 'click',
	  placement: 'bottom',
	  html: true,
	  content:'<ul class="popover-menu">'+
		'<li><a href="###">权限管理</a></li>'+
		'<li><a href="###">支付方式</a></li>'+
		'<li><a href="###">提现设置</a></li>'+
	  '</ul>',
	});
	*/

    $(".table-list").on('mouseenter', '.table', function () {
        $(this).find(".tfoot").css("display", " ");
    });
    $(".table-list").on('mouseleave', '.table', function () {
        $(this).find(".tfoot").css("display", "none");
    });
    // 点击收縮表单
    $(".form-horizontal").on('click', 'legend', function () {
        if ($(this).hasClass('open')) {
            $(this).removeClass('open');
            $(this).find('.pull-right').html('展开 <i class="icon icon-double-angle-down"></i>');
            $(this).siblings().hide();
        }
        else {
            $(this).addClass('open');
            $(this).find('.pull-right').html('收起 <i class="icon icon-double-angle-up"></i>');
            $(this).siblings().show();
        }
    });



    var tabPage = {
        init: function () {
            var self = this;

            var $tabLinkUl = $('#tabLinkUl'),
                $tabPagePrev = $('.tabPage-prev'),
                $tabPageNext = $('.tabPage-next'),
                $pageBody = $('#pageBody'),
                $navWrapper = $('#navWrapper');
            $(document).on('click', '[data-href]', function (e) {
                e.preventDefault();
                self.createPage(this);
            });
            //----------------------------------1-------------------------------
            $(document).on('click', '[Module]', function (e) {
                e.preventDefault();
                var moduleValue = $(this).attr("Module-Type");

                var $clickTab = $("[Tab-Type='" + moduleValue + "']");

                if ($clickTab != null && $clickTab.length > 0) {
                    $("[Tab-Type='" + moduleValue + "']:first").click();
                    
                }
                $("[CustomAPP]").addClass("CustomAPP");

                $("[Tab-Type='AppStore']").parent().removeClass("CustomAPP");

            });

            $(document).on('click', '[Tab]', function (e) {
                e.preventDefault();
                var linkValue = $(this).attr("Tab-Link");
                var $linkTab = $("[Link-Type='" + linkValue + "']");

                if ($linkTab != null && $linkTab.length > 0) {

                    $("[Link-Type='" + linkValue + "']:first").click();
                }

            });

            $(document).on('click', '[Menu]', function (e) {
                e.preventDefault();
                var linkValue = $(this).attr("Menu-Link");
                var $linkTab = $("[Menu-Type='" + linkValue + "']");
                if ($linkTab != null && $linkTab.length > 0) {

                    $("[Menu-Type='" + linkValue + "']:first").click();
                }


            });
            //----------------------------1-------------------------------------
            $(document).on('click', '[data-tabbtn]', function (e) {

                e.preventDefault();
                self.changePage(this);
            });
            $('.tabPage-links').on('click', '.close-page', function (e) {
                e.preventDefault();
                self.closePage(this);
            });
            var stepw = 94;
            var iNow = 0;
            $('.tabPage-links').on('click', '.tabPage-prev', function (e) {
                if (iNow < 0) {
                    iNow += 1;
                }
                $tabLinkUl.animate({
                    "left": stepw * iNow + "px"
                });
            });
            $('.tabPage-links').on('click', '.tabPage-next', function (e) {
                var _abs = $tabLinkUl.width() - $navWrapper.width();
                var l = parseInt($tabLinkUl.css("left"));
                if (_abs > 0) {
                    if (Math.abs(l) < _abs) {
                        iNow -= 1;
                    }
                }
                $tabLinkUl.animate({
                    "left": stepw * iNow + "px"
                });
            });

        },
        setWidth: function () {
            var navWrapperWidth = $('#navWrapper').width();
            var $tabLinkUl = $('#tabLinkUl');
            var $li = $tabLinkUl.find('li');
            // var liSize = $li.size();
            var allLiWidth = 0;
            $li.each(function (index) {
                allLiWidth += $($li).eq(index).width();
            });
            $tabLinkUl.width(allLiWidth);
            if (allLiWidth > navWrapperWidth) {
                $('.tabPage-prev, .tabPage-next').show();
                $('#navWrapper').css('left', 23);
            } else {
                $('.tabPage-prev, .tabPage-next').hide();
                $('#navWrapper,#tabLinkUl').css('left', 0);
            }

        },
        activate: function (id) {
            var $tabLinkUl = $('#tabLinkUl');
            $tabLinkUl.find('li').removeClass('active');
            $('#M' + id).parent().siblings().removeClass('active');
            $('#M' + id).parent().addClass('active');
            $(".comifrs").hide();
            var Tid = 'T' + id;
            var Fid = 'F' + id;
            $('#' + Tid).addClass('active');
            $('#' + Fid).show();
        },
        createPage: function (obj) {
            var $tabLinkUl = $('#tabLinkUl');
            var $pageBody = $('#pageBody');
            var obj = $(obj);
            var url = obj.attr('data-href');
            var text = obj.attr('data-tabname') || obj.text();
            if (url === '') return;
            $('#cfmain').attr('src', url).show();
            return;
            if (obj.attr('id')) {
                var idnum = obj.attr('id').substr(1);
                this.activate(idnum);
                return;
            };
            var id = Math.floor(Math.random() * new Date());
            obj.attr('id', 'M' + id);
            $tabLinkUl.find('li').removeClass('active');
            $(".comifrs").hide();
            $tabLinkUl.append('<li class="active" id=T' + id + '><a data-tabbtn href="#">' + text + '</i></a><i class="icon icon-times close-page"></i></li>');
            $pageBody.append('<iframe width="100%" height="100%" id=F' + id + ' name="cfmain" class="comifrs" src="' + url + '" style="border: 0px;"></iframe>');
            this.setWidth();
        },
        changePage: function (obj) {
            var obj = $(obj);
            var ParentID = obj.parent('li').attr('id');
            var idnum = ParentID.substr(1);
            this.activate(idnum);
        },
        closePage: function (obj) {
            var $li = $('#tabLinkUl li');
            var obj = $(obj);
            var ParentID = obj.parent('li').attr('id');
            var ParentNextID = obj.parent('li').next().attr('id');
            var ParentPrevID = obj.parent('li').prev().attr('id');
            var idnum = ParentID.substr(1);
            $('#F' + idnum).remove();
            $('#T' + idnum).remove();
            $('#M' + idnum).parent().removeClass('active')
            $('#M' + idnum).attr('id', '');
            if (ParentNextID) {
                this.activate(ParentNextID.substr(1));
            } else {
                this.activate(ParentPrevID.substr(1));
            }
            this.setWidth();
        }
    }
    tabPage.init();
    $(window).resize(function () {
        tabPage.setWidth();
    });




})(jQuery);

//--------------------------------2---------------------------------
function changeApp(code, appName, appID) {

    $("[module-type='MyApp']").click();

    var $linkTab = $("[tab-link='" + code + "']");
    if ($linkTab != null && $linkTab.length > 0) {

        $("[tab-link='" + code + "']:first").click();
    }

    $("[CustomAPP]").addClass("CustomAPP");
    $("[tab-link='" + code + "']").parent().removeClass("CustomAPP");

}

$(document).ready(function () {
    $(".setting_app").click(function () {
        $("#SettingLi").click();
    });
    $("[BasicsApp]").click(function () {
        $("[CustomAPP]").addClass("CustomAPP");
        $("[Tab-Type='AppStore']").parent().removeClass("CustomAPP");
    });

    $(".GoAppCenter").click(function () {
        $(".tree-menu").find("li").removeClass("active1");
        $("[module-type='AppStore']").click();
    });


});

function TranAppCX(tabContent1, code, key, img, text) {
    var _this = $(".li_category_cx_a");
    _this.attr("href", tabContent1).attr("data-tab", "").attr("Tab-Type", code).attr("Tab-Link", key).attr("tab", "");
    _this.find("img").attr("src", img);
    _this.find("span").html(text);
    _this.find("i").attr("title", text);

    $("[tab-link='" + key + "']:first").click()
    $(".li_category_cx").addClass("active");

}
function TranAppPT(tabContent1, code, key, img, text) {
    var _this = $(".li_category_pt_a");
    _this.attr("href", tabContent1).attr("data-tab", "").attr("Tab-Type", code).attr("Tab-Link", key).attr("tab", "");
    _this.find("img").attr("src", img);
    _this.find("span").html(text);
    _this.find("i").attr("title", text);

    $("[tab-link='" + key + "']:first").click();
    $(".li_category_pt").addClass("active");

}


/*水波*/
var canvas = {},
    centerX = 0,
    centerY = 0,
    color = '',
    containers = document.getElementsByClassName('material-design')
context = {},
element = {},
radius = 0,

requestAnimFrame = function () {
    return (
      window.requestAnimationFrame ||
      window.mozRequestAnimationFrame ||
      window.oRequestAnimationFrame ||
      window.msRequestAnimationFrame ||
      function (callback) {
          window.setTimeout(callback, 1000 / 60);
      }
    );
}(),

init = function () {
    containers = Array.prototype.slice.call(containers);
    for (var i = 0; i < containers.length; i += 1) {
        canvas = document.createElement('canvas');
        canvas.addEventListener('click', press, false);
        containers[i].appendChild(canvas);
        canvas.style.width = '100%';
        canvas.style.height = '100%';
        canvas.width = canvas.offsetWidth;
        canvas.height = canvas.offsetHeight;
    }
},

press = function (event) {
    color = event.toElement.parentElement.dataset.color;
    element = event.toElement;
    context = element.getContext('2d');
    radius = 0;
    centerX = event.offsetX;
    centerY = event.offsetY;
    context.clearRect(0, 0, element.width, element.height);
    draw();
},

draw = function () {
    context.beginPath();
    context.arc(centerX, centerY, radius, 0, 9 * Math.PI, false);
    context.fillStyle = color;
    context.fill();
    radius += 2;
    if (radius < element.width) {
        requestAnimFrame(draw);
    }
};

init();



/*数字从0滚动指定数字*/
$.fn.countTo = function (options) {
    options = options || {};

    return $(this).each(function () {

        var settings = $.extend({}, $.fn.countTo.defaults, {
            from: $(this).data('from'),
            to: $(this).data('to'),
            speed: $(this).data('speed'),
            refreshInterval: $(this).data('refresh-interval'),
            decimals: $(this).data('decimals')
        }, options);


        var loops = Math.ceil(settings.speed / settings.refreshInterval),
            increment = (settings.to - settings.from) / loops;


        var self = this,
            $self = $(this),
            loopCount = 0,
            value = settings.from,
            data = $self.data('countTo') || {};

        $self.data('countTo', data);


        if (data.interval) {
            clearInterval(data.interval);
        }
        data.interval = setInterval(updateTimer, settings.refreshInterval);


        render(value);

        function updateTimer() {
            value += increment;
            loopCount++;

            render(value);

            if (typeof (settings.onUpdate) == 'function') {
                settings.onUpdate.call(self, value);
            }

            if (loopCount >= loops) {

                $self.removeData('countTo');
                clearInterval(data.interval);
                value = settings.to;

                if (typeof (settings.onComplete) == 'function') {
                    settings.onComplete.call(self, value);
                }
            }
        }

        function render(value) {
            var formattedValue = settings.formatter.call(self, value, settings);
            $self.html(formattedValue);
        }
    });
};

$.fn.countTo.defaults = {
    from: 0,
    to: 0,
    speed: 1000,
    refreshInterval: 100,
    decimals: 0,
    formatter: formatter,
    onUpdate: null,
    onComplete: null
};

function formatter(value, settings) {
    return value.toFixed(settings.decimals);
}




$('#count-number').data('countToOptions', {
    formatter: function (value, options) {
        return value.toFixed(options.decimals).replace(/\B(?=(?:\d{3})+(?!\d))/g, ',');
    }
});


$('.timer').each(count);

function count(options) {
    var $this = $(this);
    options = $.extend({}, options || {}, $this.data('countToOptions') || {});
    $this.countTo(options);
}


