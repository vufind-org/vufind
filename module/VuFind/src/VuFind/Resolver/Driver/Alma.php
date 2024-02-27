<?php

/**
 * Alma Link Resolver Driver
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2019
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
 * @package  Resolver_Drivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:link_resolver_drivers Wiki
 */

namespace VuFind\Resolver\Driver;

use function in_array;

/**
 * Alma Link Resolver Driver
 *
 * @category VuFind
 * @package  Resolver_Drivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:link_resolver_drivers Wiki
 */
class Alma extends AbstractBase
{
    /**
     * HTTP client
     *
     * @var \Laminas\Http\Client
     */
    protected $httpClient;

    /**
     * List of filter reasons that are ignored (displayed regardless of filtering)
     *
     * @var array
     */
    protected $ignoredFilterReasons = ['Date Filter'];

    /**
     * Constructor
     *
     * @param string               $baseUrl    Base URL for link resolver
     * @param \Laminas\Http\Client $httpClient HTTP client
     * @param array                $options    OpenURL Configuration (optional)
     */
    public function __construct(
        $baseUrl,
        \Laminas\Http\Client $httpClient,
        array $options = []
    ) {
        parent::__construct($baseUrl);
        $this->httpClient = $httpClient;
        if (isset($options['ignoredFilterReasons'])) {
            $this->ignoredFilterReasons
                = empty($options['ignoredFilterReasons'])
                    ? [] : array_filter((array)$options['ignoredFilterReasons']);
        }
    }

    /**
     * Fetch Links
     *
     * Fetches a set of links corresponding to an OpenURL
     *
     * @param string $openURL openURL (url-encoded)
     *
     * @return string         Raw XML returned by resolver
     */
    public function fetchLinks($openURL)
    {
        // Make the call to Alma and load results
        $url = $this->getResolverUrl(
            'svc_dat=CTO&response_type=xml&' . $openURL
        );
        return $this->httpClient->setUri($url)->send()->getBody();
    }

    /**
     * Parse Links
     *
     * Parses an XML file returned by a link resolver
     * and converts it to a standardised format for display
     *
     * @param string $xmlstr Raw XML returned by resolver
     *
     * @return array         Array of values
     */
    public function parseLinks($xmlstr)
    {
        $records = []; // array to return
        try {
            $xml = new \SimpleXmlElement($xmlstr);
        } catch (\Exception $e) {
            return $records;
        }

        foreach ($xml->context_services->children() as $service) {
            $filtered = $this->getKeyWithId($service, 'Filtered');
            if ('true' === $filtered) {
                $reason = $this->getKeyWithId($service, 'Filter reason');
                if (!in_array($reason, $this->ignoredFilterReasons)) {
                    continue;
                }
            }
            $originalServiceType = (string)$service->attributes()->service_type;
            $serviceType = $this->mapServiceType($originalServiceType);
            if (!$serviceType) {
                continue;
            }
            if ('getWebService' === $serviceType) {
                $title = $this->getKeyWithId($service, 'public_name');
                $href = $this->getKeyWithId($service, 'url');
                $access = '';
            } else {
                $title = $this->getKeyWithId($service, 'package_display_name');
                if (!$title) {
                    $title = $this->getKeyWithId($service, 'package_public_name');
                }
                $href = (string)$service->resolution_url;
                if (
                    'getOpenAccessFullText' === $originalServiceType
                    || $this->getKeyWithId($service, 'Is_free')
                ) {
                    $access = 'open';
                } else {
                    $access = 'limited';
                }
            }
            if ($coverage = $this->getKeyWithId($service, 'Availability')) {
                $coverage = $this->cleanupText($coverage);
            }
            if ($notes = $this->getKeyWithId($service, 'public_note')) {
                $notes = $this->cleanupText($notes);
            }
            $authentication = $this->getKeyWithId($service, 'Authentication_note');
            if ($authentication) {
                $authentication = $this->cleanupText($authentication);
            }

            $record = compact(
                'title',
                'coverage',
                'access',
                'href',
                'notes',
                'authentication'
            );
            $record['service_type'] = $serviceType;
            $records[] = $record;
        }
        return $records;
    }

    /**
     * Get a key with the specified id from the context_service element
     *
     * @param \SimpleXMLElement $service Service element
     * @param string            $id      Key id
     *
     * @return string
     */
    protected function getKeyWithId(\SimpleXMLElement $service, $id)
    {
        foreach ($service->keys->children() as $key) {
            if ((string)$key->attributes()->id === $id) {
                return (string)$key;
            }
        }
        return '';
    }

    /**
     * Map Alma service types to VuFind. Returns an empty string for an unmapped
     * value.
     *
     * @param string $serviceType Alma service type
     *
     * @return string
     */
    protected function mapServiceType($serviceType)
    {
        $map = [
            'getFullTxt' => 'getFullTxt',
            'getOpenAccessFullText' => 'getFullTxt',
            'getHolding' => 'getHolding',
            'GeneralElectronicService' => 'getWebService',
            'DB' => 'getFullTxt',
            'Package' => 'getFullTxt',
        ];
        return $map[$serviceType] ?? '';
    }

    /**
     * Clean up textual information
     *
     * @param string $str Text
     *
     * @return string
     */
    protected function cleanupText($str)
    {
        $str = trim(preg_replace('/<br\/?>/', ' ', $str));
        $str = strip_tags($str);
        return $str;
    }
}
