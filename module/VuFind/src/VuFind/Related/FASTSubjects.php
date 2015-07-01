<?php
/**
 * Related Records: FAST related subject headings
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2009.
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
 * @package  Related_Records
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_a_related_record_module Wiki
 */
namespace VuFind\Related;

/**
 * Related Records: FAST related subject headings
 *
 * @category VuFind2
 * @package  Related_Records
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_a_related_record_module Wiki
 */
class FASTSubjects implements RelatedInterface
{
	/**
	 * Similar records
	 *
	 * @var array
	 */
	protected $results;
	
	
    /**
     * Establishes base settings for making recommendations.
     *
     * @param string                            $settings Settings from config.ini
     * @param \VuFind\RecordDriver\AbstractBase $driver   Record driver object
     *
     * @return void
     */
    public function init($settings, $driver)
    {
		// this only works with WorldCat Discovery driver need to reflect that in the code
	    if ($driver instanceof \VuFind\RecordDriver\WorldCatDiscovery):
	        // Add FASTSubjects to query
	        $abouts = $driver->getRawObject()->getAbout();
	         
	        $subjects = [];
	        
	        foreach($abouts as $subject)
	        {
	        	$relatedSubjects = [];
	        	$broaderSubjects = [];
	        	$narrowerSubjects = [];
	        	// || 0 === strpos($subject->getUri(), 'http://id.loc.gov/authorities/subjects/')
	        	if(0 === strpos($subject->getUri(), 'http://id.worldcat.org/fast/')) {
	        		$subjectObject = \WorldCat\Discovery\Thing::findByUri($subject->getUri(), ['accept' => 'application/rdf+xml']);
	        		foreach($subjectObject->allResources('skos:related') as $relatedSubject) {
	        			array_push($relatedSubjects, $relatedSubject->getLiteral('rdfs:label')->getValue());
	        		}
	        		
	        		// Add loop from broaderSubjects
	        		// Add loop for narrowerSubjects
	        		// Change next array to account for these
	        
	        		if(is_a($subject->getName(), 'EasyRdf_Literal')) {
	        			if(empty($subjects[$subject->getName()->getValue()])) {
	        				$subjects[$subject->getName()->getValue()] = $relatedSubjects;
	        			}
	        		}
	        		else if($subject->getName() != null) {
	        			$subjects[$subject->getName()] = [];
	        		}
	        	}
	        	 
	        
	        }
	        $this->results = $subjects;
	     endif;
    }
    
    /**
     * Get an array of Record Driver objects representing other editions of the one
     * passed to the constructor.
     *
     * @return array
     */
    public function getResults()
    {
    	return $this->results;
    }
}
