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
class PrimoPermissionHandler
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
     * @param Zend\Config\Config|array $primoPermConfig Primo-Config for
     * InstitutionOnCampus
     *
     * @return void
     */
    public function __construct($primoPermConfig)
    {
        // Initialize instCode
        $this->instCode = null;

        if (is_array($primoPermConfig)) {
            $this->primoConfig = $primoPermConfig;
        } else {
            $this->primoConfig = $primoPermConfig->toArray();
        }

        $this->checkConfig();
    }

    /**
     * Set the institution code (no autodetection)
     *
     * @param string $code Institutioncode
     *
     * @return void
     */
    public function setInstCode($code)
    {
        // When we try setting the institution code, set it to false
        // to indicate that it is not null any more
        $this->instCode = false;

        // and then check if this code is valid in the configuration
        if ($this->instCodeExists($code) === true) {
            $this->instCode = $code;
        }
    }

    /**
     * Determine if a institution code is set in config file
     *
     * @param string $code Code to approve against config file
     *
     * @return boolean
     */
    public function instCodeExists($code)
    {
        if (in_array($code, $this->getInstCodes()) === true) {
            return true;
        }
        return false;
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
            $this->autodetectCode();
        }
        return $this->instCode;
    }

    /**
     * Check if the user has permission
     *
     * @return boolean
     */
    public function hasPermission()
    {
        if ($this->instCode === null) {
            $this->autodetectCode();
        }
        if (false !== $this->instCode
            && $this->checkPermission($this->instCode) === true
        ) {
            return true;
        }
        // if its not set, the user has no permission
        return false;
    }

    /**
     * Checks the config file section for validity
     *
     * @return void
     */
    protected function checkConfig()
    {
        if (isset($this->primoConfig['institutionCode'])) {
            return;
        }

        if (isset($this->primoConfig['onCampusRule'])) {
            return;
        }

        // if no rule has matched until here, assume the user gets the default code
        if ($this->getDefaultCode() !== false) {
            return;
        }

        // If we reach this point, no institution code is set in config.
        // Primo will not work without an institution code!
        throw new \Exception(
            'No institutionCode found. Please be sure, that at least a '
            . 'defaultCode is configured in section [InstitutionOnCampus] '
            . 'in Primo.ini.'
        );
    }

    /**
     * Gets all possible institution codes from config file
     *
     * @return array Array with valid Primo institution codes
     */
    protected function getInstCodes()
    {
        $codes = [ ];

        if ($this->getDefaultCode() !== false) {
            $codes[] = $this->getDefaultCode();
        }

        if (isset($this->primoConfig['institutionCode'])
            && is_array($this->primoConfig['institutionCode']) === true
        ) {
            foreach ($this->primoConfig['institutionCode'] as $code => $permRule) {
                if (in_array($code, $codes) === false) {
                    $codes[] = $code;
                }
            }
        }

        if (isset($this->primoConfig['onCampusRule'])
            && is_array($this->primoConfig['onCampusRule']) === true
        ) {
            foreach ($this->primoConfig['onCampusRule'] as $code => $permRule) {
                if (in_array($code, $codes) === false) {
                    $codes[] = $code;
                }
            }
        }

        return $codes;
    }

    /**
     * Autodetects the permissions by configuration file
     *
     * @return void
     */
    protected function autodetectCode()
    {
        $authService = $this->getAuthorizationService();

        // if no authorization service is available, don't do anything
        if (!$authService) {
            $this->instCode = false;
            return;
        }

        // walk through the institutionCodes and check, if one of them is granted
        if (isset($this->primoConfig['institutionCode'])
            && is_array($this->primoConfig['institutionCode']) === true
        ) {
            foreach ($this->primoConfig['institutionCode'] as $code => $permRule) {
                if ($authService->isGranted($permRule)) {
                    $this->instCode = $code;
                    return;
                }
            }
        }

        // if none of the institutionCodes matched, walk through the permissionRules
        // and check, if one of them is granted
        if (isset($this->primoConfig['onCampusRule'])
            && is_array($this->primoConfig['onCampusRule']) === true
        ) {
            foreach ($this->primoConfig['onCampusRule'] as $code => $permRule) {
                if ($authService->isGranted($permRule)) {
                    $this->instCode = $code;
                    return;
                }
            }
        }

        // if no rule has matched until here, assume the user gets the default code
        if ($this->getDefaultCode() !== false) {
            $this->instCode = $this->getDefaultCode();
            return;
        }

        // Autodetection failed, set instCode to false
        // Primo will not work without an institution code!
        if ($this->instCode === null) {
            $this->instCode = false;
        }

    }

    /**
     * Determine the default institution code
     * Returns false, if no default code has been set
     *
     * @return string|boolean
     */
    protected function getDefaultCode()
    {
        return (isset($this->primoConfig['defaultCode']))
            ? $this->primoConfig['defaultCode'] : false;
    }

    /**
     * Determine the default onCampus Rule
     *
     * @return string
     */
    protected function getDefaultOnCampusRule()
    {
        $defaultCode = $this->getDefaultCode();
        if ($defaultCode === false) {
            return null;
        }

        return $this->getOnCampusRule($defaultCode);
    }

    /**
     * Determine a onCampus Rule for a certain code
     *
     * @param string $code Code to determine the rule name for
     *
     * @return string
     */
    protected function getOnCampusRule($code)
    {
        if ($code === null) {
            return null;
        }

        $onCampusRule
            = isset($this->primoConfig['onCampusRule'][$code])
            ? $this->primoConfig['onCampusRule'][$code] : false;

        if (false !== $onCampusRule) {
            return $onCampusRule;
        }

        // If primoConfig->onCampusRule[] is not set
        // no rule can get applied.
        // So return null to indicate that nothing can get matched.
        return null;
    }

    /**
     * Checks, if a rule is granted
     *
     * @param string $code Code to check the rule name for
     *
     * @return bool
     */
    protected function checkPermission($code)
    {
        $onCampusRule = $this->getOnCampusRule($code);
        $authService = $this->getAuthorizationService();

        // if no authorization service is available, don't do anything
        if (!$authService) {
            return false;
        }

        if ($authService->isGranted($onCampusRule)) {
            return true;
        }

        return false;
    }
}
