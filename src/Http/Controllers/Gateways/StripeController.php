<?php

namespace Samehdoush\LaravelPayments\Http\Controllers\Gateways;

use App\Http\Controllers\Controller;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Subscription;
use Laravel\Cashier\Payment;
use Stripe\PaymentIntent;
use Stripe\Plan;
use Stripe\StripeClient;

/**
 * Controls ALL Payment actions of Stripe
 */
class StripeController extends BaseController
{
    /**
     * Reads GatewayProducts table and returns price id of the given plan
     */
    public static function getStripePriceId($planId)
    {


        //check if plan exists

        if (!is_null($planId)) {
            $product = config('payments.models.gateway_products')::where(["plan_id" => $planId, "gateway_code" => "stripe"])->first();
            if ($product != null) {
                return $product->price_id;
            } else {
                return null;
            }
        }
        return null;
    }


    /**
     * Returns provider of Stripe gateway.
     */

    public static function getStripProvider(): StripeClient
    {


        if (!config('payments.stripe.enable')) {
            abort(404);
        }
        $currency = config('payments.stripe.currency');
        $site_url = config('app.url');

        $client_id = config('payments.stripe.publishable');
        $client_secret = config('payments.stripe.secret');


        config(['cashier.key' => $client_id]); //$settings->stripe_key
        config(['cashier.secret' => $client_secret]); //$settings->stripe_secret
        config(['cashier.currency' => $currency]); //currency()->code


        // error_log("getPaypalProvider() => config:\n".json_encode($config));

        $stripe = new \Stripe\StripeClient($client_secret);


        return $stripe;
    }

    /**
     * Displays Payment Page of Stripe gateway.
     */
    public static function subscribe($planId, $plan, $company)
    {

        if (!config('payments.stripe.enable')) {
            abort(404);
        }




        $stripe = self::getStripProvider();



        $currentCustomerIdsArray = [];
        foreach ($stripe->customers->all()->data as $data) {
            array_push($currentCustomerIdsArray, $data->id);
        }

        if (in_array($company->stripe_id, $currentCustomerIdsArray) == false) {

            $userData = [
                "email" => $company->user->email,
                "name" => $company->user->name,
                "phone" => $company->user->phone,
                "address" => [
                    "line1" => $company->user->about,
                    "postal_code" => $company->postal ?? '0000',
                ],
            ];

            $stripeCustomer = $stripe->customers->create($userData);

            $company->stripe_id = $stripeCustomer->id;
            $company->save();
        }


        $intent = null;
        try {
            $intent = $company->createSetupIntent();
            $exception = null;
            if (self::getStripePriceId($planId) == null) {
                $exception = "Stripe product ID is not set! Please save Membership Plan again.";
            }
        } catch (\Exception $th) {
            // $exception = $th;
            $exception = Str::before($th->getMessage(), ':');
        }


        return [
            'intent' => $intent,
            'exception' => $exception,
            'plan' => $plan,
            'gateway' => 'stripe',
        ];
        // return view('panel.user.payment.subscription.payWithStripe', compact('plan', 'intent', 'gateway', 'exception'));
    }


    /**
     * Handles payment action of Stripe.
     * 
     * Subscribe payment page posts here.
     */
    public function subscribePay(Request $request)
    {

        $plan = config('payments.models.plan')::find($request->plan);
        $user = $request->user()->mycompany;


        if (!config('payments.stripe.enable')) {
            abort(404);
        }
        $stripe = self::getStripProvider();



        if (!$user->hasDefaultPaymentMethod()) {
            $user->updateDefaultPaymentMethodFromStripe();
        }

        $planId = $plan->id;

        $productId = self::getStripePriceId($planId);

        self::cancelAllSubscriptions($user);

        if ($plan->trial_days != 0) {
            $subscription = $user->newSubscription('main', $productId)
                ->trialDays((int)$plan->trial_days)
                ->create($request->token);
        } else {
            $subscription = $user->newSubscription('main', $productId)
                ->create($request->token);
        }
        // if ($plan->trial_days != 0) {
        //     $subscription = $user->newSubscription($planId, $productId)
        //         ->trialDays((int)$plan->trial_days)
        //         ->create($request->token);
        // } else {
        //     $subscription = $user->newSubscription($planId, $productId)
        //         ->create($request->token);
        // }





        $subscription->plan_id = $planId;
        $subscription->paid_with = 'stripe';
        $subscription->save();


        $user->orders()->create([
            'order_id' => Str::random(12),
            'plan_id' => $planId,
            'payment_type' => 'Stripe',
            'price' => $plan->price,
            'status' => 'Success',
            'country' => $user->country ?? 'Unknown'

        ]);


        if ($user && config('payments.models.plan')) {
            $plan = config('payments.models.plan')::find($planId);

            if ($sup =  $user->planSubscription('main')) {


                $sup->changePlan($plan);
            } else {
                $user->newPlanSubscription('main', $plan);
            }
            try {
                $user->planSubscription('main')->recordFeatureUsage('limit-user', $user->users()->count(), false);
                $user->planSubscription('main')->recordFeatureUsage('limit-callrecord', $user->callrecords()->count(), false);
                $user->planSubscription('main')->recordFeatureUsage('device', $user->waDevices()->count(), false);
                $user->planSubscription('main')->recordFeatureUsage('space', ($user->scripts()->select('id')->withSum('media', 'size')->get()?->sum('media_sum_size') / (1024 * 100)) ?? 0, false);
            } catch (\Throwable $th) {
                //throw $th;
            }
        }


        return redirect()->route('billing')->with(['message' => 'Thank you for your purchase. Enjoy', 'type' => 'success']);
    }

