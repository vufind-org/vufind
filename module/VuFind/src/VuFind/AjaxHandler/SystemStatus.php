<?php

/**
 * "System Status" AJAX handler.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\AjaxHandler;

use Laminas\Session\SessionManager;
use Lmc\Rbac\Mvc\Service\AuthorizationServiceAwareInterface;
use Lmc\Rbac\Mvc\Service\AuthorizationServiceAwareTrait;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Db\Service\SessionServiceInterface;
use VuFind\Exception\Forbidden;
use VuFind\ILS\Connection;
use VuFind\Search\Results\PluginManager as ResultsManager;

/**
 * "System Status" AJAX handler.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class SystemStatus extends AbstractBase implements \Psr\Log\LoggerAwareInterface, AuthorizationServiceAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;
    use AuthorizationServiceAwareTrait;

    /**
     * Default status check config.
     *
     * @var array
     */
    protected array $defaultStatusCheckConfig = [
        'index' => 'default_enabled',
        'eds' => 'default_disabled',
        'database' => 'default_enabled',
        'ils' => 'default_disabled',
    ];

    /**
     * Constructor.
     *
     * @param SessionManager          $sessionManager Session manager
     * @param ResultsManager          $resultsManager Results manager
     * @param array                   $config         Top-level VuFind configuration (config.ini)
     * @param SessionServiceInterface $sessionService Session database service
     * @param Connection              $ils            ILS connection
     */
    public function __construct(
        protected SessionManager $sessionManager,
        protected ResultsManager $resultsManager,
        protected array $config,
        protected SessionServiceInterface $sessionService,
        protected Connection $ils
    ) {
        parent::__construct(null);
    }

    /**
     * Handle a request.
     *
     * @param ServerRequestInterface $request Request
     *
     * @return array [response data, HTTP status code]
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function handleRequest(ServerRequestInterface $request): array
    {

        if (!$this->getAuthorizationService()->isGranted('access.SystemStatus')) {
            throw new Forbidden('Access denied');
        }

        // Check system status
        $healthCheckFile = $this->config['System']['healthCheckFile'] ?? null;
        if (
            ($healthCheckFile !== null)
            && file_exists($healthCheckFile)
        ) {
            return $this->formatResponse(
                'Health check file exists',
                self::STATUS_HTTP_UNAVAILABLE
            );
        }

        // Test logging (note that the message doesn't need to get written for the log writers to initialize):
        $this->log('info', 'SystemStatus log check', [], true);

        foreach (get_class_methods($this) as $checkMethod) {
            if (!str_ends_with($checkMethod, 'Check')) {
                continue;
            }
            $component = substr($checkMethod, 0, -5);
            $setting = $this->config['System']['statusChecks'][$component]
                ?? $this->defaultStatusCheckConfig[$component]
                ?? 'always_disabled';
            if (
                method_exists($this, $checkMethod)
                && ($setting !== 'always_disabled')
                && $this->getPostOrQueryParam($request, $component, ($setting === 'default_enabled'))
                && $errorResponse = $this->$checkMethod()
            ) {
                return $errorResponse;
            }
        }

        // This may be called frequently, don't leave sessions dangling
        $this->sessionManager->destroy();

        return $this->formatResponse('');
    }

    /**
     * Check the index connection.
     *
     * @return array
     */
    protected function indexCheck(): array
    {
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
        return [];
    }

    /**
     * Check the EDS connection.
     *
     * @return array
     */
    protected function edsCheck(): array
    {
        try {
            $results = $this->resultsManager->get('EDS');
            $results->getParams()->setBasicSearch('*');
            $results->performAndProcessSearch();
        } catch (\Exception $e) {
            return $this->formatResponse(
                'EDS connection error: ' . $e->getMessage(),
                self::STATUS_HTTP_ERROR
            );
        }
        return [];
    }

    /**
     * Check the database connection.
     *
     * @return array
     */
    protected function databaseCheck(): array
    {
        try {
            $this->sessionService->getSessionById('healthcheck', false);
        } catch (\Exception $e) {
            return $this->formatResponse(
                'Database error: ' . $e->getMessage(),
                self::STATUS_HTTP_ERROR
            );
        }
        return [];
    }

    /**
     * Check the ils connection.
     *
     * @return array
     */
    protected function ilsCheck(): array
    {
        try {
            if ($this->ils->getOfflineMode(true) == 'ils-offline') {
                throw new \Exception('ILS offline');
            }
        } catch (\Exception $e) {
            return $this->formatResponse(
                'ILS connection error: ' . $e->getMessage(),
                self::STATUS_HTTP_ERROR
            );
        }
        return [];
    }
}
