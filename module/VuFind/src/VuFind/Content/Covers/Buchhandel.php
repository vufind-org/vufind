<?php

/**
 * Buchhandel cover content loader.
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
 * @author   Jochen Lienhard <lienhard@ub.uni-freiburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Content\Covers;

/**
 * Buchhandel cover content loader.
 *
 * @category VuFind
 * @package  Content
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Jochen Lienhard <lienhard@ub.uni-freiburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Buchhandel extends \VuFind\Content\AbstractCover
{
    /**
     * Base URL for Buchhandel
     *
     * @var string
     */
    protected $url;

    /**
     * API token for Buchhandel
     *
     * @var string
     */
    protected $apiToken;

    /**
     * Constructor
     *
     * @param string $url      Base URL for Buchhandel
     * @param string $apiToken API token for Buchhandel
     */
    public function __construct($url, $apiToken)
    {
        $this->url = $url;
        $this->apiToken = $apiToken;
        $this->supportsIsbn = true;
        $this->cacheAllowed = false;
    }

    /**
     * Get image URL for a particular API token and set of IDs (or false if invalid).
     *
     * @param string $key  API key
     * @param string $size Size of image to load (small/medium/large)
     * @param array  $ids  Associative array of identifiers (keys may include 'isbn'
     * pointing to an ISBN object and 'issn' pointing to a string)
     *
     * @return string|bool
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getUrl($key, $size, $ids)
    {
        if (!isset($ids['isbn'])) {
            return false;
        }
        $isbn = $ids['isbn']->get13();
        switch ($size) {
            case 'small':
            case 'medium':
            case 'large':
                $lsize = substr($size, 0, 1);
                break;
            default:
                $lsize = 's';
                break;
        }

        return "{$this->url}{$isbn}/{$lsize}?access_token={$this->apiToken}";
    }
}