    /**
     * This function is stripe specific.
     * 
     */
    public function cancelAllSubscriptions($user)
    {
        if (!config('payments.stripe.enable')) {
            abort(404);
        }



        $stripe = self::getStripProvider();





        $allSubscriptions = $stripe->subscriptions->all();
        if ($allSubscriptions != null) {
            // Log::driver('slack')->info('Stripe Subscription Cancelled for ', collect($allSubscriptions)->first()->toArray());
            // Log::driver('slack')->info('Stripe name Subscription Cancelled for ' . collect($allSubscriptions)->first()->name ?? '');
            try {
                $user->subscription('main')?->cancelNow();
            } catch (\Throwable $th) {
                //throw $th;
            }

            // foreach ($allSubscriptions as $subs) {
            //     if ($subs->name != 'undefined' and $subs->name != null) {
            //         $user->subscription($subs->name)->cancelNow();
            //         
            //     }
            // }
        }
    }

    /**
     * Cancels current subscription plan
     */
    public static function subscribeCancel($user)
    {



        if (!config('payments.stripe.enable')) {
            abort(404);
        }

        $stripe = self::getStripProvider();
        $activeSub = $user->subscriptions()->where('stripe_status', 'active')->orWhere('stripe_status', 'trialing')->first();

        if ($activeSub != null) {
            // $plan = config('payments.models.plan')::where('id', $activeSub->plan_id)->first();

            try {
                $user->subscription('main')?->cancelNow();
            } catch (\Throwable $th) {
                //throw $th;
            }

            $user->save();



            return back()->with(['message' => 'Your subscription is cancelled succesfully.', 'type' => 'success']);
        }

        return back()->with(['message' => 'Could not find active subscription. Nothing changed!', 'type' => 'error']);
    }


    /**
     * Displays Payment Page of Stripe gateway for prepaid plans.
     */
    public static function prepaid($planId, $plan, $incomingException = null)
    {

        // if (!config('payments.stripe.enable')) {
        //     abort(404);
        // }


        // $currency = Currency::where('id', $gateway->currency)->first()->code;

        // if ($gateway->mode == 'sandbox') {
        //     config(['cashier.key' => $gateway->sandbox_client_id]);
        //     config(['cashier.secret' => $gateway->sandbox_client_secret]);
        //     config(['cashier.currency' => $currency]);
        // } else {
        //     config(['cashier.key' => $gateway->live_client_id]); //$settings->stripe_key
        //     config(['cashier.secret' => $gateway->live_client_secret]); //$settings->stripe_secret
        //     config(['cashier.currency' => $currency]); //currency()->code
        // }

        // $user = Auth::user();
        // $activesubs = $user->subscriptions()->where('stripe_status', 'active')->orWhere('stripe_status', 'trialing')->get();
        // $intent = null;
        // try {
        //     $intent = auth()->user()->createSetupIntent();
        //     $exception = $incomingException;
        //     if (self::getStripePriceId($planId) == null) {
        //         $exception = "Stripe product ID is not set! Please save Membership Plan again.";
        //     }
        // } catch (\Exception $th) {
        //     $exception = Str::before($th->getMessage(), ':');
        // }

        // return view('panel.user.payment.prepaid.payWithStripe', compact('plan', 'intent', 'gateway', 'exception', 'activesubs'));
    }


