<?php

/**
 * ILS driver plugin manager
 *
 * PHP version 8
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

use Laminas\ServiceManager\Factory\InvokableFactory;

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
        'aleph' => Aleph::class,
        'alma' => Alma::class,
        'amicus' => Amicus::class,
        'composeddriver' => ComposedDriver::class,
        'daia' => DAIA::class,
        'demo' => Demo::class,
        'evergreen' => Evergreen::class,
        'folio' => Folio::class,
        'genieplus' => GeniePlus::class,
        'horizon' => Horizon::class,
        'horizonxmlapi' => HorizonXMLAPI::class,
        'innovative' => Innovative::class,
        'koha' => Koha::class,
        'kohailsdi' => KohaILSDI::class,
        'koharest' => KohaRest::class,
        'multibackend' => MultiBackend::class,
        'newgenlib' => NewGenLib::class,
        'noils' => NoILS::class,
        'paia' => PAIA::class,
        'polaris' => Polaris::class,
        'sample' => Sample::class,
        'sierrarest' => SierraRest::class,
        'symphony' => Symphony::class,
        'unicorn' => Unicorn::class,
        'virtua' => Virtua::class,
        'voyager' => Voyager::class,
        'voyagerrestful' => VoyagerRestful::class,
        'xcncip2' => XCNCIP2::class,
    ];

    /**
     * Default plugin factories.
     *
     * @var array
     */
    protected $factories = [
        Aleph::class => AlephFactory::class,
        Alma::class => DriverWithDateConverterFactory::class,
        Amicus::class => InvokableFactory::class,
        ComposedDriver::class => AbstractMultiDriverFactory::class,
        DAIA::class => DriverWithDateConverterFactory::class,
        Demo::class => DemoFactory::class,
        Evergreen::class => DriverWithDateConverterFactory::class,
        Folio::class => FolioFactory::class,
        GeniePlus::class => GeniePlusFactory::class,
        Horizon::class => DriverWithDateConverterFactory::class,
        HorizonXMLAPI::class => DriverWithDateConverterFactory::class,
        Innovative::class => InvokableFactory::class,
        Koha::class => DriverWithDateConverterFactory::class,
        KohaILSDI::class => DriverWithDateConverterFactory::class,
        KohaRest::class => KohaRestFactory::class,
        MultiBackend::class => MultiBackendFactory::class,
        NewGenLib::class => InvokableFactory::class,
        NoILS::class => NoILSFactory::class,
        PAIA::class => PAIAFactory::class,
        Polaris::class => InvokableFactory::class,
        Sample::class => InvokableFactory::class,
        SierraRest::class => SierraRestFactory::class,
        Symphony::class => SymphonyFactory::class,
        Unicorn::class => UnicornFactory::class,
        Virtua::class => InvokableFactory::class,
        Voyager::class => DriverWithDateConverterFactory::class,
        VoyagerRestful::class => VoyagerRestfulFactory::class,
        XCNCIP2::class => XCNCIP2Factory::class,
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
    public function __construct(
        $configOrContainerInstance = null,
        array $v3config = []
    ) {
        $this->addAbstractFactory(PluginFactory::class);
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
        return DriverInterface::class;
    }
}
