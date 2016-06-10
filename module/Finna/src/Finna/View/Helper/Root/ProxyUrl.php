<?php
/**
 * Proxy URL view helper
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\View\Helper\Root;

use VuFind\Net\ipAddressUtils;

/**
 * Proxy URL view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class ProxyUrl extends \VuFind\View\Helper\Root\ProxyUrl
{
    /**
     * IP address utils
     *
     * @var ipAddressUtils
     */
    protected $ipAddressUtils;

    /**
     * Permissions configuration
     *
     * @var \Zend\Config\Config
     */
    protected $permissions;

    /**
     * Cached value for IP check
     *
     * @var null|bool
     */
    protected $ipInRange = null;

    /**
     * Constructor
     *
     * @param ipAddressUtils      $ipUtils     IP address utils
     * @param \Zend\Config\Config $permissions Permissions configuration
     * @param \Zend\Config\Config $config      VuFind configuration
     */
    public function __construct(
        ipAddressUtils $ipUtils, $permissions, $config = null
    ) {
        parent::__construct($config);

        $this->ipAddressUtils = $ipUtils;
        $this->permissions = $permissions;
    }

    /**
     * Apply proxy prefix to URL (if configured).
     *
     * @param string $url The raw URL to adjust
     *
     * @return string
     */
    public function __invoke($url)
    {
        // Shortcut
        if (!isset($this->config->EZproxy->host)) {
            return $url;
        }
        $config = $this->config->EZproxy;
        if (isset($config->proxy_known_ip_addresses)
            && !$config->proxy_known_ip_addresses
            && $this->getIpInRange()
        ) {
            return $url;
        }

        if (isset($config->include_url) || isset($config->include_url_re)) {
            $pass = false;
            if (isset($config->include_url)) {
                foreach ($config->include_url as $mask) {
                    if (strstr($url, $mask)) {
                        $pass = true;
                        break;
                    }
                }
            }

            if (!$pass && isset($config->include_url_re)) {
                foreach ($config->include_url_re as $mask) {
                    if (preg_match($mask, $url)) {
                        $pass = true;
                        break;
                    }
                }
            }

            if (!$pass) {
                return $url;
            }
        }

        if (isset($config->exclude_url)) {
            foreach ($config->exclude_url->toArray() as $mask) {
                if (strstr($url, $mask)) {
                    return $url;
                }
            }
        }

        if (isset($config->exclude_url_re)) {
            foreach ($config->exclude_url_re->toArray() as $mask) {
                if (preg_match($mask, $url)) {
                    return $url;
                }
            }
        }

        // Check for source specific filters
        if (!empty($config->include_source)
            || !empty($config->include_datasource)
            || !empty($config->exclude_datasource)
        ) {
            $driver = $this->getView()->driver;
            if (null !== $driver) {
                if (!empty($config->include_source)) {
                    $source = $driver->getSourceIdentifier();
                    if (!in_array($source, $config->include_source->toArray())) {
                        return $url;
                    }
                }
                $datasources = $driver->tryMethod('getSource');
                if (!empty($datasources)) {
                    foreach (is_array($datasources) ? $datasources : [$datasource]
                        as $datasource
                    ) {
                        if (!empty($config->include_datasource)) {
                            $sources = $config->include_datasource->toArray();
                            if (!in_array($datasource, $sources)) {
                                return $url;
                            }
                        }
                        if (!empty($config->exclude_datasource)) {
                            $sources = $config->exclude_datasource->toArray();
                            if (in_array($datasource, $sources)) {
                                return $url;
                            }
                        }
                    }
                }
            }
        }

        return parent::__invoke($url);
    }

    /**
     * Check if the requester's IP is in any known IP address range and cache the
     * result
     *
     * @return bool
     */
    protected function getIpInRange()
    {
        if ($this->ipInRange !== null) {
            return $this->ipInRange;
        }

        $this->ipInRange = false;
        $remoteAddress = new \Zend\Http\PhpEnvironment\RemoteAddress();
        $remoteIp = $remoteAddress->getIpAddress();
        // Iterate all permissions with ipRanges. We'll accept any range for now.
        foreach ($this->permissions as $permission) {
            if (empty($permission['ipRange'])) {
                continue;
            }
            $ranges = [];
            foreach ($permission['ipRange']->toArray() as $range) {
                list($ip) = explode('#', $range, 2);
                $ranges = array_merge($ranges, array_map('trim', explode(',', $ip)));
            }

            if ($this->ipAddressUtils->isInRange($remoteIp, $ranges)) {
                $this->ipInRange = true;
                break;
            }
        }
        return $this->ipInRange;
    }
}
