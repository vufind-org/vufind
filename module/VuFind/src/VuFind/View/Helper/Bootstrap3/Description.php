<?php
/**
 * Description view helper
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
 * @author   Jochen Lienhard <lienhard@ub.uni-freiburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\View\Helper\Bootstrap3;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use \Zend\View\Helper\EscapeHtml;

/**
 * Description view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Jochen Lienhard <lienhard@ub.uni-freiburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class Description extends \Zend\View\Helper\AbstractHelper implements TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;


    /**
     * Array of keys for the methods and values 
     *
     * @array
     */
    protected $items = [
    'Summary',
    'Published',
/*    'Item Description',
    'Physical Description',
    'Publication Frequency',
    'Playing Time',
    'Format',
    'Audience',
    'Awards',
    'Production Credits',
    'Bibliography', */
    'ISBN',
/*    'ISSN',
    'DOI',
    'Related Items',
    'Finding Aid',
    'Publication_Place',
    'Author Notes', */
    ];

    protected $driver = null;

    /**
    * Initial method
    *
    * @param string $driver the driver
    *
    * @return driver
    */
    public function __invoke($driver)
    {
        // Set up driver context:
        $this->driver = $driver;
        return $this;
    }

    /**
    * Get the names of the functions 
    *
    * @return array 
    */
    public function getItems() 
    {
        $results = [];
        foreach ($this->items as $item) {
            $function = [$this, 'get' . preg_replace('/( |\_)/','',$item)];
            $itemValue = call_user_func($function);
            if ($itemValue) {
                $results[$item] = $itemValue;
            }
        }
        return $results;
    }

    /**
     * Render summary as array with br
     *
     * @return string 
     */
    protected function getSummary()
    {
        $html_result = "";
        $recordpart = $this->driver->getSummary();
        if ($recordpart != null) {
          foreach ($recordpart as $field) { 
            $html_result .= $field . "<br/>";
          }
        }
        return $html_result;
    }

    /**
     * Render published as array with br
     *
     * @return string 
     */
    protected function getPublished()
    {
        $html_result = "";
        $recordpart = $this->driver->getDateSpan();
        if ($recordpart != null) {
          foreach ($recordpart as $field) { 
            $html_result .= $field . "<br/>";
          }
        }
        return $html_result;
    }

// here must be the other content ... and now the ISBN as a basic example

    /**
     * Render ISBN as array with br
     *
     * @return string 
     */
    protected function getISBN()
    {
        $html_result = "";
        $recordpart = $this->driver->getISBNs();
        if ($recordpart != null) {
          foreach ($recordpart as $field) {
            $html_result .= $field . "<br/>";
          }
        }
        return $html_result;
    }



}
