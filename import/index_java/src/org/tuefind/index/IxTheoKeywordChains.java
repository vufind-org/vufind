package org.tuefind.index;

import java.text.Collator;
import java.util.*;
import org.marc4j.marc.DataField;
import org.marc4j.marc.Record;
import org.marc4j.marc.Subfield;
import org.marc4j.marc.VariableField;

public class IxTheoKeywordChains extends TueFind {

    private final static String KEYWORD_DELIMITER = "/";
    private final static String SUBFIELD_CODES = "abcdetnpzf";
    private final static TueFindBiblio tueFindBiblio = new TueFindBiblio();

    public Set<String> getKeyWordChain(final Record record, final String fieldSpec, final String lang) {
        final List<VariableField> variableFields = record.getVariableFields(fieldSpec);
        final Map<Character, List<String>> keyWordChains = new HashMap<>();

        for (final VariableField variableField : variableFields) {
            final DataField dataField = (DataField) variableField;
            processField(dataField, keyWordChains, lang);
        }
        return concatenateKeyWordsToChains(keyWordChains);
    }

    /**
     * Create set version of the terms contained in the keyword chains
     */

    public Set<String> getKeyWordChainBag(final Record record, final String fieldSpec, final String lang) {
        final List<VariableField> variableFields = record.getVariableFields(fieldSpec);
        final Map<Character, List<String>> keyWordChains = new HashMap<>();
        final Set<String> keyWordChainBag = new HashSet<>();

        for (final VariableField variableField : variableFields) {
            final DataField dataField = (DataField) variableField;
            processField(dataField, keyWordChains, lang);
        }

        for (List<String> keyWordChain : keyWordChains.values()) {
            keyWordChainBag.addAll(keyWordChain);
        }

        return keyWordChainBag;
    }

    public Set<String> getKeyWordChainSorted(final Record record, final String fieldSpec, final String lang) {
        final List<VariableField> variableFields = record.getVariableFields(fieldSpec);
        final Map<Character, List<String>> keyWordChains = new HashMap<>();

        for (final VariableField variableField : variableFields) {
            final DataField dataField = (DataField) variableField;
            processField(dataField, keyWordChains, lang);

            // Sort keyword chain
            final char chainID = dataField.getIndicator1();
            final List<String> keyWordChain = getKeyWordChain(keyWordChains, chainID);
            Collator collator = Collator.getInstance(Locale.forLanguageTag(lang));
            Collections.sort(keyWordChain, collator);
        }
        return concatenateKeyWordsToChains(keyWordChains);
    }


    private boolean isSubfieldFollowedBySubfield(final List<Subfield> subfields, char subfieldCode, char subsequentSubfieldCode) {
        final Iterator<Subfield> subfields_iterator = subfields.iterator();
            while(subfields_iterator.hasNext()) {
                if (subfields_iterator.next().getCode() == subfieldCode &&
                    subfields_iterator.hasNext() && subfields_iterator.next().getCode() == subsequentSubfieldCode)
                    return true;
            }
         return false;
    }


    private boolean isSubfieldPrecededBySubfield(final List<Subfield> subfields, char subfieldCode, char precedingSubfieldCode) {
        return isSubfieldFollowedBySubfield(subfields, precedingSubfieldCode, subfieldCode);
    }


