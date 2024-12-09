<?php

/**
 * Base class to enable sharing of common methods between SMS subclasses
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2009.
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
 * @package  SMS
 * @author   Ronan McHugh <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\SMS;

/**
 * Base class to enable sharing of common methods between SMS subclasses
 *
 * @category VuFind
 * @package  SMS
 * @author   Ronan McHugh <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
abstract class AbstractBase implements SMSInterface
{
    /**
     * SMS configuration
     *
     * @var \Laminas\Config\Config
     */
    protected $smsConfig;

    /**
     * Constructor
     *
     * @param \Laminas\Config\Config $config SMS configuration
     */
    public function __construct(\Laminas\Config\Config $config)
    {
        $this->smsConfig = $config;
    }

    /**
     * Filter bad characters from a phone number
     *
     * @param string $num Phone number to filter
     *
     * @return string
     */
    protected function filterPhoneNumber($num)
    {
        $filter = $this->smsConfig->General->filter ?? '-.() ';
        return str_replace(str_split($filter), '', $num);
    }

    /**
     * Get validation type for phone numbers
     *
     * @return string
     */
    public function getValidationType()
    {
        // Load setting from config; at present, only US is implemented in templates
        return $this->smsConfig->General->validation ?? 'US';
    }
}
