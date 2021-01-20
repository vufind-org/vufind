<?php
/**
 * Feedback Recommendations Module.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2020.
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
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
namespace Finna\Recommend;

use Finna\Cookie\RecommendationMemory;
use VuFind\Recommend\RecommendInterface;

/**
 * Feedback Recommendations Module.
 *
 * This class provides a way to give feedback on recommendations.
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
class Feedback implements RecommendInterface
{
    /**
     * Recommendation memory.
     *
     * @var RecommendationMemory
     */
    protected $recMemory;

    /**
     * Possible recommendation data.
     *
     * @var array
     */
    protected $recData = null;

    /**
     * Feedback constructor.
     *
     * @param RecommendationMemory $recMemory Recommendation memory
     */
    public function __construct(RecommendationMemory $recMemory)
    {
        $this->recMemory = $recMemory;
    }

    /**
     * Store the configuration of the recommendation module.
     *
     * @param string $settings Settings from searches.ini.
     *
     * @return void
     */
    public function setConfig($settings)
    {
    }

    /**
     * Called at the end of the Search Params objects' initFromRequest() method.
     * This method is responsible for setting search parameters needed by the
     * recommendation module and for reading any existing search parameters that may
     * be needed.
     *
     * @param \VuFind\Search\Base\Params $params  Search parameter object
     * @param \Laminas\StdLib\Parameters $request Parameter object representing user
     *                                            request.
     *
     * @return void
     */
    public function init($params, $request)
    {
        $this->recData = $this->recMemory->get($request);
    }

    /**
     * Called after the Search Results object has performed its main search.  This
     * may be used to extract necessary information from the Search Results object
     * or to perform completely unrelated processing.
     *
     * @param \VuFind\Search\Base\Results $results Search results object
     *
     * @return void
     */
    public function process($results)
    {
    }

    /**
     * Returns data for the feedback theme template.
     *
     * @return array Array with data or an empty array if the feedback form should
     *               not be shown.
     */
    public function getTemplateData(): array
    {
        if (empty($this->recData)) {
            return [];
        }
        return [
            'recommendation'
                => $this->recData[RecommendationMemory::RECOMMENDATION],
            'formData' => [
                'source_module'
                    => $this->recData[RecommendationMemory::SOURCE_MODULE],
                'recommendation'
                    => $this->recData[RecommendationMemory::RECOMMENDATION],
                'original'
                    => $this->recData[RecommendationMemory::ORIGINAL],
                'recommendation_type'
                    => $this->recData[RecommendationMemory::RECOMMENDATION_TYPE]
            ]
        ];
    }
}
