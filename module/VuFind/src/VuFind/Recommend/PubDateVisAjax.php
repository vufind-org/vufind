<?php

/**
 * PubDateVisAjax Recommendations Module
 *
 * PHP version 8
 *
 * Copyright (C) Till Kinstler 2011.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Till Kinstler <kinstler@gbv.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */

namespace VuFind\Recommend;

use function array_slice;

/**
 * PubDateVisAjax Recommendations Module
 *
 * This class displays a visualisation of facet values in a recommendation module
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Till Kinstler <kinstler@gbv.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
class PubDateVisAjax implements RecommendInterface
{
    use DateFacetTrait;

    /**
     * Raw settings string
     *
     * @var string
     */
    protected string $settings;

    /**
     * Search results object
     *
     * @var \VuFind\Search\Base\Results
     */
    protected \VuFind\Search\Base\Results $searchObject;

    /**
     * Should we allow zooming?
     *
     * @var bool
     */
    protected bool $zooming = false;

    /**
     * Should we initially hide controls?
     *
     * @var bool
     */
    protected bool $initiallyHideControls = false;

    /**
     * Facet fields to use
     *
     * @var array
     */
    protected array $dateFacets = [];

    /**
     * Store the configuration of the recommendation module.
     *
     * @param string $settings Settings from searches.ini.
     *
     * @return void
     */
    public function setConfig($settings)
    {
        // Save the basic parameters:
        $this->settings = $settings;

        // Parse the additional settings:
        $params = explode(':', $settings);
        if ($params[0] == 'true' || $params[0] == 'false') {
            $this->zooming = $params[0] === 'true';
            $params = array_slice($params, 1);
        }
        if ($params[0] == 'true' || $params[0] == 'false') {
            $this->initiallyHideControls = $params[0] === 'true';
            $params = array_slice($params, 1);
        }
        $this->dateFacets = $params;
    }

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
        // No action needed.
    }

    /**
     * Called after the Search Results object has performed its main search. This
     * may be used to extract necessary information from the Search Results object
     * or to perform completely unrelated processing.
     *
     * @param \VuFind\Search\Base\Results $results Search results object
     *
     * @return void
     */
    public function process($results)
    {
        $this->searchObject = $results;
    }

    /**
     * Get visual facet details.
     *
     * @return array
     */
    public function getVisFacets(): array
    {
        // Don't bother processing if the result set is empty:
        if ($this->searchObject->getResultTotal() <= 0) {
            return [];
        }
        return $this->processDateFacets(
            $this->searchObject,
            $this->searchObject->getParams()->getRawFilters(),
            $this->dateFacets
        );
    }

    /**
     * Get zoom setting
     *
     * @return bool
     */
    public function getZooming(): bool
    {
        return $this->zooming;
    }

    /**
     * Get zoom setting
     *
     * @return bool
     */
    public function getInitiallyHideControlsSetting(): bool
    {
        return $this->initiallyHideControls;
    }

    /**
     * Get facet fields
     *
     * @return string
     */
    public function getFacetFields(): string
    {
        return implode(':', $this->dateFacets);
    }

    /**
     * Get search parameters
     *
     * @return string of params
     */
    public function getSearchParams(): string
    {
        // Get search parameters and return them minus the leading ?:
        return substr($this->searchObject->getUrlQuery()->getParams(false), 1);
    }
}
