<?php

/**
 * Abstract base class for actions.
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

use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use VuFind\ActionHelper\HelperInterface;
use VuFind\ActionHelper\PermissionHelper;
use VuFind\ActionHelper\PluginManager as HelperPluginManager;
use VuFind\Exception\ConfigException;
use VuFind\Http\RouteHelper;
use VuFind\Session\Settings as SessionSettings;

/**
 * Abstract base class for actions.
 *
 * @category VuFind
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
abstract class AbstractAction implements ActionInterface, AccessPermissionInterface
{
    /**
     * Current request.
     *
     * @var ?ServerRequestInterface $request
     */
    protected ?ServerRequestInterface $request = null;

    /**
     * Current response.
     *
     * @var ?ResponseInterface $response
     */
    protected ?ResponseInterface $response = null;

    /**
     * Route helper.
     *
     * @var ?RouteHelper
     */
    protected ?RouteHelper $routeHelper = null;

    /**
     * Action helper plugin manager.
     *
     * @var ?HelperPluginManager
     */
    protected ?HelperPluginManager $helperPluginManager = null;

    /**
     * Session settings.
     *
     * @var ?SessionSettings
     */
    protected ?SessionSettings $sessionSettings = null;

    /**
     * Permission that must be granted to access this action (false for no restriction, null to use configured default
     * (which is usually the same as false)).
     *
     * @var string|false|null
     */
    protected string|false|null $accessPermission = null;

    /**
     * Behavior when access is denied (used unless overridden through permissionBehavior.ini). Valid values are
     * 'promptLogin' and 'exception'. Leave at null to use the defaultDeniedActionBehavior set in
     * permissionBehavior.ini (normally 'promptLogin' unless changed).
     *
     * @var ?string
     */
    protected ?string $accessDeniedBehavior = null;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->init();
    }

    /**
     * Set helper plugin manager.
     *
     * @param HelperPluginManager $helperPluginManager Helper plugin manager
     *
     * @return static
     */
    public function setHelperPluginManager(HelperPluginManager $helperPluginManager): static
    {
        $this->helperPluginManager = $helperPluginManager;
        return $this;
    }

    /**
     * Set route helper.
     *
     * @param RouteHelper $routeHelper Route helper
     *
     * @return static
     */
    public function setRouteHelper(RouteHelper $routeHelper): static
    {
        $this->routeHelper = $routeHelper;
        return $this;
    }

    /**
     * Set session settings.
     *
     * @param SessionSettings $sessionSettings Session settings
     *
     * @return static
     */
    public function setSessionSettings(SessionSettings $sessionSettings): static
    {
        $this->sessionSettings = $sessionSettings;
        return $this;
    }

    /**
     * Get access permission.
     *
     * @return string|false|null
     *
     * @see AbstractAction::$accessPermission
     */
    public function getAccessPermission(): string|false|null
    {
        return $this->accessPermission;
    }

    /**
     * Set access permission.
     *
     * @param string|false|null $permission Permission to require
     *
     * @return static
     *
     * @see AbstractAction::$accessPermission
     */
    public function setAccessPermission(string|false|null $permission): static
    {
        $this->accessPermission = $permission;
        return $this;
    }

    /**
     * Get access denied behavior.
     *
     * @return ?string
     *
     * @see AbstractAction::$accessDeniedBehavior
     */
    public function getAccessDeniedBehavior(): ?string
    {
        return $this->accessDeniedBehavior;
    }

    /**
     * Set access denied behavior.
     *
     * @param ?string $behavior Access denied behavior
     *
     * @return static
     *
     * @see AbstractAction::$accessDeniedBehavior
     */
    public function setAccessDeniedBehavior(?string $behavior): static
    {
        $this->accessDeniedBehavior = $behavior;
        return $this;
    }

    /**
     * Invoke the action.
     *
     * @param ServerRequestInterface $request  Server request
     * @param ResponseInterface      $response Response
     *
     * @return ResponseInterface
     */
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        $this->request = $request;
        $this->response = $response;

        try {
            if ($preResponse = $this->checkPrerequisites($request, $response)) {
                return $preResponse;
            }

            if ($accessDeniedResponse = $this->validateAccessPermission()) {
                return $accessDeniedResponse;
            }
            return $this->action($request, $response);
        } catch (Throwable $exception) {
            return $this->handleException($exception);
        }
    }

    /**
     * Initialize the action.
     *
     * @return void
     */
    protected function init(): void
    {
        // This function is called after constructor for any initialization required.
    }

    /**
     * Check that everything is in order for the action to be executed.
     *
     * May return a response or throw an exception if there are issues.
     *
     * @param ServerRequestInterface $request  Request
     * @param ResponseInterface      $response Response
     *
     * @return ?ResponseInterface
     */
    protected function checkPrerequisites(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ?ResponseInterface {
        return null;
    }

    /**
     * Perform the action.
     *
     * @param ServerRequestInterface $request  Server request
     * @param ResponseInterface      $response Response
     *
     * @return ResponseInterface
     */
    abstract protected function action(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface;

    /**
     * Prevent session writes -- this is designed to be called prior to time-
     * consuming AJAX operations to help reduce the odds of a timing-related bug
     * that causes the wrong version of session data to be written to disk (see
     * VUFIND-716 for more details).
     *
     * @return void
     */
    protected function disableSessionWrites()
    {
        $this->getSessionSettings()->disableWrite();
    }

    /**
     * Handle an exception during action.
     *
     * @param Throwable $exception Exception
     *
     * @return ResponseInterface
     */
    protected function handleException(Throwable $exception): ResponseInterface
    {
        // Default behavior; re-throw the exception:
        throw $exception;
    }

    /**
     * Check if method is POST.
     *
     * @return bool
     */
    protected function isPost(): bool
    {
        return $this->request->getMethod() === 'POST';
    }

    /**
     * Get a parameter from POST fields or query string.
     *
     * @param string            $param   Param name
     * @param array|string|null $default Default value
     *
     * @return array|string|null
     */
    protected function getPostOrQueryParam(string $param, array|string|null $default = null): array|string|null
    {
        return $this->getPostParam($param)
            ?? $this->getQueryParam($param)
            ?? $default;
    }

    /**
     * Get a parameter from POST fields.
     *
     * @param string            $param   Param name
     * @param array|string|null $default Default value
     *
     * @return array|string|null
     */
    protected function getPostParam(string $param, array|string|null $default = null): array|string|null
    {
        return $this->request->getParsedBody()[$param] ?? $default;
    }

    /**
     * Get a parameter from query string.
     *
     * @param string            $param   Param name
     * @param array|string|null $default Default value
     *
     * @return array|string|null
     */
    protected function getQueryParam(string $param, array|string|null $default = null): array|string|null
    {
        return $this->request->getQueryParams()[$param] ?? $default;
    }

    /**
     * Get a parameter from route match.
     *
     * @param string            $param   Param name
     * @param array|string|null $default Default value
     *
     * @return array|string|null
     */
    protected function getRouteParam(string $param, array|string|null $default = null): array|string|null
    {
        return $this->request->getAttribute('route-match')?->getParam($param) ?? $default;
    }

    /**
     * Get a helper plugin.
     *
     * @param class-string<T> $name Name of plugin
     *
     * @template T
     *
     * @return T
     */
    protected function getHelper(string $name): HelperInterface
    {
        if (null === $this->helperPluginManager) {
            throw new Exception($this::class . ' action not properly initialized; helper plugin manager missing');
        }
        return $this->helperPluginManager->get($name);
    }

    /**
     * Get route helper.
     *
     * @return RouteHelper
     */
    protected function getRouteHelper(): RouteHelper
    {
        if (null === $this->routeHelper) {
            throw new Exception($this::class . ' action not properly initialized; route helper missing');
        }
        return $this->routeHelper;
    }

    /**
     * Get session settings.
     *
     * @return SessionSettings
     */
    protected function getSessionSettings(): SessionSettings
    {
        if (null === $this->sessionSettings) {
            throw new Exception($this::class . ' action not properly initialized; session settings missing');
        }
        return $this->sessionSettings;
    }

    /**
     * Validate any access permission for the action.
     *
     * @return ?ResponseInterface A response if access is denied, null otherwise
     */
    public function validateAccessPermission(): ?ResponseInterface
    {
        $permissionBehaviorConfig = $this->getHelper(PermissionHelper::class)->getPermissionBehaviorConfig();
        $actionPermissions = $permissionBehaviorConfig['global']['actionAccess'] ?? [];
        // If controllerAccess is defined, make sure it's not configured for a controller that no longer exists,
        // and any controllerAccess['*'] matches actionAccess['*']:
        if ($controllerAccess = $permissionBehaviorConfig['global']['controllerAccess'] ?? null) {
            foreach ($controllerAccess as $controller => $permission) {
                // TODO: remove the conditions when controllers are no longer supported.
                if ('*' === $controller) {
                    if ($permission !== ($actionPermissions['*'] ?? null)) {
                        throw new ConfigException(
                            "actionAccess['*'] and controllerAccess['*'] must match in permissionBehavior configuration"
                        );
                    }
                } elseif (!class_exists($controller)) {
                    throw new ConfigException(
                        "permissionBehavior configuration defines controllerAccess for controller '$controller'"
                        . ' that does not exist. Please review configuration and replace controllerAccess with'
                        . ' actionAccess where appropriate.'
                    );
                }
            }
        }

        // If the current permission is null (as opposed to false or a string), that means it has no internally
        // configured default; thus, we should apply the default value:
        if (null === $this->accessPermission) {
            if ($actionPermissions) {
                // Iterate through parent classes until we find the most specific class access permission defined
                // (if any):
                $class = static::class;
                $categoryPermission = null;
                do {
                    if (null !== ($classPermission = $actionPermissions[$class] ?? null)) {
                        $this->accessPermission = $classPermission;
                        break;
                    }

                    // Check for action category specific configuration:
                    if (null === $categoryPermission) {
                        $categoryName = preg_replace('/\\\\Action\\\\(.+?)\\\\.*/', '\\Action\\\$1', $class);
                        $categoryPermission = $actionPermissions[$categoryName] ?? null;
                    }

                    $class = get_parent_class($class);
                } while ($class);

                $this->accessPermission ??= $categoryPermission;
            }

            // Check for a default permission if a more specific permission was not found above:
            $this->accessPermission ??= $actionPermissions['*'] ?? null;
        }

        // If there is an access permission set for this action, pass it through to the permission helper and return the
        // response:
        if ($this->accessPermission) {
            return $this->getHelper(PermissionHelper::class)
                ->check($this->request, $this->response, $this->accessPermission, $this->accessDeniedBehavior);
        }
        return null;
    }
}
