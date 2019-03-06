
//商品列表组件项
function ListItem() {
    this.Type = 'ListItem';
    this.Name = 'ListItem';
    this.Img = '';
    this.ProductName = '';
    this.Price = '0';
    this.Code = '';
    this.Platform = '';
    this.ProductID = 0;
  
    this.OldPrice = 0;
    this.ID = 'Item' + $.CreateID()
}

ListItem.prototype = new BaseItem();


