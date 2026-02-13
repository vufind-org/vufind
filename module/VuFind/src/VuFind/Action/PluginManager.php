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

use VuFind\Action\ShortLink\RedirectAction;
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
     * Action classes are auto-discovered in autoDiscoveryNamespaces (see below). The names must follow the convention
     * Category\Classname to be auto-discovered.
     *
     * @var array
     */
    protected $aliases = [
        // ShortLink doesn't follow the auto-discovery naming convention:
        'shortlink/redirect' => RedirectAction::class,
    ];

    /**
     * Default plugin factories.
     *
     * Autowiring factory is automatically added for any auto-discovered class unless already specified here.
     *
     * @var array
     */
    protected $factories = [
        ShortLink\RedirectAction::class => ShortLink\RedirectActionFactory::class,
    ];

    /**
     * Namespaces used for auto-discovery (excluding leading backslash).
     *
     * The namespaces are checked in order from first to last.
     *
     * @var array
     */
    protected $autoDiscoveryNamespaces = [
        __NAMESPACE__,
    ];

    /**
     * Constructor
     *
     * Make sure plugins are properly initialized.
     *
     * @param mixed $configOrContainerInstance Configuration or container instance
     * @param array $v3config                  If $configOrContainerInstance is a
     * container, this value will be passed to the parent constructor.
     */
    public function __construct(
        $configOrContainerInstance = null,
        array $v3config = []
    ) {
        $this->addInitializer(ActionInitializer::class);
        parent::__construct($configOrContainerInstance, $v3config);
    }

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
     * Get an action.
     *
     * @param class-string<InstanceType>|string $name    Service name of plugin to retrieve.
     * @param null|array<mixed>                 $options Options to use when creating the instance.
     *
     * @return mixed
     *
     * @throws Exception\ServiceNotFoundException If the manager does not have a service definition for the instance,
     * and the service is not auto-invokable.
     * @throws InvalidServiceException If the plugin created is invalid for the plugin context.
     */
    public function get($name, ?array $options = null)
    {
        return parent::get($this->resolveAlias($name), $options);
    }

    /**
     * Check if an action is available.
     *
     * @param string|class-string $name Name
     *
     * @return bool
     */
    public function has($name)
    {
        return parent::has($this->resolveAlias($name));
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

        // If the class is known already, just return it:
        if ($normalizedCategory && $normalizedAction && $this->has("$normalizedCategory/$normalizedAction")) {
            return "$normalizedCategory/$normalizedAction";
        } elseif ($this->has("$normalizedCategory$normalizedAction")) {
            return "$normalizedCategory$normalizedAction";
        }

        return null;
    }

    /**
     * Resolve alias using auto-discovery as required.
     *
     * @param string $alias Alias
     *
     * @return string
     */
    protected function resolveAlias(string $alias): string
    {
        if (null !== ($result = $this->aliases[$alias] ?? null)) {
            return $result;
        }

        $nameParts = array_map(
            fn ($s) => ucfirst(strtolower($s)),
            explode('/', $alias)
        );
        $actionClass = implode('\\', $nameParts) . 'Action';

        foreach ($this->autoDiscoveryNamespaces as $ns) {
            $unprefixedClassName = $ns . '\\' . $actionClass;
            $className = '\\' . $unprefixedClassName;
            if (class_exists($className)) {
                $this->aliases[$alias] = $className;
                $this->factories[$unprefixedClassName] ??= AutowiringFactory::class;
                $this->factories[$className] ??= AutowiringFactory::class;
                return $unprefixedClassName;
            }
        }
        return $alias;
    }
}
