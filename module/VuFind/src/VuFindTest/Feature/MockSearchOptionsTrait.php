<?php

/**
 * Trait for tests that need a mock \VuFind\Search\Base\Options object.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Feature;

use VuFind\Config\ConfigManagerInterface;
use VuFind\Search\Base\Options;

/**
 * Trait for tests that need a mock \VuFind\Search\Base\Options object.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
trait MockSearchOptionsTrait
{
    /**
     * Get mock Options object
     *
     * @param ?ConfigManagerInterface $configManager Config manager for Options object (null
     * for new mock)
     *
     * @return Options
     */
    protected function getMockOptions(?ConfigManagerInterface $configManager = null): Options
    {
        return new class ($configManager) extends Options {
            /**
             * Return the route name for the search results action.
             *
             * @return string
             */
            public function getSearchAction()
            {
                return '';
            }

            /**
             * Get the identifier used for naming the various search classes in this family.
             *
             * @return string
             */
            public function getSearchClassId()
            {
                return 'Mock';
            }
        };
    }
}
