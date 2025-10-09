<?php

/**
 * BrowZine cover content loader.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Content
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Content\Covers;

use VuFindSearch\Backend\BrowZine\Command\LookupIssnsCommand;
use VuFindSearch\Service;

use function in_array;

/**
 * BrowZine cover content loader.
 *
 * @category VuFind
 * @package  Content
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class BrowZine extends \VuFind\Content\AbstractCover
{
    /**
     * Constructor
     *
     * @param Service  $searchService Search service
     * @param string[] $ignoreList    Cover image URLs to ignore (we don't want to display third-party generic images)
     */
    public function __construct(protected Service $searchService, protected array $ignoreList)
    {
        $this->supportsIssn = true;
    }

    /**
     * Get image URL for a particular API key and set of IDs (or false if invalid).
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
        // Don't bother trying if ISSN is missing:
        if (!isset($ids['issn'])) {
            return false;
        }

        $command = new LookupIssnsCommand('BrowZine', $ids['issn']);
        $result = $this->searchService->invoke($command)->getResult();
        $url = $result['data'][0]['coverImageUrl'] ?? false;
        return ($url && in_array($url, $this->ignoreList)) ? false : $url;
    }
}
