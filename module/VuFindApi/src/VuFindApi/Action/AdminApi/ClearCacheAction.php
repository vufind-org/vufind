<?php

/**
 * AdminApi clear cache action.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2026.
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

namespace VuFindApi\Action\AdminApi;

use Laminas\Cache\Storage\FlushableInterface;
use Lmc\Rbac\Mvc\Service\AuthorizationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Cache\Manager as CacheManager;
use VuFind\DeveloperSettings\DeveloperSettingsService;
use VuFind\ServiceManager\Factory\Autowire;
use VuFindApi\Action\AbstractApiAction;

/**
 * AdminApi clear cache action.
 *
 * @category VuFind
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
class ClearCacheAction extends AbstractApiAction
{
    /**
     * Constructor.
     *
     * @param AuthorizationService     $authorizationService     Authorization service
     * @param DeveloperSettingsService $developerSettingsService Developer settings service
     * @param array                    $config                   VuFind configuration
     * @param CacheManager             $cacheManager             Cache manager
     */
    public function __construct(
        AuthorizationService $authorizationService,
        protected DeveloperSettingsService $developerSettingsService,
        #[Autowire(config: 'config')]
        array $config,
        protected CacheManager $cacheManager,
    ) {
        parent::__construct($authorizationService, $developerSettingsService, $config);
        // Force the access permission so that the API spec works:
        $this->accessPermission = 'access.api.admin.cache';
    }

    /**
     * Clear caches.
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
        try {
            $cacheList = $this->getQueryParam('id') ?: $this->getDefaultCachesToClear();
            foreach ((array)$cacheList as $id) {
                $cache = $this->cacheManager->getCache($id);
                if ($cache instanceof FlushableInterface) {
                    $cache->flush();
                }
            }
        } catch (\Exception $e) {
            return $this->output([], self::STATUS_ERROR, 500, $e->getMessage());
        }

        return $this->output([], self::STATUS_OK);
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
     * Get API specification fragment for services provided by the action.
     *
     * @return array
     */
    public function getApiSpecFragment(): array
    {
        $spec = [];
        if (!$this->isAccessDenied($this->accessPermission)) {
            $defaultCaches = implode(',', $this->getDefaultCachesToClear());
            $spec['paths']['/admin/cache']['delete'] = [
                'summary' => 'Clear caches',
                'description' => 'Flushes the specified caches',
                'parameters' => [
                    [
                        'name' => 'id[]',
                        'in' => 'query',
                        'description' => "Caches to clear. By default the following caches are cleared: $defaultCaches",
                        'required' => false,
                        'style' => 'form',
                        'explode' => true,
                        'schema' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'string',
                            ],
                        ],
                    ],
                ],
                'tags' => ['admin'],
                'responses' => [
                    '200' => [
                        'description' => 'An OK response',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    '$ref' => '#/components/schemas/Success',
                                ],
                            ],
                        ],
                    ],
                    'default' => [
                        'description' => 'Error',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    '$ref' => '#/components/schemas/Error',
                                ],
                            ],
                        ],
                    ],
                ],
            ];
        }

        return $spec;
    }

    /**
     * Get an array of caches to clear by default.
     *
     * @return array
     */
    protected function getDefaultCachesToClear(): array
    {
        $result = [];
        foreach ($this->cacheManager->getNonPersistentCacheList() as $id) {
            $cache = $this->cacheManager->getCache($id);
            if ($cache instanceof \Laminas\Cache\Storage\FlushableInterface) {
                $result[] = $id;
            }
        }
        return $result;
    }
}
