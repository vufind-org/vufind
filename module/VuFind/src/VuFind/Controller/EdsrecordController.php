<?php
/**
 * EDS Record Controller
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
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Controller;

/**
 * EDS Record Controller
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class EdsrecordController extends AbstractRecord
{
    /**
     * Constructor
     */
    public function __construct()
    {
        // Override some defaults:
        $this->searchClassId = 'EDS';
        $this->fallbackDefaultTab = 'Description';

        // Call standard record controller initialization:
        parent::__construct();
    }

    /**
     * PDF display action.
     *
     * @return mixed
     */
    public function pdfAction()
    {
        $driver = $this->loadRecord();
        //if the user is a guest, redirect them to the login screen.
        if (!$this->isAuthenticationIP() && false == $this->getUser()) {
            return $this->forceLogin();
        } else {
            return $this->redirect()->toUrl($driver->getPdfLink());
        }
    }

    /**
     * Is the result scroller active?
     *
     * @return bool
     */
    protected function resultScrollerActive()
    {
        $config = $this->getServiceLocator()->get('VuFind\Config')->get('EDS');
        return (isset($config->Record->next_prev_navigation)
            && $config->Record->next_prev_navigation);
    }

     /**
     * Is IP Authentication being used?
     *
     * @return bool
     */
    protected function isAuthenticationIP()
    {
        $config = $this->getServiceLocator()->get('VuFind\Config')->get('EDS');
        return (isset($config->EBSCO_Account->ip_auth)
            && 'true' ==  $config->EBSCO_Account->ip_auth);
    }
}