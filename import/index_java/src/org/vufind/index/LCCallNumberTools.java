package org.vufind.index;
/**
 * LC Call number indexing routines.
 *
 * Copyright (C) Villanova University 2017.
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 */

import org.marc4j.marc.Record;
import org.solrmarc.callnum.LCCallNumber;
import org.solrmarc.index.SolrIndexer;

/**
 * LC Call number indexing routines.
 */
public class LCCallNumberTools
{
    /**
     * Extract the full call number from a record, stripped of spaces
     * @param record MARC record
     * @return Call number label
     * @deprecated Obsolete as of VuFind 2.4.
     *          This method exists only to support the VuFind call number search, version <= 2.3.
     *          As of VuFind 2.4, the munging for call number search in handled entirely in Solr.
     */
    @Deprecated
    public String getFullCallNumber(final Record record) {

        return(getFullCallNumber(record, "099ab:090ab:050ab"));
    }

    /**
     * Extract the full call number from a record, stripped of spaces
     * @param record MARC record
     * @param fieldSpec taglist for call number fields
     * @return Call number label
     * @deprecated Obsolete as of VuFind 2.4.
     *          This method exists only to support the VuFind call number search, version <= 2.3.
     *          As of VuFind 2.4, the munging for call number search in handled entirely in Solr.
     */
    @Deprecated
    public String getFullCallNumber(final Record record, String fieldSpec) {

        String val = SolrIndexer.instance().getFirstFieldVal(record, fieldSpec);

        if (val != null) {
            return val.toUpperCase().replaceAll(" ", "");
        } else {
            return val;
        }
    }

    /**
     * Extract the call number label from a record
     * @param record MARC record
     * @return Call number label
     */
    public String getCallNumberLabel(final Record record) {

        return getCallNumberLabel(record, "090a:050a");
    }

    /**
     * Extract the call number label from a record
     * @param record MARC record
     * @param fieldSpec taglist for call number fields
     * @return Call number label
     */
    public String getCallNumberLabel(final Record record, String fieldSpec) {

        String val = SolrIndexer.instance().getFirstFieldVal(record, fieldSpec);

        if (val != null) {
            int dotPos = val.indexOf(".");
            if (dotPos > 0) {
                val = val.substring(0, dotPos);
            }
            return val.toUpperCase();
        } else {
            return val;
        }
    }

    /**
     * Extract the subject component of the call number
     *
     * Can return null
     *
     * @param record MARC record
     * @return Call number subject letters
     */
    public String getCallNumberSubject(final Record record) {

        return(getCallNumberSubject(record, "090a:050a"));
    }

    /**
     * Extract the subject component of the call number
     *
     * Can return null
     *
     * @param record current MARC record
     * @return Call number subject letters
     */
    public String getCallNumberSubject(final Record record, String fieldSpec) {

        String val = SolrIndexer.instance().getFirstFieldVal(record, fieldSpec);

        if (val != null) {
            String [] callNumberSubject = val.toUpperCase().split("[^A-Z]+");
            if (callNumberSubject.length > 0)
            {
                return callNumberSubject[0];
            }
        }
        return(null);
    }

    /**
     * Normalize a single LC call number
     * @param record current MARC record
     * @return String Normalized LCCN
     */
    public String getFullCallNumberNormalized(final Record record) {

        return(getFullCallNumberNormalized(record, "099ab:090ab:050ab"));
    }

    /**
     * Normalize a single LC call number
     * @param record current MARC record
     * @param fieldSpec which MARC fields / subfields need to be analyzed
     * @return String Normalized LC call number
     */
    public String getFullCallNumberNormalized(final Record record, String fieldSpec) {

        // TODO: is the null fieldSpec still an issue?
        if (fieldSpec != null) {
            String cn = SolrIndexer.instance().getFirstFieldVal(record, fieldSpec);
            return (new LCCallNumber(cn)).getShelfKey();
        }
        // If we got this far, we couldn't find a valid value:
        return null;
    }
}