    /**
     * Handles payment action of Stripe.
     * 
     * Prepaid payment page posts here.
     */
    public function prepaidPay(Request $request)
    {

        // $plan = PaymentPlans::find($request->plan);
        // $user = Auth::user();
        // $settings = Setting::first();

        // if (!config('payments.stripe.enable')) {
        //     abort(404);
        // }


        // $currency = Currency::where('id', $gateway->currency)->first()->code;

        // if ($gateway->mode == 'sandbox') {
        //     config(['cashier.key' => $gateway->sandbox_client_id]);
        //     config(['cashier.secret' => $gateway->sandbox_client_secret]);
        //     config(['cashier.currency' => $currency]);
        // } else {
        //     config(['cashier.key' => $gateway->live_client_id]); //$settings->stripe_key
        //     config(['cashier.secret' => $gateway->live_client_secret]); //$settings->stripe_secret
        //     config(['cashier.currency' => $currency]); //currency()->code
        // }

        // $paymentMethod = $request->payment_method;

        // try {
        //     $user->createOrGetStripeCustomer();
        //     $user->updateDefaultPaymentMethod($paymentMethod);
        //     $user->charge($plan->price * 100, $paymentMethod);
        // } catch (\Exception $exception) {
        //     return self::prepaid($plan->id, $plan, $incomingException = $exception->getMessage());
        //     // return back()->with('error', $exception->getMessage());
        // }

        // $payment = new Order();
        // $payment->order_id = Str::random(12);
        // $payment->plan_id = $plan->id;
        // $payment->type = 'prepaid';
        // $payment->user_id = $user->id;
        // $payment->payment_type = 'Credit, Debit Card';
        // $payment->price = $plan->price;
        // $payment->affiliate_earnings = ($plan->price * $settings->affiliate_commission_percentage) / 100;
        // $payment->status = 'Success';
        // $payment->country = $user->country ?? 'Unknown';
        // $payment->save();

        // $user->remaining_words += $plan->total_words;
        // $user->remaining_images += $plan->total_images;
        // $user->save();

        // createActivity($user->id, 'Purchased', $plan->name . ' Token Pack', null);

        // return redirect()->route('dashboard.index')->with(['message' => 'Thank you for your purchase. Enjoy your remaining words and images.', 'type' => 'success']);
    }


    /**
     * Saves Membership plan product in stripe gateway.
     * @param planId ID of plan in PaymentPlans model.
     * @param productName Name of the product, plain text
     * @param price Price of product
     * @param frequency Time interval of subscription, month / annual
     * @param type Type of product subscription/one-time
     */
    public static function saveProduct($planId, $productName, $price, $frequency = "MONTH", $type = 's')
    {

        try {

            $price = (int)(((float)$price) * 100); // Must be in cents level for stripe

            if (!config('payments.stripe.enable')) {
                abort(404);
            }

            $stripe =  self::getStripProvider();

            $product = null;
            $oldProductId = null;

            //check if product exists
            $productData = config('payments.models.gateway_products')::where(["plan_id" => $planId, "gateway_code" => "stripe", 'mode' => config('payments.paypal.mode')])->first();
            if ($productData != null) {

                // Create product in every situation. maybe user updated stripe credentials.

                if ($productData->product_id != null && $productName != null) {
                    //Product has been created before
                    $oldProductId = $productData->product_id;
                } else {
                    //Product has not been created before but record exists. Create new product and update record.
                }

                $newProduct = $stripe->products->create(['name' => $productName]);
                $productData->product_id = $newProduct->id;
                $productData->plan_name = $productName;
                $productData->save();

                $product = $productData;
            } else {

                $newProduct = $stripe->products->create(['name' => $productName,]);

                $model = config('payments.models.gateway_products');
                $product = new $model;
                $product->plan_id = $planId;
                $product->plan_name = $productName;
                $product->gateway_code = "stripe";
                $product->gateway_title = "Stripe";
                $product->product_id = $newProduct->id;
                $product->mode = config('payments.stripe.mode');
                $product->save();
            }
            $currency = config('payments.stripe.currency');

            //check if price exists
            if ($product->price_id != null) {
                //Price exists
                // Since stripe api does not allow to update recurring values, we deactivate all prices added to this product before and add a new price object.

                // Deactivate all prices
                foreach ($stripe->prices->all(['product' => $product->product_id]) as $oldPrice) {
                    $stripe->prices->update($oldPrice->id, ['active' => false]);
                }

                // One-Time price
                if ($type == "o") {
                    $updatedPrice = $stripe->prices->create([
                        'unit_amount' => $price,
                        'currency' => $currency,
                        'product' => $product->product_id,
                    ]);
                    $product->price_id = $updatedPrice->id;
                    $product->save();
                } else {
                    // Subscription

                    $oldPriceId = $product->price_id;

                    $updatedPrice = $stripe->prices->create([
                        'unit_amount' => $price,
                        'currency' => $currency,
                        'recurring' => ['interval' => $frequency == "MONTH" ? 'month' : ($frequency == 'DAY' ? 'day' : 'year')],
                        'product' => $product->product_id,
                    ]);
                    $product->price_id = $updatedPrice->id;
                    $product->save();

                    $model = config('payments.models.old_gateway_products');
                    $history = new $model;
                    $history->plan_id = $planId;
                    $history->plan_name = $productName;
                    $history->gateway_code = 'stripe';
                    $history->product_id = $product->product_id;
                    $history->old_product_id = $oldProductId;
                    $history->old_price_id = $oldPriceId;
                    $history->new_price_id = $updatedPrice->id;
                    $history->status = 'check';
                    $history->save();

                    // $tmp = self::updateUserData();
                }
            } else {
                // One-Time price
                if ($type == "o") {
                    $updatedPrice = $stripe->prices->create([
                        'unit_amount' => $price,
                        'currency' => $currency,
                        'product' => $product->product_id,
                    ]);
                    $product->price_id = $updatedPrice->id;
                    $product->save();
                } else {
                    // Subscription
                    $updatedPrice = $stripe->prices->create([
                        'unit_amount' => $price,
                        'currency' => $currency,
                        'recurring' => ['interval' => $frequency == "MONTH" ? 'month' : ($frequency == 'DAY' ? 'day' : 'year')],
                        'product' => $product->product_id,
                    ]);
                    $product->price_id = $updatedPrice->id;
                    $product->save();
                }
            }
        } catch (\Exception $ex) {
            error_log("StripeController::saveProduct()\n" . $ex->getMessage());
            return back()->with(['message' => $ex->getMessage(), 'type' => 'error']);
        }
    }


