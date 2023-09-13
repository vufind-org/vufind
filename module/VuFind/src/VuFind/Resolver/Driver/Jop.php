<?php

/**
 * JOP Link Resolver Driver
 *
 * JOP is a free service -- the API endpoint is available at
 * http://services.dnb.de/fize-service/gvr/full.xml
 *
 * API documentation is available at
 * http://www.zeitschriftendatenbank.de/services/journals-online-print
 *
 * PHP version 8
 *
 * Copyright (C) Markus Fischer, info@flyingfischer.ch
 *
 * last update: 2011-04-13
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
 * @author   Markus Fischer <info@flyingfischer.ch>
 * @author   André Lahmann <lahmann@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:link_resolver_drivers Wiki
 */

namespace VuFind\Resolver\Driver;

use DOMDocument;
use DOMXpath;
use VuFind\Net\UserIpReader;

use function in_array;

/**
 * JOP Link Resolver Driver
 *
 * @category VuFind
 * @package  Resolver_Drivers
 * @author   Markus Fischer <info@flyingfischer.ch>
 * @author   André Lahmann <lahmann@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:link_resolver_drivers Wiki
 */
class Jop extends AbstractBase
{
    /**
     * As the JOP resolver provides also generic labels 'Article', 'Journal'
     * etc. in element AccessLevel this label can be used as title for
     * resolver results by setting this variable to 'AccessLevel'
     *
     * @var string
     */
    protected $xpathTitleSelector = 'Title';

    /**
     * HTTP client
     *
     * @var \Laminas\Http\Client
     */
    protected $httpClient;

    /**
     * User IP address reader
     *
     * @var UserIpReader
     */
    protected $userIpReader;

    /**
     * Constructor
     *
     * @param string               $baseUrl      Base URL for link resolver
     * @param \Laminas\Http\Client $httpClient   HTTP client
     * @param UserIpReader         $userIpReader User IP address reader
     */
    public function __construct(
        $baseUrl,
        \Laminas\Http\Client $httpClient,
        UserIpReader $userIpReader
    ) {
        parent::__construct($baseUrl);
        $this->httpClient = $httpClient;
        $this->userIpReader = $userIpReader;
    }

    /**
     * Fetch Links
     *
     * Fetches a set of links corresponding to an OpenURL
     *
     * @param string $openURL openURL (url-encoded)
     *
     * @return string         raw XML returned by resolver
     */
    public function fetchLinks($openURL)
    {
        // Get the actual resolver url for the given openUrl
        $url = $this->getResolverUrl($openURL);

        // Make the call to the JOP and load results
        $feed = $this->httpClient->setUri($url)->send()->getBody();
        return $feed;
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

        $xml = new DOMDocument();
        if (!@$xml->loadXML($xmlstr)) {
            return $records;
        }

        $xpath = new DOMXpath($xml);

        // get results for online
        $this->getElectronicResults('0', 'Free', $records, $xpath);
        $this->getElectronicResults('1', 'Partially free', $records, $xpath);
        $this->getElectronicResults('2', 'Licensed', $records, $xpath);
        $this->getElectronicResults('3', 'Partially licensed', $records, $xpath);
        $this->getElectronicResults('4', 'Not free', $records, $xpath);
        $this->getElectronicResults('10', 'Unknown Electronic', $records, $xpath);

        // get results for print, only if available
        $this->getPrintResults('2', 'Print available', $records, $xpath);
        $this->getPrintResults('3', 'Print partially available', $records, $xpath);
        $this->getPrintResults('10', 'Unknown Print', $records, $xpath);

        return $records;
    }

    /**
     * Get Resolver Url
     *
     * Transform the OpenURL as needed to get a working link to the resolver.
     *
     * @param string $openURL openURL (url-encoded)
     *
     * @return string Link
     */
    public function getResolverUrl($openURL)
    {
        // Unfortunately the JOP-API only allows OpenURL V0.1 and
        // breaks when sending a non expected parameter.
        // So we do have to 'downgrade' the OpenURL-String from V1.0 to V0.1
        // and exclude all parameters that are not compliant with the JOP.

        // Parse OpenURL into associative array:
        $tmp = explode('&', $openURL);
        $parsed = [];

        foreach ($tmp as $current) {
            $tmp2 = explode('=', $current, 2);
            $parsed[$tmp2[0]] = $tmp2[1] ?? null;
        }

        // Downgrade 1.0 to 0.1
        if ($parsed['ctx_ver'] ?? null == 'Z39.88-2004') {
            $openURL = $this->downgradeOpenUrl($parsed);
        }

        // make the request IP-based to allow automatic
        // indication on institution level
        $ipAddr = $this->userIpReader->getUserIp();
        $openURL .= '&pid=client_ip%3D' . urlencode($ipAddr);

        // Make the call to the JOP and load results
        $url = $this->baseUrl . '?' . $openURL;

        return $url;
    }

