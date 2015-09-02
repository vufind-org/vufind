<?php
/**
 * Primo Permission Controller..
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2013.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Search
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Search\Primo;

use ZfcRbac\Service\AuthorizationServiceAwareInterface,
    ZfcRbac\Service\AuthorizationServiceAwareTrait;

/**
 * Primo Permission Controller.
 *
 * @category VuFind2
 * @package  Search
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class PrimoPermissionController
{
    use AuthorizationServiceAwareTrait;

    /**
     * Primo-Config for InstitutionPermissions.
     *
     * @var array
     */
    protected $primoConfig;


    /**
     * Institution code applicable for the user
     *
     * @var string
     */
    protected $instCode;

    /**
     * Constructor.
     *
     * @param Zend\Config\Config|array $primoConfig Primo-Config for
     * InstitutionPermissions
     *
     * @return void
     */
    public function __construct($primoPermConfig)
    {
        $this->instCode = null;
        if (null === $primoPermConfig) {
            throw new \Exception('The Primo Permission System has not been '
                . 'configured. Please configure section [InstitutionPermission] '
                . 'in Primo.ini.'
            );
        }
        if (is_array($primoPermConfig)) {
            $this->primoConfig = $primoPermConfig;
        } else {
            $this->primoConfig = $primoPermConfig->toArray();
        }
    }

    /**
     * Determines the permissions of the user
     *
     * @return void
     */
    protected function detectPermissions()
    {
        $this->defaultPermissionLevel = false;

        $codes = isset($this->primoConfig['code'])
            ? $this->primoConfig['code'] : [];
        $permRules = isset($this->primoConfig['permissionRule'])
            ? $this->primoConfig['permissionRule'] : [];
        if ((
            empty($codes) && empty($permRules)
            && !(isset($this->primoConfig['defaultCode']))
            )
            || count($codes) != count($permRules)
        ) {
            throw new \Exception('[InstitutionPermission] section in Primo.ini is not '
                . 'configured properly. Please check the section.'
            );
        }

        $authService = $this->getAuthorizationService();

        // if no authorization service is available, don't do anything
        if (!$authService) {
            return false;
        }

        // walk through the permissionRules and check, if one of them is granted
        for ($i = 0; $i < count($permRules); $i++) {
            if ($authService->isGranted($permRules[$i])) {
                $this->instCode = $codes[$i];
            }
        }

        // if no rule has matched, assume the user gets the default code
        if (isset($this->primoConfig['defaultCode'])) {
            $this->defaultPermissionLevel = true;
            $this->instCode = $this->primoConfig['defaultCode'];
        }

        // if no institution code has been found for this config, set it to false
        // Primo will not work without an institution code!
        if ($this->instCode === null) {
            $this->instCode = false;
        }

    }

    /**
     * Determine the institution code
     * Returns false, if no institution can get set
     *
     * @return string|boolean
     */
    public function getInstCode()
    {
        if ($this->instCode === null) {
            $this->detectPermissions();
        }
        return $this->instCode;
    }

    /**
     * Get the users authentication status
     *
     * @return boolean
     */
    public function isAuthenticated()
    {
        if ($this->instCode === null) {
            $this->detectPermissions();
        }
        if (false !== $this->instCode) {
            return true;
        }
        return false;
    }

    /**
     * Determine the default Permission Rule
     *
     * @return string
     */
    protected function getDefaultPermissionRule()
    {
        $defaultPermissionRule
            = isset($this->primoConfig['defaultPermissionRule'])
            ? $this->primoConfig['defaultPermissionRule'] : false;

        if (false !== $defaultPermissionRule) {
            return $defaultPermissionRule;
        }

        // If primoConfig->defaultPermissionRule is not set
        // no rule can get applied.
        // So return null to indicate that nothing can get matched.
        return null;
    }

    /**
     * Determine, if the user is on the default Permission Level.
     *
     * @return bool
     */
    public function isOnDefaultPermission()
    {
        return $this->defaultPermissionLevel;
    }

    /**
     * Checks, if the default rule is granted
     *
     * @return bool
     */
    public function checkDefaultPermission()
    {
        $defRule = $this->getDefaultPermissionRule();

        // if no default rule is configured, return false.
        if (null === $defRule) {
            return false;
        }

        $authService = $this->getAuthorizationService();

        // if no authorization service is available, don't do anything
        if (!$authService) {
            return;
        }

        if ($authService->isGranted($defRule)) {
            return true;
        }

        return false;
    }

}
