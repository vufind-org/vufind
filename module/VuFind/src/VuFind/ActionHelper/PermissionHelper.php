<?php

/**
 * VuFind Action Helper - Permission Checker.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2017.
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
 * @package  Action_Helper
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\ActionHelper;

use Lmc\Rbac\Identity\IdentityInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerAwareInterface;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Exception\Forbidden as ForbiddenException;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\Role\PermissionDeniedManager;
use VuFind\Role\PermissionManager;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * VuFind Action Helper - Permission Checker.
 *
 * @category VuFind
 * @package  Action_Helper
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class PermissionHelper implements
    HelperInterface,
    LoggerAwareInterface,
    TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Constructor.
     *
     * @param PermissionManager       $permissionManager       Permission Manager
     * @param PermissionDeniedManager $permissionDeniedManager Permission Denied Manager
     * @param AuthManager             $authManager             Auth manager
     * @param LoginHelper             $loginHelper             Login helper
     * @param RedirectHelper          $redirectHelper          Redirect helper
     */
    public function __construct(
        protected PermissionManager $permissionManager,
        protected PermissionDeniedManager $permissionDeniedManager,
        protected AuthManager $authManager,
        #[Autowire(container: PluginManager::class)]
        protected LoginHelper $loginHelper,
        #[Autowire(container: PluginManager::class)]
        protected RedirectHelper $redirectHelper,
    ) {
    }

    /**
     * Check if a permission is authorized, returning a boolean value without
     * applying any additional behavior.
     *
     * @param string $permission Permission to check
     * @param mixed  $context    Context for the permission behavior (optional)
     *
     * @return bool
     */
    public function isAuthorized($permission, $context = null)
    {
        return $this->permissionManager->isAuthorized($permission, $context);
    }

    /**
     * Check if a permission is denied; if so, throw an exception or return an
     * error response as configured in permissionBehavior.ini.
     *
     * @param ServerRequestInterface $request         Request
     * @param ResponseInterface      $response        Response
     * @param string                 $permission      Permission to check
     * @param ?string                $defaultBehavior Default behavior to use if none configured
     * (null to use default configured in the manager, false to take no action).
     * @param bool                   $passIfUndefined Should the check pass if no rules are defined for $permission in
     * permissions.ini?
     *
     * @return mixed
     */
    public function check(
        ServerRequestInterface $request,
        ResponseInterface $response,
        string $permission,
        ?string $defaultBehavior = null,
        bool $passIfUndefined = false
    ): ?ResponseInterface {
        // If no permission rule is defined and we're only checking defined
        // permissions, bail out now....
        if (
            !$this->permissionManager->permissionRuleExists($permission)
            && $passIfUndefined
        ) {
            return null;
        }

        // Make sure the current user has permission:
        if ($this->permissionManager->isAuthorized($permission) !== true) {
            $dl = $this->permissionDeniedManager->getDeniedControllerBehavior(
                $permission,
                $defaultBehavior
            );
            if ($dl === false) {
                return null;
            }
            $exceptionDescription = $dl['exceptionMessage'] ?? 'Access denied.';
            switch (strtolower($dl['action'])) {
                case 'promptlogin':
                    // If the user is already logged in, but we're getting a "prompt
                    // login" denied permission requirement, there is probably a
                    // configuration error somewhere; throw an exception rather than
                    // triggering an infinite login redirection loop.
                    if ($this->getIdentity()) {
                        throw new ForbiddenException(
                            'Trying to prompt login due to denied ' . $permission
                            . ' permission, but a user is already logged in; '
                            . 'possible configuration problem in permissions.ini.'
                        );
                    }
                    $msg = empty($dl['value']) ? null : $dl['value'];
                    return $this->loginHelper->forceLogin($request, $response, $msg, [], false);
                case 'showmessage':
                    return $this->redirectHelper->redirectToRoute(
                        $response,
                        'error-permissiondenied',
                        [],
                        ['msg' => $dl['value']]
                    );
                case 'exception':
                    $exceptionClass
                        = (isset($dl['value']) && class_exists($dl['value']))
                        ? $dl['value'] : \VuFind\Exception\Forbidden::class;
                    $exception = new $exceptionClass($exceptionDescription);
                    if ($exception instanceof \Exception) {
                        throw $exception;
                    }
                    $this->logError('Permission configuration problem.');
                    throw new \Exception("$exceptionClass is not an exception!");
                default:
                    throw new ForbiddenException($exceptionDescription);
            }
        }
        return null;
    }

    /**
     * Get the current identity from the authentication manager.
     *
     * @return ?IdentityInterface
     */
    public function getIdentity(): ?IdentityInterface
    {
        return $this->authManager->getIdentity();
    }
}
