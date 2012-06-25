<?php
/**
 * MobileMenu view helper
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */

/**
 * MobileMenu view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
class VuFind_Theme_Root_Helper_MobileMenu extends Zend_View_Helper_Abstract
{
    protected $controller;
    protected $action;

    /**
     * Constructor
     */
    public function __construct()
    {
        $request = Zend_Controller_Front::getInstance()->getRequest();
        // TODO: is there a better way to make controller/action names consistent?
        $this->controller = preg_replace(
            '/[^\w]/', '', strtolower($request->getControllerName())
        );
        $this->action = preg_replace(
            '/[^\w]/', '', strtolower($request->getActionName())
        );
    }

    /**
     * Get access to the helper object.
     *
     * @return VuFind_Theme_Root_Helper_MobileMenu
     */
    public function mobileMenu()
    {
        return $this;
    }

    /**
     * Display the top menu.
     *
     * @param array $extras Associative array of extra parameters to send to the
     * view template.
     *
     * @return string
     */
    public function header($extras = array())
    {
        $context = array(
            'controller' => $this->controller,
            'action' => $this->action
        ) + $extras;
        return $this->view->context($this->view)->renderInContext(
            'header.phtml', $context
        );
    }

    /**
     * Display the bottom menu.
     *
     * @param array $extras Associative array of extra parameters to send to the
     * view template.
     *
     * @return string
     */
    public function footer($extras = array())
    {
        $context = array(
            'controller' => $this->controller,
            'action' => $this->action,
            'account' => VF_Account_Manager::getInstance()
        ) + $extras;
        return $this->view->context($this->view)->renderInContext(
            'footer.phtml', $context
        );
    }
}