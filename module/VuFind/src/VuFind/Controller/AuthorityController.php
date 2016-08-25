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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace VuFind\Controller;

/**
 * Authority Controller
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class AuthorityController extends AbstractSearch
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->searchClassId = 'SolrAuth';
        parent::__construct();
    }

    /**
     * Home action
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function homeAction()
    {
        // If we came in with a record ID, forward to the record action:
        if ($id = $this->params()->fromRoute('id', false)) {
            $this->getRequest()->getQuery()->set('id', $id);
            return $this->forwardTo('Authority', 'Record');
        }

        // Do nothing -- just display template
        return $this->createViewModel();
    }

    /**
     * Record action -- display a record
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function recordAction()
    {
        $id = $this->params()->fromQuery('id');
        $cfg = $this->getServiceLocator()->get('Config');
        $tabConfig = $cfg['vufind']['recorddriver_tabs'];
        $driver = $this->getServiceLocator()->get('VuFind\RecordLoader')
            ->load($id, 'SolrAuth');
        $request = $this->getRequest();
        $tabs = $this->getServiceLocator()
            ->get('VuFind\RecordTabPluginManager')
            ->getTabsForRecord($driver, $tabConfig, $request);
        return $this->createViewModel(['driver' => $driver, 'tabs' => $tabs]);
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
