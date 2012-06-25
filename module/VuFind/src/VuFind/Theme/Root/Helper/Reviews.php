<?php
/**
 * Reviews view helper
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

/**
 * Reviews view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
class VuFind_Theme_Root_Helper_Reviews extends Zend_View_Helper_Abstract
{
    protected $config;
    protected $isbn;

    /**
     * Do the actual work of loading the reviews.
     *
     * @param string $isbn ISBN of book to find reviews for
     *
     * @return array
     */
    public function reviews($isbn)
    {
        // We can't proceed without an ISBN:
        if (empty($isbn)) {
            return array();
        }

        $this->config = VF_Config_Reader::getConfig();
        $this->isbn = new VF_Code_ISBN($isbn);
        $results = array();

        // Fetch from provider
        if (isset($this->config->Content->reviews)) {
            $providers = explode(',', $this->config->Content->reviews);
            foreach ($providers as $provider) {
                $parts = explode(':', trim($provider));
                $provider = strtolower($parts[0]);
                $func = 'load' . ucwords($provider);
                $key = $parts[1];
                try {
                    $results[$provider] = method_exists($this, $func) ?
                        $this->$func($key) : false;
                    // If the current provider had no valid excerpts, store nothing:
                    if (empty($results[$provider])) {
                        unset($results[$provider]);
                    }
                } catch (Exception $e) {
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
     * Amazon Reviews
     *
     * This method is responsible for connecting to Amazon AWS and abstracting
     * customer reviews for the specific ISBN
     *
     * @param string $id Amazon access key
     *
     * @throws Exception
     * @return array     Returns array with review data.
     * @author Andrew Nagy <vufind-tech@lists.sourceforge.net>
     */
    protected function loadAmazon($id)
    {
        // TODO: rewrite this to use Zend_Service_Amazon once the library is updated
        // to support later Amazon API versions (since this relies on 2010-10-10, we
        // can't currently use the library).

        // Collect all the parameters:
        $endpoint = 'webservices.amazon.com';
        $requestURI = '/onca/xml';
        $params = array(
            'AWSAccessKeyId' => $id,
            'ItemId' => $this->getIsbn10(),
            'Service' => 'AWSECommerceService',
            'Operation' => 'ItemLookup',
            'ResponseGroup' => 'Reviews',
            'Version' => '2010-10-10',
            'Timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'AssociateTag' => isset($this->config->Content->amazonassociate)
                ? $this->config->Content->amazonassociate : null
        );

        // Alphabetize the parameters:
        ksort($params);

        // URL encode and assemble the parameters:
        $encodedParams = array();
        foreach ($params as $key => $value) {
            $encodedParams[] = rawurlencode($key) . '=' . rawurlencode($value);
        }
        $encodedParams = implode('&', $encodedParams);

        // Build the HMAC signature:
        $sigData = "GET\n{$endpoint}\n{$requestURI}\n{$encodedParams}";
        $key = $this->config->Content->amazonsecret;
        $hmacHash = hash_hmac('sha256', $sigData, $key, 1);

        // Save the final request URL:
        $url = 'http://' . $endpoint . $requestURI . '?' . $encodedParams
            . '&Signature=' . rawurlencode(base64_encode($hmacHash));

        $client = new VF_Http_Client();
        $client->setUri($url);
        $result = $client->request('GET');

        $data = $result->isError()
            ? false : @simplexml_load_string($result->getBody());
        if (!$data) {
            return array();
        }

        $result = array();
        $reviews = isset($data->Items->Item->CustomerReviews->Review)
            ? $data->Items->Item->CustomerReviews->Review : null;
        if (!empty($reviews)) {
            $i = 0;
            foreach ($reviews as $review) {
                $result[$i]['Rating'] = (string)$review->Rating;
                $result[$i]['Summary'] = (string)$review->Summary;
                $result[$i]['Content'] = (string)$review->Content;
                $i++;
            }
        }

        // If we weren't able to extract any individual reviews, we'll have
        // to resort to displaying results in an iframe.
        if (empty($result)) {
            $iframe = isset($data->Items->Item->CustomerReviews->IFrameURL)
                ? (string)$data->Items->Item->CustomerReviews->IFrameURL
                : null;
            if (!empty($iframe)) {
                // CSS for iframe (explicit dimensions needed for IE
                // compatibility -- using 100% has bad results there):
                $css = "width: 700px; height: 500px;";
                $result[] = array(
                    'Rating' => '',
                    'Summary' => '',
                    'Content' =>
                        "<iframe style=\"{$css}\" src=\"{$iframe}\" />"
                );
            }
        }

        return $result;
    }

    /**
     * Amazon Editorial
     *
     * This method is responsible for connecting to Amazon AWS and abstracting
     * editorial reviews for the specific ISBN
     *
     * @param string $id Amazon access key
     *
     * @return array     Returns array with review data, otherwise a PEAR_Error.
     * @author Andrew Nagy <vufind-tech@lists.sourceforge.net>
     */
    protected function loadAmazoneditorial($id)
    {
        try {
            $amazon = new Zend_Service_Amazon(
                $id, 'US', $this->config->Content->amazonsecret
            );
            $params = array(
                'ResponseGroup' => 'EditorialReview',
                'AssociateTag' => isset($this->config->Content->amazonassociate)
                    ? $this->config->Content->amazonassociate : null
            );
            $data = $amazon->itemLookup($this->getIsbn10(), $params);
        } catch (Exception $e) {
            // Something went wrong?  Just return empty list.
            return array();
        }

        if ($data) {
            $i = 0;
            $result = array();
            $reviews = isset($data->EditorialReviews)
                ? $data->EditorialReviews : null;
            if (!empty($reviews)) {
                foreach ($reviews as $review) {
                    // Filter out product description
                    if ((string)$review->Source != 'Product Description') {
                        foreach ($review as $key => $value) {
                            $result[$i][$key] = (string)$value;
                        }
                        $i++;
                    }
                }
            }
            return $result;
        }
    }

    /**
     * syndetics
     *
     * This method is responsible for connecting to Syndetics and abstracting
     * reviews from multiple providers.
     *
     * It first queries the master url for the ISBN entry seeking a review URL.
     * If a review URL is found, the script will then use HTTP request to
     * retrieve the script. The script will then parse the review according to
     * US MARC (I believe). It will provide a link to the URL master HTML page
     * for more information.
     * Configuration:  Sources are processed in order - refer to $sourceList.
     * If your library prefers one reviewer over another change the order.
     * If your library does not like a reviewer, remove it.  If there are more
     * syndetics reviewers add another entry.
     *
     * @param string $id     Client access key
     * @param bool   $s_plus Are we operating in Syndetics Plus mode?
     *
     * @throws Exception
     * @return array     Returns array with review data.
     * @author Joel Timothy Norman <joel.t.norman@wmich.edu>
     * @author Andrew Nagy <vufind-tech@lists.sourceforge.net>
     */
    protected function loadSyndetics($id, $s_plus=false)
    {
        //list of syndetic reviews
        $sourceList = array(
            'CHREVIEW' => array('title' => 'Choice Review',
                                'file' => 'CHREVIEW.XML',
                                'div' => '<div id="syn_chreview"></div>'),
            'NYREVIEW' => array('title' => 'New York Times Review',
                                'file' => 'NYREVIEW.XML',
                                'div' => '<div id="syn_nyreview"></div>'),
            'BLREVIEW' => array('title' => 'Booklist Review',
                                'file' => 'BLREVIEW.XML',
                                'div' => '<div id="syn_blreview"></div>'),
            'PWREVIEW' => array('title' => "Publisher's Weekly Review",
                                'file' => 'PWREVIEW.XML',
                                'div' => '<div id="syn_pwreview"></div>'),
            'LJREVIEW' => array('title' => 'Library Journal Review',
                                'file' => 'LJREVIEW.XML',
                                'div' => '<div id="syn_ljreview"></div>'),
            'SLJREVIEW' => array('title' => 'School Library Journal Review',
                                'file' => 'SLJREVIEW.XML',
                                'div' => '<div id="syn_sljreview"></div>'),
            'HBREVIEW' => array('title' => 'Horn Book Review',
                                'file' => 'HBREVIEW.XML',
                                'div' => '<div id="syn_hbreview"></div>'),
            'KIRKREVIEW' => array('title' => 'Kirkus Book Review',
                                'file' => 'KIRKREVIEW.XML',
                                'div' => '<div id="syn_kireview"></div>'),
            'CRITICASREVIEW' => array('title' => 'Criticas Review',
                                'file' => 'CRITICASREVIEW.XML',
                                'div' => '<div id="syn_criticasreview"></div>'),
            // These last two entries are probably typos -- retained for legacy
            // compatibility just in case they're actually used for something!
            'KIREVIEW' => array('title' => 'Kirkus Book Review',
                                'file' => 'KIREVIEW.XML'),
            'CRITICASEREVIEW' => array('title' => 'Criti Case Review',
                                'file' => 'CRITICASEREVIEW.XML')
        );

        //first request url
        $baseUrl = isset($this->config->Syndetics->url) ?
            $this->config->Syndetics->url : 'http://syndetics.com';
        $url = $baseUrl . '/index.aspx?isbn=' . $this->getIsbn10() . '/' .
               'index.xml&client=' . $id . '&type=rw12,hw7';

        $review = array();

        //find out if there are any reviews
        $client = new VF_Http_Client();
        $client->setUri($url);
        $result = $client->request('GET');
        if ($result->isError()) {
            return $review;
        }

        // Test XML Response
        if (!($xmldoc = @DOMDocument::loadXML($result->getBody()))) {
            throw new Exception('Invalid XML');
        }

        $i = 0;
        foreach ($sourceList as $source => $sourceInfo) {
            $nodes = $xmldoc->getElementsByTagName($source);
            if ($nodes->length) {
                // Load reviews
                $url = $baseUrl . '/index.aspx?isbn=' . $this->getIsbn10() . '/' .
                       $sourceInfo['file'] . '&client=' . $id . '&type=rw12,hw7';
                $client->setUri($url);
                $result2 = $client->request('GET');
                if ($result2->isError()) {
                    continue;
                }

                // Test XML Response
                $xmldoc2 = @DOMDocument::loadXML($result2->getBody());
                if (!$xmldoc2) {
                    throw new Exception('Invalid XML');
                }

                // If we have syndetics plus, we don't actually want the content
                // we'll just stick in the relevant div
                if ($s_plus) {
                    $review[$i]['Content'] = $sourceInfo['div'];
                } else {

                    // Get the marc field for reviews (520)
                    $nodes = $xmldoc2->GetElementsbyTagName("Fld520");
                    if (!$nodes->length) {
                        // Skip reviews with missing text
                        continue;
                    }
                    // Decode the content and strip unwanted <a> tags:
                    $review[$i]['Content'] = preg_replace(
                        '/<a>|<a [^>]*>|<\/a>/', '',
                        html_entity_decode($xmldoc2->saveXML($nodes->item(0)))
                    );

                    // Get the marc field for copyright (997)
                    $nodes = $xmldoc2->GetElementsbyTagName("Fld997");
                    if ($nodes->length) {
                        $review[$i]['Copyright']
                            = html_entity_decode($xmldoc2->saveXML($nodes->item(0)));
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

                //change the xml to actual title:
                $review[$i]['Source'] = $sourceInfo['title'];

                $review[$i]['ISBN'] = $this->getIsbn10(); //show more link

                $i++;
            }
        }

        return $review;
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

    /**
     * Guardian Reviews
     *
     * This method is responsible for connecting to the Guardian and abstracting
     * reviews for the specific ISBN.
     *
     * @param string $id Guardian API key
     *
     * @throws Exception
     * @return array     Returns array with review data
     * @author Eoghan Ó Carragáin <eoghan.ocarragain@gmail.com>
     */
    protected function loadGuardian($id)
    {
        //first request url
        $url
            = "http://content.guardianapis.com/search?order-by=newest&format=json" .
                "&show-fields=all&reference=isbn%2F" . $this->isbn->get13();

        // Only add api-key if one has been provided in config.ini. If no key is
        // provided, a link to the Guardian can still be shown.
        if (strlen($id) > 0) {
            $url = $url . "&api-key=" . $id;
        }

        //find out if there are any reviews
        $client = new VF_Http_Client();
        $client->setUri($url);
        $result = $client->request('GET');

        // Was the request successful?
        if (!$result->isError()) {
            // parse json from response
            $data = json_decode($result->getBody(), true);
            if ($data) {
                $result = array();
                $i = 0;
                foreach ($data['response']['results'] as $review) {
                    $result[$i]['Date'] = $review['webPublicationDate'];
                    $result[$i]['Summary'] = $review['fields']['headline'] . ". " .
                        preg_replace(
                            '/<p>|<p [^>]*>|<\/p>/', '',
                            html_entity_decode($review['fields']['trailText'])
                        );
                    $result[$i]['ReviewURL'] = $review['fields']['shortUrl'];

                    // TODO: Make this configurable (or store it locally), so users
                    //       running VuFind behind SSL don't get warnings due to
                    //       inclusion of this non-SSL image URL:
                    $poweredImage
                        = 'http://image.guardian.co.uk/sys-images/Guardian/' .
                        'Pix/pictures/2010/03/01/poweredbyguardianBLACK.png';

                    $result[$i]['Copyright'] = "<a href=\"" .
                        $review['fields']['shortUrl'] . "\" target=\"new\">" .
                        "<img src=\"{$poweredImage}\" " .
                        "alt=\"Powered by the Guardian\" /></a>";

                    $result[$i]['Source'] = $review['fields']['byline'];
                    // Only return Content if the body tag contains a usable review
                    $redist = "Redistribution rights for this field are unavailable";
                    if ((strlen($review['fields']['body']) > 0)
                        && (!strstr($review['fields']['body'], $redist))
                    ) {
                        $result[$i]['Content'] = $review['fields']['body'];
                    }
                    $i++;
                }
                return $result;
            } else {
                throw new Exception('Could not parse Guardian response.');
            }
        } else {
            return array();
        }
    }
}