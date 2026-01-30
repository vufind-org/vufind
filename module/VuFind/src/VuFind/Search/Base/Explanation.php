<?php

/**
 * Abstract explanation model.
 *
 * PHP version 8
 *
 * Copyright (C) Hebis Verbundzentrale 2023.
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
 * @package  Search_Base
 * @author   Dennis Schrittenlocher <Dennis.Schrittenlocher@outlook.de>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Search\Base;

use VuFind\Config\ConfigManagerInterface;
use VuFindSearch\Service as SearchService;

/**
 * Abstract explanation model.
 *
 * This abstract class defines the methods for modeling an explanation in VuFind.
 *
 * @category VuFind
 * @package  Search_Base
 * @author   Dennis Schrittenlocher <Dennis.Schrittenlocher@outlook.de>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
abstract class Explanation
{
    /**
     * Configuration
     *
     * @var \VuFind\Config\Config
     */
    protected \VuFind\Config\Config $config;

    /**
     * Configuration file to read search settings from
     *
     * @var string
     */
    protected string $searchIni = 'searches';

    /**
     * Search string used for query.
     *
     * @var string
     */
    protected string $lookfor;

    /**
     * RecordId of title the explanation is built for.
     *
     * @var string
     */
    protected string $recordId;

    /**
     * Constructor
     *
     * @param \VuFind\Search\Base\Params $params        Search parameters object
     * @param SearchService              $searchService Search Service
     * @param ConfigManagerInterface     $configManager Config manager
     */
    public function __construct(
        protected \VuFind\Search\Base\Params $params,
        protected SearchService $searchService,
        ConfigManagerInterface $configManager
    ) {
        $this->config = $configManager->getConfigObject($this->searchIni);
    }

    /**
     * Performing request and creating explanation.
     *
     * @param string $recordId Record Id
     *
     * @throws \VuFindSearch\Backend\Exception\BackendException
     * @return void
     */
    abstract public function performRequest(string $recordId): void;

    /**
     * Get the search string used for query.
     *
     * @return string
     */
    public function getLookfor(): string
    {
        return $this->lookfor;
    }

    /**
     * Get the record id of title the explanation is built for.
     *
     * @return string
     */
    public function getRecordId(): string
    {
        return $this->recordId;
    }

    /**
     * Get the search parameters object.
     *
     * @return \VuFind\Search\Base\Params
     */
    public function getParams(): \VuFind\Search\Base\Params
    {
        return $this->params;
    }
}
