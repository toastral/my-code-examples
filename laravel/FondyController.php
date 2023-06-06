<?php

namespace App\Http\Controllers\Payment\Checkout;

use App\{Classes\GeniusMailer,
    Helpers\OrderHelper,
    Models\Cart,
    Models\Fondy\Fondy,
    Models\Fondy\FondyLogFactory,
    Models\Fondy\OrderFondy,
    Models\Fondy\StoreCoreDO,
    Models\Order,
    Models\PaymentGateway,
    Models\Reward,
    Models\User
};
use App\Models\Country;
use App\Models\State;
use Cloudipsp\Exception\ApiException;
use Illuminate\Http\Request;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;


class FondyController extends CheckoutBaseControlller
{
    protected $session;

    public function __construct(SessionManager $session)
    {
        $this->session = $session;
        parent::__construct();
        if (empty($this->curr)) $this->setCurrency(); // в тестах почему то не инициализируется через middleware
    }

    /**
     * auth
     * @param array $input
     * @return void
     */
    public function validateAuth($input)
    {
        if ($input["pass_check"]) {
            $auth = OrderHelper::auth_check($input); // For Authentication Checking
            if (!$auth['auth_success']) {
                throw new \RuntimeException($auth['error_message']);
            }
        }
    }

    /**
     * check cart
     * @param object $session
     * @return void
     */
    public function validateCart($session)
    {
        if (!$session->has('cart')) {
            throw new \RuntimeException("You don't have any product to checkout.");
        }
    }

    /**
     * new cart
     * @param object $cart
     * @return false|string
     */
    public function getOrderTableCart($cart)
    {
        $newCart = [];
        $newCart['totalQty'] = $cart->totalQty;
        $newCart['totalPrice'] = $cart->totalPrice;
        $newCart['items'] = $cart->items;
        $newCart = json_encode($newCart);
        return $newCart;
    }

    /**
     * tax loaction logic
     * @param array $input
     * @param State $state
     * @param Country $country
     * @return null
     */
    public function getTaxLocation(array $input, State $state, Country $country)
    {
        $tax_location = null;
        $inputTax = $input['tax'] ?? null;

        if (empty($inputTax)) {
            return $tax_location;
        }

        $inputTaxType = $input['tax_type'] ?? null;
        if ($inputTaxType == 'state_tax') {
            if ($s = $state->find($inputTax)) {
                $tax_location = $s->state;
            }
            return $tax_location;
        }

        if ($c = $country->find($inputTax)) {
            $tax_location = $c->country_name;
        }
        return $tax_location;
    }

    /**
     * wrapper for StoreMain
     * @param Request $request
     * @return mixed
     */
    public function store(Request $request)
    {
        $redirectData = $this->storeMain($request->all(), $this->session);
        $redirectCascade = redirect();
        isset($redirectData["route"]) ? $redirectCascade = $redirectCascade->route($redirectData["route"]) : "";
        isset($redirectData["to"]) ? $redirectCascade = $redirectCascade->to($redirectData["to"]) : "";
        isset($redirectData["with"]) ? $redirectCascade = $redirectCascade->with($redirectData["with"]) : "";
        return $redirectCascade;
    }

    /**
     * input checking
     * @param $input
     * @return void
     */
    public function validateRequaredInput($input)
    {
        $nonEmptyKeys = ['personal_email', 'wallet_price', "personal_name", "totalQty", "currency_sign", "currency_name", "currency_value", "shipping_cost", "total"];
        foreach ($nonEmptyKeys as $key) {
            if (!isset($input[$key])) {
                throw new \InvalidArgumentException("Empty requaired input value for key: '$key'");
            }
        }
    }

    /**
     * wrapper for StoreCore
     * @param array $input
     * @param object $session
     * @return array
     */
    public function storeMain($input, $session)
    {

        try {
            $this->validateRequaredInput($input);
        } catch (\InvalidArgumentException $e) {
            return ["status" => "err", "route" => "front.checkout", "with" => ['unsuccess' => __('Payment Error. Invalid argument: ' . $e->getMessage())]];
        }

        $input["pass_check"] ??= null;

        try {
            $this->validateAuth($input);
        } catch (\InvalidArgumentException $e) {
            $backUrl = redirect()->back()->getTargetUrl();
            return ["status" => "err", "to" => $backUrl, "with" => ['unsuccess' => __('Auth Error: ' . $e->getMessage())]];
        }

        try {
            $this->validateCart($session);
        } catch (\InvalidArgumentException $e) {
            return ["status" => "err", "route" => 'front.cart', "with" => ['success' => __($e->getMessage())]];
        }

        $inputNewData = [
            "tax_location" => $this->getTaxLocation($input, new State(), new Country()),
            "tax" => $session->get('current_tax') ?? 0,
            "item_name" => $this->gs->title . " Order",
            "item_number" => Str::random(4) . time(),
            "item_amount" => $input["total"] ?? null,
            "txnid" => "FONDY_TXN_" . uniqid(),
            'user_id' => Auth::check() ? Auth::user()->id : NULL,
            // это мыло нужно для pay и order поэтому определяем глобально, здесь
            'customer_email' => $input['customer_email'] ?? $input["personal_email"]
        ];

        $storeCoreDO = new StoreCoreDO();
        $storeCoreDO->input = array_merge($input, $inputNewData);
        $storeCoreDO->currency = $this->curr;
        $storeCoreDO->cart->newCart = $this->getOrderTableCart(new Cart($session->get('cart')));
        $storeCoreDO->cart->itemIds = (new Cart($session->get('cart')))->getItemIds();
        $storeCoreDO->pay->paydata = PaymentGateway::whereKeyword("fondy")->first()->convertAutoData();
        $storeCoreDO->pay->responseUrl = route('front.fondy.response');
        return $this->storeCore($storeCoreDO);
    }

