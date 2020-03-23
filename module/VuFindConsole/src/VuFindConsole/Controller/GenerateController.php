<?php
/**
 * CLI Controller Module (language tools)
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace VuFindConsole\Controller;

use Laminas\Console\Console;

/**
 * This controller handles various command-line tools for dealing with language files
 *
 * @category VuFind
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class GenerateController extends AbstractBase
{
    /**
     * Create a custom theme from the template, configure.
     *
     * @return \Laminas\Console\Response
     */
    public function themeAction()
    {
        // Validate command line argument:
        $request = $this->getRequest();
        $name = $request->getParam('themename');
        if (empty($name)) {
            Console::writeLine("\tNo themename found, using \"custom\"");
            $name = 'custom';
        }

        // Use the theme generator to create and configure the theme:
        $generator = $this->serviceLocator->get(\VuFindTheme\ThemeGenerator::class);
        if (!$generator->generate($name)
            || !$generator->configure($this->getConfig(), $name)
        ) {
            Console::writeLine($generator->getLastError());
            return $this->getFailureResponse();
        }
        Console::writeLine("\tFinished.");
        return $this->getSuccessResponse();
    }

    /**
     * Create a custom theme from the template.
     *
     * @return \Laminas\Console\Response
     */
    public function thememixinAction()
    {
        // Validate command line argument:
        $request = $this->getRequest();
        $name = $request->getParam('name');
        if (empty($name)) {
            Console::writeLine("\tNo mixin name found, using \"custom\"");
            $name = 'custom';
        }

        // Use the theme generator to create and configure the theme:
        $generator = $this->serviceLocator->get(\VuFindTheme\MixinGenerator::class);
        if (!$generator->generate($name)) {
            Console::writeLine($generator->getLastError());
            return $this->getFailureResponse();
        }
        Console::writeLine(
            "\tFinished. Add to your theme.config.php 'mixins' setting to activate."
        );
        return $this->getSuccessResponse();
    }
}
