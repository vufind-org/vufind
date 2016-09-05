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
use VuFind\PermissionDeniedManager as PermissionDeniedManager;
use VuFind\PermissionManager as PermissionManager;
use Zend\View\Helper\AbstractHelper;

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
    /**
     * PermissionDenied manager for behavior on denied permissions
     *
     * @var PermissionDeniedManager
     */
    protected $permissionDeniedManager;

    /**
     * Permission manager to decide if a permission has been granted or not
     *
     * @var PermissionManager
     */
    protected $permissionManager;

    /**
     * Constructor
     *
     * @param PermissionsManager       $permissionManager       Manager to decide
     *                                                          if a permission has
     *                                                          been granted or not
     * @param PermissionsDeniedManager $permissionDeniedManager Manager for
     *                                                          behavior on
     *                                                          denied permissions
     */
    public function __construct(
        PermissionManager $permissionManager,
        PermissionDeniedManager $permissionDeniedManager
    ) {
        $this->permissionManager = $permissionManager;
        $this->permissionDeniedManager = $permissionDeniedManager;
    }

    /**
     * Determine if a local block inside the template should be displayed
     *
     * @param string $context Name of the permission rule
     *
     * @return bool
     */
    public function showLocalBlock($context)
    {
        // Treat a non existing permission rule in this case as a granted permssion
        // Just return true to indicate that the default can get applied
        if ($this->permissionManager->permissionRuleExists($context) === false) {
            return true;
        }
        // If permission has been granted, we do not need to continue
        // Just return true to indicate that the default can get applied
        if ($this->permissionManager->isAuthorized($context) === true) {
            return true;
        }
        // If we are getting to this point, we know that the permission has been
        // denied. Nevertheless show the local block if there is no
        // permissionDeniedDisplayLogic set for this context.
        $displayLogic = $this->permissionDeniedManager->getDisplayLogic($context);
        if (!isset($displayLogic['action'])) {
            return true;
        }
        return false;
    }

    /**
     * Get block to display
     *
     * @param string $context Name of the permission rule
     *
     * @return string
     */
    public function getDisplayBlock($context)
    {
        $displayLogic = $this->permissionDeniedManager->getDisplayLogic($context);

        if ($displayLogic) {
            $return = '';
            if ($displayLogic['action'] == 'showMessage') {
                $return = $this->view->translate($displayLogic['value']);
            }
            elseif ($displayLogic['action'] == 'showTemplate') {
                $return = $this->view->context($this->view)->renderInContext(
                    $displayLogic['value'],
                    $this->permissionDeniedManager->getDisplayLogicParameters($context)
                );
            }
            return $return;
        }
        return null;
    }
}