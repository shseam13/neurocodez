<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\StageSet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Starter pipelines. These are ordinary editable records, not fixtures — the
 * whole point of stage sets is that you build your own.
 *
 * Note the visibility columns: internal stages such as "Code review" and "QA"
 * are hidden from clients and collapse into the previous visible stage, so the
 * client sees steady progress rather than your internal workflow.
 */
class StageSetSeeder extends Seeder
{
    public function run(): void
    {
        if (StageSet::query()->exists()) {
            return;
        }

        $sets = [
            [
                'name' => 'Web Development',
                'description' => 'Full website or web application build.',
                'is_default' => true,
                'stages' => [
                    ['Requirements', null, true, true, false],
                    ['Design', null, true, false, false],
                    ['Development', null, true, true, false],
                    ['Code review', 'In progress', false, false, false],
                    ['QA', 'In progress', false, false, false],
                    ['Client review', 'Your review', true, false, false],
                    ['Delivered', null, true, true, true],
                ],
            ],
            [
                'name' => 'Logo & Brand Design',
                'description' => 'Identity work: logo, palette, brand assets.',
                'is_default' => false,
                'stages' => [
                    ['Brief', null, true, true, false],
                    ['Concepts', null, true, false, false],
                    ['Revisions', null, true, false, false],
                    ['Final files', null, true, true, true],
                ],
            ],
            [
                'name' => 'Maintenance Retainer',
                'description' => 'Ongoing support and small changes.',
                'is_default' => false,
                'stages' => [
                    ['Reported', null, true, false, false],
                    ['In progress', null, true, true, false],
                    ['Resolved', null, true, true, true],
                ],
            ],
        ];

        foreach ($sets as $definition) {
            $set = StageSet::create([
                'name' => $definition['name'],
                'description' => $definition['description'],
                'is_default' => $definition['is_default'],
                'is_active' => true,
            ]);

            foreach ($definition['stages'] as $i => [$name, $clientLabel, $visibleToClient, $visibleToPartner, $isTerminal]) {
                $set->stages()->create([
                    'name' => $name,
                    'client_label' => $clientLabel,
                    'slug' => Str::slug($name),
                    'position' => $i,
                    'is_terminal' => $isTerminal,
                    'visible_to_client' => $visibleToClient,
                    'visible_to_partner' => $visibleToPartner,
                ]);
            }
        }
    }
}
