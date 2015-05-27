<?php
/**
 * Model for WorldCat Discovery records.
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
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
namespace VuFind\RecordDriver;
use VuFind\Connection\WorldCatKnowledgeBaseUrlService as UrlService;

/**
 * Model for WorldCat Discovery records.
 *
 * @category VuFind2
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
class WorldCatDiscovery extends SolrDefault implements \VuFindHttp\HttpServiceAwareInterface
{
    use IlsAwareTrait;
    use \VuFindHttp\HttpServiceAwareTrait;

    /**
     * Raw WorldCatDiscovery response object.
     *
     * @var object
     */
    protected $rawObject = null;

    /**
     * WorldCat Knowledge Base URL Service
     *
     * @var UrlService
     */
    protected $urlService = null;

    public function setRawData($data)
    {
        // Because we extend the SolrDefault driver, there is an expectation that
        // data will be found in $this->fields; however, we don't actually want
        // to put anything there for our purposes. Instead, we'll store the object
        // in a separate property and leave the fields array empty so that parent
        // functionality will use appropriate default behaviors.
        $this->fields = [];
        $this->rawObject = $data;
    }
    

    public function setWorldCatKnowledgeBaseUrlService(UrlService $service)
    {
        $this->urlService = $service;
    }

    public function getWorldCatKnowledgeBaseUrlService()
    {
        return $this->urlService;
    }

    public function getRawObject()
    {
        if (null === $this->rawObject) {
            throw new \Exception('Data not initialized.');
        }
        return $this->rawObject;
    }
    
    public function setOffers($offers)
    {
        $this->offers = $offers;
    }
    
    public function getOffers()
    {
        return $this->offers;
    }
    
    public function getOffer($id){
        $offers = $this->getOffers();
        
        $offersAtInstitution = array_filter($offers, function($offer) use ($id)
        {

            return($offer->getSeller()->getURI() == 'http://worldcat.org/wcr/organization/resource/' . $id);
        });
        return $offersAtInstitution;
    }
    
    public function getOtherLibraryOffers(){
    	$id = $this->recordConfig->General->institution;
    	$offers = $this->getOffers();
    	
    	$offersAtOtherInstitutions = array_filter($offers, function($offer) use ($id)
    	{
    	
    		return($offer->getSeller()->getURI() != 'http://worldcat.org/wcr/organization/resource/' . $id);
    	});
    	return $offersAtOtherInstitutions;
    }

    /**
     * Get Subjects
     *
     * @return array
     */
    public function getAllSubjectHeadings()
    {
        $subjects = $this->getRawObject()->getAbout();
        array_walk($subjects, function(&$subject)
        {
            $subject = is_callable([$subject, 'getName'])
                ? $subject->getName() : null;
        });
        $subjects = array_unique(array_filter($subjects));
        array_walk($subjects, function(&$subject)
        {
            $subject = [$subject];
        });
        return $subjects;
    }

    /**
     * Get award notes for the record.
     *
     * @return array
     */
    public function getAwards()
    {
        $obj = $this->getRawObject();
        return is_callable([$obj, 'getAwards'])
            ? $obj->getAwards('586') : [];
    }

    /**
     * Return the first valid DOI found in the record (false if none).
     *
     * @return mixed
     */
    public function getCleanDOI()
    {

    }

    /**
     * Get an array of all the formats associated with the record.
     *
     * @return array
     */
    public function getFormats()
    {
        $formats = $this->getRawObject()->types();
        array_walk($formats, function(&$format)
        {
            if (strchr($format, '/')) {
                $format = substr(strchr($format, '/'), 1);
            } else {
                $format = substr(strchr($format, ':'), 1);
            }
            
            if (strchr($format, '_')) {
                $format = str_replace("_", " ", $format);
            }
            
            
        });
        return $formats;
    }

    /**
     * Get general notes on the record.
     *
     * @return array
     */
    public function getGeneralNotes()
    {
        $notes = $this->getRawObject()->getDescriptions();
        array_walk($notes, function(&$note)
        {
            $note = $note->getValue();
        });
        return $notes;
    }

    /**
     * Get an array of all ISBNs associated with the record (may be empty).
     *
     * @return array
     */
    public function getISBNs()
    {
        $response = $this->getRawObject();
        if (is_a($response, 'WorldCat\Discovery\Book')){
            $manifestations = $response->getManifestations();
            array_walk($manifestations, function(&$manifestation)
            {
                $manifestation = is_callable([$manifestation, 'getISBN'])
                    ? $manifestation->getISBN() : null;
            });
            return $manifestations;
        }
        return [];
    }

    /**
     * Get an array of all ISSNs associated with the record (may be empty).
     *
     * @return array
     */
    public function getISSNs()
    {
        $response = $this->getRawObject();
        if (is_a($response, 'WorldCat\Discovery\Periodical')){
            // TODO: this doesn't work
            //return array($response->getIssn()->getValue());
        }
        return [];
    }

    /**
     * Get an array of all the languages associated with the record.
     *
     * @return array
     */
    public function getLanguages()
    {
        return [$this->getRawObject()->getLanguage()];
    }

    /**
     * Get the OCLC number of the record.
     *
     * @return array
     */
    public function getOCLC()
    {
        return [$this->getRawObject()->getOCLCNumber()->getValue()];
    }

    /**
     * Get the OpenURL parameters to represent this record (useful for the
     * title attribute of a COinS span tag).
     *
     * @return string OpenURL parameters.
     */
    public function getOpenURL()
    {
        $kbrequest = [];
        $record = $this->getRawObject();
        if ($record instanceof \WorldCat\Discovery\Article) {
            if ($record->getSameAs()){
                $doi = str_replace("http://dx.doi.org/", "info:doi:", $record->getSameAs());
                $kbrequest[] = 'rft_id=' . $doi;
            } else {
                if ($part = $record->getIsPartOf()) {
                    if (is_callable([$part, 'getVolume'])
                        && ($vol = $part->getVolume())
                    ) {
                        $kbrequest[] = "rft.issn=" . $vol->getPeriodical()->getIssn();
                        $kbrequest[] = "rft.volume=" . $vol->getVolumeNumber();
                    }
                    if (is_callable([$part, 'getIssueNumber'])) {
                        $kbrequest[] = "rft.issue=" . $part->getIssueNumber();
                    }
                }
                $kbrequest[] = "rft.spage=" . $record->getPageStart();
                $kbrequest[] = "rft.atitle=" . $record->getName();
            }
        } elseif ($record instanceof \WorldCat\Discovery\Book
            && ($manifestations = $record->getManifestations())
            && isset($manifestations[0])
            && is_callable([$manifestations[0], 'getISBN'])
        ) {
            $kbrequest[] = "rft.isbn=" . $manifestations[0]->getISBN();
        } else {
            $kbrequest[] = "rft.oclcnum=" . $record->getOCLCNumber();
        }
        return implode('&', $kbrequest);
    }

    /**
     * Get the item's place of publication.
     *
     * @return array
     */
    public function getPlacesOfPublication()
    {
        // need to fix the WorldCat Discovery code library to account for this
        $placesOfPublication = $this->getRawObject()->getPlacesOfPublication();
        array_walk($placesOfPublication, function(&$placeOfPublication)
        {
            if ($placeOfPublication->get('schema:name')) {
                $placeOfPublication = $placeOfPublication->getName();
            } else {
                $placeOfPublication = $placeOfPublication->get('http://purl.org/dc/terms/identifier');
            }
        });
        return $placesOfPublication;
    }

    /**
     * Get the main author of the record.
     *
     * @return string
     */
    public function getPrimaryAuthor()
    {
        $author = $this->getRawObject()->getAuthor();
        
        return is_callable([$author, 'getName']) ? $author->getName() : '';
    }

    /**
     * Get the publication dates of the record.
     *
     * @return array
     */
    public function getPublicationDates()
    {
        return [$this->getRawObject()->getDatePublished()];
    }

    /**
     * Get the publishers of the record.
     *
     * @return array
     */
    public function getPublishers()
    {
        // WorldCat\Discovery\Bib needs its type mapping updated
        $publisher = $this->getRawObject()->getPublisher();
        return $publisher ? [$publisher->get('schema:name')] : [];

    }

    /**
     * Get an array of all secondary authors (complementing getPrimaryAuthor()).
     *
     * @return array
     */
    public function getSecondaryAuthors()
    {
        $contributors = $this->getRawObject()->getContributors();
        array_walk($contributors, function(&$contributor)
        {
            $contributor = $contributor->getName();
        });
        return $contributors;
    }
    
    /**
     * Get the short (pre-subtitle) title of the record.
     *
     * @return string
     */
    public function getShortTitle()
    {
        return $this->getRawObject()->getName();
    }

    /**
     * Get the full title of the record.
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->getRawObject()->getName();
    }

    /**
     * Return the unique identifier of this record within the Solr index;
     * useful for retrieving additional information (like tags and user
     * comments) from the external MySQL database.
     *
     * @return string Unique identifier.
     */
    public function getUniqueID()
    {
        return (string)$this->getRawObject()->getOCLCNumber()->getValue();
    }

    /**
     * getGenres
     */

    /**
     * getWork
     */

    /**
     * getUrls
     */
    public function getUrls()
    {
    	$urls = array();
    	if (isset($this->recordConfig->eHoldings->active)
            && $this->recordConfig->eHoldings->active == true
        ) {
	    	$kbrequest = "http://worldcat.org/webservices/kb/openurl/resolve?";
	    	$kbrequest .= $this->getOpenURL();
	    	$kbrequest .= '&wskey=' . $this->recordConfig->General->wskey;
	    		
	    	$client = $this->httpService
	    	->createClient($kbrequest);
	    	$adapter = new \Zend\Http\Client\Adapter\Curl();
	    	$client->setAdapter($adapter);
	    	$result = $client->setMethod('GET')->send();
	    	
	    	if ($result->isSuccess()){
	    		$kbresponse = json_decode($result->getBody(), true);
	    		if (isset($kbresponse[0]['url'])){
	    			$urls[] = ['url' => $kbresponse[0]['url'], 'desc' => $kbresponse[0]['collection_name']];
	    		}
	    	} else {
	    		throw new \Exception('WorldCat Knowledge Base API error - ' . $result->getStatusCode() . ' - ' . $result->getReasonPhrase());
	    	}
    	}
    	if ($urlService = $this->getWorldCatKnowledgeBaseUrlService()) {
            $moreUrls = $urlService->getUrls($this);
            $urls = array_merge($urls, $moreUrls);
        }
    	return $urls;
    }

    /**
     * Book specific metadata
     */

    /**
     * Get the edition of the current record.
     *
     * @return string
     */
    public function getEdition()
    {
        $response = $this->getRawObject();
        if (is_a($response, 'WorldCat\Discovery\Book')){
            $edition = $response->getBookEdition();
            return is_callable([$edition, 'getValue'])
                ? $edition->getValue() : '';
        }
        return '';
    }

    /**
     * getNumberOfPages
     */

    /**
     * getReviews
     */

    /**
     * Articles
     */

    /**
     * Get the title of the item that contains this record
     *
     * @return string
     */
    public function getContainerTitle()
    {
        $response = $this->getRawObject();
        if (is_a($response, 'WorldCat\Discovery\Article')){
            $parent = $response->getIsPartOf();
            if ($parent && is_callable([$parent, 'getVolume'])) {
                $volume = $parent->getVolume();
                if ($volume && is_callable([$volume, 'getPeriodical'])) {
                    $name = $volume->getPeriodical()->getName();
                    if ($name) {
                        return $name->getValue();
                    }
                }
            }
        }
        return '';
    }

    /**
     * Get the volume of the item that contains this record.
     *
     * @return string
     */
    public function getContainerVolume()
    {
        $response = $this->getRawObject();
        if (is_a($response, 'WorldCat\Discovery\Article')){
            $parent = $response->getIsPartOf();
            if ($parent) {
                return $parent->getVolume()->getVolumeNumber();
            }
        }
        return '';
    }

    /**
     * Get the issue of the item that contains this record.
     *
     * @return string
     */
    public function getContainerIssue()
    {
        $response = $this->getRawObject();
        if (is_a($response, 'WorldCat\Discovery\Article')){
            return $response->getIsPartOf()->getIssueNumber();
        }
        return '';
    }

    /**
     * Get the start page of the item that contains this record.
     *
     * @return string
     */
    public function getContainerStartPage()
    {
        $response = $this->getRawObject();
        if (is_a($response, 'WorldCat\Discovery\Article')){
            return $response->getPageStart();
        }
        return '';
    }

    /**
     * Get the end page of the item that contains this record.
     *
     * @return string
     */
    public function getContainerEndPage()
    {
        $response = $this->getRawObject();
        if (is_a($response, 'WorldCat\Discovery\Article')){
            return $response->getPageEnd();
        }
        return '';
    }

    /**
     * Get a full, free-form reference to the context of the item that contains this
     * record (i.e. volume, year, issue, pages).
     *
     * @return string
     */
    public function getContainerReference()
    {
        $response = $this->getRawObject();
        if (is_a($response, 'WorldCat\Discovery\Article')){
            $str = '';
            $vol = $this->getContainerVolume();
            if (!empty($vol)) {
                $str .= $this->translate('citation_volume_abbrev')
                . ' ' . $vol;
            }
            $no = $this->getContainerIssue();
            if (!empty($no)) {
                if (strlen($str) > 0) {
                    $str .= '; ';
                }
                $str .= $this->translate('citation_issue_abbrev')
                . ' ' . $no;
            }
            $start = $this->getContainerStartPage();
            if (!empty($start)) {
                if (strlen($str) > 0) {
                    $str .= '; ';
                }
                $end = $this->getContainerEndPage();
                if ($start == $end) {
                    $str .= $this->translate('citation_singlepage_abbrev')
                    . ' ' . $start;
                } else {
                    $str .= $this->translate('citation_multipage_abbrev')
                    . ' ' . $start . ' - ' . $end;
                }
            }
            return $str;
        }
        return '';
    }

    /**
     * getSameAs
     */
}
