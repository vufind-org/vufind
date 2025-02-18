<?php

/**
 * Primo Central Controller
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2023.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Controller;

use Laminas\ServiceManager\ServiceLocatorInterface;

/**
 * Primo Central Controller
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class PrimoController extends AbstractSearch
{
    /**
     * Constructor
     *
     * @param ServiceLocatorInterface $sm Service locator
     */
    public function __construct(ServiceLocatorInterface $sm)
    {
        $this->accessPermission = 'access.PrimoModule';
        $this->searchClassId = 'Primo';
        parent::__construct($sm);
    }

    /**
     * Is the result scroller active?
     *
     * @return bool
     */
    protected function resultScrollerActive()
    {
        $config = $this->getService(\VuFind\Config\PluginManager::class)->get('Primo');
        return $config->Record->next_prev_navigation ?? false;
    }

    /**
     * Show results of "cited by" search.
     *
     * @return mixed
     */
    public function citedByAction()
    {
        $this->flashMessenger()->addInfoMessage('results_citing_title_note');
        return $this->performCitationSearch();
    }

    /**
     * Show results of "cites" search.
     *
     * @return mixed
     */
    public function citesAction()
    {
        $this->flashMessenger()->addInfoMessage('results_cited_by_title_note');
        return $this->performCitationSearch();
    }

    /**
     * Perform a "cited" or "cited by" search
     *
     * @return mixed
     */
    protected function performCitationSearch()
    {
        if (!($id = trim($this->params()->fromQuery('lookfor', ''), '"'))) {
            return $this->forwardTo('Primo', 'Home');
        }
        $driver = $this->getRecordLoader()->load($id, $this->searchClassId);

        // Don't save to history -- history page doesn't handle correctly:
        $this->saveToHistory = false;

        $callback = function ($runner, $params, $searchId) {
            $options = $params->getOptions();
            $options->disableHighlighting();
            $options->spellcheckEnabled(false);
            if ($lastLimit = $this->getSearchMemory()->retrieveLastSetting($this->searchClassId, 'limit')) {
                $params->setLimit($lastLimit);
            }
        };

        $view = $this->getSearchResultsView($callback);
        $view->driver = $driver;
        return $view;
    }

    /**
     * Search action -- call standard results action
     *
     * @return mixed
     */
    public function searchAction()
    {
        return $this->resultsAction();
    }
}