    /**
     * main login processing order
     * @param $storeCoreDO
     * @return array
     */
    public function storeCore($storeCoreDO)
    {
        $input = $storeCoreDO->input;

        $storeDO = (object)[
            "input" => $input,
            "newCart" => $storeCoreDO->cart->newCart,
            "order" => $storeCoreDO->order,
            "currency" => $storeCoreDO->currency
        ];

        $orderId = $this->storeOrder($storeDO); // orderId - может передавать его в pay как-нибудь?
        if (empty($orderId)) {
            return ["status" => "err", "route" => "front.checkout", "with" => ['unsuccess' => __('Payment Error. Empty order_id after insert')]];
        }

        if (isset($input['coupon_id']) && $input['coupon_id'] != "") {
            OrderHelper::coupon_check($input['coupon_id']); // For Coupon Checking
        }

        $payDO = (object)[
            "orderId" => $orderId,
            'itemIds' => $storeCoreDO->cart->itemIds,
            "input" => $input,
            "paydata" => $storeCoreDO->pay->paydata,
            "cloudipsp" => $storeCoreDO->pay->cloudipsp,
            "cloudipspCheckout" => $storeCoreDO->pay->cloudipspCheckout,
            "currency" => $storeCoreDO->currency,
            "responseUrl" => $storeCoreDO->pay->responseUrl,
        ];

        $data = $storeCoreDO->fondy->pay($payDO);
        $fondyLog = $storeCoreDO->fondyLogFactory->create();
        if ($data["response_status"] != "success") {
            $fondyLog->saveUsingData(["type" => "err_checkout", "data" => $data, "description" => "invalid response_status"]);
            return ["status" => "err", "route" => "front.checkout", "with" => ['unsuccess' => __('Payment Declined. Invalid response_status (' . $data["response_status"] . ')')]];
        }
        $fondyLog->saveUsingData(["type" => "ok_checkout", "data" => $data, "order_id" => $orderId, "payment_id" => $data["payment_id"] ?? ""]);
        $storeCoreDO->orderFondy->saveUsingData($orderId, $data);
        return ["status" => "ok", "to" => $data["checkout_url"]];
    }

    /**
     * save to database
     * @param $storeDO
     * @return int
     */
    public function storeOrder($storeDO): int
    {
        $input = $storeDO->input;
        $input['cart'] = $storeDO->newCart;
        $input['pay_amount'] = $input['item_amount'] / $storeDO->currency->value;
        $input['order_number'] = $input['item_number'];
        $input['wallet_price'] = $input['wallet_price'] / $storeDO->currency->value;
        $input['payment_status'] = "Pending";

        $input['customer_email'] ??= $input["personal_email"]; // зто есть в более глобальном контексте, но тут пусть тоже будет
        $input['customer_name'] ??= $input["personal_name"];
        $input['customer_country'] ??= 232; // USA
        $input['customer_phone'] ??= 11111;
        $input['customer_address'] ??= "default address string";
        $input['customer_city'] ??= "default city string";
        $input['customer_state'] ??= "default state string";
        $input['customer_zip'] ??= "default zip string";

        if (!$storeDO->order->fill($input)->save()) {
            return 0;
        }
        return $storeDO->order->id;
    }

    /**
     * response processing
     * @param Request $request
     * @return mixed
     */
    public function response(Request $request)
    {
        $input = $request->all();

        $responseCoreDO = (object)[
            "input" => $input,
            "order" => new Order(),
            "orderFondy" => new OrderFondy(),
            "fondyLogFactory" => new fondyLogFactory(),
            "fondy" => new Fondy(),
            "isUpdateReward" => 0,
            "user" => new User(),
            "reward" => new Reward()
        ];

        $responseCoreResult = $this->responseCore($responseCoreDO);

        $responseCoreDO->orderFondy->updateResponseData($responseCoreDO->input);

        if ($responseCoreResult["status"] == "err") {
            return redirect()->route($responseCoreResult["route"])->with($responseCoreResult["with"]);
        }
        $fondyLog = $responseCoreDO->fondyLogFactory->create();
        $fondyLog->saveUsingData(["type" => "ok_response", "data" => $responseCoreDO->input, "description" => ""]);
        $order = $responseCoreResult["order"];

        // cart
        $this->responseCartManupulation(Session::get('cart'), $responseCoreResult["order"]);

        // mails
        $this->responseSendMails($order);

        return redirect(route('front.payment.return'));
    }

