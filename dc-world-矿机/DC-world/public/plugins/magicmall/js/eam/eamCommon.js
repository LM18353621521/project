
/**
 * 自动映射通用js,依赖:lib.js
 */
var EamCommon = {
    getUrlKeyParamStr: function() {

        var assemblysStr = $.getUrlParamters("assemblys");
        var separateTableIdentityStr = $.getUrlParamters("separateTableIdentity");
        var isCustomLoadScriptStr = $.getUrlParamters("isCustomLoadScript");

        var keyUrlStr = "assemblys=" + assemblysStr;
        if (separateTableIdentityStr)
            keyUrlStr = keyUrlStr + "&separateTableIdentity=" + separateTableIdentityStr;
        if (isCustomLoadScriptStr)
            keyUrlStr = keyUrlStr + "&isCustomLoadScript=" + isCustomLoadScriptStr;

        return keyUrlStr;
    }
}