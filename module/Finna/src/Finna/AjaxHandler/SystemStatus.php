<?php
/**
 * "System Status" AJAX handler
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2018.
 * Copyright (C) The National Library of Finland 2019.
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
namespace Finna\AjaxHandler;

use Laminas\Mvc\Controller\Plugin\Params;

/**
 * "System Status" AJAX handler
 *
 * This is responsible for keeping the session alive whenever called
 * (via JavaScript)
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class SystemStatus extends \VuFind\AjaxHandler\SystemStatus
{
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
        $startTime = microtime(true);

        // Check system status
        if (!empty($this->config->System->healthCheckFile)
            && file_exists($this->config->System->healthCheckFile)
        ) {
            error_log('SystemStatus: Health check file exists');
            return $this->formatResponse(
                'Health check file exists', self::STATUS_HTTP_UNAVAILABLE
            );
        }

        // Test search index
        if ($params->fromPost('index', $params->fromQuery('index', 1))) {
            try {
                $results = $this->resultsManager->get('Solr');
                $paramsObj = $results->getParams();
                $paramsObj->setQueryIDs(['healthcheck']);
                $results->performAndProcessSearch();
            } catch (\Exception $e) {
                error_log(
                    'SystemStatus ERROR: Search index error: ' . $e->getMessage()
                );
                return $this->formatResponse(
                    'Search index error: ' . $e->getMessage(),
                    self::STATUS_HTTP_ERROR
                );
            }
        }

        $duration = microtime(true) - $startTime;
        if ($duration > 10) {
            error_log(
                "SystemStatus WARNING: Search index check took $duration seconds"
            );
        }

        // Test database connection
        $dbStartTime = microtime(true);
        try {
            $this->sessionTable->getBySessionId('healthcheck', false);
        } catch (\Exception $e) {
            error_log('SystemStatus ERROR: Database error: ' . $e->getMessage());
            return $this->formatResponse(
                'Database error: ' . $e->getMessage(), self::STATUS_HTTP_ERROR
            );
        }

        $duration = microtime(true) - $dbStartTime;
        if ($duration > 10) {
            error_log("SystemStatus WARNING: Database check took $duration seconds");
        }

        // This may be called frequently, don't leave sessions dangling
        $this->sessionManager->destroy();

        $duration = microtime(true) - $startTime;
        if ($duration > 10) {
            error_log("SystemStatus WARNING: Health check took $duration seconds");
        }

        return $this->formatResponse('');
    }
}
