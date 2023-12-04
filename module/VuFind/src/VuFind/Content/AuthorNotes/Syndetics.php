<?php

/**
 * Syndetics author notes content loader.
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

namespace VuFind\Content\AuthorNotes;

/**
 * Syndetics author notes content loader.
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
     * List of data sources for author notes.
     *
     * @var array
     */
    protected $sourceList = [
        'ANOTES' => [
            'title' => 'Author Notes',
            'file' => 'ANOTES.XML',
            'div' => '<div id="syn_anotes"></div>',
        ],
    ];

    /**
     * This method is responsible for connecting to Syndetics and abstracting
     * author notes.
     *
     * It first queries the master url for the ISBN entry seeking a note URL.
     * If a note URL is found, the script will then use HTTP request to
     * retrieve the script. The script will then parse the note according to
     * US MARC (I believe). It will provide a link to the URL master HTML page
     * for more information.
     * Configuration:  Sources are processed in order - refer to $sourceList above.
     *
     * @param string           $key     API key
     * @param \VuFindCode\ISBN $isbnObj ISBN object
     *
     * @throws \Exception
     * @return array     Returns array with author note data.
     * @author Joel Timothy Norman <joel.t.norman@wmich.edu>
     * @author Andrew Nagy <vufind-tech@lists.sourceforge.net>
     */
    public function loadByIsbn($key, \VuFindCode\ISBN $isbnObj)
    {
        // Initialize return value
        $anotes = [];

        // Find out if there are any notes
        $isbn = $this->getIsbn10($isbnObj);
        $url = $this->getIsbnUrl($isbn, $key);
        $result = $this->getHttpClient($url)->send();
        if (!$result->isSuccess()) {
            return $anotes;
        }

        // Test XML Response
        if (!($xmldoc = $this->xmlToDOMDocument($result->getBody()))) {
            throw new \Exception('Invalid XML');
        }

        $i = 0;
        foreach ($this->sourceList as $source => $sourceInfo) {
            $nodes = $xmldoc->getElementsByTagName($source);
            if ($nodes->length) {
                // Load notes
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
                    $anotes[$i]['Content'] = $sourceInfo['div'];
                } else {
                    // Get the marc field for author notes (980)
                    $nodes = $xmldoc2->GetElementsbyTagName('Fld980');
                    if (!$nodes->length) {
                        // Skip fields with missing text
                        continue;
                    }
                    // Decode the content and strip unwanted <a> tags:
                    $anotes[$i]['Content'] = preg_replace(
                        '/<a>|<a [^>]*>|<\/a>/',
                        '',
                        html_entity_decode($xmldoc2->saveXML($nodes->item(0)))
                    );
                }

                // change the xml to actual title:
                $anotes[$i]['Source'] = $sourceInfo['title'];

                $anotes[$i]['ISBN'] = $isbn;
                $anotes[$i]['username'] = $key;

                $i++;
            }
        }

        return $anotes;
    }
}
