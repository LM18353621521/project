(function () {
    "use strict";
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