<?php
/**
 * Abstract base class for commands that take relative paths as parameters.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2020.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFindConsole\Command;

use Symfony\Component\Console\Command\Command;

/**
 * Abstract base class for commands that take relative paths as parameters.
 *
 * @category VuFind
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
abstract class RelativeFileAwareCommand extends Command
{
    /**
     * Constructor
     *
     * @param string|null $name The name of the command; passing null means it
     * must be set in configure()
     */
    public function __construct($name = null)
    {
        // Switch the context back to the original working directory so that
        // relative paths work as expected. (This constant is set in
        // public/index.php)
        if (defined('ORIGINAL_WORKING_DIRECTORY')) {
            chdir(ORIGINAL_WORKING_DIRECTORY);
        }

        parent::__construct($name);
    }
}
