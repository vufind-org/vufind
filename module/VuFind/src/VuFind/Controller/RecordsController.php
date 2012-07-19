<?php
/**
 * Records Controller
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
use VuFind\Record;

/**
 * Records Controller
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class RecordsController extends AbstractSearch
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->searchClassId = 'MixedList';
        $this->useResultScroller = false;
        parent::__construct();
    }

    /**
     * Home action -- call standard results action
     *
     * @return void
     */
    public function homeAction()
    {
        // If there is exactly one record, send the user directly there:
        $ids = $this->params()->fromQuery('id', array());
        if (count($ids) == 1) {
            $parts = explode('|', $ids[0], 2);
            if (count($parts) == 2) {
                list($source, $id) = $parts;
            } else {
                list($source, $id) = array('VuFind', $parts[0]);
            }
            $driver = Record::load($id, $source);
            $target = $this->url()->fromRoute(
                $driver->getRecordRoute(), array('id' => $driver->getUniqueId())
            );
            // forward print param, if necessary:
            $print = $this->params()->fromQuery('print');
            $params = empty($print) ? '' : '?print=' . urlencode($print);
            return $this->redirect()->toUrl($target . $params);
        }

        // Not exactly one record -- show search results:
        return $this->resultsAction();
    }
}

