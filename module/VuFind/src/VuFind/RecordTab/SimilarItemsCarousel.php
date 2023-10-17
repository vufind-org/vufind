<?php

/**
 * Similar items carousel tab.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010, 2022.
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
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */

namespace VuFind\RecordTab;

use VuFindSearch\Command\SimilarCommand;

/**
 * Similar items carousel tab.
 *
 * @category VuFind
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */
class SimilarItemsCarousel extends AbstractBase
{
    /**
     * Similar records
     *
     * @var array
     */
    protected $results;

    /**
     * Search service
     *
     * @var \VuFindSearch\Service
     */
    protected $searchService;

    /**
     * Configuration
     *
     * @var \Laminas\Config\Config
     */
    protected $config;

    /**
     * Constructor
     *
     * @param \VuFindSearch\Service   $search Search service
     * @param ?\Laminas\Config\Config $config Configuration
     */
    public function __construct(
        \VuFindSearch\Service $search,
        ?\Laminas\Config\Config $config = null
    ) {
        $this->searchService = $search;
        $this->config = $config;
    }

    /**
     * Get the on-screen description for this tab.
     *
     * @return string
     */
    public function getDescription()
    {
        return 'Similar Items';
    }

    /**
     * Get an array of Record Driver objects representing items similar to the one
     * passed to the constructor.
     *
     * @return RecordCollectionInterface
     */
    public function getResults()
    {
        $record = $this->getRecordDriver();
        $rows = $this->config->Record->similar_carousel_items ?? 40;
        $params = new \VuFindSearch\ParamBag(['rows' => $rows]);
        $command = new SimilarCommand(
            $record->getSourceIdentifier(),
            $record->getUniqueId(),
            $params
        );
        return $this->searchService->invoke($command)->getResult();
    }
}
