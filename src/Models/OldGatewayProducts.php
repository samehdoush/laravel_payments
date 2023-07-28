<?php

namespace Samehdoush\LaravelPayments\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OldGatewayProducts extends Model
{
    use HasFactory;
    protected $guarded = [];
    public function __construct(array $attributes = [])
    {
        $this->setTable(config('payments.tables.old_gateway_products'));


        parent::__construct($attributes);
    }
}
