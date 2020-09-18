<?php
/**
 * Make link view helper
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2020.
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
 * Make link view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class MakeLink extends AbstractHelper
{
    /**
     * Create an anchor tag (or just text if no href provided)
     *
     * @param string $text  Link contents
     * @param string $href  Link destination
     * @param array  $attrs Link attributes (associative array)
     *
     * @return string HTML for an anchor tag
     */
    public function __invoke($text, $href = null, $attrs = [])
    {
        if (!$href && !isset($attrs['href'])) {
            $text;
        }

        // Skip href
        if (is_array($href)) {
            return $this->compileAttrs($text, $href);
        }

        // Class string
        if (is_string($attrs)) {
            $attrs = ['class' => $attrs];
        }

        $attrs['href'] = $href;
        return $this->compileAttrs($text, $attrs);
    }

    /**
     * Turn associative array into a string of attributes in an anchor
     *
     * @param string $text  Link contents
     * @param array  $attrs Link attributes (associative array)
     *
     * @return string
     */
    protected function compileAttrs($text, $attrs)
    {
        $escAttr = $this->getView()->plugin('escapeHtmlAttr');

        $anchor = '<a';
        foreach ($attrs as $key => $val) {
            $anchor .= ' ' . $key . '="' . $escAttr($val) . '"';
        }

        $anchor .= '>' . $text . '</a>';
        return $anchor;
    }
}
