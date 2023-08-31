<?php

/**
 * Locale Detector Strategy for VuFind POST Parameter
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018,
 *               Leipzig University Library <info@ub.uni-leipzig.de> 2018.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Sebastian Kehr <kehr@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\I18n\Locale;

use SlmLocale\LocaleEvent;
use SlmLocale\Strategy\AbstractStrategy;

use function in_array;

/**
 * Locale Detector Strategy for VuFind POST Parameter
 *
 * @category VuFind
 * @package  I18n\Locale
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Sebastian Kehr <kehr@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class LocaleDetectorParamStrategy extends AbstractStrategy
{
    public const PARAM_NAME = 'mylang';

    /**
     * Attempt to detect the locale from a POST parameter.
     *
     * @param LocaleEvent $event Event
     *
     * @return ?string
     */
    public function detect(LocaleEvent $event)
    {
        $request = $event->getRequest();
        $locale = $request->getPost(self::PARAM_NAME);
        if (in_array($locale, $event->getSupported())) {
            return $locale;
        }
    }
}
