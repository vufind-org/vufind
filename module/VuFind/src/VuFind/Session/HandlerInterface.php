<?php

/**
 * Session handler interface
 *
 * Copyright (C) Villanova University 2018,
 *               Leipzig University Library <info@ub.uni-leipzig.de> 2018.
 *
 * PHP version 8
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

use Laminas\Session\SaveHandler\SaveHandlerInterface;
use VuFind\Db\Table\DbTableAwareInterface;

/**
 * Session handler interface
 *
 * @category VuFind
 * @package  Session_Handlers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Sebastian Kehr <kehr@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:session_handlers Wiki
 */
interface HandlerInterface extends SaveHandlerInterface, DbTableAwareInterface
{
    /**
     * Enable session writing (default)
     *
     * @return void
     */
    public function enableWrites();

    /**
     * Disable session writing, i.e. make it read-only
     *
     * @return void
     */
    public function disableWrites();
}
