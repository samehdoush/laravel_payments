<?php

namespace Samehdoush\LaravelPayments\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GatewayProducts extends Model
{
    use HasFactory;
    protected $guarded = [];
    public function __construct(array $attributes = [])
    {
        $this->setTable(config('payments.tables.gateway_products'));


        parent::__construct($attributes);
    }
}
