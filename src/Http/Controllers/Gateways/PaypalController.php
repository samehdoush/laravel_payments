<?php

namespace Samehdoush\LaravelPayments\Http\Controllers\Gateways;

use Illuminate\Routing\Controller as BaseController;




use Illuminate\Http\Request;
use Illuminate\Support\Str;


use Srmklive\PayPal\Services\PayPal as PayPalClient;

use App\Events\PaypalWebhookEvent;
use Samehdoush\LaravelPayments\Events\PaypalWebhookEvent as EventsPaypalWebhookEvent;

/**
 * Controls ALL Payment actions of PayPal
 */
class PaypalController extends BaseController
{



    /**
     * Reads GatewayProducts table and returns price id of the given plan
     */
    public static function getPaypalPriceId($planId)
    {

        //check if plan exists

        if (!is_null($planId)) {
            $product = config('payments.models.gateway_products')::where(["plan_id" => $planId, "gateway_code" => "paypal"])->first();
            if ($product != null) {
                return $product->price_id;
            } else {
                return null;
            }
        }
        return null;
    }

    /**
     * Reads GatewayProducts table and returns price id of the given plan
     */
    public static function getPaypalProductId($planId)
    {

        //check if plan exists

        if (!is_null($planId)) {
            $product = config('payments.models.gateway_products')::where(["plan_id" => $planId, "gateway_code" => "paypal"])->first();
            if ($product != null) {
                return $product->product_id;
            } else {
                return null;
            }
        }
        return null;
    }

    /**
     * Returns provider of Paypal
     */
    public static function getPaypalProvider(): PayPalClient
    {


        if (!config('payments.paypal.enable')) {
            abort(404);
        }
        $currency = config('payments.paypal.currency');
        $site_url = config('app.url');

        $client_id = config('payments.paypal.client_id');
        $client_secret = config('payments.paypal.secret');
        $app_id = config('payments.paypal.app_id');
        $config = [
            'mode'    =>  config('payments.paypal.mode', 'sandbox'),
            'sandbox' => [
                'client_id'         => $client_id,
                'client_secret'     => $client_secret,
                'app_id'            => $app_id,
            ],
            'live' => [
                'client_id'         => $client_id,
                'client_secret'     => $client_secret,
                'app_id'            => $app_id,
            ],
            'payment_action' => 'Sale',
            'currency'       => $currency,
            'notify_url'     => $site_url . '/paypal/notify',
            'locale'         => $currency,
            'validate_ssl'   => config('payments.paypal.mode', 'sandbox')  == 'sandbox' ? false : true,
        ];


        // error_log("getPaypalProvider() => config:\n".json_encode($config));

        $provider = new PayPalClient($config);
        $provider->getAccessToken();

        return $provider;
    }


    public static function deactivateOtherPlans(PayPalClient $provider, $productName)
    {

        $plans = $provider->listPlans();
        if ($plans != null) {
            foreach ($plans['plans'] as $plan) {
                error_log(json_encode($plan));
                // if($plan[])
                // error_log($plan['name']." -> ".$plan['id']);
            }
        } else {
            error_log("deactivateOtherPlans() : List returned null");
        }

        return true;
    }

