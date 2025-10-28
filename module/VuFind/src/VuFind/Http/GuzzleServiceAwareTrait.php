<?php

/**
 * Trait for classes that need GuzzleService
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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
 * @package  Http
 * @author   Sambhav Pokharel <sambhavpokharel@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development
 */

namespace VuFind\Http;

/**
 * Trait for classes that need GuzzleService
 *
 * @category VuFind
 * @package  Http
 * @author   Sambhav Pokharel <sambhavpokharel@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development
 */
trait GuzzleServiceAwareTrait
{
    /**
     * GuzzleService
     *
     * @var ?GuzzleService
     */
    protected $guzzleService = null;

    /**
     * Set the GuzzleService
     *
     * @param GuzzleService $service GuzzleService
     *
     * @return void
     */
    public function setGuzzleService(GuzzleService $service): void
    {
        $this->guzzleService = $service;
    }

    /**
     * Get the GuzzleService
     *
     * @return ?GuzzleService
     */
    public function getGuzzleService(): ?GuzzleService
    {
        return $this->guzzleService;
    }
}
