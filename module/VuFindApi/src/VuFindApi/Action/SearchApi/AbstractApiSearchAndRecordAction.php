<?php

/**
 * Abstract base class for API search and record actions.
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

use Lmc\Rbac\Mvc\Service\AuthorizationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\BackendIdInterface;
use VuFind\Action\CustomConfigInterface;
use VuFind\Action\SearchClassIdInterface;
use VuFind\Config\ConfigManager;
use VuFind\DeveloperSettings\DeveloperSettingsService;
use VuFind\Exception\ConfigException;
use VuFind\Search\Options\PluginManager as SearchOptionsPluginManager;
use VuFind\ServiceManager\Factory\Autowire;
use VuFindApi\Action\AbstractApiAction;
use VuFindApi\Formatter\RecordFormatter;

use function in_array;
use function is_array;

/**
 * Abstract base class for API search and record actions.
 *
 * @category VuFind
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
abstract class AbstractApiSearchAndRecordAction extends AbstractApiAction implements
    BackendIdInterface,
    CustomConfigInterface,
    SearchClassIdInterface
{
    /**
     * Search backend identifier.
     *
     * @var ?string
     */
    protected ?string $backendId = null;

    /**
     * Search class to use.
     *
     * @var ?string
     */
    protected ?string $searchClassId = null;

    /**
     * Record URL path for API spec.
     *
     * @var string
     */
    protected $recordRoute = 'record';

    /**
     * Search URL path for API spec.
     *
     * @var string
     */
    protected $searchRoute = 'search';

    /**
     * Descriptive label for the index managed by this action.
     *
     * @var string
     */
    protected $indexLabel = 'primary';

    /**
     * Prefix for use in model names used by API.
     *
     * @var string
     */
    protected $modelPrefix = '';

    /**
     * Max limit of search results in API response (default 100);
     * Applies to record requests and searches not using resumptionToken.
     *
     * @var int
     */
    protected $maxLimit = 100;

    /**
     * Default max limit for cursor based search. Even if cursor search is cheaper in terms of processing in Solr,
     * PHP memory still has limitations so set the default to be a decent amount. (Default 200).
     * Value is adjustable in searches.ini [API] cursorLimit.
     *
     * @var int
     */
    protected $cursorLimit = 200;

    /**
     * Facet configuration.
     *
     * @var array
     */
    protected $facetConfig;

    /**
     * Hierarchical facets.
     *
     * @var array
     */
    protected $hierarchicalFacets;

    /**
     * Record field config file.
     *
     * @var string
     */
    protected string $recordFieldConfigFile = 'SearchApiRecordFields';

    /**
     * Constructor.
     *
     * @param AuthorizationService       $authorizationService       Authorization service
     * @param DeveloperSettingsService   $developerSettingsService   Developer settings service
     * @param array                      $config                     VuFind configuration
     * @param RecordFormatter            $recordFormatter            Record formatter
     * @param ConfigManager              $configManager              Configuration manager
     * @param SearchOptionsPluginManager $searchOptionsPluginManager Search options plugin manager
     */
    public function __construct(
        AuthorizationService $authorizationService,
        DeveloperSettingsService $developerSettingsService,
        #[Autowire(config: 'config')]
        array $config,
        protected RecordFormatter $recordFormatter,
        protected ConfigManager $configManager,
        protected SearchOptionsPluginManager $searchOptionsPluginManager,
    ) {
        parent::__construct($authorizationService, $developerSettingsService, $config);
    }

    /**
     * Get backend identifier.
     *
     * @return string
     */
    public function getBackendId(): string
    {
        if (null === $this->backendId) {
            throw new ConfigException('Backend ID not properly configured.');
        }
        return $this->backendId;
    }

    /**
     * Set backend identifier.
     *
     * @param string $id Backend identifier
     *
     * @return static
     */
    public function setBackendId(string $id): static
    {
        $this->backendId = $id;
        return $this;
    }

    /**
     * Set custom configuration.
     *
     * @param array $config Configuration
     *
     * @return static
     */
    public function setCustomConfig(array $config): static
    {
        $allowedKeys = [
            'recordRoute',
            'searchRoute',
            'indexLabel',
            'modelPrefix',
            'maxLimit',
            'cursorLimit',
            'recordFieldConfigFile',
        ];
        foreach ($config as $key => $value) {
            if (!in_array($key, $allowedKeys)) {
                throw new ConfigException("Invalid custom config key: $key");
            }
            $this->$key = $value;
        }
        return $this;
    }

    /**
     * Get search class identifier.
     *
     * @return string
     */
    public function getSearchClassId(): string
    {
        // Return backend id if search class id is not set:
        return $this->searchClassId ?? $this->getBackendId();
    }

    /**
     * Set search class identifier.
     *
     * @param string $id Search class identifier
     *
     * @return static
     */
    public function setSearchClassId(string $id): static
    {
        $this->searchClassId = $id;
        return $this;
    }

    /**
     * Get API specification fragment for services provided by the action.
     *
     * @return array|string An array or a JSON string
     */
    public function getApiSpecFragment(): array|string
    {
        $params = [
            'config' => $this->config,
            'apiKeysEnabled' => $this->developerSettingsService?->apiKeysEnabled() ?? false,
            'apiKeyHeaderField' => $this->apiKeyHeaderField,
            'apiKeyMode' => $this->developerSettingsService?->getApiKeyMode(),
            'version' => \VuFind\Config\Version::getBuildVersion(),
        ];
        return $this->getTemplateRenderer()->renderTemplateAsString(template: 'api/openapi', params: $params);
    }

    /**
     * Check that everything is in order for the action to be executed.
     *
     * This method is executed in the very beginning of the action invocation before any permission checks etc.
     * It is meant for technical checks such as route-based configuration being correctly applied.
     * It may return a suitable response or throw an exception if there are issues.
     *
     * @param ServerRequestInterface $request  Request
     * @param ResponseInterface      $response Response
     *
     * @return ?ResponseInterface
     */
    protected function validateActionConfig(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ?ResponseInterface {
        if ($result = parent::validateActionConfig($request, $response)) {
            return $result;
        }

        if (null === $this->backendId) {
            throw new ConfigException('Backend ID not properly configured.');
        }
        return null;
    }

    /**
     * Get field list based on the request.
     *
     * @param array $request Request params
     *
     * @return array
     */
    protected function getFieldList(array $request): array
    {
        $fieldList = [];
        if (isset($request['field'])) {
            if (!empty($request['field']) && is_array($request['field'])) {
                $fieldList = $request['field'];
            }
        } else {
            $fieldList = $this->getDefaultRecordFieldNames();
        }
        return $fieldList;
    }

    /**
     * Get default record field names.
     *
     * @return array
     */
    protected function getDefaultRecordFieldNames(): array
    {
        return array_keys(
            array_filter(
                $this->getRecordFieldConfig(),
                fn ($fieldSpec) => $fieldSpec['vufind.default'] ?? false
            )
        );
    }

    /**
     * Get optional record field names.
     *
     * @return array
     */
    protected function getOptionalRecordFieldNames(): array
    {
        return array_keys(
            array_filter(
                $this->getRecordFieldConfig(),
                fn ($fieldSpec) => !($fieldSpec['vufind.default'] ?? false)
            )
        );
    }

    /**
     * Get record field configuration.
     *
     * @return array
     */
    protected function getRecordFieldConfig(): array
    {
        return $this->configManager->getConfigArray($this->getRecordFieldConfigFile());
    }

    /**
     * Get default record configuration file name.
     *
     * @return string
     */
    protected function getRecordFieldConfigFile(): string
    {
        if (null === $this->recordFieldConfigFile) {
            throw new ConfigException('Record field config file not properly configured.');
        }
        return $this->recordFieldConfigFile;
    }
}
