<?php

/**
 * API record action.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2015-2026.
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
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */

namespace VuFindApi\Action\SearchApi;

use Exception;
use Lmc\Rbac\Mvc\Service\AuthorizationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Config\ConfigManager;
use VuFind\DeveloperSettings\DeveloperSettingsService;
use VuFind\Record\Loader as RecordLoader;
use VuFind\ResumptionToken\ResumptionTokenTrait;
use VuFind\Search\Options\PluginManager as SearchOptionsPluginManager;
use VuFind\ServiceManager\Factory\Autowire;
use VuFindApi\Formatter\RecordFormatter;

use function count;
use function is_array;

/**
 * API record action.
 *
 * @category VuFind
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
class RecordAction extends AbstractApiSearchAndRecordAction
{
    use ResumptionTokenTrait;

    /**
     * Record access permission.
     *
     * @var ?string
     */
    protected ?string $recordAccessPermission = null;

    /**
     * Constructor.
     *
     * @param AuthorizationService       $authorizationService       Authorization service
     * @param DeveloperSettingsService   $developerSettingsService   Developer settings service
     * @param array                      $config                     VuFind configuration
     * @param RecordFormatter            $recordFormatter            Record formatter
     * @param ConfigManager              $configManager              Configuration manager
     * @param SearchOptionsPluginManager $searchOptionsPluginManager Search options plugin manager
     * @param RecordLoader               $recordLoader               Record loader
     */
    public function __construct(
        AuthorizationService $authorizationService,
        DeveloperSettingsService $developerSettingsService,
        #[Autowire(config: 'config')]
        array $config,
        RecordFormatter $recordFormatter,
        ConfigManager $configManager,
        SearchOptionsPluginManager $searchOptionsPluginManager,
        protected RecordLoader $recordLoader,
    ) {
        parent::__construct(
            $authorizationService,
            $developerSettingsService,
            $config,
            $recordFormatter,
            $configManager,
            $searchOptionsPluginManager
        );
    }

    /**
     * Perform a search.
     *
     * @param ServerRequestInterface $request  Server request
     * @param ResponseInterface      $response Response
     *
     * @return ResponseInterface
     */
    public function action(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        $this->applyApiSettings();

        // Check for access permission from config:
        if (
            $this->recordAccessPermission
            && ($result = $this->getResponseIfAccessDenied($this->recordAccessPermission))
        ) {
            return $result;
        }

        // Check any API key:
        if (!$this->checkRequestForApiKey()) {
            return $this->outputMissingApiKey();
        }

        $requestParams = $request->getQueryParams() + $request->getParsedBody();

        if (!isset($requestParams['id'])) {
            return $this->output([], self::STATUS_ERROR, 400, 'Missing id');
        }

        try {
            if (is_array($requestParams['id'])) {
                if (count($requestParams['id']) > $this->maxLimit) {
                    return $this->output([], self::STATUS_ERROR, 400, "Record limit ($this->maxLimit) exceeded");
                }
                $results = $this->recordLoader->loadBatchForSource($requestParams['id'], $this->getBackendId());
            } else {
                $results = [
                    $this->recordLoader->load($requestParams['id'], $this->getBackendId()),
                ];
            }
        } catch (Exception $e) {
            return $this->output(
                [],
                self::STATUS_ERROR,
                400,
                'Error loading record'
            );
        }

        $response = [
            'resultCount' => count($results),
        ];
        $requestedFields = $this->getFieldList($requestParams);
        if ($records = $this->recordFormatter->format($results, $requestedFields, $this->getRecordFieldConfig())) {
            $response['records'] = $records;
        }

        return $this->output($response, self::STATUS_OK);
    }

    /**
     * Apply API settings.
     *
     * @return void
     */
    protected function applyApiSettings(): void
    {
        // Apply all supported configurations:
        $options = $this->searchOptionsPluginManager->get($this->getSearchClassId());
        $settings = $options->getAPISettings();
        if (null !== ($maxLimit = $settings['maxLimit'] ?? null)) {
            $this->maxLimit = $maxLimit;
        }
        if (null !== ($permission = $settings['recordAccessPermission'] ?? null)) {
            $this->recordAccessPermission = $permission;
        }
    }

    /**
     * Get API specification fragment for services provided by the action.
     *
     * @return array
     */
    public function getApiSpecFragment(): array
    {
        // All specs are provided by SearchAction.
        return [];
    }
}
