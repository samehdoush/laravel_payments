<?php

declare(strict_types=1);

namespace Samehdoush\LaravelPayments\Traits;

use App\Http\Controllers\Gateways\PaypalController;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasOrder
{
    /**
     * Define a polymorphic one-to-many relationship.
     *
     * @param string $related
     * @param string $name
     * @param string $type
     * @param string $id
     * @param string $localKey
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    abstract public function morphMany($related, $name, $type = null, $id = null, $localKey = null);


    /**
     * The orderable may have many order.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function orders(): MorphMany
    {
        return $this->morphMany(config('payments.models.order'), 'orderable', 'orderable_type', 'orderable_id');
    }


    // create order for orderable
    public function createOrder($planId,  $price, $country = null, $paymentType = 'PayPal')
    {


        if ($paymentType == 'PayPal') {
            $order = PaypalController::createPayPalOrder($price);
        }

        if ($order) {
            $orderId = $order['id'];


            return $this->orders()->create([
                'plan_id' => $planId,
                'order_id' => $orderId,
                'type' => 'prepaid',
                'payment_type' => $paymentType,
                'price' => $price,
                'status' => 'Waiting',
                'country' => $country,
            ]);
        }
    }

    // subscribe order for orderable
    public function subscribe($planId,  $user, $paymentType = 'PayPal', $incomingException = null)
    {
        if ($paymentType == 'PayPal') {
            $order = PaypalController::subscribe($planId, $user, $incomingException);
        }
    }
    public function approveSubscription($paymentType = 'PayPal')
    {
        if ($paymentType == 'PayPal') {

            $order = PaypalController::approvePaypalSubscription(request());
        }
    }
    public function cancelSubscription($paymentType = 'PayPal')
    {
        $order = $this->orders()->latest()->where('status', 'Success')->where('payment_type', $paymentType)->first();
        if (!$order) return;
        if ($paymentType == 'PayPal') {

            $order = PaypalController::subscribeCancel($order);
        }
    }
    public function getSubscriptionDaysLeft($paymentType = 'PayPal')
    {
        $order = $this->orders()->latest()->where('status', 'Success')->where('payment_type', $paymentType)->first();
        if (!$order) return;
        if ($paymentType == 'PayPal') {

            $order = PaypalController::getSubscriptionDaysLeft($order);
        }
    }
}
