<?php
/**
 * EIT Record Controller
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
 * @author   Julia Bauder <bauderj@grinnell.edu>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Controller;
use Zend\Mvc\MvcEvent;

/**
 * EIT Record Controller
 * Largely copied from Summon Record Controller
 *
 * @category VuFind2
 * @package  Controller
 * @author   Julia Bauder <bauderj@grinnell.edu>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

class EITrecordController extends AbstractRecord
{
    /**
     * Constructor
     */
    public function __construct()
    {
        // Override some defaults:
        $this->accessPermission = 'access.EITModule';
        $this->searchClassId = 'EIT';
        $this->defaultTab = 'Description';

        // Call standard record controller initialization:
        parent::__construct();
    }

    /**
     * Is the result scroller active?
     *
     * @return bool
     */
    protected function resultScrollerActive()
    {
        $config = $this->getServiceLocator()->get('VuFind\Config')->get('EIT');
        return (isset($config->Record->next_prev_navigation)
            && $config->Record->next_prev_navigation);
    }

}
