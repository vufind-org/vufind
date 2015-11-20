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
        $view->caches = $this->getServiceLocator()->get('VuFind\CacheManager')
            ->getCacheList();
        $view->setTemplate('admin/maintenance/home');
        return $view;
    }

    /**
     * Clear cache(s).
     *
     * @return mixed
     */
    public function clearcacheAction()
    {
        $cacheManager = $this->getServiceLocator()->get('VuFind\CacheManager');
        foreach ($this->params()->fromQuery('cache', []) as $cache) {
            $cacheManager->getCache($cache)->flush();
        }
        // If cache is unset, we didn't go through the loop above, so no message
        // needs to be displayed.
        if (isset($cache)) {
            $this->flashMessenger()->addMessage('Cache(s) cleared.', 'success');
        }
        return $this->forwardTo('AdminMaintenance', 'Home');
    }

    /**
     * Delete expired searches.
     *
     * @return mixed
     */
    public function deleteexpiredsearchesAction()
    {
        // Delete the expired searches--this cleans up any junk left in the
        // database from old search histories that were not caught by the
        // session garbage collector.
        return $this->expire(
            'Search',
            '%%count%% expired searches deleted.',
            'No expired searches to delete.'
        );
    }

    /**
     * Delete expired sessions.
     *
     * @return mixed
     */
    public function deleteexpiredsessionsAction()
    {
        // Delete the expired sessions--this cleans up any junk left in the
        // database by the session garbage collector.
        return $this->expire(
            'Session',
            '%%count%% expired sessions deleted.',
            'No expired sessions to delete.'
        );
    }

    /**
     * Abstract delete method.
     *
     * @param string $table         Table to operate on.
     * @param string $successString String for reporting success.
     * @param string $failString    String for reporting failure.
     * @param int    $minAge        Minimum age allowed for expiration (also used
     * as default value).
     *
     * @return mixed
     */
    protected function expire($table, $successString, $failString, $minAge = 2)
    {
        $daysOld = intval($this->params()->fromQuery('daysOld', $minAge));
        if ($daysOld < $minAge) {
            $this->flashMessenger()->addMessage(
                str_replace(
                    '%%age%%', $minAge,
                    'Expiration age must be at least %%age%% days.'
                ), 'error'
            );
        } else {
            $search = $this->getTable($table);
            if (!method_exists($search, 'getExpiredQuery')) {
                throw new \Exception($table . ' does not support getExpiredQuery()');
            }
            $query = $search->getExpiredQuery($daysOld);
            if (($count = count($search->select($query))) == 0) {
                $msg = $failString;
            } else {
                $search->delete($query);
                $msg = str_replace('%%count%%', $count, $successString);
            }
            $this->flashMessenger()->addMessage($msg, 'success');
        }
        return $this->forwardTo('AdminMaintenance', 'Home');
    }
}