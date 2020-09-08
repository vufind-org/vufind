<?php
/**
 * Linkify a string so that the links become clickable HTML
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2019.
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

/**
 * Linkify a string so that the links become clickable HTML
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Linkify extends AbstractHelper
{
    /**
     * Linkify a string
     *
     * @param string $str String to linkify (must be HTML-escaped)
     *
     * @return string
     */
    public function __invoke($str)
    {
        $linkify = new \Misd\Linkify\Linkify();
        $proxyUrl = $this->getView()->plugin('proxyUrl');
        $escapeHtmlAttr = $this->getView()->plugin('escapeHtmlAttr');
        $callback = function ($url, $caption, $isEmail) use ($proxyUrl,
            $escapeHtmlAttr
        ) {
            $url = html_entity_decode($url);
            return '<a href="' . $escapeHtmlAttr($proxyUrl($url)) . '">'
                . "$caption</a>";
        };
        return $linkify->process($str, ['callback' => $callback]);
    }
}
