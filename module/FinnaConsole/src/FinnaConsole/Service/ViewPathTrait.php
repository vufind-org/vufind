<?php
/**
 * Trait for view path handling.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2016-2020.
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
 * @package  Service
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace FinnaConsole\Service;

/**
 * Trait for view path handling.
 *
 * @category VuFind
 * @package  Service
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
trait ViewPathTrait
{
    /**
     * Get the default view path
     *
     * @return string
     */
    protected function getDefaultViewPath()
    {
        return 'default';
    }

    /**
     * Check if the given view path points to the default view
     *
     * @param string $path View path
     *
     * @return bool
     */
    protected function isDefaultViewPath($path)
    {
        if (strpos($path, '/') >= 0) {
            $parts = explode('/', $path);
            $path = array_pop($parts);
        }
        return $path === $this->getDefaultViewPath();
    }

    /**
     * Resolve path to the view directory.
     *
     * @param string $institution Institution
     * @param string $view        View
     *
     * @return string|boolean view path or false on error
     */
    protected function resolveViewPath($institution, $view = false)
    {
        if (!$view) {
            $view = $this->getDefaultViewPath();
            if (isset($this->datasourceConfig[$institution]['mainView'])) {
                list($institution, $view)
                    = explode(
                        '/',
                        $this->datasourceConfig[$institution]['mainView'], 2
                    );
            }
        }
        $path = "{$this->viewBaseDir}/$institution/$view";

        // Assume that view is functional if index.php exists.
        if (!is_file("$path/public/index.php")) {
            $this->err("Could not resolve view path for $institution/$view", '=');
            return false;
        }

        return $path;
    }
}
