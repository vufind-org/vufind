<?php

/**
 * "Keep Alive" AJAX handler
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2018.
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
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\AjaxHandler;

use Laminas\Config\Config;
use Laminas\Mvc\Controller\Plugin\Params;
use Laminas\Session\SessionManager;
use VuFind\Db\Table\Session;
use VuFind\Search\Results\PluginManager as ResultsManager;

/**
 * "Keep Alive" AJAX handler
 *
 * This is responsible for keeping the session alive whenever called
 * (via JavaScript)
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class SystemStatus extends AbstractBase
{
    /**
     * Session Manager
     *
     * @var SessionManager
     */
    protected $sessionManager;

    /**
     * Session database table
     *
     * @var Session
     */
    protected $sessionTable;

    /**
     * Results manager
     *
     * @var ResultsManager
     */
    protected $resultsManager;

    /**
     * Top-level VuFind configuration (config.ini)
     *
     * @var Config
     */
    protected $config;

    /**
     * Constructor
     *
     * @param SessionManager $sm     Session manager
     * @param ResultsManager $rm     Results manager
     * @param Config         $config Top-level VuFind configuration (config.ini)
     * @param Session        $table  Session database table
     */
    public function __construct(
        SessionManager $sm,
        ResultsManager $rm,
        Config $config,
        Session $table
    ) {
        $this->sessionManager = $sm;
        $this->resultsManager = $rm;
        $this->config = $config;
        $this->sessionTable = $table;
    }

    /**
     * Handle a request.
     *
     * @param Params $params Parameter helper from controller
     *
     * @return array [response data, HTTP status code]
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function handleRequest(Params $params)
    {
        // Check system status
        if (!empty($this->config->System->healthCheckFile)
            && file_exists($this->config->System->healthCheckFile)
        ) {
            return $this->formatResponse(
                'Health check file exists',
                self::STATUS_HTTP_UNAVAILABLE
            );
        }

        // Test search index
        try {
            $results = $this->resultsManager->get('Solr');
            $paramsObj = $results->getParams();
            $paramsObj->setQueryIDs(['healthcheck']);
            $results->performAndProcessSearch();
        } catch (\Exception $e) {
            return $this->formatResponse(
                'Search index error: ' . $e->getMessage(),
                self::STATUS_HTTP_ERROR
            );
        }

        // Test database connection
        try {
            $this->sessionTable->getBySessionId('healthcheck', false);
        } catch (\Exception $e) {
            return $this->formatResponse(
                'Database error: ' . $e->getMessage(),
                self::STATUS_HTTP_ERROR
            );
        }

        // This may be called frequently, don't leave sessions dangling
        $this->sessionManager->destroy();

        return $this->formatResponse('');
    }
}
