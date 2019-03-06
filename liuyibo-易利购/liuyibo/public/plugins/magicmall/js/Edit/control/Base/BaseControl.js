
//父控件
function BaseControl() {
    this.ID = 0;
    this.Name = 'BaseControl';
   this.Type = 'Base';
    this.Item = new Array();
}

BaseControl.prototype.BuildPhoneHtml = function () {
    this.ObjectType = objectType;
    this.ObjectID = objectID;
    return $.JuicerHtml(this.Type + "PhoneControl", this);
};

//Items属性 
BaseControl.prototype.Items = function () {
    return this.Item;
};

//Count属性 
BaseControl.prototype.Count = function () {
    return this.Item.length;

};

//Get属性 
BaseControl.prototype.Get = function (id) {
    return $.ForEach(this.Item, id);

};

//添加元素
BaseControl.prototype.Add = function (item) {
    
    if (item!=null)
    {
        this.Item.push(item);
    }
   
};

//Set属性
BaseControl.prototype.Set = function (newItem) {
    var i = -1;
    for (var index in this.Item) {
        if (this.Item[index].ID == newItem.ID) {
            i = index;
        }
    }

    if (i<0) {
        this.Add(newItem);
    } else {
        this.Item[i] = newItem;

    }
};

//删除元素 
BaseControl.prototype.Remove = function (id) {
  
    for (var i = this.Item.length - 1; i >= 0; i--) {
       
        if (this.Item[i].ID == id) {
           
            this.Item.splice(i, 1);
        }
    }
   
};

//打印
BaseControl.prototype.Log = function () {
   
    for (var index in this.Item) {
       
        console.log(this.Item[index]);
    }
};