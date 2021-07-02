package org.tuefind.index;

import java.util.*;
import org.marc4j.marc.DataField;
import org.marc4j.marc.Record;
import org.marc4j.marc.Subfield;
import org.marc4j.marc.VariableField;

public class IxTheoPublisher extends TueFind {
    private final static Map<String, String> replacements = new LinkedHashMap<>(128);
    private final static Set<String> replacementBlackList = new HashSet<>();

    static {
// delete commas at the end
        replacements.put("\\s*,$", "");
// delete comments
        replacements.put("\\[(.*)\\]", "");
// Substitute multiple spaces to single spaces
        replacements.put("\\s+", " ");
// insert space after a period if doesn't exists.
        replacements.put("\\.(?!\\s)", ". ");
        replacements.put("\\s-", "-");

// Replace some abbreviation:
        replacements.put(" und ", " u. ");
        replacements.put(" der ", " d. ");
// replacements.put("&", "und");

        replacements.put("Univ\\.-Verl", "Universitätsverlag");
        replacements.put("Verl\\.-Haus", "Verlagshaus");

        replacements.put("Verlag-Anst$", "Verlagsanstalt");
        replacements.put("Verl\\.-Anst$", "Verlagsanstalt");
        replacements.put("Verl-Anst\\.", "Verlagsanstalt");
        replacements.put("Verl\\.-Anst\\.", "Verlagsanstalt");

        replacements.put("Verlag-Anstalt$", "Verlagsanstalt");

        replacements.put("Verlag Anst$", "Verlagsanstalt");
        replacements.put("Verlag Anst\\.", "Verlagsanstalt");
        replacements.put("Verlag Anstalt", "Verlagsanstalt");
        replacements.put("Verlagsanst$", "Verlagsanstalt");
        replacements.put("Verlagsanst\\.", "Verlagsanstalt");

        replacements.put("^Verlag d\\. ", "");
        replacements.put("^Verlag der ", "");
        replacements.put("^Verlag des ", "");

        replacements.put("Verl\\.", "Verlag");
        replacements.put("Verl$", "Verlag");

        replacements.put("Akad$", "Akademie");
        replacements.put("Akad\\.", "Akademie");
        replacements.put("Akadem\\.", "Akademie");
        replacements.put("Akade\\.", "Akademie");
        replacements.put("Acad$", "Academy");
        replacements.put("Acad\\.", "Academy");

        replacements.put("wiss\\.", "wissenschaft");
        replacements.put("Wiss$", "Wissenschaft");
        replacements.put("Wiss\\.", "Wissenschaft");
        replacements.put("Lit$", "Literatur");
        replacements.put("Lit\\.", "Literatur");

        replacements.put("Anst$", "Anstalt");
        replacements.put("Anst\\.$", "Anstalt");
        replacements.put("anst$", "anstalt");
        replacements.put("anst\\.$", "anstalt");

        replacements.put("Kathol\\.", "Katholische");
        replacements.put("Evang\\.", "Evangelische");
        replacements.put("Ev\\.", "Evangelische");

        replacements.put("Pr$", "Press");
        replacements.put("Pr\\.", "Press");

        replacements.put("^Priv\\.", "Privilegierte");
        replacements.put("^Privileg\\.", "Privilegierte");

        replacements.put("Württ\\.", "Württembergische");
        replacements.put("Württemb\\.", "Württembergische");
        replacements.put("Bayer\\.", "Bayerische");

        replacements.put("ges\\.", "gesellschaft");
        replacements.put("ges$", "gesellschaft");
        replacements.put("Ges\\.", "Gesellschaft");
        replacements.put("Ges$", "Gesellschaft");

        replacements.put("Inst\\.", "Institution");

        replacements.put("Internat$", "International");
        replacements.put("T&T", "International");

        replacements.put("Univ\\. of", "University of");
        replacements.put("Univ\\.-Bibliothek", "Universitätsbibliothek");

        replacements.put("^1st ", "1st. ");
        replacements.put(" Fd ", " Field ");
        replacements.put(" Fd\\. ", " Field ");
        replacements.put(" Svy ", " Survey ");
        replacements.put(" Regt", " Regiment");
        replacements.put(" Regt\\.", " Regiment");
        replacements.put("RE$", " R. E.");

        replacements.put("Calif.", "California");

        replacementBlackList.add("Lit"); // Suppress rewriting of publisher Lit
    }

    /**
     * Get all available publishers from the record.
     *
     * @param record the record
     * @return publishers
     */
    public Set<String> getNormalizedPublishers(final Record record) {
        Set<String> publishers = new LinkedHashSet<>();
        final Set<String> rawPublishers = getRawPublishers(record);

        for (String publisher : rawPublishers) {
            publisher = publisher.trim();
            for (final Map.Entry<String, String> replacement : replacements.entrySet()) {
                // In some cases make sure that we abort after only some part of the rewriting has taken place
                if (replacementBlackList.contains(publisher))
                    break;
                publisher = publisher.replaceAll(replacement.getKey(), replacement.getValue()).trim();
            }

            if (!publisher.isEmpty()) {
                publishers.add(publisher);
            }
        }
        return publishers;
    }

    public Set<String> getPublishersOrUnassigned(final Record record) {
        final Set<String> publishers = getNormalizedPublishers(record);
        if (publishers == null || publishers.isEmpty()) {
            return TueFindBiblio.UNASSIGNED_SET;
        }
        return publishers;
    }

    public Set<String> getRawPublishers(final Record record) {
        final Set<String> publishers = new LinkedHashSet<>();

        // First check old-style 260b name:
        final List<VariableField> list260 = record.getVariableFields("260");
        for (final VariableField vf : list260) {
            final DataField df = (DataField) vf;
            final Subfield current = df.getSubfield('b');
            if (current != null) {
                publishers.add(current.getData());
            }
        }

        // Now track down relevant RDA-style 264b names; we only care about
        // copyright and publication names (and ignore copyright names if
        // publication names are present).
        final Set<String> pubNames = new LinkedHashSet<>();
        final Set<String> copyNames = new LinkedHashSet<>();
        final List<VariableField> list264 = record.getVariableFields("264");
        for (final VariableField vf : list264) {
            final DataField df = (DataField) vf;
            final Subfield currentName = df.getSubfield('b');
            if (currentName != null) {
                final char ind2 = df.getIndicator2();
                switch (ind2) {
                    case '1':
                        pubNames.add(currentName.getData());
                        break;
                    case '4':
                        copyNames.add(currentName.getData());
                        break;
                }
            }
        }
        if (!pubNames.isEmpty()) {
            publishers.addAll(pubNames);
        } else if (!copyNames.isEmpty()) {
            publishers.addAll(copyNames);
        }

        return publishers;
    }
}
