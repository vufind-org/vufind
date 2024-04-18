<?php

/**
 * OAI Server class for Authority core
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
 * @package  OAI_Server
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\OAI\Server;

use VuFind\Db\Service\OaiResumptionServiceInterface;
use VuFind\OAI\Server as Base;

/**
 * OAI Server class for Authority core
 *
 * This class provides OAI server functionality.
 *
 * @category VuFind
 * @package  OAI_Server
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Auth extends Base
{
    /**
     * Constructor
     *
     * @param \VuFind\Search\Results\PluginManager $resultsManager    Search manager for retrieving records
     * @param \VuFind\Record\Loader                $recordLoader      Record loader
     * @param \VuFind\Db\Table\PluginManager       $tableManager      Table manager
     * @param OaiResumptionServiceInterface        $resumptionService Database service for resumption tokens
     */
    public function __construct(
        protected \VuFind\Search\Results\PluginManager $resultsManager,
        protected \VuFind\Record\Loader $recordLoader,
        protected \VuFind\Db\Table\PluginManager $tableManager,
        protected OaiResumptionServiceInterface $resumptionService
    ) {
        parent::__construct($resultsManager, $recordLoader, $tableManager, $resumptionService);
        $this->core = 'authority';
        $this->searchClassId = 'SolrAuth';
    }

    /**
     * Load data from the OAI section of config.ini. (This is called by the
     * constructor and is only a separate method to allow easy override by child
     * classes).
     *
     * @param \Laminas\Config\Config $config VuFind configuration
     *
     * @return void
     */
    protected function initializeSettings(\Laminas\Config\Config $config)
    {
        // Use some of the same settings as the regular OAI server, but override
        // others:
        parent::initializeSettings($config);
        $this->repositoryName = 'Authority Data Store';
        $this->setField = 'source';
    }
}
