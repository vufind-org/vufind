<?php
/**
 * Auth handler plugin manager
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
 * @package  Authentication
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\Auth;

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
        'almadatabase' => 'VuFind\Auth\AlmaDatabase',
        'cas' => 'VuFind\Auth\CAS',
        'choiceauth' => 'VuFind\Auth\ChoiceAuth',
        'database' => 'VuFind\Auth\Database',
        'facebook' => 'VuFind\Auth\Facebook',
        'ils' => 'VuFind\Auth\ILS',
        'ldap' => 'VuFind\Auth\LDAP',
        'multiauth' => 'VuFind\Auth\MultiAuth',
        'multiils' => 'VuFind\Auth\MultiILS',
        'shibboleth' => 'VuFind\Auth\Shibboleth',
        'sip2' => 'VuFind\Auth\SIP2',
        // for legacy 1.x compatibility
        'db' => 'VuFind\Auth\Database',
        'sip' => 'VuFind\Auth\SIP2',
    ];

    /**
     * Default plugin factories.
     *
     * @var array
     */
    protected $factories = [
        'VuFind\Auth\AlmaDatabase' => 'VuFind\Auth\Factory::getAlmaDatabase',
        'VuFind\Auth\CAS' => 'Zend\ServiceManager\Factory\InvokableFactory',
        'VuFind\Auth\ChoiceAuth' => 'VuFind\Auth\Factory::getChoiceAuth',
        'VuFind\Auth\Database' => 'Zend\ServiceManager\Factory\InvokableFactory',
        'VuFind\Auth\Facebook' => 'VuFind\Auth\Factory::getFacebook',
        'VuFind\Auth\ILS' => 'VuFind\Auth\Factory::getILS',
        'VuFind\Auth\LDAP' => 'Zend\ServiceManager\Factory\InvokableFactory',
        'VuFind\Auth\MultiAuth' => 'VuFind\Auth\Factory::getMultiAuth',
        'VuFind\Auth\MultiILS' => 'VuFind\Auth\Factory::getMultiILS',
        'VuFind\Auth\Shibboleth' => 'VuFind\Auth\Factory::getShibboleth',
        'VuFind\Auth\SIP2' => 'Zend\ServiceManager\Factory\InvokableFactory',
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
        $this->addAbstractFactory('VuFind\Auth\PluginFactory');
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
        return 'VuFind\Auth\AbstractBase';
    }
}
