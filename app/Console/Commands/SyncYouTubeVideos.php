<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Video;
use App\Services\YouTubeFeedService;
use Illuminate\Console\Command;
use Throwable;

class SyncYouTubeVideos extends Command
{
    protected $signature = 'youtube:sync {--channel= : Channel id, @handle or URL (defaults to config)}';

    protected $description = 'Mirror the latest videos from the YouTube channel RSS feed';

    public function handle(YouTubeFeedService $feed): int
    {
        $channel = $this->option('channel') ?: config('neuro.youtube.channel');

        if (blank($channel)) {
            $this->components->error('No channel configured. Set NEURO_YOUTUBE_CHANNEL in .env (an @handle is fine).');

            return self::FAILURE;
        }

        try {
            $videos = $feed->fetch($channel);
        } catch (Throwable $e) {
            // Never fatal: the site reads the local table, so a YouTube outage
            // leaves the existing videos on display rather than an empty page.
            $this->components->error("Sync failed: {$e->getMessage()}");
            report($e);

            return self::FAILURE;
        }

        $created = 0;
        $updated = 0;

        foreach ($videos as $i => $data) {
            $video = Video::firstOrNew(['youtube_id' => $data['youtube_id']]);
            $exists = $video->exists;

            // Hand-curated entries keep their edited title, description and
            // ordering — the sync must not overwrite deliberate editorial work.
            if ($video->is_manual) {
                $video->synced_at = now();
                $video->save();

                continue;
            }

            $video->fill([
                'title' => $data['title'],
                'description' => $data['description'],
                'published_at' => $data['published_at'],
                'thumbnail_url' => $data['thumbnail_url'],
                'position' => $i,
                'synced_at' => now(),
            ]);

            if (! $exists) {
                $video->is_published = true;
            }

            $video->save();

            $exists ? $updated++ : $created++;
        }

        $this->components->info("YouTube sync complete: {$created} new, {$updated} updated.");

        return self::SUCCESS;
    }
}
