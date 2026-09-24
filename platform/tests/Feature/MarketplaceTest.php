<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Company;
use App\Models\CrmContact;
use App\Models\Product;
use App\Models\Rfq;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceTest extends TestCase
{
    use RefreshDatabase;

    private function supplier(string $name = 'قیر پارس'): Company
    {
        $user = User::create(['name' => 'فروشنده', 'email' => fake()->unique()->safeEmail(), 'password' => 'password', 'role' => 'supplier', 'phone' => '09120000001']);

        return $user->company()->create(['name' => $name, 'slug' => Company::uniqueSlug($name), 'city' => 'تهران']);
    }

    private function product(Company $company): Product
    {
        $category = Category::firstOrCreate(['slug' => 'bitumen'], ['name' => 'قیر']);

        return $company->products()->create([
            'category_id' => $category->id, 'name' => 'قیر ۶۰/۷۰', 'slug' => Product::uniqueSlug('قیر ۶۰/۷۰'),
            'unit' => 'تن', 'min_order' => 10, 'price_min' => 18_000_000,
        ]);
    }

    private function buyer(): User
    {
        return User::create(['name' => 'خریدار', 'email' => fake()->unique()->safeEmail(), 'password' => 'password', 'role' => 'buyer', 'phone' => '09350000001']);
    }

    public function test_public_pages_render(): void
    {
        $product = $this->product($this->supplier());

        $this->get('/')->assertOk()->assertSee('آسفالت با ما');
        $this->get('/products?q=قیر')->assertOk()->assertSee('قیر ۶۰/۷۰');
        $this->get(route('products.show', $product))->assertOk()->assertSee('۱۸٬۰۰۰٬۰۰۰ تومان');
        $this->get(route('companies.show', $product->company))->assertOk();
        $this->get('/rfq')->assertOk();
    }

    public function test_slugs_keep_persian_letters_and_stay_unique(): void
    {
        $this->supplier('آسفالت البرز');
        $this->assertSame('آسفالت-البرز-2', Company::uniqueSlug('آسفالت البرز'));
    }

    public function test_supplier_registration_creates_company(): void
    {
        $this->post('/register', [
            'role' => 'supplier', 'name' => 'رضا', 'email' => 'r@example.com', 'phone' => '09121112233',
            'password' => 'secret123', 'password_confirmation' => 'secret123', 'company_name' => 'امولسیون نو', 'city' => 'یزد',
        ])->assertRedirect(route('panel.dashboard'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('companies', ['name' => 'امولسیون نو', 'city' => 'یزد', 'is_verified' => false]);
    }

    public function test_product_inquiry_lands_in_inbox_and_crm_without_duplicates(): void
    {
        $product = $this->product($company = $this->supplier());
        $payload = ['name' => 'مهندس کریمی', 'phone' => '09131234567', 'quantity' => 40, 'message' => 'قیمت؟'];

        $this->post(route('inquiries.store', $product), $payload)->assertRedirect();
        $this->post(route('inquiries.store', $product), $payload)->assertRedirect();

        $this->assertSame(2, $company->inquiries()->count());
        $this->assertSame(1, $company->contacts()->count());
        $this->assertDatabaseHas('crm_contacts', ['phone' => '09131234567', 'source' => 'inquiry', 'stage' => 'new']);

        $this->actingAs($company->owner)->get('/panel/inquiries')->assertOk()->assertSee('مهندس کریمی');
    }

    public function test_rfq_quote_accept_flow_updates_crm(): void
    {
        $company = $this->supplier();
        $buyer = $this->buyer();

        $this->actingAs($buyer)->post('/rfq', [
            'title' => '۲۰۰ تن قیر', 'quantity' => 200, 'unit' => 'تن',
            'contact_name' => 'خریدار', 'contact_phone' => '09350000001',
        ])->assertRedirect();
        $rfq = Rfq::firstOrFail();

        $this->actingAs($company->owner)
            ->post(route('panel.quotes.store', $rfq), ['unit_price' => 18_500_000, 'delivery_days' => 7])
            ->assertRedirect();

        $contact = $company->contacts()->firstOrFail();
        $this->assertSame('rfq', $contact->source);
        $this->assertSame(3_700_000_000, (int) $contact->deal_value);

        $quote = $rfq->quotes()->firstOrFail();
        $this->actingAs($buyer)->post(route('panel.buyer.quotes.accept', $quote))->assertRedirect();

        $this->assertSame('closed', $rfq->fresh()->status);
        $this->assertSame('accepted', $quote->fresh()->status);
        $this->assertSame('won', $contact->fresh()->stage);

        // Closed RFQs no longer accept quotes.
        $this->actingAs($this->supplier('دیگری')->owner)
            ->post(route('panel.quotes.store', $rfq), ['unit_price' => 1])
            ->assertStatus(422);
    }

    public function test_buyer_cannot_accept_quotes_on_someone_elses_rfq(): void
    {
        $company = $this->supplier();
        $rfq = Rfq::create(['buyer_id' => $this->buyer()->id, 'title' => 'x', 'quantity' => 1, 'unit' => 'تن', 'contact_name' => 'a', 'contact_phone' => '09350000001']);
        $quote = $rfq->quotes()->create(['company_id' => $company->id, 'unit_price' => 100]);

        $this->actingAs($this->buyer())->post(route('panel.buyer.quotes.accept', $quote))->assertNotFound();
    }

    public function test_crm_pipeline_and_activities(): void
    {
        $company = $this->supplier();
        $this->actingAs($company->owner);

        $this->post('/panel/crm', ['name' => 'شهرداری', 'stage' => 'new', 'deal_value' => 500])->assertRedirect();
        $contact = CrmContact::firstOrFail();

        $this->patch(route('panel.crm.stage', $contact), ['stage' => 'negotiation'])->assertRedirect();
        $this->assertSame('negotiation', $contact->fresh()->stage);

        $this->post(route('panel.crm.activities.store', $contact), ['type' => 'task', 'body' => 'پیگیری', 'due_at' => now()->addDay()->format('Y-m-d\TH:i')]);
        $task = $contact->activities()->where('type', 'task')->firstOrFail();
        $this->patch(route('panel.crm.activities.done', $task))->assertRedirect();
        $this->assertNotNull($task->fresh()->done_at);

        $this->get('/panel/crm')->assertOk()->assertSee('شهرداری');
        $this->get('/panel/crm/tasks')->assertOk()->assertSee('پیگیری');
    }

    public function test_suppliers_cannot_touch_each_others_data(): void
    {
        $mine = $this->supplier('من');
        $theirs = $this->supplier('رقیب');
        $product = $this->product($theirs);
        $contact = $theirs->contacts()->create(['name' => 'مشتری رقیب', 'stage' => 'new']);

        $this->actingAs($mine->owner);
        $this->get(route('panel.products.edit', $product))->assertNotFound();
        $this->delete(route('panel.products.destroy', $product))->assertNotFound();
        $this->get(route('panel.crm.show', $contact))->assertNotFound();
        $this->get('/panel/crm')->assertDontSee('مشتری رقیب');
    }

    public function test_role_gates(): void
    {
        $this->get('/panel')->assertRedirect('/login');
        $this->actingAs($this->buyer())->get('/panel/crm')->assertForbidden();
        $this->get('/panel/admin/companies')->assertForbidden();

        $company = $this->supplier();
        $admin = User::create(['name' => 'ادمین', 'email' => 'a@a.ir', 'password' => 'password', 'role' => 'admin']);
        $this->actingAs($admin)->patch(route('panel.admin.companies.verify', $company))->assertRedirect();
        $this->assertTrue($company->fresh()->is_verified);
    }
}
