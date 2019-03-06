$("#time").daterangepicker({
    startDate: moment().startOf('day'),   //开始时间
    endDate: moment(),   //结束时间
    minDate: '01/01/2012',    //最小时间  
    maxDate: moment(), //最大时间 
    showDropdowns: true,
    showWeekNumbers: true,
    timePickerSeconds: true, //显示秒
    timePicker: true, //是否显示小时和分钟  
    timePickerIncrement: 60, //时间的增量，单位为分钟  
    timePicker12Hour: false, //是否使用12小时制来显示时间  
    ranges: {
        '最近1小时': [moment().subtract(1, 'hours'), moment()],
        '今日': [moment().startOf('day'), moment()],
        '昨日': [moment().subtract(1, 'days').startOf('day'), moment().subtract(1, 'days').endOf('day')],
        '最近7日': [moment().subtract(6, 'days'), moment()],
        '最近30日': [moment().subtract(29, 'days'), moment()]
    },
    opens: 'right', //日期选择框的弹出位置 【left 跟 right 选择】
    buttonClasses: ['btn btn-default'], //不用写  默认
    applyClass: 'btn-small btn-primary blue', //不用写  默认
    cancelClass: 'btn-small', //不用写  默认
    format: 'YYYY-MM-DD HH:mm:ss', //控件中from和to 显示的日期格式  
});

/*

 ------------以下参数为准

direction: 位置
separator: 分割符号
format：格式日历
startDate（Date对象，moment对象或字符串）最初选择的日期范围的开始
endDate：（Date对象，moment对象或字符串）最初选择的日期范围的结束
minDate：（Date对象，moment对象或字符串）用户可以选择的最早日期
maxDate：（Date对象，moment对象或字符串）用户可以选择的最近日期
dateLimit：（object）所选开始日期和结束日期之间的最大跨度。可以有任何属性，你可以添加到一个时刻对象（即天，月）
showDropdowns：（boolean）在日历上方显示年和月选择框以跳转到特定的月份和年份
showWeekNumbers：（boolean）在日历上的每周开始显示本地化周数
showISOWeekNumbers：（boolean）在日历上显示每周开始的ISO周数
timePicker：（boolean）允许用时间选择日期，而不仅仅是日期
timePickerIncrement：（number）分钟选择列表的时间增量（即30，仅允许选择以0或30结束的时间）
timePicker24Hour：（boolean）使用24小时而不是12小时时间，删除AM / PM选择
timePickerSeconds：（boolean）在timePicker中显示秒数
ranges：（object）设置用户可以选择的预定义日期范围。每个键是范围的标签，其值是一个数组，其中两个日期表示范围的边界
showCustomRangeLabel：（boolean）当使用ranges选项时，在预定义范围列表的末尾显示标记为“Custom Range”的项目。当当前日期范围选择与预定义范围之一不匹配时，此选项将突出显示。单击它将显示日历以选择新范围。
alwaysShowCalendars：（boolean）通常，如果您使用范围选项指定预定义的日期范围，则在用户单击“自定义范围”之前，不会显示用于选择自定义日期范围的日历。当此选项设置为true时，将始终显示用于选择自定义日期范围的日历。
opens：（string：'left'/'right'/'center'）选择器是否显示为左对齐，右对齐，或居中在HTML元素下
drop：（string：'down'或'up'）选择器是出现在下面（默认）还是高于它附加的HTML元素
buttonClasses：（array）将被添加到选择器中的所有按钮的CSS类名
applyClass：（string）将被添加到应用按钮的CSS类字符串
cancelClass：（string）将被添加到取消按钮的CSS类字符串
locale：（object）允许您为按钮和标签提供本地化字符串，自定义日期格式，并更改日历的第一天。在配置生成器中检查“区域设置（使用示例设置）”以了解如何自定义这些选项。
singleDatePicker：（boolean）只显示一个日历来选择一个日期，而不是带有两个日历的范围选择器;提供给回调的开始和结束日期将是选择的同一个日期
autoApply：（boolean）隐藏应用和取消按钮，并在选择两个日期或预定义范围后自动应用新的日期范围
linkedCalendars：（boolean）启用时，所显示的两个日历将始终为连续两个月（即一月和二月），并且当点击日历上方的向左或向右箭头时，两个日历都会提前。禁用时，两个日历可以单独提前显示任何月份/年。
isInvalidDate：（function）在两个日历中的每个日期显示之前传递的函数，并且可能返回true或false以指示该日期是否应该可供选择。
isCustomDate：（function）在显示两个日历之前传递它们的每个日期的函数，并且可能返回要应用于该日期的日历单元格的CSS类名称的字符串或数组。
autoUpdateInput：（boolean）指示日期范围选择器是否应该在初始化时和所选日期更改时自动更新其附加的<input>元素的值。
parentEl：（string）日期范围选择器将被添加到的父元素的jQuery选择器，如果没有提供，这将是“body”
*/




/*

 事件

在附加选择器的元素上触发了几个事件，您可以监听这些事件。
    show.daterangepicker：当显示选择器时触发
    hide.daterangepicker：当选择器被隐藏时触发
    showCalendar.daterangepicker：当显示日历时触发
    hideCalendar.daterangepicker：当日历被隐藏时触发
    apply.daterangepicker：单击应用程序按钮时触发，或者单击预定义的范围时触发
    cancel.daterangepicker：点击取消按钮时触发
一些应用程序需要“清除”而不是“取消”功能，这可以通过更改按钮标签并观察取消事件来实现：
*/