<?php

/**
 * Action plugin manager
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

use VuFind\ServiceManager\Factory\AutowiringFactory;

/**
 * Action plugin manager
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
class PluginManager extends \VuFind\ServiceManager\AbstractPluginManager
{
    /**
     * Default plugin aliases.
     *
     * @var array
     */
    protected $aliases = [
        'content/content' => Content\ContentAction::class,
        'shortlink/redirect' => ShortLink\RedirectAction::class,
    ];

    /**
     * Default plugin factories.
     *
     * @var array
     */
    protected $factories = [
        Content\ContentAction::class => AutowiringFactory::class,
        ShortLink\RedirectAction::class => ShortLink\RedirectActionFactory::class,
    ];

    /**
     * Return the name of the base class or interface that plug-ins must conform
     * to.
     *
     * @return string
     */
    protected function getExpectedInterface()
    {
        return ActionInterface::class;
    }

    /**
     * Given a category and action name, return the most appropriate action handler class.
     *
     * @param string $category Category name
     * @param string $action   Action name
     *
     * @return ActionInterface
     */
    public function getActionHandler(string $category, string $action): ActionInterface
    {
        $normalizedCategory = strtolower($category);
        $normalizedAction = strtolower($action);
        return $this->has("$normalizedCategory/$normalizedAction")
            ? $this->get("$normalizedCategory/$normalizedAction")
            : $this->get($normalizedCategory);
    }
}
