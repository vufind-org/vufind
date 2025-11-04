<?php

/**
 * Back-compatibility class for ScheduledSearch/Notify
 *
 * PHP version 8
 *
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace FinnaConsole\Command\Util;

use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Back-compatibility class for ScheduledSearch/Notify
 *
 * @category VuFind
 * @package  Command
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
#[AsCommand(
    name: 'util/scheduled_alerts'
)]
class ScheduledAlerts extends \FinnaConsole\Command\ScheduledSearch\NotifyCommand
{
    /**
     * Configure the command.
     *
     * @return void
     */
    protected function configure()
    {
        parent::configure();
        $this->setDescription(
            'Scheduled Search Notifier (deprecated, use scheduledsearch/notify)'
        );
    }
}
