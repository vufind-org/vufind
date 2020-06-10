<?php
/**
 * Back-compatibility class for ScheduledSearch/Notify
 *
 * PHP version 7
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Console
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace FinnaConsole\Command\Util;

/**
 * Back-compatibility class for ScheduledSearch/Notify
 *
 * @category VuFind
 * @package  Command
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class ScheduledAlerts extends \FinnaConsole\Command\ScheduledSearch\NotifyCommand
{
    /**
     * The name of the command (the part after "public/index.php")
     *
     * Used via reflection, don't remove even though it's the same as in parent class
     *
     * @var string
     */
    protected static $defaultName = 'util/scheduled_alerts';

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
