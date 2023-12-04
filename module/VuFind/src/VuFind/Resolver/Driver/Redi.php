<?php

/**
 * ReDi Link Resolver Driver
 *
 * PHP version 8
 *
 * Copyright (C) Leipzig University Library 2015
 *
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
 * @author   André Lahmann <lahmann@ub.uni-leipzig.de>
 * @author   Gregor Gawol <gawol@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:link_resolver_drivers Wiki
 */

namespace VuFind\Resolver\Driver;

use DOMDocument;
use Laminas\Dom\DOMXPath;

use function chr;
use function count;

/**
 * ReDi Link Resolver Driver
 *
 * @category VuFind
 * @package  Resolver_Drivers
 * @author   André Lahmann <lahmann@ub.uni-leipzig.de>
 * @author   Gregor Gawol <gawol@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:link_resolver_drivers Wiki
 */
class Redi extends AbstractBase
{
    /**
     * HTTP client
     *
     * @var \Laminas\Http\Client
     */
    protected $httpClient;

    /**
     * Parsed resolver links
     *
     * @var array
     */
    protected $links;

    /**
     * Constructor
     *
     * @param string               $baseUrl    Base URL for link resolver
     * @param \Laminas\Http\Client $httpClient HTTP client
     */
    public function __construct($baseUrl, \Laminas\Http\Client $httpClient)
    {
        parent::__construct($baseUrl);
        $this->httpClient = $httpClient;
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
        $url = $this->getResolverUrl($openURL);
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
        $xml = new DOMDocument();
        if (!@$xml->loadHTML($xmlstr)) {
            return [];
        }

        // parse the raw resolver-data
        $this->links = array_merge(
            $this->parseDOI($xml),
            $this->parseRediOpenURLs($xml)
        );

        // perform (individual) postprocessing on parsed resolver-data
        $this->postProcessing();

        return $this->links;
    }

    /**
     * Parse the Redi XML response and return array with DOI information.
     *
     * @param DOMDocument $xml Loaded xml document
     *
     * @return array Get back a array with title, URL and service_type
     */
    protected function parseDOI($xml)
    {
        $retval = [];

        $xpath = new DOMXPath($xml);

        $doiTerm = $xpath
            ->query("//dt[@class='doi_t']");
        $doiDefinition = $xpath
            ->query("//dd[@class='doi_d']");

        if ($doiTerm->length == $doiDefinition->length) {
            for ($i = 0; $i < $doiTerm->length; $i++) {
                $href = $xpath
                    ->query('.//@href', $doiDefinition->item($i))
                    ->item(0)->textContent;
                $retval[] = [
                    'title' => $doiTerm->item($i)->textContent
                        . $doiDefinition->item($i)->textContent,
                    'href' => $href,
                    'access' => 'unknown',
                    'coverage' => null,
                    'service_type' => 'getDOI',
                ];
            }
        }

        return $retval;
    }

    /**
     * Parse Redi additional information elements and return the one identified by
     * the infoToken provided (e.g. "*")
     *
     * @param DOMDocument $xml       Loaded xml document
     * @param string      $infoToken InfoToken to search for
     *
     * @return string
     */
    protected function parseRediInfo($xml, $infoToken)
    {
        $xpath = new DOMXPath($xml);

        // additional info nodes - marked by "<sup>*</sup>"
        $infoTokenNodes = $xpath->query("//div[@id='t_ezb']/div[@class='t']/p/sup");

        if ($infoTokenNodes->length > 0) {
            for ($i = 0; $i < $infoTokenNodes->length; $i++) {
                if ($infoToken == $infoTokenNodes->item($i)->textContent) {
                    return $xpath
                        ->query("//div[@id='t_ezb']/div[@class='t']/p/sup/..")
                        ->item($i)->textContent;
                }
            }
        }

        return '';
    }

    /**
     * Parse if the Redi xml snippet contains Redi urls.
     *
     * @param DOMDocument $xml Loaded xml document
     *
     * @return array Get back Redi direct link to sources containing title, URL and
     *               service_type
     */
    protected function parseRediOpenURLs($xml)
    {
        $retval = [];

        $xpath = new DOMXPath($xml);

        $ezbResultsNodesText = $xpath
            ->query("//div[@class='t_ezb_result']/p");
        $ezbResultsNodesURL = $xpath
            ->query("//div[@class='t_ezb_result']/p/span[@class='t_link']/a");

        if ($ezbResultsNodesText->length == $ezbResultsNodesURL->length) {
            for ($i = 0; $i < $ezbResultsNodesText->length; $i++) {
                $accessClass = 'unknown';
                $accessClassExpressions = [
                    'denied'    => "//div[@class='t_ezb_result']["
                        . ($i + 1) . "]/p/span[@class='t_ezb_red']",
                    'limited' => "//div[@class='t_ezb_result']["
                        . ($i + 1) . "]/p/span[@class='t_ezb_yellow']",
                    'open'  => "//div[@class='t_ezb_result']["
                        . ($i + 1) . "]/p/span[@class='t_ezb_green']",
                ]; // $i+1 because XPath-element-counting starts with 1
                foreach ($accessClassExpressions as $key => $value) {
                    if ($xpath->evaluate("count({$value})") == 1) {
                        $accessClass = $key;
                    }
                }

                $itemInfo = '';

                $expression = "//div[@class='t_ezb_result']["
                    . ($i + 1) . ']/p/sup';
                if ($xpath->evaluate("count({$expression})") == 1) {
                    $itemInfo = $this->parseRediInfo(
                        $xml,
                        $xpath->query($expression)->item(0)->textContent
                    );
                }

                $retval[] = [
                    'title' => $ezbResultsNodesText->item($i)->textContent,
                    'href' => $ezbResultsNodesURL->item($i)
                        ->attributes->getNamedItem('href')->textContent,
                    'access'       => $accessClass,
                    'coverage'     => $itemInfo,
                    'service_type' => 'getFullTxt',
                ];
            }
        }

        return $retval;
    }

    /**
     * Hook for post processing of the parsed resolver response (e.g. by removing any
     * double angle quotation mark from each link['title']).
     *
     * @return void
     */
    protected function postProcessing()
    {
        for ($i = 0; $i < count($this->links); $i++) {
            if (isset($this->links[$i]['title'])) {
                $this->links[$i]['title'] = $this
                    ->removeDoubleAngleQuotationMarks($this->links[$i]['title']);
                $this->links[$i]['title'] = trim($this->links[$i]['title']);
            }
            if (isset($this->links[$i]['coverage'])) {
                $this->links[$i]['coverage'] = trim($this->links[$i]['coverage']);
            }
        }
    }

    /**
     * Helper function to remove hardcoded link-string "»" in Redi response
     *
     * @param string $string String to be manipulated
     *
     * @return string
     */
    protected function removeDoubleAngleQuotationMarks($string)
    {
        return str_replace(
            ['»', chr(194) . chr(160)],
            ['', ''],
            $string
        ); // hack to replace \u00a0
    }
}
