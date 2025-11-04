<?php

/**
 * User preference support service
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2023.
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
 * @package  Service
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\Service;

use VuFind\Auth\Manager as AuthManager;
use VuFind\Cookie\CookieManager;

use function is_array;

/**
 * User preference support service
 *
 * @category VuFind
 * @package  Service
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class UserPreferenceService
{
    /**
     * Authentication manager
     *
     * @var AuthManager
     */
    protected $authManager;

    /**
     * Cookie manager
     *
     * @var ?CookieManager
     */
    protected $cookieManager;

    /**
     * Temporal cache
     *
     * @var array
     */
    protected $cache = [];

    /**
     * Constructor.
     *
     * @param AuthManager    $authManager   Authentication manager
     * @param ?CookieManager $cookieManager Cookie manager
     */
    public function __construct(
        AuthManager $authManager,
        ?CookieManager $cookieManager = null
    ) {
        $this->authManager = $authManager;
        $this->cookieManager = $cookieManager;
    }

    /**
     * Get preferred data sources in order of importance for a user
     *
     * @return array
     */
    public function getPreferredDataSources(): array
    {
        $cacheKey = __FUNCTION__;
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }
        $result = [];

        // preferredRecordSource cookie defines the primary sources:
        if ($this->cookieManager) {
            $preferred = $this->cookieManager->get('preferredRecordSource');
            if (!empty($preferred)) {
                // Check if the cookie is a JSON array:
                if ('[' === mb_substr($preferred, 0, 1)) {
                    $preferred = json_decode($preferred);
                    if (is_array($preferred)) {
                        $result = $preferred;
                    }
                } else {
                    $result = [$preferred];
                }
            }
        }

        // Selected library card is used as secondary/fallback:
        if ($user = $this->authManager->getUserObject()) {
            if ($catUsername = $user->getCatUsername()) {
                [$source] = explode('.', $catUsername, 2);
                $result[] = $source;
            }
        }

        return $this->cache[$cacheKey] = $result;
    }
}
