<?php

/**
 * HTML-safe JSON encoding.
 *
 * This helper is used to ensure that we consistently escape JSON data when
 * embedding it directly into HTML (typically via data attributes).
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
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
 * HTML-safe JSON encoding.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class HtmlSafeJsonEncode extends AbstractHelper
{
    /**
     * JSON-encode $value in an HTML-safe manner.
     *
     * @param mixed   $value        Data to encode
     * @param ?string $outerEscaper Name of a view helper to use to escape the JSON
     * (null/empty value for no extra escaping). Defaults to escapeHtmlAttr.
     *
     * @return string
     */
    public function __invoke($value, ?string $outerEscaper = 'escapeHtmlAttr')
    {
        $json = json_encode(
            $value,
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
        );
        return $outerEscaper
            ? ($this->getView()->plugin($outerEscaper))($json)
            : $json;
    }
}
