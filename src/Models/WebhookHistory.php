<?php

namespace Samehdoush\LaravelPayments\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookHistory extends Model
{
    use HasFactory;
    public function __construct(array $attributes = [])
    {
        $this->setTable(config('payments.tables.webhook_history'));


        parent::__construct($attributes);
    }
}
