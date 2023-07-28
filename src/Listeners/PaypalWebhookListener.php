<?php

namespace Samehdoush\LaravelPayments\Listeners;

use Samehdoush\LaravelPayments\Events\PaypalWebhookEvent;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

use Throwable;

class PaypalWebhookListener implements ShouldQueue
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    use InteractsWithQueue;

    public $afterCommit = true;

    // /**
    //  * The name of the connection the job should be sent to.
    //  *
    //  * @var string|null
    //  */
    // public $connection = 'sqs';

    /**
     * The name of the queue the job should be sent to.
     *
     * @var string|null
     */
    public $queue = 'paypallisteners';

    /**
     * The time (seconds) before the job should be processed.
     *
     * @var int
     */
    public $delay = 5; //60



    /**
     * Handle the event.
     */
    public function handle(PaypalWebhookEvent $event): void
    {
        try {
            Log::info(json_encode($event->payload));

            $incomingJson = json_decode($event->payload);

            // Incoming data is verified at PaypalController handleWebhook function, which fires this event.

            $event_type = $incomingJson->event_type;
            $resource_id = $incomingJson->resource->id;

            // save incoming data

            $model = config('payments.models.webhook_history');
            $array = [];
            if ($event_type == 'PAYMENT.SALE.COMPLETED') {

                $array['parent_payment'] = $incomingJson->resource->parent_payment;
                $array['amount_total'] = $incomingJson->resource->amount->total;
                $array['amount_currency'] = $incomingJson->resource->amount->currency;
            }
            $data = [
                'gatewaycode' => 'paypal',
                'webhook_id' => $incomingJson->id,
                'create_time' => $incomingJson->create_time,
                'resource_type' => $incomingJson->resource_type,
                'event_type' => $event_type,
                'summary' => $incomingJson->summary,
                'resource_id' => $resource_id,
                'resource_state' => $incomingJson->resource->state,
                'incoming_json' => json_encode($incomingJson),
                'status' => 'check',
            ];
            $data = array_merge($data, $array);
            $model::create($data);


            // switch/check event type

            if ($event_type == 'BILLING.SUBSCRIPTION.CANCELLED') {
                $order = config('payments.models.order');
                $currentSubscription =  $order::where('stripe_id', $resource_id)->first();
                $currentSubscription->stripe_status = "cancelled";
                $currentSubscription->ends_at = \Carbon\Carbon::now();
                $currentSubscription->save();
                try {
                    $order->ordable->planSubscription('main')?->cancel(false);
                } catch (\Throwable $th) {
                    //throw $th;
                }
            } else if ($event_type == 'PAYMENT.SALE.COMPLETED') {
                $order = config('payments.models.order');
                $currentSubscription =  $order::where('stripe_id', $resource_id)->first();
                if ($currentSubscription) {
                    $order->ordable->newPlanSubscriptionWithOutTrail('main', $currentSubscription->plan_id);
                }
            }











            // save new order if required
            // on cancel we do not delete anything. just check if subs cancelled



        } catch (\Exception $ex) {
            Log::error("PaypalWebhookListener::handle()\n" . $ex->getMessage());
            error_log("PaypalWebhookListener::handle()\n" . $ex->getMessage());
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(PaypalWebhookEvent $event, Throwable $exception): void
    {
        // $space = "*************************************************************************************************************";
        $space = "*****";
        $msg = '\n' . $space . '\n' . $space;
        $msg = $msg . json_encode($event->payload);
        $msg = $msg . '\n' . $space . '\n';
        $msg = $msg . '\n' . $exception . '\n';
        $msg = $msg . '\n' . $space . '\n' . $space;

        Log::error($msg);
    }
}
