<?php

/**
 * XML external entity loader with caching support
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind
 * @package  Util
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\Util;

use Laminas\Log\LoggerAwareInterface;
use VuFind\Cache\CacheTrait;
use VuFind\Log\LoggerAwareTrait;
use VuFindHttp\HttpServiceAwareInterface;
use VuFindHttp\HttpServiceAwareTrait;

/**
 * XML external entity loader with caching support
 *
 * @category VuFind
 * @package  Util
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class CachingXmlEntityLoader implements HttpServiceAwareInterface, LoggerAwareInterface
{
    use CacheTrait;
    use HttpServiceAwareTrait;
    use LoggerAwareTrait;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Cache items for two weeks:
        $this->cacheLifetime = 60 * 60 * 24 * 14;
    }

    /**
     * Load an external entity from cache or external site
     *
     * @param ?string $publicId Public ID
     * @param string  $systemId System ID
     * @param array   $context  Context
     *
     * @return resource|string|null
     */
    public function resolve(?string $publicId, string $systemId, array $context)
    {
        // Cache entities loaded from http(s) URLs:
        if (str_starts_with($systemId, 'http://') || str_starts_with($systemId, 'https://')) {
            if (null === ($data = $this->getCachedData($systemId))) {
                $result = $this->httpService->get($systemId);
                if (!$result->isSuccess()) {
                    $this->logError(
                        'Could not load external entity $systemId: ' . $result->getStatusCode()
                        . ' ' . $result->getReasonPhrase()
                    );
                    return $systemId;
                }
                $data = $result->getBody();
                $this->putCachedData($systemId, $data);
            }
        } else {
            $data = file_get_contents($systemId);
        }
        // Return as a resource:
        $f = fopen('php://temp', 'r+');
        fwrite($f, $data);
        rewind($f);
        return $f;
    }
}
