<?php
/**
 * VuFind Theme Support Methods
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Theme
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Theme;
use Zend\Session\Container as SessionContainer;

/**
 * VuFind Theme Support Methods
 *
 * @category VuFind2
 * @package  Theme
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Tools
{
    /**
     * Base directory for theme files
     *
     * @var string
     */
    protected $baseDir;

    /**
     * Resource container
     *
     * @var ResourceContainer
     */
    protected $resourceContainer;

    /**
     * Session (persistence) container
     *
     * @var SessionContainer
     */
    protected $sessionContainer;

    /**
     * Constructor
     *
     * @param string $baseDir Base directory for theme files.
     */
    public function __construct($baseDir)
    {
        $this->baseDir = $baseDir;
        $this->resourceContainer = new ResourceContainer();
        $this->sessionContainer = new SessionContainer('Theme');
    }

    /**
     * Get the base directory for themes.
     *
     * @return string
     */
    public function getBaseDir()
    {
        return $this->baseDir;
    }

    /**
     * Get the container used for handling public resources for themes
     * (CSS, JS, etc.)
     *
     * @return ResourceContainer
     */
    public function getResourceContainer()
    {
        return $this->resourceContainer;
    }

    /**
     * Get the container used for persisting theme-related settings from
     * page to page.
     *
     * @return SessionContainer
     */
    public function getPersistenceContainer()
    {
        return $this->sessionContainer;
    }

    /**
     * Search the themes for a particular file.  If it exists, return the
     * first matching theme name; otherwise, return null.
     *
     * @param string|array $relativePath Relative path (or array of paths) to
     * search within themes
     * @param bool         $returnFile   If true, return full file path instead
     * of theme name
     *
     * @return string
     */
    public function findContainingTheme($relativePath, $returnFile = false)
    {
        $session = $this->getPersistenceContainer();
        $basePath = $this->getBaseDir();
        $allPaths = is_array($relativePath)
            ? $relativePath : array($relativePath);

        $currentTheme = $session->currentTheme;

        while (!empty($currentTheme)) {
            foreach ($allPaths as $currentPath) {
                $file = "$basePath/$currentTheme/$currentPath";
                if (file_exists($file)) {
                    return $returnFile ? $file : $currentTheme;
                }
            }
            $currentTheme = $session->allThemeInfo[$currentTheme]->extends;
        }

        return null;
    }
}