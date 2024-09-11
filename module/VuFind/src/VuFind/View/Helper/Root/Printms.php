<?php

/**
 * Prints a human readable format from a number of milliseconds
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
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use Laminas\View\Helper\AbstractHelper;

use function sprintf;

/**
 * Prints a human readable format from a number of milliseconds
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Printms extends AbstractHelper
{
    /**
     * Prints a human readable format from a number of milliseconds
     *
     * @param float $ms Number of milliseconds
     *
     * @return string   Human-readable representation
     */
    public function __invoke($ms)
    {
        // If we can't do the math, don't bother formatting the value:
        if (!is_numeric($ms)) {
            return $ms;
        }
        $seconds = floor($ms / 1000);

        $minutes = floor($seconds / 60);
        $seconds = ($seconds % 60);

        $hours = floor($minutes / 60);
        $minutes = ($minutes % 60);

        if ($hours) {
            $days = floor($hours / 60);
            $hours = ($hours % 60);

            if ($days) {
                $years = floor($days / 365);
                $days = ($days % 365);

                if ($years) {
                    return sprintf(
                        '%d years %d days %d hours %d minutes %d seconds',
                        $years,
                        $days,
                        $hours,
                        $minutes,
                        $seconds
                    );
                } else {
                    return sprintf(
                        '%d days %d hours %d minutes %d seconds',
                        $days,
                        $hours,
                        $minutes,
                        $seconds
                    );
                }
            } else {
                return sprintf(
                    '%d hours %d minutes %d seconds',
                    $hours,
                    $minutes,
                    $seconds
                );
            }
        } else {
            return sprintf('%d minutes %d seconds', $minutes, $seconds);
        }
    }
}
