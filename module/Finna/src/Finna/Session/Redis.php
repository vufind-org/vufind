<?php

/**
 * Redis session handler extensions
 *
 * PHP version 8
 *
 * Coypright (C) The National Library of Finland 2023.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Session_Handlers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:session_handlers Wiki
 */

namespace Finna\Session;

/**
 * Redis session handler extensions
 *
 * @category VuFind
 * @package  Session_Handlers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:session_handlers Wiki
 */
class Redis extends \VuFind\Session\Redis
{
    use \Finna\Statistics\ReporterTrait;

    /**
     * Read function must return string value always to make save handler work as
     * expected. Return empty string if there is no data to read.
     *
     * Finna: Provides support for statistics
     *
     * @param string $sessId The session ID to read
     *
     * @return string
     */
    public function read($sessId): string
    {
        if ($result = parent::read($sessId)) {
            return $result;
        }
        $this->triggerStatsSessionStart((string)$sessId);
        return $result;
    }
}
