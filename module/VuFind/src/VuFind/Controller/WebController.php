<?php
/**
 * Web Controller
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
 * Web Controller
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class WebController extends AbstractSearch
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->searchClassId = 'SolrWeb';
        parent::__construct();
    }

    /**
     * Home action
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function homeAction()
    {
        // Do nothing -- just display template
        return $this->createViewModel();
    }

    /**
     * Process the jumpto parameter -- either redirect to a specific record and
     * return view model, or ignore the parameter and return false.
     *
     * @param \VuFind\Search\Base\Results $results Search results object.
     *
     * @return mixed
     */
    protected function processJumpTo($results)
    {
        // Missing/invalid parameter?  Ignore it:
        $jumpto = $this->params()->fromQuery('jumpto');
        if (empty($jumpto) || !is_numeric($jumpto)) {
            return false;
        }

        // Parameter out of range?  Ignore it:
        $recordList = $results->getResults();
        if (!isset($recordList[$jumpto - 1])) {
            return false;
        }

        // If we got this far, we have a valid parameter so we should redirect
        // and report success:
        $url = $recordList[$jumpto - 1]->getUrl();
        return $url ? $this->redirect()->toUrl($url) : false;
    }
}
