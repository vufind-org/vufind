<?php

/**
 * Site status helper class.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024.
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
 * @package  View_Helpers
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\View\Helper\Root;

use Finna\Site\SiteStatus as SiteStatusEnum;

/**
 * Site status helper class.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class SiteStatus extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Constructor.
     *
     * @param array $config Configuration
     */
    public function __construct(protected array $config)
    {
    }

    /**
     * Return site status.
     *
     * @return SiteStatusEnum
     */
    public function __invoke(): SiteStatusEnum
    {
        return SiteStatusEnum::tryFrom($this->config['Finna']['site_status'] ?? '')
            ?? SiteStatusEnum::PRODUCTION;
    }
}
