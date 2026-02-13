<?php

/**
 * Action helper plugin manager
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
 * @package  Action_Helper
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */

namespace VuFind\Action\Helper;

use Laminas\ServiceManager\AbstractPluginManager;
use VuFind\ServiceManager\Factory\AutowiringFactory;

/**
 * Action helper plugin manager
 *
 * @category VuFind
 * @package  Action_Helper
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
class PluginManager extends AbstractPluginManager
{
    /**
     * An object type that the created instance must be instanced of
     *
     * @var ?string
     */
    protected $instanceOf = AbstractHelper::class;

    /**
     * Default plugin factories.
     *
     * @var array
     */
    protected $factories = [
        ForwardHelper::class => AutowiringFactory::class,
        LoginHelper::class => AutowiringFactory::class,
        RedirectHelper::class => AutowiringFactory::class,
    ];
}
