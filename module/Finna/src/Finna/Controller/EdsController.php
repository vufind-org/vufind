<?php
/**
 * EDS Controller
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2017.
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
 * @author   Anna Niku <anna.niku@gofore.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace Finna\Controller;

/**
 * EDS Controller
 *
 * @category VuFind
 * @package  Controller
 * @author   Anna Niku <anna.niku@gofore.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class EdsController extends \VuFind\Controller\EdsController
{
    use SearchControllerTrait;

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

    /**
     * Get the search memory
     *
     * @return \Finna\Search\Memory
     */
    public function getSearchMemory()
    {
        return $this->serviceLocator->get('Finna\Search\Memory');
    }

    /**
     * Handle an advanced search
     *
     * @return mixed
     */
    public function advancedAction()
    {
        $view = parent::advancedAction();

        $config = $this->getConfig();
        $ticks = [0, 900, 1800, 1910];
        if (!empty($config->Site->advSearchYearScale)) {
            $ticks = array_map(
                'trim', explode(',', $config->Site->advSearchYearScale)
            );
        }
        $rangeEnd = date('Y', strtotime('+1 year'));

        $range = [];
        if ($view->dateRangeLimit) {
            $values = $view->dateRangeLimit;
            if ($values[0] && $values[1]) {
                $range['values'] = [$values[0], $values[1]];
                if ($ticks[0] > $values[0]) {
                    $ticks[0] = $values[0];
                }
                if ($rangeEnd < $values[1]) {
                    $rangeEnd = $values[1];
                }
            } else {
                $range['values'] = [null, null];
            }
        }
        array_push($ticks, $rangeEnd);
        $range['ticks'] = $ticks;

        $positions = [];
        for ($i = 0; $i < count($ticks); $i++) {
            $positions[] = floor($i * 100 / (count($ticks) - 1));
        }
        $range['ticks_positions'] = $positions;

        $view->daterange = $range;

        return $view;
    }
}