    /**
     * Create Billing plan in Paypal
     * @param $productId Product ID of the plan
     * @param $productName Name of the plan
     * @param $trials Number of trials
     * @param $currency Currency of the plan
     * @param $interval Interval of the plan
     * @param $price Price of the plan
     */
    public static function createBillingPlanData($productId, $productName, $trials, $currency, $interval = 'MONTH', $interval_count = 1, $price)
    {

        if ($trials == 0) {
            $planData = [
                "product_id"        => $productId,
                "name"              => $productName,
                "description"       => "Billing Plan of " . $productName,
                "status"            => "ACTIVE",
                "billing_cycles"    =>
                [
                    [
                        "frequency" =>
                        [
                            "interval_unit"     => $interval,
                            "interval_count"    => $interval_count
                        ],
                        "tenure_type"       => "REGULAR",
                        "sequence"          => 1,
                        "total_cycles"      => 0,
                        "pricing_scheme"    =>
                        [
                            "fixed_price"   =>
                            [
                                "value"         => strval($price),
                                "currency_code" => $currency
                            ]
                        ]
                    ]
                ],
                "payment_preferences" =>
                [
                    "auto_bill_outstanding" => true,
                    "setup_fee" =>
                    [
                        "value"         => "0",
                        "currency_code" => $currency
                    ],
                    "setup_fee_failure_action"  => "CANCEL",
                    "payment_failure_threshold" => 3
                ]
            ];
        } else {
            $planData = [
                "product_id"        => $productId,
                "name"              => $productName,
                "description"       => "Billing Plan of " . $productName,
                "status"            => "ACTIVE",
                "billing_cycles"    =>
                [
                    [
                        "frequency" =>
                        [
                            "interval_unit"     => 'DAY',
                            "interval_count"    => 1
                        ],
                        "tenure_type"       => "TRIAL",
                        "sequence"          => 1,
                        "total_cycles"      => $trials,
                        "pricing_scheme"    =>
                        [
                            "fixed_price"   =>
                            [
                                "value"         => 0,
                                "currency_code" => $currency
                            ]
                        ]
                    ],
                    [
                        "frequency" =>
                        [
                            "interval_unit"     => $interval,
                            "interval_count"    => $interval_count
                        ],
                        "tenure_type"       => "REGULAR",
                        "sequence"          => 2,
                        "total_cycles"      => 0,
                        "pricing_scheme"    =>
                        [
                            "fixed_price"   =>
                            [
                                "value"         => strval($price),
                                "currency_code" => $currency
                            ]
                        ]
                    ]
                ],
                "payment_preferences" =>
                [
                    "auto_bill_outstanding" => true,
                    "setup_fee" =>
                    [
                        "value"         => "0",
                        "currency_code" => $currency
                    ],
                    "setup_fee_failure_action"  => "CANCEL",
                    "payment_failure_threshold" => 3
                ]
            ];

            // "taxes" => 
            //     [
            //         "percentage" => "0",
            //         "inclusive" => false
            //     ]
        }

        return $planData;
    }


