package org.tuefind.index;

import java.util.ArrayList;
import java.util.Collections;
import java.util.List;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

/** \class BCEReplacer
 *  \brief Provides mapping from non-standard German BCE references to standard German BCE references.
 */
public class BCEReplacer {
    Pattern pattern;
    String replacement;
    private BCEReplacer(final String pattern, final String replacement) {
        this.pattern = Pattern.compile(pattern);
        this.replacement = replacement;
    }

    /** \return If the regex matched all matches will be replaced by the replacemnt pattern o/w the original
        "subject" will be returned. */
    private String replaceAll(final String subject) {
        final Matcher matcher = this.pattern.matcher(subject);
        return matcher.replaceAll(this.replacement);
    }

    // Non-standard BCE year references and their standardized replacements. Backreferences for matched groups look like $N
    // where N is a single-digit ASCII character referecing the N-th matched group.
    private static List<BCEReplacer> bce_replacement_map;
    static {
        final ArrayList<BCEReplacer> tempList = new ArrayList<BCEReplacer>();
        tempList.add(new BCEReplacer("v(\\d+) ?- ?v(\\d+)", "$1 v.Chr.-$2 v.Chr"));
        tempList.add(new BCEReplacer("v(\\d+) ?- ?(\\d+)", "$1 v.Chr.-$2"));
        tempList.add(new BCEReplacer("v(\\d+)", "$1 v. Chr."));
        bce_replacement_map = Collections.unmodifiableList(tempList);
    }

    // Replaces all occurences of the first match found in bce_replacement_map, or returns the original string if no matches were found.
    public static String replaceBCEPatterns(final String s) {
        for (final BCEReplacer regex_and_replacement : bce_replacement_map) {
            final String patchedString = regex_and_replacement.replaceAll(s);
            if (!patchedString.equals(s))
                return patchedString;
        }

        return s;
    }
}
