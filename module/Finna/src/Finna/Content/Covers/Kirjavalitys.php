<?php

/**
 * Kirjavalitys Cover Image Service cover content loader.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2018-2023.
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
 * @author   Kalle Pyykkönen <kalle.pyykkonen@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\Content\Covers;

use VuFindCode\ISBN;

/**
 * Kirjavalitys Cover Image Service cover content loader.
 *
 * @category VuFind
 * @package  Content
 * @author   Kalle Pyykkönen <kalle.pyykkonen@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Kirjavalitys extends \VuFind\Content\AbstractCover
{
    /**
     * Recordloader to fetch the current record
     *
     * @var VuFind\RecordLoader
     */
    protected $recordLoader = null;

    /**
     * Constructor
     *
     * @param VuFind\RecordLoader $recordLoader Record loader.
     */
    public function __construct(\VuFind\Record\Loader $recordLoader)
    {
        $this->recordLoader = $recordLoader;
        $this->supportsRecordid = true;
        $this->cacheAllowed = false;
    }

    /**
     * Get image URL for a particular API key and set of IDs (or false if invalid).
     *
     * @param string $key  Library ID
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
        if (!$key) {
            return false;
        }
        $sizeCodes = [
            'medium' => 'max400',
            'small' => 'max200',
            'large' => 'max800',
        ];
        try {
            $pid = '';
            $driver = $this->recordLoader->load($ids['recordid'], 'Solr');
            $recordISBN = new ISBN($driver->getCleanISBN());
            if ($isbn = $recordISBN->get13()) {
                $pid = $isbn;
            } else {
                $standardCodes = $driver->tryMethod('getStandardCodes');
                if ($standardCodes) {
                    foreach ($standardCodes as $code) {
                        $parts = explode(' ', $code);
                        if (isset($parts[1]) && 'EAN' === $parts[0]) {
                            $pid = $parts[1];
                            break;
                        }
                    }
                }
            }
            if ('' !== $pid) {
                $params = [
                    'format' => 'image',
                    'size' => $sizeCodes[$size] ?? 'max400',
                ];
                return 'https://media.kirjavalitys.fi/library/cover/' . rawurlencode($key) . '/' . rawurlencode($pid)
                    . '?' . http_build_query($params);
            }
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
}