    /**
     * Saves Membership plan product in paypal gateway.
     * @param planId ID of plan in PaymentPlans model.
     * @param productName Name of the product, plain text
     * @param price Price of product
     * @param frequency Time interval of subscription, MONTH / YEAR
     * @param type Type of product subscription/one-time  {o for one-time, s for subscription}
     * @param incomingProvider Paypal provider object
     * @param trials Number of trials
     */
    public static function saveProduct($planId, $productName, $price, $frequency = "MONTH", $type = 's', PayPalClient $incomingProvider = null, $interval_count = 1, $trials = 0)
    {

        try {


            if (!config('payments.paypal.enable')) {
                return abort(404);
            }
            $currency = config('payments.paypal.currency');


            $provider = $incomingProvider ?? self::getPaypalProvider();



            $product = null;



            $oldProductId = null;

            //check if product exists
            $productData = config('payments.models.gateway_products')::where(["plan_id" => $planId, "gateway_code" => "paypal"])->first();
            if (!is_null($productData)) {
                // Create product in every situation. maybe user updated paypal credentials.
                if ($productData->product_id != null) { // && $productName != null
                    //Product has been created before
                    $oldProductId = $productData->product_id;
                } else {
                    //Product has NOT been created before but record exists. Create new product and update record.
                }



                $data =   json_decode('[
                    {
                      "op": "replace",
                      "path": "/description",
                      "value": ' . $productName . '
                    },
                    {
                      "op": "replace",
                      "path": "/name",
                      "value": ' . $productName . '
                    }
                  
                  ]', true);

                $request_id = 'create-product-' . time();

                $newProduct = $provider->updateProduct($productData->product_id, $data);

                // $productData->product_id = $newProduct['id'];
                $productData->plan_name = $productName;
                $productData->save();


                $product = $productData;
            } else {

                $data = [
                    "name"          => $productName,
                    "description"   => $productName,
                    "type"          => "SERVICE",
                    "category"      => "SOFTWARE",
                    // "home_url" => config('app.url'),
                ];

                $request_id = 'create-product-' . time();

                $newProduct = $provider->createProduct($data, $request_id);

                $model = config('payments.models.gateway_products');
                $product = new $model;
                $product->plan_id = $planId;
                $product->plan_name = $productName;
                $product->gateway_code = "paypal";
                $product->gateway_title = "PayPal";
                $product->product_id = $newProduct['id'];
                $product->save();
            }

            //check if price exists
            if (!is_null($product->price_id)) {
                //Price exists - here price_id is plan_id in PayPal ( Billing plans id )

                // One-Time price
                if ($type == "o") {

                    // Paypal handles one time prices with orders, so we do not need to set anything for one-time payments.
                    $product->price_id = __('Not Needed');
                    $product->save();
                } else {
                    // Subscription
                    // Deactivate old billing plan --> Moved to updateUserData()
                    $oldBillingPlanId = $product->price_id;
                    // $oldBillingPlan = $provider->deactivatePlan($oldBillingPlanId);
                    // create new billing plan with new values
                    $interval = $frequency;

                    $planData = self::createBillingPlanData($product->product_id, $productName, $trials, $currency, $interval, $interval_count, $price);

                    // This line is not in docs. but required in execution. Needed ~5 hours to fix.
                    $request_id = 'create-plan-' . time();

                    $billingPlan = $provider->createPlan($planData, $request_id);

                    $product->price_id = $billingPlan['id'];
                    $product->save();
                    $model = config('payments.models.old_gateway_products');
                    $history = new $model;
                    $history->plan_id = $planId;
                    $history->plan_name = $productName;
                    $history->gateway_code = 'paypal';
                    $history->product_id = $product->product_id;
                    $history->old_product_id = $oldProductId;
                    $history->old_price_id = $oldBillingPlanId;
                    $history->new_price_id = $billingPlan['id'];
                    $history->status = 'check';
                    $history->save();

                    $tmp = self::updateUserData();

                    ///////////// To support old entries and prevent update issues on trial and non-trial areas
                    ///////////// update system is cancelled. instead we are going to create new ones, deactivate old ones and replace them.

                }
            } else {
                // price_id is null so we need to create plans

                // One-Time price
                if ($type == "o") {

                    // Paypal handles one time prices with orders, so we do not need to set anything for one-time payments.
                    $product->price_id = __('Not Needed');
                    $product->save();
                } else {
                    // Subscription

                    // to subscribe, first create billing plan. then subscribe with it. so price_id is billing_plan_id
                    // subscribe has different id and logic in paypal

                    $interval = $frequency;

                    $pricing = json_decode('{
                        "pricing_schemes": [
                          {
                            "billing_cycle_sequence": 2,
                            "pricing_scheme": {
                              "fixed_price": {
                                "value": ' . $price . ',
                                "currency_code": ' . $currency . '
                              }
                            }
                          }
                        ]
                      }', true);

                    // convet to array
                    $pricing = json_decode(json_encode($pricing), true);


                    $plan = $provider->updatePlanPricing($product->product_id, $pricing);
                }
            }
        } catch (\Exception $ex) {
            dd($ex->getMessage());
            error_log("PaypalController::saveProduct()\n" . $ex->getMessage());
            return back()->with(['message' => $ex->getMessage(), 'type' => 'error']);
        }
    } // saveProduct()



    /**
     * Used to generate new product id and price id of all saved membership plans in paypal gateway.
     */
    public static function saveAllProducts($plans)
    {
        try {


            if (!config('payments.paypal.enable')) {
                return back()->with(['message' => __('Please enable PayPal'), 'type' => 'error']);
                abort(404);
            }

            // Get all membership plans

            $provider = self::getPaypalProvider();
            foreach ($plans as $plan) {
                // Replaced definitions here. Because if monthly or prepaid words change just updating here will be enough.
                $freq = $plan->invoice_interval == "monthly" || $plan->invoice_interval ==  'month' ? 'MONTH' : 'YEAR'; // m => month | y => year
                // $typ = $plan->type == "prepaid" ? "o" : "s"; // o => one-time | s => subscription
                $typ = "s"; // o => one-time | s => subscription
                self::saveProduct($plan->id, $plan->name, $plan->price, $freq, $typ, $provider, $plan->invoice_period, $plan->trial_period);
            }

            // Create webhook of paypal
            $tmp = self::createWebhook();
        } catch (\Exception $ex) {
            error_log("PaypalController::saveAllProducts()\n" . $ex->getMessage());
            return back()->with(['message' => $ex->getMessage(), 'type' => 'error']);
        }
    }


