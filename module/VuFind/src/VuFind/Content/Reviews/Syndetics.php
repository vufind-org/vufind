<?php

/**
 * Syndetics review content loader.
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

namespace VuFind\Content\Reviews;

/**
 * Syndetics review content loader.
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
     * List of syndetic review sources
     *
     * @var array
     */
    protected $sourceList = [
        'CHREVIEW' => ['title' => 'Choice Review',
                            'file' => 'CHREVIEW.XML',
                            'div' => '<div id="syn_chreview"></div>'],
        'NYREVIEW' => ['title' => 'New York Times Review',
                            'file' => 'NYREVIEW.XML',
                            'div' => '<div id="syn_nyreview"></div>'],
        'BLREVIEW' => ['title' => 'Booklist Review',
                            'file' => 'BLREVIEW.XML',
                            'div' => '<div id="syn_blreview"></div>'],
        'PWREVIEW' => ['title' => "Publisher's Weekly Review",
                            'file' => 'PWREVIEW.XML',
                            'div' => '<div id="syn_pwreview"></div>'],
        'LJREVIEW' => ['title' => 'Library Journal Review',
                            'file' => 'LJREVIEW.XML',
                            'div' => '<div id="syn_ljreview"></div>'],
        'SLJREVIEW' => ['title' => 'School Library Journal Review',
                            'file' => 'SLJREVIEW.XML',
                            'div' => '<div id="syn_sljreview"></div>'],
        'HBREVIEW' => ['title' => 'Horn Book Review',
                            'file' => 'HBREVIEW.XML',
                            'div' => '<div id="syn_hbreview"></div>'],
        'KIRKREVIEW' => ['title' => 'Kirkus Book Review',
                            'file' => 'KIRKREVIEW.XML',
                            'div' => '<div id="syn_kireview"></div>'],
        'CRITICASREVIEW' => ['title' => 'Criticas Review',
                            'file' => 'CRITICASREVIEW.XML',
                            'div' => '<div id="syn_criticasreview"></div>'],
        // These last two entries are probably typos -- retained for legacy
        // compatibility just in case they're actually used for something!
        'KIREVIEW' => ['title' => 'Kirkus Book Review',
                            'file' => 'KIREVIEW.XML'],
        'CRITICASEREVIEW' => ['title' => 'Criti Case Review',
                            'file' => 'CRITICASEREVIEW.XML'],
    ];

    /**
     * This method is responsible for connecting to Syndetics and abstracting
     * reviews from multiple providers.
     *
     * It first queries the master url for the ISBN entry seeking a review URL.
     * If a review URL is found, the script will then use HTTP request to
     * retrieve the script. The script will then parse the review according to
     * US MARC (I believe). It will provide a link to the URL master HTML page
     * for more information.
     * Configuration:  Sources are processed in order - refer to $sourceList above.
     * If your library prefers one reviewer over another change the order.
     * If your library does not like a reviewer, remove it. If there are more
     * syndetics reviewers add another entry.
     *
     * @param string           $key     API key (unused here)
     * @param \VuFindCode\ISBN $isbnObj ISBN object
     *
     * @throws \Exception
     * @return array     Returns array with review data.
     * @author Joel Timothy Norman <joel.t.norman@wmich.edu>
     * @author Andrew Nagy <vufind-tech@lists.sourceforge.net>
     */
    public function loadByIsbn($key, \VuFindCode\ISBN $isbnObj)
    {
        // Initialize return value
        $review = [];

        // Find out if there are any reviews
        $isbn = $this->getIsbn10($isbnObj);
        $url = $this->getIsbnUrl($isbn, $key);
        $result = $this->getHttpClient($url)->send();
        if (!$result->isSuccess()) {
            return $review;
        }

        // Test XML Response
        if (!($xmldoc = $this->xmlToDOMDocument($result->getBody()))) {
            throw new \Exception('Invalid XML');
        }

        $i = 0;
        foreach ($this->sourceList as $source => $sourceInfo) {
            $nodes = $xmldoc->getElementsByTagName($source);
            if ($nodes->length) {
                // Load reviews
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
                    $review[$i]['Content'] = $sourceInfo['div'];
                } else {
                    // Get the marc field for reviews (520)
                    $nodes = $xmldoc2->GetElementsbyTagName('Fld520');
                    if (!$nodes->length) {
                        // Skip reviews with missing text
                        continue;
                    }
                    // Decode the content and strip unwanted <a> tags:
                    $review[$i]['Content'] = preg_replace(
                        '/<a>|<a [^>]*>|<\/a>/',
                        '',
                        html_entity_decode($xmldoc2->saveXML($nodes->item(0)))
                    );

                    // Get the marc field for copyright (997)
                    $nodes = $xmldoc2->GetElementsbyTagName('Fld997');
                    if ($nodes->length) {
                        $review[$i]['Copyright']
                            = html_entity_decode($xmldoc2->saveXML($nodes->item(0)));
                    } else {
                        $review[$i]['Copyright'] = null;
                    }

                    if ($review[$i]['Copyright']) {  //stop duplicate copyrights
                        $location = strripos(
                            $review[0]['Content'],
                            (string)$review[0]['Copyright']
                        );
                        if ($location > 0) {
                            $review[$i]['Content']
                                = substr($review[0]['Content'], 0, $location);
                        }
                    }
                }

                //change the xml to actual title:
                $review[$i]['Source'] = $sourceInfo['title'];

                $review[$i]['ISBN'] = $isbn;

                $i++;
            }
        }

        return $review;
    }
}
