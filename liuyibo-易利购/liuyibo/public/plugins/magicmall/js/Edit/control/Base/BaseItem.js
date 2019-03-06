
//父项
function BaseItem() {
    this.ID = 0;
    this.Name = 'BaseItem';
    this.Type = 'Base';
    this.ControlItem = new Array();
}

//渲染HTML
BaseItem.prototype.Html = function () {
    return "";
};

