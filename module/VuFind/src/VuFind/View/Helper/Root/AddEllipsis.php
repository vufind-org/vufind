<?php

/**
 * "Add ellipsis" view helper
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\View\Helper\Root;

use Laminas\View\Helper\AbstractHelper;

use function strlen;

/**
 * "Add ellipsis" view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class AddEllipsis extends AbstractHelper
{
    /**
     * Adds "..." to the beginning and/or end of a highlighted phrase when
     *  incomplete text is detected.
     *
     * @param string $highlighted Highlighted, possibly abbreviated string
     * @param mixed  $fullString  Full, non-highlighted text
     *
     * @return string             Highlighted string with ellipsis added
     */
    public function __invoke($highlighted, $fullString)
    {
        // Remove highlighting markers from the string so we can perform a clean
        // comparison:
        $dehighlighted = str_replace(
            ['{{{{START_HILITE}}}}', '{{{{END_HILITE}}}}'],
            '',
            $highlighted
        );

        // If the dehighlighted string is shorter than the full string, we need
        // to figure out where things changed:
        if (strlen($dehighlighted) < strlen($fullString)) {
            // If we can splice the highlighted text into the unhighlighted text,
            // let's do so!
            $pos = strpos($fullString, $dehighlighted);
            if ($pos !== false) {
                // Attach the highlighted snippet to the unhighlighted preceding text
                $title = substr($fullString, 0, $pos) . $highlighted;
                // If the overall title is relatively short, attach the rest;
                // otherwise, unless we already have the full string, add ellipses.
                if (strlen($fullString) < 160) {
                    $title .= substr($fullString, $pos + strlen($dehighlighted));
                } elseif ($pos + strlen($dehighlighted) < strlen($fullString)) {
                    $title = trim($title) . '...';
                }
                return $title;
            }

            // If the first five characters don't match chances are something was cut
            // from the front:
            if (strncmp($dehighlighted, $fullString, 5) !== 0) {
                $highlighted = '...' . $highlighted;
            }

            // If the last five characters don't match, chances are something was cut
            // from the end:
            if (substr($dehighlighted, -5) != substr($fullString, -5)) {
                $highlighted .= '...';
            }
        }

        // Send back our augmented string:
        return $highlighted;
    }
}
