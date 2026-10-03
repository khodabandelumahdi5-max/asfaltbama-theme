<?php

namespace Tests\Feature;

use App\Models\CarMake;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PartCategory;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ShopTest extends TestCase
{
    use RefreshDatabase;

    private Vehicle $tucson;

    private Vehicle $corolla;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tucson = CarMake::create(['name' => 'هیوندای', 'slug' => 'hyundai', 'origin' => 'korean'])->vehicles()->create(['model' => 'توسان', 'year_from' => 1394, 'year_to' => 1397]);
        $this->corolla = CarMake::create(['name' => 'تویوتا', 'slug' => 'toyota', 'origin' => 'japanese'])->vehicles()->create(['model' => 'کرولا']);
    }

    private function product(array $attrs = [], ?int $cost = 1_000_000): Product
    {
        $cat = PartCategory::firstOrCreate(['slug' => 'brakes'], ['name' => 'ترمز']);
        $p = Product::create($attrs + [
            'part_category_id' => $cat->id, 'name' => 'لنت جلو', 'slug' => Product::uniqueSlug('لنت جلو'),
            'part_number' => '58101-2S', 'authenticity' => 'aftermarket', 'stock_status' => 'in_stock', 'stock_qty' => 5, 'price' => 1_500_000,
        ]);
        $p->vehicles()->attach($this->tucson);
        if ($cost) {
            $p->offers()->create(['supplier_id' => Supplier::firstOrCreate(['name' => 'بنکدار'])->id, 'cost_price' => $cost]);
        }

        return $p;
    }

    private function staff(string $role): User
    {
        return User::create(['name' => $role, 'email' => "$role@x.ir", 'password' => 'password', 'role' => $role]);
    }

    private function order(Product $product, array $attrs = []): Order
    {
        $customer = Customer::firstOrCreate(['phone' => '09121111111'], ['name' => 'علی']);
        $order = Order::create($attrs + ['customer_id' => $customer->id, 'city' => 'تهران', 'address' => 'x']);
        $order->items()->create(['product_id' => $product->id, 'qty' => 1, 'unit_price' => $product->price, 'unit_cost' => $product->offers()->min('cost_price')]);

        return $order;
    }

    public function test_search_matches_part_number_ignoring_dashes_spaces_and_persian_digits(): void
    {
        $this->product();
        $this->get('/parts?q='.urlencode('۵۸۱۰۱ 2s'))->assertOk()->assertSee('لنت جلو');
        $this->get('/parts?q=99999')->assertOk()->assertDontSee('لنت جلو');
    }

    public function test_choosing_a_vehicle_shows_only_verified_compatible_parts(): void
    {
        $p = $this->product();
        $this->post('/vehicle', ['vehicle_id' => $this->corolla->id])->assertRedirect('/parts');
        $this->get('/parts')->assertDontSee('لنت جلو');
        $this->get(route('parts.show', $p))->assertSee('سازگاری این قطعه با خودروی انتخابی شما تأیید نشده');

        $this->post('/vehicle', ['vehicle_id' => $this->tucson->id]);
        $this->get('/parts')->assertSee('لنت جلو');
        $this->get(route('parts.show', $p))->assertSee('تأیید شده است');
    }

    public function test_product_page_shows_three_distinct_stock_states_and_matching_structured_data(): void
    {
        $onRequest = $this->product(['stock_status' => 'on_request', 'lead_time_days' => 3, 'slug' => 'a']);
        $this->get(route('parts.show', $onRequest))->assertSee('قابل تأمین پس از تأیید')->assertSee('schema.org/BackOrder', false)->assertDontSee('schema.org/InStock', false);

        $out = $this->product(['stock_status' => 'out_of_stock', 'slug' => 'b']);
        $this->get(route('parts.show', $out))->assertSee('خبرم کن موجود شد')->assertSee('schema.org/OutOfStock', false);
        $this->post(route('cart.add', $out))->assertStatus(422);
    }

    public function test_checkout_creates_order_awaiting_confirmation_with_shipping_and_customer_file(): void
    {
        $p = $this->product();
        $this->post(route('cart.add', $p), ['qty' => 2]);
        $this->get('/checkout?city=کرج')->assertOk()->assertSee('۱۶۰٬۰۰۰ تومان');

        $response = $this->post('/checkout', [
            'name' => 'علی', 'phone' => '09121111111', 'type' => 'garage', 'city' => 'کرج', 'address' => 'کرج',
            'vehicle_id' => $this->tucson->id, 'year' => 1395, 'vin' => 'KMHJ381', 'source' => 'instagram', 'marketing_consent' => 1,
        ]);

        $order = Order::with('items', 'customer', 'customerVehicle')->firstOrFail();
        $response->assertRedirect();
        $this->assertSame('awaiting_confirmation', $order->status);
        $this->assertSame(160_000, $order->shipping_charge);
        $this->assertSame(3_000_000 + 160_000, $order->total());
        $this->assertSame(1_000_000, $order->items->first()->unit_cost);
        $this->assertSame('garage', $order->customer->type);
        $this->assertTrue($order->customer->marketing_consent);
        $this->assertSame('KMHJ381', $order->customerVehicle->vin);

        // The customer's tracking page must not leak the VIN or costs.
        $this->get(URL::signedRoute('orders.show', $order))->assertOk()->assertDontSee('KMHJ381')->assertDontSee('1,000,000');
        $this->get(route('orders.show', $order))->assertForbidden(); // unsigned
    }

    public function test_stock_alert_is_not_duplicated(): void
    {
        $p = $this->product(['stock_status' => 'out_of_stock']);
        $this->post(route('parts.alert', $p), ['name' => 'حمید', 'phone' => '09125555555']);
        $this->post(route('parts.alert', $p), ['name' => 'حمید', 'phone' => '09125555555']);
        $this->assertSame(1, $p->alerts()->count());
    }

    public function test_fitment_request_captures_customer_without_duplicates(): void
    {
        foreach ([1, 2] as $_) {
            $this->post('/fitment', ['name' => 'رضا', 'phone' => '09123333333', 'vehicle_text' => 'هاوال H6', 'description' => 'آینه'])->assertRedirect('/fitment/thanks');
        }
        $this->assertSame(1, Customer::count());
        $this->assertSame(2, Customer::first()->fitmentRequests()->count());
    }

    public function test_sales_cannot_sell_below_floor_but_manager_can(): void
    {
        $p = $this->product();
        $order = $this->order($p);
        $item = $order->items->first();
        $payload = fn ($price, $discount = 0) => ['items' => [$item->id => ['qty' => 1, 'unit_price' => $price]], 'discount' => $discount, 'shipping_charge' => 0];

        $this->actingAs($this->staff('sales'))->put(route('admin.orders.update', $order), $payload(900_000))->assertSessionHasErrors("items.$item->id.unit_price");
        $this->put(route('admin.orders.update', $order), $payload(1_500_000, 600_000))->assertSessionHasErrors('discount');
        $this->put(route('admin.orders.update', $order), $payload(1_200_000))->assertSessionHasNoErrors();

        $this->actingAs($this->staff('manager'))->put(route('admin.orders.update', $order), $payload(900_000))->assertSessionHasNoErrors();
        $this->assertSame(900_000, $item->fresh()->unit_price);
    }

    public function test_confirmation_requires_fitment_check_and_promised_date(): void
    {
        $p = $this->product(['requires_fitment_check' => true]);
        $order = $this->order($p);
        $tech = $this->staff('technical');

        $this->actingAs($tech)->patch(route('admin.orders.status', $order), ['status' => 'confirmed'])->assertSessionHasErrors('status');

        $this->put(route('admin.orders.update', $order), [
            'items' => [], 'discount' => 0, 'shipping_charge' => 0, 'fitment_confirmed' => 1,
            'fitment_note' => 'شماره فنی تطبیق شد', 'promised_at' => now()->addDay()->format('Y-m-d\TH:i'),
        ]);
        $this->patch(route('admin.orders.status', $order), ['status' => 'confirmed'])->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame('confirmed', $order->status);
        $this->assertSame($tech->id, $order->fitment_checked_by);
        $this->patch(route('admin.orders.status', $order), ['status' => 'delivered'])->assertStatus(422); // skipping steps
        $this->patch(route('admin.orders.status', $order), ['status' => 'cancelled'])->assertSessionHasErrors('cancel_reason');
    }

    public function test_paying_decrements_stock_and_shipping_requires_tracking_code(): void
    {
        $p = $this->product();
        $order = $this->order($p, ['status' => 'confirmed', 'promised_at' => now()->addDay()]);
        $this->actingAs($this->staff('shipping'));

        $this->patch(route('admin.orders.status', $order), ['status' => 'paid']);
        $this->assertSame(4, $p->fresh()->stock_qty);
        $this->patch(route('admin.orders.status', $order), ['status' => 'shipped'])->assertSessionHasErrors('tracking_code');
        $this->patch(route('admin.orders.status', $order), ['status' => 'shipped', 'tracking_code' => 'TPX-1'])->assertSessionHasNoErrors();
    }

    public function test_contribution_subtracts_purchase_and_variable_costs(): void
    {
        $order = $this->order($this->product(), [
            'shipping_charge' => 120_000, 'discount' => 100_000, 'shipping_cost' => 110_000, 'inbound_cost' => 50_000,
            'packaging_cost' => 25_000, 'payment_fee' => 15_000, 'acquisition_cost' => 100_000, 'return_reserve' => 30_000,
        ]);
        // 1,500,000 − 100,000 + 120,000 = 1,520,000 paid; − 1,000,000 cost; − 330,000 variable.
        $this->assertSame(1_520_000, $order->total());
        $this->assertSame(190_000, $order->contribution());

        $order->items()->update(['unit_cost' => null]);
        $this->assertNull($order->fresh()->contribution());
    }

    public function test_costs_and_report_are_manager_only(): void
    {
        $order = $this->order($this->product(cost: 1_234_567));
        $this->actingAs($this->staff('sales'))->get(route('admin.orders.show', $order))->assertOk()->assertDontSee('۱٬۲۳۴٬۵۶۷');
        $this->get(route('admin.report'))->assertForbidden();

        $this->actingAs($this->staff('manager'))->get(route('admin.orders.show', $order))->assertSee('1234567');
        $this->get(route('admin.report'))->assertOk();
        $this->get('/admin')->assertOk();
    }

    public function test_reviews_only_from_delivered_orders_and_published_only_with_consent(): void
    {
        $order = $this->order($this->product(), ['status' => 'shipped']);
        $this->post(URL::signedRoute('orders.review', $order), ['rating' => 5])->assertForbidden();

        $order->update(['status' => 'delivered', 'delivered_at' => now()]);
        $this->post(URL::signedRoute('orders.review', $order), ['rating' => 4, 'body' => 'خوب'])->assertRedirect();

        $this->actingAs($this->staff('manager'))->patch(route('admin.reviews.toggle', $order->review))->assertStatus(422);
        $this->get('/')->assertDontSee('خوب');
    }

    public function test_installed_photo_needs_vehicle_caption(): void
    {
        $p = $this->product();
        $this->actingAs($this->staff('sales'))
            ->post(route('admin.products.images.store', $p), ['kind' => 'installed', 'url' => 'https://example.com/a.jpg'])
            ->assertSessionHasErrors('caption');
    }

    public function test_guests_are_sent_to_staff_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }
}
