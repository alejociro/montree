<?php

declare(strict_types=1);

namespace App\Services\Tenant;

final class CustomCssSanitizer
{
    /**
     * Whitelist of allowed CSS property names (no vendor prefixes).
     */
    private const ALLOWED_PROPERTIES = [
        'color', 'background-color', 'background-image', 'background',
        'border', 'border-color', 'border-radius',
        'font-family', 'font-size', 'font-weight', 'line-height', 'letter-spacing', 'text-align',
        'margin', 'padding', 'width', 'height', 'max-width', 'max-height',
        'display', 'flex', 'grid', 'gap', 'opacity', 'transition', 'transform',
    ];

    /**
     * Whitelist of allowed custom CSS properties (variables).
     */
    private const ALLOWED_CSS_VARIABLES = [
        '--primary', '--secondary', '--accent', '--background', '--foreground', '--card',
    ];

    /**
     * Selectors that are NEVER allowed (global scope hijack).
     */
    private const FORBIDDEN_SELECTOR_PATTERNS = [
        '/(^|[\s,])\*/',
        '/(^|[\s,]):root\b/i',
        '/(^|[\s,])html\b/i',
        '/(^|[\s,])body\b/i',
    ];

    /**
     * Dangerous tokens that MUST NEVER appear in custom CSS.
     */
    private const FORBIDDEN_TOKENS = [
        '/expression\s*\(/i',
        '/javascript:/i',
        '/behavior\s*:/i',
        '/@import\b/i',
        '/@font-face\b/i',
    ];

    /**
     * Sanitize a raw CSS string.
     *
     * Returns the cleaned CSS plus the list of removed declarations / blocks
     * so callers can report back to the user.
     *
     * @return array{css: string, removed: array<int, string>}
     */
    public function sanitize(string $css): array
    {
        $removed = [];

        // Strip CSS comments to simplify scanning.
        $stripped = preg_replace('#/\*.*?\*/#s', '', $css) ?? '';

        foreach (self::FORBIDDEN_TOKENS as $pattern) {
            if (preg_match($pattern, $stripped) === 1) {
                $removed[] = trim(preg_match($pattern, $stripped, $m) ? $m[0] : 'forbidden token');

                return ['css' => '', 'removed' => $removed];
            }
        }

        if (preg_match('/url\s*\(\s*([\'"]?)(.*?)\1\s*\)/i', $stripped, $matches) === 1) {
            $url = trim($matches[2]);

            if (! $this->isUrlAllowed($url)) {
                $removed[] = "url($url)";

                return ['css' => '', 'removed' => $removed];
            }
        }

        // Split into rule blocks via simple regex. Not a full CSS parser, but
        // matches `selector { decl; decl; }` at the top level.
        if (preg_match_all('/([^{}]+)\{([^{}]*)\}/s', $stripped, $blocks, PREG_SET_ORDER) === false || $blocks === []) {
            // No blocks (might be empty); fall through to declaration-only path.
            return $this->sanitizeDeclarationsOnly($stripped, $removed);
        }

        $cleaned = [];

        foreach ($blocks as $block) {
            $selector = trim($block[1]);
            $body = $block[2];

            if (! $this->isSelectorAllowed($selector)) {
                $removed[] = "selector: {$selector}";

                continue;
            }

            $cleanedBody = $this->cleanDeclarations($body, $removed);

            if ($cleanedBody === '') {
                continue;
            }

            $cleaned[] = "{$selector} { {$cleanedBody} }";
        }

        return [
            'css' => implode("\n", $cleaned),
            'removed' => $removed,
        ];
    }

    /**
     * @param  array<int, string>  $removed
     * @return array{css: string, removed: array<int, string>}
     */
    private function sanitizeDeclarationsOnly(string $stripped, array $removed): array
    {
        $cleaned = $this->cleanDeclarations($stripped, $removed);

        return ['css' => $cleaned, 'removed' => $removed];
    }

    /**
     * @param  array<int, string>  $removed
     */
    private function cleanDeclarations(string $body, array &$removed): string
    {
        $declarations = array_filter(array_map('trim', explode(';', $body)));
        $kept = [];

        foreach ($declarations as $declaration) {
            if (! str_contains($declaration, ':')) {
                $removed[] = $declaration;

                continue;
            }

            [$rawProp, $value] = array_map('trim', explode(':', $declaration, 2));
            $prop = strtolower($rawProp);

            if (! $this->isPropertyAllowed($prop)) {
                $removed[] = $declaration;

                continue;
            }

            if (! $this->isValueSafe($value)) {
                $removed[] = $declaration;

                continue;
            }

            $kept[] = "{$prop}: {$value}";
        }

        return implode('; ', $kept);
    }

    private function isPropertyAllowed(string $prop): bool
    {
        if (str_starts_with($prop, '--')) {
            return in_array($prop, self::ALLOWED_CSS_VARIABLES, true);
        }

        return in_array($prop, self::ALLOWED_PROPERTIES, true);
    }

    private function isValueSafe(string $value): bool
    {
        foreach (self::FORBIDDEN_TOKENS as $pattern) {
            if (preg_match($pattern, $value) === 1) {
                return false;
            }
        }

        if (preg_match('/url\s*\(\s*([\'"]?)(.*?)\1\s*\)/i', $value, $m) === 1) {
            return $this->isUrlAllowed(trim($m[2]));
        }

        return true;
    }

    private function isUrlAllowed(string $url): bool
    {
        $lower = strtolower(trim($url));

        if ($lower === '') {
            return false;
        }

        if (str_starts_with($lower, 'data:image/svg+xml')) {
            return true;
        }

        if (str_starts_with($lower, 'data:')) {
            return false;
        }

        if (str_starts_with($lower, 'javascript:')) {
            return false;
        }

        return str_starts_with($lower, 'https://') || str_starts_with($lower, '/');
    }

    private function isSelectorAllowed(string $selector): bool
    {
        if ($selector === '') {
            return false;
        }

        foreach (self::FORBIDDEN_SELECTOR_PATTERNS as $pattern) {
            if (preg_match($pattern, $selector) === 1) {
                return false;
            }
        }

        // Allow at-rules like @media only if they wrap allowed selectors.
        // Out of scope for MVP: reject @keyframes / @supports / @media for safety.
        if (str_starts_with($selector, '@')) {
            return false;
        }

        // Restrict to selectors that start with `.tenant-` or a class/id/element
        // limited to safe characters (no attribute selectors with quotes).
        return (bool) preg_match('/^[\.\#a-zA-Z0-9_\- ,>:()]+$/', $selector);
    }
}
