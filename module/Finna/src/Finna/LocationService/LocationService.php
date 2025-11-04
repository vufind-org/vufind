<?php

/**
 * Location Service.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2016.
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\LocationService;

use function is_array;

/**
 * Location Service.
 *
 * @category VuFind
 * @package  Content
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class LocationService
{
    /**
     * Location service configuration.
     *
     * @var \VuFind\Config\Config
     */
    protected $config = null;

    /**
     * Constructor.
     *
     * @param \VuFind\Config\Config $config Configuration
     */
    public function __construct($config)
    {
        $this->config = $config->toArray();
    }

    /**
     * Return configuration parameter for a Location Service link.
     *
     * @param string  $source     Record source
     * @param string  $title      Record title
     * @param ?string $callnumber Callnumber that is used as a location code.
     * @param ?string $collection Collection
     * @param ?string $location   Location
     * @param string  $language   Language
     * @param array   $fields     Additional data fields
     *
     * @return array Array with the following keys:
     *   [url]   string  URL to the Location Service map.
     *   [modal] boolean True if the map should be displayed in a modal.
     *   [qr]    boolean True if a QR-code of the map link should be displayed.
     */
    public function getConfig(
        $source,
        $title,
        $callnumber,
        $collection,
        $location,
        $language,
        $fields
    ) {
        if (
            empty($this->config['General']['enabled'])
            || empty($this->config['General']['url'])
            || empty($this->config[$source])
            || (empty($this->config[$source]['owner'])
            && empty($this->config[$source]['url']))
        ) {
            return false;
        }

        $url = $this->config['General']['url'];
        if (!empty($this->config[$source]['url'])) {
            $url = $this->config[$source]['url'];
        }

        if (is_array($url)) {
            if (isset($url[$language])) {
                $url = $url[$language];
            } else {
                $url = reset($url);
            }
        }

        $callnum = $fields['callnumber'] ?? '';
        if ($callnum instanceof \VuFind\I18n\TranslatableString) {
            $callnum = $callnum->getDisplayString();
        }
        [$lang] = explode('-', $language, 2);
        $params = [
            'callno' => $callnum,
            'collection' => $collection,
            'location' => $location,
            'branch' => $fields['branch'] ?? '',
            'department' => $fields['department'] ?? '',
            'title' => $title,
            'lang' => $lang,
            'locale' => $language,
            'owner' => $this->config[$source]['owner'] ?? '',
        ];

        foreach ($params as $key => $val) {
            $url = str_replace('{' . $key . '}', urlencode($val ?? ''), $url);
        }

        return [
           'url' => $url,
           'modal' => $this->config['General']['modal'] ?? true,
           'qrCodeRecord' => $this->config['General']['qr_code_record'] ?? false,
           'qrCodeResults' => $this->config['General']['qr_code_results'] ?? false,
           'level' => $this->config[$source]['level'] ?? 'location',
        ];
    }

    /**
     * Check if QR-code option is enabled.
     *
     * @return boolean
     */
    public function useQrCode()
    {
        return !empty($this->config['General']['qr_code_record'])
            || !empty($this->config['General']['qr_code_results']);
    }
}
