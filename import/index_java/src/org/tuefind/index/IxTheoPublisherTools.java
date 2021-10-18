package org.tuefind.index;

import java.util.*;
import org.marc4j.marc.DataField;
import org.marc4j.marc.Record;
import org.marc4j.marc.Subfield;
import org.marc4j.marc.VariableField;

public class IxTheoPublisherTools extends org.vufind.index.PublisherTools {
    protected final static Map<String, String> replacements = new LinkedHashMap<>(128);
    protected final static Set<String> replacementBlackList = new HashSet<>();

    // Placeholder values inspired by https://www.loc.gov/marc/bibliographic/concise/bd264.html
    protected final static String placeholderForCopyright = "[copyright not identified]";
    protected final static String placeholderForPublication = "[publication not identified]";
    protected final static String placeholderForPublisher = "[publisher not identified]";

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
// Substitute double quotes and brackets at beginning and end
        replacements.put("^\\((.*)\\)$", "$1");
        replacements.put("^\"(.*)\"$", "$1");
        replacements.put("^'(.*)'$", "$1");


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
        final Set<String> rawPublishers = getPublishers(record);

        for (String publisher : rawPublishers) {
            publisher = publisher.trim();
            for (final Map.Entry<String, String> replacement : replacements.entrySet()) {
                // In some cases make sure that we abort after only some part of the rewriting has taken place
                if (replacementBlackList.contains(publisher))
                    break;
                if (publisher.equals(placeholderForCopyright) || publisher.equals(placeholderForPublication) || publisher.equals(placeholderForPublisher))
                    break;
                publisher = publisher.replaceAll(replacement.getKey(), replacement.getValue()).trim();
            }

            if (!publisher.isEmpty()) {
                publishers.add(publisher);
            } else {
                publishers.add(placeholderForPublisher);
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

    /**
     * We need to override this parent function to store placeholder values if no subfield is found.
     * This is necessary to keep the publishers field in sync with other fields
     * which will be combined on PHP side.
     *
     * See also:
     * - VuFind\RecordDriver\DefaultRecord::getPublicationDetails()
     * - Issue #1339
     *
     * @param record
     *
     * @return
     */
    public Set<String> getPublishers(final Record record) {
        Set<String> publishers = new LinkedHashSet<String>();
        boolean publisherFound = false;
        boolean publicationFound = false;

        // First check 773d especially for articles of journals:
        List<VariableField> list773 = record.getVariableFields("773");
        for (VariableField vf : list773)
        {
            DataField df = (DataField) vf;
            for (Subfield current : df.getSubfields('d')) {
                String s773d = current.getData().trim();
                if (s773d.contains(":"))
                {
                    s773d = s773d.substring(s773d.indexOf(":") + 1).trim();
                    if (s773d.contains(","))
                    {
                        s773d = s773d.substring(0, s773d.lastIndexOf(",")).trim();
                        if (s773d.isEmpty() == false)
                        {
                            publishers.add(s773d);
                            publicationFound = true;
                        }
                    }
                }
            }
        }

        // Second check old-style 260b name:
        List<VariableField> list260 = record.getVariableFields("260");
        for (VariableField vf : list260)
        {
            DataField df = (DataField) vf;
            String currentString = "";
            for (Subfield current : df.getSubfields('b')) {
                currentString = currentString.trim().concat(" " + current.getData()).trim();
            }

            if (currentString.length() > 0) {
                publishers.add(currentString);
            }
        }

        // Now track down relevant RDA-style 264b names; we only care about
        // copyright and publication names (and ignore copyright names if
        // publication names are present).
        Set<String> pubNames = new LinkedHashSet<String>();
        Set<String> copyNames = new LinkedHashSet<String>();
        List<VariableField> list264 = record.getVariableFields("264");
        for (VariableField vf : list264)
        {
            DataField df = (DataField) vf;
            String currentString = "";
            for (Subfield current : df.getSubfields('b')) {
                currentString = currentString.trim().concat(" " + current.getData()).trim();
            }

            // TueFind: Add placeholder if the subfield is missing
            char ind2 = df.getIndicator2();
            switch (ind2)
            {
                case '1':
                    if (currentString.isEmpty()) {
                        currentString = placeholderForPublication;
                    }
                    else {
                        publicationFound = true;
                    }
                    pubNames.add(currentString);
                    break;
                case '4':
                    if (currentString.isEmpty()) {
                        currentString = placeholderForCopyright;
                    }
                    copyNames.add(currentString);
                    break;
            }
        }

        // if publication was set in article field 773d but not in 264b, "not identified" flag not needed here
        // for copyright flag no correction because we assume that 773d does not refer to copyright entry
        if (publicationFound == true)
        {
            pubNames.remove(placeholderForPublication);
        }
        else { // assuming that a "full publication (found) does contain a publisher and thus does not need a "publisher not identified"
            if (publishers.size() == 0) {
                publishers.add(placeholderForPublisher);
            }
        }

        if (pubNames.size() > 0) {
            publishers.addAll(pubNames);
        } else if (copyNames.size() > 0) {
            publishers.addAll(copyNames);
        }

        return publishers;
    }
}