    /**
     * Used to generate new product id and price id of all saved membership plans in stripe gateway.
     */
    public static function saveAllProducts($companies, $plans)
    {
        try {


            if (!config('payments.stripe.enable')) {
                abort(404);
            }



            $stripe = self::getStripProvider();

            // Create customers if not exist

            $currentCustomerIdsArray = [];
            foreach ($stripe->customers->all()->data as $data) {
                array_push($currentCustomerIdsArray, $data->id);
            }


            foreach ($companies as $aUser) {

                if (in_array($aUser->stripe_id, $currentCustomerIdsArray) == false) {

                    $userData = [
                        "email" => $aUser->email,
                        "name" => $aUser->name . " " . $aUser->surname,
                        "phone" => $aUser->phone,
                        "address" => [
                            "line1" => $aUser->address,
                            "postal_code" => $aUser->postal,
                        ],
                    ];

                    $stripeCustomer = $stripe->customers->create($userData);

                    $aUser->stripe_id = $stripeCustomer->id;
                    $aUser->save();
                }
            }

            // Get all membership plans



            foreach ($plans as $plan) {
                // Replaced definitions here. Because if monthly or prepaid words change just updating here will be enough.
                $freq = $plan->invoice_interval == "monthly" || $plan->invoice_interval ==  'month' ? 'MONTH' : ($plan->invoice_interval == 'day' ? 'DAY' : 'YEAR'); // m => month | y => year

                // $freq = $plan->invoice_interval == "monthly" || $plan->invoice_interval ==  'month' ? 'MONTH' : 'YEAR'; // m => month | y => year
                // $typ = $plan->type == "prepaid" ? "o" : "s"; // o => one-time | s => subscription
                $typ = "s"; // o => one-time | s => subscription

                self::saveProduct($plan->id, $plan->name, $plan->price, $freq, $typ);
            }
        } catch (\Exception $ex) {
            error_log("StripeController::saveAllProducts()\n" . $ex->getMessage());
            return back()->with(['message' => $ex->getMessage(), 'type' => 'error']);
        }
    }



    public static function getSubscriptionDaysLeft($company)
    {

        // $plan = PaymentPlans::find($request->plan);
        $user = $company;


        self::getStripProvider();

        $sub = $user->subscriptions()->where('stripe_status', 'active')->orWhere('stripe_status', 'trialing')->first();
        $activeSub = $sub->asStripeSubscription();

        if ($activeSub->status == 'active') {
            return \Carbon\Carbon::now()->diffInDays(\Carbon\Carbon::createFromTimeStamp($activeSub->current_period_end));
        } else {
            error_log($sub->trial_ends_at);
            return \Carbon\Carbon::now()->diffInDays(\Carbon\Carbon::parse($sub->trial_ends_at));
        }

        // return $activeSub->current_period_end;

    }