    /**
     * @param Order $order
     * @return void
     */
    public function responseSendMails(Order $order)
    {
        //Sending Email To Buyer
        $data = [
            'to' => $order->customer_email,
            'type' => "new_order",
            'cname' => $order->customer_name,
            'oamount' => "",
            'aname' => "",
            'aemail' => "",
            'wtitle' => "",
            'onumber' => $order->order_number,
        ];

        $mailer = new GeniusMailer();
        $mailer->sendAutoOrderMail($data, $order->id);

        //Sending Email To Admin
        $data = [
            'to' => $this->ps->contact_email,
            'subject' => "New Order Recieved!!",
            'body' => "Hello Admin!<br>Your store has received a new order.<br>Order Number is " . $order->order_number . ".Please login to your panel to check. <br>Thank you.",
        ];
        $mailer = new GeniusMailer();
        $mailer->sendCustomMail($data);
    }

    /**
     * @param Cart $cart
     * @param Order $order
     * @return void
     */
    public function responseCartManupulation($cart, $order)
    {
        OrderHelper::size_qty_check($cart); // For Size Quantiy Checking
        OrderHelper::stock_check($cart); // For Stock Checking
        OrderHelper::vendor_order_check($cart, $order); // For Vendor Order Checking

        Session::put('temporder', $order);
        Session::put('tempcart', $cart);
        Session::forget('cart');
        Session::forget('already');
        Session::forget('coupon');
        Session::forget('coupon_total');
        Session::forget('coupon_total1');
        Session::forget('coupon_percentage');

        if ($order->user_id != 0 && $order->wallet_price != 0) {
            OrderHelper::add_to_transaction($order, $order->wallet_price); // Store To Transactions
        }
    }

    /**
     * @param array $input
     * @param OrderFondy $orderFondy
     * @param $fondyLogFactory
     * @return array
     */
    public function responseCoreValidate($input, $orderFondy, $fondyLogFactory)
    {
        $fondyLog = $fondyLogFactory->create();
        if (!isset($input["payment_id"])) {
            $fondyLog->saveUsingData(["type" => "err_response", "data" => $input, "description" => "empty payment_id"]);
            return ["status" => "err", "route" => "front.checkout", "with" => ['unsuccess' => __('Payment Declined. Invalid post data (empty payment_id)')]];
        }
        $orderStatus = $input["order_status"] ?? "";
        if (empty($orderStatus) || $orderStatus != 'approved') {
            $fondyLog->saveUsingData(["type" => "err_response", "data" => $input, "description" => "not approved"]);
            return ["status" => "err", "route" => "front.checkout", "with" => ['unsuccess' => __('Payment Declined. Order status is not approved. Current status ' . $orderStatus)]];
        }
        $orderFondy = $orderFondy->where('checkout_payment_id', $input["payment_id"])->first();
        if (!$orderFondy || empty($orderFondy->order_id)) {
            $fondyLog->saveUsingData(["type" => "err_response", "data" => $input, "description" => "empty order_id"]);
            return ["status" => "err", "route" => "front.checkout", "with" => ['unsuccess' => __('Not found order_id linked with payment_id: ' . $input["payment_id"])]];
        }
        return ["status" => "ok", "orderId" => $orderFondy->order_id];
    }

    /**
     * @param object $responseCoreDO
     * @return array
     */
    public function responseCore($responseCoreDO)
    {
        $validateResult = $this->responseCoreValidate($responseCoreDO->input, $responseCoreDO->orderFondy, $responseCoreDO->fondyLogFactory);
        if ($validateResult["status"] == "err") {
            return $validateResult;
        }

        $order = $responseCoreDO->order->find($validateResult["orderId"]);
        $order->payment_status = 'Completed';
        $order->status = 'completed';
        $order->update();

        $order->tracks()->create(['title' => 'Pending', 'text' => 'You have successfully placed your order.']);
        $order->notifications()->create();

        if ($responseCoreDO->isUpdateReward && $order->user_id) {
            $user = $responseCoreDO->user->find($order->user_id);
            $this->updateReward($responseCoreDO->reward, $user, $order);
        }

        return ["status" => "ok", "order" => $order];
    }

    /**
     * @param object $reward
     * @param object $user
     * @param Order $order
     * @return void
     */
    public function updateReward($reward, $user, $order)
    {
        $num = $order->pay_amount;
        $rewards = $reward->get();
        foreach ($rewards as $i) {
            $smallest[$i->order_amount] = abs($i->order_amount - $num);
        }

        asort($smallest);
        $final_reword = $reward->where('order_amount', key($smallest))->first();
        $user->update(['reward' => $user->reward + $final_reword->reward]);
    }

    public function setCurrency()
    {
        if (Session::has('currency')) {
            $this->curr = DB::table('currencies')->find(Session::get('currency'));
        } else {
            $this->curr = DB::table('currencies')->where('is_default', '=', 1)->first();
        }
    }
}
