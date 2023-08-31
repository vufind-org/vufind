<?php

/**
 * Guardian review content loader.
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

use function strlen;

/**
 * Guardian review content loader.
 *
 * @category VuFind
 * @package  Content
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Guardian extends \VuFind\Content\AbstractBase
{
    /**
     * Guardian Reviews
     *
     * This method is responsible for connecting to the Guardian and abstracting
     * reviews for the specific ISBN.
     *
     * @param string           $key     API key (unused here)
     * @param \VuFindCode\ISBN $isbnObj ISBN object
     *
     * @throws \Exception
     * @return array     Returns array with review data.
     * @author Eoghan Ó Carragáin <eoghan.ocarragain@gmail.com>
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function loadByIsbn($key, \VuFindCode\ISBN $isbnObj)
    {
        // Base request URL:
        $url
            = 'http://content.guardianapis.com/search?order-by=newest&format=json' .
                '&show-fields=all&reference=isbn%2F' . $isbnObj->get13();

        // Only add api-key if one has been provided in config.ini. If no key is
        // provided, a link to the Guardian can still be shown.
        if (strlen($key) > 0) {
            $url = $url . '&api-key=' . $key;
        }

        $this->debug('Guardian request: ' . $url);

        // Find out if there are any reviews:
        $result = $this->getHttpClient($url)->send();

        // Was the request successful?
        if ($result->isSuccess()) {
            // parse json from response
            $data = json_decode($result->getBody(), true);
            if ($data) {
                $result = [];
                $i = 0;
                foreach ($data['response']['results'] as $review) {
                    $result[$i]['Date'] = $review['webPublicationDate'];
                    $result[$i]['Summary'] = $review['fields']['headline'] . '. ' .
                        preg_replace(
                            '/<p>|<p [^>]*>|<\/p>/',
                            '',
                            html_entity_decode($review['fields']['trailText'])
                        );
                    $result[$i]['ReviewURL'] = $review['fields']['shortUrl'];

                    // TODO: Make this configurable (or store it locally), so users
                    //       running VuFind behind SSL don't get warnings due to
                    //       inclusion of this non-SSL image URL:
                    $poweredImage
                        = 'http://image.guardian.co.uk/sys-images/Guardian/' .
                        'Pix/pictures/2010/03/01/poweredbyguardianBLACK.png';

                    $result[$i]['Copyright'] = '<a href="' .
                        $review['fields']['shortUrl'] . '" target="new">' .
                        "<img src=\"{$poweredImage}\" " .
                        'alt="Powered by the Guardian" /></a>';

                    $result[$i]['Source'] = $review['fields']['byline'];
                    // Only return Content if the body tag contains a usable review
                    $redist = 'Redistribution rights for this field are unavailable';
                    if (
                        (strlen($review['fields']['body']) > 0)
                        && (!strstr($review['fields']['body'], $redist))
                    ) {
                        $result[$i]['Content'] = $review['fields']['body'];
                    }
                    $i++;
                }
                return $result;
            } else {
                throw new \Exception('Could not parse Guardian response.');
            }
        } else {
            return [];
        }
    }
}
