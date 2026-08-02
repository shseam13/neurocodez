<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Markdown -> HTML for blog posts and portfolio case studies.
 *
 * Rendered once when the author saves, into `body_html`. Rendering per request
 * would burn CPU on every visit producing output that only changes when someone
 * edits — and this site is meant to survive a free-tier container.
 */
class MarkdownService
{
    /**
     * Raw HTML in the source is STRIPPED, not escaped and not passed through.
     *
     * Posts are staff-authored, so this is defence in depth rather than the
     * primary control — but an author pasting a snippet from somewhere should
     * never be able to inject a script into a public page.
     */
    private const OPTIONS = [
        'html_input' => 'strip',
        'allow_unsafe_links' => false,
        'max_nesting_level' => 50,
        'renderer' => [
            'soft_break' => "<br />\n",
        ],
    ];

    public function toHtml(?string $markdown): string
    {
        if (blank($markdown)) {
            return '';
        }

        return $this->addHeadingAnchors(Str::markdown($markdown, self::OPTIONS));
    }

    /**
     * Estimate reading time from the Markdown source.
     *
     * Code blocks are stripped first: people scan code rather than reading it
     * word by word, and counting it makes a short tutorial claim 20 minutes.
     */
    public function readingMinutes(?string $markdown): int
    {
        if (blank($markdown)) {
            return 1;
        }

        $prose = preg_replace('/```.*?```/s', '', $markdown) ?? $markdown;
        $prose = preg_replace('/`[^`]*`/', '', $prose) ?? $prose;

        $words = str_word_count(strip_tags($prose));

        return max(1, (int) ceil($words / 200));
    }

    /** First paragraph of prose, for meta descriptions and listing cards. */
    public function excerpt(?string $markdown, int $limit = 160): string
    {
        if (blank($markdown)) {
            return '';
        }

        $text = preg_replace('/```.*?```/s', '', $markdown) ?? $markdown;
        $text = preg_replace('/^#{1,6}\s+.*$/m', '', $text) ?? $text;      // headings
        $text = preg_replace('/!?\[([^\]]*)\]\([^)]*\)/', '$1', $text) ?? $text; // links/images
        $text = preg_replace('/[*_>`#-]/', '', $text) ?? $text;

        return Str::limit(trim(preg_replace('/\s+/', ' ', $text) ?? ''), $limit);
    }

    /**
     * Give headings ids so posts can be deep-linked, and so a table of contents
     * can be generated without a second parse.
     */
    private function addHeadingAnchors(string $html): string
    {
        return preg_replace_callback(
            '/<h([2-4])>(.*?)<\/h\1>/s',
            function (array $m): string {
                $id = Str::slug(strip_tags($m[2]));

                return $id === ''
                    ? $m[0]
                    : sprintf('<h%1$s id="%2$s">%3$s</h%1$s>', $m[1], $id, $m[2]);
            },
            $html
        ) ?? $html;
    }

    /** @return array<int, array{level: int, id: string, text: string}> */
    public function tableOfContents(?string $html): array
    {
        if (blank($html)) {
            return [];
        }

        preg_match_all('/<h([2-3]) id="([^"]+)">(.*?)<\/h\1>/s', $html, $matches, PREG_SET_ORDER);

        return array_map(fn (array $m): array => [
            'level' => (int) $m[1],
            'id' => $m[2],
            'text' => trim(strip_tags($m[3])),
        ], $matches);
    }
}
