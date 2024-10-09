<?php

/**
 * Escape view helper
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
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use Laminas\View\Helper\EscapeHtml as LaminasEscapeHtml;

/**
 * Escape view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class EscapeHtml extends \Laminas\View\Helper\AbstractHelper
{
    protected $laminasEscapeHtml;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->laminasEscapeHtml = new LaminasEscapeHtml();
    }

    /**
     * This helper calls Laminas escapeHtml, but allows safe styling characters
     *
     * @param string $str    The string to escape
     * @param array  $except Array of tag names to leave as is.  Only simple tags
     *                       (no attributes).
     *
     * @return string The partially escaped string
     */
    public function __invoke($str, $except = ['em', 'i', 'b'])
    {
        $escaped = $this->laminasEscapeHtml->__invoke($str);

        // Revert ok chars
        foreach ($except as $tag) {
            $escaped = str_replace("&lt;{$tag}&gt;", "<{$tag}>", $escaped);
            $escaped = str_replace("&lt;/{$tag}&gt;", "</{$tag}>", $escaped);
        }

        return $escaped;
    }
}
