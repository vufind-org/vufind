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
namespace VuFind;

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
     * @var String $defaultAction Defaults to 'promptlogin'
     */
    protected $defaultAction = 'promptlogin';

    /**
     * Constructor
     *
     * @param array $config configuration
     */
    public function __construct($config)
    {
        $this->config = $config;
        // if the config contains a parameter for the defaultAction, apply it
        if (
            isset($config['global']['defaultAction'])
            && $config['global']['defaultAction']
        ) {
            $this->defaultAction = $config['global']['defaultAction'];
        }
    }

    /**
     * Set the default action for a denied permission
     *
     * @param String $value Default action for a denied permission
     */
    public function setDefaultAction($value)
    {
        $this->defaultAction = $value;
    }

    /**
     * Get action logic
     *
     * @param string $context Context for the permission behavior
     *
     * @return array|bool Associative array of permission behavior for the given
     *                    context (containing the keys action, value and
     *                    exceptionMessage for exceptions). If the permission
     *                    for this context is granted, this method will return
     *                    boolean FALSE. If the context has
     *                    no permissionDeniedAction configuration
     */
    public function getActionLogic($context)
    {
        $permissionDeniedAction = null;
        $permissionDeniedActions = $this->getPermissionDeniedLogic($context, 'permissionDeniedAction');
        if ($permissionDeniedActions === false) {
            $permissionDeniedAction = $permissionDeniedActions;
        } else if ($permissionDeniedActions) {
            $permissionDeniedAction = [
                'action' => $permissionDeniedActions[0]
            ];
            if (isset($permissionDeniedActions[1])) {
                $permissionDeniedAction['value']
                    = $permissionDeniedActions[1];
            }
            if (isset($permissionDeniedActions[2])
                && $permissionDeniedActions[0] == 'exception'
            ) {
                $permissionDeniedAction['exceptionMessage']
                    = $permissionDeniedActions[2];
            }
        }

        return $permissionDeniedAction;
    }

    /**
     * Get display logic
     *
     * @param string $context Context for the permission behavior
     *
     * @return array|bool
     */
    public function getDisplayLogic($context)
    {
        $permissionDeniedAction = null;
        $permissionDeniedActions = $this->getPermissionDeniedLogic($context, 'permissionDeniedDisplayLogic');
        // If $permissionDeniedActions is false, this means, that permission is denied,
        // but there is no behaviour configured for that
        if ($permissionDeniedActions === false) {
            $permissionDeniedAction = $permissionDeniedActions;
        } else if ($permissionDeniedActions) {
            $permissionDeniedAction = [
                'action' => $permissionDeniedActions[0]
            ];
            if (isset($permissionDeniedActions[1])) {
                $permissionDeniedAction['value']
                    = $permissionDeniedActions[1];
            }
            if (isset($permissionDeniedActions[2])
                && $permissionDeniedActions[0] == 'exception'
            ) {
                $permissionDeniedAction['exceptionMessage']
                    = $permissionDeniedActions[2];
            }
        }

        return $permissionDeniedAction;
    }

    /**
     * Get action logic parameters
     *
     * @param string $context Context for the permission behavior
     *
     * @return array|bool
     */
    public function getActionLogicParameters($context)
    {
        return $this->getPermissionDeniedParameters($context, 'permissionDeniedAction');
    }

    /**
     * Get display logic parameters
     *
     * @param string $context Context for the permission behavior
     *
     * @return array|bool
     */
    public function getDisplayLogicParameters($context)
    {
        return $this->getPermissionDeniedParameters($context, 'permissionDeniedDisplayLogic');
    }

    /**
     * Get permission denied logic
     *
     * @param string $context Context for the permission behavior
     * @param string $mode    Mode of the operation. Should be either
     *                        permissionDeniedAction or permissionDeniedDisplayLogic
     *
     * @return array
     */
    protected function getPermissionDeniedLogic($context, $mode)
    {
        if (
            $mode !== 'permissionDeniedAction'
            && $mode !== 'permissionDeniedDisplayLogic'
        ) {
            throw new Exception(
                'Error. ' . $mode . ' is not supported by PermissionDeniedManager.'
            );
        }
        if (isset($this->config[$context][$mode])
            && $this->config[$context][$mode]
        ) {
            return explode(':', $this->config[$context][$mode]);
        }
        // This context has not been configured at all
        if ($mode == 'permissionDeniedAction') {
            return [ 0 => $this->defaultAction ];
        } else {
            return false;
        }
    }

    /**
     * Get action logic parameters
     *
     * @param string $context Context for the permission behavior
     *
     * @return array|bool
     */
    protected function getPermissionDeniedParameters($context, $mode)
    {
        $params = $this->getPermissionDeniedLogic($context, $mode);
        $p = [ ];
        // Normally start parameters at index position 2
        $startAtIndex = 2;
        if (isset($params[2]) && $params[0] == 'exception') {
            // But if this is an exception, start at index position 3
            $startAtIndex = 3;
        }
        if (count($params) > $startAtIndex) {
            for ($n = $startAtIndex; isset($params[$n]) === true; $n++) {
                $pArray = explode('=', $params[$n]);
                if (count($pArray) == 2) {
                    $p[$pArray[0]] = $pArray[1];
                } else {
                    $p[] = $params[$n];
                }
            }
        }

        return $p;
    }

}