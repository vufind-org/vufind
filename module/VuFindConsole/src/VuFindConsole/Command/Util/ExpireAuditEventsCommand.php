<?php

/**
 * Console command: expire audit events.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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

namespace VuFindConsole\Command\Util;

use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Console command: expire audit events.
 *
 * @category VuFind
 * @package  Console
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
#[AsCommand(
    name: 'util/expire_audit_events'
)]
class ExpireAuditEventsCommand extends AbstractExpireCommand
{
    /**
     * Minimum legal age (in days) of rows to delete or null if age isn't applicable.
     *
     * @var int|float|null
     */
    protected $minAge = 1;

    /**
     * Default age of rows (in days) to delete. $minAge is used if $defaultAge is
     * null.
     *
     * @var int|float|null
     */
    protected $defaultAge = 365;

    /**
     * Help description for the command.
     *
     * @var string
     */
    protected $commandDescription = 'Database audit_event table cleanup';

    /**
     * Label to use for rows in help messages.
     *
     * @var string
     */
    protected $rowLabel = 'audit events';
}
