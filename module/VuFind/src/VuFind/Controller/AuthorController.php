<?php
/**
 * Author Search Controller
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
 * Author Search Options
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class AuthorController extends AbstractSearch
{
    /**
     * Sets the configuration for displaying author results
     *
     * @return mixed
     */
    public function resultsAction()
    {
        $this->searchClassId = 'SolrAuthor';

        // Save author searches if next_prev_navigation is enabled - otherwise
        // there are wacky results when trying to page through results (the
        // next/prev links only appear for records which were included in the
        // results for the previous keyword search, and the next/prev links will
        // iterate you through that search).
        $this->saveToHistory = $this->resultScrollerActive();

        return parent::resultsAction();
    }

    /**
     * Sets the configuration for performing an author search
     *
     * @return mixed
     */
    public function searchAction()
    {
        $this->searchClassId = 'SolrAuthorFacets';
        $this->saveToHistory = false;
        $this->rememberSearch = false;
        return parent::resultsAction();
    }

    /**
     * Displays the proper page for a search action
     *
     * @return mixed
     */
    public function homeAction()
    {
        // If an author was requested, forward to the results page; otherwise,
        // display the search form:
        $author = $this->params()->fromQuery('author');
        if (!empty($author)) {
            return $this->forwardTo('Author', 'Results');
        }
        return $this->createViewModel();
    }

    /**
     * Is the result scroller active?
     *
     * @return bool
     */
    protected function resultScrollerActive()
    {
        $config = $this->getServiceLocator()->get('VuFind\Config')->get('config');
        return (isset($config->Record->next_prev_navigation)
            && $config->Record->next_prev_navigation);
    }
}

