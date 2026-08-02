<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Post;
use App\Models\User;
use App\Models\Video;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContentAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    private ?User $staff = null;

    private function staff(): User
    {
        return $this->staff ??= User::create([
            'name' => 'Staff', 'email' => 'staff@example.com', 'password' => Hash::make('x'),
            'type' => AccountType::Staff, 'is_active' => true,
        ])->assignRole(User::ROLE_ADMIN);
    }

    // ------------------------------------------------------------------ posts

    #[Test]
    public function a_post_is_created_with_a_slug_rendered_html_and_reading_time(): void
    {
        $this->actingAs($this->staff())->post('/admin/posts', [
            'title' => 'Never store money in a float',
            'body_markdown' => "## Why\n\nBinary floating point cannot hold 1.15 exactly.",
            'status' => 'draft',
        ])->assertRedirect();

        $post = Post::firstOrFail();

        $this->assertSame('never-store-money-in-a-float', $post->slug);
        $this->assertStringContainsString('<h2 id="why">Why</h2>', $post->body_html);
        $this->assertGreaterThanOrEqual(1, $post->reading_minutes);
        // Excerpt is generated when left blank so cards are never empty.
        $this->assertNotEmpty($post->excerpt);
    }

    #[Test]
    public function markdown_html_is_stripped_rather_than_rendered(): void
    {
        $this->actingAs($this->staff())->post('/admin/posts', [
            'title' => 'Pasted from somewhere',
            'body_markdown' => "Normal text.\n\n<script>alert('xss')</script>",
            'status' => 'published',
        ]);

        $post = Post::firstOrFail();

        $this->assertStringNotContainsString('<script', $post->body_html);
        $this->assertStringContainsString('Normal text', $post->body_html);
    }

    #[Test]
    public function a_draft_is_not_reachable_on_the_public_site(): void
    {
        $this->actingAs($this->staff())->post('/admin/posts', [
            'title' => 'Work in progress', 'body_markdown' => 'Soon.', 'status' => 'draft',
        ]);

        $post = Post::firstOrFail();

        $this->get("/blog/{$post->slug}")->assertNotFound();
        $this->get('/blog')->assertOk()->assertDontSee('Work in progress');
    }

    #[Test]
    public function a_future_dated_post_stays_hidden_until_its_date(): void
    {
        $this->actingAs($this->staff())->post('/admin/posts', [
            'title' => 'Scheduled piece', 'body_markdown' => 'Later.',
            'status' => 'published', 'published_at' => now()->addWeek()->toDateString(),
        ]);

        $post = Post::firstOrFail();

        $this->assertFalse($post->isPublished());
        $this->get("/blog/{$post->slug}")->assertNotFound();
    }

    #[Test]
    public function publishing_makes_a_post_live(): void
    {
        $this->actingAs($this->staff())->post('/admin/posts', [
            'title' => 'Live one', 'body_markdown' => 'Hello.', 'status' => 'published',
        ]);

        $post = Post::firstOrFail();

        $this->assertNotNull($post->published_at);
        $this->get("/blog/{$post->slug}")->assertOk()->assertSee('Live one');
    }

    #[Test]
    public function tags_are_created_from_a_comma_separated_list(): void
    {
        $this->actingAs($this->staff())->post('/admin/posts', [
            'title' => 'Tagged', 'body_markdown' => 'x', 'status' => 'draft',
            'tags' => 'Laravel, CSS ,  Laravel ',
        ]);

        // Trimmed and de-duplicated.
        $this->assertSame(['CSS', 'Laravel'], Post::firstOrFail()->tags->pluck('name')->sort()->values()->all());
    }

    #[Test]
    public function the_preview_endpoint_returns_the_same_html_the_page_will_show(): void
    {
        $response = $this->actingAs($this->staff())
            ->postJson('/admin/posts/preview', ['body_markdown' => "## Heading\n\nText."])
            ->assertOk();

        $this->assertStringContainsString('<h2 id="heading">Heading</h2>', $response->json('html'));
    }

    #[Test]
    public function the_slug_is_frozen_once_created(): void
    {
        // Changing it would break every existing link and any search ranking.
        $this->actingAs($this->staff())->post('/admin/posts', [
            'title' => 'Original title', 'body_markdown' => 'x', 'status' => 'published',
        ]);

        $post = Post::firstOrFail();

        // Route-model binding uses the slug, not the id — hitting
        // /admin/posts/{id} would 404 and this test would pass vacuously.
        $this->actingAs($this->staff())
            ->put(route('admin.posts.update', $post), [
                'title' => 'Completely different title', 'body_markdown' => 'x', 'status' => 'published',
            ])->assertRedirect();

        $post->refresh();

        // Proves the update actually landed...
        $this->assertSame('Completely different title', $post->title);
        // ...while the slug stayed put, so existing links keep working.
        $this->assertSame('original-title', $post->slug);
    }

    // ----------------------------------------------------------------- videos

    #[Test]
    public function a_video_can_be_added_by_hand_from_a_url(): void
    {
        $this->actingAs($this->staff())->post('/admin/videos', [
            'url' => 'https://www.youtube.com/watch?v=QPLJlmgrNuo',
            'title' => 'Lecture 4',
        ])->assertRedirect();

        $video = Video::firstOrFail();

        $this->assertSame('QPLJlmgrNuo', $video->youtube_id);
        // Marked manual so a later RSS sync leaves the edited title alone.
        $this->assertTrue($video->is_manual);
    }

    #[Test]
    public function a_bad_video_url_is_rejected(): void
    {
        $this->actingAs($this->staff())->post('/admin/videos', [
            'url' => 'https://example.com/not-a-video', 'title' => 'Nope',
        ])->assertSessionHasErrors('url');

        $this->assertSame(0, Video::count());
    }

    #[Test]
    public function hiding_a_video_removes_it_from_the_public_page(): void
    {
        $video = Video::create([
            'youtube_id' => 'QPLJlmgrNuo', 'title' => 'Lecture 4',
            'published_at' => now(), 'is_published' => true,
        ]);

        $this->get('/videos')->assertOk()->assertSee('Lecture 4');

        $this->actingAs($this->staff())->put("/admin/videos/{$video->id}", ['title' => 'Lecture 4']);

        $this->assertFalse($video->fresh()->is_published);
        $this->get('/videos')->assertOk()->assertDontSee('Lecture 4');
    }

    // ------------------------------------------------------------------ leads

    #[Test]
    public function a_contact_form_enquiry_becomes_a_lead(): void
    {
        $this->post('/contact', [
            'name' => 'Rahim', 'email' => 'rahim@example.com',
            'message' => 'I need a website for my shop, please advise.',
        ])->assertRedirect();

        $this->assertDatabaseHas('leads', ['name' => 'Rahim', 'status' => 'new']);
    }

    #[Test]
    public function a_lead_converts_into_a_client_and_is_idempotent(): void
    {
        $lead = Lead::create([
            'name' => 'Rahim Traders', 'email' => 'rahim@example.com',
            'message' => 'Need a site.', 'status' => 'new',
        ]);

        $this->actingAs($this->staff())
            ->post("/admin/leads/{$lead->id}/convert")
            ->assertRedirect();

        $client = Client::where('name', 'Rahim Traders')->firstOrFail();
        $this->assertSame('converted', $lead->fresh()->status);
        $this->assertStringContainsString('Need a site.', $client->notes);

        // Converting twice must not create a second client.
        $this->actingAs($this->staff())->post("/admin/leads/{$lead->id}/convert");
        $this->assertSame(1, Client::where('name', 'Rahim Traders')->count());
    }

    #[Test]
    public function the_honeypot_silently_accepts_and_discards_bot_submissions(): void
    {
        $this->post('/contact', [
            'name' => 'Bot', 'email' => 'bot@example.com',
            'message' => 'Buy cheap things from my website now.',
            'website' => 'http://spam.example',
        ])->assertRedirect();

        // Reported as success so nobody learns the field is a trap.
        $this->assertSame(0, Lead::count());
    }

    // ------------------------------------------------------------ permissions

    #[Test]
    public function portal_accounts_cannot_reach_content_admin(): void
    {
        $user = User::create([
            'name' => 'C', 'email' => 'c@example.com', 'password' => Hash::make('x'),
            'type' => AccountType::Client, 'client_id' => Client::create(['name' => 'A'])->id,
            'is_active' => true,
        ]);

        foreach (['/admin/posts', '/admin/videos', '/admin/leads'] as $url) {
            $this->actingAs($user)->get($url)->assertRedirect('/portal');
        }
    }

    #[Test]
    public function the_content_admin_pages_render(): void
    {
        $staff = $this->staff();

        foreach (['/admin/posts', '/admin/posts/create', '/admin/videos', '/admin/leads'] as $url) {
            $this->actingAs($staff)->get($url)->assertOk();
        }
    }
}
