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

import com.ibm.icu.text.Transliterator;

import java.text.Normalizer;
import java.util.Hashtable;
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
     * Cache for Transliterator instances to avoid having to recreate them
     */
    Hashtable<String, Transliterator> transliterators = new Hashtable<String, Transliterator>();

    /**
     * Get all work identification keys for the record.
     *
     * @param  record               MARC record
     * @param  uniformTitleTagList  The field specification for uniform titles
     * @param  titleTagList         The field specification for titles
     * @param  titleTagListNF       The field specification for titles with
     * non-filing characters removed
     * @param  authorTagList        The field specification for authors
     * @param  includeRegEx         Regular expression defining characters to keep
     * @param  excludeRegEx         Regular expression defining characters to remove
     * @param  transliterationRules ICU transliteration rules to be applied before
     * the include and exclude regex's. See
     * https://unicode-org.github.io/icu/userguide/transforms/general/#icu-transliterators
     * for more information.
     * @return set of keys
     */
    public Set<String> getWorkKeys(final Record record, final String uniformTitleTagList,
        final String titleTagList, final String titleTagListNF, final String authorTagList,
        final String includeRegEx, final String excludeRegEx, final String transliterationRules

    ) {
        Set<String> workKeys = new LinkedHashSet<String>();

        final Transliterator transliterator = transliterationRules.isEmpty()
            ? null : this.transliterators.computeIfAbsent(transliterationRules, rules ->
                Transliterator.createFromRules("workkeys", rules, Transliterator.FORWARD));

        // Uniform title
        final Set<String> uniformTitles = FieldSpecTools.getFieldsByTagList(record, uniformTitleTagList);
        for (String uniformTitle : uniformTitles) {
            final String normalizedUniformTitle
                = normalizeWorkKey(uniformTitle, includeRegEx, excludeRegEx, transliterator);
            if (!normalizedUniformTitle.isEmpty()) {
                workKeys.add("UT ".concat(normalizedUniformTitle));
            }
        }

        // Title + Author
        Set<String> titles = FieldSpecTools.getFieldsByTagList(record, titleTagList);
        titles.addAll(FieldSpecTools.getFieldsByTagList(record, titleTagListNF, true));
        final Set<String> authors = FieldSpecTools.getFieldsByTagList(record, authorTagList);

        if (!authors.isEmpty()) {
            for (String title : titles) {
                final String normalizedTitle
                    = normalizeWorkKey(title, includeRegEx, excludeRegEx, transliterator);
                if (!normalizedTitle.isEmpty()) {
                    for (String author : authors) {
                        final String normalizedAuthor
                            = normalizeWorkKey(author, includeRegEx, excludeRegEx, transliterator);
                        if (!normalizedAuthor.isEmpty()) {
                            workKeys.add("AT ".concat(normalizedAuthor).concat(" ").concat(normalizedTitle));
                        }
                    }
                }
            }
        }

        return workKeys;
    }

    /**
     * Create a key string
     *
     * @param  s              String to normalize
     * @param  includeRegEx   Regular expression defining characters to keep
     * @param  excludeRegEx   Regular expression defining characters to remove
     * @param  transliterator Optional ICU transliterator to use
     */
    protected String normalizeWorkKey(final String s, final String includeRegEx, final String excludeRegEx,
        final Transliterator transliterator
    ) {
        String normalized = transliterator != null ? transliterator.transliterate(s)
            : Normalizer.normalize(s, Normalizer.Form.NFKC);
        if (!includeRegEx.chars().allMatch(Character::isWhitespace)) {
            StringBuilder result = new StringBuilder();
            Matcher m = Pattern.compile(includeRegEx).matcher(normalized);
            while (m.find()) {
                result.append(m.group());
            }
            normalized = result.toString();
        }
        if (!excludeRegEx.chars().allMatch(Character::isWhitespace)) {
            normalized = normalized.replaceAll(excludeRegEx, "");
        }
        int length = normalized.length();
        return normalized.toLowerCase().substring(0, length > 255 ? 255 : length);
    }
}
