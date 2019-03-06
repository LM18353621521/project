function Kitchen1Item() {
    this.Type = 'Kitchen1Item';
    this.Name = 'Kitchen1Item';
    this.Link = '';
    this.Img = '';
    this.DIVLink = "0";
    this.DisplayText = '';
    this.ID = 'Item' + $.CreateID()
}

Kitchen1Item.prototype = new BaseItem();