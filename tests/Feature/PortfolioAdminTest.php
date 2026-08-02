<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Client;
use App\Models\PortfolioItem;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PortfolioAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        Storage::fake('local');
    }

    private ?User $staff = null;

    private function staff(): User
    {
        return $this->staff ??= User::create([
            'name' => 'Staff', 'email' => 'staff@example.com', 'password' => Hash::make('x'),
            'type' => AccountType::Staff, 'is_active' => true,
        ])->assignRole(User::ROLE_ADMIN);
    }

    private function create(array $overrides = []): PortfolioItem
    {
        $this->actingAs($this->staff())->post('/admin/portfolio', array_merge([
            'title' => 'Bazar Bhai grocery platform',
            'summary' => 'A storefront and admin dashboard.',
            'body_markdown' => "## The brief\n\nOrders were taken over WhatsApp.",
            'status' => 'draft',
            'tech' => 'Laravel, MySQL , Tailwind',
            'year' => 2025,
        ], $overrides));

        return PortfolioItem::latest('id')->firstOrFail();
    }

    #[Test]
    public function a_case_study_is_created_with_a_slug_and_parsed_tech_list(): void
    {
        $item = $this->create();

        $this->assertSame('bazar-bhai-grocery-platform', $item->slug);
        // Comma list becomes a trimmed array so cards can render chips.
        $this->assertSame(['Laravel', 'MySQL', 'Tailwind'], $item->tech);
        $this->assertStringContainsString('<h2 id="the-brief">The brief</h2>', $item->body_html);
    }

    #[Test]
    public function a_draft_case_study_is_not_reachable_publicly(): void
    {
        $item = $this->create(['status' => 'draft']);

        $this->get("/work/{$item->slug}")->assertNotFound();
        $this->get('/work')->assertOk()->assertDontSee('Bazar Bhai grocery platform');
    }

    #[Test]
    public function publishing_makes_it_live(): void
    {
        $item = $this->create(['status' => 'published']);

        $this->assertNotNull($item->published_at);
        $this->get("/work/{$item->slug}")->assertOk()->assertSee('Bazar Bhai grocery platform');
    }

    #[Test]
    public function the_public_page_shows_the_display_name_and_never_the_real_client(): void
    {
        /*
         * The whole reason portfolio_items is a separate table. Linking to a
         * project must not leak the client's real name, the agreed amount or
         * commission terms.
         */
        $client = Client::create(['name' => 'Rahim Traders Private Limited']);
        $project = Project::create([
            'client_id' => $client->id, 'title' => 'Internal project name',
            'agreed_amount' => 987654,
        ]);

        $item = $this->create([
            'status' => 'published',
            'project_id' => $project->id,
            'client_display_name' => 'A grocery delivery startup',
        ]);

        $response = $this->get("/work/{$item->slug}")->assertOk();

        $response->assertSee('A grocery delivery startup');
        $response->assertDontSee('Rahim Traders Private Limited');
        $response->assertDontSee('Internal project name');
        $response->assertDontSee('987,654');
        $response->assertDontSee('9,876.54');
    }

    #[Test]
    public function gallery_images_upload_and_order_contiguously(): void
    {
        $item = $this->create();

        $this->actingAs($this->staff())->post(route('admin.portfolio.images.store', $item), [
            'images' => [
                UploadedFile::fake()->image('one.jpg'),
                UploadedFile::fake()->image('two.jpg'),
                UploadedFile::fake()->image('three.jpg'),
            ],
        ])->assertRedirect();

        $this->assertSame([0, 1, 2], $item->images()->pluck('position')->all());
    }

    #[Test]
    public function moving_an_image_swaps_it_with_its_neighbour(): void
    {
        $item = $this->create();

        $this->actingAs($this->staff())->post(route('admin.portfolio.images.store', $item), [
            'images' => [
                UploadedFile::fake()->image('one.jpg'),
                UploadedFile::fake()->image('two.jpg'),
            ],
        ]);

        [$first, $second] = $item->images()->get()->all();

        $this->actingAs($this->staff())
            ->post(route('admin.portfolio.images.move', [$item, $second]), ['direction' => 'up'])
            ->assertRedirect();

        $this->assertSame($second->id, $item->images()->first()->id);
    }

    #[Test]
    public function an_image_cannot_be_touched_through_a_different_case_study(): void
    {
        $a = $this->create();
        $b = $this->create(['title' => 'Another study']);

        $this->actingAs($this->staff())->post(route('admin.portfolio.images.store', $a), [
            'images' => [UploadedFile::fake()->image('one.jpg')],
        ]);

        $image = $a->images()->firstOrFail();

        $this->actingAs($this->staff())
            ->delete(route('admin.portfolio.images.destroy', [$b, $image]))
            ->assertNotFound();

        $this->assertDatabaseHas('portfolio_images', ['id' => $image->id]);
    }

    #[Test]
    public function removing_an_image_deletes_the_stored_object(): void
    {
        // Unlike project files, a gallery image has no audit value worth keeping.
        $item = $this->create();

        $this->actingAs($this->staff())->post(route('admin.portfolio.images.store', $item), [
            'images' => [UploadedFile::fake()->image('one.jpg')],
        ]);

        $image = $item->images()->firstOrFail();
        $path = $image->path;
        Storage::disk('local')->assertExists($path);

        $this->actingAs($this->staff())
            ->delete(route('admin.portfolio.images.destroy', [$item, $image]))
            ->assertRedirect();

        Storage::disk('local')->assertMissing($path);
    }

    #[Test]
    public function a_case_study_saves_while_models_are_unguarded(): void
    {
        /*
         * Regression: `db:seed` wraps seeders in Model::unguarded(), where
         * isFillable() returns true for every key. The Publishable trait used
         * that as a stand-in for "has a reading_minutes column", so portfolio
         * items tried to write a column that only exists on posts and the
         * insert blew up — but only ever during seeding, never in a request.
         */
        \Illuminate\Database\Eloquent\Model::unguarded(function () {
            $item = PortfolioItem::create([
                'title' => 'Unguarded save',
                'body_markdown' => '## Heading' . PHP_EOL . PHP_EOL . 'Body text.',
                'status' => 'draft',
            ]);

            $this->assertSame('unguarded-save', $item->slug);
            $this->assertStringContainsString('<h2 id="heading">', $item->body_html);
        });

        $this->assertDatabaseHas('portfolio_items', ['title' => 'Unguarded save']);
    }

    #[Test]
    public function portal_accounts_cannot_reach_portfolio_admin(): void
    {
        $user = User::create([
            'name' => 'C', 'email' => 'c@example.com', 'password' => Hash::make('x'),
            'type' => AccountType::Client, 'client_id' => Client::create(['name' => 'A'])->id,
            'is_active' => true,
        ]);

        $this->actingAs($user)->get('/admin/portfolio')->assertRedirect('/portal');
    }

    #[Test]
    public function the_portfolio_admin_pages_render(): void
    {
        $item = $this->create();

        foreach (['/admin/portfolio', '/admin/portfolio/create', route('admin.portfolio.edit', $item)] as $url) {
            $this->actingAs($this->staff())->get($url)->assertOk();
        }
    }
}
