<?php

/**
 * Class to generate a new mixin from a template.
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
 * @author   Chris Hallberg <challber@villanova.edu>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFindTheme;

/**
 * Class to generate a new mixin from a template.
 *
 * @category VuFind
 * @package  Theme
 * @author   Chris Hallberg <challber@villanova.edu>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class MixinGenerator extends AbstractThemeUtility implements GeneratorInterface
{
    use \VuFindConsole\ConsoleOutputTrait;

    /**
     * Generate a new mixin from a template.
     *
     * @param string $name     Name of mixin to generate.
     * @param string $template Name of template mixin directory
     *
     * @return bool
     */
    public function generate($name, $template = 'local_mixin_example')
    {
        // Check for existing theme
        $baseDir = $this->info->getBaseDir() . '/';
        if (realpath($baseDir . $name)) {
            return $this->setLastError('Mixin "' . $name . '" already exists');
        }
        $this->writeln('Creating new mixin: "' . $name . '"');
        $source = $baseDir . $template;
        $dest = $baseDir . $name;
        $this->writeln("\tCopying $template");
        $this->writeln("\t\tFrom: " . $source);
        $this->writeln("\t\tTo: " . $dest);
        return $this->copyDir($source, $dest);
    }
}
