function Kitchen2Item() {
    this.Type = 'Kitchen2Item';
    this.Name = 'Kitchen2Item';
    this.Link = '';
    this.Img = '';
    this.DIVLink = "0";
    this.DisplayText = '';
    this.ID = 'Item' + $.CreateID()
}

Kitchen2Item.prototype = new BaseItem();