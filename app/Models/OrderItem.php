<?php

// app\Models\OrderItem.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $table = 'order_item';

    // Define the relationship with Order
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'order_id');
    }


    protected $fillable = ['order_id', 'product_id', 'product_name', 'lot_number', 'lot_frac', 'lot_amount'];

      // Remove or set timestamps to false
      public $timestamps = false;
    // Your model logic goes here
}
