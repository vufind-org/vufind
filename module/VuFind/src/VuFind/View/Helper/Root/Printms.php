<?php
/**
 * Prints a human readable format from a number of milliseconds
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
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\View\Helper\Root;
use Zend\View\Helper\AbstractHelper;

/**
 * Prints a human readable format from a number of milliseconds
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
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
        $seconds = floor($ms / 1000);
        $ms = ($ms % 1000);

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
                        "%d years %d days %d hours %d minutes %d seconds",
                        $years, $days, $hours, $minutes, $seconds
                    );
                } else {
                    return sprintf(
                        "%d days %d hours %d minutes %d seconds",
                        $days, $hours, $minutes, $seconds
                    );
                }
            } else {
                return sprintf(
                    "%d hours %d minutes %d seconds", $hours, $minutes, $seconds
                );
            }
        } else {
            return sprintf("%d minutes %d seconds", $minutes, $seconds);
        }
    }
}