<?php
/**
 * Excerpt view helper
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
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\View\Helper\Root;
use DOMDocument;

/**
 * Excerpt view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class Excerpt extends AbstractSyndetics
{
    /**
     * Do the actual work of loading the excerpts.
     *
     * @param string $isbn ISBN of book to find excerpts for
     *
     * @return array
     */
    public function __invoke($isbn)
    {
        $providers = isset($this->config->Content->excerpts)
            ? $this->config->Content->excerpts : '';
        return $this->getResults($isbn, $providers);
    }

    /**
     * This method is responsible for connecting to Syndetics and abstracting
     * excerpts.
     *
     * It first queries the master url for the ISBN entry seeking an excerpt URL.
     * If an excerpt URL is found, the script will then use HTTP request to
     * retrieve the script. The script will then parse the excerpt according to
     * US MARC (I believe). It will provide a link to the URL master HTML page
     * for more information.
     * Configuration:  Sources are processed in order - refer to $sourceList.
     *
     * @param string $id     Client access key
     * @param bool   $s_plus Are we operating in Syndetics Plus mode?
     *
     * @throws \Exception
     * @return array     Returns array with excerpt data.
     * @author Joel Timothy Norman <joel.t.norman@wmich.edu>
     * @author Andrew Nagy <vufind-tech@lists.sourceforge.net>
     */
    protected function loadSyndetics($id, $s_plus=false)
    {
        //list of syndetic excerpts
        $sourceList = array(
            'DBCHAPTER' => array(
                'title' => 'First Chapter or Excerpt',
                'file' => 'DBCHAPTER.XML',
                'div' => '<div id="syn_dbchapter"></div>'
            )
        );

        //first request url
        $baseUrl = isset($this->config->Syndetics->url) ?
            $this->config->Syndetics->url : 'http://syndetics.com';
        $url = $baseUrl . '/index.aspx?isbn=' . $this->getIsbn10() .
               '/index.xml&client=' . $id . '&type=rw12,hw7';

        $review = array();

        //find out if there are any excerpts
        $result = $this->getHttpClient($url)->send();
        if (!$result->isSuccess()) {
            return $review;
        }

        // Test XML Response
        if (!($xmldoc = DOMDocument::loadXML($result->getBody()))) {
            throw new \Exception('Invalid XML');
        }

        $i = 0;
        foreach ($sourceList as $source => $sourceInfo) {
            $nodes = $xmldoc->getElementsByTagName($source);
            if ($nodes->length) {
                // Load excerpts
                $url = $baseUrl . '/index.aspx?isbn=' . $this->getIsbn10() . '/' .
                       $sourceInfo['file'] . '&client=' . $id . '&type=rw12,hw7';

                $result2 = $this->getHttpClient($url)->send();
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
                    $review[$i]['Content'] = $sourceInfo['div'];
                } else {
                    // Get the marc field for excerpts (520)
                    $nodes = $xmldoc2->GetElementsbyTagName("Fld520");
                    if (!$nodes->length) {
                        // Skip excerpts with missing text
                        continue;
                    }
                    $review[$i]['Content']
                        = html_entity_decode($xmldoc2->saveXML($nodes->item(0)));

                    // Get the marc field for copyright (997)
                    $nodes = $xmldoc->GetElementsbyTagName("Fld997");
                    if ($nodes->length) {
                        $review[$i]['Copyright'] = html_entity_decode(
                            $xmldoc2->saveXML($nodes->item(0))
                        );
                    } else {
                        $review[$i]['Copyright'] = null;
                    }

                    if ($review[$i]['Copyright']) {  //stop duplicate copyrights
                        $location = strripos(
                            $review[0]['Content'], $review[0]['Copyright']
                        );
                        if ($location > 0) {
                            $review[$i]['Content']
                                = substr($review[0]['Content'], 0, $location);
                        }
                    }
                }

                // change the xml to actual title:
                $review[$i]['Source'] = $sourceInfo['title'];

                $review[$i]['ISBN'] = $this->getIsbn10(); //show more link
                $review[$i]['username'] = $id;

                $i++;
            }
        }

        return $review;
    }
}