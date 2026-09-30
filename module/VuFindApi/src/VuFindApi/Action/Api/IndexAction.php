<?php

/**
 * API index page action.
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */

namespace VuFindApi\Action\Api;

use Exception;
use Lmc\Rbac\Mvc\Service\AuthorizationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\ActionConfigManager;
use VuFind\Action\PluginManager as ActionPluginManager;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\ActionHelper\ResponseHelper;
use VuFind\DeveloperSettings\DeveloperSettingsService;
use VuFind\ServiceManager\Factory\Autowire;
use VuFindApi\Action\AbstractApiAction;
use VuFindApi\Action\ApiInterface;

/**
 * API index page action.
 *
 * @category VuFind
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
class IndexAction extends AbstractApiAction
{
    /**
     * Constructor.
     *
     * @param AuthorizationService     $authorizationService     Authorization service
     * @param DeveloperSettingsService $developerSettingsService Developer settings service
     * @param array                    $config                   VuFind configuration
     * @param array                    $appConfig                Application configuration
     * @param ActionPluginManager      $actionPluginManager      Action plugin manager
     * @param ActionConfigManager      $actionConfigManager      Action configuration manager
     */
    public function __construct(
        AuthorizationService $authorizationService,
        DeveloperSettingsService $developerSettingsService,
        #[Autowire(config: 'config')]
        array $config,
        #[Autowire(service: 'Config')]
        protected array $appConfig,
        protected ActionPluginManager $actionPluginManager,
        protected ActionConfigManager $actionConfigManager,
    ) {
        parent::__construct($authorizationService, $developerSettingsService, $config);
    }

    /**
     * Return API specs or redirect to Swagger UI.
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
        if (
            null === $this->getQueryParam('swagger')
            && null === $this->getQueryParam('openapi')
        ) {
            $base = rtrim($this->getRouteHelper()->getUrlFromRoute('home'), '/');
            $url = "$base/swagger-ui/?url=" . urlencode("$base/api?openapi");
            return $this->getHelper(RedirectHelper::class)->redirectToUrl($response, $url);
        }
        return $this->getHelper(ResponseHelper::class)->getJsonResponse(
            $response,
            $this->getApiSpecs(),
            jsonFlags: JSON_PRETTY_PRINT
        );
    }

    /**
     * Get API specification fragment for services provided by the action.
     *
     * @return array
     */
    public function getApiSpecFragment(): array
    {
        return json_decode(
            $this->getTemplateRenderer()
                ->renderTemplateAsString(template: 'api/openapi', params: $this->getOpenApiTemplateParams()),
            true
        );
    }

    /**
     * Initialize the action.
     *
     * @return void
     */
    protected function init(): void
    {
        $this->disableSessionWrites();
    }

    /**
     * Merge specification fragments from all APIs to an array.
     *
     * @return array
     */
    protected function getApiSpecs(): array
    {
        $fragments = [
            'index' => $this->getApiSpecFragment(),
        ];
        foreach ($this->appConfig['vufind_api']['actions_for_specs'] ?? [] as $actionId) {
            $action = $this->actionPluginManager->get($actionId);
            $this->actionConfigManager->applyActionConfig($action, null, $actionId);
            if (!($action instanceof ApiInterface)) {
                throw new Exception($action::class . ' does not implement ApiInterface');
            }
            $fragments[$actionId] = $action->getApiSpecFragment();
        }

        $results = [];
        foreach ($fragments as $fragment) {
            foreach ($fragment as $key => $spec) {
                if (isset($results[$key])) {
                    if ('components' === $key) {
                        $results['components']['schemas'] = array_merge(
                            $results['components']['schemas'] ?? [],
                            $spec['schemas'] ?? []
                        );
                        if (array_diff(array_keys($spec), ['schemas'])) {
                            throw new Exception("Only 'schemas' element is supported for 'components'");
                        }
                    } else {
                        $results[$key] = array_merge($results[$key], $spec);
                    }
                } else {
                    $results[$key] = $spec;
                }
            }
        }

        return $results;
    }
}