    /**
     * Displays Payment Page of PayPal gateway for prepaid plans.
     */
    public static function prepaid($planId, $plan, $incomingException = null)
    {


        if (!config('payments.paypal.enable')) {
            abort(404);
        }

        $currency = config('payments.paypal.currency');

        $provider = self::getPaypalProvider();

        $orderId = null;
        $exception = $incomingException;

        try {
            if (self::getPaypalProductId($planId) == null) {
                $exception = "Product ID is not set! Please save Membership Plan again.";
            }
        } catch (\Exception $th) {
            $exception = Str::before($th->getMessage(), ':');
        }

        return view('panel.user.payment.prepaid.payWithPaypal', compact('plan', 'orderId', 'gateway', 'exception', 'currency'));
    }



    static  public function createPayPalOrder($price)
    {

        $provider = self::getPaypalProvider();

        $data = [
            "intent" => "CAPTURE",
            "purchase_units" =>
            [
                [
                    "amount" =>
                    [
                        "currency_code" => config('payments.paypal.currency'),
                        "value" => strval($price)
                    ]
                ]
            ]
        ];

        $order = $provider->createOrder($data);
        return $order;
    }


    public function capturePayPalOrder(Request $request)
    {

        try {
            $orderId = $request->orderID;

            $provider = self::getPaypalProvider();

            $order = $provider->capturePaymentOrder($orderId);
            $model = config('payments.model.order');
            $payment = $model::where('order_id', $orderId)->first();

            if ($payment != null) {

                $payment->status = 'Success';
                $payment->save();
                try {
                    if ($payment->orderable && config('payments.models.plan')) {
                        $plan = config('payments.models.plan')::find($payment->plan_id);
                        if ($sup =  $payment->orderable->planSubscription('main')) {
                            $sup->changePlan($plan);
                        } else {
                            $payment->orderable->newPlanSubscriptionWithOutTrail('main', $plan);
                        }
                    }
                } catch (\Throwable $th) {
                    //throw $th;
                }

                // createActivity($user->id, 'Purchased', $plan->name . ' Token Pack', null);
            } else {
                error_log("PaypalController::capturePayPalOrder(): Could not find required payment order!");
            }

            return $order;
        } catch (\Exception $th) {
            error_log($th->getMessage());
            return Str::before($th->getMessage(), ':');
        }
    }



    /**
     * Displays Payment Page of Stripe gateway.
     */
    public static function subscribe($planId, $plan, $user, $incomingException = null)
    {

        if (!config('payments.paypal.enable')) {
            abort(404);
        }




        $subscriptionId = null;
        $exception = $incomingException;
        $orderId = Str::random(12);
        $productId = self::getPaypalProductId($planId);
        $billingPlanId = self::getPaypalPriceId($planId);

        try {
            if ($productId == null) {
                $exception = "Product ID is not set! Please save Membership Plan again.";
            }

            if ($billingPlanId == null) {
                $exception = "Plan ID is not set! Please save Membership Plan again.";
            }


            if ($exception == null) {
                $payment = config('payments.model.order');
                $payment::create([
                    'order_id' => $orderId,
                    'plan_id' => $planId,
                    'user_id' => $user->id,
                    'payment_type' => 'PayPal',
                    'price' => $plan->price,
                    'affiliate_earnings' => ($plan->price * config('payments.affiliate_commission_percentage')) / 100,
                    'status' => 'Waiting',
                    'country' => $user->country ?? 'Unknown'

                ]);
            }
        } catch (\Exception $th) {
            $exception = Str::before($th->getMessage(), ':');
        }
        return [
            'plan' => $plan,
            'billingPlanId' => $billingPlanId,
            'exception' => $exception,
            'orderId' => $orderId,
            'productId' => $productId,
            'gateway' => 'PayPal',
            'planId' => $planId
        ];

        // return view('panel.user.payment.subscription.payWithPaypal', compact('plan', 'billingPlanId', 'exception', 'orderId', 'productId', 'gateway', 'planId'));
    }



