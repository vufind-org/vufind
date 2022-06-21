package org.vufind.index;
/**
 * DOI indexing routines.
 *
 * Copyright (C) Villanova University 2020.
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

import java.util.LinkedHashSet;
import java.util.regex.Matcher;
import java.util.regex.Pattern;
import java.util.Set;
import org.marc4j.marc.Record;
import org.solrmarc.index.SolrIndexer;

/**
 * Call number indexing routines.
 */
public class DoiTools
{
    /**
     * Extract DOIs from URLs with the specified prefix
     * @param record MARC record
     * @param fieldSpec taglist for URL fields
     * @param baseUrl Base URL that will be followed by a DOI
     * @return Set of DOIs
     */
    public Set<String> getDoiFromUrl(final Record record, String fieldSpec, String baseUrl) {
        // Initialize our return value:
        Set<String> result = new LinkedHashSet<String>();

        // Loop through the specified MARC fields:
        Set<String> input = SolrIndexer.instance().getFieldList(record, fieldSpec);
        for (String current: input) {
            // If the base URL is found in the string, crop it off for our DOI!
            if (current.startsWith(baseUrl)) {
                result.add(current.substring(baseUrl.length()));
            }
        }

        // If we found no matches, return null; otherwise, return our results:
        return result.isEmpty() ? null : result;
    }

    /**
     * Extract DOIs from URLs matching the specified regular expression
     * @param record MARC record
     * @param fieldSpec taglist for URL fields
     * @param regEx Regular expression for matching URLs
     * @param groupIndex Index of the grouped expression in regEx containing the DOI to extract (passed as a String instead of an Integer due to SolrMarc limitations)
     * @return Set of DOIs
     */
    public Set<String> getDoisFromUrlWithRegEx(final Record record, String fieldSpec, String regEx, String groupIndex) {
        // Build the regular expression:
        Pattern pattern = Pattern.compile(regEx);

        // Initialize our return value:
        Set<String> result = new LinkedHashSet<String>();

        // Loop through the specified MARC fields:
        Set<String> input = SolrIndexer.instance().getFieldList(record, fieldSpec);
        for (String current: input) {
            // If the string matches our pattern, get the extracted DOI.
            Matcher matcher = pattern.matcher(current);
            if (matcher.find()) {
                result.add(matcher.group(Integer.parseInt(groupIndex)));
            }
        }

        // If we found no matches, return null; otherwise, return our results:
        return result.isEmpty() ? null : result;
    }
}
