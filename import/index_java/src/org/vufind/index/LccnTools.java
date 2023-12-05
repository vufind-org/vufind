package org.vufind.index;
/**
 * LCCN indexing routines.
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
import org.solrmarc.index.SolrIndexer;
import java.util.LinkedHashSet;
import java.util.Set;

/**
 * LCCN indexing routines.
 */
public class LccnTools
{
    /**
     * Normalize a single LCCN using the procedure specified at:
     *      http://www.loc.gov/marc/lccn-namespace.html#normalization
     * @param lccn
     * @return Normalized LCCN
     */
    public String getNormalizedLCCN(String lccn) {
        // Remove whitespace:
        lccn = lccn.replaceAll(" ", "");

        // Chop off anything following a forward slash:
        String[] parts = lccn.split("/", 2);
        lccn = parts[0];

        // Normalize any characters following a hyphen to at least six digits:
        parts = lccn.split("-", 2);
        if (parts.length > 1) {
            String secondPart = parts[1];
            while (secondPart.length() < 6) {
                secondPart = "0" + secondPart;
            }
            lccn = parts[0] + secondPart;
        }

        // Send back normalized LCCN:
        return lccn;
    }

    /**
     * Extract LCCNs from a record and return them in a normalized format
     * @param record
     * @param fieldSpec
     * @return Set of normalized LCCNs
     */
    public Set<String> getNormalizedLCCNs(Record record, String fieldSpec) {
        // Initialize return value:
        Set<String> result = new LinkedHashSet<String>();

        // Loop through relevant fields and normalize everything:
        for (String raw : SolrIndexer.instance().getFieldList(record, fieldSpec)) {
            String current = getNormalizedLCCN(raw);
            if (current != null && current.length() > 0) {
                result.add(current);
            }
        }

        // Send back results:
        return result;
    }

    /**
     * Extract LCCNs from a record and return them in a normalized format
     * @param record
     * @return Set of normalized LCCNs
     */
    public Set<String> getNormalizedLCCNs(Record record) {
        // Send in a default fieldSpec if none was provided by the user:
        return getNormalizedLCCNs(record, "010a");
    }

    /**
     * Extract the first valid LCCN from a record and return it in a normalized format
     * with an optional prefix added (helpful for guaranteeing unique IDs)
     * @param indexer
     * @param record
     * @param fieldSpec
     * @param prefix
     * @return Normalized LCCN
     */
    public String getFirstNormalizedLCCN(SolrIndexer indexer,
        Record record, String fieldSpec, String prefix) {
        // Loop through relevant fields in search of first valid LCCN:
        for (String raw : SolrIndexer.instance().getFieldList(record, fieldSpec)) {
            String current = getNormalizedLCCN(raw);
            if (current != null && current.length() > 0) {
                return prefix + current;
            }
        }

        // If we got this far, we couldn't find a valid value:
        return null;
    }

    /**
     * Extract the first valid LCCN from a record and return it in a normalized format
     * with an optional prefix added (helpful for guaranteeing unique IDs)
     * @param record
     * @param fieldSpec
     * @param prefix
     * @return Normalized LCCN
     */
    public String getFirstNormalizedLCCN(Record record, String fieldSpec, String prefix) {
        return getFirstNormalizedLCCN(SolrIndexer.instance(), record, fieldSpec, prefix);
    }

    /**
     * Extract the first valid LCCN from a record and return it in a normalized format
     * @param record
     * @param fieldSpec
     * @return Normalized LCCN
     */
    public String getFirstNormalizedLCCN(Record record, String fieldSpec) {
        // Send in a default prefix if none was provided by the user:
        return getFirstNormalizedLCCN(SolrIndexer.instance(), record, fieldSpec, "");
    }

    /**
     * Extract the first valid LCCN from a record and return it in a normalized format
     * @param record
     * @return Normalized LCCN
     */
    public String getFirstNormalizedLCCN(Record record) {
        // Send in a default fieldSpec/prefix if none were provided by the user:
        return getFirstNormalizedLCCN(SolrIndexer.instance(), record, "010a", "");
    }
}