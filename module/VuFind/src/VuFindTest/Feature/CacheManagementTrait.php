<?php

/**
 * Trait adding the ability to clear object cache.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024.
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
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Feature;

use Laminas\Http\Request;
use VuFindHttp\HttpService;

/**
 * Trait adding the ability to clear object cache.
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
trait CacheManagementTrait
{
    /**
     * Get configuration required for cache clear permission
     *
     * @return array
     */
    protected function getCacheClearPermissionConfig(): array
    {
        return [
            'permissions' => [
                'enable-admin-cache-api' => [
                    'permission' => 'access.api.admin.cache',
                    'require' => 'ANY',
                    'role' => 'guest',
                ],
            ],
        ];
    }

    /**
     * Call the API to clear object cache
     *
     * @return voi
     */
    protected function clearObjectCache(): void
    {
        $http = new HttpService();
        $client = $http->createClient($this->getVuFindUrl('/api/v1/admin/cache?id=object'), Request::METHOD_DELETE);
        $response = $client->send();
        if (200 !== $response->getStatusCode()) {
            throw new \Exception('Could not clear object cache: ' . $response->getBody());
        }
    }
}
