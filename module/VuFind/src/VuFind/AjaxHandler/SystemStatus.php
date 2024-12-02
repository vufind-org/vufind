<?php

/**
 * "System Status" AJAX handler
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\AjaxHandler;

use Laminas\Config\Config;
use Laminas\Mvc\Controller\Plugin\Params;
use Laminas\Session\SessionManager;
use VuFind\Db\Service\SessionServiceInterface;
use VuFind\Search\Results\PluginManager as ResultsManager;

/**
 * "System Status" AJAX handler
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class SystemStatus extends AbstractBase implements \Laminas\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Constructor
     *
     * @param SessionManager          $sessionManager Session manager
     * @param ResultsManager          $resultsManager Results manager
     * @param Config                  $config         Top-level VuFind configuration (config.ini)
     * @param SessionServiceInterface $sessionService Session database service
     */
    public function __construct(
        protected SessionManager $sessionManager,
        protected ResultsManager $resultsManager,
        protected Config $config,
        protected SessionServiceInterface $sessionService
    ) {
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
        if (
            !empty($this->config->System->healthCheckFile)
            && file_exists($this->config->System->healthCheckFile)
        ) {
            return $this->formatResponse(
                'Health check file exists',
                self::STATUS_HTTP_UNAVAILABLE
            );
        }

        // Test logging (note that the message doesn't need to get written for the log writers to initialize):
        $this->log('info', 'SystemStatus log check', [], true);

        // Test search index
        if ($params->fromPost('index') ?? $params->fromQuery('index', 1)) {
            try {
                $results = $this->resultsManager->get(DEFAULT_SEARCH_BACKEND);
                $paramsObj = $results->getParams();
                $paramsObj->setQueryIDs(['healthcheck' . date('His')]);
                $results->performAndProcessSearch();
            } catch (\Exception $e) {
                return $this->formatResponse(
                    'Search index error: ' . $e->getMessage(),
                    self::STATUS_HTTP_ERROR
                );
            }
        }

        // Test database connection
        if ($params->fromPost('database') ?? $params->fromQuery('database', 1)) {
            try {
                $this->sessionService->getSessionById('healthcheck', false);
            } catch (\Exception $e) {
                return $this->formatResponse(
                    'Database error: ' . $e->getMessage(),
                    self::STATUS_HTTP_ERROR
                );
            }
        }

        // This may be called frequently, don't leave sessions dangling
        $this->sessionManager->destroy();

        return $this->formatResponse('');
    }
}
