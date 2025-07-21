<?php

/**
 * HTML Cleaner view helper
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2019-2024.
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
 * @link     http://vufind.org   Main Site
 */

namespace VuFind\View\Helper\Root;

use Closure;

/**
 * HTML Cleaner view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class CleanHtml extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Purifiers
     *
     * @var \HTMLPurifier[]
     */
    protected $purifiers = [];

    /**
     * Constructor
     *
     * @param Closure $purifierFactory Purifier factory callback
     */
    public function __construct(protected Closure $purifierFactory)
    {
    }

    /**
     * Clean up HTML
     *
     * @param string $html        HTML
     * @param bool   $targetBlank Whether to add target=_blank to outgoing links
     * @param string $context     Display context (e.g. 'heading')
     *
     * @return string
     */
    public function __invoke(string $html, bool $targetBlank = false, string $context = 'default'): string
    {
        if (!str_contains($html, '<')) {
            return $html;
        }
        $key = ($targetBlank ? 'blank' : 'noblank') . "_$context";
        if (!isset($this->purifiers[$key])) {
            $this->purifiers[$key] = ($this->purifierFactory)(compact('targetBlank', 'context'));
        }
        return $this->purifiers[$key]->purify($html);
    }
}
