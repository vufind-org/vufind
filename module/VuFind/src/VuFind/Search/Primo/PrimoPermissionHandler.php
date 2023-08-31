<?php

/**
 * Primo Permission Handler.
 *
 * PHP version 8
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Search
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Search\Primo;

use LmcRbacMvc\Service\AuthorizationServiceAwareTrait;

use function in_array;
use function is_array;

/**
 * Primo Permission Handler.
 *
 * @category VuFind
 * @package  Search
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class PrimoPermissionHandler
{
    use AuthorizationServiceAwareTrait;

    /**
     * Primo-Config for Institutions.
     *
     * @var array
     */
    protected $primoConfig;

    /**
     * Institution code applicable for the user
     *
     * @var string
     */
    protected $instCode = null;

    /**
     * Constructor.
     *
     * @param Laminas\Config\Config|array $primoPermConfig Primo-Config for
     * Institutions
     *
     * @return void
     */
    public function __construct($primoPermConfig)
    {
        if ($primoPermConfig instanceof \Laminas\Config\Config) {
            $primoPermConfig = $primoPermConfig->toArray();
        }
        $this->primoConfig = is_array($primoPermConfig) ? $primoPermConfig : [];
        $this->checkLegacySettings();
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
        // If the code is valid, we'll set it; otherwise, we'll use "false" to
        // clear instCode's null status and indicate that the setter has been used.
        $this->instCode = ($this->instCodeExists($code) === true) ? $code : false;
    }

    /**
     * Determine if a institution code is set in config file
     *
     * @param string $code Code to approve against config file
     *
     * @return bool
     */
    public function instCodeExists($code)
    {
        return in_array($code, $this->getInstCodes()) === true;
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
     * @return bool
     */
    public function hasPermission()
    {
        $code = $this->getInstCode();
        return false !== $code && $this->checkPermission($code) === true;
    }

    /**
     * Checks the config file section for validity
     *
     * @return void
     */
    protected function checkConfig()
    {
        if (
            isset($this->primoConfig['institutionCode'])
            || isset($this->primoConfig['onCampusRule'])
            || ($this->getDefaultCode() !== false)
        ) {
            return;
        }

        // If we reach this point, no institution code is set in config.
        // Primo will not work without an institution code!
        throw new \Exception(
            'No institutionCode found. Please be sure that at least a '
            . 'defaultCode is configured in section [Institutions] '
            . 'in Primo.ini.'
        );
    }

    /**
     * Legacy settings support
     *
     * @return void
     */
    protected function checkLegacySettings()
    {
        // if we already have settings, ignore the legacy ones
        if (
            isset($this->primoConfig['defaultCode'])
            || isset($this->primoConfig['onCampusRule'])
        ) {
            return;
        }

        // Handle legacy options
        $codes = $this->primoConfig['code'] ?? [];
        $regex = $this->primoConfig['regex'] ?? [];
        if (!empty($codes) && !empty($regex)) {
            throw new \Exception(
                'Legacy [Institutions] settings detected.'
                . ' Please run upgrade process or correct settings manually'
                . ' in Primo.ini and permissions.ini.'
            );
        }
    }

    /**
     * Gets all possible institution codes from config file
     *
     * @return array Array with valid Primo institution codes
     */
    protected function getInstCodes()
    {
        // Start with default code (if any):
        $defaultCode = $this->getDefaultCode();
        $codes = ($defaultCode !== false) ? [$defaultCode] : [];

        // Add additional keys from relevant config sections:
        foreach (['institutionCode', 'onCampusRule'] as $section) {
            if (
                isset($this->primoConfig[$section])
                && is_array($this->primoConfig[$section])
            ) {
                $codes = array_merge(
                    $codes,
                    array_keys($this->primoConfig[$section])
                );
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

        // walk through the relevant config sections and check if one is granted
        foreach (['institutionCode', 'onCampusRule'] as $section) {
            if (
                isset($this->primoConfig[$section])
                && is_array($this->primoConfig[$section])
            ) {
                foreach ($this->primoConfig[$section] as $code => $permRule) {
                    if ($authService->isGranted($permRule)) {
                        $this->instCode = $code;
                        return;
                    }
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
        return $this->primoConfig['defaultCode'] ?? false;
    }

    /**
     * Determine the default onCampus Rule
     *
     * @return string
     */
    protected function getDefaultOnCampusRule()
    {
        $defaultCode = $this->getDefaultCode();
        return ($defaultCode !== false)
            ? $this->getOnCampusRule($defaultCode) : null;
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
            = $this->primoConfig['onCampusRule'][$code] ?? false;

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

        // if no authorization service is available, the user can't get permission
        return $authService && $authService->isGranted($onCampusRule);
    }
}
