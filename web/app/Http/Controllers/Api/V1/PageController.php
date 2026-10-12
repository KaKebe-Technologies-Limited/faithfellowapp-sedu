<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use DOMDocument;
use DOMElement;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * The legal pages (terms, privacy) as plain blocks the app can draw natively. The text stays in the website's
 * Blade views — the single source — and is read from the rendered page, so the two can never disagree.
 */
class PageController extends Controller
{
    private const PAGES = ['terms' => 'public.terms', 'privacy' => 'public.privacy'];

    public function show(string $page): JsonResponse
    {
        abort_unless(isset(self::PAGES[$page]), 404);

        return response()->json(['data' => Cache::remember('api-page-'.$page.'-'.app()->getLocale(), 600, fn () => $this->parse($page))]);
    }

    /** @return array{slug: string, title: string, updated: ?string, blocks: array<int, array{type: string, text: string}>} */
    protected function parse(string $page): array
    {
        $html = view(self::PAGES[$page])->render();
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);

        $title = trim((string) $dom->getElementsByTagName('h1')->item(0)?->textContent);
        $updated = null;
        $blocks = [];

        foreach ($dom->getElementsByTagName('p') as $p) {
            if (str_contains($p->getAttribute('class'), 'text-ff-muted') && str_starts_with(trim($p->textContent), 'Last updated')) {
                $updated = trim($p->textContent);
                break;
            }
        }

        foreach ($dom->getElementsByTagName('div') as $div) {
            if (! str_contains($div->getAttribute('class'), 'ff-prose')) {
                continue;
            }
            foreach ($div->childNodes as $node) {
                if (! $node instanceof DOMElement) {
                    continue;
                }
                $text = $this->clean($node->textContent);
                if ($text === '') {
                    continue;
                }
                match ($node->tagName) {
                    'h2', 'h3' => $blocks[] = ['type' => 'heading', 'text' => $text],
                    'ul', 'ol' => collect($node->getElementsByTagName('li'))->each(function ($li) use (&$blocks) {
                        $blocks[] = ['type' => 'bullet', 'text' => $this->clean($li->textContent)];
                    }),
                    default => $blocks[] = ['type' => 'paragraph', 'text' => $text],
                };
            }
            break;
        }

        return ['slug' => $page, 'title' => $title, 'updated' => $updated, 'blocks' => $blocks];
    }

    protected function clean(string $text): string
    {
        return trim(preg_replace('/\s+/', ' ', $text));
    }
}
