<?php

/**
 * Records Controller
 *
 * PHP version 8
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

use function count;

/**
 * Records Controller
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class RecordsController extends AbstractSearch
{
    /**
     * Constructor
     *
     * @param ServiceLocatorInterface $sm Service locator
     */
    public function __construct(ServiceLocatorInterface $sm)
    {
        $this->searchClassId = 'MixedList';
        parent::__construct($sm);
    }

    /**
     * Home action -- call standard results action
     *
     * @return mixed
     */
    public function homeAction()
    {
        // If there is exactly one record, send the user directly there:
        $ids = $this->params()->fromQuery('id', []);
        $print = $this->params()->fromQuery('print');
        if (count($ids) == 1) {
            $details = $this->getRecordRouter()->getTabRouteDetails($ids[0]);
            $target = $this->url()->fromRoute($details['route'], $details['params']);
            // forward print param, if necessary:
            $params = empty($print) ? '' : '?print=' . urlencode($print);
            return $this->redirect()->toUrl($target . $params);
        }
        // Ignore Print for Search History:
        if (!empty($print)) {
            $this->saveToHistory = false;
        }

        // Not exactly one record -- show search results:
        return $this->resultsAction();
    }
}