    /**
     * Allows for resolver driver specific enabling/disabling of the more options
     * link which will link directly to the resolver URL. This should return false if
     * the resolver returns data in XML or any other human unfriendly response.
     *
     * @return bool
     */
    public function supportsMoreOptionsLink()
    {
        // the JOP link resolver returns unstyled XML which is not helpful for the
        // user
        return false;
    }

    /**
     * Downgrade an OpenURL from v1.0 to v0.1 for compatibility with JOP.
     *
     * @param array $parsed Array of parameters parsed from the OpenURL.
     *
     * @return string       JOP-compatible v0.1 OpenURL
     */
    protected function downgradeOpenUrl($parsed)
    {
        // we need 'genre' but only the values
        // article or journal are allowed...
        $downgraded[] = 'genre=article';

        // prepare content for downgrading
        // resolver only accepts date formats YYYY, YYYY-MM, and YYYY-MM-DD
        // in case we have a date in another format, drop the date information
        if (
            isset($parsed['rft.date'])
            && !preg_match('/^\d{4}(-\d\d(-\d\d)?)?$/', $parsed['rft.date'])
        ) {
            unset($parsed['rft.date']);
        }

        $map = [
            'rfr_id' => 'sid',
            'rft.date' => 'date',
            'rft.issn' => 'issn',
            'rft.isbn' => 'isbn', // isbn is supported as of 12/2021
            'rft.volume' => 'volume',
            'rft.issue' => 'issue',
            'rft.spage' => 'spage',
            'rft.pages' => 'pages',
        ];

        // ignore all other parameters
        foreach ($parsed as $key => $value) {
            // exclude empty parameters
            if (isset($value) && $value !== '') {
                if (isset($map[$key])) {
                    $downgraded[] = "{$map[$key]}=$value";
                } elseif (in_array($key, $map)) {
                    $downgraded[] = "$key=$value";
                }
            }
        }

        return implode('&', $downgraded);
    }

    /**
     * Extract electronic results from the JOP response and inject them into the
     * $records array.
     *
     * @param string   $state    The state attribute value to extract
     * @param string   $coverage The coverage string to associate with the state
     * @param array    $records  The array of results to update
     * @param DOMXpath $xpath    The XPath object containing parsed XML
     *
     * @return void
     */
    protected function getElectronicResults($state, $coverage, &$records, $xpath)
    {
        $results = $xpath->query(
            '/OpenURLResponseXML/Full/ElectronicData/ResultList/Result[@state=' .
            $state . ']'
        );

        /*
         * possible state values:
         * -1 ISSN nicht eindeutig
         *  0 Standort-unabhängig frei zugänglich
         *  1 Standort-unabhängig teilweise zugänglich (Unschärfe bedingt durch
         *    unspezifische Anfrage oder Moving-Wall)
         *  2 Lizenziert
         *  3 Für gegebene Bibliothek teilweise lizenziert (Unschärfe bedingt durch
         *    unspezifische Anfrage oder Moving-Wall)
         *  4 nicht lizenziert
         *  5 Zeitschrift gefunden
         *    Angaben über Erscheinungsjahr, Datum ... liegen außerhalb des
         *    hinterlegten bibliothekarischen Zeitraums
         * 10 Unbekannt (ISSN unbekannt, Bibliothek unbekannt)
         */
        $state_access_mapping = [
            '-1' => 'error',
            '0'  => 'open',
            '1'  => 'open',
            '2'  => 'limited',
            '3'  => 'limited',
            '4'  => 'denied',
            '5'  => 'denied',
            '10' => 'unknown',
        ];

        $i = 0;
        foreach ($results as $result) {
            $record = [];

            // get title from XPath Element defined in $xpathTitleSelector
            $titleXP = '/OpenURLResponseXML/Full/ElectronicData/ResultList/' .
                "Result[@state={$state}][" . ($i + 1) . ']/' .
                $this->xpathTitleSelector;
            $title = $xpath->query($titleXP, $result)->item(0);
            if (isset($title)) {
                $record['title'] = strip_tags($title->nodeValue);
            }

            // get additional coverage information
            $additionalXP = '/OpenURLResponseXML/Full/ElectronicData/ResultList/' .
                "Result[@state={$state}][" . ($i + 1) . ']/Additionals/Additional';
            $additionalType = ['nali', 'intervall', 'moving_wall'];
            $additionals = [];
            foreach ($additionalType as $type) {
                $additional = $xpath
                    ->query($additionalXP . "[@type='" . $type . "']", $result)
                    ->item(0);
                if (isset($additional->nodeValue)) {
                    $additionals[$type] = strip_tags($additional->nodeValue);
                }
            }
            $record['coverage']
                = !empty($additionals) ? implode('; ', $additionals) : $coverage;

            $record['access'] = $state_access_mapping[$state];

            // try to find direct access URL
            $accessUrlXP = '/OpenURLResponseXML/Full/ElectronicData/ResultList/' .
                "Result[@state={$state}][" . ($i + 1) . ']/AccessURL';
            $accessUrl = $xpath->query($accessUrlXP, $result)->item(0);

            // try to find journal URL as fallback for direct access URL
            $journalUrlXP = '/OpenURLResponseXML/Full/ElectronicData/ResultList/' .
                "Result[@state={$state}][" . ($i + 1) . ']/JournalURL';
            $journalUrl = $xpath->query($journalUrlXP, $result)->item(0);

            // return direct access URL if available otherwise journal URL fallback
            if (isset($accessUrl->nodeValue)) {
                $record['href'] = $accessUrl->nodeValue;
            } elseif (isset($journalUrl)) {
                $record['href'] = $journalUrl->nodeValue;
            }
            // Service type needs to be hard-coded for calling code to properly
            // categorize links. The commented code below picks a more appropriate
            // value but won't work for now -- retained for future reference.
            //$service_typeXP = "/OpenURLResponseXML/Full/ElectronicData/ResultList/"
            //    . "Result[@state={$state}][".($i+1)."]/AccessLevel";
            //$record['service_type']
            //    = $xpath->query($service_typeXP, $result)->item(0)->nodeValue;
            $record['service_type'] = 'getFullTxt';
            array_push($records, $record);
            $i++;
        }
    }

