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
     * Action-specific configuration.
     *
     * The configuration is an array of associative arrays of configuration entries.
     *
     * Valid keys for each configuration entry:
     *  - actionIds             An array of action identifiers or prefixes the configuration applies to
     *                          (format: category/action in lowercase)
     *  - accessPermission      Set access permission (string|false|null, see AccessPermissionInterface)
     *  - accessDeniedBehavior  Set behavior when access is denied (string|null, see AccessPermissionInterface)
     *  - backendId             Set search backend identifier (string)
     *  - defaultTab            Set default tab (string|null)
     *  - fallbackDefaultTab    Set fallback default tab (string; empty string to use Site/defaultRecordTab from config)
     *  - poweredBy             Set "Powered by" displayed in page footer
     *
     * @var array
     */
    protected array $actionConfig = [
        // EDS:
        [
            'actionIds' => [
                'edsrecord',
                [
                    'type' => 'prefix',
                    'prefix' => 'edsrecord/',
                ],
            ],
            'accessPermission' => 'access.EDSModule',
            'backendId' => 'EDS',
            'fallbackDefaultTab' => 'Description',
        ],

        // EIT:
        [
            'actionIds' => [
                'eitrecord',
                [
                    'type' => 'prefix',
                    'prefix' => 'eitrecord/',
                ],
            ],
            'accessPermission' => 'access.EITModule',
            'backendId' => 'EIT',
            'fallbackDefaultTab' => 'Description',
        ],

        // EPF:
        [
            'actionIds' => [
                'epfrecord',
                [
                    'type' => 'prefix',
                    'prefix' => 'epfrecord/',
                ],
            ],
            'accessPermission' => 'access.EPFModule',
            'backendId' => 'EPF',
        ],

        // Record, Collection (Default backend):
        [
            'actionIds' => [
                'collection',
                [
                    'type' => 'prefix',
                    'prefix' => 'collection/',
                ],
                'missingrecord',
                'missingrecord/home',
                'record',
                [
                    'type' => 'prefix',
                    'prefix' => 'record/',
                ],
            ],
            'backendId' => DEFAULT_SEARCH_BACKEND,
            'fallbackDefaultTab' => '',
        ],

        // Primo:
        [
            'actionIds' => [
                'primorecord',
                [
                    'type' => 'prefix',
                    'prefix' => 'primorecord/',
                ],
            ],
            'accessPermission' => 'access.PrimoModule',
            'backendId' => 'Primo',
            'fallbackDefaultTab' => 'Description',
        ],

        // ProquestFSG:
        [
            'actionIds' => [
                'proquestfsgrecord',
                [
                    'type' => 'prefix',
                    'prefix' => 'proquestfsgrecord/',
                ],
            ],
            'backendId' => 'ProQuestFSG',
            'checkEnabled' => true,
        ],

        // Search2Record, Search2Collection:
        [
            'actionIds' => [
                'search2collection',
                [
                    'type' => 'prefix',
                    'prefix' => 'search2collection/',
                ],
                'search2record',
                [
                    'type' => 'prefix',
                    'prefix' => 'search2record/',
                ],
            ],
            'backendId' => 'Search2',
            'fallbackDefaultTab' => 'Description',
        ],

        // Summon:
        [
            'actionIds' => [
                'summonrecord',
                [
                    'type' => 'prefix',
                    'prefix' => 'summonrecord/',
                ],
            ],
            'backendId' => 'Summon',
            'fallbackDefaultTab' => 'Description',
            'poweredBy' => 'Powered by Summon™ from Serials Solutions, a division of ProQuest.',
        ],

        // WorldCat2 and legacy WorldCat actions:
        [
            'actionIds' => [
                // Legacy WorldCat actions:
                'worldcatrecord',
                [
                    'type' => 'prefix',
                    'prefix' => 'worldcatrecord/',
                ],
                // Current WorldCat2 actions:
                'worldcat2record',
                [
                    'type' => 'prefix',
                    'prefix' => 'worldcat2record/',
                ],
            ],
            'backendId' => 'WorldCat2',
        ],
    ];

    /**
     * Constructor.
     *
     * @param GlobalsContainer $globalsContainer Global data container
     * @param array            $config           VuFind configuration
     */
    public function __construct(
        protected GlobalsContainer $globalsContainer,
        #[Autowire(config: 'config')]
        protected array $config,
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
        foreach ($this->actionConfig as $currentConfig) {
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
