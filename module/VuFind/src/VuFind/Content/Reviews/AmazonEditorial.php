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
use ZendService\Amazon\Amazon;

/**
 * Amazon review content loader.
 *
 * @category VuFind2
 * @package  Content
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class AmazonEditorial extends \VuFind\Content\AbstractAmazon
{
    /**
     * Amazon Editorial
     *
     * This method is responsible for connecting to Amazon AWS and abstracting
     * editorial reviews for the specific ISBN
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
        try {
            $amazon = new Amazon($key, 'US', $this->secret);
            $amazon->getRestClient()->setHttpClient($this->getHttpClient());
            $params = [
                'ResponseGroup' => 'EditorialReview',
                'AssociateTag' => $this->associate
            ];
            $isbn = $this->getIsbn10($isbnObj);
            $data = $amazon->itemLookup($isbn, $params);
        } catch (\Exception $e) {
            // Something went wrong?  Just return empty list.
            return [];
        }

        if ($data) {
            $i = 0;
            $result = [];
            $reviews = isset($data->EditorialReviews)
                ? $data->EditorialReviews : null;
            if (!empty($reviews)) {
                foreach ($reviews as $review) {
                    // Filter out product description
                    if ((string)$review->Source != 'Product Description') {
                        foreach ($review as $key => $value) {
                            $result[$i][$key] = (string)$value;
                        }
                        if (!isset($result[$i]['Copyright'])) {
                            $result[$i]['Copyright'] = $this->getCopyright($isbn);
                        }
                        $i++;
                    }
                }
            }
            return $result;
        }
    }
}
