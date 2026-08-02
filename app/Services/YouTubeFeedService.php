<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use SimpleXMLElement;

/**
 * Reads a YouTube channel's public RSS feed.
 *
 * RSS rather than the Data API on purpose: no API key, no quota, no Google
 * project to maintain, and nothing secret to leak. The trade-off is that the
 * feed returns only the latest ~15 uploads with no view counts or durations —
 * older videos are pinned manually instead.
 */
class YouTubeFeedService
{
    private const FEED = 'https://www.youtube.com/feeds/videos.xml?channel_id=';

    /**
     * Resolve whatever the user configured into a channel id.
     *
     * Accepts a raw `UC…` id, an `@handle`, or any channel URL — because
     * nobody knows their own UC id off the top of their head, but everyone
     * knows their handle.
     */
    public function resolveChannelId(string $input): string
    {
        $input = trim($input);

        if (str_starts_with($input, 'UC') && strlen($input) === 24) {
            return $input;
        }

        return Cache::remember(
            'youtube:channel-id:'.md5($input),
            now()->addDays(30),
            function () use ($input) {
                $url = match (true) {
                    str_starts_with($input, 'http') => $input,
                    str_starts_with($input, '@') => "https://www.youtube.com/{$input}",
                    default => "https://www.youtube.com/@{$input}",
                };

                $response = Http::timeout(15)
                    ->withHeaders(['Accept-Language' => 'en'])
                    ->get($url);

                if (! $response->successful()) {
                    throw new RuntimeException("Could not load channel page: {$url}");
                }

                if (preg_match('/"channelId":"(UC[\w-]{22})"/', $response->body(), $m)) {
                    return $m[1];
                }

                if (preg_match('~<link rel="canonical" href="https://www\.youtube\.com/channel/(UC[\w-]{22})"~', $response->body(), $m)) {
                    return $m[1];
                }

                throw new RuntimeException(
                    "Could not find a channel id at {$url}. Paste the UC… id from ".
                    'https://www.youtube.com/account_advanced instead.'
                );
            }
        );
    }

    /**
     * Fetch and parse the feed.
     *
     * @return array<int, array{youtube_id: string, title: string, description: ?string, published_at: ?string, thumbnail_url: ?string}>
     */
    public function fetch(string $channelInput): array
    {
        $channelId = $this->resolveChannelId($channelInput);

        $response = Http::timeout(20)->retry(2, 500)->get(self::FEED.$channelId);

        if (! $response->successful()) {
            throw new RuntimeException("YouTube feed returned HTTP {$response->status()}.");
        }

        return $this->parse($response->body());
    }

    /** @return array<int, array<string, mixed>> */
    public function parse(string $xml): array
    {
        // Guard against XXE. Untrusted-ish XML from the network should never be
        // able to pull in local files.
        $previous = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_use_internal_errors($previous);

        if ($doc === false) {
            throw new RuntimeException('YouTube feed was not valid XML.');
        }

        $videos = [];

        foreach ($doc->entry ?? [] as $entry) {
            $yt = $entry->children('http://www.youtube.com/xml/schemas/2015');
            $media = $entry->children('http://search.yahoo.com/mrss/');

            $id = (string) ($yt->videoId ?? '');
            if ($id === '') {
                continue;
            }

            $group = $media->group ?? null;
            $thumbnail = $group?->thumbnail?->attributes()?->url;

            $videos[] = [
                'youtube_id' => $id,
                'title' => trim((string) $entry->title),
                'description' => $group ? trim((string) $group->description) : null,
                'published_at' => (string) $entry->published ?: null,
                'thumbnail_url' => $thumbnail ? (string) $thumbnail : null,
            ];
        }

        return $videos;
    }
}
