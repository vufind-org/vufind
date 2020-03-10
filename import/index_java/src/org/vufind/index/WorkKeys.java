package org.vufind.index;

/**
 * Indexing routines for creating work keys for FRBR functionality.
 *
 * Copyright (C) The National Library of Finland 2020.
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
import org.marc4j.marc.DataField;
import org.marc4j.marc.Subfield;

import org.vufind.index.FieldSpecTools;

import java.text.Normalizer;
import java.text.Normalizer.Form;
import java.util.LinkedHashSet;
import java.util.Set;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

/**
 * Indexing routines for creating work keys for FRBR functionality.
 */
public class WorkKeys
{
    /**
     * Get all work identification keys for the record.
     *
     * @param  record              MARC record
     * @param  uniformTitleTagList The field specification for uniform titles
     * @param  titleTagList        The field specification for titles
     * @param  authorTagList       The field specification for authors
     * @param  includeRegEx        Regular expression defining characters to keep
     * @param  excludeRegEx        Regular expression defining characters to remove
     * @return set of keys
     */
    public Set<String> getWorkKeys(final Record record, final String uniformTitleTagList,
        final String titleTagList, final String authorTagList, final String includeRegEx,
        final String excludeRegEx

    ) {
        Set<String> workKeys = new LinkedHashSet<String>();

        // Uniform title
        final Set<String> uniformTitles = FieldSpecTools.getFieldsByTagList(record, uniformTitleTagList);
        for (String uniformTitle : uniformTitles) {
            final String normalizedUniformTitle = normalizeWorkKey(uniformTitle, includeRegEx, excludeRegEx);
            if (!normalizedUniformTitle.isEmpty()) {
                workKeys.add("UT ".concat(normalizedUniformTitle));
            }
        }

        // Title + Author
        final Set<String> titles = FieldSpecTools.getFieldsByTagList(record, titleTagList);
        final Set<String> authors = FieldSpecTools.getFieldsByTagList(record, authorTagList);

        for (String title : titles) {
            final String normalizedTitle = normalizeWorkKey(title, includeRegEx, excludeRegEx);
            if (!normalizedTitle.isEmpty()) {
                for (String author : authors) {
                    final String normalizedAuthor = normalizeWorkKey(author, includeRegEx, excludeRegEx);
                    if (!normalizedAuthor.isEmpty()) {
                        workKeys.add("AT ".concat(normalizedAuthor).concat(" ").concat(normalizedTitle));
                    }
                }
            }
        }

        return workKeys;
    }

    /**
     * Create a key string
     *
     * @param  s      String to normalize
     * @param  regExp Regular expression defining characters to strip out
     * @return cleaned up string
     */
    protected String normalizeWorkKey(final String s, final String includeRegEx, final String excludeRegEx)
    {
        String normalized = Normalizer.normalize(s, Normalizer.Form.NFKC);
        if (!includeRegEx.isBlank()) {
            StringBuilder result = new StringBuilder();
            Matcher m = Pattern.compile(includeRegEx).matcher(normalized);
            while (m.find()) {
                result.append(m.group());
            }
            normalized = result.toString();
        }
        if (!excludeRegEx.isBlank()) {
            normalized = normalized.replaceAll(excludeRegEx, "");
        }
        return normalized.toLowerCase();
    }
}
