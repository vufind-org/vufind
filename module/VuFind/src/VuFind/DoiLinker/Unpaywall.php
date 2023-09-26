<?php

/**
 * Unpaywall DOI linker
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2019.
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
 * @package  DOI
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:doi_linkers Wiki
 */

namespace VuFind\DoiLinker;

use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFindHttp\HttpServiceAwareInterface;

/**
 * Unpaywall DOI linker
 *
 * @category VuFind
 * @package  DOI
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:doi_linkers Wiki
 */
class Unpaywall implements
    DoiLinkerInterface,
    TranslatorAwareInterface,
    HttpServiceAwareInterface
{
    use \VuFindHttp\HttpServiceAwareTrait;
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * URL to Unpaywall API
     *
     * @var string api url
     */
    protected $apiUrl;

    /**
     * E-mail used as parameter when calling API
     *
     * @var string email
     */
    protected $email;

    /**
     * Constructor
     *
     * @param \Laminas\Config\Config $config DOI section of main VuFind config
     *
     * @throws \Exception
     */
    public function __construct($config)
    {
        if (!isset($config->unpaywall_email)) {
            throw new \Exception(
                'Missing configuration for Unpaywall DOI linker: unpaywall_email'
            );
        }
        $this->email = $config->unpaywall_email;
        $this->apiUrl = $config->unpaywall_api_url ?? 'https://api.unpaywall.org/v2';
    }

    /**
     * Given an array of DOIs, perform a lookup and return an associative array
     * of arrays, keyed by DOI. Each array contains one or more associative arrays
     * with required 'link' (URL to related resource) and 'label' (display text)
     * keys and an optional 'icon' (URL to icon graphic) or localIcon (name of
     * configured icon in theme) key.
     *
     * @param array $doiArray DOIs to look up
     *
     * @return array
     */
    public function getLinks(array $doiArray)
    {
        $response = [];
        foreach ($doiArray as $doi) {
            $json = $this->callApi($doi);
            if ($json === null) {
                continue;
            }
            $data = json_decode($json, true);
            if (!empty($data['best_oa_location']['url_for_pdf'])) {
                $response[$doi][] = [
                    'link' => $data['best_oa_location']['url_for_pdf'],
                    'label' => $this->translate('PDF Full Text'),
                ];
            } elseif (!empty($data['best_oa_location']['url'])) {
                $response[$doi][] = [
                    'link' => $data['best_oa_location']['url'],
                    'label' => $this->translate('online_resources'),
                ];
            }
        }
        return $response;
    }

    /**
     * Takes a DOI and do an API call to Unpaywall service
     *
     * @param string $doi DOI
     *
     * @return null|string
     */
    protected function callApi($doi)
    {
        $url = $this->apiUrl . '/' . urlencode($doi) . '?'
            . http_build_query(['email' => $this->email]);
        $client = $this->httpService->createClient($url);
        $response = $client->send();
        if ($response->isSuccess()) {
            return $response->getBody();
        }
        return null;
    }
}
