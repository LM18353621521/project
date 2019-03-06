
//秒杀商品列表组件项
function SecondKillItem() {
    this.Type = 'SecondKillItem';
    this.Name = 'SecondKillItem';
    this.Img = '';
    this.ProductName = '';
    this.Price = '0';
    this.Code = '';
    this.Platform = '';
    this.ProductID = 0;
  
    this.OldPrice = 0;
    this.ID = 'Item' + $.CreateID()
}

SecondKillItem.prototype = new BaseItem();


