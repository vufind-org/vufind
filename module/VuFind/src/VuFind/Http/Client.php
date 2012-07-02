<?php
/**
 * Proxy Server Support for VuFind
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2009.
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
 * @package  Support_Classes
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/system_classes Wiki
 */
namespace VuFind\Http;
use VuFind\Config\Reader as ConfigReader, Zend\Http\Client as BaseClient;

/**
 * Proxy_Request Class
 *
 * This is a wrapper class around the Zend HTTP client which automatically
 * initializes proxy server support when requested by the configuration file.
 *
 * @category VuFind2
 * @package  Support_Classes
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/system_classes Wiki
 */
class Client extends BaseClient
{
    protected $checkProxy = false;

    /**
     * Constructor
     *
     * @param string            $uri     Target URI
     * @param array|Traversable $options options array (passed to \Zend\Http\Client)
     */
    public function __construct($uri = null, $options = array())
    {
        // If an adapter was not explicitly passed in, set a flag indicating that
        // we need to recheck the proxy settings whenever the request URI is changed
        // (since we need different behavior for localhost vs. proxy)
        if (!isset($options['adapter'])) {
            $this->checkProxy = true;
        }

        // Set up the configuration with the parent class; this will in turn call
        // our overridden setUri() method and configure the adapter if $uri is set.
        parent::__construct($uri, $options);
    }

    /**
     * Set the URI for the next request
     *
     * @param \Zend\Uri\Http|string $uri Target URI
     *
     * @return Client
     */
    public function setUri($uri)
    {
        // If the "check proxy" flag is set, make sure the adapter is configured
        // appropriately for the current URI.
        if ($this->checkProxy) {
            $this->configureAdapter($uri);
        }
        return parent::setUri($uri);
    }

    /**
     * Configure the adapter based on VuFind settings.
     *
     * @param string $uri Target URI
     *
     * @return void
     */
    protected function configureAdapter($uri)
    {
        $config = ConfigReader::getConfig();

        // Never proxy localhost traffic, even if configured to do so:
        $skipProxy = (strstr($uri, '//localhost') !== false);

        // Proxy server settings
        if (isset($config->Proxy->host) && !$skipProxy) {
            $options = array(
                'adapter' => 'Zend\\Http\\Client\\Adapter\\Proxy',
                'proxy_host' => $config->Proxy->host
            );
            if (isset($config->Proxy->port)) {
                $options['proxy_port'] = $config->Proxy->port;
            }
            $this->setConfig($options);
        } else {
            // Default if no proxy settings found:
            $this->setAdapter('Zend\\Http\\Client\\Adapter\\Socket');
        }
    }
}
