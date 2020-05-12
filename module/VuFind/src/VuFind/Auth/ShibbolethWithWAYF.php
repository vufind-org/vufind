<?php
/**
 * Shibboleth authentication module.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2014.
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Authentication
 * @author   Vaclav Rosecky <vaclav.rosecky@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace VuFind\Auth;

use VuFind\Exception\Auth as AuthException;

/**
 * Shibboleth with WAYF authentication module for authentication against
 * multiple IdPs.
 *
 * @category VuFind
 * @package  Authentication
 * @author   Vaclav Rosecky <vaclav.rosecky@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class ShibbolethWithWAYF extends AbstractShibboleth
{

    /**
     * Configured IdPs with entityId and overridden attribute mapping
     *
     * @param \Laminas\Config\Config
     */
    protected $shibbolethConfig;

    /**
     * Constructor
     *
     * @param \Laminas\Session\ManagerInterface $sessionManager session manager
     * @param \Laminas\Config\Config            $shibConfig     shibboleth
     * configuration file (shibboleth.ini)
     */
    public function __construct(\Laminas\Session\ManagerInterface $sessionManager,
        \Laminas\Config\Config $shibConfig
    ) {
        parent::__construct($sessionManager);
        $this->shibbolethConfig = $shibConfig;
    }

    /**
     * Return shibboleth configuration.
     *
     * @param \Laminas\Http\PhpEnvironment\Request $request Request object containing
     * account credentials.
     *
     * @throws \VuFind\Exception\Auth
     * @return array shibboleth configuration
     */
    protected function getShibbolethConfiguration($request)
    {
        $entityId = $this->fetchCurrentEntityId($request);
        $config = $this->config->Shibboleth->toArray();
        $idpConfig = null;
        $prefix = null;
        foreach ($this->shibbolethConfig as $name => $configuration) {
            if ($entityId == trim($configuration['entityId'])) {
                $idpConfig = $configuration->toArray();
                $prefix = $name;
                break;
            }
        }
        if ($idpConfig == null) {
            $this->debug(
                "Missing configuration for Idp with entityId: {$entityId})"
            );
            throw new AuthException('Missing configuration for IdP.');
        }
        foreach ($idpConfig as $key => $value) {
            $config[$key] = $value;
        }
        $config['prefix'] = $prefix;
        return $config;
    }

    /**
     * Fetch entityId used for authentication
     *
     * @param \Laminas\Http\PhpEnvironment\Request $request Request object
     *
     * @return string entityId of IdP
     */
    protected function fetchCurrentEntityId($request)
    {
        return $this->getAttribute($request, static::DEFAULT_IDPSERVERPARAM);
    }
}
