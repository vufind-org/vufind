<?php
/**
 * National Library of Finland Location Service.
 *
 * PHP version 5
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Content
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\LocationService;

/**
 * National Library of Finland Location Service.
 *
 * @category VuFind2
 * @package  Content
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class LocationService
{
    /**
     * National Library of Finland Location service configuration.
     *
     * @var \Zend\Config\Config
     */
    protected $config = null;

    /**
     * Constructor.
     *
     * @param \Zend\Config\Config $config Configuration
     */
    public function __construct($config)
    {
        $this->config = $config->toArray();
    }

    /**
     * Return configuration parameter for a Location Service link.
     *
     * @param string $source     Record source
     * @param string $callnumber Callnumber that is used as a location code.
     * @param string $collection Collection
     * @param string $language   Language
     *
     * @return array Array with the following keys:
     *   [url]   string  URL to the Location Service map.
     *   [modal] boolean True if the map should be displayed in a modal.
     *   [qr]    boolean True if a QR-code of the map link should be displayed.
     */
    public function getConfig($source, $callnumber, $collection, $language)
    {
        if (empty($this->config['General']['enabled'])
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

        $params = [
            'callno' => $callnumber,
            'collection' => $collection,
            'lang' => substr($language, 0, 2),
        ];

        if (isset($this->config[$source]['owner'])) {
            $params['owner'] = $this->config[$source]['owner'];
        }

        foreach ($params as $key => $val) {
            $url = str_replace('{' . $key . '}', urlencode($val), $url);
        }

        return [
           'url' => $url,
           'modal' => isset($this->config['General']['modal'])
              ? $this->config['General']['modal'] : true,
           'qr' => isset($this->config['General']['qr_code'])
              ? $this->config['General']['qr_code'] : false
        ];
    }

    /**
     * Check if QR-code option is enabled.
     *
     * @return boolean
     */
    public function useQrCode()
    {
        return isset($this->config['General']['qr_code'])
            && $this->config['General']['qr_code'];
    }
}
