<?php

/**
 * Adjust heading level view helper
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022.
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
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\View\Helper\Root;

use Laminas\View\Helper\AbstractHelper;

/**
 * Adjust heading level view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class AdjustHeadingLevel extends AbstractHelper
{
    /**
     * Adjust HTML heading levels in the provided string.
     *
     * @param string $html       HTML string
     * @param int    $adjustment Adjustment
     *
     * @return string
     */
    public function __invoke(string $html, int $adjustment = 0)
    {
        if (0 === $adjustment) {
            return $html;
        }
        return preg_replace_callback(
            '/(<\/?[h|H])([1-6])([\s>])/',
            function ($matches) use ($adjustment) {
                $adjusted = $matches[2] + $adjustment;
                if ($adjusted < 1) {
                    $adjusted = 1;
                } elseif ($adjusted > 6) {
                    $adjusted = 6;
                }
                return $matches[1] . $adjusted . $matches[3];
            },
            $html
        );
    }
}
