<?php

/**
 * Trait to import secret from file rather than a hardcoded config
 *
 * PHP version 8
 *
 * Copyright (C) Michigan State University 2024.
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
 * @author   Robby ROUDON <roudonro@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Config\Feature;

use Laminas\Config\Config;

/**
 * Trait to import secret from file rather than a hardcoded config
 *
 * @category VuFind
 * @package  Config
 * @author   Robby ROUDON <roudonro@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
trait SecretTrait
{
    /**
     * @param Config|array|null $config The config to read from
     * @param string            $key    The key to retrieve
     * @param bool              $trim   (default: false) trim the input config
     *
     * @return string|null
     */
    protected function getSecretFromConfigOrSecretFile(Config|array|null $config, string $key, bool $trim = false): ?string
    {
        if (!$config) {
            return null;
        }
        if ($config instanceof Config) {
            $config = $config->toArray();
        }
        if ($config[$key . '_file']) {
            $value = file_get_contents($config[$key . '_file']);
        } else {
            $value = $config[$key];
        }
        return ($trim ? trim($value) : $value) ?? null;
    }
}
