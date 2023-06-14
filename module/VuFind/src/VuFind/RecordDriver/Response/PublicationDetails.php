<?php

/**
 * Class encapsulating publication details.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */

namespace VuFind\RecordDriver\Response;

/**
 * Class encapsulating publication details.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
class PublicationDetails
{
    /**
     * Place of publication
     *
     * @var string
     */
    protected $place;

    /**
     * Name of publisher
     *
     * @var string
     */
    protected $name;

    /**
     * Date of publication
     *
     * @var string
     */
    protected $date;

    /**
     * Constructor
     *
     * @param string $place Place of publication
     * @param string $name  Name of publisher
     * @param string $date  Date of publication
     */
    public function __construct($place, $name, $date)
    {
        $this->place = $place;
        $this->name = $name;
        $this->date = $date;
    }

    /**
     * Get place of publication
     *
     * @return string
     */
    public function getPlace()
    {
        return $this->place;
    }

    /**
     * Get name of publisher
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get date of publication
     *
     * @return string
     */
    public function getDate()
    {
        return $this->date;
    }

    /**
     * Represent object as a string
     *
     * @return string
     */
    public function __toString()
    {
        return trim(
            preg_replace(
                '/\s+/',
                ' ',
                implode(' ', [$this->place, $this->name, $this->date])
            )
        );
    }
}
