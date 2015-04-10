<?php
/**
 * Amazon review content loader.
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
 * @package  Content
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\Content\Reviews;

/**
 * Amazon review content loader.
 *
 * @category VuFind2
 * @package  Content
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class Amazon extends \VuFind\Content\AbstractAmazon
{
    /**
     * Amazon Reviews
     *
     * This method is responsible for connecting to Amazon AWS and abstracting
     * customer reviews for the specific ISBN
     *
     * @param string           $key     API key
     * @param \VuFindCode\ISBN $isbnObj ISBN object
     *
     * @throws \Exception
     * @return array     Returns array with review data.
     * @author Andrew Nagy <vufind-tech@lists.sourceforge.net>
     */
    public function loadByIsbn($key, \VuFindCode\ISBN $isbnObj)
    {
        // TODO: rewrite this to use ZendService\Amazon.

        // Collect all the parameters:
        $endpoint = 'webservices.amazon.com';
        $requestURI = '/onca/xml';
        $isbn = $this->getIsbn10($isbnObj);
        $params = [
            'AWSAccessKeyId' => $key,
            'ItemId' => $isbn,
            'Service' => 'AWSECommerceService',
            'Operation' => 'ItemLookup',
            'ResponseGroup' => 'Reviews',
            'Version' => '2010-10-10',
            'Timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'AssociateTag' => $this->associate
        ];

        // Alphabetize the parameters:
        ksort($params);

        // URL encode and assemble the parameters:
        $encodedParams = [];
        foreach ($params as $key => $value) {
            $encodedParams[] = rawurlencode($key) . '=' . rawurlencode($value);
        }
        $encodedParams = implode('&', $encodedParams);

        // Build the HMAC signature:
        $sigData = "GET\n{$endpoint}\n{$requestURI}\n{$encodedParams}";
        $hmacHash = hash_hmac('sha256', $sigData, $this->secret, 1);

        // Save the final request URL:
        $url = 'http://' . $endpoint . $requestURI . '?' . $encodedParams
            . '&Signature=' . rawurlencode(base64_encode($hmacHash));

        $result = $this->getHttpClient($url)->send();

        $data = !$result->isSuccess()
            ? false : simplexml_load_string($result->getBody());
        if (!$data) {
            return [];
        }

        $result = [];
        $reviews = isset($data->Items->Item->CustomerReviews->Review)
            ? $data->Items->Item->CustomerReviews->Review : null;
        if (!empty($reviews)) {
            $i = 0;
            foreach ($reviews as $review) {
                $result[$i]['Rating'] = (string)$review->Rating;
                $result[$i]['Summary'] = (string)$review->Summary;
                $result[$i]['Content'] = (string)$review->Content;
                $result[$i]['Copyright'] = $this->getCopyright($isbn);
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
                // Replacement for http/https compatibility
                $iframe = str_replace('http://', '//', $iframe);
                $result[] = [
                    'Rating' => '',
                    'Summary' => '',
                    'Copyright' => $this->getCopyright($isbn),
                    'Content' =>
                        "<iframe style=\"{$css}\" src=\"{$iframe}\"></iframe>"
                ];
            }
        }

        return $result;
    }
}