    /**
     * Extract print results from the JOP response and inject them into the
     * $records array.
     *
     * @param string   $state    The state attribute value to extract
     * @param string   $coverage The coverage string to associate with the state
     * @param array    $records  The array of results to update
     * @param DOMXpath $xpath    The XPath object containing parsed XML
     *
     * @return void
     */
    protected function getPrintResults($state, $coverage, &$records, $xpath)
    {
        $results = $xpath->query(
            "/OpenURLResponseXML/Full/PrintData/ResultList/Result[@state={$state}]"
        );

        /*
         * possible state values:
         * -1 ISSN nicht eindeutig
         *  2 Vorhanden
         *  3 Teilweise vorhanden (Unschärfe bedingt durch unspezifische Anfrage bei
         *    nicht vollständig vorhandener Zeitschrift)
         *  4 Nicht vorhanden
         * 10 Unbekannt (ZDB-ID unbekannt, ISSN unbekannt, Bibliothek unbekannt)
         */
        $state_access_mapping = [
            '-1' => 'error',
            '2'  => 'open',
            '3'  => 'limited',
            '4'  => 'denied',
            '10' => 'unknown',
        ];

        $i = 0;
        foreach ($results as $result) {
            $record = [];
            $record['title'] = $coverage;

            $resultXP = '/OpenURLResponseXML/Full/PrintData/ResultList/' .
                "Result[@state={$state}][" . ($i + 1) . ']';
            $resultElements = [
                'Title', 'Location', 'Signature', 'Period', 'Holding_comment',
            ];
            $elements = [];
            foreach ($resultElements as $element) {
                $elem = $xpath->query($resultXP . '/' . $element, $result)->item(0);
                if (isset($elem->nodeValue)) {
                    $elements[$element] = strip_tags($elem->nodeValue);
                }
            }
            $record['coverage']
                = !empty($elements) ? implode('; ', $elements) : $coverage;

            $record['access'] = $state_access_mapping[$state];

            $urlXP = '/OpenURLResponseXML/Full/PrintData/References/Reference/URL';
            $url = $xpath->query($urlXP, $result)->item($i);
            if (isset($url->nodeValue)) {
                $record['href'] = $url->nodeValue;
            }
            // Service type needs to be hard-coded for calling code to properly
            // categorize links. The commented code below picks a more appropriate
            // value but won't work for now -- retained for future reference.
            //$service_typeXP = "/OpenURLResponseXML/Full/PrintData/References"
            //    . "/Reference/Label";
            //$record['service_type']
            //    = $xpath->query($service_typeXP, $result)->item($i)->nodeValue;
            $record['service_type'] = 'getHolding';
            array_push($records, $record);
            $i++;
        }
    }
}
