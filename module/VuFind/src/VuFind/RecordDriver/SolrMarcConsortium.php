<?php
/**
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
 * @author   Chris Delis 
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 *
 ************************************************************************
 *
 * Model for Consortial (Deduped) MARC records in Solr.
 *
 * A. Non-consortial scenario (Single institution)
 *
 * 001 - Control Number (i.e., BIB ID)
 * 003 - Control Number Identifier (i.e., Institution code)
 *
 * E.g., Three bibliographic records that "match" (are effectively "the same" record in multiple institutions' repositories, i.e., they are all "copies" of one another)
 *
 * 001 - 123
 * 003 - UIUdb
 *
 * 001 - 123
 * 001 - EIUdb
 *
 * 001 - 456
 * 003 - XYZdb
 *
 * B. Consortial scenario (Multiple institutions, i.e., consortium)
 *
 * 001 - Control Number (i.e., BIB ID)
 * 035 $a - System control number (i.e., BIB ID *and* Institution code combined into one value)
 *
 * E.g., A deduped bibliographic record (coming from three matching "source" records)
 *
 * 001 - 567
 * 035 $a - (UIUdb)123
 * 035 $a - (EIUdb)123
 * 035 $a - (XYZdb)456
 *
 */
namespace VuFind\RecordDriver;
use VuFind\Exception\ILS as ILSException,
    VuFind\View\Helper\Root\RecordLink,
    VuFind\XSLT\Processor as XSLTProcessor;

/**
 * Model for MARC records in Solr.
 *
 * @category VuFind2
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
class SolrMarcConsortium extends SolrMarc
{

    /**
     * Get an array of information about record holdings, obtained in real-time
     * from the ILS.
     *
     * @return array
     */
    public function getRealTimeHoldings()
    {
        // We pass along all of the 035$a records to our ILS Driver.
        // The first one in the list is the ID of the aggregate (deduped) record.
        $the_035s = array();
        $the_035s[] = $this->getUniqueID();
        foreach ($this->getFieldArray('035', 'a', true) as $_035) {
            $the_035s[] = $_035;
        }
        return $this->hasILS()
            ? $this->holdLogic->getHoldings($the_035s)
            : array();
    }

}
