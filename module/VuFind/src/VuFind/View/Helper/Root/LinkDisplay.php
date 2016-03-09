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
     * Constructor
     *
     * @param PermissionsDeniedManager $permissionDeniedManager Search options
     *                                                          plugin manager
     */
    public function __construct(
        PermissionDeniedManager $permissionDeniedManager
    ) {
        $this->permissionDeniedManager = $permissionDeniedManager;
    }

    /**
     * Get block to display
     *
     * @param string                         $context Context for the permission
     *                                                behavior
     *
     * @return string|bool
     */
    public function getDisplayBlock($context)
    {
        $favSaveDisplayLogic = $this->permissionDeniedManager
            ->getDisplayLogic($context);
        if ($favSaveDisplayLogic === false) {
            return false;
        }
        $return = '';
        if ($favSaveDisplayLogic['action'] == 'showMessage') {
            $return = $this->view->translate($favSaveDisplayLogic['value']);
        }
        elseif ($favSaveDisplayLogic['action'] == 'showTemplate') {
            $return = $this->view->context($this->view)->renderInContext(
                $favSaveDisplayLogic['value'],
                $this->permissionDeniedManager->getDisplayLogicParameters($context)
            );
        }
        return $return;
    }
}