    public static function getSubscriptionRenewDate($company)
    {

        // $plan = PaymentPlans::find($request->plan);
        $user = $company;


        if (!config('payments.stripe.enable')) {
            abort(404);
        }

        self::getStripProvider();
        $activeSub = $user->subscriptions()->where('stripe_status', 'active')->orWhere('stripe_status', 'trialing')->first()->asStripeSubscription();

        return \Carbon\Carbon::createFromTimeStamp($activeSub->current_period_end)->format('F jS, Y');
    }

    /**
     * Checks status directly from gateway and updates database if cancelled or suspended.
     */
    public static function getSubscriptionStatus($company)
    {

        // $plan = PaymentPlans::find($request->plan);
        $user = $company;


        if (!config('payments.stripe.enable')) {
            abort(404);
        }

        self::getStripProvider();
        $sub = $user->subscriptions()->where('stripe_status', 'active')->orWhere('stripe_status', 'trialing')->first();
        if ($sub != null) {
            if ($sub->paid_with == 'stripe') {
                $activeSub = $sub->asStripeSubscription();

                if ($activeSub->status == 'active' or $activeSub->status == 'trialing') {
                    return true;
                } else {
                    $activeSub->stripe_status = 'cancelled';
                    $activeSub->ends_at = \Carbon\Carbon::now();
                    $activeSub->save();
                    return false;
                }
            }
        }

        return false;
    }


    public static function checkIfTrial($company)
    {

        // $plan = PaymentPlans::find($request->plan);
        $user = $company;


        if (!config('payments.stripe.enable')) {
            abort(404);
        }


        self::getStripProvider();

        $sub = $user->subscriptions()->where('stripe_status', 'active')->orWhere('stripe_status', 'trialing')->first();
        if ($sub != null) {
            if ($sub->paid_with == 'stripe') {
                // $activeSub = $sub->asStripeSubscription();
                // return $activeSub->onTrial();
                return $user->subscription($sub->name)->onTrial();
            }
        }

        return false;
    }



    /**
     * Since price id is changed, we must update user data, i.e cancel current payments.
     */
    public static function updateUserData($company)
    {

        try {

            $history = config('payments.models.old_gateway_products')::where([
                "gateway_code" => 'stripe',
                "status" => 'check'
            ])->get();

            if ($history != null) {

                $user = $company;

                if (!config('payments.stripe.enable')) {
                    abort(404);
                }

                $stripe = self::getStripProvider();

                foreach ($history as $record) {

                    // check record current status from gateway
                    $lookingFor = $record->old_price_id;

                    // if active disable it
                    if ($lookingFor != 'undefined') {
                        $stripe->prices->update($lookingFor, ['active' => false]);
                    }

                    // search subscriptions for record
                    $subs = config('payments.models.order')::where([
                        'stripe_status' => 'active',
                        'stripe_price'  => $lookingFor
                    ])->get();

                    if ($subs != null) {
                        foreach ($subs as $sub) {
                            // cancel subscription order from gateway
                            $user->subscription('main')->cancelNow();

                            // cancel subscription from our database
                            $sub->stripe_status = 'cancelled';
                            $sub->ends_at = \Carbon\Carbon::now();
                            $sub->save();
                        }
                    }

                    $record->status = 'checked';
                    $record->save();
                }
            }
        } catch (\Exception $th) {
            error_log("StripeController::updateUserData(): " . $th->getMessage());
            return ["result" => Str::before($th->getMessage(), ':')];
            // return Str::before($th->getMessage(),':');
        }
    }



    public static function cancelSubscribedPlan($planId, $company)
    {
        try {
            $user = $company;


            if (!config('payments.stripe.enable')) {
                abort(404);
            }

            $stripe = self::getStripProvider();
            $user->subscription('main')?->cancelNow();
            // $user->subscription($planId)->cancelNow();
            $user->save();

            return true;
        } catch (\Exception $th) {
            error_log("\n------------------------\nStripeController::cancelSubscribedPlan(): " . $th->getMessage() . "\n------------------------\n");
            // return Str::before($th->getMessage(),':');
            return false;
        }
    }



    // Table structure of gatewayproducts
    // $table->integer('plan_id')->default(0);
    // $table->string('plan_name')->nullable();
    // $table->string('gateway_code')->nullable();
    // $table->string('gateway_title')->nullable();
    // $table->string('product_id')->nullable();
    // $table->string('price_id')->nullable();


}
