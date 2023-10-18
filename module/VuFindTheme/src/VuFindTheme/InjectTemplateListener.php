<?php

/**
 * VuFind "Inject Template" Listener
 *
 * PHP version 8
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
 * @package  Theme
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFindTheme;

use function strlen;

/**
 * VuFind "Inject Template" Listener -- this extends the core MVC class to adjust
 * default template configurations to something more appropriate for VuFind.
 *
 * @category VuFind
 * @package  Theme
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Sebastian Kehr <kehr@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class InjectTemplateListener extends \Laminas\Mvc\View\Http\InjectTemplateListener
{
    /**
     * List of prefixes for theme files
     *
     * @var array $prefixes
     */
    protected $prefixes;

    /**
     * InjectTemplateListener constructor.
     *
     * @param string[] $prefixes List of prefixes for theme files
     */
    public function __construct(array $prefixes)
    {
        $this->prefixes = $prefixes;
    }

    /**
     * Get the prefixes recognized by the listener.
     *
     * @return string[]
     */
    public function getPrefixes(): array
    {
        return $this->prefixes;
    }

    /**
     * Inflect a name to a normalized value
     *
     * @param string $name Name to inflect
     *
     * @return string
     */
    protected function inflectName($name)
    {
        foreach ($this->prefixes as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return strtolower(substr($name, strlen($prefix)));
            }
        }
        return strtolower($name);
    }
}
