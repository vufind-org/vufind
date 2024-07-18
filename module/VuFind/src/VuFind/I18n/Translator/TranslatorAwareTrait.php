<?php

/**
 * Reusable implementation of TranslatorAwareInterface.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Translator
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\I18n\Translator;

use Laminas\I18n\Translator\TranslatorInterface;

use function count;
use function is_array;
use function is_callable;
use function is_string;

/**
 * Reusable implementation of TranslatorAwareInterface.
 *
 * @category VuFind
 * @package  Translator
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
trait TranslatorAwareTrait
{
    /**
     * Translator
     *
     * @var \Laminas\I18n\Translator\TranslatorInterface
     */
    protected $translator = null;

    /**
     * Set a translator
     *
     * @param TranslatorInterface $translator Translator
     *
     * @return TranslatorAwareInterface
     */
    public function setTranslator(TranslatorInterface $translator)
    {
        $this->translator = $translator;
        return $this;
    }

    /**
     * Get translator object.
     *
     * @return \Laminas\I18n\Translator\TranslatorInterface
     */
    public function getTranslator()
    {
        return $this->translator;
    }

    /**
     * Get the locale from the translator.
     *
     * @param string $default Default to use if translator absent.
     *
     * @return string
     */
    public function getTranslatorLocale($default = 'en')
    {
        return null !== $this->translator
            && is_callable([$this->translator, 'getLocale'])
            ? $this->translator->getLocale()
            : $default;
    }

    /**
     * Build a debug-mode translation
     *
     * @param string $domain Text domain
     * @param string $str    String to translate
     * @param array  $tokens Tokens to inject into the translated string
     *
     * @return string
     */
    protected function getDebugTranslation(string $domain, string $str, array $tokens): string
    {
        $targetString = $domain !== 'default' ? "$domain::$str" : $str;
        $keyValueToString = function ($key, $val) {
            return "$key = $val";
        };
        $tokenDetails = empty($tokens)
            ? ''
            : ' | [' .
            implode(', ', array_map($keyValueToString, array_keys($tokens), array_values($tokens))) .
            ']';
        return "*$targetString$tokenDetails*";
    }

    /**
     * Translate a string (or string-castable object)
     *
     * @param string|object|array $target          String to translate or an array of text
     *                                             domain and string to translate
     * @param array               $tokens          Tokens to inject into the translated string
     * @param string              $default         Default value to use if no translation is
     *                                             found (null for no default).
     * @param bool                $useIcuFormatter Should we use an ICU message formatter instead
     * of the default behavior?
     * @param string[]            $fallbackDomains Text domains to check if no match is found in
     * the domain specified in $target
     *
     * @return string
     */
    public function translate(
        $target,
        $tokens = [],
        $default = null,
        $useIcuFormatter = false,
        $fallbackDomains = []
    ) {
        // Figure out the text domain for the string:
        [$domain, $str] = $this->extractTextDomain($target);

        if ($this->getTranslatorLocale() == 'debug') {
            return $this->getDebugTranslation($domain, $str, $tokens);
        }

        // Special case: deal with objects with a designated display value:
        if ($str instanceof \VuFind\I18n\TranslatableStringInterface) {
            if (!$str->isTranslatable()) {
                return $str->getDisplayString();
            }
            // On this pass, don't use the $default, since we want to fail over
            // to getDisplayString before giving up:
            $translated = $this
                ->translateString((string)$str, $tokens, null, $domain, $useIcuFormatter);
            if ($translated !== (string)$str) {
                return $translated;
            }
            // Override $domain/$str using getDisplayString() before proceeding:
            $str = $str->getDisplayString();
            // Also the display string can be a TranslatableString. This makes it
            // possible have multiple levels of translatable values while still
            // providing a sane default string if translation is not found. Used at
            // least with hierarchical facets where translation key can be the exact
            // facet value (e.g. "0/Book/") or a displayable value (e.g. "Book").
            if ($str instanceof \VuFind\I18n\TranslatableStringInterface) {
                return $this->translate($str, $tokens, $default, $useIcuFormatter);
            } else {
                [$domain, $str] = $this->extractTextDomain($str);
            }
        }

        // Default case: deal with ordinary strings (or string-castable objects):
        $translation = $this->translateString((string)$str, $tokens, $default, $domain, $useIcuFormatter);
        // If we have fallback domains, apply them now:
        while ($translation === (string)($default ?? $str) && !empty($fallbackDomains)) {
            $domain = array_shift($fallbackDomains);
            $translation = $this->translateString(
                (string)$str,
                $tokens,
                $default,
                $domain,
                $useIcuFormatter
            );
        }
        return $translation;
    }

    /**
     * Translate a string (or string-castable object) using a prefix, or without the
     * prefix if a prefixed translation is not found.
     *
     * @param string              $prefix          Translation key prefix
     * @param string|object|array $target          String to translate or an array of text
     *                                             domain and string to translate
     * @param array               $tokens          Tokens to inject into the translated string
     * @param string              $default         Default value to use if no translation is
     *                                             found (null for no default).
     * @param bool                $useIcuFormatter Should we use an ICU message formatter instead
     * of the default behavior?
     * @param string[]            $fallbackDomains Text domains to check if no match is found in
     * the domain specified in $target
     *
     * @return string
     */
    public function translateWithPrefix(
        $prefix,
        $target,
        $tokens = [],
        $default = null,
        $useIcuFormatter = false,
        $fallbackDomains = []
    ) {
        if (is_string($target)) {
            if (null === $default) {
                $default = $target;
            }
            $target = $prefix . $target;
        }
        return $this->translate($target, $tokens, $default, $useIcuFormatter, $fallbackDomains);
    }

    /**
     * Get translation for a string
     *
     * @param string $rawStr          String to translate
     * @param array  $tokens          Tokens to inject into the translated string
     * @param string $default         Default value to use if no translation is found
     *                                (null for no default).
     * @param string $domain          Text domain (omit for default)
     * @param bool   $useIcuFormatter Should we use an ICU message formatter instead
     * of the default behavior?
     *
     * @return string
     */
    protected function translateString(
        $rawStr,
        $tokens = [],
        $default = null,
        $domain = 'default',
        $useIcuFormatter = false
    ) {
        if (null === $this->translator) {
            $msg = $str = $rawStr;
        } else {
            $str = $this->sanitizeTranslationKey($rawStr);
            $msg = $this->translator->translate($str, $domain);
        }

        // Did the translation fail to change anything?  If so, use default:
        if ($msg == $str) {
            $finalDefault = $default ?? $rawStr;
            $msg = $finalDefault instanceof \VuFind\I18n\TranslatableStringInterface
                ? $finalDefault->getDisplayString() : $finalDefault;
        }

        // Do we need to perform substitutions?
        if (!empty($tokens)) {
            if ($useIcuFormatter) {
                return \MessageFormatter::formatMessage($this->getTranslatorLocale(), $msg, $tokens);
            }
            $in = $out = [];
            foreach ($tokens as $key => $value) {
                $in[] = $key;
                $out[] = $value;
            }
            $msg = str_replace($in, $out, $msg);
        }

        return $msg;
    }

    /**
     * Given a translation string with or without a text domain, return an
     * array with the raw string and the text domain separated.
     *
     * @param string|object|array $target String to translate or an array of text
     * domain and string to translate
     *
     * @return array
     */
    protected function extractTextDomain($target)
    {
        $parts = is_array($target) ? $target : explode('::', $target, 2);
        if (count($parts) < 1 || count($parts) > 2) {
            throw new \Exception('Unexpected value sent to translator!');
        }
        if (count($parts) == 2) {
            if (empty($parts[0])) {
                $parts[0] = 'default';
            }
            if ($target instanceof \VuFind\I18n\TranslatableStringInterface) {
                $class = $target::class;
                $parts[1] = new $class(
                    $parts[1],
                    $target->getDisplayString(),
                    $target->isTranslatable()
                );
            }
            return $parts;
        }
        return ['default', is_array($target) ? $parts[0] : $target];
    }

    /**
     * Make sure there are not any illegal characters in the translation key
     * that might prevent successful lookup in language files.
     *
     * @param string $key Key to sanitize
     *
     * @return string Sanitized key
     */
    protected function sanitizeTranslationKey(string $key): string
    {
        // The characters ()!?| are not allowed in keys in the Lokalise translation
        // platform, so they should not be allowed in our code. We'll replace them
        // with underscore-prefixed, urlencode-inspired codes so that translations
        // can still be provided if the input cannot be changed (e.g. if it comes
        // from a third-party system).
        return str_replace(
            ['(', ')', '!', '?', '|'],
            ['_28', '_29', '_21', '_3F', '_7C'],
            $key
        );
    }
}
