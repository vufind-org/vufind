<?php
/**
 * Localization based number formatting
 *
 * PHP version 5
 *
 * Copyright (C) snowflake productions gmbh 2014.
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
 * @author   Nicolas Karrer <nkarrer@snowflake.ch>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 */
namespace VuFind\View\Helper\Root;
use Zend\View\Helper\AbstractHelper;

/**
 * Class NumberFormat
 * allows localization based formating of numbers in view
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Nicolas Karrer <nkarrer@snowflake.ch>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 */
class LocalizedNumber extends AbstractHelper
{
    /**
     * @var string
     */
    protected $defaultDecimalPoint = '.';


    /**
     * @var string
     */
    protected $defaultThousandSep = ',';


    /**
     * @param   string  $number
     * @param   int     $decimals
     * @return  string
     */
    public function __invoke($number, $decimals = 0)
    {
      $translator = $this->getView()->plugin('translate');

      return number_format($number,
                           $decimals,
                           $translator('number_decimal_point', array(), $this->defaultDecimalPoint),
                           $translator('number_thousands_separator', array(), $this->defaultThousandSep)
      );
    }
}