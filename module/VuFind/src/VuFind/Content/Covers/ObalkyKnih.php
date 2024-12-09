<?php

/**
 * Class ObalkyKnih
 *
 * PHP version 8
 *
 * Copyright (C) Moravian Library 2019.
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
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

declare(strict_types=1);

namespace VuFind\Content\Covers;

use VuFind\Content\ObalkyKnihService;

/**
 * Class ObalkyKnih
 *
 * @category VuFind
 * @package  Content
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ObalkyKnih extends \VuFind\Content\AbstractCover
{
    /**
     * Obalky knih service
     *
     * @var ObalkyKnihService
     */
    protected $service;

    /**
     * Constructor
     *
     * @param ObalkyKnihService $service Service for getting metadata from
     * obalkyknih.cz
     */
    public function __construct(ObalkyKnihService $service)
    {
        $this->supportsIsbn = true;
        $this->supportsIssn = true;
        $this->supportsIsmn = true;
        $this->supportsOclc = true;
        $this->supportsUpc = true;
        $this->supportsNbn = true;
        $this->supportsRecordid = true;
        $this->supportsUuid = true;
        $this->cacheAllowed = false;
        $this->directUrls = true;
        $this->mandatoryBacklinkLocations = ['core'];

        $this->service = $service;
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
        $data = $this->service->getData($ids);
        if (!isset($data)) {
            return false;
        }
        switch ($size) {
            case 'small':
                $imageUrl = $data->cover_icon_url ?? false;
                break;
            case 'medium':
                $imageUrl = $data->cover_medium_url ?? false;
                break;
            case 'large':
                $imageUrl = $data->cover_preview510_url ?? false;
                break;
            default:
                $imageUrl = $data->cover_medium_url ?? false;
                break;
        }
        return $imageUrl;
    }

    /**
     * Get cover metadata for a particular API key and set of IDs (or empty array).
     *
     * @param string $key  API key
     * @param string $size Size of image to load (small/medium/large)
     * @param array  $ids  Associative array of identifiers (keys may include 'isbn'
     * pointing to an ISBN object, 'issn' pointing to a string and 'oclc' pointing
     * to an OCLC number string)
     *
     * @return array Array with keys: url, backlink_url, backlink_text
     */
    public function getMetadata(?string $key, string $size, array $ids)
    {
        $url = $this->getUrl($key, $size, $ids);
        if ($url) {
            $data = $this->service->getData($ids);
            return [
                'url' => $url,
                'backlink_url' => $data->backlink_url ?? '',
                'backlink_text' => 'ObálkyKnih.cz',
            ];
        }
        return [];
    }
}
