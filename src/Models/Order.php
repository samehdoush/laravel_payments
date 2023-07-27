<?php

namespace Samehdoush\LaravelPayments\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Samehdoush\LaravelPayments\Traits\BelongsToPlan;

class Order extends Model
{
    use HasFactory;
    use BelongsToPlan;

    public function __construct(array $attributes = [])
    {
        $this->setTable(config('payments.tables.orders'));


        parent::__construct($attributes);
    }

    // public function plan()
    // {
    //     return $this->belongsTo(PaymentPlans::class);
    // }

    /**
     * Get the owning orderable.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function orderable(): MorphTo
    {
        return $this->morphTo('orderable', 'orderable_type', 'orderable_id', 'id');
    }
}
