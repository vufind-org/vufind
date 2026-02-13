<?php

/**
 * Interface for action classes.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */

namespace VuFind\Action;

use VuFind\Action\Helper\PluginManager as HelperPluginManager;
use VuFind\Http\RouteHelper;

/**
 * Interface for action classes.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
interface ActionInterface
{
    /**
     * Set helper plugin manager.
     *
     * @param HelperPluginManager $helperPluginManager Helper plugin manager
     *
     * @return static
     */
    public function setHelperPluginManager(HelperPluginManager $helperPluginManager): static;

    /**
     * Set route helper.
     *
     * @param RouteHelper $routeHelper Route helper
     *
     * @return static
     */
    public function setRouteHelper(RouteHelper $routeHelper): static;
}
