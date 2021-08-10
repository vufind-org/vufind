package org.tuefind.index;

import java.lang.invoke.MethodHandles;
import java.util.ArrayList;
import java.util.HashMap;
import java.util.Map.Entry;
import java.util.HashSet;
import java.util.Iterator;
import java.util.LinkedHashSet;
import java.util.LinkedList;
import java.util.List;
import java.util.Map;
import java.util.Set;
import java.util.TreeSet;
import java.util.concurrent.ConcurrentHashMap;
import java.util.logging.Logger;
import java.util.regex.Pattern;


import org.marc4j.marc.DataField;
import org.marc4j.marc.Record;
import org.marc4j.marc.Subfield;
import org.marc4j.marc.VariableField;
import org.solrmarc.index.SolrIndexer;
import org.solrmarc.index.SolrIndexerMixin;

public class TueFind extends SolrIndexerMixin {

    /**
     * Initialize Logger using MethodHandles.lookup().
     * This was each subclass will have its own logger with the correct name
     * without needing to explicitly override it in the subclass itself.
     */
    protected static final Logger logger = Logger.getLogger(MethodHandles.lookup().lookupClass().getName());

    protected static final Pattern SORTABLE_STRING_REMOVE_PATTERN = Pattern.compile("[^\\p{Lu}\\p{Ll}\\p{Lt}\\p{Lo}\\p{N}]+");

    protected static Set<String> getAllSubfieldsBut(final Record record, final String fieldSpecList, char excludeSubfield) {
        final Set<String> extractedValues = new TreeSet<>();
        final String[] fieldSpecs = fieldSpecList.split(":");
        List<Subfield> subfieldsToSearch = new ArrayList<>();
        for (final String fieldSpec : fieldSpecs) {
            final List<VariableField> fieldSpecFields = record.getVariableFields(fieldSpec.substring(0,3));
            for (final VariableField variableField : fieldSpecFields) {
                 final DataField field = (DataField) variableField;
                 if (field == null)
                     continue;
                 // Differentiate between field and subfield specifications:
                 if (fieldSpec.length() == 3 + 1)
                     subfieldsToSearch = field.getSubfields(fieldSpec.charAt(3));
                 else if (fieldSpec.length() == 3)
                     subfieldsToSearch = field.getSubfields();
                 else {
                     logger.severe("in TueFindBase.getAllSubfieldsBut: invalid field specification: " + fieldSpec);
                     System.exit(1);
                 }
                 for (final Subfield subfield : subfieldsToSearch)
                     if (subfield.getCode() != excludeSubfield)
                         extractedValues.add(subfield.getData());
            }
        }
        return extractedValues;
    }

    protected interface SubfieldMatcher {
        boolean matched(final Subfield subfield);
    }

    /**
     * Finds the first subfield which is nonempty.
     *
     * @param dataField
     *            the data field
     * @param subfieldIDs
     *            the subfield identifiers to search for
     * @return a nonempty subfield or null
     */
    protected Subfield getFirstNonEmptySubfield(final DataField dataField, final char... subfieldIDs) {
        for (final char subfieldID : subfieldIDs) {
            for (final Subfield subfield : dataField.getSubfields(subfieldID)) {
                if (subfield != null && subfield.getData() != null && !subfield.getData().isEmpty()) {
                    return subfield;
                }
            }
        }
        return null;
    }

    protected static String getFirstSubfieldValue(final Record record, final String tag, final char subfieldCode) {
        if (tag == null || tag.length() != 3)
            throw new IllegalArgumentException("bad tag (null or length != 3)!");

        for (final VariableField variableField : record.getVariableFields(tag)) {
            final DataField dataField = (DataField) variableField;
            final Subfield subfield = dataField.getSubfield(subfieldCode);
            if (subfield != null)
                return subfield.getData();
        }

        return null;
    }

    /**
     * Get all subfields matching a tagList definition
     * (Iteration taken from VuFind's CreatorTools.getAuthorsFilteredByRelator)
     *
     * @param record       The record
     * @param subfieldList Like in marc.properties, e.g. "110ab:111abc:710ab:711ab"
     * @param matcher      Instance of SubfieldMatcher or null
     * @return             A list with all subfields matching the tagList
     */
    protected List<Subfield> getSubfieldsMatchingList(final Record record, final String subfieldList, final SubfieldMatcher matcher) {
        List<Subfield> returnSubfields = new ArrayList<>();
        HashMap<String, Set<String>> parsedTagList = getParsedTagList(subfieldList);
        List<VariableField> fields = SolrIndexer.instance().getFieldSetMatchingTagList(record, subfieldList);

        for (final VariableField variableField : fields) {
            DataField field = (DataField)variableField;
            for (final String subfieldCharacters : parsedTagList.get(field.getTag())) {
                final List<Subfield> subfields = field.getSubfields("[" + subfieldCharacters + "]");
                for (final Subfield subfield : subfields) {
                    if (matcher == null || matcher.matched(subfield))
                        returnSubfields.add(subfield);
                }
            }
        }
        return returnSubfields;
    }

    protected List<Subfield> getSubfieldsMatchingList(final Record record, final String subfieldList) {
        return getSubfieldsMatchingList(record, subfieldList, null);
    }

    protected List<String> getSubfieldValuesMatchlingList(final Record record, final String subfieldList) {
        List<Subfield> subfields = getSubfieldsMatchingList(record, subfieldList);
        List<String> values = new ArrayList<>();
        for (final Subfield subfield : subfields) {
            values.add(subfield.getData());
        }
        return values;
    }

