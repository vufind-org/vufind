<?php

/**
 * Locale Detector Strategy for language cookie
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022.
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
 * @package  I18n\Locale
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\I18n\Locale;

use SlmLocale\LocaleEvent;
use SlmLocale\Strategy\CookieStrategy;

/**
 * Locale Detector Strategy for language cookie
 *
 * @category VuFind
 * @package  I18n\Locale
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class LocaleDetectorCookieStrategy extends CookieStrategy
{
    /**
     * Event handler for the 'found' event
     *
     * @param LocaleEvent $event Event
     *
     * @return void
     */
    public function found(LocaleEvent $event)
    {
        // Setting a cookie is handled separately, so we don't need to do anything
        // here (see LocaleDetectorFactory).
    }
}
