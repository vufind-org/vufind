<?php

/**
 * Console command: expire persistent login tokens.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2023.
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
 * @author   Jaro Ravila <jaro.ravila@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFindConsole\Command\Util;

/**
 * Console command: expire persistent login tokens.
 *
 * @category VuFind
 * @package  Console
 * @author   Jaro Ravila <jaro.ravila@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ExpireLoginTokensCommand extends AbstractExpireCommand
{
    /**
     * Help description for the command.
     *
     * @var string
     */
    protected $commandDescription = 'Database login_token table cleanup';

    /**
     * Label to use for rows in help messages.
     *
     * @var string
     */
    protected $rowLabel = 'login tokens';

    /**
     * The name of the command (the part after "public/index.php")
     *
     * @var string
     */
    protected static $defaultName = 'util/expire_login_tokens';
}
