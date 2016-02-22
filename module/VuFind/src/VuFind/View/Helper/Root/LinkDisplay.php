<?php
/**
 * Link display helper
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
namespace VuFind\View\Helper\Root;
use Zend\View\Helper\AbstractHelper;

use ZfcRbac\Service\AuthorizationServiceAwareInterface,
    ZfcRbac\Service\AuthorizationServiceAwareTrait;

/**
 * Link display helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class LinkDisplay extends AbstractHelper
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
     * Get mode
     *
     * @param string $context Context for the permission behavior
     *
     * @return string
     */
    public function getPermissionBehavior($context)
    {
        return $this->config[$context];
    }


    /**
     * Get display logic for a denied permission
     *
     * @param string $context Context for the permission behavior
     *
     * @return array
     */
    public function getDisplayLogic($context)
    {
        $authService = $this->getAuthorizationService();

        // if no authorization service is available return false (or the value configured for a denied permission)
        if (!$authService) {
            return explode(':', $this->getPermissionBehavior($context)['permissionDeniedDisplayLogic']);
        }

        // For a granted permission always terurn true
        if ($authService->isGranted($context)) {
            return ($this->getPermissionBehavior($context)['permissionGrantedDisplayLogic']) ? explode(':', $this->getPermissionBehavior($context)['permissionGrantedDisplayLogic']) : [ 0 => 'showLink' ];
        }

        return explode(':', $this->getPermissionBehavior($context)['permissionDeniedDisplayLogic']);
    }

    /**
     * Get display logic parameters
     *
     * @param string $context Context for the permission behavior
     *
     * @return array
     */
    public function getDisplayLogicParameters($context)
    {
        $params = explode(':', $this->getPermissionBehavior($context)['permissionDeniedDisplayLogic']);

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
}