<?php

/**
 * Condition handler plugin manager
 *
 * PHP version 8
 *
 * Copyright (C) Hebis Verbundzentrale 2026.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Condition_Handler
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:condition_handlers Wiki
 */

namespace VuFind\Condition\Handler;

use Laminas\ServiceManager\Factory\InvokableFactory;
use VuFind\ServiceManager\Factory\AutowiringFactory;

/**
 * Condition handler plugin manager
 *
 * @category VuFind
 * @package  Condition_Handler
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:condition_handlers Wiki
 */
class PluginManager extends \VuFind\ServiceManager\AbstractPluginManager
{
    /**
     * Default plugin aliases.
     *
     * @var array
     */
    protected $aliases = [
        'date' => Date::class,
        'date_time' => DateTime::class,
        'env' => Env::class,
        'filetype' => Filetype::class,
        'logged_in' => LoggedIn::class,
        'string' => StringHandler::class,
        'time' => Time::class,
        'url_path' => UrlPath::class,
        'user_ip' => UserIp::class,
    ];

    /**
     * Default plugin factories.
     *
     * @var array
     */
    protected $factories = [
        Date::class => InvokableFactory::class,
        DateTime::class => InvokableFactory::class,
        Env::class => InvokableFactory::class,
        Filetype::class => InvokableFactory::class,
        LoggedIn::class => AutowiringFactory::class,
        StringHandler::class => InvokableFactory::class,
        Time::class => InvokableFactory::class,
        UrlPath::class => AutowiringFactory::class,
        UserIp::class => AutowiringFactory::class,
    ];

    /**
     * Return the name of the base class or interface that plug-ins must conform
     * to.
     *
     * @return string
     */
    protected function getExpectedInterface()
    {
        return ConditionHandlerInterface::class;
    }
}
