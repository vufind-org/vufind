<?php
/**
 * View helper for formatting dates and times.
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\View\Helper\Root;

/**
 * View helper for formatting dates and times
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class DateTime extends \Zend\View\Helper\AbstractHelper
{
    /**
     * Date converter
     *
     * @var \VuFind\Date\Converter
     */
    protected $converter;

    /**
     * Constructor
     *
     * @param \VuFind\Date\Converter $converter Date converter
     */
    public function __construct(\VuFind\Date\Converter $converter)
    {
        $this->converter = $converter;
    }

    /**
     * Extract a year from a human-readable date.  Return false if no year can
     * be found.
     *
     * @param string $date Date to reformat
     *
     * @return string|bool
     */
    public function extractYear($date)
    {
        try {
            return $this->converter->convertFromDisplayDate('Y', $date);
        } catch (\VuFind\Exception\Date $e) {
            // bad date? just ignore it!
            return false;
        }
    }

    /**
     * Extract a full date and time string from human-readable date.
     *
     * @param string $createFormat The format the date was created in
     * @apram string $dateString   The date String
     *
     * @return string|bool
     */
    public function extractFull($createFormat = false, $dateString = false)
    {
        $createFormat = (false === $createFormat) ?
            'H:m:s H:i:s' : $createFormat;

        $dateString = (false === $dateString) ?
            date('H:m:s H:i:s') : $dateString;

        try {
            return $this->converter->convertToDisplayDate($createFormat, $dateString);
        } catch (\VuFind\Exception\Date $e) {
            // bad date? just ignore it!
            return false;
        }
    }

    /**
     * Builds an alphabetical help string based on the default display date format.
     *
     * @return string
     */
    public function getDisplayDateFormat()
    {
        $dueDateHelpString
            = $this->converter->convertToDisplayDate("m-d-y", "11-22-3333");
        $search = array("1", "2", "3");
        $replace = array(
            $this->view->translate("date_month_placeholder"),
            $this->view->translate("date_day_placeholder"),
            $this->view->translate("date_year_placeholder")
        );

        return str_replace($search, $replace, $dueDateHelpString);
    }
}