<?php

// app/Models/Transaction.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $table = 'transactions';
    protected $primaryKey = 'transaction_id';
    protected $fillable = ['debit', 'credit', 'balance', 'seller_id', 'transaction_remarks'];

      // Remove or set timestamps to false
      public $timestamps = false;
}

