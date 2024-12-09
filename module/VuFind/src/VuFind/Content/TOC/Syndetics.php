<?php

/**
 * Syndetics TOC content loader.
 *
 * PHP version 8
 *
 * Copyright (C) The University of Chicago 2017.
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
 * @author   John Jung <jej@uchicago.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Content\TOC;

use function sprintf;

/**
 * Syndetics TOC content loader.
 *
 * @category VuFind
 * @package  Content
 * @author   John Jung <jej@uchicago.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Syndetics extends \VuFind\Content\AbstractSyndetics
{
    /**
     * List of data sources for author notes.
     *
     * @var array
     */
    protected $sourceList = [
        'TOC' => [
            'title' => 'TOC',
            'file' => 'TOC.XML',
            'div' => '<div id="syn_toc"></div>',
        ],
    ];

    /**
     * This method is responsible for connecting to Syndetics for tables
     * of contents.
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
     * @return array     Returns array with table of contents data.
     * @author John Jung <jej@uchicago.edu>
     */
    public function loadByIsbn($key, \VuFindCode\ISBN $isbnObj)
    {
        // Initialize return value:
        $toc = [];

        // Find out if there are any tables of contents
        $isbn = $this->getIsbn10($isbnObj);
        $url = $this->getIsbnUrl($isbn, $key);
        $result = $this->getHttpClient($url)->send();
        if (!$result->isSuccess()) {
            return $toc;
        }

        // Test XML Response
        if (!($xmldoc = $this->xmlToDOMDocument($result->getBody()))) {
            throw new \Exception('Invalid XML');
        }

        foreach ($this->sourceList as $source => $sourceInfo) {
            $nodes = $xmldoc->getElementsByTagName($source);
            if ($nodes->length) {
                // Load toc
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
                    $toc = $sourceInfo['div'];
                } else {
                    // Get the marc field for toc (970)
                    $nodes = $xmldoc2->GetElementsbyTagName('Fld970');

                    foreach ($nodes as $node) {
                        $li = '';

                        // Chapter labels.
                        $nodeList = $node->getElementsByTagName('l');
                        if ($nodeList->length > 0) {
                            $li .= sprintf('%s. ', $nodeList->item(0)->nodeValue);
                        }

                        // Chapter title.
                        $nodeList = $node->getElementsByTagName('t');
                        if ($nodeList->length > 0) {
                            $li .= $nodeList->item(0)->nodeValue;
                        }

                        $toc[] = preg_replace(
                            '/<a>|<a [^>]*>|<\/a>/',
                            '',
                            html_entity_decode($li)
                        );
                    }
                }
            }
        }

        return $toc;
    }
}
