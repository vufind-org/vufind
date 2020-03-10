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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 */

import org.marc4j.marc.Record;
import org.marc4j.marc.DataField;

import org.solrmarc.index.SolrIndexer;

import java.util.HashMap;
import java.util.Iterator;
import java.util.LinkedHashSet;
import java.util.List;
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
        Set<String> result = new LinkedHashSet<String>();
        final HashMap<String, Set<String>> parsedTagList = getParsedTagList(tagList);
        final List fields = SolrIndexer.instance().getFieldSetMatchingTagList(record, tagList);
        if (fields != null) {
            Iterator fieldsIter = fields.iterator();
            while (fieldsIter.hasNext()) {
                DataField field = (DataField) fieldsIter.next();
                for (String subfields : parsedTagList.get(field.getTag())) {
                    String current = SolrIndexer.instance().getDataFromVariableField(field, "["+subfields+"]", " ", false);
                    if (null != current) {
                        result.add(current);
                    }
                }
            }
        }
        return result;
    }
}
