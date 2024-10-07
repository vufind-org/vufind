<?php

/**
 * Dir Locations Resolver
 *
 * PHP version 8
 *
 * Copyright (C) Hebis Verbundzentrale 2024.
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
 * @package  Config
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Config;

use Laminas\Config\Config;
use VuFind\Config\Feature\IniReaderTrait;

use function defined;
use function in_array;
use function strlen;

/**
 * Dir Locations Resolver
 *
 * @category VuFind
 * @package  Config
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class DirLocationsResolver
{
    use IniReaderTrait;

    /**
     * Dir locations stack.
     *
     * @var array
     */
    protected array $dirLocationsStack;

    /**
     * Get the paths and dirLocation.ini configs of the local dirs in the stack.
     *
     * @return array
     */
    public function getDirLocationsStack(): array
    {
        if (!isset($this->dirLocationsStack)) {
            $this->dirLocationsStack = $this->resolveDirLocationsStack();
        }
        return $this->dirLocationsStack;
    }

    /**
     * Resolve the paths and dirLocation.ini configs of the local dirs in the stack.
     *
     * @return array
     */
    protected function resolveDirLocationsStack(): array
    {
        $localDirs = [];
        $currentDir = defined('LOCAL_OVERRIDE_DIR')
            && strlen(trim(LOCAL_OVERRIDE_DIR)) > 0
            ? LOCAL_OVERRIDE_DIR : '';
        while (!empty($currentDir)) {
            // check if the directory exists
            if (!($canonicalizedCurrentDir = realpath($currentDir))) {
                trigger_error('Configured local directory does not exist: ' . $currentDir, E_USER_WARNING);
                break;
            }
            $currentDir = $canonicalizedCurrentDir;

            // check if the current directory was already included in the stack to avoid infinite loops
            if (in_array($currentDir, array_column($localDirs, 'dir'))) {
                trigger_error('Current directory was already included in the stack: ' . $currentDir, E_USER_WARNING);
                break;
            }

            // loading DirLocations.ini of currentDir
            $systemConfigFile = $currentDir . '/DirLocations.ini';
            $systemConfig = new Config(
                file_exists($systemConfigFile)
                    ? $this->getIniReader()->fromFile($systemConfigFile)
                    : []
            );

            // adding directory to the stack
            array_unshift(
                $localDirs,
                [
                    'dir' => $currentDir,
                    'dirLocationConfig' => $systemConfig,
                ]
            );

            // If there's a parent, set it as the current directory for the next loop iteration:
            if (!empty($systemConfig['Parent_Dir']['path'])) {
                $isRelative = $systemConfig['Parent_Dir']['is_relative_path'] ?? false;
                $parentDir = $systemConfig['Parent_Dir']['path'];
                $currentDir = $isRelative ? $currentDir . '/' . $parentDir : $parentDir;
            } else {
                $currentDir = '';
            }
        }
        return $localDirs;
    }
}
