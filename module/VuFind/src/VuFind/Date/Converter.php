<?php
/**
 * Date/time conversion functionality.
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Date
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\Date;
use DateTime, VuFind\Config\Reader as ConfigReader,
    VuFind\Exception\Date as DateException;

/**
 * Date/time conversion functionality.
 *
 * @category VuFind2
 * @package  Date
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
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
     * Constructor
     *
     * @param \Zend\Config\Config $config Configuration to use (set to null to load
     * default configuration using ConfigReader class).
     */
    public function __construct($config = null)
    {
        if (is_null($config)) {
            $config = ConfigReader::getConfig();
        }

        // Set Display Date Format
        $this->displayDateFormat
            = (isset($config->Site->displayDateFormat))
            ? $config->Site->displayDateFormat : "m-d-Y";

        // Set Display Date Format
        $this->displayTimeFormat
            = (isset($config->Site->displayTimeFormat))
            ? $config->Site->displayTimeFormat : "H:i";
    }

    /**
     * Protected method for conversion of a time / date string
     *
     * @param string $inputFormat  The format of the time string to be changed
     * @param string $outputFormat The desired output format
     * @param string $dateString   The date string
     *
     * @throws DateException
     * @return string               A re-formated time string
     */
    protected function process($inputFormat, $outputFormat, $dateString)
    {
        $errors = "Date/time problem: Details: ";

        // For compatibility with PHP 5.2.x, we have to restrict the input formats
        // to a fixed list...  but we'll check to see if we have access to PHP 5.3.x
        // before failing if we encounter an input format that isn't whitelisted.
        $validFormats = array(
            "m-d-Y", "m-d-y", "m/d/Y", "m/d/y", "U", "m-d-y H:i", "Y-m-d",
            "Y-m-d H:i"
        );
        $isValid = in_array($inputFormat, $validFormats);
        if ($isValid) {
            if ($inputFormat == 'U') {
                // Special case for Unix timestamps:
                $dateString = '@' . $dateString;
            } else {
                // Strip leading zeroes from date string and normalize date separator
                // to slashes:
                $regEx = '/0*([0-9]+)(-|\/)0*([0-9]+)(-|\/)0*([0-9]+)/';
                $dateString = trim(preg_replace($regEx, '$1/$3/$5', $dateString));
            }
            $getErrors = array(
                'warning_count' => 0, 'error_count' => 0, 'errors' => array()
            );
            try {
                $date = new DateTime($dateString);
            } catch (\Exception $e) {
                $getErrors['error_count']++;
                $getErrors['errors'][] = $e->getMessage();
            }
        } else {
            if (!method_exists('DateTime', 'createFromFormat')) {
                throw new DateException(
                    "Date format {$inputFormat} requires PHP 5.3 or higher."
                );
            }
            $date = DateTime::createFromFormat($inputFormat, $dateString);
            $getErrors = DateTime::getLastErrors();
        }

        if ($getErrors['warning_count'] == 0
            && $getErrors['error_count'] == 0 && $date
        ) {
            return $date->format($outputFormat);
        } else {
            if (is_array($getErrors['errors']) && $getErrors['error_count'] > 0) {
                foreach ($getErrors['errors'] as $error) {
                    $errors .= $error . " ";
                }
            } else if (is_array($getErrors['warnings'])) {
                foreach ($getErrors['warnings'] as $warning) {
                    $errors .= $warning . " ";
                }
            }

            throw new DateException($errors);
        }
    }

    /**
     * public method for conversion of a date string to admin defined
     * date string.
     *
     * @param string $createFormat The format of the date string to be changed
     * @param string $dateString   The date string
     *
     * @throws DateException
     * @return string               A re-formated date string
     */

    public function convertToDisplayDate($createFormat, $dateString)
    {
        return $this->process($createFormat, $this->displayDateFormat, $dateString);
    }

    /**
     * Public method for conversion of an admin defined date string
     * to a driver required date string
     *
     * @param string $outputFormat The format of the required date string
     * @param string $displayDate  The display formatted date string
     *
     * @throws DateException
     * @return string               A re-formated date string
     */

    public function convertFromDisplayDate($outputFormat, $displayDate)
    {
        return $this->process(
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
     * @return string               A re-formated time string
     */

    public function convertToDisplayTime($createFormat, $timeString)
    {
        return $this->process($createFormat, $this->displayTimeFormat, $timeString);
    }
}
