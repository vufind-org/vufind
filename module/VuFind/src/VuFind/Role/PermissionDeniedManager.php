<?php

/**
 * Permission Manager.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  Authorization
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/ Wiki
 */

namespace VuFind\Role;

use VuFind\Exception\ConfigException;

use function count;

/**
 * Permission Manager.
 *
 * @category VuFind
 * @package  Authorization
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/ Wiki
 */
class PermissionDeniedManager
{
    /**
     * List config.
     *
     * @var array
     */
    protected $config;

    /**
     * Default behavior for denied permissions at the action level.
     *
     * @var string|bool
     */
    protected $defaultDeniedActionBehavior = 'promptLogin';

    /**
     * Default behavior for denied permissions at the template level.
     * (False means "do nothing").
     *
     * @var string|bool
     */
    protected $defaultDeniedTemplateBehavior = false;

    /**
     * Constructor.
     *
     * @param array $config configuration
     */
    public function __construct($config)
    {
        $this->config = $config;

        // Throw an exception if the config contains legacy settings:
        if (isset($config['global']['defaultDeniedControllerBehavior'])) {
            throw new ConfigException(
                'permissionBehavior configuration must not contain the legacy defaultDeniedControllerBehavior setting'
            );
        }
        foreach ($config as $section => $settings) {
            if (isset($settings['deniedControllerBehavior'])) {
                throw new ConfigException(
                    'permissionBehavior configuration must not contain the legacy deniedControllerBehavior setting'
                    . " (found in [$section])"
                );
            }
        }

        // if the config contains a defaultDeniedActionBehavior setting, apply it:        ;
        if (null !== ($defaultDeniedActionBehavior = $config['global']['defaultDeniedActionBehavior'] ?? null)) {
            $this->defaultDeniedActionBehavior = $defaultDeniedActionBehavior;
        }
        // if the config contains a defaultDeniedTemplateBehavior setting, apply it
        if (null !== ($defaultDeniedTemplateBehavior = $config['global']['defaultDeniedTemplateBehavior'] ?? null)) {
            $this->defaultDeniedTemplateBehavior = $defaultDeniedTemplateBehavior;
        }
    }

    /**
     * Set the default behavior for a denied action permission.
     *
     * @param string|bool $value Default behavior for a denied action permission
     *
     * @return void
     */
    public function setDefaultDeniedActionBehavior($value)
    {
        $this->defaultDeniedActionBehavior = $value;
    }

    /**
     * Set the default behavior for a denied template permission.
     *
     * @param string|bool $value Default behavior for a denied template permission
     *
     * @return void
     */
    public function setDefaultDeniedTemplateBehavior($value)
    {
        $this->defaultDeniedTemplateBehavior = $value;
    }

    /**
     * Get behavior to apply when an action denies a permission.
     *
     * @param string $permission      Permission that has been denied
     * @param string $defaultBehavior Default behavior to use if none configured
     * (null to use default configured in this class, false to take no action).
     *
     * @return array|bool Associative array of behavior for the given permission (containing the keys 'action', 'value',
     * 'params' and 'exceptionMessage' for exceptions) or false if no action needed.
     */
    public function getDeniedActionBehavior($permission, $defaultBehavior = null)
    {
        if ($defaultBehavior === null) {
            $defaultBehavior = $this->defaultDeniedActionBehavior;
        }
        return $this->getDeniedBehavior(
            $permission,
            'deniedActionBehavior',
            $defaultBehavior
        );
    }

    /**
     * Get behavior to apply when a template denies a permission.
     *
     * @param string $permission      Permission that has been denied
     * @param string $defaultBehavior Default action to use if none configured
     * (null to use default configured in this class, false to take no action).
     *
     * @return array|bool
     */
    public function getDeniedTemplateBehavior($permission, $defaultBehavior = null)
    {
        if ($defaultBehavior === null) {
            $defaultBehavior = $this->defaultDeniedTemplateBehavior;
        }
        return $this->getDeniedBehavior(
            $permission,
            'deniedTemplateBehavior',
            $defaultBehavior
        );
    }

    /**
     * Get permission denied logic.
     *
     * @param string $permission      Permission that has been denied
     * @param string $mode            Mode of the operation. Should be either deniedActionBehavior or
     * deniedTemplateBehavior.
     * @param string $defaultBehavior Default action to use if none configured
     *
     * @return array|bool
     */
    protected function getDeniedBehavior($permission, $mode, $defaultBehavior)
    {
        $config = $this->config[$permission][$mode] ?? $defaultBehavior;

        return empty($config) ? false : $this->processConfigString($config);
    }

    /**
     * Translate a configuration string into an array.
     *
     * @param string $config Configuration string to process
     *
     * @return array
     */
    protected function processConfigString($config)
    {
        // Split config string:
        $parts = explode(':', $config);

        // Load standard values:
        $output = [
            'action' => array_shift($parts),
            'value' => array_shift($parts),
        ];

        // Special case -- extra parameters for exceptions:
        if (strtolower($output['action']) === 'exception') {
            $output['exceptionMessage'] = array_shift($parts);
        }

        // Now process any remaining keypairs:
        $params = [];
        while ($param = array_shift($parts)) {
            $paramParts = explode('=', $param, 2);
            if (count($paramParts) == 2) {
                $params[$paramParts[0]] = $paramParts[1];
            } else {
                $params[] = $paramParts[0];
            }
        }
        $output['params'] = $params;
        return $output;
    }
}
