<?php
/**
 * ReDi Link Resolver Driver
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Resolver_Drivers
 * @author   André Lahmann <lahmann@ub.uni-leipzig.de>
 * @author   Gregor Gawol <gawol@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:link_resolver_drivers Wiki
 */
namespace VuFind\Resolver\Driver;
use DOMDocument, Zend\Dom\DOMXPath;

/**
 * ReDi Link Resolver Driver
 *
 * @category VuFind2
 * @package  Resolver_Drivers
 * @author   André Lahmann <lahmann@ub.uni-leipzig.de>
 * @author   Gregor Gawol <gawol@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:link_resolver_drivers Wiki
 */
class Redi implements DriverInterface
{
    /**
     * HTTP client
     *
     * @var \Zend\Http\Client
     */
    protected $httpClient;

    /**
     * Base URL for link resolver
     *
     * @var string
     */
    protected $baseUrl;

    /**
     * Constructor
     *
     * @param string            $baseUrl    Base URL for link resolver
     * @param \Zend\Http\Client $httpClient HTTP client
     */
    public function __construct($baseUrl, \Zend\Http\Client $httpClient)
    {
        $this->baseUrl = $baseUrl;
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
        $url = $this->baseUrl.'?'.$openURL;
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

        return array_merge(
            $this->parseDOI($xml),
            $this->parseRediOpenURLs($xml)
        );
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
            ->query("//div[@id='citation']/dl/dt[@class='doi_t']");
        $doiDefinition = $xpath
            ->query("//div[@id='citation']/dl/dd[@class='doi_d']");
        $doiDefinitionURL = $xpath
            ->query(
                "//div[@id='citation']/dl/dd[@class='doi_d']/span[@class='t_link']/a"
            );

        if ($doiTerm->length == $doiDefinition->length) {
            for ($i=0; $i<$doiTerm->length; $i++) {
                $href = $doiDefinitionURL->item($i)->attributes
                    ->getNamedItem("href")->textContent;
                $retval[] = [
                    'title' => $doiTerm->item($i)->textContent
                        . $this->_removeDoubleAngleQuotationMark(
                            $doiDefinition->item($i)->textContent
                        ),
                    'href' => $href,
                    'service_type' => 'getFullTxt',
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
            for ($i=0; $i<$infoTokenNodes->length; $i++) {
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
            for ($i=0; $i<$ezbResultsNodesText->length; $i++) {

                $itemInfo = '';

                $expression = "count(//div[@class='t_ezb_result']/p[{$i}]/sup)";
                if ($xpath->evaluate("count({$expression})") == 1) {
                    $itemInfo = $this->parseRediInfo(
                        $xml, $xpath->query($expression)->item(0)->textContent
                    );
                }

                $retval[] = [
                    'title' => $this->_removeDoubleAngleQuotationMark(
                        $ezbResultsNodesText->item($i)->textContent
                    ),
                    'href' => $ezbResultsNodesURL->item($i)
                        ->attributes->getNamedItem("href")->textContent,
                    'coverage'     => $itemInfo,
                    'service_type' => 'getFullTxt',
                ];
            }
        }

        return $retval;
    }

    /**
     * Private helper function to remove hardcoded link-string "»" in Redi response
     *
     * @param string $string String to search for "»" and substitute it by ""
     *
     * @return string
     */
    private function _removeDoubleAngleQuotationMark($string)
    {
        return trim(
            str_replace(
                ['»',
                    chr(194).chr(160)
                ],
                ['', ''],
                $string
            )
        ); // hack to replace \u00a0
    }
}
