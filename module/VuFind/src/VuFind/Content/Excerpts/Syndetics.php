<?php

/**
 * Syndetics excerpt content loader.
 *
 * PHP version 8
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Content
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Content\Excerpts;

/**
 * Syndetics excerpt content loader.
 *
 * @category VuFind
 * @package  Content
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Syndetics extends \VuFind\Content\AbstractSyndetics
{
    /**
     * List of syndetic excerpts
     *
     * @var array
     */
    protected $sourceList = [
        'DBCHAPTER' => [
            'title' => 'First Chapter or Excerpt',
            'file' => 'DBCHAPTER.XML',
            'div' => '<div id="syn_dbchapter"></div>',
        ],
    ];

    /**
     * This method is responsible for connecting to Syndetics and abstracting
     * excerpts.
     *
     * It first queries the master url for the ISBN entry seeking an excerpt URL.
     * If an excerpt URL is found, the script will then use HTTP request to
     * retrieve the script. The script will then parse the excerpt according to
     * US MARC (I believe). It will provide a link to the URL master HTML page
     * for more information.
     * Configuration:  Sources are processed in order - refer to $sourceList above.
     *
     * @param string           $key     API key
     * @param \VuFindCode\ISBN $isbnObj ISBN object
     *
     * @throws \Exception
     * @return array     Returns array with excerpt data.
     * @author Joel Timothy Norman <joel.t.norman@wmich.edu>
     * @author Andrew Nagy <vufind-tech@lists.sourceforge.net>
     */
    public function loadByIsbn($key, \VuFindCode\ISBN $isbnObj)
    {
        // Initialize return value:
        $excerpt = [];

        // Find out if there are any excerpts
        $isbn = $this->getIsbn10($isbnObj);
        $url = $this->getIsbnUrl($isbn, $key);
        $result = $this->getHttpClient($url)->send();
        if (!$result->isSuccess()) {
            return $excerpt;
        }

        // Test XML Response
        if (!($xmldoc = $this->xmlToDOMDocument($result->getBody()))) {
            throw new \Exception('Invalid XML');
        }

        $i = 0;
        foreach ($this->sourceList as $source => $sourceInfo) {
            $nodes = $xmldoc->getElementsByTagName($source);
            if ($nodes->length) {
                // Load excerpts
                $url = $this->getIsbnUrl($isbn, $key, $sourceInfo['file']);
                $result2 = $this->getHttpClient($url)->send();
                if (!$result2->isSuccess()) {
                    continue;
                }

                // Test XML Response
                $xmldoc2 = $this->xmlToDOMDocument($result2->getBody());
                if (!$xmldoc2) {
                    throw new \Exception('Invalid XML');
                }

                // If we have syndetics plus, we don't actually want the content
                // we'll just stick in the relevant div
                if ($this->usePlus) {
                    $excerpt[$i]['Content'] = $sourceInfo['div'];
                } else {
                    // Get the marc field for excerpts (520)
                    $nodes = $xmldoc2->GetElementsbyTagName('Fld520');
                    if (!$nodes->length) {
                        // Skip excerpts with missing text
                        continue;
                    }
                    $excerpt[$i]['Content']
                        = html_entity_decode($xmldoc2->saveXML($nodes->item(0)));

                    // Get the marc field for copyright (997)
                    $nodes = $xmldoc->GetElementsbyTagName('Fld997');
                    if ($nodes->length) {
                        $excerpt[$i]['Copyright'] = html_entity_decode(
                            $xmldoc2->saveXML($nodes->item(0))
                        );
                    } else {
                        $excerpt[$i]['Copyright'] = null;
                    }

                    if ($excerpt[$i]['Copyright']) {  //stop duplicate copyrights
                        $location = strripos(
                            $excerpt[0]['Content'],
                            (string)$excerpt[0]['Copyright']
                        );
                        if ($location > 0) {
                            $excerpt[$i]['Content']
                                = substr($excerpt[0]['Content'], 0, $location);
                        }
                    }
                }

                // change the xml to actual title:
                $excerpt[$i]['Source'] = $sourceInfo['title'];

                $excerpt[$i]['ISBN'] = $isbn;
                $excerpt[$i]['username'] = $key;

                $i++;
            }
        }

        return $excerpt;
    }
}
