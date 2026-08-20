<?php

/**
 * Action dispatch listener.
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

use Laminas\EventManager\EventManagerInterface;
use Laminas\Http\Response as LaminasResponse;
use Laminas\Http\Response\Stream as LaminasResponseStream;
use Laminas\Mvc\Application;
use Laminas\Mvc\MvcEvent;
use Laminas\Psr7Bridge\Psr7Response;
use Laminas\Psr7Bridge\Psr7ServerRequest;
use Laminas\Router\RouteMatch;
use Laminas\View\Model\ModelInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use VuFind\Exception\ConfigException;
use VuFind\Http\RouteHelper;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\View\GlobalsContainer;

use function is_string;

/**
 * Copyright (C) The National Library of Finland 2026.
 *
 * @category VuFind
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
class ActionDispatchListener
{
    /**
     * Route-specific action configuration.
     *
     * The configuration is an array of associative arrays of configuration entries.
     *
     * Valid keys for each configuration entry:
     *  - routes                An array of route names the configuration applies to
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
            'routes' => [
                'edsrecord',
                [
                    'type' => 'prefix',
                    'prefix' => 'edsrecord-',
                ],
            ],
            'accessPermission' => 'access.EDSModule',
            'backendId' => 'EDS',
            'fallbackDefaultTab' => 'Description',
        ],
        // EIT:
        [
            'routes' => [
                'eitrecord',
                [
                    'type' => 'prefix',
                    'prefix' => 'eitrecord-',
                ],
            ],
            'accessPermission' => 'access.EITModule',
            'backendId' => 'EIT',
            'fallbackDefaultTab' => 'Description',
        ],
        [
            'routes' => [
                'epfrecord',
                [
                    'type' => 'prefix',
                    'prefix' => 'epfrecord-',
                ],
            ],
            'accessPermission' => 'access.EPFModule',
            'backendId' => 'EPF',
        ],
        // Record, Collection:
        [
            'routes' => [
                'collection',
                [
                    'type' => 'prefix',
                    'prefix' => 'collection-',
                ],
                'missingrecord',
                'missingrecord-home',
                'record',
                [
                    'type' => 'prefix',
                    'prefix' => 'record-',
                ],
            ],
            'backendId' => DEFAULT_SEARCH_BACKEND,
            'fallbackDefaultTab' => '',
        ],
        // Pazpar2:
        [
            'routes' => [
                'pazpar2record',
                'pazpar2record-home',
            ],
            'backendId' => 'Pazpar2',
        ],
        [
            'routes' => [
                'primorecord',
                [
                    'type' => 'prefix',
                    'prefix' => 'primorecord-',
                ],
            ],
            'accessPermission' => 'access.PrimoModule',
            'backendId' => 'Primo',
            'fallbackDefaultTab' => 'Description',
        ],
        // ProquestFSG:
        [
            'routes' => [
                'proquestfsgrecord',
                'proquestfsgrecord-home',
            ],
            'backendId' => 'ProQuestFSG',
        ],
        // Search2, Search2Collection:
        [
            'routes' => [
                'search2collection',
                [
                    'type' => 'prefix',
                    'prefix' => 'search2collection-',
                ],
                'search2record',
                [
                    'type' => 'prefix',
                    'prefix' => 'search2record-',
                ],
            ],
            'backendId' => 'Search2',
            'fallbackDefaultTab' => 'Description',
        ],
        [
            'routes' => [
                'summonrecord',
                [
                    'type' => 'prefix',
                    'prefix' => 'summonrecord-',
                ],
            ],
            'backendId' => 'Summon',
            'fallbackDefaultTab' => 'Description',
            'poweredBy' => 'Powered by Summon™ from Serials Solutions, a division of ProQuest.',
        ],
        [
            'routes' => [
                // Legacy WorldCat routes:
                'worldcatrecord',
                [
                    'type' => 'prefix',
                    'prefix' => 'worldcatrecord-',
                ],
                // Current WorldCat2 routes:
                'worldcat2record',
                [
                    'type' => 'prefix',
                    'prefix' => 'worldcat2record-',
                ],
            ],
            'backendId' => 'WorldCat2',
        ],
    ];

    /**
     * Constructor.
     *
     * @param PluginManager $actionPluginManager Action plugin manager
     * @param RouteHelper   $routeHelper         Route helper
     * @param GlobalsContainer $globalsContainer Global data container
     * @param array         $config              VuFind configuration
     */
    public function __construct(
        protected PluginManager $actionPluginManager,
        protected RouteHelper $routeHelper,
        protected GlobalsContainer $globalsContainer,
        #[Autowire(config: 'config')]
        protected array $config,
    ) {
    }

    /**
     * Attach listeners to an event manager.
     *
     * @param EventManagerInterface $eventManager Event manager
     * @param int                   $priority     Event priority
     *
     * @return void
     */
    public function attach(EventManagerInterface $eventManager, int $priority = 1): void
    {
        // Attach with higher priority than default to override Laminas' default dispatch handler:
        $eventManager->attach(MvcEvent::EVENT_DISPATCH, [$this, 'onDispatch'], 2);
    }

    /**
     * Listener for the dispatch event.
     *
     * @param MvcEvent $e Event
     *
     * @return mixed
     */
    public function onDispatch(MvcEvent $e)
    {
        if (null !== $e->getResult()) {
            return;
        }

        $route = $e->getRouteMatch();
        $id = $this->actionPluginManager
            ->getActionHandlerName($route->getParam('controller'), $route->getParam('action'));
        if (!$id) {
            return;
        }
        $action = $this->actionPluginManager->get($id);

        $routeMatch = $e->getRouteMatch();
        $viewModel = $e->getViewModel();
        $this->applyRouteBasedConfig($routeMatch, $action, $viewModel);

        $request = Psr7ServerRequest::fromLaminas($e->getRequest())
            ->withAttribute('action-id', $id)
            ->withAttribute('route-helper', $this->routeHelper)
            ->withAttribute('route-match', $routeMatch)
            ->withAttribute('view-model', $viewModel);
        $laminasResponse = $e->getApplication()->getResponse();
        $response = Psr7Response::fromLaminas($laminasResponse);
        try {
            $result = $action($request, $response);
            $laminasResponse = $this->updateLaminasResponse($laminasResponse, $result);
            $e->setResult($laminasResponse);
        } catch (Throwable $ex) {
            $e->setName(MvcEvent::EVENT_DISPATCH_ERROR);
            $e->setError(Application::ERROR_EXCEPTION);
            $e->setController($id);
            $e->setControllerClass($action::class);
            $e->setParam('exception', $ex);

            if (!$return = $e->getApplication()->getEventManager()->triggerEvent($e)->last()) {
                $return = $e->getResult();
            }
            $e->setResult($return);
        }
        return $e->getResult();
    }

    /**
     * Update Laminas response from PSR-7 response.
     *
     * @param LaminasResponse   $laminasResponse Laminas response
     * @param ResponseInterface $psr7Response    PSR-7 response
     *
     * @return LaminasResponse
     */
    protected function updateLaminasResponse(
        LaminasResponse $laminasResponse,
        ResponseInterface $psr7Response
    ): LaminasResponse {
        $uri = $psr7Response->getBody()->getMetadata('uri');
        $tempOrMemory = $uri === Psr7Response::URI_TEMP || $uri === Psr7Response::URI_MEMORY;
        if (!$tempOrMemory) {
            // Result is a stream, so we need to use the Stream class for it:
            $laminasResponse = new LaminasResponseStream();
        }
        $laminasResponse->setVersion($psr7Response->getProtocolVersion());
        $laminasResponse->setStatusCode($psr7Response->getStatusCode());
        $laminasResponse->setReasonPhrase($psr7Response->getReasonPhrase());
        $laminasHeaders = $laminasResponse->getHeaders();
        $laminasHeaders->clearHeaders();
        foreach ($psr7Response->getHeaders() as $name => $value) {
            $laminasHeaders->addHeaderLine($name, $value);
        }

        if ($tempOrMemory) {
            $laminasResponse->setContent((string)$psr7Response->getBody());
        } else {
            $laminasResponse->setStream(fopen($uri, 'rb'));
        }
        return $laminasResponse;
    }

    /**
     * Apply route-based configuration to the action.
     *
     * @param ?RouteMatch     $routeMatch Route match
     * @param ActionInterface $action     Action
     *
     * @return void
     */
    protected function applyRouteBasedConfig(
        ?RouteMatch $routeMatch,
        ActionInterface $action
    ): void {
        if (!$routeMatch) {
            return;
        }

        $routeName = $routeMatch->getMatchedRouteName();
        foreach ($this->actionConfig as $currentConfig) {
            if ($this->routeNameMatchesConfig($routeName, $currentConfig)) {
                // Apply configuration:
                foreach ($currentConfig as $key => $value) {
                    switch ($key) {
                        case 'routes':
                            break;
                        case 'accessPermission':
                        case 'accessDeniedBehavior':
                            if (!($action instanceof AccessPermissionInterface)) {
                                throw new ConfigException(
                                    $action::class . ' (route ' . $routeName . ')'
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
                                    $action::class . ' (route ' . $routeName . ')'
                                    . " does not implement BackendIdInterface for $key configuration"
                                );
                            }
                            $action->setBackendId($value);
                            break;
                        case 'defaultTab':
                        case 'fallbackDefaultTab':
                            if (!($action instanceof DefaultTabInterface)) {
                                throw new ConfigException(
                                    $action::class . ' (route ' . $routeName . ')'
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
                                $action::class . ' (route ' . $routeName . "): Invalid configuration key $key"
                            );
                    }
                }
                break;
            }
        }
    }

    /**
     * Check if route name matches the given config.
     *
     * @param string $routeName Route name
     * @param array  $config    Route-based config entry
     *
     * @return bool
     */
    protected function routeNameMatchesConfig(string $routeName, array $config): bool
    {
        foreach ($config['routes'] as $route) {
            if (is_string($route)) {
                if ($routeName === $route) {
                    return true;
                }
            } else {
                switch ($route['type']) {
                    case 'prefix':
                        if (str_starts_with($routeName, $route['prefix'])) {
                            return true;
                        }
                        break;
                    default:
                        throw new ConfigException(('Invalid routes entry: ' . var_export($route, true)));
                }
            }
        }
        return false;
    }
}
