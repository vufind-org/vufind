<?php
/**
 * Primo Central Controller
 *
 * PHP version 7
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace Finna\Controller;

/**
 * Primo Central Controller
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class PrimoController extends \VuFind\Controller\PrimoController
{
    use FinnaSearchControllerTrait;

    /**
     * Search class family to use.
     *
     * @var string
     */
    protected $searchClassId = 'Primo';

    /**
     * Home action
     *
     * @return mixed
     */
    public function homeAction()
    {
        $this->layout()->searchClassId = $this->searchClassId;
        return parent::homeAction();
    }

    /**
     * Handle onDispatch event
     *
     * @param \Laminas\Mvc\MvcEvent $e Event
     *
     * @return mixed
     */
    public function onDispatch(\Laminas\Mvc\MvcEvent $e)
    {
        $primoHelper = $this->getViewRenderer()->plugin('primo');
        if (!$primoHelper->isAvailable()) {
            throw new \Exception('Primo is disabled');
        }

        return parent::onDispatch($e);
    }

    /**
     * Search action -- call standard results action
     *
     * @return mixed
     */
    public function searchAction()
    {
        if ($this->getRequest()->getQuery()->get('combined')) {
            $this->saveToHistory = false;
        }
        $this->initCombinedViewFilters();
        $view = parent::resultsAction();
        $this->initSavedTabs();

        return $view;
    }

    /**
     * Save a search to the history in the database.
     * Save search Id and type to memory
     *
     * @param \VuFind\Search\Base\Results $results Search results
     *
     * @return void
     */
    public function saveSearchToHistory($results)
    {
        parent::saveSearchToHistory($results);
        $this->getSearchMemory()->rememberSearchData(
            $results->getSearchId(),
            $results->getParams()->getSearchType(),
            $results->getUrlQuery()->isQuerySuppressed()
                ? '' : $results->getParams()->getDisplayQuery()
        );
    }
}
