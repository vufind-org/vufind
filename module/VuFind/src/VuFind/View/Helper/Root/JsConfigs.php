<?php

/**
 * JsConfigs helper for passing configs to Javascript
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
 * @package  View_Helpers
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use Laminas\View\Helper\AbstractHelper;

/**
 * JsConfigs helper for passing configs to Javascript
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class JsConfigs extends AbstractHelper
{
    /**
     * Config
     *
     * @var array
     */
    protected array $config = [];

    /**
     * Add config.
     *
     * @param string $key   Config key
     * @param mixed  $value Config value
     *
     * @return void
     */
    public function add(string $key, mixed $value): void
    {
        $this->config[$key] = $value;
    }

    /**
     * Generate JSON from the internal array.
     *
     * @return string
     */
    public function getJSON(): string
    {
        if (empty($this->config)) {
            return '{}';
        }
        return json_encode($this->config);
    }
}
