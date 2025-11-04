<?php

/**
 * Matomo web analytics tracking code view helper for Matomo versions >= 4
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2014-2023.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace Finna\View\Helper\Root;

use Laminas\View\HtmlAttributesSet;

use function call_user_func_array;
use function func_get_args;
use function func_num_args;

/**
 * Matomo web analytics tracking code view helper for Matomo versions >= 4
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class MatomoTracking extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Whether Matomo tracking is enabled
     *
     * @var bool
     */
    protected $active;

    /**
     * Constructor
     *
     * @param array $config VuFind configuration
     */
    public function __construct(array $config)
    {
        $this->active = !empty($config['Matomo']['url']);
    }

    /**
     * If parameters are given, acts as a shortcut to getContentAttrs. Otherwise just
     * returns $this.
     *
     * @return mixed
     */
    public function __invoke()
    {
        return func_num_args() < 2
            ? $this
            : call_user_func_array([$this, 'getContentAttrs'], func_get_args());
    }

    /**
     * Get content impressions tracking attributes for an HTML element
     *
     * @param string $name    Content name (domain)
     * @param string $piece   Content piece
     * @param array  $options Any additional options ('target' or 'ignoreinteraction')
     *
     * @see https://matomo.org/faq/how-to/how-do-i-markup-content-for-content-tracking/
     *
     * @return HtmlAttributesSet
     */
    public function getContentAttrs(string $name, string $piece, array $options = []): HtmlAttributesSet
    {
        $htmlAttrs = $this->getView()->plugin('HtmlAttributes');
        if (!$this->active) {
            return $htmlAttrs();
        }
        $attrs = [
            'data-track-content' => null,
            'data-content-name' => $name,
            'data-content-piece' => $piece,
        ] + array_map(
            function (string $s): string {
                return "data-content-$s";
            },
            $options
        );

        return $htmlAttrs($attrs);
    }
}