    public static function approvePaypalSubscription(Request $request)
    {

        try {
            $orderId = $request->orderId;
            $paypalSubscriptionID = $request->paypalSubscriptionID;
            $billingPlanId = $request->billingPlanId;
            $productId = $request->productId;
            $planId = $request->planId;

            // return ["result" => "orderId: ".$orderId." | paypalSubscriptionID".$paypalSubscriptionID." | billingPlanId".$billingPlanId." | productId".$productId." | planId".$planId];

            $provider = self::getPaypalProvider();

            $productId = self::getPaypalProductId($planId);

            $plan = config('payments.models.plan')::find($planId);
            $payment = config('payments.model.order')::where('order_id', $orderId)->first();
            $payment->stripe_id = $paypalSubscriptionID;
            $payment->stripe_price = $billingPlanId;

            $user = $payment->orderable;

            if ($payment != null) {
                if ($user && config('payments.models.plan')) {
                    $plan = config('payments.models.plan')::find($payment->plan_id);
                    if ($sup =  $user->planSubscription('main')) {
                        $sup->changePlan($plan);
                    } else {
                        $user->newPlanSubscriptionWithOutTrail('main', $plan);
                    }
                }

                $payment->stripe_status = 'Success';

                $payment->save();

                // $user->remaining_words += $plan->total_words;
                // $user->remaining_images += $plan->total_images;
                // $user->save();

                // createActivity($user->id, 'Subscribed', $plan->name . ' Plan', null);

                return ["result" => "OK"];

                // return redirect()->route('dashboard.index')->with(['message' => 'Thank you for your purchase. Enjoy your remaining words and images.', 'type' => 'success']);

            } else {
                $msg = "PaypalController::approvePaypalSubscription(): Could not find required payment order!";
                error_log($msg);
                return ["result" => $msg];
            }
        } catch (\Exception $th) {
            error_log("PaypalController::approvePaypalSubscription(): " . $th->getMessage());
            return ["result" => Str::before($th->getMessage(), ':')];
            // return Str::before($th->getMessage(),':');
        }

        return ["result" => "Error"];
    }


    /**
     * Cancels current subscription plan
     */
    public static function subscribeCancel($order)
    {



        $provider = self::getPaypalProvider();




        $response = $provider->cancelSubscription($order->stripe_id, 'Not satisfied with the service');

        if ($response == "") {
            $order->stripe_status = "cancelled";
            $order->ends_at = \Carbon\Carbon::now();
            $order->save();
            // createActivity($user->id, 'Cancelled', 'Subscription plan', null);
            return ['message' => 'Your subscription is cancelled succesfully.', 'type' => 'success'];
        } else {
            return ['message' => 'Your subscription could not cancelled.', 'type' => 'error'];
        }


        return ['message' => 'Could not find active subscription. Nothing changed!', 'type' => 'error'];
    }



    public static function getSubscriptionDaysLeft($order)
    {
        $provider = self::getPaypalProvider();


        $subscription = $provider->showSubscriptionDetails($order->stripe_id);
        if (!isset($subscription['error'])) {
            //if user is in trial
            if (isset($subscription['billing_info']['cycle_executions'][0]['tenure_type'])) {
                if ($subscription['billing_info']['cycle_executions'][0]['tenure_type'] == 'TRIAL') {
                    return $subscription['billing_info']['cycle_executions'][0]['cycles_remaining'];
                } else {
                    if (isset($subscription['billing_info']['next_billing_time'])) {
                        return \Carbon\Carbon::now()->diffInDays(\Carbon\Carbon::parse($subscription['billing_info']['next_billing_time']));
                    } else {
                        $order->stripe_status = "cancelled";
                        $order->ends_at = \Carbon\Carbon::now();
                        $order->save();
                        return \Carbon\Carbon::now()->format('F jS, Y');
                    }
                }
            }
        } else {
            error_log("PaypalController::getSubscriptionStatus() :\n" . json_encode($subscription));
        }

        return null;
    }

    public static function checkIfTrial($order)
    {
        $provider = self::getPaypalProvider();

        // Get current active subscription

        $subscription = $provider->showSubscriptionDetails($order->stripe_id);
        if (isset($subscription['error'])) {
            error_log("PaypalController::getSubscriptionStatus() :\n" . json_encode($subscription));
            return back()->with(['message' => 'PayPal Gateway : ' . $subscription['error']['message'], 'type' => 'error']);
        }
        if (isset($subscription['billing_info']['cycle_executions'][0]['tenure_type'])) {
            if ($subscription['billing_info']['cycle_executions'][0]['tenure_type'] == 'TRIAL') {
                return true;
            }
        }

        return false;
    }

