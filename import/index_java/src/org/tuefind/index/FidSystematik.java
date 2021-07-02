package org.tuefind.index;

import java.util.HashSet;
import java.util.Iterator;
import java.util.Set;
import java.util.regex.Matcher;
import java.util.regex.Pattern;
import org.marc4j.marc.DataField;
import org.marc4j.marc.Record;
import org.marc4j.marc.Subfield;
import org.marc4j.marc.VariableField;

public class FidSystematik extends TueFind {
    private final static Pattern FID_SYSTEMATIK_PATTERN = Pattern.compile("k\\d+(\\.\\d+)+");
    private final static Set<String> roman_numerals = new HashSet<String>() {
        {
            this.add("I");
            this.add("II");
            this.add("III");
            this.add("IV");
            this.add("V");
            this.add("VI");
            this.add("VII");
            this.add("VIII");
            this.add("IX");
            this.add("X");
            this.add("XI");
            this.add("XII");
            this.add("XIII");
            this.add("XIV");
            this.add("XV");
            this.add("XVI");
            this.add("XVII");
            this.add("XVIII");
            this.add("XIX");
            this.add("XX");
        }
    };

    private final static String INSTITUT_SYSTEMATIK_LETTERS = "ABCDEFGHJKLMNOPQX";


    public Set<String> getInstitutsSystematik(final Record record, final int level) {
        final Set<String> results = new HashSet<>();
        for (final VariableField variableField : record.getVariableFields("LOK")) {
            final DataField lokfield = (DataField) variableField;
            final Iterator<Subfield> subfieldsIter = lokfield.getSubfields().iterator();
            while (subfieldsIter.hasNext()) {
                Subfield subfield = subfieldsIter.next();
                char formatCode = subfield.getCode();
                final String dataString = subfield.getData(); // 852?

                // Looking for 852-Tag
                if (formatCode != '0' || !dataString.equals("852 1") || !subfieldsIter.hasNext())
                    continue;

                subfield = subfieldsIter.next();
                formatCode = subfield.getCode();
                if (formatCode != 'c')
                    continue;

                final String[] parts = subfield.getData().split(" ");
                if (parts.length < 2)
                    continue;

                if (!INSTITUT_SYSTEMATIK_LETTERS.contains(parts[0]) || !roman_numerals.contains(parts[1]))
                    continue;

                if (level == 1)
                    results.add(parts[0]);
                else // Assume level == 2.
                    results.add(parts[0] + parts[1]);
            }
        }

        return results;
    }

    public Set getInstitutsSystematik1(final Record record) {
        return getInstitutsSystematik(record, 1);
    }


    public Set getInstitutsSystematik2(final Record record) {
        return getInstitutsSystematik(record, 2);
    }


    public Set<String> getFIDSystematikHelper(final Record record, final int level) {
        final Set<String> results = new HashSet<>();
        for (final VariableField variableField : record.getVariableFields("LOK")) {
            final DataField lokfield = (DataField)variableField;
            final Iterator<Subfield> subfieldsIter = lokfield.getSubfields().iterator();
            while (subfieldsIter.hasNext()) {
                Subfield subfield = subfieldsIter.next();
                char formatCode = subfield.getCode();

                final String dataString = subfield.getData(); // 936ln?

                // Looking for 936ln-Tag
                if (formatCode != '0' || !dataString.equals("936ln") || !subfieldsIter.hasNext())
                    continue;

                subfield = subfieldsIter.next();
                formatCode = subfield.getCode();
                if (formatCode != 'a')
                    continue;

                String subfield_data = subfield.getData();
                final Matcher matcher = FID_SYSTEMATIK_PATTERN.matcher(subfield_data);
                if (!matcher.matches())
                    continue;

                // Remove leading "k":
                subfield_data = subfield_data.substring(1);

                if (level == 0) {
                    results.add(subfield_data);
                    continue;
                }

                final String[] parts = subfield_data.split("\\.");
                if (parts.length < 1)
                    continue;

                switch ( Math.min(level, parts.length)) {
                    case 1:
                        results.add(parts[0]);
                        break;
                    case 2:
                        results.add(parts[0] + "." + parts[1]);
                        break;
                    case 3:
                        results.add(parts[0] + "." + parts[1] + "." + parts[2]);
                        break;
                    case 4:
                        results.add(parts[0] + "." + parts[1] + "." + parts[2] + "." + parts[3]);
                        break;
                    case 5:
                        results.add(parts[0] + "." + parts[1] + "." + parts[2] + "." + parts[3] + "." + parts[4]);
                        break;
                    default:
                        throw new IllegalArgumentException("Level is " + level + ", but is only allowed to be between 1 and 5 (inclusive)!");
                }
            }
        }

        return results;
    }


    public Set getFIDSystematik(final Record record) {
        return getFIDSystematikHelper(record, 0);
    }


    public Set getFIDSystematik1(final Record record) {
        return getFIDSystematikHelper(record, 1);
    }


    public Set getFIDSystematik2(final Record record) {
        return getFIDSystematikHelper(record, 2);
    }


    public Set getFIDSystematik3(final Record record) {
        return getFIDSystematikHelper(record, 3);
    }


    public Set getFIDSystematik4(final Record record) {
        return getFIDSystematikHelper(record, 4);
    }


    public Set getFIDSystematik5(final Record record) {
        return getFIDSystematikHelper(record, 5);
    }

}
