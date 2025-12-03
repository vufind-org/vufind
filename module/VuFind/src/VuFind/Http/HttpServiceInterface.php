<?php

/**
 * HTTP service interface.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Http
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development
 */

namespace VuFind\Http;

use Psr\Http\Client\ClientInterface;

/**
 * HTTP service interface.
 *
 * @category VuFind
 * @package  Http
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development
 * @todo     Merge with PSR-18 HTTP Client Service when implemented
 */
interface HttpServiceInterface
{
    /**
     * Default regular expression matching a request to localhost.
     *
     * @var string
     */
    public const LOCAL_ADDRESS_RE = '@^(localhost|127(\.\d+){3}|\[::1\])@';

    /**
     * Return a new HTTP client.
     *
     * @param ?string $url     Target URL (required for proper proxy setup for non-local addresses)
     * @param ?float  $timeout Request timeout in seconds (overrides configuration)
     *
     * @return ClientInterface
     */
    public function createClient(?string $url = null, ?float $timeout = null): ClientInterface;
}
