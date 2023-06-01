<?php

/**
 * Interface shared by theme and mixin generator classes.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2017.
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
 * @package  Theme
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFindTheme;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Interface shared by theme and mixin generator classes.
 *
 * @category VuFind
 * @package  Theme
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
interface GeneratorInterface
{
    /**
     * Generate a new resource.
     *
     * @param string $name Name of resource to generate.
     *
     * @return bool
     */
    public function generate($name);

    /**
     * Get last error message.
     *
     * @return string
     */
    public function getLastError();

    /**
     * Set the output interface. Implements a fluent interface.
     *
     * @param OutputInterface $output Output interface
     *
     * @return mixed
     */
    public function setOutputInterface(OutputInterface $output);
}
