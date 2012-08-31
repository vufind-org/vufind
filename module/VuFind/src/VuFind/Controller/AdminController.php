<?php
/**
 * Admin Controller
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
use VuFind\Config\Reader as ConfigReader, VuFind\Db\Table\Search as SearchTable,
    VuFind\Exception\Forbidden as ForbiddenException,
    VuFind\Http\Client as HttpClient, Zend\Mvc\MvcEvent,
    VuFind\Statistics\AbstractBase as Statistics;

/**
 * Class controls VuFind administration.
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

class AdminController extends AbstractBase
{
    /**
     * preDispatch -- block access when appropriate.
     *
     * @param MvcEvent $e Event object
     *
     * @return void
     */
    public function preDispatch(MvcEvent $e)
    {
        // Disable search box in Admin module:
        $this->layout()->searchbox = false;

        // If we're using the "disabled" action, we don't need to do any further
        // checking to see if we are disabled!!
        $routeMatch = $e->getRouteMatch();
        if (strtolower($routeMatch->getParam('action')) == 'disabled') {
            return;
        }

        // Block access to everyone when module is disabled:
        $config = ConfigReader::getConfig();
        if (!isset($config->Site->admin_enabled) || !$config->Site->admin_enabled) {
            $routeMatch->setParam('action', 'disabled');
            return;
        }

        // Block access by IP when IP checking is enabled:
        if (isset($config->AdminAuth->ipRegEx)) {
            $ipMatch = preg_match(
                $config->AdminAuth->ipRegEx,
                $this->getRequest()->getServer()->get('REMOTE_ADDR')
            );
            if (!$ipMatch) {
                throw new ForbiddenException('Access denied.');
            }
        }

        // Block access by username when user whitelist is enabled:
        if (isset($config->AdminAuth->userWhitelist)) {
            $user = $this->getUser();
            if ($user == false) {
                $e->setResponse($this->forceLogin(null, array(), false));
                return;
            }
            $matchFound = false;
            foreach ($config->AdminAuth->userWhitelist as $check) {
                if ($check == $user->username) {
                    $matchFound = true;
                    break;
                }
            }
            if (!$matchFound) {
                throw new ForbiddenException('Access denied.');
            }
        }
    }

    /**
     * Register the default events for this controller
     *
     * @return void
     */
    protected function attachDefaultListeners()
    {
        parent::attachDefaultListeners();
        $events = $this->getEventManager();
        $events->attach(MvcEvent::EVENT_DISPATCH, array($this, 'preDispatch'), 1000);
    }

    /**
     * Display disabled message.
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function disabledAction()
    {
        return $this->createViewModel();
    }

    /**
     * Admin home.
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function homeAction()
    {
        $config = ConfigReader::getConfig();
        $xml = false;
        if (isset($config->Index->url)) {
            $client = new HttpClient($config->Index->url . '/admin/multicore');
            $response = $client->setMethod('GET')->send();
            $xml = $response->isSuccess() ? $response->getBody() : false;
        }
        $view = $this->createViewModel();
        $view->xml = $xml ? simplexml_load_string($xml) : false;
        return $view;
    }

    /**
     * Statistics reporting
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function statisticsAction()
    {
        $view = $this->createViewModel();

        $config = ConfigReader::getConfig();
        $statsFilled = array(
            'search' => false,
            'record' => false
        );
        // Search statistics
        $search = new \VuFind\Statistics\Search();
        $view->searchesBySource
            = $config->Statistics->searchesBySource
            ?: false;
        $searchSummary = $search->getStatsSummary(
            $config, 7, $config->Statistics->searchesBySource
        );
        $view->topSearches = isset($searchSummary['top'])
            ? $searchSummary['top'] : null;
        $view->emptySearches = isset($searchSummary['empty'])
            ? $searchSummary['empty'] : null;
        $view->totalSearches = isset($searchSummary['total'])
            ? $searchSummary['total'] : null;

        // Record statistics
        $records = new \VuFind\Statistics\Record();
        $view->recordsBySource = $config->Statistics->recordsBySource ?: false;
        $recordSummary = $records->getStatsSummary(
            $config, 5, $config->Statistics->recordsBySource
        );
        $view->topRecords = isset($recordSummary['top'])
            ? $recordSummary['top'] : null;
        $view->totalRecordViews = isset($recordSummary['total'])
            ? $recordSummary['total'] : null;

        // Browser statistics
        $view->currentBrowser = Statistics::getBrowser(
            $this->getRequest()->getServer('HTTP_USER_AGENT')
        );

        // Look for universal statistics recorder
        $matchFound = false;
        foreach (Statistics::getDriversForSource(null) as $currentDriver) {
            $browserStats = $currentDriver->getBrowserStats(false, 5);
            if (!empty($browserStats)) {
                $matchFound = true;
                break;
            }
        }

        // If no full coverage mode found, take the first valid source
        if (!$matchFound) {
            $drivers = Statistics::getDriversForSource(null, true);
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

    /**
     * Configuration management
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function configAction()
    {
        $view = $this->createViewModel();
        $view->baseConfigPath = ConfigReader::getBaseConfigPath('');
        $conf = ConfigReader::getConfig();
        $view->showInstallLink
            = isset($conf->System->autoConfigure) && $conf->System->autoConfigure;
        return $view;
    }

    /**
     * Support action for config -- attempt to enable auto configuration.
     *
     * @return mixed
     */
    public function enableautoconfigAction()
    {
        $configFile = ConfigReader::getConfigPath('config.ini');
        $writer = new \VuFind\Config\Writer($configFile);
        $writer->set('System', 'autoConfigure', 1);
        if ($writer->save()) {
            $this->flashMessenger()->setNamespace('info')
                ->addMessage('Auto-configuration enabled.');

            // Reload config now that it has been edited (otherwise, old setting
            // will persist in cache):
            ConfigReader::getConfig(null, true);
        } else {
            $this->flashMessenger()->setNamespace('error')
                ->addMessage(
                    'Could not enable auto-configuration; check permissions on '
                    . $configFile . '.'
                );
        }
        return $this->forwardTo('Admin', 'Config');
    }

    /**
     * System maintenance
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function maintenanceAction()
    {
        return $this->createViewModel();
    }

    /**
     * Support action for maintenance -- delete expired searches.
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
            $search = new SearchTable();
            $query = $search->getExpiredQuery($daysOld);
            if (($count = count($search->select($query))) == 0) {
                $msg = "No expired searches to delete.";
            } else {
                $search->delete($query);
                $msg = "{$count} expired searches deleted.";
            }
            $this->flashMessenger()->setNamespace('info')->addMessage($msg);
        }
        return $this->forwardTo('Admin', 'Maintenance');
    }
}

