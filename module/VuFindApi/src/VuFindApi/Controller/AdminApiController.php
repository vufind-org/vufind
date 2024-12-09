<?php

/**
 * Admin Api Controller
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2021.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.    See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA    02111-1307    USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindApi\Controller;

use Laminas\ServiceManager\ServiceLocatorInterface;
use VuFind\Cache\Manager as CacheManager;

/**
 * Admin Api Controller
 *
 * @category VuFind
 * @package  Controller
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class AdminApiController extends \VuFind\Controller\AbstractBase implements ApiInterface
{
    use ApiTrait;

    /**
     * Cache manager
     *
     * @var CacheManager
     */
    protected $cacheManager;

    /**
     * Constructor
     *
     * @param ServiceLocatorInterface $sm Service locator
     * @param CacheManager            $cm Cache manager
     */
    public function __construct(ServiceLocatorInterface $sm, CacheManager $cm)
    {
        parent::__construct($sm);
        $this->cacheManager = $cm;
    }

    /**
     * Permission required for the clear cache endpoint
     *
     * @var string
     */
    protected $cacheAccessPermission = 'access.api.admin.cache';

    /**
     * Clear the cache
     *
     * @return \Laminas\Http\Response
     */
    public function clearCacheAction()
    {
        $this->disableSessionWrites();
        $this->determineOutputMode();

        if ($result = $this->isAccessDenied($this->cacheAccessPermission)) {
            return $result;
        }

        try {
            $cacheList = $this->getRequest()->getQuery()->get('id')
                ?: $this->getDefaultCachesToClear();
            foreach ((array)$cacheList as $id) {
                $this->cacheManager->getCache($id)->flush();
            }
        } catch (\Exception $e) {
            return $this->output([], self::STATUS_ERROR, 500, $e->getMessage());
        }

        return $this->output([], self::STATUS_OK);
    }

    /**
     * Get API specification JSON fragment for services provided by the
     * controller
     *
     * @return string
     */
    public function getApiSpecFragment()
    {
        $spec = [];
        if (!$this->isAccessDenied($this->cacheAccessPermission)) {
            $defaultCaches = implode(',', $this->getDefaultCachesToClear());
            $spec['paths']['/admin/cache']['delete'] = [
                'summary' => 'Clear caches',
                'description' => 'Flushes the specified caches',
                'parameters' => [
                    [
                        'name' => 'id[]',
                        'in' => 'query',
                        'description' => 'Caches to clear. By default the following'
                            . " caches are cleared: $defaultCaches",
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

        return json_encode($spec);
    }

    /**
     * Get an array of caches to clear by default
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
