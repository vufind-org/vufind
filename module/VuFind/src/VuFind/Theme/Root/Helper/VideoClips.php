<?php
/**
 * Video clip view helper
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
use DOMDocument;

/**
 * Video clip view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
class VideoClips extends AbstractSyndetics
{
    /**
     * Do the actual work of loading the clips.
     *
     * @param string $isbn ISBN of book to find clips for
     *
     * @return array
     */
    public function __invoke($isbn)
    {
        if (!$this->setIsbn($isbn)) {
            return array();
        }

        $results = array();

        // Fetch from provider
        if (isset($this->config->Content->videoClips)) {
            $providers = explode(',', $this->config->Content->videoClips);
            foreach ($providers as $provider) {
                $parts = explode(':', trim($provider));
                $provider = strtolower($parts[0]);
                $func = 'load' . ucwords($provider);
                $key = $parts[1];
                try {
                    $results[$provider] = method_exists($this, $func) ?
                        $this->$func($key) : false;
                    // If the current provider had no valid clips, store nothing:
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
     * This method is responsible for connecting to Syndetics and abstracting
     * clips.
     *
     * It first queries the master url for the ISBN entry seeking a clip URL.
     * If a clip URL is found, the script will then use HTTP request to
     * retrieve the script. The script will then parse the clip according to
     * US MARC (I believe). It will provide a link to the URL master HTML page
     * for more information.
     * Configuration:  Sources are processed in order - refer to $sourceList.
     *
     * @param string $id     Client access key
     * @param bool   $s_plus Are we operating in Syndetics Plus mode?
     *
     * @throws \Exception
     * @return array     Returns array with video clip data.
     * @author Joel Timothy Norman <joel.t.norman@wmich.edu>
     * @author Andrew Nagy <vufind-tech@lists.sourceforge.net>
     */
    protected function loadSyndetics($id, $s_plus=false)
    {
        $sourceList = array(
            'VIDEOCLIP' => array(
                'title' => 'Video Clips',
                'file' => 'VIDEOCLIP.XML',
                'div' => '<div id="syn_video_clip"></div>'
            )
        );

        //first request url
        $baseUrl = isset($this->config->Syndetics->url) ?
            $this->config->Syndetics->url : 'http://syndetics.com';
        $url = $baseUrl . '/index.aspx?isbn=' . $this->getIsbn10() .
               '/index.xml&client=' . $id . '&type=rw12,hw7';

        $vclips = array();

        //find out if there are any clips
        $result = $this->getHttpClient($url)->send();
        if (!$result->isSuccess()) {
            return $vclips;
        }

        // Test XML Response
        if (!($xmldoc = DOMDocument::loadXML($result->getBody()))) {
            throw new \Exception('Invalid XML');
        }

        $i = 0;
        foreach ($sourceList as $source => $sourceInfo) {
            $nodes = $xmldoc->getElementsByTagName($source);
            if ($nodes->length) {
                // Load clips
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
                    $vclips[$i]['Content'] = $sourceInfo['div'];
                } else {
                    // Get the field for video clips (VideoLink)
                    $nodes = $xmldoc2->GetElementsbyTagName("VideoLink");
                    if (!$nodes->length) {
                        // Skip clips with missing text
                        continue;
                    }
                    // stick the link into an embed tag.
                    $vclips[$i]['Content']
                        = '<embed width="400" height="300" type="' .
                        'application/x-shockwave-flash"' .
                        'allowfullscreen="true" src="' .
                        html_entity_decode($nodes->item(0)->nodeValue) .
                        '">';

                    // Get the marc field for copyright (997)
                    $nodes = $xmldoc->GetElementsbyTagName("Fld997");
                    if ($nodes->length) {
                        $vclips[$i]['Copyright'] = html_entity_decode(
                            $xmldoc2->saveXML($nodes->item(0))
                        );
                    } else {
                        $vclips[$i]['Copyright'] = null;
                    }
                }

                // change the xml to actual title:
                $vclips[$i]['Source'] = $sourceInfo['title'];

                $vclips[$i]['ISBN'] = $this->getIsbn10(); //show more link
                $vclips[$i]['username'] = $id;

                $i++;
            }
        }

        return $vclips;
    }
}