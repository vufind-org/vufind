<?php
/**
 * VuFind Plugin Manager
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  ServiceManager
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\ServiceManager;
use Zend\ServiceManager\AbstractPluginManager as Base,
    Zend\ServiceManager\ConfigInterface,
    Zend\ServiceManager\Exception\RuntimeException as ServiceManagerRuntimeException;

/**
 * VuFind Plugin Manager
 *
 * @category VuFind2
 * @package  ServiceManager
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 *
 * @SuppressWarnings(PHPMD.NumberOfChildren)
 */
abstract class AbstractPluginManager extends Base
{
    /**
     * Constructor
     *
     * Make sure table gateways are properly initialized.
     *
     * @param ConfigInterface $configuration Configuration settings (optional)
     */
    public function __construct(ConfigInterface $configuration = null)
    {
        parent::__construct($configuration);
        $this->addInitializer(
            ['VuFind\ServiceManager\Initializer', 'initPlugin'], false
        );
    }

    /**
     * Validate the plugin
     *
     * Checks that the filter loaded is either a valid callback or an instance
     * of FilterInterface.
     *
     * @param mixed $plugin Plugin to validate
     *
     * @throws ServiceManagerRuntimeException if invalid
     * @return void
     */
    public function validatePlugin($plugin)
    {
        $expectedInterface = $this->getExpectedInterface();
        if (!($plugin instanceof $expectedInterface)) {
            throw new ServiceManagerRuntimeException(
                'Plugin ' . get_class($plugin) . ' does not belong to '
                . $expectedInterface
            );
        }
    }

    /**
     * Return the name of the base class or interface that plug-ins must conform
     * to.
     *
     * @return string
     */
    abstract protected function getExpectedInterface();
}