<?php


namespace Tests\Unit\Payment\Checkout\Fondy;

use App\Http\Controllers\Payment\Checkout\FondyController;
use App\Models\Cart;
use App\Models\Country;
use App\Models\Fondy\Fondy;
use App\Models\Order;
use App\Models\PaymentGateway;
use App\Models\State;
use Cloudipsp\Exception\ApiException;
use Illuminate\Session\SessionManager;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use Tests\TestCase;
use Mockery;
use Illuminate\Support\Facades\Session;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

class FondyPayTest extends TestCase
{
    public $app;
    public $out;

    public function __construct()
    {
        parent::__construct();
        $this->app = $this->createApplication();
        $this->out = new Fondy();

    }

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function getPayDO($client_id, $secret_key)
    {
        $payDO = (object)[
            "orderId" => 876,
            'itemIds' => [11, 22, 33],
            "input" => ["item_amount" => 123, "customer_email" => "super_" . rand(1000, 9999) . "@mail.ru"],
            "paydata" => ['client_id' => $client_id, 'secret_key' => $secret_key],
            "cloudipsp" => new \Cloudipsp\Configuration(),
            "cloudipspCheckout" => new \Cloudipsp\Checkout(),
            "currency" => (object)["name" => "USD"],
            "responseUrl" => "https://prompt.shopping/complete.php",
        ];
        return $payDO;
    }


    public function test_pay()
    {
        $client_id = 123456;
        $secret_key = 'test';
        $configMock = Mockery::mock(\Cloudipsp\Configuration::class);
        $configMock->shouldReceive('setMerchantId')->with($client_id)->once();
        $configMock->shouldReceive('setSecretKey')->with($secret_key)->once();

        $data = [
            "checkout_url" => "https://pay.fondy.eu/merchants/d7cee327b6d8d0ea3497dfc25a20e7cbbc9d70d8/default/index.html?token=5ad0baf2eca8fa9d65211dab5750051baf2b0ce0",
            "payment_id" => "566101413",
            "response_status" => "success",
        ];
        $urlMock = \Mockery::mock(\Cloudipsp\Response\Response::class);
        $urlMock->shouldReceive('getData')->andReturn($data);
        $checkoutMock = \Mockery::mock(\Cloudipsp\Checkout::class);
        $checkoutMock->shouldReceive('url')->andReturn($urlMock);
        //$this->app->instance(\Cloudipsp\Checkout::class, $checkoutMock);
        $payDO = $this->getPayDO($client_id, $secret_key);
        $payDO->cloudipsp = $configMock;
        $payDO->cloudipspCheckout = $checkoutMock;

        $this->assertEquals($data, $this->out->pay($payDO));
    }

    public function test_failure()
    {
        $client_id = 123456;
        $secret_key = 'test';
        $payDO = $this->getPayDO($client_id, $secret_key);
        $data = $this->out->pay($payDO);
        $this->assertEquals("failure", $data["response_status"]);
    }

    public function test_success()
    {
        $paydata = PaymentGateway::whereKeyword("fondy")->first()->convertAutoData();
        $payDO = $this->getPayDO($paydata["client_id"], $paydata["secret_key"]);
        $data = $this->out->pay($payDO);
        $this->assertEquals("success", $data["response_status"]);
    }
}
