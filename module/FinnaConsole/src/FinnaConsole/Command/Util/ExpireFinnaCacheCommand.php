<?php

/**
 * Console command: expire Finna cache records.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2020.
 * Copyright (C) The National Library of Finland 2020.
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
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace FinnaConsole\Command\Util;

use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Console command: expire Finna cache records.
 *
 * @category VuFind
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
#[AsCommand(
    name: 'util/expire_finna_cache'
)]
class ExpireFinnaCacheCommand extends \VuFindConsole\Command\Util\AbstractExpireCommand
{
    /**
     * Help description for the command.
     *
     * @var string
     */
    protected $commandDescription = 'Database finna_cache table cleanup';

    /**
     * Label to use for rows in help messages.
     *
     * @var string
     */
    protected $rowLabel = 'Finna cache records';
}
