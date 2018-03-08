<?php
/**
 * Database table plugin manager
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
namespace Finna\Db\Table;

/**
 * Database table plugin manager
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class PluginManager extends \VuFind\Db\Table\PluginManager
{
    /**
     * Plugin aliases.
     *
     * @var array
     */
    protected $finnaAliases = [
        'changetracker' => 'VuFind\Db\Table\ChangeTracker',
        'commentsinappropriate' => 'Finna\Db\Table\CommentsInappropriate',
        'commentsrecord' => 'Finna\Db\Table\CommentsRecord',
        'duedatereminder' => 'Finna\Db\Table\DueDateReminder',
        'fee' => 'Finna\Db\Table\Fee',
        'finnacache' => 'Finna\Db\Table\FinnaCache',
        'transaction' => 'Finna\Db\Table\Transaction',
    ];

    /**
     * Plugin factories.
     *
     * @var array
     */
    protected $finnaFactories = [
        'VuFind\Db\Table\Comments' => 'Finna\Db\Table\GatewayFactory',
        'Finna\Db\Table\CommentsInappropriate' => 'Finna\Db\Table\GatewayFactory',
        'Finna\Db\Table\CommentsRecord' => 'Finna\Db\Table\GatewayFactory',
        'Finna\Db\Table\Fee' => 'Finna\Db\Table\GatewayFactory',
        'Finna\Db\Table\FinnaCache' => 'Finna\Db\Table\GatewayFactory',
        'Finna\Db\Table\Transaction' => 'Finna\Db\Table\ResourceFactory',
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
        $this->aliases = array_merge($this->aliases, $this->finnaAliases);
        $this->factories = array_merge($this->factories, $this->finnaFactories);
        $this->addAbstractFactory('Finna\Db\Table\PluginFactory');
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
        return 'VuFind\Db\Table\Gateway';
    }
}
