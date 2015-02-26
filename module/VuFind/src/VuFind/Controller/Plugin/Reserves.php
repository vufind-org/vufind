<?php
/**
 * VuFind Action Helper - Reserves Support Methods
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
 * @package  Controller_Plugins
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Controller\Plugin;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

/**
 * Zend action helper to perform reserves-related actions
 *
 * @category VuFind2
 * @package  Controller_Plugins
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class Reserves extends AbstractPlugin
{
    /**
     * Do we need to use the Solr index for reserves (true) or the ILS driver
     * (false)?
     *
     * @var bool
     */
    protected $useIndex;

    /**
     * Constructor
     *
     * @param bool $useIndex Do we need to use the Solr index for reserves (true)
     * or the ILS driver (false)?
     */
    public function __construct($useIndex = false)
    {
        $this->useIndex = $useIndex;
    }

    /**
     * Do we need to use the Solr index for reserves (true) or the ILS driver
     * (false)?
     *
     * @return bool
     */
    public function useIndex()
    {
        return $this->useIndex;
    }

    /**
     * Get reserve info from the catalog or Solr reserves index.
     *
     * @param string $course Course ID to use as limit (optional)
     * @param string $inst   Instructor ID to use as limit (optional)
     * @param string $dept   Department ID to use as limit (optional)
     *
     * @return array
     */
    public function findReserves($course = null, $inst = null, $dept = null)
    {
        // Special case -- process reserves info using index
        if ($this->useIndex()) {
            // get the selected reserve record from reserves index
            // and extract the bib IDs from it
            $result = $this->getController()->getServiceLocator()
                ->get('VuFind\Search')
                ->retrieve('SolrReserves', $course . '|' . $inst . '|' . $dept);
            $bibs = [];
            if ($result->getTotal() < 1) {
                return $bibs;
            }
            $record = current($result->getRecords());
            $instructor = $record->getInstructor();
            $course = $record->getCourse();
            foreach ($record->getItemIds() as $bib_id) {
                $bibs[] = [
                    'BIB_ID' => $bib_id,
                    'bib_id' => $bib_id,
                    'course' => $course,
                    'instructor' => $instructor
                ];
            }
            return $bibs;
        }

        // Default case -- find reserves info from the catalog
        $catalog = $this->getController()->getILS();
        return $catalog->findReserves($course, $inst, $dept);
    }
}