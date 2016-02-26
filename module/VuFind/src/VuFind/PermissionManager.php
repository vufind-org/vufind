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

use ZfcRbac\Service\AuthorizationServiceAwareInterface,
    ZfcRbac\Service\AuthorizationServiceAwareTrait;

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
class PermissionManager
{
    use AuthorizationServiceAwareTrait;

    /**
     * List config
     *
     * @var array
     */
    protected $config;

    /**
     * Constructor
     *
     * @param string $mode List mode (enabled or disabled)
     */
    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * Determine if the user is authorized in a certain context or not
     *
     * @param string $context Context for the permission behavior
     *
     * @return bool
     */
    public function isAuthorized($context)
    {
        $authService = $this->getAuthorizationService();

        // if no authorization service is available return false (or the value configured for a denied permission)
        if (!$authService) {
            return false;
        }


        // For a granted permission return the permissionGrantedDisplayLogic
        if ($authService->isGranted($context)) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the user is authorized in a certain context or not
     *
     * @param string $context Context for the permission behavior
     *
     * @return bool
     */
    public function getConfigContext($context)
    {
        return $this->config[$context];
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
        // if a permission is granted, return false as there is nothing the helper needs to do
        if ($this->isAuthorized($context) === true) {
            return false;
        }

        return ($this->config[$context]['permissionDeniedAction']) ? explode(':', $this->config[$context]['permissionDeniedAction']) : false;
    }

    /**
     * Get display logic parameters
     *
     * @param string $context Context for the permission behavior
     *
     * @return array|bool
     */
    protected function getDisplayLogicParameters($context)
    {
        $params = $this->getDisplayLogic($context);

        // if a permission is granted or if no permissionDeniedDisplayLogic has been configured for this context, 
        // return false as there is nothing the helper needs to do
        if ($this->isAuthorized($context) === true || $params === false) {
            return false;
        }

        $p = [ ];
        if (count($params) > 2) {
            for ($n = 2; isset($params[$n]) === true; $n++) {
                $pArray = explode('=', $params[$n]);
                if (count($pArray) == 2) {
                    $p[$pArray[0]] = $pArray[1];
                }
                else {
                    $p[] = $params[$n];
                }
            }
        }

        return $p;
    }

    /**
     * Get block to display
     *
     * @param string $context Context for the permission behavior
     *
     * @return string|bool
     */
    public function getReaction($context)
    {
        $favSaveDisplayLogic = $this->getDisplayLogic($context);
        if ($favSaveDisplayLogic === false) {
            return false;
        }
        $return = '';
        if ($favSaveDisplayLogic[0] == 'showMessage') {
            $return = $tmpl->transEsc($favSaveDisplayLogic[1]);
        }
        elseif ($favSaveDisplayLogic[0] == 'showTemplate') {
            $return = $tmpl->context($tmpl)->renderInContext($favSaveDisplayLogic[1], $this->getDisplayLogicParameters($context));
        }
        return $return;
    }
}