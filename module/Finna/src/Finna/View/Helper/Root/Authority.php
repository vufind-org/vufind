<?php

/**
 * Authority view helper
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2020-2023.
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
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\View\Helper\Root;

use Finna\Search\Solr\AuthorityHelper;

/**
 * Authority view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Authority extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Authority configuration
     *
     * @var \VuFind\Config\Config
     */
    protected $config;

    /**
     * Authority helper
     *
     * @var AuthorityHelper
     */
    protected $authorityHelper;

    /**
     * Constructor
     *
     * @param \VuFind\Config\Config $config          Authority configuration
     * @param AuthorityHelper       $authorityHelper Authority helper
     */
    public function __construct(
        \VuFind\Config\Config $config,
        AuthorityHelper $authorityHelper
    ) {
        $this->config = $config;
        $this->authorityHelper = $authorityHelper;
    }

    /**
     * Check if authhority search is available
     *
     * @return bool
     */
    public function isAvailable()
    {
        return $this->config->General->enabled ?? false;
    }

    /**
     * Get the number of records having the given authority id as an author
     *
     * @param string $id Authority ID
     *
     * @return int
     */
    public function getCountAsAuthor(string $id): int
    {
        return $this->authorityHelper
            ->getRecordsByAuthorityId($id, AuthorityHelper::AUTHOR2_ID_FACET, true);
    }

    /**
     * Get the number of records having the given authority id as a topic
     *
     * @param string $id Authority ID
     *
     * @return int
     */
    public function getCountAsTopic(string $id): int
    {
        return $this->authorityHelper
            ->getRecordsByAuthorityId($id, AuthorityHelper::TOPIC_ID_FACET, true);
    }
}