    public static function getSubscriptionDetails($order)
    {
        $provider = self::getPaypalProvider();

        return $provider->showSubscriptionDetails($order->stripe_id);
    }



    public static function getSubscriptionRenewDate($order)
    {
        $provider = self::getPaypalProvider();

        $subscription = $provider->showSubscriptionDetails($order->stripe_id);

        if (isset($subscription['error'])) {
            error_log("PaypalController::getSubscriptionStatus() :\n" . json_encode($subscription));
            return back()->with(['message' => 'PayPal Gateway : ' . $subscription['error']['message'], 'type' => 'error']);
        }

        if ($subscription['billing_info']['next_billing_time']) {
            return \Carbon\Carbon::parse($subscription['billing_info']['next_billing_time'])->format('F jS, Y');
        } else {
            $order->stripe_status = "cancelled";
            $order->ends_at = \Carbon\Carbon::now();
            $order->save();
            return \Carbon\Carbon::now()->format('F jS, Y');
        }

        return null;
    }


    /**
     * Checks status directly from gateway and updates database if cancelled or suspended.
     */
    public static function getSubscriptionStatus($order)
    {
        $provider = self::getPaypalProvider();

        $subscription = $provider->showSubscriptionDetails($order->stripe_id);

        if (isset($subscription['error'])) {
            error_log("PaypalController::getSubscriptionStatus() :\n" . json_encode($subscription));
            return back()->with(['message' => 'PayPal Gateway : ' . $subscription['error']['message'], 'type' => 'error']);
        }

        if ($subscription['status'] == 'ACTIVE') {
            return true;
        } else {
            $order->stripe_status = 'cancelled';
            $order->ends_at = \Carbon\Carbon::now();
            $order->save();
            return false;
        }

        return null;
    }


    /**
     * Since price id (billing plan) is changed, we must update user data, i.e cancel current payments.
     */
    public static function updateUserData()
    {

        // $history = Oldconfig('payments.models.gateway_products')::where([
        //     "gateway_code" => 'paypal',
        //     "status" => 'check'
        // ])->get();

        // if ($history != null) {

        //     $provider = self::getPaypalProvider();

        //     foreach ($history as $record) {

        //         // check record current status from gateway
        //         $lookingFor = $record->old_price_id; // billingPlan id in paypal

        //         // if active disable it
        //         $oldBillingPlan = $provider->deactivatePlan($lookingFor);

        //         if ($oldBillingPlan == "") {
        //             //deactivated billing plan from gateway
        //         } else {
        //             error_log("PaypalController::updateUserData():\n" . json_encode($oldBillingPlan));
        //         }

        //         // search subscriptions for record
        //         $subs = SubscriptionsModel::where([
        //             'stripe_status' => 'active',
        //             'stripe_price'  => $lookingFor
        //         ])->get();

        //         if ($subs != null) {
        //             foreach ($subs as $sub) {
        //                 // if found get order id
        //                 $orderId = $sub->stripe_id;

        //                 // cancel subscription order from gateway
        //                 $response = $provider->cancelSubscription($orderId, 'New plan created by admin.');

        //                 // cancel subscription from our database
        //                 $sub->stripe_status = 'cancelled';
        //                 $sub->ends_at = \Carbon\Carbon::now();
        //                 $sub->save();
        //             }
        //         }

        //         $record->status = 'checked';
        //         $record->save();
        //     }
        // }
    }




    public static function cancelSubscribedPlan($planId, $subsId)
    {

        // $user = Auth::user();

        // $provider = self::getPaypalProvider();

        // $currentSubscription = SubscriptionsModel::where('id', $subsId)->first();

        // if ($currentSubscription != null) {
        //     $plan = self::$planModel::where('id', $planId)->first();

        //     $response = $provider->cancelSubscription($currentSubscription->stripe_id, 'Plan deleted by admin.');

        //     if ($response == "") {
        //         $currentSubscription->stripe_status = "cancelled";
        //         $currentSubscription->ends_at = \Carbon\Carbon::now();
        //         $currentSubscription->save();
        //         return true;
        //     }
        // }

        // return false;
    }

