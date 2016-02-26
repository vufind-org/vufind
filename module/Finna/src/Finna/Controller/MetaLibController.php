<?php
/**
 * MetaLib Controller
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace Finna\Controller;
use Finna\MetaLib\MetaLibIrdTrait,
    Finna\Search\MetaLib\Options as Options,
    Finna\Search\MetaLib\Params as Params,
    Finna\Search\MetaLib\Results as Results,
    Finna\Search\Results\Factory as Factory,
    VuFindSearch\ParamBag as ParamBag,
    VuFindSearch\Query\Query as Query,
    Zend\Session\Container as SessionContainer;

/**
 * MetaLib Controller
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class MetaLibController extends \VuFind\Controller\AbstractSearch
{
    use MetaLibIrdTrait,
        SearchControllerTrait;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->searchClassId = 'MetaLib';
        parent::__construct();
    }

    /**
     * Home action
     *
     * @return mixed
     */
    public function homeAction()
    {
        if (!$this->isAvailable()) {
            throw new \Exception('MetaLib is not enabled');
        }

        $view = $this->createViewModel();
        $this->layout()->searchClassId = $this->searchClassId;
        $query = new Query();
        $view = $this->initSets($view, $query);
        $this->layout()->metalibSet = $this->getRequest()->getQuery()->get('set');
        $view->browseDatabase = $this->isBrowseDatabaseAvailable();
        return $view;
    }

    /**
     * Search action -- call standard results action
     *
     * @return mixed
     */
    public function searchAction()
    {
        if (!$this->isAvailable()) {
            throw new \Exception('MetaLib is not enabled');
        }

        $query = $this->getRequest()->getQuery();
        if ($query->get('ajax') || $query->get('view') == 'rss') {
            $view = parent::resultsAction();
        } else {
            $configLoader = $this->getServiceLocator()->get('VuFind\Config');
            $options = new Options($configLoader);
            $params = new Params($options, $configLoader);
            $params->initFromRequest($query);
            list($isIRD, $set)
                = $this->getMetaLibSet($this->getRequest()->getQuery()->get('set'));
            if ($irds = $this->getMetaLibIrds($set)) {
                $params->setIrds($irds);
            }
            $results = new Results($params);
            $results
                = Factory::initUrlQueryHelper(
                    $results, $this->getServiceLocator()
                );
            $view = $this->createViewModel();
            $view->qs = $this->getRequest()->getUriString();
            $view->params = $params;
            $view->results = $results;
            $view->disablePiwik = true;
            $view = $this->initSets($view, $params->getQuery());
            $view->browseDatabase = $this->isBrowseDatabaseAvailable();
        }
        $this->initSavedTabs();
        return $view;
    }

    /**
     * Handle an advanced search
     *
     * @return mixed
     */
    public function advancedAction()
    {
        if (!$this->isAvailable()) {
            throw new \Exception('MetaLib is not enabled');
        }

        $view = parent::advancedAction();
        $view = $this->initSets($view, $this->getRequest()->getQuery());
        return $view;
    }

    /**
     * Support function for assigning MetaLib search sets to a view.
     *
     * @param \Zend\View\Model\View\Model $view  View
     * @param \VuFind\Search\Query\Query  $query Query
     *
     * @return $view
     */
    protected function initSets($view, $query)
    {
        $allowedSets = $this->getMetaLibSets();
        $sets = [];
        foreach ($allowedSets as $key => $set) {
            $sets[$key] = $set['name'];
        }
        $view->sets = $sets;
        list($isIrd, $set) = $this->getMetaLibSet(
            $this->getRequest()->getQuery()->get('set')
        );
        $view->currentSet = $set;
        $session = new SessionContainer('MetaLib');
        if ($isIrd) {
            $metalib = $this->getServiceLocator()->get('VuFind\Search');
            $backendParams = new ParamBag();
            $backendParams->add('irdInfo', explode(',', substr($set, 5)));
            $result
                = $metalib->search('MetaLib', $query, false, false, $backendParams);
            $info = $result->getIRDInfo();
            $name = $info ? $info['name'] : $set;
            if (!isset($session->recentSets)) {
                $session->recentSets = [];
            }
            unset($session->recentSets[$set]);
            $session->recentSets[$set] = $isIrd ? $name : $sets[$set];
        }
        $view->recentSets
            = isset($session->recentSets) ? array_reverse($session->recentSets) : [];
        return $view;
    }

    /**
     * Is the result scroller active?
     *
     * @return bool
     */
    protected function resultScrollerActive()
    {
        return true;
        $config = $this->getServiceLocator()->get('VuFind\Config')->get('MetaLib');
        return (isset($config->Record->next_prev_navigation)
            && $config->Record->next_prev_navigation);
    }

    /**
     * Check if MetaLib is available.
     *
     * @return bool
     */
    protected function isAvailable()
    {
        $config = $this->getServiceLocator()->get('VuFind\Config')->get('MetaLib');
        return isset($config->General->enabled) && $config->General->enabled;
    }

    /**
     * Check if database browsing is available.
     *
     * @return bool
     */
    protected function isBrowseDatabaseAvailable()
    {
        $config = $this->getServiceLocator()->get('VuFind\Config')->get('browse');
        return isset($config['General']['Database'])
            && $config['General']['Database'];
    }
}
