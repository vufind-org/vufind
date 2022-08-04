package org.tuefind.index;

import java.io.FileNotFoundException;
import java.sql.Timestamp;
import java.time.format.DateTimeFormatter;
import java.util.ArrayList;
import java.util.Collection;
import java.util.HashSet;
import java.util.LinkedHashSet;
import java.util.List;
import java.util.Map;
import java.util.regex.Matcher;
import java.util.regex.Pattern;
import java.util.Set;
import java.util.TreeMap;
import java.util.TreeSet;
import java.util.logging.Logger;

import org.apache.commons.lang3.StringUtils;
import org.marc4j.marc.DataField;
import org.marc4j.marc.Record;
import org.marc4j.marc.Subfield;
import org.marc4j.marc.VariableField;

public class TueFindAuth extends TueFind {

    protected final static Pattern SORTABLE_STRING_REMOVE_PATTERN = Pattern.compile("[^\\p{Lu}\\p{Ll}\\p{Lt}\\p{Lo}\\p{N}]+");
    protected final static Pattern YEAR_RANGE_PATTERN = Pattern.compile("^([v]?)(\\d+)-([v]?)(\\d*)$");

    /**
     * normalize string due to specification for isni or orcid
     * @param input    input string to normalize
     * @param category category isni or orchid
     * @return normalized value depending on isni, orcid pattern
     */
    private String normalizeByCategory(String input, String category) {
        String stripped = stripDashesAndWhitespaces(input);
        if (stripped.length() != 16) {
            return input;
        } else {
            if (category.equalsIgnoreCase("isni")) {
                return stripped.substring(0, 4) + " " + stripped.substring(4, 8) + " " + stripped.substring(8, 12) + " " + stripped.substring(12, 16);
            } else if (category.equalsIgnoreCase("orcid")) {
                return stripped.substring(0, 4) + "-" + stripped.substring(4, 8) + "-" + stripped.substring(8, 12) + "-" + stripped.substring(12, 16);
            } else {
                return input;
            }
        }
    }

    private String stripDashesAndWhitespaces(String input) {
        return input.replaceAll("-", "").replaceAll("\\s", "");
    }

    /**
     * @param record          implicit call
     * @param tagNumber       e.g. 024, only use Datafields > 010
     * @param number2Category isni | orcid
     * @return
     */
    public Set<String> getNormalizedValuesByTag2(final Record record, final String tagNumber, final String number2Category) {

        @SuppressWarnings("unchecked")
        List<DataField> mainFields = (List<DataField>) (List<?>) record.getVariableFields(tagNumber);
        mainFields.removeIf(m -> m.getSubfield('2') == null);
        mainFields.removeIf(m -> m.getSubfield('a') == null);
        mainFields.removeIf(m -> m.getSubfield('2').getData().equalsIgnoreCase(number2Category) == false);

        if (mainFields.size() == 0) {
            return null;
        } else {
            Set<String> normalizedValues = new HashSet<String>();
            for (DataField mainField : mainFields) {
                final String numberA = mainField.getSubfield('a').getData();
                String normalizedValue = normalizeByCategory(numberA, number2Category);
                normalizedValues.add(normalizedValue);
            }
            return normalizedValues;
        }
    }

    protected String normalizeSortableString(String string) {
        // Only keep letters & numbers. For unicode character classes, see:
        // https://en.wikipedia.org/wiki/Template:General_Category_(Unicode)
        if (string == null)
            return null;
        //c.f. https://stackoverflow.com/questions/1466959/string-replaceall-vs-matcher-replaceall-performance-differences (21/03/16)
        return SORTABLE_STRING_REMOVE_PATTERN.matcher(string).replaceAll("").trim();
    }

    public Collection<String> normalizeSortableString(Collection<String> extractedValues) {
        Collection<String> results = new ArrayList<String>();
        for (final String value : extractedValues) {
            final String newValue = normalizeSortableString(value);
            if (newValue != null && !newValue.isEmpty())
                results.add(StringUtils.stripAccents(newValue));
        }
        return results;
    }

    public enum YearRangeType {
        RANGE, BBOX
    }