    protected Subfield getFirstSubfieldWithPrefix(final Record record, final String subfieldList, final String prefix) {
        SubfieldMatcher matcher = new SubfieldMatcher() {
            public boolean matched(final Subfield subfield) {
                return subfield.getData().startsWith(prefix);
            }
        };
        final List<Subfield> subfields = getSubfieldsMatchingList(record, subfieldList, matcher);
        for (final Subfield subfield : subfields) {
            final String data = subfield.getData();
            if (data.startsWith(prefix))
                return subfield;
        }
        return null;
    }

    public String getFirstSubfieldValueWithPrefix(final Record record, final String subfieldList, final String prefix) {
        final Subfield subfield = getFirstSubfieldWithPrefix(record, subfieldList, prefix);
        if (subfield == null)
            return null;
        return subfield.getData().substring(prefix.length());
    }

    public Set<String> getSubfieldValuesWithPrefix(final Record record, final String subfieldList, final String prefix) {
        Set<String> results = new HashSet<>();
        SubfieldMatcher matcher = new SubfieldMatcher() {
            public boolean matched(final Subfield subfield) {
                return subfield.getData().startsWith(prefix);
            }
        };
        final List<Subfield> subfields = getSubfieldsMatchingList(record, subfieldList, matcher);
        for (final Subfield subfield : subfields) {
            final String data = subfield.getData();
            if (data.startsWith(prefix))
                results.add(data.substring(prefix.length()));
        }
        return results;
    }

    protected String normalizeSortableString(String string) {
        // Only keep letters & numbers. For unicode character classes, see:
        // https://en.wikipedia.org/wiki/Template:General_Category_(Unicode)
        if (string == null)
            return null;
        //c.f. https://stackoverflow.com/questions/1466959/string-replaceall-vs-matcher-replaceall-performance-differences (21/03/16)
        return SORTABLE_STRING_REMOVE_PATTERN.matcher(string).replaceAll("").trim();
    }

    /**
     * At the moment used for time range(s) parsing, if more than one timerange exists in TIM-field, minimum lower and maximum upper have to be implemented
     * @param record
     * @param fieldTag
     * @param subfieldTag
     * @param partNumber starting with zero
     * @return
     */
    public String getRangeSplitByUnderscore(final Record record, final String fieldTag, final String subfieldTag, final String partNumber) {
        final DataField field = (DataField) record.getVariableField(fieldTag);
        if (field == null)
            return null;

        if (subfieldTag.trim().length() < 1)
            return null;

        try {
            Integer part = Integer.parseInt(partNumber.trim());

            final Subfield subfield = field.getSubfield(subfieldTag.trim().charAt(0));
            final String[] parts = subfield.getData().split("_");

            if (parts == null || parts.length == 0 || !(part < parts.length))
                return null;

            return parts[part];
        } catch (NumberFormatException ne) {
            return null;
        }
    }

    /**
     * This function is copied from VuFind's CreatorTools
     * (can't be re-used since it's protected)
     */
    protected HashMap<String, Set<String>> getParsedTagList(final String tagList) {
        final String[] tags = tagList.split(":");//convert string input to array
        HashMap<String, Set<String>> tagMap = new HashMap<String, Set<String>>();
        //cut tags array up into key/value pairs in hash map
        Set<String> currentSet;
        for (final String tagsItem : tags) {
            String tag = tagsItem.substring(0, 3);
            if (!tagMap.containsKey(tag)) {
                currentSet = new LinkedHashSet<String>();
                tagMap.put(tag, currentSet);
            } else {
                currentSet = tagMap.get(tag);
            }
            currentSet.add(tagsItem.substring(3));
        }
        return tagMap;
    }

    protected static String getFirstSubfieldValue(final Record record, final String tag, final char indicator1, final char indicator2, final char subfieldCode) {
        if (tag == null || tag.length() != 3)
            throw new IllegalArgumentException("bad tag (null or length != 3)!");

        for (final VariableField variableField : record.getVariableFields(tag)) {
            final DataField dataField = (DataField) variableField;
            if (dataField.getIndicator1() != indicator1 || dataField.getIndicator2() != indicator2)
                continue;

            final Subfield subfield = dataField.getSubfield(subfieldCode);
            if (subfield != null)
                return subfield.getData();
        }

        return null;
    }

    public Set<String> getAuthorsAndIds(final Record record, String tagList) {
        final String separator = ":";
        final String ID_SEARCH_PREFIX = "(DE-627)";
        final String ID_REGEX_PREFIX = "\\(DE-627\\)";
        Set<String> result = new HashSet<>();

        Map<String, String> authorToId = new HashMap<>();

        if (tagList.contains(":") == false && tagList.trim().length() > 2) {
            tagList = tagList + ":";
        }

        for (String tag : tagList.split(":")) {
            if (tag == null || tag.isEmpty()) {
                continue;
            }

            for (final VariableField variableField : record.getVariableFields(tag)) {
                final DataField dataField = (DataField) variableField;
                final Subfield subfield_a = dataField.getSubfield('a');
                if (subfield_a == null || subfield_a.getData().isEmpty()) {
                    continue;
                }
                final List<Subfield> subfields_0 = dataField.getSubfields('0');
                String authorName = subfield_a.getData();
                for (Subfield subfield_0 : subfields_0) {
                    String author_id = subfield_0.getData();
                    if (author_id.contains(ID_SEARCH_PREFIX)) {
                        authorToId.put(authorName, author_id.replaceAll(ID_REGEX_PREFIX, "").trim());
                    }
                    else if (authorToId.containsKey(authorName) == false){
                        authorToId.put(authorName, "");
                    }
                }
            }
        }

        for (Entry<String,String> pair : authorToId.entrySet()){
            result.add(pair.getValue() + separator + pair.getKey());
        }

        return result;
    }
}
