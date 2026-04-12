package org.vufind.index;
/**
 * Indexing routines for dealing with SolrMarc field specs.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 */

import org.marc4j.marc.Record;
import org.marc4j.marc.DataField;
import org.marc4j.marc.Subfield;
import org.marc4j.marc.VariableField;

import org.solrmarc.index.extractor.formatter.FieldFormatter;
import org.solrmarc.index.extractor.formatter.FieldFormatterBase;
import org.solrmarc.index.extractor.formatter.FieldFormatter.eCleanVal;
import org.solrmarc.index.SolrIndexer;

import java.lang.StringBuilder;
import java.util.HashMap;
import java.util.LinkedHashSet;
import java.util.List;
import java.util.regex.Pattern;
import java.util.regex.Matcher;
import java.util.Set;

/**
 * Indexing routines for dealing with SolrMarc field specs.
 */
public class FieldSpecTools
{
    /**
     * Parse a SolrMarc fieldspec into a map of tag name to set of subfield strings
     * (note that we need to map to a set rather than a single string, because the
     * same tag may repeat with different subfields to extract different sections
     * of the same field into distinct values).
     *
     * @param tagList The field specification to parse
     * @return HashMap
     */
    public static HashMap<String, Set<String>> getParsedTagList(String tagList)
    {
        String[] tags = tagList.split(":");//convert string input to array
        HashMap<String, Set<String>> tagMap = new HashMap<String, Set<String>>();
        //cut tags array up into key/value pairs in hash map
        Set<String> currentSet;
        for(int i = 0; i < tags.length; i++){
            String tag = tags[i].substring(0, 3);
            if (!tagMap.containsKey(tag)) {
                currentSet = new LinkedHashSet<String>();
                tagMap.put(tag, currentSet);
            } else {
                currentSet = tagMap.get(tag);
            }
            currentSet.add(tags[i].substring(3));
        }
        return tagMap;
    }

    /**
     * Get field data specified by a SolrMarc tag list
     *
     * @param record  Record
     * @param tagList The field specification
     *
     * @return Set
     */
    public static final Set<String> getFieldsByTagList(final Record record, final String tagList)
    {
        return getFieldsByTagList(record, tagList, false);
    }

    /**
     * Get field data specified by a SolrMarc tag list
     *
     * @param record          Record
     * @param tagList         The field specification
     * @param removeNonFiling Whether to remove non-filing characters
     *
     * @return Set
     */
    public static final Set<String> getFieldsByTagList(final Record record, final String tagList, Boolean removeNonFiling)
    {
        Set<String> result = new LinkedHashSet<String>();
        final HashMap<String, Set<String>> parsedTagList = getParsedTagList(tagList);
        final FieldFormatter formatter = removeNonFiling
            ? new FieldFormatterBase(false).addCleanVal(eCleanVal.STRIP_INDICATOR) : null;
        for (VariableField variableField : SolrIndexer.instance().getFieldSetMatchingTagList(record, tagList)) {
            DataField field = (DataField) variableField;
            for (String subfields : parsedTagList.get(field.getTag())) {
                String current = getFieldData(field, subfields, formatter);
                if (null != current) {
                    result.add(current);
                }
            }
        }
        return result;
    }

    /**
     * Get subfields from a data field
     *
     * @param dataFiel     Data field
     * @param subfieldCode Subfield codes to get
     * @param formatter    Formatter to use (or null)
     *
     * @return Set
     */
    protected static final String getFieldData(DataField dataField, String subfieldCodes, FieldFormatter formatter)
    {
        StringBuilder result = new StringBuilder(64);
        final List<Subfield> subfields = dataField.getSubfields();
        for (Subfield subfield : subfields) {
            final char subfieldCode = subfield.getCode();
            if (subfieldCodes.indexOf(subfieldCode) != -1) {
                if (result.length() > 0) {
                    result.append(' ');
                }
                final String subfieldData = subfield.getData().trim();
                if (null != formatter) {
                    result.append(formatter.cleanData(dataField, 'a' == subfieldCode, subfieldData));
                } else {
                    result.append(subfieldData);
                }
            }
        }

        return result.length() > 0 ? result.toString() : null;
    }


    private static Pattern unicodeEscape = Pattern.compile("(?i)\\\\u([0-9a-f]{4})");

    /**
     * Convert an escaped separator into its actual String value.
     *
     * @param separatorWithEscapes  a string representing the delimiter to use between subfields,
     *                              which can contain Unicode escape sequences in the format \\uXXXX.
     * @return                      The string version of the escaped separator
     */
    private static String getUnescapedSeparator(String separatorWithEscapes)
    {
        StringBuilder separator = new StringBuilder(separatorWithEscapes);
        Matcher m = unicodeEscape.matcher(separator);

        while (m.find()) {
            int codepoint = Integer.parseInt(m.group(1), 16);
            separator.replace(m.start(), m.end(), new String(Character.toChars(codepoint)));
            m.reset();
        }

        return separator.toString();
    }

    /**
     * Retrieves all subfields from a record, separated by a specified UTF-8 delimiter.
     *
     * This method takes a MARC record and a specification for the fields to extract, and returns
     * a set of strings representing the subfields found, concatenated with a specified separator.
     * The separator can include Unicode escape sequences, which will be converted to their corresponding
     * characters.
     *
     * @param record                the MARC record from which to extract subfields.
     * @param fieldSpec             a string specifying the fields and subfields to retrieve.
     * @param separatorWithEscapes  a string representing the delimiter to use between subfields,
     *                              which can contain Unicode escape sequences in the format \\uXXXX.
     * @return                      a set of strings with the concatenated subfields, separated by the given delimiter.
     */
    public static Set<String> getAllSubfieldsUTF8Delimited(final Record record, String fieldSpec, String separatorWithEscapes)
    {
        return org.solrmarc.index.SolrIndexer.instance().getAllSubfields(record, fieldSpec, getUnescapedSeparator(separatorWithEscapes));
    }

    /**
     * Retrieves all alphabetic subfields from a record, separated by a specified UTF-8 delimiter.
     *
     * This method takes a MARC record and a specification for the fields to extract, and returns
     * a set of strings representing the subfields found, concatenated with a specified separator.
     * The separator can include Unicode escape sequences, which will be converted to their corresponding
     * characters.
     *
     * @param record                the MARC record from which to extract subfields.
     * @param fieldSpec             a string specifying the fields and subfields to retrieve.
     * @param separatorWithEscapes  a string representing the delimiter to use between subfields,
     *                              which can contain Unicode escape sequences in the format \\uXXXX.
     * @return                      a set of strings with the concatenated subfields, separated by the given delimiter.
     */
    public static Set<String> getAllAlphaSubfieldsUTF8Delimited(final Record record, String fieldSpec, String separatorWithEscapes)
    {
        // getAllAlphaSubfields has a signature that's inconsistent with getAllSubfields (above); we need to
        // construct a join modifier using the separator instead of directly providing the separator as a result.
        return org.solrmarc.index.SolrIndexer.instance().getAllAlphaSubfields(record, fieldSpec, "join(\"" + getUnescapedSeparator(separatorWithEscapes) + "\")");
    }
}
