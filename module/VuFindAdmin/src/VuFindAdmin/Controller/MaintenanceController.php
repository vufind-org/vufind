<?php
/**
 * Admin Maintenance Controller
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
namespace VuFindAdmin\Controller;

/**
 * Class helps maintain database
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

class MaintenanceController extends AbstractAdmin
{
    /**
     * System Maintenance
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function homeAction()
    {
        $view = $this->createViewModel();
        $view->setTemplate('admin/maintenance/home');
        return $view;
    }

    /**
     * Delete expired searches.
     *
     * @return mixed
     */
    public function deleteexpiredsearchesAction()
    {
        $daysOld = intval($this->params()->fromQuery('daysOld', 2));
        if ($daysOld < 2) {
            $this->flashMessenger()->setNamespace('error')
                ->addMessage(
                    'Expiration age must be at least two days.'
                );
        } else {
            // Delete the expired searches--this cleans up any junk left in the
            // database from old search histories that were not caught by the
            // session garbage collector.
            $search = $this->getTable('Search');
            $query = $search->getExpiredQuery($daysOld);
            if (($count = count($search->select($query))) == 0) {
                $msg = "No expired searches to delete.";
            } else {
                $search->delete($query);
                $msg = "{$count} expired searches deleted.";
            }
            $this->flashMessenger()->setNamespace('info')->addMessage($msg);
        }
        return $this->forwardTo('AdminMaintenance', 'Home');
    }
}

