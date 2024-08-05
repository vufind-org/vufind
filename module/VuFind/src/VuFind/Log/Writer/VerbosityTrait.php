<?php

/**
 * Trait to add configurable verbosity settings to loggers
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2016.
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
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFind\Log\Writer;

use function is_array;

/**
 * Trait to add configurable verbosity settings to loggers
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
trait VerbosityTrait
{
    /**
     * Holds the verbosity level
     *
     * @var int
     */
    protected $verbosity = 1;

    /**
     * Set verbosity
     *
     * @param int $verb verbosity setting
     *
     * @return void
     */
    public function setVerbosity($verb)
    {
        $this->verbosity = $verb;
    }

    /**
     * Apply verbosity setting to message.
     *
     * @param array $event event data
     *
     * @return array
     */
    protected function applyVerbosity(array $event)
    {
        // Apply verbosity filter:
        if (is_array($event['message'])) {
            $event['message'] = $event['message'][$this->verbosity];
        }
        return $event;
    }
}
