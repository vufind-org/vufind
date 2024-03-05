<?php

/**
 * Translate + escape view helper
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
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use Laminas\View\Helper\AbstractHelper;

/**
 * Translate + escape view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class TransEsc extends AbstractHelper
{
    /**
     * Translate and escape a string
     *
     * @param string|object|array $str             String to translate or an array of text
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
    public function __invoke(
        $str,
        $tokens = [],
        $default = null,
        $useIcuFormatter = false,
        $fallbackDomains = []
    ) {
        $escaper = $this->getView()->plugin('escapeHtml');
        $translator = $this->getView()->plugin('translate');
        return $escaper($translator($str, $tokens, $default, $useIcuFormatter, $fallbackDomains));
    }
}