    function verifyIncomingJson(Request $request)
    {

        try {

            if (!config('payments.paypal.enable')) {
                return true;
            }
            if (config('payments.paypal.mode') == 'sandbox') {
                // Paypal does not support verification on sandbox mode
                return true;
            }

            if ($request->hasHeader('PAYPAL-AUTH-ALGO') == true) {
                $auth_algo = $request->header('PAYPAL-AUTH-ALGO');
            } else {
                return false;
            }

            if ($request->hasHeader('PAYPAL-CERT-URL') == true) {
                $cert_url = $request->header('PAYPAL-CERT-URL');
            } else {
                return false;
            }

            if ($request->hasHeader('PAYPAL-TRANSMISSION-ID') == true) {
                $transmission_id = $request->header('PAYPAL-TRANSMISSION-ID');
            } else {
                return false;
            }

            if ($request->hasHeader('PAYPAL-TRANSMISSION-SIG') == true) {
                $transmission_sig = $request->header('PAYPAL-TRANSMISSION-SIG');
            } else {
                return false;
            }

            if ($request->hasHeader('PAYPAL-TRANSMISSION-TIME') == true) {
                $transmission_time = $request->header('PAYPAL-TRANSMISSION-TIME');
            } else {
                return false;
            }

            $webhook_event = $request->getContent();
            if ($webhook_event == null) {
                return false;
            }
            if (isJson($webhook_event) == false) {
                return false;
            }


            $webhook_id = setting('paypal.webhook_id');
            if ($webhook_id == null) {
                return false;
            }

            $data = [
                "auth_algo" => $auth_algo,
                "cert_url" => $cert_url,
                "transmission_id" => $transmission_id,
                "transmission_sig" => $transmission_sig,
                "transmission_time" => $transmission_time,
                "webhook_id" => $webhook_id,
                "webhook_event" => $webhook_event
            ];

            $provider = self::getPaypalProvider();

            $response = $provider->verifyWebHook($data);

            if (json_decode($response)->verification_status == 'SUCCESS') {
                return true;
            }
        } catch (\Exception $th) {
            error_log("PaypalController::verifyIncomingJson(): " . $th->getMessage());
        }

        return false;
    }

    public function handleWebhook(Request $request)
    {

        $verified = self::verifyIncomingJson($request);

        if ($verified == true) {

            // Retrieve the JSON payload
            $payload = $request->getContent();

            // Fire the event with the payload
            event(new EventsPaypalWebhookEvent($payload));

            return response()->json(['success' => true]);
        } else {
            // Incoming json is NOT verified
            abort(404);
        }
    }


    public static function createWebhook()
    {

        try {



            $provider = self::getPaypalProvider();



            $webhooks = $provider->listWebHooks();

            if (count($webhooks['webhooks']) > 0) {
                // There is/are webhook(s) defined. Remove existing.
                foreach ($webhooks['webhooks'] as $hook) {
                    $provider->deleteWebHook($hook->id);
                }
            }

            // Create new webhook

            $url = url('/') . '/webhooks/paypal';

            $events = [
                'PAYMENT.SALE.COMPLETED',           // A payment is made on a subscription.
                'BILLING.SUBSCRIPTION.CANCELLED'   // A subscription is cancelled.
            ];
            // 'BILLING.SUBSCRIPTION.EXPIRED',     // A subscription expires.
            // 'BILLING.SUBSCRIPTION.SUSPENDED'    // A subscription is suspended.

            $response = $provider->createWebHook($url, $events);
            setting('paypal.webhook_id', $response->id)->save();
            // $gateway->webhook_id = $response->id;
            // $gateway->save();
        } catch (\Exception $th) {
            error_log("PaypalController::createWebhook(): " . $th->getMessage());
            return back()->with(['message' => $th->getMessage(), 'type' => 'error']);
        }
    }


    /**
     * This is specific to Paypal. Intead of using simulator you can simulate with rest api.
     */
    public static function simulateWebhookEvent()
    {

        $url = url('/') . '/webhooks/paypal';

        $testJson = [
            "event_type" => "PAYMENT.SALE.COMPLETED",
            "url" => $url,
            "resource_version" => "1.0"
        ];

        $provider = self::getPaypalProvider();

        return $provider->simulateWebhookEvent($testJson);
    }
}
