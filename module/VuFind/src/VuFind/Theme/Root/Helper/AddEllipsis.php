<?php
/**
 * "Add ellipsis" view helper
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Theme\Root\Helper;
use Zend\View\Helper\AbstractHelper;

/**
 * "Add ellipsis" view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
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
            array('{{{{START_HILITE}}}}', '{{{{END_HILITE}}}}'), '', $highlighted
        );

        // If the dehighlighted string is shorter than the full string, we need
        // to figure out where things changed:
        if (strlen($dehighlighted) < strlen($fullString)) {
            // If the first five characters don't match chances are something was cut
            // from the front:
            if (substr($dehighlighted, 0, 5) != substr($fullString, 0, 5)) {
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