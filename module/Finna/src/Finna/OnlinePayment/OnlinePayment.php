<?php

/**
 * Online payment service
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2015-2023.
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
 * @package  OnlinePayment
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */

namespace Finna\OnlinePayment;

use Finna\OnlinePayment\Handler\PluginManager as HandlerPluginManager;

/**
 * Online payment service
 *
 * @category VuFind
 * @package  OnlinePayment
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class OnlinePayment
{
    /**
     * Online payment handler plugin manager
     *
     * @var HandlerPluginManager
     */
    protected $handlerManager;

    /**
     * Data source configuration
     *
     * @var \VuFind\Config\Config
     */
    protected $config;

    /**
     * Constructor.
     *
     * @param HandlerPluginManager  $handlerManager Handler plugin manager
     * @param \VuFind\Config\Config $config         Data source configuration
     */
    public function __construct(
        HandlerPluginManager $handlerManager,
        \VuFind\Config\Config $config
    ) {
        $this->handlerManager = $handlerManager;
        $this->config = $config;
    }

    /**
     * Get online payment handler
     *
     * @param string $source Datasource
     *
     * @return Finna\OnlinePayment\OnlinePaymentHandlerInterface
     */
    public function getHandler($source)
    {
        if (!($handlerName = $this->getHandlerName($source))) {
            throw new \Exception("Online payment handler not defined for $source");
        }
        if (!$this->handlerManager->has($handlerName)) {
            throw new \Exception(
                "Online payment handler $handlerName not found for $source"
            );
        }

        $handler = $this->handlerManager->get($handlerName);
        $handler->init($this->getConfig($source));
        return $handler;
    }

    /**
     * Get online payment handler name.
     *
     * @param string $source Datasource
     *
     * @return string
     */
    public function getHandlerName($source)
    {
        if ($config = $this->getConfig($source)) {
            return $config['handler'] ?? '';
        }
        return '';
    }

    /**
     * Check if online payment is enabled for a datasource.
     *
     * @param string $source Datasource
     *
     * @return bool
     */
    public function isEnabled($source)
    {
        return $this->getConfig($source) ? true : false;
    }

    /**
     * Get online payment handler configuration for a datasource.
     *
     * @param string $source Datasource
     *
     * @return array
     */
    protected function getConfig($source)
    {
        return $this->config[$source]['onlinePayment'] ?? [];
    }
}
