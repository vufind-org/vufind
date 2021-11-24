package org.tuefind.index;

import java.io.BufferedReader;
import java.io.File;
import java.io.FileReader;
import java.io.IOException;
import java.lang.invoke.MethodHandles;
import java.util.ArrayList;
import java.util.HashMap;
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

    protected static Set<String> getAllSubfieldsBut(final Record record, final String fieldSpecList, final String excludeSubfields) {
        final Set<String> extractedValues = new LinkedHashSet<>();
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

                for (final Subfield subfield : subfieldsToSearch) {
                    if (!excludeSubfields.contains(Character.toString(subfield.getCode())))
                        extractedValues.add(subfield.getData());
                }
            }
        }
        return extractedValues;
    }

    protected static Set<String> getAllSubfieldsBut(final Record record, final String fieldSpec, final String excludeSubfields, final char excludeIndicator1, final char excludeIndicator2) {
        final Set<String> extractedValues = new LinkedHashSet<>();
        List<Subfield> subfieldsToSearch = new ArrayList<>();
        final List<VariableField> fieldSpecFields = record.getVariableFields(fieldSpec.substring(0,3));
        for (final VariableField variableField : fieldSpecFields) {
            final DataField field = (DataField) variableField;
            if (field == null)
                continue;
            if (field.getIndicator1() == excludeIndicator1 && field.getIndicator2() == excludeIndicator2)
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

            for (final Subfield subfield : subfieldsToSearch) {
                if (!excludeSubfields.contains(Character.toString(subfield.getCode())))
                    extractedValues.add(subfield.getData());
            }
        }
        return extractedValues;
    }

    public static String getAllSubfieldsBut(final Record record, final String fieldSpecList, final String excludeSubfields, final String delimiter) {
        final Set<String> subfields = getAllSubfieldsBut(record, fieldSpecList, excludeSubfields);
        return  String.join(delimiter, subfields);
    }

    /**
     * Map 1-Dimensional range to 2-Dimensional BBox.
     *
     * ENVELOPE is symbolizing a box. Since we have only a line,
     * we have to map it to a box with a surface area > 0
     * for correct overlapping calculation.
     *
     * WKT/CQL ENVELOPE syntax:
     * ENVELOPE(minX, maxX, maxY, minY) => CAREFUL! THIS UNUSUAL ORDER IS CORRECT!
     *
     * see also: https://solr.apache.org/guide/7_0/spatial-search.html#bboxfield
     */
    public static String getBBoxRangeValue(final String from, final String to) {
        return "ENVELOPE(" + from + "," + to + ",1,0)";
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

    protected List<String> getSubfieldValuesMatchingList(final Record record, final String subfieldList) {
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

    /*
     * translation map cache
     */
    protected static Map<String, String> translation_map_en = new HashMap<String, String>();
    protected static Map<String, String> translation_map_fr = new HashMap<String, String>();
    protected static Map<String, String> translation_map_it = new HashMap<String, String>();
    protected static Map<String, String> translation_map_es = new HashMap<String, String>();
    protected static Map<String, String> translation_map_hant = new HashMap<String, String>();
    protected static Map<String, String> translation_map_hans = new HashMap<String, String>();
    protected static Map<String, String> translation_map_pt = new HashMap<String, String>();
    protected static Map<String, String> translation_map_ru = new HashMap<String, String>();
    protected static Map<String, String> translation_map_el = new HashMap<String, String>();

    /**
     * get translation map for normdata translations
     *
     * either get from cache or load from file, if cache empty
     *
     * @param langAbbrev
     *
     * @return Map<String, String>
     * @throws IllegalArgumentException
     */
    public static Map<String, String> getTranslationMap(final String langAbbrev) throws IllegalArgumentException {
        Map<String, String> translation_map;

        switch (langAbbrev) {
        case "en":
            translation_map = translation_map_en;
            break;
        case "fr":
            translation_map = translation_map_fr;
            break;
        case "it":
            translation_map = translation_map_it;
            break;
        case "es":
            translation_map = translation_map_es;
            break;
        case "hant":
            translation_map = translation_map_hant;
            break;
        case "hans":
            translation_map = translation_map_hans;
            break;
        case "pt":
            translation_map = translation_map_pt;
            break;
        case "ru":
            translation_map = translation_map_ru;
            break;
        case "el":
            translation_map = translation_map_el;
            break;
        default:
            throw new IllegalArgumentException("Invalid language shortcut: " + langAbbrev);
        }

        
        final String dir = "/usr/local/ub_tools/bsz_daten/";
        final String ext = "txt";
        final String basename = "normdata_translations";
        String translationsFilename = dir + basename + "_" + langAbbrev + "." + ext;

        // Only read the data from file if necessary
        if (translation_map.isEmpty() && (new File(translationsFilename).length() != 0)) {
            try {
                BufferedReader in = new BufferedReader(new FileReader(translationsFilename));
                String line;

                while ((line = in.readLine()) != null) {
                    // We now also have synonyms in the translation files
                    // These are not relevant in this context and are thus discarded
                    line = line.replaceAll(Pattern.quote("||") + ".*", "");
                    final String[] translations = line.split("\\|");
                    if (!translations[0].isEmpty() && !translations[1].isEmpty())
                        translation_map.put(translations[0], translations[1]);
                }
            } catch (IOException e) {
                logger.severe("Could not open file: " + e.toString());
                System.exit(1);
            }
        }

        return translation_map;
    }

    /*
     * try to translate a string
     *
     * @param string        string to translate
     * @param langAbbrev  language code
     *
     * @return              translated string if available in a foreign language, null else
     */
    public static String getTranslationOrNull(final String string, final String langAbbrev) {
       if (langAbbrev.equals("de"))
           return null;
       final Map<String, String> translationMap = getTranslationMap(langAbbrev);
       return translationMap.get(string);
    }


    /**
     * translate a string if available
     *
     * @param string        string to translate
     * @param langAbbrev  language code
     *
     * @return              translated string if available, else input string
     */
    public static String getTranslation(final String string, final String langAbbrev) {
        if (langAbbrev.equals("de")) {
            return string;
        }
        final Map<String, String> translationMap = getTranslationMap(langAbbrev);
        final String translatedString = translationMap.get(string);
        return (translatedString != null) ? translatedString : string;
    }
}
