<?php
/**
 * Date format display view helper -- build a language-appropriate format key for
 * entering dates.
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
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
namespace VuFind\Theme\Root\Helper;
use VuFind\Date\Converter as DateConverter, Zend\View\Helper\AbstractHelper;

/**
 * Date format display view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
class DisplayDateFormat extends AbstractHelper
{
    /**
     * Builds an alphabetical help string based on the default display date format.
     *
     * @return string
     */
    public function __invoke()
    {
        $dateFormat = new DateConverter();
        $dueDateHelpString
            = $dateFormat->convertToDisplayDate("m-d-y", "11-22-3333");
        $search = array("1", "2", "3");
        $replace = array(
            $this->view->translate("date_month_placeholder"),
            $this->view->translate("date_day_placeholder"),
            $this->view->translate("date_year_placeholder")
        );

        return str_replace($search, $replace, $dueDateHelpString);
    }
}