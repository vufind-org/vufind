<?php
/**
 * Translate + escape view helper for EDS labels
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2022.
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
 * Translate + escape view helper for EDS labels
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class TransEscEDSLabel extends AbstractHelper
{
    /**
     * Translate and escape a string
     *
     * @param string $str     String to escape and translate
     * @param array  $tokens  Tokens to inject into the translated string
     * @param string $default Default value to use if no translation is found (null
     * for no default).
     *
     * @return string
     */
    public function __invoke($str)
    {
        // Obtain helpers:
        $escaper = $this->getView()->plugin('escapeHtml');
        $translator = $this->getView()->plugin('translate');

        // Replace non-alphanumeric characters with underscores (to ensure
        // legal translation keys):
        $normalizedStr = preg_replace("/[^A-Za-z0-9 ]/", "_", $str);

        // Now apply translation: ideally we want a match from the EDS namespace,
        // but if that's not found, we'll fall back on the default namespace, and
        // finally resort to returning the raw, non-normalized string.
        $fallback = $translator($normalizedStr, [], $str);
        $result = $translator("EDS::$normalizedStr", [], $fallback);

        return $escaper($result);
    }
}
