<?php

/**
 * Abstract base AJAX handler
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018.
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
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\AjaxHandler;

use VuFind\Session\Settings as SessionSettings;

/**
 * Abstract base AJAX handler
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
abstract class AbstractBase implements AjaxHandlerInterface
{
    /**
     * Session settings
     *
     * @var SessionSettings
     */
    protected $sessionSettings = null;

    /**
     * Prevent session writes -- this is designed to be called prior to time-
     * consuming AJAX operations to help reduce the odds of a timing-related bug
     * that causes the wrong version of session data to be written to disk (see
     * VUFIND-716 for more details).
     *
     * @return void
     */
    protected function disableSessionWrites()
    {
        if (null === $this->sessionSettings) {
            throw new \Exception('Session settings object missing.');
        }
        $this->sessionSettings->disableWrite();
    }

    /**
     * Format a response array.
     *
     * @param mixed $response Response data
     * @param int   $httpCode HTTP status code (omit for default)
     *
     * @return array
     */
    protected function formatResponse($response, $httpCode = null)
    {
        $arr = [$response];
        if ($httpCode !== null) {
            $arr[] = $httpCode;
        }
        return $arr;
    }
}
