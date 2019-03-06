

function BottomMenuItem() {
    this.Type = 'BottomMenuItem';
    this.Name = 'BottomMenuItem';
    this.Link = '';
    this.Img = '';
    this.DisplayText = '';
    this.FontColor = '#3a3a3a';
    this.BackgroudColor = '#FFFFFF';
    this.DIVLink = "0";
    this.LinkText = '选择链接地址';
    this.ID = 'Item' + $.CreateID()
}

BottomMenuItem.prototype = new BaseItem();


