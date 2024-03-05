<?php

/**
 * Helper to check if a translation is empty
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use Laminas\View\Helper\AbstractHelper;
use VuFind\I18n\Translator\TranslatorAwareInterface;

/**
 * Helper to check if a translation is empty
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class TranslationEmpty extends AbstractHelper implements TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Check if a translation is empty
     *
     * @param string|object $str             String to translate
     * @param string[]      $fallbackDomains Text domains to check if no match is found in
     * the domain specified in $target
     *
     * @return bool
     */
    public function __invoke($str, $fallbackDomains = [])
    {
        $result = $this->translate($str, [], '', false, $fallbackDomains);
        // Existing empty translations will result in &#x200C, otherwise the default
        // '' is returned
        return $result === ''
            || $result === html_entity_decode('&#x200C;', ENT_NOQUOTES, 'UTF-8');
    }
}
