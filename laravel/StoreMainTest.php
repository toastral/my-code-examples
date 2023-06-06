<?php


namespace Tests\Unit\Payment\Checkout\Fondy;

use App\Helpers\OrderHelper;
use App\Http\Controllers\Payment\Checkout\FondyController;
use App\Models\Cart;
use App\Models\Country;
use App\Models\Fondy\StoreCoreDO;
use App\Models\Order;
use App\Models\PaymentGateway;
use App\Models\State;
use Illuminate\Session\SessionManager;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;
use Mockery;
use Illuminate\Support\Facades\Session;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

class StoreMainTest extends TestCase
{
    public $app;
    public $out;

    public function __construct()
    {
        parent::__construct();
        $this->app = $this->createApplication();
        $this->out = $this->app->make(FondyController::class);
    }

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function makeMockSession()
    {

        $sessionManagerMock = Mockery::mock(SessionManager::class);
        $sessionManagerMock->shouldReceive('get')
            ->with('current_tax')
            ->andReturn(10.0); // Возвращаемое значение для метода get('current_tax')
        $sessionManagerMock->shouldReceive('get')
            ->with('cart')
            ->andReturn(new Cart((object)["items" => [], "totalQty" => 0, "totalPrice" => 0])); // Возвращаемое значение для метода get('cart')
        $sessionManagerMock->shouldReceive('has')
            ->with('cart')
            ->andReturn(true); // Возвращаемое значение для метода has('cart')

        return $sessionManagerMock;
    }

    public function getValidInputData()
    {
        return ['personal_email' => "qwe@qwe.ru", 'wallet_price' => 0, "personal_name" => "weqeerte", "totalQty" => 2, "currency_sign" => "$", "currency_name" => "USD", "currency_value" => 1, "shipping_cost" => 0, "total" => 123];
    }

    public function test_store_core()
    {
        $redirectData = $this->out->storeMain(["aaa" => 1], $this->makeMockSession());
        $this->assertEquals("err", $redirectData["status"]);
        $this->assertEquals("front.checkout", $redirectData["route"]);

        $redirectData = $this->out->storeMain($this->getValidInputData(), $this->makeMockSession());
        $this->assertEquals("ok", $redirectData["status"]);
        $this->assertTrue(isset($redirectData["to"]));
    }
}
