<?php
/**
 * Date/time conversion functionality.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2011.
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
 * @package  Date
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\Date;

use DateTime;
use DateTimeZone;
use VuFind\Exception\Date as DateException;

/**
 * Date/time conversion functionality.
 *
 * @category VuFind
 * @package  Date
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Converter
{
    /**
     * Format string for dates
     *
     * @var string
     */
    protected $displayDateFormat;

    /**
     * Format string for times
     *
     * @var string
     */
    protected $displayTimeFormat;

    /**
     * Time zone to use for conversions
     *
     * @var DateTimeZone
     */
    protected $timezone;

    /**
     * Constructor
     *
     * @param \Zend\Config\Config $config Configuration to use (set to null to use
     * defaults)
     */
    public function __construct($config = null)
    {
        // Set Display Date Format
        $this->displayDateFormat
            = isset($config->Site->displayDateFormat)
            ? $config->Site->displayDateFormat : "m-d-Y";

        // Set Display Date Format
        $this->displayTimeFormat
            = isset($config->Site->displayTimeFormat)
            ? $config->Site->displayTimeFormat : "H:i";

        // Set time zone
        $zone = isset($config->Site->timezone)
            ? $config->Site->timezone : 'America/New_York';
        $this->timezone = new DateTimeZone($zone);
    }

    /**
     * Generic method for conversion of a time / date string
     *
     * @param string $inputFormat  The format of the time string to be changed
     * @param string $outputFormat The desired output format
     * @param string $dateString   The date string
     *
     * @throws DateException
     * @return string               A re-formatted time string
     */
    public function convert($inputFormat, $outputFormat, $dateString)
    {
        // These are date formats that we definitely know how to handle, and some
        // benefit from special processing. However, items not found in this list
        // will still be attempted in a generic fashion before giving up.
        $validFormats = [
            "m-d-Y", "m-d-y", "m/d/Y", "m/d/y", "U", "m-d-y H:i", "Y-m-d",
            "Y-m-d H:i"
        ];
        $isValid = in_array($inputFormat, $validFormats);
        if ($isValid) {
            if ($inputFormat == 'U') {
                // Special case for Unix timestamps (including workaround for
                // floating point numbers):
                $dateString = '@'
                    . (is_float($dateString) ? intval($dateString) : $dateString);
            } else {
                // Strip leading zeroes from date string and normalize date separator
                // to slashes:
                $regEx = '/0*([0-9]+)(-|\/)0*([0-9]+)(-|\/)0*([0-9]+)/';
                $dateString = trim(preg_replace($regEx, '$1/$3/$5', $dateString));
            }
            $errors = [
                'warning_count' => 0, 'error_count' => 0, 'errors' => []
            ];
            try {
                $date = new DateTime($dateString, $this->timezone);
            } catch (\Exception $e) {
                $errors['error_count']++;
                $errors['errors'][] = $e->getMessage();
            }
        } else {
            $date = DateTime::createFromFormat(
                $inputFormat, $dateString, $this->timezone
            );
            $errors = DateTime::getLastErrors();
        }

        if ($errors['warning_count'] == 0 && $errors['error_count'] == 0 && $date) {
            $date->setTimeZone($this->timezone);
            return $date->format($outputFormat);
        }
        throw new DateException($this->getDateExceptionMessage($errors));
    }

    /**
     * Build an exception message from a detailed error array.
     *
     * @param array $details Error details
     *
     * @return string
     */
    protected function getDateExceptionMessage($details)
    {
        $errors = "Date/time problem: Details: ";
        if (is_array($details['errors']) && $details['error_count'] > 0) {
            foreach ($details['errors'] as $error) {
                $errors .= $error . " ";
            }
        } elseif (is_array($details['warnings'])) {
            foreach ($details['warnings'] as $warning) {
                $errors .= $warning . " ";
            }
        }
        return $errors;
    }

    /**
     * Convert a date string to admin-defined format.
     *
     * @param string $createFormat The format of the date string to be changed
     * @param string $dateString   The date string
     *
     * @throws DateException
     * @return string               A re-formatted date string
     */
    public function convertToDisplayDate($createFormat, $dateString)
    {
        return $this->convert($createFormat, $this->displayDateFormat, $dateString);
    }

    /**
     * Public method for conversion of an admin defined date string
     * to a driver required date string
     *
     * @param string $outputFormat The format of the required date string
     * @param string $displayDate  The display formatted date string
     *
     * @throws DateException
     * @return string               A re-formatted date string
     */
    public function convertFromDisplayDate($outputFormat, $displayDate)
    {
        return $this->convert(
            $this->displayDateFormat, $outputFormat, $displayDate
        );
    }

    /**
     * Public support method for conversion of a time string to admin defined
     * time string.
     *
     * @param string $createFormat The format of the time string to be changed
     * @param string $timeString   The time string
     *
     * @throws DateException
     * @return string               A re-formatted time string
     */
    public function convertToDisplayTime($createFormat, $timeString)
    {
        return $this->convert($createFormat, $this->displayTimeFormat, $timeString);
    }

    /**
     * Public method for getting a date prepended to a time.
     *
     * @param string $createFormat The format of the time string to be changed
     * @param string $timeString   The time string
     * @param string $separator    String between time/date
     *
     * @throws DateException
     * @return string               A re-formatted time string
     */
    public function convertToDisplayDateAndTime($createFormat, $timeString,
        $separator = ' '
    ) {
        return $this->convertToDisplayDate($createFormat, $timeString)
            . $separator . $this->convertToDisplayTime($createFormat, $timeString);
    }

    /**
     * Public method for getting a time prepended to a date.
     *
     * @param string $createFormat The format of the time string to be changed
     * @param string $timeString   The time string
     * @param string $separator    String between time/date
     *
     * @throws DateException
     * @return string               A re-formatted time string
     */
    public function convertToDisplayTimeAndDate($createFormat, $timeString,
        $separator = ' '
    ) {
        return $this->convertToDisplayTime($createFormat, $timeString)
            . $separator . $this->convertToDisplayDate($createFormat, $timeString);
    }
}
