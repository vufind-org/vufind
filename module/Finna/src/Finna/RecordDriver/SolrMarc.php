<?php
/**
 * Model for MARC records in Solr.
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2014.
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
 * @package  RecordDrivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
namespace Finna\RecordDriver;

/**
 * Model for MARC records in Solr.
 *
 * @category VuFind2
 * @package  RecordDrivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
class SolrMarc extends \VuFind\RecordDriver\SolrMarc
{
    use SolrFinna;

    /**
     * Get an array of embedded component parts
     *
     * @return array Component parts
     */
    public function getEmbeddedComponentParts()
    {
        $componentParts = array();
        $partOrderCounter = 0;
        foreach ($this->marcRecord->getFields('979') as $field) {
            $partOrderCounter++;
            $partAuthors = array();
            $uniformTitle = '';
            $duration = '';
            $subfields = $field->getSubfields();
            foreach ($subfields as $subfield) {
                $subfieldCode = $subfield->getCode();
                switch ($subfieldCode) {
                case 'a':
                    $partId = $subfield->getData();
                    break;
                case 'b':
                    $partTitle = $subfield->getData();
                    break;
                case 'c':
                    $partAuthors[] = $subfield->getData();
                    break;
                case 'd':
                    $partAuthors[] = $subfield->getData();
                    break;
                case 'e':
                    $uniformTitle = $subfield->getData();
                    break;
                case 'f':
                    $duration = $subfield->getData();
                    if ($duration == '000000') {
                        $duration = '';
                    }
                    break;
                }
            }
            // Filter out any empty fields
            $partAuthors = array_filter($partAuthors);

            $partPresenters = array();
            $partArrangers = array();
            $partOtherAuthors = array();
            foreach ($partAuthors as $author) {
                if (isset($this->mainConfig->Record->presenter_roles)) {
                    foreach ($this->mainConfig->Record->presenter_roles as $role) {
                        $author = trim($author);
                        if (substr($author, -strlen($role) - 2) == ", $role") {
                            $partPresenters[] = $author;
                            continue 2;
                        }
                    }
                }
                if (isset($this->mainConfig->Record->arranger_roles)) {
                    foreach ($this->mainConfig->Record->arranger_roles as $role) {
                        if (substr($author, -strlen($role) - 2) == ", $role") {
                            $partArrangers[] = $author;
                            continue 2;
                        }
                    }
                }
                $partOtherAuthors[] = $author;
            }

            $componentParts[] = array(
                'number' => $partOrderCounter,
                'id' => $partId,
                'title' => $partTitle,
                'author' => implode('; ', $partAuthors), // For backward compatibility
                'authors' => $partAuthors,
                'uniformTitle' => $uniformTitle,
                'duration' => $duration ? substr($duration, 0, 2) . ':' . substr($duration, 2, 2) . ':' . substr($duration, 4, 2) : '',
                'presenters' => $partPresenters,
                'arrangers' => $partArrangers,
                'otherAuthors' => $partOtherAuthors,
            );
        }
        return $componentParts;
    }

    /**
     * Does this record have embedded component parts
     *
     * @return bool Whether this record has embedded component parts
     */
    public function hasEmbeddedComponentParts()
    {
        return $this->marcRecord->getFields('979') ? true : false;
    }

    /**
     * Return an array of associative URL arrays with one or more of the following
     * keys:
     *
     * <li>
     *   <ul>desc: URL description text to display (optional)</ul>
     *   <ul>url: fully-formed URL (required if 'route' is absent)</ul>
     *   <ul>route: VuFind route to build URL with (required if 'url' is absent)</ul>
     *   <ul>routeParams: Parameters for route (optional)</ul>
     *   <ul>queryString: Query params to append after building route (optional)</ul>
     * </li>
     *
     * @return array
     */
    public function getURLs()
    {
        $urls = array();
        foreach (parent::getURLs() as $url) {
            if (!$this->urlBlacklisted(
                isset($url['url']) ? $url['url'] : '',
                isset($url['desc']) ? $url['desc'] : ''
            )) {
                $urls[] = $url;
            }
        }
        return $urls;
    }

    /**
     * Return an external URL where a displayable description text
     * can be retrieved from, if available; false otherwise.
     *
     * @return mixed
     * @access public
     */
    public function getDescriptionURL()
    {
        $url = '';
        $type = '';
        foreach ($this->marcRecord->getFields('856') as $url) {
            $type = $url->getSubfield('q');
            if ($type) {
                $type = $type->getData();
                if ("TEXT" == $type || "text/html" == $type) {
                    $address = $url->getSubfield('u');
                    if ($address && !$this->urlBlacklisted($address->getData())) {
                        $address = $address->getData();
                        return $address;
                    }
                }
            }
        }

        return 'http://siilo-kk.lib.helsinki.fi/getText.php?query=' . 
            $this->getCleanISBN();
    }

    /**
     * Check if a URL (typically from getURLs()) is blacklisted based on the URL
     * itself and optionally its description.
     *
     * @param string $url  URL
     * @param string $desc Optional description of the URL
     *
     * @return boolean Whether the URL is blacklisted
     */
    protected function urlBlacklisted($url, $desc = '')
    {
        if (!isset($this->recordConfig->Record->url_blacklist)) {
            return false;
        }
        foreach ($this->recordConfig->Record->url_blacklist as $rule) {
            if (substr($rule, 0, 1) == '/' && substr($rule, -1, 1) == '/') {
                if (preg_match($rule, $url)
                    || ($desc !== '' && preg_match($rule, $desc))
                ) {
                    return true;
                }
            } elseif ($rule == $url || $rule == $desc) {
                return true;
            }
        }
        return false;
    }
}