<?php

/**
 * Site status enum.
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
 * @package  Site
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\Site;

/**
 * Site status enum.
 *
 * @category VuFind
 * @package  Site
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
enum SiteStatus: string
{
    case BETA = 'beta';
    case DISABLED = 'disabled';
    case PRODUCTION = 'production';
    case TEST = 'test';

    /**
     * Return a translation key representing the status.
     *
     * @return string
     */
    public function getTranslationKey(): string
    {
        return 'site_status_' . $this->value;
    }

    /**
     * Return a CSS class representing the status.
     *
     * @return string
     */
    public function getCssClass(): string
    {
        return 'site-status-' . $this->value;
    }
}
