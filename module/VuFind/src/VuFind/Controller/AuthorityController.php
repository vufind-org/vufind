<?php
/**
 * Authority Controller
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
use VuFind\Search\SolrAuth\Results;

/**
 * Authority Controller
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class AuthorityController extends AbstractSearch
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->searchClassId = 'SolrAuth';
        $this->useResultScroller = false;
        parent::__construct();
    }

    /**
     * Home action
     *
     * @return void
     */
    public function homeAction()
    {
        // Do nothing -- just display template
        return $this->createViewModel();
    }

    /**
     * Record action -- display a record
     *
     * @return void
     */
    public function recordAction()
    {
        return $this->createViewModel(
            array(
                'driver' => Results::getRecord($this->params()->fromQuery('id'))
            )
        );
    }

    /**
     * Search action -- call standard results action
     *
     * @return void
     */
    public function searchAction()
    {
        return $this->resultsAction();
    }
}

