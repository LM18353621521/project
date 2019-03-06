

function NoticeItem() {
    this.Type = 'CarouselItem';
    this.Name = 'CarouselItem';
    this.Link = '';
    this.Img = '';
    this.DisplayText = '';
    this.ID = 'Item' + $.CreateID();
    this.DIVLink = "0";
}

NoticeItem.prototype = new BaseItem();


