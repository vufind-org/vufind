<?php
/**
 * Admin Statistics Controller
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
 * Class controls VuFind statistical data.
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class StatisticsController extends AbstractAdmin
{
    /**
     * Statistics reporting
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function homeAction()
    {
       
        $view = $this->createViewModel();
        $view->setTemplate('admin/statistics/home');
        $config = $this->getConfig();

        // Search statistics
        $search = $this->getServiceLocator()->get('VuFind\SearchStats');
        $view->searchesBySource = $config->Statistics->searchesBySource ?: false;
        $searchSummary = $search->getStatsSummary(7, $view->searchesBySource);
        foreach (['top', 'empty', 'total'] as $section) {
            $key = $section . 'Searches';
            $view->$key = isset($searchSummary[$section])
                ? $searchSummary[$section] : null;
        }

        // Record statistics
        $records = $this->getServiceLocator()->get('VuFind\RecordStats');
        $view->recordsBySource = $config->Statistics->recordsBySource ?: false;
        $recordSummary = $records->getStatsSummary(5, $view->recordsBySource);
        $view->topRecords = isset($recordSummary['top'])
            ? $recordSummary['top'] : null;
        $view->totalRecordViews = isset($recordSummary['total'])
            ? $recordSummary['total'] : null;

        // Browser statistics
        $view->currentBrowser = $search->getBrowser(
            $this->getRequest()->getServer('HTTP_USER_AGENT')
        );

        // Look for universal statistics recorder
        $matchFound = false;
        foreach ($search->getDriversForSource(null) as $currentDriver) {
            $browserStats = $currentDriver->getBrowserStats(false, 5);
            if (!empty($browserStats)) {
                $matchFound = true;
                break;
            }
        }

        // If no full coverage mode found, take the first valid source
        if (!$matchFound) {
            $drivers = $search->getDriversForSource(null, true);
            foreach ($drivers as $currentDriver) {
                $browserStats = $currentDriver->getBrowserStats(false, 5);
                if (!empty($browserStats)) {
                    $matchFound = true;
                    break;
                }
            }
        }

        // Initialize browser/version data in view based on what we found above:
        if ($matchFound) {
            $view->browserStats = $browserStats;
            $view->topVersions = $currentDriver->getBrowserStats(true, 5);
        } else {
            $view->browserStats = $view->topVersions = null;
        }

        return $view;
    }
}

