
//拼团商品列表组件项
function TeamBuyingItem() {
    this.Type = 'TeamBuyingItem';
    this.Name = 'TeamBuyingItem';
    this.Img = '';
    this.ProductName = '';
    this.Price = '0';
    this.Code = '';
    this.Platform = '';
    this.ProductID = 0;
  
    this.OldPrice = 0;
    this.ID = 'Item' + $.CreateID()
}

TeamBuyingItem.prototype = new BaseItem();


