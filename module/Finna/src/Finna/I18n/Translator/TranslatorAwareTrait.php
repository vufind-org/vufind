<?php
/**
 * Lightweight translator aware marker interface.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2017.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace Finna\I18n\Translator;

/**
 * Lightweight translator aware marker interface (used as an alternative to
 * \Laminas\I18n\Translator\TranslatorAwareInterface, which requires an excessive
 * number of methods to be implemented).  If we switch to PHP 5.4 traits in the
 * future, we can eliminate this interface in favor of the default Laminas version.
 *
 * @category VuFind
 * @package  Translator
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
trait TranslatorAwareTrait
{
    /**
     * Translate a string (or string-castable object)
     *
     * @param string|object|array $target  String to translate or an array of text
     * domain and string to translate
     * @param array               $tokens  Tokens to inject into the translated
     * string
     * @param string              $default Default value to use if no translation is
     * found (null for no default).
     *
     * Finna: Added translation of hierarchical strings without the middle level.
     *
     * @return string
     */
    public function translate($target, $tokens = [], $default = null)
    {
        // Figure out the text domain for the string:
        list($domain, $str) = $this->extractTextDomain($target);

        // Special case: deal with objects with a designated display value:
        if ($str instanceof \VuFind\I18n\TranslatableStringInterface) {
            if (!$str->isTranslatable()) {
                return $str->getDisplayString();
            }
            // On this pass, don't use the $default, since we want to fail over
            // to getDisplayString before giving up:
            $translated = $this
                ->translateString((string)$str, $tokens, null, $domain);
            if ($translated !== (string)$str) {
                return $translated;
            }

            $translated
                = $this->translateHierarchicalString((string)$str, $domain, $tokens);
            if (null !== $translated) {
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
                return $this->translate($str, $tokens, $default);
            } else {
                list($domain, $str) = $this->extractTextDomain($str);
            }
        }

        // Default case: deal with ordinary strings (or string-castable objects):
        $defaultTranslation
            = $this->translateString((string)$str, $tokens, $default, $domain);

        if ($defaultTranslation !== (string)$str && $defaultTranslation !== $default
        ) {
            return $defaultTranslation;
        }

        $translated
            = $this->translateHierarchicalString((string)$str, $domain, $tokens);
        if (null !== $translated) {
            return $translated;
        }

        return $defaultTranslation;
    }

    /**
     * Tries to translate a hierarchical string without the middle levels, bu only if
     * it looks like a hierarchical facet that starts with a number and ends with a
     * slash
     *
     * @param string $str    String to translate
     * @param string $domain Translation domain
     * @param array  $tokens Tokens to inject into the translated
     * string
     *
     * @return string|null Translation or null
     */
    protected function translateHierarchicalString($str, $domain, $tokens)
    {
        $parts = explode('/', (string)$str);
        $c = count($parts);
        if ($c > 3 && is_numeric($parts[0]) && array_pop($parts) === '') {
            // First attempt with the first meaningful level if we have enough levels
            if ($c > 4) {
                $sub = $parts[0] . '/' . $parts[1] . '/*/' . $parts[$c - 2] . '/';
                $translated = $this
                    ->translateString($sub, $tokens, null, $domain);
                if ($translated !== $sub) {
                    return $translated;
                }
            }
            $sub = $parts[0] . '/*/' . $parts[$c - 2] . '/';
            $translated = $this
                ->translateString($sub, $tokens, null, $domain);
            if ($translated !== $sub) {
                return $translated;
            }
        }

        return null;
    }
}
