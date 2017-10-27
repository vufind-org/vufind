<?php
/**
 * SFX Link Resolver Driver
 *
 * PHP version 5
 *
 * Copyright (C) Royal Holloway, University of London
 * Copyright (C) The National Library of Finland 2015-2017.
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
 * @author   Graham Seaman <Graham.Seaman@rhul.ac.uk>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:link_resolver_drivers Wiki
 */
namespace Finna\Resolver\Driver;

/**
 * SFX Link Resolver Driver
 *
 * @category VuFind
 * @package  Resolver_Drivers
 * @author   Graham Seaman <Graham.Seaman@rhul.ac.uk>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:link_resolver_drivers Wiki
 */
class Sfx extends \VuFind\Resolver\Driver\Sfx
{
    /**
     * Constructor
     *
     * @param string            $baseUrl    Base URL for link resolver
     * @param \Zend\Http\Client $httpClient HTTP client
     * @param Config            $config     Config
     */
    public function __construct($baseUrl, \Zend\Http\Client $httpClient, $config)
    {
        parent::__construct($baseUrl, $httpClient);
        $timeout = isset($config->Http->timeout)
            ? $config->Http->timeout : 30;
        $this->httpClient->setOptions(['timeout' => $timeout]);
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

        $root = $xml->xpath("//ctx_obj_targets");
        $xml = $root[0];
        foreach ($xml->children() as $target) {
            if ('getMessageNoFullTxt' === (string)$target->service_type
                || 'MESSAGE_NO_FULLTXT' === (string)$target->target_name
            ) {
                continue;
            }
            $record = [];
            $record['title'] = (string)$target->target_public_name;
            $record['href'] = (string)$target->target_url;
            $record['service_type'] = (string)$target->service_type;
            if (isset($target->coverage->coverage_text)) {
                $coverageText = & $target->coverage->coverage_text;
                $record['coverage'] = (string)$coverageText
                    ->threshold_text->coverage_statement;
                if (isset($coverageText->embargo_text->embargo_statement)) {
                    $record['coverage'] .= ' ' . (string)$coverageText
                        ->embargo_text->embargo_statement;
                    $record['embargo'] = (string)$coverageText
                        ->embargo_text->embargo_statement;
                }
            }
            if (isset($target->coverage)) {
                $record['coverage_details'] = json_decode(
                    json_encode($target->coverage),
                    true
                );
            }

            array_push($records, $record);
        }
        return $records;
    }
}
