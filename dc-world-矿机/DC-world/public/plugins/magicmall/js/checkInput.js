$(function () {
    $("body").on("blur", "input", function () {
        var text = $(this).val();
        var pattern = new RegExp(/["'<>%;]/g);
        var result = text.match(pattern);
        if (result) {
            $(this).val($(this).val().replace(pattern, ""));
        }
    });

    $("body").on("keyup", "input", function () {
        var text = $(this).val();
        var pattern = new RegExp(/["'<>%;]/g);
        var result = text.match(pattern);
        if (result) {
            $(this).val($(this).val().replace(pattern, ""));
        }
    });
});
