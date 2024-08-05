<?php

/**
 * Theme generator command.
 *
 * PHP version 8
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

namespace VuFindConsole\Command\Generate;

use Laminas\Config\Config;
use Symfony\Component\Console\Attribute\AsCommand;
use VuFindTheme\ThemeGenerator;

/**
 * Theme generator command.
 *
 * @category VuFind
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
#[AsCommand(
    name: 'generate/theme'
)]
class ThemeCommand extends AbstractThemeCommand
{
    /**
     * Type of resource being generated (used in help messages)
     *
     * @var string
     */
    protected $type = 'theme';

    /**
     * Configuration from config.ini
     *
     * @var Config
     */
    protected $config;

    /**
     * Constructor
     *
     * @param ThemeGenerator $generator Generator to call
     * @param Config         $config    Configuration from config.ini
     * @param string|null    $name      The name of the command; passing null
     * means it must be set in configure()
     */
    public function __construct(
        ThemeGenerator $generator,
        Config $config,
        $name = null
    ) {
        $this->config = $config;
        parent::__construct($generator, $name);
    }

    /**
     * Run the generator.
     *
     * @param string $name Name of resource to generate
     *
     * @return bool
     */
    protected function generate($name)
    {
        return parent::generate($name)
            && $this->generator->configure($this->config, $name);
    }
}
