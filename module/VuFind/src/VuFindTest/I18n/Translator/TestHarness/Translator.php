<?php

/**
 * Test translator.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2026.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  I18n
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\I18n\Translator\TestHarness;

use Laminas\Translator\TranslatorInterface;

/**
 * Test translator.
 *
 * @category VuFind
 * @package  I18n
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Translator implements TranslatorInterface
{
    /**
     * Translator locale.
     *
     * @var string
     */
    protected string $locale = 'en';

    /**
     * Constructor.
     *
     * @param array $translations Translations
     */
    public function __construct(protected array $translations)
    {
    }

    /**
     * Translate a message.
     *
     * @param string  $message    Message
     * @param string  $textDomain Text domain
     * @param ?string $locale     Locale
     *
     * @return string
     */
    public function translate(
        string $message,
        string $textDomain = self::DEFAULT_TEXT_DOMAIN,
        ?string $locale = null,
    ): string {
        return $this->translations[$textDomain][$message]
            ?? $this->translations['default'][$message]
            ?? $message;
    }

    /**
     * Translate a plural message.
     *
     * @param string  $singular   Singular message
     * @param string  $plural     Plural message
     * @param int     $number     Number to compare
     * @param string  $textDomain Text domain
     * @param ?string $locale     Locale
     *
     * @return string
     */
    public function translatePlural(
        string $singular,
        string $plural,
        int $number,
        string $textDomain = self::DEFAULT_TEXT_DOMAIN,
        ?string $locale = null
    ): string {
        $message = $number === 1 ? $singular : $plural;
        return $this->translate($message, $textDomain, $locale);
    }

    /**
     * Get locale.
     *
     * @return string
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * Set locale.
     *
     * @param string $locale Locale
     *
     * @return void
     */
    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }
}
