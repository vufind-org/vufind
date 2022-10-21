<?php
/**
 * Authority Controller
 *
 * PHP version 7
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

use Laminas\ServiceManager\ServiceLocatorInterface;

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
     *
     * @param ServiceLocatorInterface $sm Service locator
     */
    public function __construct(ServiceLocatorInterface $sm)
    {
        $this->searchClassId = 'SolrAuth';
        parent::__construct($sm);
    }

    /**
     * Home action
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function homeAction()
    {
        // If we came in with a record ID, forward to the record action:
        if ($id = $this->params()->fromRoute('id', false)) {
            if ($id !== 'Record') {
                $this->getRequest()->getQuery()->set('id', $id);
            }
            return $this->forwardTo('Authority', 'Record');
        }

        // Default behavior:
        return parent::homeAction();
    }

    /**
     * Record action -- display a record
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function recordAction()
    {
        // Due to quirks of the MVC router and the way this controller is structured,
        // trying to access a record with an ID of "Record" will route the user
        // directly to this action instead of through the homeAction, which means
        // that we need to default the id to the value of 'Record' to allow support
        // for this (unlikely but not impossible) situation.
        $id = $this->params()->fromQuery('id', 'Record');
        $driver = $this->serviceLocator->get(\VuFind\Record\Loader::class)
            ->load($id, 'SolrAuth');
        $request = $this->getRequest();
        $tabs = $this->getRecordTabManager()->getTabsForRecord($driver, $request);
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
