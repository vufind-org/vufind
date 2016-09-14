<?php
/**
 * Holdings callnumber view helper
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
 * @category VuFind
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\View\Helper\Root;

/**
 * Holdings callnumber view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Callnumber extends \Zend\View\Helper\AbstractHelper
{
    /**
     * Location Service.
     *
     * @var \Finna\LocationService
     */
    protected $locationService = null;

    /**
     * Constructor
     *
     * @param \Finna\LocationService $locationService Location Service 
     * of Finland Location Service
     */
    public function __construct($locationService)
    {
        $this->locationService = $locationService;
    }

    /**
     * Returns HTML for a holding callnumber.
     *
     * @param string $source     Record source
     * @param string $title      Record title
     * @param string $callnumber Callnumber
     * @param string $collection Collection
     * @param string $location   Location
     * @param string $language   Language
     * @param string $page       Page (record|results)
     *
     * @return string
     */
    public function callnumber(
        $source, $title, $callnumber, $collection, $location,
        $language, $page = 'record'
    ) {
        $params = [
            'callnumber' => $callnumber, 'location' => $location, 'title' => $title,
            'page' => $page, 'source' => $source
        ];
        $config = $this->locationService->getConfig(
            $source, $title, $callnumber, $collection, $location, $language
        );

        if ($config) {
            $params['collection'] = $collection;
            $params['location'] = $location;
            $params['title'] = $title;
            $params['locationServiceUrl'] = $config['url'];
            $params['locationServiceModal'] = $config['modal'];
            $params['qrCode']
                = $config[$page == 'results' ? 'qrCodeResults' : 'qrCodeRecord'];
        }
        return $this->getView()->render(
            'Helpers/holding-callnumber.phtml', $params
        );
    }

    /**
     * Check if QR-code option is enabled.
     *
     * @return boolean
     */
    public function useQrCode()
    {
        return $this->locationService->useQrCode();
    }
}
