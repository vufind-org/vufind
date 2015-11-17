<?php
/**
 * Online payment service
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015.
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
namespace Finna\OnlinePayment;
use Zend\Config\Config;

/**
 * Online payment service
 *
 * @category VuFind2
 * @package  Content
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class OnlinePayment
{
    /**
     * Configuration.
     *
     * @var array
     */
    protected $config;

    /**
     * Service manager
     *
     * @var ServiceManager
     */
    protected $serviceManager;

    /**
     * Constructor.
     *
     * @param ServiceManager $sm     ServiceManager
     * @param Config         $config Configuration
     */
    public function __construct($sm, $config)
    {
        $this->serviceManager = $sm;
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
        $handler = $this->getHandlerName($source);
        $class = "Finna\OnlinePayment\\$handler";
        if (!class_exists($class)) {
            throw new \Exception(
                "Online payment handler $class not found for $source"
            );
        }
        $handler =  new $class($this->getConfig($source));
        $handler->setDbTableManager(
            $this->serviceManager->get('VuFind\DbTablePluginManager')
        );
        return $handler;
    }

    /**
     * Get online payment handler name.
     *
     * @param string $source Datasource
     *
     * @return boolean
     */
    public function getHandlerName($source)
    {
        if ($config = $this->getConfig($source)) {
            return $config['handler'];
        }
        return false;
    }

    /**
     * Check if online payment is enabled for a datasource.
     *
     * @param string $source Datasource
     *
     * @return boolean
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
     * @return mixed null|array
     */
    protected function getConfig($source)
    {
        return isset($this->config[$source]['onlinePayment'])
            ? $this->config[$source]['onlinePayment'] : null;
    }
}
