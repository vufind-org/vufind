<?php
/**
 * Map tab
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
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_tabs Wiki
 */
namespace VuFind\RecordTab;
use VuFind\Config\Reader as ConfigReader;

/**
 * Map tab
 *
 * @category VuFind2
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_tabs Wiki
 */
class Map extends AbstractBase
    implements \VuFind\I18n\Translator\TranslatorAwareInterface
{
     /**
     * Translator (or null if unavailable)
     *
     * @var \Zend\I18n\Translator\Translator
     */
    protected $translator = null;

    /**
     * Get the on-screen description for this tab.
     *
     * @return string
     */
    public function getDescription()
    {
        return 'Map View';
    }

    /**
     * getGoogleMapMarker - gets the JSON needed to display the record on a Google
     * map.
     *
     * @return string
     */
    public function getGoogleMapMarker()
    {
        $longLat = $this->getRecordDriver()->tryMethod('getLongLat');
        if (empty($longLat)) {
            return json_encode(array());
        }
        $longLat = explode(',', $longLat);
        $markers = array(
            array(
                'title' => (string) $this->getRecordDriver()->getBreadcrumb(),
                'lon' => $longLat[0],
                'lat' => $longLat[1]
            )
        );
        return json_encode($markers);
    }

    /**
     * Set a translator
     *
     * @param \Zend\I18n\Translator\Translator $translator Translator
     *
     * @return ResultGoogleMapAjax
     */
    public function setTranslator(\Zend\I18n\Translator\Translator $translator)
    {
        $this->translator = $translator;
        return $this;
    }

    /**
     * Get translator object.
     *
     * @return \Zend\I18n\Translator\Translator
     */
    public function getTranslator()
    {
        return $this->translator;
    }

    /**
     * getUserLang
     *
     * @return string of lang
     */
    public function userLang()
    {
        $translator = $this->getTranslator();
        return is_object($translator) ? $translator->getLocale() : 'en';
    }

    /**
     * Is this tab active?
     *
     * @return bool
     */
    public function isActive()
    {
        $config = ConfigReader::getConfig();
        if (!isset($config->Content->recordMap)) {
            return false;
        }
        $longLat = $this->getRecordDriver()->tryMethod('getLongLat');
        return !empty($longLat);
    }
}