<?php
/**
 * Resolver driver plugin manager
 *
 * PHP version 7
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Resolver_Drivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:link_resolver_drivers Wiki
 */
namespace VuFind\Resolver\Driver;

/**
 * Resolver driver plugin manager
 *
 * @category VuFind
 * @package  Resolver_Drivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:link_resolver_drivers Wiki
 */
class PluginManager extends \VuFind\ServiceManager\AbstractPluginManager
{
    /**
     * Default plugin aliases.
     *
     * @var array
     */
    protected $aliases = [
        '360link' => 'VuFind\Resolver\Driver\Threesixtylink',
        'demo' => 'VuFind\Resolver\Driver\Demo',
        'ezb' => 'VuFind\Resolver\Driver\Ezb',
        'sfx' => 'VuFind\Resolver\Driver\Sfx',
        'redi' => 'VuFind\Resolver\Driver\Redi',
        'threesixtylink' => 'VuFind\Resolver\Driver\Threesixtylink',
    ];

    /**
     * Default plugin factories.
     *
     * @var array
     */
    protected $factories = [
        'VuFind\Resolver\Driver\Threesixtylink' =>
            'VuFind\Resolver\Driver\DriverWithHttpClientFactory',
        'VuFind\Resolver\Driver\Demo' =>
            'Zend\ServiceManager\Factory\InvokableFactory',
        'VuFind\Resolver\Driver\Ezb' =>
            'VuFind\Resolver\Driver\DriverWithHttpClientFactory',
        'VuFind\Resolver\Driver\Sfx' =>
            'VuFind\Resolver\Driver\DriverWithHttpClientFactory',
        'VuFind\Resolver\Driver\Redi' =>
            'VuFind\Resolver\Driver\DriverWithHttpClientFactory',
    ];

    /**
     * Constructor
     *
     * Make sure plugins are properly initialized.
     *
     * @param mixed $configOrContainerInstance Configuration or container instance
     * @param array $v3config                  If $configOrContainerInstance is a
     * container, this value will be passed to the parent constructor.
     */
    public function __construct($configOrContainerInstance = null,
        array $v3config = []
    ) {
        $this->addAbstractFactory('VuFind\Resolver\Driver\PluginFactory');
        parent::__construct($configOrContainerInstance, $v3config);
    }

    /**
     * Return the name of the base class or interface that plug-ins must conform
     * to.
     *
     * @return string
     */
    protected function getExpectedInterface()
    {
        return 'VuFind\Resolver\Driver\DriverInterface';
    }
}
