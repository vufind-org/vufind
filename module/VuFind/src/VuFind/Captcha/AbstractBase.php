<?php

/**
 * Abstract base CAPTCHA
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2020.
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
 * @package  CAPTCHA
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Captcha;

use Laminas\Mvc\Controller\Plugin\Params;

use function get_class;

/**
 * Abstract base CAPTCHA
 *
 * @category VuFind
 * @package  CAPTCHA
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
abstract class AbstractBase
{
    /**
     * Get list of URLs with JS dependencies to load for the active CAPTCHA type.
     *
     * @return array
     */
    public function getJsIncludes(): array
    {
        return [];
    }

    /**
     * Get ID for current CAPTCHA (to use e.g. in HTML forms)
     *
     * @return string
     */
    public function getId(): string
    {
        return preg_replace('"^.*\\\\"', '', get_class($this));
    }

    /**
     * Get any error message after a failed captcha verification. The message can be
     * displayed to the user.
     *
     * @return string
     */
    public function getErrorMessage(): string
    {
        return 'captcha_not_passed';
    }

    /**
     * Pull the captcha field from controller params and check them for accuracy
     *
     * @param Params $params Controller params
     *
     * @return bool
     */
    abstract public function verify(Params $params): bool;
}