    /**
     * Extracts the keyword from data field and inserts it into the right
     * keyword chain.
     */
    private void processField(final DataField dataField, final Map<Character, List<String>> keyWordChains, String lang) {
        final char chainID = dataField.getIndicator1();
        final List<String> keyWordChain = getKeyWordChain(keyWordChains, chainID);

        boolean gnd_seen = false;
        StringBuilder keyword = new StringBuilder();
        // Collect elements within one chain in case there is a translation for a whole string
        List<String> complexElements = new ArrayList<String>();
        final List<Subfield> subfields = dataField.getSubfields();
        for (final Subfield subfield : subfields) {
            if (gnd_seen) {
                if (SUBFIELD_CODES.indexOf(subfield.getCode()) != -1) {
                    if (keyword.length() > 0) {
                        if (subfield.getCode() == 'z') {
                            keyword.append(" (" + tueFindBiblio.translateTopic(subfield.getData(), lang) + ")");
                            continue;
                        }
                        // We need quite a bunch of special logic here to group subsequent d and c fields
                        else if (subfield.getCode() == 'c') {
                            if (isSubfieldPrecededBySubfield(subfields, 'c', 'd')) {
                               keyword.append(" : " + subfield.getData() + ")");
                               continue;
                            }
                            else
                               keyword.append(", ");
                        } else if (subfield.getCode() == 'd') {
                            if (isSubfieldFollowedBySubfield(subfields, 'd', 'c'))
                                keyword.append(" (");
                            else
                                keyword.append(" ");

                        } else if (subfield.getCode() == 'f') {
                            keyword.append(" (" + subfield.getData() + ")");
                            continue;
                        } else if (subfield.getCode() == 'n')
                            keyword.append(" ");
                        else if (subfield.getCode() == 'p')
                            keyword.append(". ");
                        else
                            keyword.append(", ");
                    }
                    final String term = subfield.getData().trim();
                    keyword.append(tueFindBiblio.translateTopic(term, lang));
                    complexElements.add(term);
                } else if (subfield.getCode() == '9' && keyword.length() > 0 && subfield.getData().startsWith("g:")) {
                    // For Ixtheo-translations the specification in the g:-Subfield is appended in angle
                    // brackets, so this is a special case where we have to begin from scratch
                    final String specification = subfield.getData().substring(2);
                    final Subfield germanASubfield = dataField.getSubfield('a');
                    if (germanASubfield != null) {
                        final String translationCandidate = germanASubfield.getData() + " <" + specification + ">";
                        final String translation = tueFindBiblio.translateTopic(translationCandidate, lang);
                        keyword.setLength(0);
                        keyword.append(translation.replaceAll("<", "(").replaceAll(">", ")"));
                    }
                    else {
                        keyword.append(" (");
                        keyword.append(tueFindBiblio.translateTopic(specification, lang));
                        keyword.append(')');
                    }
                }

            } else if (subfield.getCode() == '2' && subfield.getData().equals("gnd"))
                gnd_seen = true;
        }

        if (keyword.length() > 0) {
            // Check whether there exists a translation for the whole chain
            final String complexTranslation = (complexElements.size() > 1) ?
                                              TueFindBiblio.getTranslationOrNull(String.join(" / ", complexElements), lang) : null;
            String keywordString = (complexTranslation != null) ? complexTranslation : keyword.toString();
            keywordString = keywordString.replace("/", "\\/");
            keyWordChain.add(lang.equals("de") ? BCEReplacer.replaceBCEPatterns(keywordString) : keywordString);
        }
    }

    /**
     * Finds the right keyword chain for a given chain id.
     *
     * @return A map containing the keywords of the chain (id -> keyword), or an
     *         empty map.
     */
    private List<String> getKeyWordChain(final Map<Character, List<String>> keyWordChains, final char chainID) {
        List<String> keyWordChain = keyWordChains.get(chainID);
        if (keyWordChain == null) {
            keyWordChain = new ArrayList<>();
            keyWordChains.put(chainID, keyWordChain);
        }

        return keyWordChain;
    }

    private Set<String> concatenateKeyWordsToChains(final Map<Character, List<String>> keyWordChains) {
        final List<Character> chainIDs = new ArrayList<>(keyWordChains.keySet());
        Collections.sort(chainIDs);

        final Set<String> chainSet = new LinkedHashSet<>();
        for (final Character chainID : chainIDs) {
            chainSet.add(keyChainToString(keyWordChains.get(chainID)));
        }
        return chainSet;
    }

    private String keyChainToString(final List<String> keyWordChain) {
        final StringBuilder buffer = new StringBuilder();
        for (final String keyWord : keyWordChain) {
            buffer.append(KEYWORD_DELIMITER);
            buffer.append(keyWord);
        }

        if (buffer.length() == 0) {
            return "";
        }
        // Discard leading keyword delimiter.
        return buffer.toString().substring(1);
    }
}
