<?php

/**
 * Helper class to load .ini files from disk.
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

namespace VuFind\I18n\Translator\Loader;

use Laminas\I18n\Translator\TextDomain;

use function is_array;

/**
 * Helper class to load .ini files from disk.
 *
 * @category VuFind
 * @package  Translator
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ExtendedIniReader
{
    /**
     * Parse a language file.
     *
     * @param string|array $input         Either a filename to read (passed as a
     * string) or a set of data to convert into a TextDomain (passed as an array)
     * @param bool         $convertBlanks Should we convert blank strings to
     * zero-width non-joiners?
     *
     * @return TextDomain
     */
    public function getTextDomain($input, $convertBlanks = true)
    {
        $data = new TextDomain();

        // Manually parse the language file:
        $contents = is_array($input) ? $input : file($input);
        if (is_array($contents)) {
            foreach ($contents as $current) {
                // Split the string on the equals sign, keeping a max of two chunks:
                $parts = explode('=', $current, 2);
                // Trim off outermost single quotes, if any, from keys (these are
                // needed by Lokalise in some cases for keys with numeric values)
                $key = preg_replace(
                    '/^\'?(.*?)\'?$/',
                    '$1',
                    trim($parts[0])
                );
                if ($key !== '' && !str_starts_with($key, ';')) {
                    // Trim outermost matching single or double quotes off the value if present:
                    if (isset($parts[1])) {
                        $value = stripslashes(
                            preg_replace(
                                '/^(["\'])?(.*?)\1?$/',
                                '$2',
                                trim($parts[1])
                            )
                        );

                        // Store the key/value pair (allow empty values -- sometimes
                        // we want to replace a language token with a blank string,
                        // but Laminas translator doesn't support them so replace
                        // with a zero-width non-joiner):
                        if ($convertBlanks && $value === '') {
                            $value = html_entity_decode(
                                '&#x200C;',
                                ENT_NOQUOTES,
                                'UTF-8'
                            );
                        }
                        $data[$key] = $value;
                    }
                }
            }
        }

        return $data;
    }
}
