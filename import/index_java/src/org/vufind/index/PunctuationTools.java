package org.vufind.index;
/**
 * Punctuation indexing routines.
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
import java.util.LinkedHashSet;
import java.util.Set;
import java.util.regex.Pattern;
import org.solrmarc.index.SolrIndexer;

/**
 * Punctuation indexing routines.
 */
public class PunctuationTools
{
    /**
     * Normalize trailing punctuation. This mimics the functionality built into VuFind's
     * textFacet field type, so that you can get equivalent values when indexing into
     * a string field. (Useful for docValues support). See CreatorTools.java for more
     * creator-specific punctuation stripping logic.
     *
     * Can return null
     *
     * @param record current MARC record
     * @param fieldSpec which MARC fields / subfields need to be analyzed
     * @return Set containing normalized values
     */
    public Set<String> normalizeTrailingPunctuation(Record record, String fieldSpec) {
        // Initialize our return value:
        Set<String> result = new LinkedHashSet<String>();

        // Loop through the specified MARC fields:
        Set<String> input = SolrIndexer.instance().getFieldList(record, fieldSpec);
        Pattern pattern = Pattern.compile("(?<!\b[A-Z])[.\\s]*$");
        for (String current: input) {
            result.add(pattern.matcher(current).replaceAll(""));
        }

        // If we found no matches, return null; otherwise, return our results:
        return result.isEmpty() ? null : result;
    }
}