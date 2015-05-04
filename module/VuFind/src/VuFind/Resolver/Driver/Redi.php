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
 * @author   Gregor Gawol <gawol@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:link_resolver_drivers Wiki
 */
namespace VuFind\Resolver\Driver;
use DOMDocument;

/**
 * ReDi Link Resolver Driver
 *
 * @category VuFind2
 * @package  Resolver_Drivers
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
        $retval = [];
        $xml = new DOMDocument();
        if (!@$xml->loadHTML($xmlstr)) {
            return $retval;
        }

        $retval = $this->parseDOI($xml, $retval);
        $retval = $this->parseRediInfo($xml, $retval);
        $retval = $this->parseRediOpenURLs($xml, $retval);

        return $retval;
    }

    /**
     * Parse if the Redi xml snippet contains a DOI.
     *
     * @param DOMDocument $xml    Loaded xml document
     * @param array       $retval Get back a array with title, URL and service_type
     *
     * @return array Get back a array with title, URL and service_type
     */
    protected function parseDOI($xml, $retval)
    {
        $citation = $xml->getElementById('citation');
        if (is_object($citation->childNodes)) {
            foreach ($citation->childNodes as $deflist) {
                if (is_object($deflist->childNodes)) {
                    foreach ($deflist->childNodes as $defterm) {
                        $tmp = [];
                        if ($defterm->hasAttributes()) {
                            $elem = $defterm->getAttribute('class');
                            if ($elem == 'doi_t') {
                                $doiText = trim($defterm->nodeValue);
                                $tmp['title'] = $doiText;
                            }
                            if ($elem == 'doi_d') {
                                $doiURL = trim($this->getRediLink($defterm));
                                $tmp['href'] = $doiURL;
                            }
                        }
                        $tmp['service_type'] = 'getDOI';

                        if (!empty($tmp['title']) && !empty($tmp['href'])) {
                            $retval[] = $tmp;
                        }
                    }
                }
            }
        }
        return $retval;
    }

    /**
     * Parse if the Redi xml snippet contains information about the Redi offer.
     *
     * @param DOMDocument $xml    Loaded xml document
     * @param array       $retval Return array with Redi catalogue information
     *                            consisting of Text & Link.
     *
     * @return array Return array with Redi catalogue information consisting of
     *               Text & Link.
     */
    protected function parseRediInfo($xml, $retval)
    {
        if ($ezb = $xml->getElementById('t_ezb')) {
            if (is_object($ezb->childNodes)) {
                foreach ($ezb->childNodes as $divClassT) {
                    if (is_object($divClassT->childNodes)) {
                        foreach ($divClassT->childNodes as $nodes) {
                            // infotext

                            if ($nodes->nodeName == 'p') {
                                $tmp = [];
                                $rediInfoText = trim($nodes->firstChild->nodeValue);
                                if ($this->isRediOpenURLsWithInfo($xml)) {
                                    $rediInfoInfo = trim($nodes->nodeValue);
                                }
                                $rediInfoURL = trim($this->getRediLink($nodes));
                                if (is_object($nodes->childNodes)) {
                                    foreach ($nodes->childNodes as $bold) {
                                        if ($bold->nodeName == 'b') {
                                            $rediInfoText = $rediInfoText
                                                .' '.trim($bold->nodeValue);
                                        }
                                    }
                                }
                                $tmp['title'] = (isset($rediInfoText)?
                                    $rediInfoText:'');
                                $tmp['href'] = (isset($rediInfoURL)?
                                    $rediInfoURL:'');
                                $tmp['info'] = (isset($rediInfoInfo)?
                                    $rediInfoInfo:'');
                                $tmp['service_type'] = 'getHolding';

                                if (!empty($tmp['title']) && !empty($tmp['href'])) {
                                    $retval[] = $tmp;
                                }
                            }
                        }
                    }
                }
            }
        }
        return $retval;
    }

    /**
     * Parse if the Redi xml snippet contains Redi urls.
     *
     * @param DOMDocument $xml    Loaded xml document
     * @param array       $retval Get back Redi direct link to sources
     *                            containing title, URL and service_type
     *
     * @return array Get back Redi direct link to sources containing title, URL and
     *               service_type
     */
    protected function parseRediOpenURLs($xml, $retval)
    {
        if ($ezb = $xml->getElementById('t_ezb')) {
            if (is_object($ezb->childNodes)) {
                foreach ($ezb->childNodes as $divClassT) {
                    if (is_object($divClassT->childNodes)) {
                        foreach ($divClassT->childNodes as $nodes) {
                            $tmp = [];
                            // fulltext
                            if ($nodes->nodeName == 'div') {
                                $text = trim(
                                    str_replace(
                                        ['»',
                                            chr(194).chr(160)
                                        ],
                                        ['', ''],
                                        $nodes->nodeValue
                                    )
                                ); // hack to replace \u00a0
                                $available = $nodes->getElementsByTagName('span');
                                foreach ($available as $span) {
                                    if ($span->hasAttributes()) {
                                        $class = $span->getAttribute('class');
                                        if ($class == 't_link') {
                                            $url = $this->getRediLink($nodes);
                                        }
                                    }
                                }
                                $tmp['title'] = (isset($text)?$text:'');
                                $tmp['href'] = (isset($url)?$url:'');
                                $tmp['service_type'] = 'getFullTxt';

                                if (!empty($tmp['title']) && !empty($tmp['href'])) {
                                    $retval[] = $tmp;
                                }
                            }
                        } // end foreach
                    } // end if
                } // end foreach
            } // end if
        } // end if
        return $retval;
    }

    /**
     * Is a star in ReDi links text snippet
     *
     * @param DOMDocument $xml loaded xml document
     *
     * @return bool
     * @access protected
     */
    protected function isRediOpenURLsWithInfo($xml)
    {
        if ($ezb = $xml->getElementById('t_ezb')) {
            if (is_object($ezb->childNodes)) {
                foreach ($ezb->childNodes as $divClassT) {
                    if (is_object($divClassT->childNodes)) {
                        foreach ($divClassT->childNodes as $nodes) {
                            if ($nodes->nodeName == 'div') {
                                $text = trim(
                                    str_replace(
                                        ['»',
                                            chr(194).chr(160)
                                        ],
                                        ['', ''],
                                        $nodes->nodeValue
                                    )
                                ); // hack to replace \u00a0
                                if (preg_match('/.*(\*).*/', $text)) {
                                    return true;
                                }
                            }
                        } // end foreach
                    } // end if
                } // end foreach
            } // end if
        } // end if
        return false;
    }

    /**
     * Get the ReDi links of a DOM document snippet.
     *
     * @param object $doc Document snippet from ReDi site
     *
     * @return string Url of ReDi
     * @access protected
     */
    protected function getRediLink($doc)
    {
        $hrefs = $doc->getElementsByTagName('a');
        foreach ($hrefs as $a) {
            if ($a->hasAttributes()) {
                return $a->getAttribute('href');
            }
        }
        return '';
    }
}