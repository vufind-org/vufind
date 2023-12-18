<?php

/**
 * Make link view helper
 *
 * PHP version 8
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
 * @author   Chris Hallberg <crhallberg@gmail.com>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use function is_array;

/**
 * Make link view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Chris Hallberg <crhallberg@gmail.com>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class MakeLink extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Combine attributes including proxy
     *
     * @param string       $href    Link destination (null to skip)
     * @param string|array $attrs   Link attributes (class name or associative array)
     * @param array        $options Additional options
     *
     * @return array (associative) Combined attributes by key
     */
    protected function mergeAttributes($href, $attrs, $options)
    {
        // If $attrs is not an object, interpret as class name
        if (!is_array($attrs)) {
            $attrs = !empty($attrs) ? ['class' => $attrs] : [];
        }

        // Merge all attributes
        $mergedAttrs = array_merge(
            $attrs,
            !empty($href) ? ['href' => $href] : []
        );

        // Special option: proxy prefixing
        if ($options['proxyUrl'] ?? false) {
            $mergedAttrs['href'] = $options['proxyUrl'] . $mergedAttrs['href'];
        }

        return $mergedAttrs;
    }

    /**
     * Render an HTML link
     *
     * $href will override $attrs['href']
     * > Feel free to use like makeLink('text', 'href', $defaults);
     *
     * If no $href, will try to find an href in $attrs
     * > makeLink('text', null, ['href' => '#', 'class' => 'btn-link'])
     *
     * If $attrs is a string, will be treated like a class
     * > makeLink('text', $href, 'btn-link')
     *
     * Additional options
     * - proxyUrl: proxy url prefix before href
     * - escapeContent: Default true, set to false to skip escaping (like for HTML).
     *
     * @param string       $contents Link contents (must be properly-formed HTML)
     * @param string       $href     Link destination (null to skip)
     * @param string|array $attrs    Link attributes (class name / associative array)
     * @param array        $options  Additional options
     *
     * @return string HTML for an anchor tag
     */
    public function __invoke(
        string $contents,
        string $href = null,
        $attrs = [],
        $options = []
    ) {
        $mergedAttrs = $this->mergeAttributes($href, $attrs, $options);

        // Span instead of anchor when no href present
        $tag = empty($mergedAttrs['href']) ? 'span' : 'a';

        // Forward to makeTag helper
        $makeTag = $this->getView()->plugin('makeTag');
        return $makeTag($tag, $contents, $mergedAttrs, $options);
    }
}
