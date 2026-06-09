<?php

/**
 * Abstract base class for collections.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2026.
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
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\Collections;

use VuFind\Action\AbstractTemplateRenderingAction;
use VuFind\I18n\HasSorterInterface;
use VuFind\I18n\HasSorterTrait;
use VuFind\I18n\Sorter;
use VuFind\Search\Results\PluginManager as SearchResultsPluginManager;
use VuFind\ServiceManager\Factory\Autowire;
use VuFindSearch\Service as SearchService;

/**
 * Abstract base class for collections.
 *
 * @category VuFind
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
abstract class AbstractCollectionsAction extends AbstractTemplateRenderingAction implements HasSorterInterface
{
    use HasSorterTrait;

    /**
     * Constructor.
     *
     * @param array                      $config                     VuFind configuration
     * @param SearchService              $searchService              Search service
     * @param SearchResultsPluginManager $searchResultsPluginManager Search results plugin manager
     * @param Sorter                     $sorter                     Sorter
     */
    public function __construct(
        #[Autowire(config: 'config')]
        protected array $config,
        protected SearchService $searchService,
        protected SearchResultsPluginManager $searchResultsPluginManager,
        Sorter $sorter,
    ) {
        parent::__construct();
        $this->setSorter($sorter);
    }

    /**
     * Get the collection browse page size.
     *
     * @return int
     */
    protected function getBrowseLimit(): int
    {
        return (int)($this->config['Collections']['browseLimit'] ?? 20);
    }

    /**
     * Get a list of initial letters to display.
     *
     * @return array
     */
    protected function getAlphabetList(): array
    {
        return array_merge(range('0', '9'), range('A', 'Z'));
    }

    /**
     * Get the delimiter used to separate title from ID in the browse strings.
     *
     * @return string
     */
    protected function getBrowseDelimiter(): string
    {
        return $this->config['Collections']['browseDelimiter'] ?? '{{{_ID_}}}';
    }
}
