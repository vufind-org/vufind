<?php
/**
 * Compile Controller Module
 *
 * PHP version 5
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
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace VuFindConsole\Controller;
use Zend\Console\Console;

/**
 * This controller handles the command-line tool for compiling themes.
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class CompileController extends AbstractBase
{
    /**
     * Compile theme action.
     *
     * @return mixed
     */
    public function themeAction()
    {
        $request = $this->getRequest();
        $source = $request->getParam('source');
        if (empty($source)) {
            Console::writeLine(
                'Usage: ' . $request->getScriptName()
                . ' compile theme [--force] SOURCE [TARGET]'
            );
            Console::writeLine("\tSOURCE - the source theme to compile (required)");
            Console::writeLine(
                "\tTARGET - the target name for the compiled theme "
                . '(optional; defaults to SOURCE_compiled)'
            );
            Console::writeLine(
                "(If TARGET exists, it will only be overwritten when --force is set)"
            );
            return $this->getFailureResponse();
        }
        $target = $request->getParam('target');
        if (empty($target)) {
            $target = "{$source}_compiled";
        }
        $compiler = $this->serviceLocator->get('VuFindTheme\ThemeCompiler');
        if (!$compiler->compile($source, $target, $request->getParam('force'))) {
            Console::writeLine($compiler->getLastError());
            return $this->getFailureResponse();
        }
        Console::writeLine('Success.');
        return $this->getSuccessResponse();
    }
}