    protected String getYearRangeOfSubfieldValue(String subfieldData, YearRangeType rangeType) {
        final Matcher matcher = YEAR_RANGE_PATTERN.matcher(subfieldData);
        if (matcher.matches()) {
            String year1 = matcher.group(2);
            String year2 = matcher.group(4);

            boolean year1IsBC = matcher.group(1).equals("v");
            boolean year2IsBC = matcher.group(3).equals("v");

            if (year1.isEmpty()) {
                year1 = "9999";
                year1IsBC = true;
            }
            if (year2.isEmpty()) {
                year2 = "9999";
                year2IsBC = false;
            }

            Integer year1Int = Integer.parseInt(year1);
            Integer year2Int = Integer.parseInt(year2);
            if (!year1IsBC && !year2IsBC && year2Int < year1Int) {
                year1IsBC = true;
                year2IsBC = true;
            }

            if (year1Int != 9999 && year2Int != 9999) {
                if (year1IsBC == year2IsBC && (Math.abs(year1Int - year2Int) > 110))
                    return null;
                if (year1IsBC != year2IsBC && (year1Int > 110 || year2Int > 110))
                    return null;
            }

            // convert int back to string (e.g. -00 => 0)
            year1 = String.valueOf(year1Int);
            year2 = String.valueOf(year2Int);

            if (year1IsBC && year1Int != 0)
                year1 = "-" + year1;
            if (year2IsBC && year2Int != 0)
                year2 = "-" + year2;

            switch (rangeType) {
                case RANGE:
                    return "[" + year1 + " TO " + year2 + "]";
                case BBOX:
                    return getBBoxRangeValue(year1, year2);
            }
        }
        return null;
    }

    public String getYearRangeHelper(final Record record, YearRangeType rangeType) {
        List<String> values = getSubfieldValuesMatchingList(record, "400d:548a");
        for (final String value : values) {
            final String yearRange = getYearRangeOfSubfieldValue(value, rangeType);
            if (yearRange != null)
                return yearRange;
        }

        return null;
    }

    public String getYearRange(final Record record) {
        return getYearRangeHelper(record, YearRangeType.RANGE);
    }

    public String getYearRangeBBox(final Record record) {
        return getYearRangeHelper(record, YearRangeType.BBOX);
    }

    private boolean isInvalidYearNumber(String year, boolean isBc) {
        if (year.length() > 4) {
            return true;
        }
        try
        {
            int iYear = Integer.parseInt(year);
            if (isBc && iYear > 8000) {
                return true;
            }
            else if (!isBc && iYear > 2099) {
                return true;
            }
        } catch (NumberFormatException e) {
        }
        return false;
    }

    private String extractDate(String dateStr) {
        String retVal = dateStr.replaceAll("XX\\.", "01.").replaceAll("xx\\.", "01.");
        boolean bc = false;
        if (retVal.contains("v")) {
            bc = true;
            retVal = retVal.replaceAll("v", "");
        }

        retVal = retVal.replaceAll("ca.", "").trim();

        if (retVal.matches("[0-9\\.]+") == false) {
            return null;
        }
        else if (retVal.contains(".") && retVal.length() != retVal.replaceAll("\\.", "").length() + 2) {
            return null;
        }
        else if (retVal.length() == retVal.replaceAll("\\.", "").length() + 2) { //exact date
            String[] dateElems = retVal.split("\\.");
            String year = dateElems[2];
            String month = dateElems[1];
            String day = dateElems[0];
            if (month.equalsIgnoreCase("00")) {
                month = "01";
            }
            if (day.equalsIgnoreCase("00")) {
                day = "01";
            }
            if (day.length() > 2 && year.length() < 3) {
                day = dateElems[2];
                year = dateElems[0];
            }
            if (isInvalidYearNumber(year, bc)) {
                return null;
            } else {
                return (bc==true?"-":"") + year + "-" + month + "-" + day + "T00:00:00Z";
            }
        }
        else {
            if (isInvalidYearNumber(retVal, bc)) {
                return null;
            } else {
                return (bc==true?"-":"") + retVal + "-01-01T00:00:00Z"; //Format YYYY-MM-DDThh:mm:ssZ
            }
        }
    }

