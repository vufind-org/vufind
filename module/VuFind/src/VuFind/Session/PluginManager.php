<?php

/**
 * Session handler plugin manager
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010,
 *               Leipzig University Library <info@ub.uni-leipzig.de> 2018.
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
 * @package  Session_Handlers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Sebastian Kehr <kehr@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:session_handlers Wiki
 */

namespace VuFind\Session;

/**
 * Session handler plugin manager
 *
 * @category VuFind
 * @package  Session_Handlers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Sebastian Kehr <kehr@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:session_handlers Wiki
 */
class PluginManager extends \VuFind\ServiceManager\AbstractPluginManager
{
    /**
     * Default plugin aliases.
     *
     * @var array
     */
    protected $aliases = [
        'database' => Database::class,
        'file' => File::class,
        'memcache' => Memcache::class,
        'redis' => Redis::class,
        // for legacy 1.x compatibility
        'filesession' => File::class,
        'memcachesession' => Memcache::class,
        'mysqlsession' => Database::class,
    ];

    /**
     * Default plugin factories.
     *
     * @var array
     */
    protected $factories = [
        Database::class => AbstractBaseFactory::class,
        File::class => AbstractBaseFactory::class,
        Memcache::class => AbstractBaseFactory::class,
        Redis::class => RedisFactory::class,
    ];

    /**
     * Default delegator factories.
     *
     * @var string[][]|\Laminas\ServiceManager\Factory\DelegatorFactoryInterface[][]
     */
    protected $delegators = [
        Database::class => [SecureDelegatorFactory::class],
        File::class => [SecureDelegatorFactory::class],
        Memcache::class => [SecureDelegatorFactory::class],
        Redis::class => [SecureDelegatorFactory::class],
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
        return HandlerInterface::class;
    }
}
