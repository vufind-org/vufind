<?php
/**
 * Strip tags view helper
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2020.
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
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\View\Helper\Root;

/**
 * Strip tags view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class StripTags extends \Zend\View\Helper\AbstractHelper
{
    /**
     * Strip HTML tags from a string.
     *
     * @param string  $string            A string that may or may not contain
     *                                   HTML tags.
     * @param boolean $remove_whitespace Whether to also remove line breaks and
     *                                   extra white space chars (optional).
     *
     * @return string
     */
    public function __invoke($string, $remove_whitespace = true)
    {
        if (false === strpos($string, '<')) {
            return $string;
        }

        // Replace specific tags with a space. This is to prevent words from being
        // concatenated in cases like foo<br>bar and <p>foo</p><p>bar</p>.
        $string = str_replace(
            ['<p>', '<br>', '<br/>', '<br />', '<li>'], ' ', $string
        );

        // Remove the contents of <script> and <style> tags.
        $string = preg_replace(
            '@<(script|style)[^>]*?>.*?</\\1>@si', '', $string
        );

        $string = strip_tags($string);

        if ($remove_whitespace) {
            $string = preg_replace('/[\r\n\t ]+/', ' ', $string);
        }

        return trim($string);
    }
}