    public String getInitDate(final Record record) {
        String retVal = null;
        List<String> values = getSubfieldValuesMatchingList(record, "548a:400d:111d");
        for (String value : values) {
            if (value.contains("-")) {
                value = value.split("-")[0].trim();
            }
            String extractedDate = extractDate(value);
            if (value.length() == value.replaceAll("\\.", "").length() + 2) { //exact date
                if (extractedDate != null) {
                    return extractedDate;
                }
            }
            if (extractedDate != null) {
                retVal = extractedDate;
            }
        }
        return retVal;
    }

    public String getAuthorityType(final Record record) {
        final List<VariableField> fields100 = record.getVariableFields("100");
        if (fields100.size() > 0) {
            for (VariableField field100 : fields100) {
                if (((DataField)field100).getSubfield('t') != null) {
                    return "work";
                }
            }
            return "person";
        }
        final List<VariableField> fields110 = record.getVariableFields("110");
        if (fields110.size() > 0) {
            for (VariableField field110 : fields110) {
                if (((DataField)field110).getSubfield('t') != null) {
                    return "work";
                }
            }
            return "corporate";
        }
        final List<VariableField> fields111 = record.getVariableFields("111");
        if (fields111.size() > 0) {
            for (VariableField field111 : fields111) {
                if (((DataField)field111).getSubfield('t') != null) {
                    return "work";
                }
            }
            return "meeting";
        }
        if (record.getVariableFields("150").size() > 0)
            return "keyword";
        if (record.getVariableFields("151").size() > 0)
            return "place";
        if (record.getVariableFields("155").size() > 0)
            return "genre";
        return null;
    }

    protected Set<String> getExternalReferenceTypesFrom024(final Record record, Map<String, String> typesToMatch) {
        final Set<String> referenceTypes = new TreeSet<>();

        for (final VariableField variableField : record.getVariableFields("024")) {
            final DataField field = (DataField) variableField;
            if (field.getIndicator1() != '7')
                continue;

            final Subfield subfield_2 = field.getSubfield('2');
            if (subfield_2 == null)
                continue;

            String type = subfield_2.getData();
            if (typesToMatch.containsKey(type)) {
                referenceTypes.add(typesToMatch.get(type));
            }
        }
        return referenceTypes;
    }

    public Set<String> getExternalReferences(final Record record) {
        Map<String, String> typesToMatch = new TreeMap<>();
        typesToMatch.put("isni", "ISNI");
        typesToMatch.put("orcid", "ORCID");
        typesToMatch.put("viaf", "VIAF");
        typesToMatch.put("wikidata", "Wikidata");
        final Set<String> externalReferences = getExternalReferenceTypesFrom024(record, typesToMatch);

        String gndNumber = getFirstSubfieldValueWithPrefix(record, "035a","(DE-588)");
        if (gndNumber != null)
            externalReferences.add("GND");

        for (final String beaconId : getSubfieldValuesMatchingList(record, "BEAa")) {
            externalReferences.add(beaconId);
        }

        String wikipediaUrl = getFirstSubfieldValueWithPrefix(record, "670a","Wikipedia");
        if (wikipediaUrl != null)
            externalReferences.add("Wikipedia");

        return externalReferences;
    }

    public Set<String> getOccupations(final Record record, String langAbbrev) {
        Set<String> occupations = new LinkedHashSet<>();
        Set<String> retOccupations = new LinkedHashSet<>();

        for (final VariableField variableField : record.getVariableFields("374")) {
            final DataField field = (DataField) variableField;
            final Subfield subfield_a = field.getSubfield('a');
            if (subfield_a == null)
                continue;
            String occ = subfield_a.getData();
            occupations.add(occ);
        }

        for (final VariableField variableField : record.getVariableFields("550")) {
            final DataField field = (DataField) variableField;
            final Subfield subfield_a = field.getSubfield('a');
            final Subfield subfield_4 = field.getSubfield('4');
            if (subfield_a == null || subfield_4 == null)
                continue;
            String sub_4 = subfield_4.getData();
            if (sub_4 == null || !(sub_4.equalsIgnoreCase("berc") || sub_4.equalsIgnoreCase("beru")))
                continue;
            String occ = subfield_a.getData();
            occupations.add(occ);
        }
        for (String elem : occupations)
            retOccupations.add(getTranslation(elem, langAbbrev));

        return retOccupations;
    }
}
