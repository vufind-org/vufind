<?php

/**
 * Auth handler plugin manager
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
 * @package  Authentication
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Auth;

use Laminas\ServiceManager\Factory\InvokableFactory;

/**
 * Auth handler plugin manager
 *
 * @category VuFind
 * @package  Authentication
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class PluginManager extends \VuFind\ServiceManager\AbstractPluginManager
{
    /**
     * Default plugin aliases.
     *
     * @var array
     */
    protected $aliases = [
        'almadatabase' => AlmaDatabase::class,
        'cas' => CAS::class,
        'choiceauth' => ChoiceAuth::class,
        'database' => Database::class,
        'email' => Email::class,
        'facebook' => Facebook::class,
        'ils' => ILS::class,
        'ldap' => LDAP::class,
        'multiauth' => MultiAuth::class,
        'multiils' => MultiILS::class,
        'shibboleth' => Shibboleth::class,
        'simulatedsso' => SimulatedSSO::class,
        'sip2' => SIP2::class,
        // for legacy 1.x compatibility
        'db' => Database::class,
        'sip' => SIP2::class,
    ];

    /**
     * Default plugin factories.
     *
     * @var array
     */
    protected $factories = [
        AlmaDatabase::class => ILSFactory::class,
        CAS::class => CASFactory::class,
        ChoiceAuth::class => ChoiceAuthFactory::class,
        Database::class => InvokableFactory::class,
        Email::class => EmailFactory::class,
        Facebook::class => FacebookFactory::class,
        ILS::class => ILSFactory::class,
        LDAP::class => LDAPFactory::class,
        MultiAuth::class => MultiAuthFactory::class,
        MultiILS::class => ILSFactory::class,
        Shibboleth::class => ShibbolethFactory::class,
        SimulatedSSO::class => SimulatedSSOFactory::class,
        SIP2::class => SIP2Factory::class,
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
        return AbstractBase::class;
    }
}
