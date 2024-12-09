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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Search_Base
 * @author   Dennis Schrittenlocher <Dennis.Schrittenlocher@outlook.de>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Search\Base;

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
     * @var \Laminas\Config\Config
     */
    protected $config;

    /**
     * Configuration file to read search settings from
     *
     * @var string
     */
    protected $searchIni = 'searches';

    /**
     * Search Service
     *
     * @var SearchService
     */
    protected $searchService;

    /**
     * Search string used for query.
     *
     * @var string
     */
    protected $lookfor;

    /**
     * RecordId of title the explanation is built for.
     *
     * @var string
     */
    protected $recordId;

    /**
     * Search parameters object
     *
     * @var \VuFind\Search\Base\Params
     */
    protected $params;

    /**
     * Constructor
     *
     * @param \VuFind\Search\Base\Params   $params        Search Parameter
     * @param SearchService                $searchService Search Service
     * @param \VuFind\Config\PluginManager $configLoader  Config Loader
     */
    public function __construct($params, $searchService, $configLoader)
    {
        $this->params = $params;
        $this->searchService = $searchService;
        $this->config = $configLoader->get($this->searchIni);
    }

    /**
     * Performing request and creating explanation.
     *
     * @param string $recordId Record Id
     *
     * @throws \VuFindSearch\Backend\Exception\BackendException
     * @return void
     */
    abstract public function performRequest($recordId);

    /**
     * Get the search string used for query.
     *
     * @return string
     */
    public function getLookfor()
    {
        return $this->lookfor;
    }

    /**
     * Get the record id of title the explanation is built for.
     *
     * @return string
     */
    public function getRecordId()
    {
        return $this->recordId;
    }

    /**
     * Get the search parameters object.
     *
     * @return \VuFind\Search\Base\Params
     */
    public function getParams()
    {
        return $this->params;
    }
}
