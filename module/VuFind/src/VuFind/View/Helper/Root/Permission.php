<?php

/**
 * Permission helper
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2017.
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
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/ Wiki
 */

namespace VuFind\View\Helper\Root;

use Laminas\View\Helper\AbstractHelper;
use VuFind\Role\PermissionDeniedManager;
use VuFind\Role\PermissionManager;

/**
 * Permission helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/ Wiki
 */
class Permission extends AbstractHelper
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
     * @param PermissionManager       $permissionManager       Manager to decide if a permission has been granted or
     * not
     * @param PermissionDeniedManager $permissionDeniedManager Manager for behavior on denied permissions
     */
    public function __construct(
        PermissionManager $permissionManager,
        PermissionDeniedManager $permissionDeniedManager
    ) {
        $this->permissionManager = $permissionManager;
        $this->permissionDeniedManager = $permissionDeniedManager;
    }

    /**
     * Determine if the current user is authorized for a permission.
     *
     * @param string $context Name of the permission rule
     *
     * @return bool
     */
    public function isAuthorized($context)
    {
        return $this->permissionManager->isAuthorized($context) === true;
    }

    /**
     * Determine if a local block inside the template should be displayed
     *
     * @param string $context Name of the permission rule
     *
     * @return bool
     */
    public function allowDisplay($context)
    {
        // If there is no context, assume permission is granted.
        if (empty($context)) {
            return true;
        }

        // If permission has been granted, we do not need to continue
        // Just return true to indicate that the default can get applied
        if ($this->permissionManager->isAuthorized($context) === true) {
            return true;
        }
        // If we are getting to this point, we know that the permission has been
        // denied. Nevertheless show the local block if there is no
        // deniedTemplateBehavior set for this context.
        $displayLogic = $this->permissionDeniedManager
            ->getDeniedTemplateBehavior($context);
        return !isset($displayLogic['action']);
    }

    /**
     * Get content to display in place of blocked content
     *
     * @param string $context Name of the permission rule
     *
     * @return string
     */
    public function getAlternateContent($context)
    {
        $displayLogic = $this->permissionDeniedManager
            ->getDeniedTemplateBehavior($context);

        switch ($displayLogic['action'] ?? '') {
            case 'showMessage':
                return $this->view->transEsc($displayLogic['value']);
            case 'showTemplate':
                return $this->view->context($this->view)->renderInContext(
                    $displayLogic['value'],
                    $displayLogic['params']
                );
            default:
                return null;
        }
    }
}
