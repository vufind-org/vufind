<?php
/**
 * Permission Manager
 *
 * PHP version 5
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\Role;

/**
 * Permission Manager
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class PermissionDeniedManager
{
    /**
     * List config
     *
     * @var array
     */
    protected $config;

    /**
     * Default action for denied permissions.
     *
     * @var string
     */
    protected $defaultAction = 'promptLogin';

    /**
     * Constructor
     *
     * @param array $config configuration
     */
    public function __construct($config)
    {
        $this->config = $config;
        // if the config contains a parameter for the defaultAction, apply it
        if (isset($config['global']['defaultAction'])
            && $config['global']['defaultAction']
        ) {
            $this->defaultAction = $config['global']['defaultAction'];
        }
    }

    /**
     * Set the default action for a denied permission
     *
     * @param String $value Default action for a denied permission
     *
     * @return void
     */
    public function setDefaultAction($value)
    {
        $this->defaultAction = $value;
    }

    /**
     * Get action logic
     *
     * @param string $context       Context for the permission behavior
     * @param string $defaultAction Default action to use if none configured
     * (null to use default configured in this class, false to take no action).
     *
     * @return array|bool Associative array of permission behavior for the given
     *                    context (containing the keys action, value and
     *                    exceptionMessage for exceptions). If the permission
     *                    for this context is granted, this method will return
     *                    boolean FALSE. If the context has
     *                    no permissionDeniedAction configuration
     */
    public function getActionLogic($context, $defaultAction = null)
    {
        return $this->getPermissionDeniedLogic(
            $context, 'permissionDeniedAction', $defaultAction
        );
    }

    /**
     * Get display logic
     *
     * @param string $context       Context for the permission behavior
     * @param string $defaultAction Default action to use if none configured
     * (null to use default configured in this class, false to take no action).
     *
     * @return array|bool
     */
    public function getDisplayLogic($context, $defaultAction = false)
    {
        return $this->getPermissionDeniedLogic(
            $context, 'permissionDeniedDisplayLogic', $defaultAction
        );
    }

    /**
     * Get permission denied logic
     *
     * @param string $context       Context for the permission behavior
     * @param string $mode          Mode of the operation. Should be either
     * permissionDeniedAction or permissionDeniedDisplayLogic
     * @param string $defaultAction Default action to use if none configured
     * (null to use default configured in this class, false to take no action).
     *
     * @return array|bool
     */
    protected function getPermissionDeniedLogic($context, $mode,
        $defaultAction = null
    ) {
        if ($mode !== 'permissionDeniedAction'
            && $mode !== 'permissionDeniedDisplayLogic'
        ) {
            throw new \Exception(
                'Error. ' . $mode . ' is not supported by PermissionDeniedManager.'
            );
        }

        $config = !empty($this->config[$context][$mode])
            ? $this->config[$context][$mode]
            : ($defaultAction === null ? $this->defaultAction : $defaultAction);
    
        return ($config === false) ? false : $this->processConfigString($config);
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
