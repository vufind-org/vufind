<?php
/**
 * Online payment service
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2015-2019.
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
 * @package  Content
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\OnlinePayment;

/**
 * Online payment service
 *
 * @category VuFind
 * @package  Content
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class OnlinePayment implements \VuFind\I18n\Translator\TranslatorAwareInterface,
    \VuFindHttp\HttpServiceAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;
    use \VuFindHttp\HttpServiceAwareTrait;

    /**
     * Configuration.
     *
     * @var \Laminas\Config\Config
     */
    protected $config;

    /**
     * Table manager
     *
     * @var \VuFind\Db\Table\PluginManager
     */
    protected $tableManager;

    /**
     * Logger
     *
     * @var \VuFind\Log\Logger
     */
    protected $logger;

    /**
     * Constructor.
     *
     * @param DbTablePluginManager $tableManager Table manager
     * @param Logger               $logger       Logger
     * @param Config               $config       Configuration
     */
    public function __construct(\VuFind\Db\Table\PluginManager $tableManager,
        \VuFind\Log\Logger $logger, \Laminas\Config\Config $config
    ) {
        $this->tableManager = $tableManager;
        $this->logger = $logger;
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
        $handler = new $class(
            $this->getConfig($source),
            $this->httpService,
            $this->translator
        );
        $handler->setDbTableManager($this->tableManager);
        $handler->setLogger($this->logger);
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
