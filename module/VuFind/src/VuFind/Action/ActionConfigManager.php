<?php

/**
 * Action configuration manager.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2026.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */

namespace VuFind\Action;

use Laminas\Router\RouteMatch;
use VuFind\Exception\ConfigException;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\View\GlobalsContainer;

use function is_string;

/**
 * Action configuration manager.
 *
 * @category VuFind
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
class ActionConfigManager
{
    /**
     * Constructor.
     *
     * @param GlobalsContainer $globalsContainer Global data container
     * @param array            $config           VuFind configuration
     * @param array            $appConfig        Application config
     */
    public function __construct(
        protected GlobalsContainer $globalsContainer,
        #[Autowire(config: 'config')]
        protected array $config,
        #[Autowire(service: 'Config')]
        protected array $appConfig,
    ) {
    }

    /**
     * Apply configuration to the action.
     *
     * @param ActionInterface $action           Action
     * @param ?RouteMatch     $routeMatch       Route match
     * @param ?string         $actionIdentifier Action identifier to use (alternative to one determined from RouteMatch)
     *
     * @return void
     */
    public function applyActionConfig(
        ActionInterface $action,
        ?RouteMatch $routeMatch = null,
        ?string $actionIdentifier = null,
    ): void {
        if ((!$routeMatch && !$actionIdentifier) || !($action instanceof ActionConfigInterface)) {
            return;
        }

        if (!$actionIdentifier) {
            // Try to use lowercase controller-action or just action if available, with route name as a fallback:
            if ($actionName = $routeMatch->getParam('action')) {
                if ($controllerName = $routeMatch->getParam('controller')) {
                    $actionIdentifier = $controllerName . '/' . $actionName;
                } else {
                    $actionIdentifier = $actionName;
                }
            } else {
                $actionIdentifier = $routeMatch->getMatchedRouteName();
            }
        }
        $actionIdentifier = strtolower($actionIdentifier);
        foreach ($this->appConfig['vufind']['action_config'] ?? [] as $currentConfig) {
            if ($this->actionIdentifierMatchesConfig($actionIdentifier, $currentConfig)) {
                // Apply configuration:
                foreach ($currentConfig as $key => $value) {
                    switch ($key) {
                        case 'actionIds':
                            break;
                        case 'accessPermission':
                        case 'accessDeniedBehavior':
                            if (!($action instanceof AccessPermissionInterface)) {
                                throw new ConfigException(
                                    $action::class . ' (action ' . $actionIdentifier . ')'
                                    . " does not implement AccessPermissionInterface for $key configuration"
                                );
                            }
                            if ('accessDeniedBehavior' === $key) {
                                $action->setAccessDeniedBehavior($value);
                            } else {
                                $action->setAccessPermission($value);
                            }
                            break;
                        case 'backendId':
                            if (!($action instanceof BackendIdInterface)) {
                                throw new ConfigException(
                                    $action::class . ' (action ' . $actionIdentifier . ')'
                                    . " does not implement BackendIdInterface for $key configuration"
                                );
                            }
                            $action->setBackendId($value);
                            break;
                        case 'defaultTab':
                        case 'fallbackDefaultTab':
                            if (!($action instanceof DefaultTabInterface)) {
                                throw new ConfigException(
                                    $action::class . ' (action ' . $actionIdentifier . ')'
                                    . " does not implement DefaultTabInterface for $key configuration"
                                );
                            }
                            if ('fallbackDefaultTab' === $key) {
                                if ('' === $value) {
                                    // Load default tab setting:
                                    if (!($value = $this->config['Site']['defaultRecordTab'] ?? null)) {
                                        break;
                                    }
                                }
                                $action->setFallbackDefaultTab($value);
                            } else {
                                $action->setDefaultTab($value);
                            }
                            break;
                        case 'poweredBy':
                            $this->globalsContainer['poweredBy'] = $value;
                            break;
                        default:
                            throw new ConfigException(
                                $action::class . ' (action ' . $actionIdentifier . "): Invalid configuration key $key"
                            );
                    }
                }
                break;
            }
        }
    }

    /**
     * Check if action identifier matches the given config.
     *
     * @param string $actionIdentifier Action identifier
     * @param array  $config           Config entry
     *
     * @return bool
     */
    protected function actionIdentifierMatchesConfig(string $actionIdentifier, array $config): bool
    {
        foreach ($config['actionIds'] as $actionId) {
            if (is_string($actionId)) {
                if ($actionIdentifier === $actionId) {
                    return true;
                }
            } else {
                switch ($actionId['type']) {
                    case 'prefix':
                        if (str_starts_with($actionIdentifier, $actionId['prefix'])) {
                            return true;
                        }
                        break;
                    default:
                        throw new ConfigException(('Invalid actionIds entry: ' . var_export($actionId, true)));
                }
            }
        }
        return false;
    }
}
