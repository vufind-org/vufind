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
        'collection/home' => Collection\HomeAction::class,
        'content/content' => Content\ContentAction::class,
        'myresearch/login' => MyResearch\LoginAction::class,
        'shortlink/redirect' => ShortLink\RedirectAction::class,
    ];

    /**
     * Default plugin factories.
     *
     * @var array
     */
    protected $factories = [
        Collection\HomeAction::class => AutowiringFactory::class,
        Content\ContentAction::class => AutowiringFactory::class,
        MyResearch\LoginAction::class => AutowiringFactory::class,
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
     * Given a category and action name, return the most appropriate action handler class name, or null if not
     * available.
     *
     * @param ?string $category Category name
     * @param ?string $action   Action name
     *
     * @return ?string
     */
    public function getActionHandlerName(?string $category, ?string $action): ?string
    {
        if (!$category && !$action) {
            return null;
        }
        $normalizedCategory = $category ? strtolower($category) : '';
        $normalizedAction = $action ? strtolower($action) : '';

        if ($normalizedCategory && $normalizedAction && $this->has("$normalizedCategory/$normalizedAction")) {
            return "$normalizedCategory/$normalizedAction";
        } elseif ($this->has("$normalizedCategory$normalizedAction")) {
            return "$normalizedCategory$normalizedAction";
        }
        return null;
    }
}
