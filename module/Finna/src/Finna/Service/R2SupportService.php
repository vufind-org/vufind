<?php
/**
 * Restricted Solr search R2 support service.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2020.
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
 * @package  Service
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\Service;

use Laminas\Config\Config;
use LmcRbacMvc\Service\AuthorizationService;

/**
 * Restricted Solr search R2 support service.
 *
 * @category VuFind
 * @package  Service
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class R2SupportService
{
    /**
     * R2 configuration.
     *
     * @var \Laminas\Config\Config
     */
    protected $config;

    /**
     * Authentication service.
     *
     * @var AuthorizationService
     */
    protected $authService;

    /**
     * Constructor.
     *
     * @param Config               $config      Configuration
     * @param AuthorizationService $authService Authorization service
     */
    public function __construct(Config $config, AuthorizationService $authService)
    {
        $this->config = $config;
        $this->authService = $authService;
    }

    /**
     * Is R2 search enabled?
     *
     * @return bool
     */
    public function isEnabled()
    {
        return $this->config->R2->enabled ?? false;
    }

    /**
     * Is R2 search is enabled and the user authenticated
     * to see restricted R2 metadata?
     *
     * @return bool
     */
    public function isAuthenticated()
    {
        return $this->isEnabled()
            && $this->authService->isGranted('access.R2Authenticated');
    }

    /**
     * Return R2 index API credentials (keyed array with 'apiUser' and 'apiKey').
     *
     * @return array
     */
    public function getCredentials()
    {
        if (empty($apiUser = $this->config->R2->apiUser ?? null)) {
            throw new \Exception('R2 apiUser not defined');
        }
        if (empty($apiKey = $this->config->R2->apiKey ?? null)) {
            throw new \Exception('R2 apiKey not defined');
        }
        return compact('apiUser', 'apiKey');
    }
}
