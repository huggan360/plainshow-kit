<?php

declare(strict_types=1);

namespace PlainShow\Kit;

/**
 * The theme colour: PlainShow orange, or the colour the person chose in the
 * dashboard's Design studio. Like the language it is a cookie for the whole
 * domain (ps_accent), so every PlainShow site shows it; core also keeps it on
 * the account. Put `<html ... <?= Theme::attributes() ?>>` in a layout so the
 * page is drawn in the right colour from the start.
 */
final class Theme
{
    public const COOKIE = 'ps_accent';

    public const DEFAULT = 'orange';

    /** Every theme colour, with its swatch colour (the accent itself). */
    public const ACCENTS = [
        'orange' => '#fc5000',
        'teal' => '#0ea5a0',
        'indigo' => '#6a5cf6',
        'red' => '#e5263f',
        'yellow' => '#f2b705',
    ];

    public static function valid(?string $accent): bool
    {
        return $accent !== null && isset(self::ACCENTS[$accent]);
    }

    /** The theme colour of this request: the cookie when it names a colour, else orange. */
    public static function accent(): string
    {
        $cookie = $_COOKIE[self::COOKIE] ?? null;

        return is_string($cookie) && self::valid($cookie) ? $cookie : self::DEFAULT;
    }

    /** ` data-accent="teal"` for the <html> element (empty for orange, the default). */
    public static function attributes(?string $accent = null): string
    {
        $accent = self::valid($accent) ? $accent : self::accent();

        return $accent === self::DEFAULT ? '' : ' data-accent="' . htmlspecialchars($accent, ENT_QUOTES, 'UTF-8') . '"';
    }
}
