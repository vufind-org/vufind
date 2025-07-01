<?php

/**
 * Trait for setting up view options. Designed to be included in a subclass of
 * \VuFind\Search\Base\Options.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2017.
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
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Search\Options;

/**
 * Trait for setting up view options. Designed to be included in a subclass of
 * \VuFind\Search\Base\Options.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
trait ViewOptionsTrait
{
    /**
     * Set up the view options.
     *
     * @param ?array $searchSettings Search settings.
     *
     * @return void
     */
    public function initViewOptions(?array $searchSettings)
    {
        if (null !== ($defaultView = $searchSettings['General']['default_view'] ?? null)) {
            $this->setConfiguredDefaultView($defaultView);
        }
        // Load view preferences (or defaults if none in .ini file):
        if ($viewOptions = $searchSettings['Views'] ?? []) {
            $this->viewOptions = $viewOptions;
        } else {
            $defaultView = $this->getConfiguredDefaultView();
            $this->viewOptions = [$defaultView => $defaultView];
        }
    }
}
