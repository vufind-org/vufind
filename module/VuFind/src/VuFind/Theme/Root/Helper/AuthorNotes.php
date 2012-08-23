<?php
/**
 * Author Notes view helper
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
namespace VuFind\Theme\Root\Helper;
use DOMDocument, VuFind\Config\Reader as ConfigReader, VuFind\Code\ISBN,
    VuFind\Http\Client as HttpClient, Zend\View\Helper\AbstractHelper;

/**
 * Author Notes view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
class AuthorNotes extends AbstractHelper
{
    protected $config;
    protected $isbn;

    /**
     * Do the actual work of loading the notes.
     *
     * @param string $isbn ISBN of book to find notes for
     *
     * @return array
     */
    public function __invoke($isbn)
    {
        // We can't proceed without an ISBN:
        if (empty($isbn)) {
            return array();
        }

        $this->config = ConfigReader::getConfig();
        $this->isbn = new ISBN($isbn);
        $results = array();

        // Fetch from provider
        if (isset($this->config->Content->authorNotes)) {
            $providers = explode(',', $this->config->Content->authorNotes);
            foreach ($providers as $provider) {
                $parts = explode(':', trim($provider));
                $provider = strtolower($parts[0]);
                $func = 'load' . ucwords($provider);
                $key = $parts[1];
                try {
                    $results[$provider] = method_exists($this, $func)
                        ? $this->$func($key) : false;
                    // If the current provider had no valid notes, store nothing:
                    if (empty($results[$provider])) {
                        unset($results[$provider]);
                    }
                } catch (\Exception $e) {
                    // Ignore exceptions:
                    unset($results[$provider]);
                }
            }
        }

        return $results;
    }

    /**
     * Attempt to get an ISBN-10; revert to ISBN-13 only when ISBN-10 representation
     * is impossible.
     *
     * @return string
     */
    protected function getIsbn10()
    {
        $isbn = $this->isbn->get10();
        if (!$isbn) {
            $isbn = $this->isbn->get13();
        }
        return $isbn;
    }

    /**
     * This method is responsible for connecting to Syndetics and abstracting
     * author notes.
     *
     * It first queries the master url for the ISBN entry seeking a note URL.
     * If a note URL is found, the script will then use HTTP request to
     * retrieve the script. The script will then parse the note according to
     * US MARC (I believe). It will provide a link to the URL master HTML page
     * for more information.
     * Configuration:  Sources are processed in order - refer to $sourceList.
     *
     * @param string $id     Client access key
     * @param bool   $s_plus Are we operating in Syndetics Plus mode?
     *
     * @throws Exception
     * @return array     Returns array with author note data.
     * @author Joel Timothy Norman <joel.t.norman@wmich.edu>
     * @author Andrew Nagy <vufind-tech@lists.sourceforge.net>
     */
    protected function loadSyndetics($id, $s_plus=false)
    {
        $sourceList = array(
            'ANOTES' => array(
                'title' => 'Author Notes',
                'file' => 'ANOTES.XML',
                'div' => '<div id="syn_anotes"></div>'
            )
        );

        //first request url
        $baseUrl = isset($this->config->Syndetics->url) ?
            $this->config->Syndetics->url : 'http://syndetics.com';
        $url = $baseUrl . '/index.aspx?isbn=' . $this->getIsbn10() .
               '/index.xml&client=' . $id . '&type=rw12,hw7';

        $anotes = array();

        //find out if there are any notes
        $client = new HttpClient();
        $client->setUri($url);
        $result = $client->setMethod('GET')->send();
        if (!$result->isSuccess()) {
            return $anotes;
        }

        // Test XML Response
        if (!($xmldoc = DOMDocument::loadXML($result->getBody()))) {
            throw new \Exception('Invalid XML');
        }

        $i = 0;
        foreach ($sourceList as $source => $sourceInfo) {
            $nodes = $xmldoc->getElementsByTagName($source);
            if ($nodes->length) {
                // Load notes
                $url = $baseUrl . '/index.aspx?isbn=' . $this->getIsbn10() . '/' .
                       $sourceInfo['file'] . '&client=' . $id . '&type=rw12,hw7';

                $client->setUri($url);
                $result2 = $client->send();
                if (!$result2->isSuccess()) {
                    continue;
                }

                // Test XML Response
                $xmldoc2 = DOMDocument::loadXML($result2->getBody());
                if (!$xmldoc2) {
                    throw new \Exception('Invalid XML');
                }

                // If we have syndetics plus, we don't actually want the content
                // we'll just stick in the relevant div
                if ($s_plus) {
                    $anotes[$i]['Content'] = $sourceInfo['div'];
                } else {
                    // Get the marc field for author notes (980)
                    $nodes = $xmldoc2->GetElementsbyTagName("Fld980");
                    if (!$nodes->length) {
                        // Skip fields with missing text
                        continue;
                    }
                    // Decode the content and strip unwanted <a> tags:
                    $anotes[$i]['Content'] = preg_replace(
                        '/<a>|<a [^>]*>|<\/a>/', '',
                        html_entity_decode($xmldoc2->saveXML($nodes->item(0)))
                    );

                    /*
                    // Get the marc field for copyright (997)
                    $nodes = $xmldoc->GetElementsbyTagName("Fld997");
                    if ($nodes->length) {
                        $anotes[$i]['Copyright'] = html_entity_decode(
                            $xmldoc2->saveXML($nodes->item(0))
                        );
                    } else {
                        $anotes[$i]['Copyright'] = null;
                    }

                    if ($anotes[$i]['Copyright']) {  //stop duplicate copyrights
                        $location = strripos(
                            $anotes[0]['Content'], $anotes[0]['Copyright']
                        );
                        if ($location > 0) {
                            $anotes[$i]['Content']
                                = substr($anotes[0]['Content'], 0, $location);
                        }
                    }
                     */
                }

                // change the xml to actual title:
                $anotes[$i]['Source'] = $sourceInfo['title'];

                $anotes[$i]['ISBN'] = $this->getIsbn10(); //show more link
                $anotes[$i]['username'] = $id;

                $i++;
            }
        }

        return $anotes;
    }

    /**
     * Wrapper around syndetics to provide Syndetics Plus functionality.
     *
     * @param string $id Client access key
     *
     * @throws Exception
     * @return array     Returns array with auth notes data.
     */
    protected function loadSyndeticsplus($id) 
    {
        return $this->loadSyndetics($id, true);
    }
}