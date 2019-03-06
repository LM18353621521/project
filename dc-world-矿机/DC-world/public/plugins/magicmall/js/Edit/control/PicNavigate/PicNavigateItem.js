function PicNavigateItem() {
    this.Type = 'PicNavigateItem';
    this.Name = 'PicNavigateItem';
    this.Link = '';
    this.Img = '';
    this.DisplayText = '导航';
    this.DIVLink = "0";
    this.LinkText = '选择链接地址';
    this.ID = 'Item' + $.CreateID()
}

PicNavigateItem.prototype = new BaseItem();