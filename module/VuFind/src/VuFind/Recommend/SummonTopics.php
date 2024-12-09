<?php

/**
 * SummonTopics Recommendations Module
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
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */

namespace VuFind\Recommend;

/**
 * SummonTopics Recommendations Module
 *
 * This class provides database recommendations by doing a search of Summon.
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
class SummonTopics extends AbstractSummonRecommend
{
    /**
     * Called before the Search Results object performs its main search
     * (specifically, in response to \VuFind\Search\SearchRunner::EVENT_CONFIGURED).
     * This method is responsible for setting search parameters needed by the
     * recommendation module and for reading any existing search parameters that may
     * be needed.
     *
     * @param \VuFind\Search\Base\Params $params  Search parameter object
     * @param \Laminas\Stdlib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    public function init($params, $request)
    {
        parent::init($params, $request);
        if ($params->getSearchClassId() == 'Summon') {
            $params->getOptions()->setMaxTopicRecommendations(1);
        }
    }

    /**
     * Get topic results.
     *
     * @return array
     */
    public function getResults()
    {
        return $this->results->getTopicRecommendations();
    }

    /**
     * If we have to create a new Summon results object, this method is used to
     * configure it with appropriate settings.
     *
     * @param \VuFind\Search\Summon\Results $results Search results object
     *
     * @return void
     */
    protected function configureSummonResults(\VuFind\Search\Summon\Results $results)
    {
        parent::configureSummonResults($results);
        $results->getOptions()->setMaxTopicRecommendations(1);
    }
}
