<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ChargeKind;
use App\Enums\ChargeStatus;
use App\Models\Client;
use App\Models\Partner;
use App\Models\PortfolioItem;
use App\Models\Post;
use App\Models\Project;
use App\Models\StageSet;
use App\Models\Tag;
use Illuminate\Database\Seeder;

/**
 * Sample records for working on the UI.
 *
 * Kept as a seeder rather than ad-hoc tinker commands so it can be recreated
 * after a reset instead of being retyped. Never run this on a live database —
 * it only checks for emptiness, not for whether the data is real.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // Each section guards itself, so a partial run can be resumed rather
        // than being blocked by whatever succeeded first time.
        if (Client::query()->exists()) {
            $this->command?->warn('Clients already exist — skipping the business records.');
        } else {
            $this->seedBusiness();
        }

        $this->seedContent();

        $this->command?->info('Demo data seeded.');
    }

    private function seedBusiness(): void
    {
        $karim = Partner::create([
            'name' => 'Karim Hossain',
            'email' => 'karim@example.com',
            'phone' => '+8801711223344',
            'default_commission_percent' => 10,
            'notes' => 'Brings most of the retail work. Pays out monthly.',
        ]);

        Partner::create([
            'name' => 'Shirin Akter',
            'email' => 'shirin@example.com',
            'default_commission_percent' => 15,
        ]);

        $rahim = Client::create([
            'name' => 'Rahim Traders', 'company' => 'Rahim Traders Ltd',
            'email' => 'rahim@example.com', 'phone' => '+8801812345678',
            'partner_id' => $karim->id,
        ]);

        $bazar = Client::create([
            'name' => 'Bazar Bhai', 'email' => 'hello@bazarbhai.com',
            'partner_id' => $karim->id,
        ]);

        $shirinFoods = Client::create(['name' => 'Shirin Foods', 'phone' => '+8801999888777']);

        $set = StageSet::where('is_default', true)->first();

        $portal = Project::create([
            'client_id' => $rahim->id, 'partner_id' => $karim->id,
            'title' => 'Client portal v2', 'agreed_amount' => 50000,
            'stage_set_id' => $set?->id, 'deadline' => now()->addDays(14),
        ]);

        $grocery = Project::create([
            'client_id' => $bazar->id, 'partner_id' => $karim->id,
            'title' => 'Grocery delivery platform', 'agreed_amount' => 120000,
            'stage_set_id' => $set?->id, 'deadline' => now()->subDays(6),
        ]);

        $landing = Project::create([
            'client_id' => $shirinFoods->id, 'title' => 'Landing page',
            'agreed_amount' => 18500, 'stage_set_id' => $set?->id,
        ]);

        $portal->payments()->create(['amount' => 20000, 'paid_at' => now()->subDays(10), 'method' => 'bkash']);
        $grocery->payments()->create(['amount' => 60000, 'paid_at' => now()->subDays(20), 'method' => 'bank']);
        $landing->payments()->create(['amount' => 18500, 'paid_at' => now()->subDays(3), 'method' => 'nagad']);

        $portal->commissionPayouts()->create([
            'partner_id' => $karim->id, 'amount' => 1000,
            'paid_at' => now()->subDays(5), 'method' => 'bkash',
        ]);

        // Mixed charge states, so the pro-rata commission path is exercised.
        $portal->charges()->create([
            'title' => 'Three extra pages',
            'description' => 'Client asked for About, Team and Careers pages.',
            'amount' => 15000, 'kind' => ChargeKind::Extra,
            'status' => ChargeStatus::Approved, 'occurred_at' => now()->subDays(4),
        ]);
        $portal->charges()->create([
            'title' => 'Logo touch-up', 'amount' => 3000,
            'kind' => ChargeKind::Revision, 'status' => ChargeStatus::Quoted,
            'occurred_at' => now()->subDay(),
        ]);
        $portal->charges()->create([
            'title' => 'Server migration', 'amount' => 8000,
            'kind' => ChargeKind::Maintenance, 'status' => ChargeStatus::Approved,
            'commission_applies' => false, 'occurred_at' => now()->subDays(2),
        ]);

    }

    private function seedContent(): void
    {
        if (Post::query()->exists() && PortfolioItem::query()->exists()) {
            $this->command?->warn('Content already exists — skipping posts and portfolio.');

            return;
        }

        if (! Post::query()->exists()) {
            $this->seedPosts();
        }

        if (PortfolioItem::query()->exists()) {
            return;
        }

        PortfolioItem::create([
            'title' => 'Bazar Bhai — grocery delivery platform',
            'summary' => 'A storefront and admin dashboard for a Chattogram grocery delivery service.',
            'body_markdown' => "## The brief

Orders were being taken over WhatsApp and written into a notebook.

## What we built

A customer storefront, a rider app and an admin dashboard, with live order tracking.",
            'client_display_name' => 'Bazar Bhai',
            'tech' => ['Laravel', 'MySQL', 'Tailwind', 'Livewire'],
            'year' => 2025, 'position' => 0, 'is_featured' => true, 'status' => 'published',
        ]);

        PortfolioItem::create([
            'title' => 'ICT School — course platform',
            'summary' => 'Live classes, notes and MCQ exams for HSC ICT students.',
            'body_markdown' => "## Overview

A learning platform with live classes, downloadable notes and timed MCQ exams.",
            'client_display_name' => 'ICT School',
            'tech' => ['Laravel', 'Alpine.js'],
            'year' => 2025, 'position' => 1, 'status' => 'published',
        ]);
    }

    private function seedPosts(): void
    {
        $glass = Post::create([
            'title' => 'Building a glassmorphic UI that stays readable',
            'excerpt' => 'Frosted panels look great in a hero and terrible behind a data table. Here is where the line sits.',
            'body_markdown' => "## Why glass fights data\n\nBlur is beautiful and expensive. `backdrop-filter` costs GPU time **per element**, so a fifty-row payments table with glass rows will drop frames on a mid-range Android.\n\n### The rule\n\nGlass goes on containers. Data sits on solid surfaces.\n\n```css\n.glass { backdrop-filter: blur(16px); }\n.surface { background: var(--surface-solid); }\n```\n\n## Contrast is not optional\n\nOur brand purple measures 3.9:1 on the dark canvas — below the 4.5:1 that body text needs. So it is a fill, never a text colour.",
            'status' => 'published',
            'is_featured' => true,
        ]);
        $glass->tags()->sync([
            Tag::findOrCreateByName('Design')->id,
            Tag::findOrCreateByName('CSS')->id,
        ]);

        $money = Post::create([
            'title' => 'Never store money in a float',
            'excerpt' => 'Why 1.15 becomes 114 poisha, and what to do instead.',
            'body_markdown' => "## The bug everyone ships once\n\n```php\n(int) (1.15 * 100); // 114, not 115\n```\n\nBinary floating point cannot represent 1.15 exactly. Across a few hundred partial payments that error compounds into figures that do not reconcile.\n\n## Store integers\n\nKeep amounts in **minor units** — poisha, cents — as a bigint. Round once, explicitly, and only where a fraction is unavoidable.",
            'status' => 'published',
        ]);
        $money->tags()->sync([Tag::findOrCreateByName('PHP')->id]);

        PortfolioItem::create([
            'title' => 'Bazar Bhai — grocery delivery platform',
            'summary' => 'A storefront and admin dashboard for a Chattogram grocery delivery service.',
            'body_markdown' => "## The brief\n\nOrders were being taken over WhatsApp and written into a notebook.\n\n## What we built\n\nA customer storefront, a rider app and an admin dashboard, with live order tracking.",
            'client_display_name' => 'Bazar Bhai',
            'tech' => ['Laravel', 'MySQL', 'Tailwind', 'Livewire'],
            'year' => 2025, 'position' => 0, 'is_featured' => true, 'status' => 'published',
        ]);

        PortfolioItem::create([
            'title' => 'ICT School — course platform',
            'summary' => 'Live classes, notes and MCQ exams for HSC ICT students.',
            'body_markdown' => "## Overview\n\nA learning platform with live classes, downloadable notes and timed MCQ exams.",
            'client_display_name' => 'ICT School',
            'tech' => ['Laravel', 'Alpine.js'],
            'year' => 2025, 'position' => 1, 'status' => 'published',
        ]);
    }
}
