<?php
/**
 * ILS driver plugin manager
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
 * @package  ILS_Drivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
namespace VuFind\ILS\Driver;

/**
 * ILS driver plugin manager
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class PluginManager extends \VuFind\ServiceManager\AbstractPluginManager
{
    /**
     * Default plugin aliases.
     *
     * @var array
     */
    protected $aliases = [
        'aleph' => 'VuFind\ILS\Driver\Aleph',
        'alma' => 'VuFind\ILS\Driver\Alma',
        'amicus' => 'VuFind\ILS\Driver\Amicus',
        'daia' => 'VuFind\ILS\Driver\DAIA',
        'demo' => 'VuFind\ILS\Driver\Demo',
        'evergreen' => 'VuFind\ILS\Driver\Evergreen',
        'horizon' => 'VuFind\ILS\Driver\Horizon',
        'horizonxmlapi' => 'VuFind\ILS\Driver\HorizonXMLAPI',
        'innovative' => 'VuFind\ILS\Driver\Innovative',
        'koha' => 'VuFind\ILS\Driver\Koha',
        'kohailsdi' => 'VuFind\ILS\Driver\KohaILSDI',
        'lbs4' => 'VuFind\ILS\Driver\LBS4',
        'multibackend' => 'VuFind\ILS\Driver\MultiBackend',
        'newgenlib' => 'VuFind\ILS\Driver\NewGenLib',
        'noils' => 'VuFind\ILS\Driver\NoILS',
        'paia' => 'VuFind\ILS\Driver\PAIA',
        'polaris' => 'VuFind\ILS\Driver\Polaris',
        'sample' => 'VuFind\ILS\Driver\Sample',
        'sierra' => 'VuFind\ILS\Driver\Sierra',
        'sierrarest' => 'VuFind\ILS\Driver\SierraRest',
        'symphony' => 'VuFind\ILS\Driver\Symphony',
        'unicorn' => 'VuFind\ILS\Driver\Unicorn',
        'virtua' => 'VuFind\ILS\Driver\Virtua',
        'voyager' => 'VuFind\ILS\Driver\Voyager',
        'voyagerrestful' => 'VuFind\ILS\Driver\VoyagerRestful',
        'xcncip2' => 'VuFind\ILS\Driver\XCNCIP2',
    ];

    /**
     * Default plugin factories.
     *
     * @var array
     */
    protected $factories = [
        'VuFind\ILS\Driver\Aleph' => 'VuFind\ILS\Driver\AlephFactory',
        'VuFind\ILS\Driver\Alma' => 'VuFind\ILS\Driver\AlmaFactory',
        'VuFind\ILS\Driver\Amicus' => 'Zend\ServiceManager\Factory\InvokableFactory',
        'VuFind\ILS\Driver\DAIA' =>
            'VuFind\ILS\Driver\DriverWithDateConverterFactory',
        'VuFind\ILS\Driver\Demo' => 'VuFind\ILS\Driver\DemoFactory',
        'VuFind\ILS\Driver\Evergreen' =>
            'Zend\ServiceManager\Factory\InvokableFactory',
        'VuFind\ILS\Driver\Horizon' =>
            'VuFind\ILS\Driver\DriverWithDateConverterFactory',
        'VuFind\ILS\Driver\HorizonXMLAPI' =>
            'VuFind\ILS\Driver\DriverWithDateConverterFactory',
        'VuFind\ILS\Driver\Innovative' =>
            'Zend\ServiceManager\Factory\InvokableFactory',
        'VuFind\ILS\Driver\Koha' =>
            'VuFind\ILS\Driver\DriverWithDateConverterFactory',
        'VuFind\ILS\Driver\KohaILSDI' =>
            'VuFind\ILS\Driver\DriverWithDateConverterFactory',
        'VuFind\ILS\Driver\LBS4' =>
            'VuFind\ILS\Driver\DriverWithDateConverterFactory',
        'VuFind\ILS\Driver\MultiBackend' => 'VuFind\ILS\Driver\MultiBackendFactory',
        'VuFind\ILS\Driver\NewGenLib' =>
            'Zend\ServiceManager\Factory\InvokableFactory',
        'VuFind\ILS\Driver\NoILS' => 'VuFind\ILS\Driver\NoILSFactory',
        'VuFind\ILS\Driver\PAIA' => 'VuFind\ILS\Driver\PAIAFactory',
        'VuFind\ILS\Driver\Polaris' =>
            'Zend\ServiceManager\Factory\InvokableFactory',
        'VuFind\ILS\Driver\Sample' => 'Zend\ServiceManager\Factory\InvokableFactory',
        'VuFind\ILS\Driver\Sierra' => 'Zend\ServiceManager\Factory\InvokableFactory',
        'VuFind\ILS\Driver\SierraRest' => 'VuFind\ILS\Driver\SierraRestFactory',
        'VuFind\ILS\Driver\Symphony' => 'VuFind\ILS\Driver\SymphonyFactory',
        'VuFind\ILS\Driver\Unicorn' =>
            'VuFind\ILS\Driver\DriverWithDateConverterFactory',
        'VuFind\ILS\Driver\Virtua' => 'Zend\ServiceManager\Factory\InvokableFactory',
        'VuFind\ILS\Driver\Voyager' =>
            'VuFind\ILS\Driver\DriverWithDateConverterFactory',
        'VuFind\ILS\Driver\VoyagerRestful' =>
            'VuFind\ILS\Driver\VoyagerRestfulFactory',
        'VuFind\ILS\Driver\XCNCIP2' =>
            'Zend\ServiceManager\Factory\InvokableFactory',
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
        $this->addAbstractFactory('VuFind\ILS\Driver\PluginFactory');
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
        return 'VuFind\ILS\Driver\DriverInterface';
    }
}
