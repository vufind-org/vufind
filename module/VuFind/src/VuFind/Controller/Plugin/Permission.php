<?php
/**
 * VuFind Action Helper - Permission Checker
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2017.
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Controller_Plugins
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace VuFind\Controller\Plugin;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\Role\PermissionDeniedManager;
use VuFind\Role\PermissionManager;
use Zend\Log\LoggerAwareInterface;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

/**
 * VuFind Action Helper - Permission Checker
 *
 * @category VuFind
 * @package  Controller_Plugins
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Permission extends AbstractPlugin implements LoggerAwareInterface,
    TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Permission manager
     *
     * @var PermissionManager
     */
    protected $permissionManager;

    /**
     * Permission denied manager
     *
     * @var PermissionDeniedManager
     */
    protected $permissionDeniedManager;

    /**
     * Constructor
     *
     * @param PermissionManager       $pm  Permission Manager
     * @param PermissionDeniedManager $pdm Permission Denied Manager
     */
    public function __construct(PermissionManager $pm, PermissionDeniedManager $pdm)
    {
        $this->permissionManager = $pm;
        $this->permissionDeniedManager = $pdm;
    }

    /**
     * Check if a permission is denied; if so, throw an exception or return an
     * error response as configured in permissionBehavior.ini.
     *
     * @param string $permission      Permission to check
     * @param string $defaultBehavior Default behavior to use if none configured
     * (null to use default configured in the manager, false to take no action).
     * @param bool   $passIfUndefined Should the check pass if no rules are
     * defined for $permission in permissions.ini?
     *
     * @return mixed
     */
    public function check($permission, $defaultBehavior = null,
        $passIfUndefined = false
    ) {
        // If no permission rule is defined and we're only checking defined
        // permissions, bail out now....
        if (!$this->permissionManager->permissionRuleExists($permission)
            && $passIfUndefined
        ) {
            return null;
        }

        // Make sure the current user has permission to access the module:
        if ($this->permissionManager->isAuthorized($permission) !== true) {
            $dl = $this->permissionDeniedManager->getDeniedControllerBehavior(
                $permission, $defaultBehavior
            );
            if ($dl === false) {
                return null;
            }
            $exceptionDescription = isset($dl['exceptionMessage'])
                ? $dl['exceptionMessage'] : 'Access denied.';
            switch (strtolower($dl['action'])) {
            case 'promptlogin':
                $msg = empty($dl['value']) ? null : $dl['value'];
                return $this->getController()->forceLogin($msg, [], false);
            case 'showmessage':
                return $this->getController()->redirect()->toRoute(
                    'error-permissiondenied', [],
                    ['query' => ['msg' => $dl['value']]]
                );
            case 'exception':
                $exceptionClass
                    = (isset($dl['value']) && class_exists($dl['value']))
                    ? $dl['value'] : 'VuFind\Exception\Forbidden';
                $exception = new $exceptionClass($exceptionDescription);
                if ($exception instanceof \Exception) {
                    throw $exception;
                }
                $this->logError("Permission configuration problem.");
                throw new \Exception("$exceptionClass is not an exception!");
            default:
                throw new ForbiddenException($exceptionDescription);
            }
        }
        return null;
    }
}
