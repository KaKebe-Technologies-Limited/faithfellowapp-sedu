<?php

use Illuminate\Support\Str;

if (! function_exists('ff_money')) {
    /**
     * Format an amount with currency, e.g. "UGX 50,000".
     */
    function ff_money(float|int|string|null $amount, ?string $currency = null): string
    {
        $currency ??= config('faithfellows.currency', 'UGX');
        $decimals = in_array($currency, ['UGX', 'KES', 'TZS', 'RWF'], true) ? 0 : 2;

        return $currency.' '.number_format((float) $amount, $decimals);
    }
}

if (! function_exists('ff_short_number')) {
    /**
     * Compact number formatting: 1.2K, 3.4M.
     */
    function ff_short_number(float|int|null $n): string
    {
        $n = (float) $n;

        return match (true) {
            $n >= 1_000_000_000 => round($n / 1_000_000_000, 1).'B',
            $n >= 1_000_000 => round($n / 1_000_000, 1).'M',
            $n >= 1_000 => round($n / 1_000, 1).'K',
            default => (string) (int) $n,
        };
    }
}

if (! function_exists('ff_initials')) {
    function ff_initials(?string $name): string
    {
        return Str::of($name ?? '?')->explode(' ')->filter()->take(2)
            ->map(fn ($p) => Str::upper(Str::substr($p, 0, 1)))->implode('');
    }
}

if (! function_exists('ff_church_url')) {
    /**
     * Public website URL for a church: custom domain → subdomain.
     */
    function ff_church_url(object $church): string
    {
        if (! empty($church->custom_domain) && ($church->custom_domain_verified ?? false)) {
            return 'https://'.$church->custom_domain;
        }

        $scheme = parse_url(config('app.url'), PHP_URL_SCHEME) ?: 'https';
        $port = parse_url(config('app.url'), PHP_URL_PORT);

        return $scheme.'://'.$church->slug.'.'.config('faithfellows.root_domain').($port ? ':'.$port : '');
    }
}

if (! function_exists('ff_platform_route')) {
    /**
     * Absolute URL to a named route on the main platform domain — used by
     * church websites served from subdomains / custom domains.
     */
    function ff_platform_route(string $name, mixed $parameters = []): string
    {
        return rtrim(config('app.url'), '/').route($name, $parameters, false);
    }
}

if (! function_exists('ff_safe_html')) {
    /**
     * Minimal allow-list sanitizer for rich-text (Quill) content.
     * Strips scripts, event handlers and javascript: URLs.
     */
    function ff_safe_html(?string $html): string
    {
        if (! $html) {
            return '';
        }

        $allowed = '<p><br><strong><b><em><i><u><s><h2><h3><h4><blockquote><ul><ol><li><a><span><img><hr><pre><code>';
        $clean = strip_tags($html, $allowed);
        $clean = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean);
        $clean = preg_replace('/(href|src)\s*=\s*(["\']?)\s*(javascript|data|vbscript):[^"\'>\s]*\2/i', '$1="#"', $clean);
        $clean = preg_replace('/\sstyle\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $clean);

        return $clean;
    }
}

if (! function_exists('plain_text')) {
    /**
     * Rich text (Quill HTML) as plain text for the mobile app: paragraphs and list items keep their
     * line breaks, every tag and entity is gone.
     */
    function plain_text(?string $html): string
    {
        $text = preg_replace('~</(p|div|h[1-6]|li|blockquote)>|<br\s*/?>~i', "\n\n", (string) $html);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5);

        return trim(preg_replace("/[ \t]+\n|\n{3,}/", "\n\n", $text));
    }
}
