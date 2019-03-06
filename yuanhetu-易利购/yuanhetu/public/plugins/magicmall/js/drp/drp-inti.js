/// <reference path="../jquery.min.js" />
/// <reference path="drp.js" />

/*实例双日历组件*/

$(function () {
    $("input[type='text'][data-drp]").each(function () {
        var v = $(this).attr("data-drp");
        var o = {
            startDate: moment().startOf('day'),
            opens: "left",
            linkedCalendars: false,
            autoUpdateInput: false,
        };
        switch (v) {
            case "1":
                o = $.extend(o, { singleDatePicker: true });
                break;
            case "2":
                break;
            case "3":
                $.extend(o, { timePicker: true });
                break;
        }
        $(this).daterangepicker(o);
        //绑定事件
        $(this).on("apply.daterangepicker", function (ev, picker) {
            $(this).val(picker.startDate.format('YYYY/MM/DD HH:mm:ss') +
                ' - ' + picker.endDate.format('YYYY/MM/DD HH:mm:ss'));
        });
        $(this).on("cancel.daterangepicker", function () {
            $(this).val("");
        })

    })

})