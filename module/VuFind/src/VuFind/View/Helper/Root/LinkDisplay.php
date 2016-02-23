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
     * Get permission behavior
     *
     * @param string $context Context for the permission behavior
     *
     * @return string
     */
    protected function getPermissionBehavior($context)
    {
        return $this->config[$context];
    }


    /**
     * Get display logic
     *
     * @param string $context Context for the permission behavior
     *
     * @return array
     */
    protected function isAuthorized($context)
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
     * Get display logic
     *
     * @param string $context Context for the permission behavior
     *
     * @return array
     */
    public function getDisplayLogic($context)
    {
        $permResult = $this->isAuthorized($context);

        $permResultDisplayLogic = 'permissionDeniedDisplayLogic';

        // For a granted permission return the permissionGrantedDisplayLogic
        if ($permResult === true) {
            $permResultDisplayLogic = 'permissionGrantedDisplayLogic';
        }

        return ($this->getPermissionBehavior($context)[$permResultDisplayLogic]) ? explode(':', $this->getPermissionBehavior($context)[$permResultDisplayLogic]) : [ ];
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
        $permResult = $this->isAuthorized($context);

        $permResultDisplayLogic = 'permissionDeniedDisplayLogic';

        // For a granted permission return the permissionGrantedDisplayLogic
        if ($permResult === true) {
            $permResultDisplayLogic = 'permissionGrantedDisplayLogic';
        }

        $params = explode(':', $this->getPermissionBehavior($context)[$permResultDisplayLogic]);

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
     * @return string
     */
    public function getDisplayBlock($context, $tmpl)
    {
        $favSaveDisplayLogic = $this->getDisplayLogic($context);
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