package org.vufind.index;
/**
 * Indexing routines for dealing with creators and relator terms.
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
import org.marc4j.marc.Subfield;
import org.marc4j.marc.DataField;
import org.marc4j.marc.VariableField;
import org.solrmarc.index.SolrIndexer;
import org.apache.log4j.Logger;
import java.util.Arrays;
import java.util.ArrayList;
import java.util.HashMap;
import java.util.LinkedHashSet;
import java.util.LinkedList;
import java.util.List;
import java.util.Map;
import java.util.regex.Pattern;
import java.util.Set;
import java.util.concurrent.ConcurrentHashMap;

/**
 * Indexing routines for dealing with creators and relator terms.
 */
public class CreatorTools
{
    // Initialize logging category
    static Logger logger = Logger.getLogger(CreatorTools.class.getName());

    private ConcurrentHashMap<String, String> relatorSynonymLookup = RelatorContainer.instance().getSynonymLookup();
    private Set<String> knownRelators = RelatorContainer.instance().getKnownRelators();
    private Set<String> relatorPrefixesToStrip = RelatorContainer.instance().getRelatorPrefixesToStrip();
    private Set<Pattern> punctuationRegEx = PunctuationContainer.instance().getPunctuationRegEx();
    private Set<String> punctuationPairs = PunctuationContainer.instance().getPunctuationPairs();
    private Set<String> untrimmedAbbreviations = PunctuationContainer.instance().getUntrimmedAbbreviations();

    /**
     * Extract all valid relator terms from a list of subfields using a whitelist.
     * @param subfields        List of subfields to check
     * @param permittedRoles   Whitelist to check against
     * @param indexRawRelators Should we index relators raw, as found
     * in the MARC (true) or index mapped versions (false)?
     * @return Set of valid relator terms
     */
    public Set<String> getValidRelatorsFromSubfields(List<Subfield> subfields, List<String> permittedRoles, Boolean indexRawRelators)
    {
        Set<String> relators = new LinkedHashSet<String>();
        for (int j = 0; j < subfields.size(); j++) {
            String raw = subfields.get(j).getData();
            String current = normalizeRelatorString(raw);
            if (permittedRoles.contains(current)) {
                relators.add(indexRawRelators ? raw : mapRelatorStringToCode(current));
            }
        }
        return relators;
    }

    /**
     * Is this relator term unknown to author-classification.ini?
     * @param current relator to check
     * @return True if unknown
     */
    public Boolean isUnknownRelator(String current)
    {
        // If we haven't loaded known relators yet, do so now:
        if (knownRelators.size() == 0) {
            Map<String, String> all = ConfigManager.instance().getConfigSection("author-classification.ini", "RelatorSynonyms");
            for (String key : all.keySet()) {
                knownRelators.add(normalizeRelatorString(key));
                for (String synonym: all.get(key).split("\\|")) {
                    knownRelators.add(normalizeRelatorString(synonym));
                }
            }
        }
        return !knownRelators.contains(normalizeRelatorString(current));
    }

    /**
     * Extract all valid relator terms from a list of subfields using a whitelist.
     * @param subfields      List of subfields to check
     * @return Set of valid relator terms
     */
    public Set<String> getUnknownRelatorsFromSubfields(List<Subfield> subfields)
    {
        Set<String> relators = new LinkedHashSet<String>();
        for (int j = 0; j < subfields.size(); j++) {
            String current = subfields.get(j).getData().trim();
            if (current.length() > 0 && isUnknownRelator(current)) {
                logger.info("Unknown relator: " + current);
                relators.add(current);
            }
        }
        return relators;
    }

    /**
     * Extract all values that meet the specified relator requirements.
     * @param authorField           Field to analyze
     * @param noRelatorAllowed      Array of tag names which are allowed to be used with
     * no declared relator.
     * @param relatorConfig         The setting in author-classification.ini which
     * defines which relator terms are acceptable (or a colon-delimited list)
     * @param unknownRelatorAllowed Array of tag names whose relators should be indexed
     * even if they are not listed in author-classification.ini.
     * @param indexRawRelators      Set to "true" to index relators raw, as found
     * in the MARC or "false" to index mapped versions.
     * @return Set
     */
    public Set<String> getValidRelators(DataField authorField,
        String[] noRelatorAllowed, String relatorConfig,
        String[] unknownRelatorAllowed, String indexRawRelators
    ) {
        // get tag number from Field
        String tag = authorField.getTag();
        List<Subfield> subfieldE = authorField.getSubfields('e');
        List<Subfield> subfield4 = authorField.getSubfields('4');

        Set<String> relators = new LinkedHashSet<String>();

        // if no relator is found, check to see if the current tag is in the "no
        // relator allowed" list.
        if (subfieldE.size() == 0 && subfield4.size() == 0) {
            if (Arrays.asList(noRelatorAllowed).contains(tag)) {
                relators.add("");
            }
        } else {
            // If we got this far, we need to figure out what type of relation they have
            List<String> permittedRoles = normalizeRelatorStringList(Arrays.asList(loadRelatorConfig(relatorConfig)));
            relators.addAll(getValidRelatorsFromSubfields(subfieldE, permittedRoles, indexRawRelators.toLowerCase().equals("true")));
            relators.addAll(getValidRelatorsFromSubfields(subfield4, permittedRoles, indexRawRelators.toLowerCase().equals("true")));
            if (Arrays.asList(unknownRelatorAllowed).contains(tag)) {
                Set<String> unknown = getUnknownRelatorsFromSubfields(subfieldE);
                if (unknown.size() == 0) {
                    unknown = getUnknownRelatorsFromSubfields(subfield4);
                }
                relators.addAll(unknown);
            }
        }
        return relators;
    }

    /**
     * Fix trailing punctuation on a name string.
     *
     * @param name Name to fix
     *
     * @return Stripped name
     */
    protected String fixTrailingPunctuation(String name)
    {
        // First, apply regular expressions:
        for (Pattern regex : punctuationRegEx) {
            name = regex.matcher(name).replaceAll("");
        }

        // Strip periods, except when they follow an initial or abbreviation:
        int nameLength = name.length();
        if (name.endsWith(".") && nameLength > 3 && !name.substring(nameLength - 3, nameLength - 2).startsWith(" ")) {
            int p = name.lastIndexOf(" ");
            String lastWord = (p > 0) ? name.substring(p + 1) : name;
            if (!untrimmedAbbreviations.contains(lastWord.toLowerCase())) {
                name = name.substring(0, nameLength - 1);
                nameLength--;
            }
        }

        // Remove trailing close characters with no corresponding open characters:
        for (String pair : punctuationPairs) {
            String left = pair.substring(0, 1);
            String right = pair.substring(1);
            if (name.endsWith(right) && !name.contains(left)) {
                name = name.substring(0, nameLength - 1);
            }
        }
        return name;
    }

    /**
     * Filter values retrieved using tagList to include only those whose relator
     * values are acceptable. Used for separating different types of authors.
     *
     * @param record               The record (fed in automatically)
     * @param tagList              The field specification to read
     * @param acceptWithoutRelator Colon-delimited list of tags whose values should
     * be accepted even if no relator subfield is defined
     * @param relatorConfig        The setting in author-classification.ini which
     * defines which relator terms are acceptable (or a colon-delimited list)
     * @param acceptUnknownRelators Colon-delimited list of tags whose relators
     * should be indexed even if they are not listed in author-classification.ini.
     * @param indexRawRelators      Set to "true" to index relators raw, as found
     * in the MARC or "false" to index mapped versions.
     * @param firstOnly            Return first result only?
     * @return List result
     */
    public List<String> getAuthorsFilteredByRelator(Record record, String tagList,
        String acceptWithoutRelator, String relatorConfig,
        String acceptUnknownRelators, String indexRawRelators, Boolean firstOnly
    ) {
        List<String> result = new LinkedList<String>();
        String[] noRelatorAllowed = acceptWithoutRelator.split(":");
        String[] unknownRelatorAllowed = acceptUnknownRelators.split(":");
        HashMap<String, Set<String>> parsedTagList = FieldSpecTools.getParsedTagList(tagList);
        for (VariableField variableField : SolrIndexer.instance().getFieldSetMatchingTagList(record, tagList)) {
            DataField authorField = (DataField) variableField;
            // add all author types to the result set; if we have multiple relators, repeat the authors
            for (String iterator: getValidRelators(authorField, noRelatorAllowed, relatorConfig, unknownRelatorAllowed, indexRawRelators)) {
                for (String subfields : parsedTagList.get(authorField.getTag())) {
                    String current = SolrIndexer.instance().getDataFromVariableField(authorField, "["+subfields+"]", " ", false);
                    // TODO: we may eventually be able to use this line instead,
                    // but right now it's not handling separation between the
                    // subfields correctly, so it's commented out until that is
                    // fixed.
                    //String current = authorField.getSubfieldsAsString(subfields);
                    if (null != current) {
                        result.add(fixTrailingPunctuation(current));
                        if (firstOnly) {
                            return result;
                        }
                    }
                }
            }
        }
        return result;
    }

    /**
     * Filter values retrieved using tagList to include only those whose relator
     * values are acceptable. Used for separating different types of authors.
     *
     * @param record               The record (fed in automatically)
     * @param tagList              The field specification to read
     * @param acceptWithoutRelator Colon-delimited list of tags whose values should
     * be accepted even if no relator subfield is defined
     * @param relatorConfig        The setting in author-classification.ini which
     * defines which relator terms are acceptable (or a colon-delimited list)
     * @return List result
     */
    public List<String> getAuthorsFilteredByRelator(Record record, String tagList,
        String acceptWithoutRelator, String relatorConfig
    ) {
        // default firstOnly to false!
        return getAuthorsFilteredByRelator(
            record, tagList, acceptWithoutRelator, relatorConfig,
            acceptWithoutRelator, "false", false
        );
    }

    /**
     * Filter values retrieved using tagList to include only those whose relator
     * values are acceptable. Used for separating different types of authors.
     *
     * @param record               The record (fed in automatically)
     * @param tagList              The field specification to read
     * @param acceptWithoutRelator Colon-delimited list of tags whose values should
     * be accepted even if no relator subfield is defined
     * @param relatorConfig        The setting in author-classification.ini which
     * defines which relator terms are acceptable (or a colon-delimited list)
     * @return List result
     * @param acceptUnknownRelators Colon-delimited list of tags whose relators
     * should be indexed even if they are not listed in author-classification.ini.
     */
    public List<String> getAuthorsFilteredByRelator(Record record, String tagList,
        String acceptWithoutRelator, String relatorConfig,
        String acceptUnknownRelators
    ) {
        // default firstOnly to false!
        return getAuthorsFilteredByRelator(
            record, tagList, acceptWithoutRelator, relatorConfig,
            acceptUnknownRelators, "false", false
        );
    }

    /**
     * Filter values retrieved using tagList to include only those whose relator
     * values are acceptable. Used for separating different types of authors.
     *
     * @param record               The record (fed in automatically)
     * @param tagList              The field specification to read
     * @param acceptWithoutRelator Colon-delimited list of tags whose values should
     * be accepted even if no relator subfield is defined
     * @param relatorConfig        The setting in author-classification.ini which
     * defines which relator terms are acceptable (or a colon-delimited list)
     * @return List result
     * @param acceptUnknownRelators Colon-delimited list of tags whose relators
     * should be indexed even if they are not listed in author-classification.ini.
     * @param indexRawRelators      Set to "true" to index relators raw, as found
     * in the MARC or "false" to index mapped versions.
     */
    public List<String> getAuthorsFilteredByRelator(Record record, String tagList,
        String acceptWithoutRelator, String relatorConfig,
        String acceptUnknownRelators, String indexRawRelators
    ) {
        // default firstOnly to false!
        return getAuthorsFilteredByRelator(
            record, tagList, acceptWithoutRelator, relatorConfig,
            acceptUnknownRelators, indexRawRelators, false
        );
    }

    /**
     * If the provided relator is included in the synonym list, convert it back to
     * a code (for better standardization/translation).
     *
     * @param relator Relator code to check
     * @return Code version, if found, or raw string if no match found.
     */
    public String mapRelatorStringToCode(String relator)
    {
        String normalizedRelator = normalizeRelatorString(relator);
        return relatorSynonymLookup.containsKey(normalizedRelator)
            ? relatorSynonymLookup.get(normalizedRelator) : relator;
    }

    /**
     * Filter values retrieved using tagList to include only those whose relator
     * values are acceptable. Used for separating different types of authors.
     *
     * @param record                The record (fed in automatically)
     * @param tagList               The field specification to read
     * @param acceptWithoutRelator  Colon-delimited list of tags whose values should
     * be accepted even if no relator subfield is defined
     * @param relatorConfig         The setting in author-classification.ini which
     * defines which relator terms  are acceptable (or a colon-delimited list)
     * @param acceptUnknownRelators Colon-delimited list of tags whose relators
     * should be indexed even if they are not listed in author-classification.ini.
     * @param indexRawRelators      Set to "true" to index relators raw, as found
     * in the MARC or "false" to index mapped versions.
     * @return String
     */
    public String getFirstAuthorFilteredByRelator(Record record, String tagList,
        String acceptWithoutRelator, String relatorConfig,
        String acceptUnknownRelators, String indexRawRelators
    ) {
        List<String> result = getAuthorsFilteredByRelator(
            record, tagList, acceptWithoutRelator, relatorConfig,
            acceptUnknownRelators, indexRawRelators, true
        );
        for (String s : result) {
            return s;
        }
        return null;
    }

    /**
     * Filter values retrieved using tagList to include only those whose relator
     * values are acceptable. Used for separating different types of authors.
     *
     * @param record                The record (fed in automatically)
     * @param tagList               The field specification to read
     * @param acceptWithoutRelator  Colon-delimited list of tags whose values should
     * be accepted even if no relator subfield is defined
     * @param relatorConfig         The setting in author-classification.ini which
     * defines which relator terms  are acceptable (or a colon-delimited list)
     * @return String
     */
    public String getFirstAuthorFilteredByRelator(Record record, String tagList,
        String acceptWithoutRelator, String relatorConfig
    ) {
        return getFirstAuthorFilteredByRelator(
            record, tagList, acceptWithoutRelator, relatorConfig,
            acceptWithoutRelator, "false"
        );
    }

    /**
     * Filter values retrieved using tagList to include only those whose relator
     * values are acceptable. Used for separating different types of authors.
     *
     * @param record                The record (fed in automatically)
     * @param tagList               The field specification to read
     * @param acceptWithoutRelator  Colon-delimited list of tags whose values should
     * be accepted even if no relator subfield is defined
     * @param relatorConfig         The setting in author-classification.ini which
     * defines which relator terms  are acceptable (or a colon-delimited list)
     * @param acceptUnknownRelators Colon-delimited list of tags whose relators
     * should be indexed even if they are not listed in author-classification.ini.
     * @return String
     */
    public String getFirstAuthorFilteredByRelator(Record record, String tagList,
        String acceptWithoutRelator, String relatorConfig,
        String acceptUnknownRelators
    ) {
        return getFirstAuthorFilteredByRelator(
            record, tagList, acceptWithoutRelator, relatorConfig,
            acceptUnknownRelators, "false"
        );
    }

    /**
     * Filter values retrieved using tagList to include only those whose relator
     * values are acceptable. Used for saving relators of authors separated by different
     * types.
     *
     * @param record               The record (fed in automatically)
     * @param tagList              The field specification to read
     * @param acceptWithoutRelator Colon-delimited list of tags whose values should
     * be accepted even if no relator subfield is defined
     * @param relatorConfig        The setting in author-classification.ini which
     * defines which relator terms are acceptable (or a colon-delimited list)
     * @param acceptUnknownRelators Colon-delimited list of tags whose relators
     * should be indexed even if they are not listed in author-classification.ini.
     * @param indexRawRelators      Set to "true" to index relators raw, as found
     * in the MARC or "false" to index mapped versions.
     * @param firstOnly            Return first result only?
     * @return List result
     */
    public List<String> getRelatorsFilteredByRelator(Record record, String tagList,
        String acceptWithoutRelator, String relatorConfig,
        String acceptUnknownRelators, String indexRawRelators, Boolean firstOnly
    ) {
        List<String> result = new LinkedList<String>();
        String[] noRelatorAllowed = acceptWithoutRelator.split(":");
        String[] unknownRelatorAllowed = acceptUnknownRelators.split(":");
        HashMap<String, Set<String>> parsedTagList = FieldSpecTools.getParsedTagList(tagList);
        for (VariableField variableField : SolrIndexer.instance().getFieldSetMatchingTagList(record, tagList)) {
            DataField authorField = (DataField) variableField;
            //add all author types to the result set
            result.addAll(getValidRelators(authorField, noRelatorAllowed, relatorConfig, unknownRelatorAllowed, indexRawRelators));
        }
        return result;
    }

    /**
     * Filter values retrieved using tagList to include only those whose relator
     * values are acceptable. Used for saving relators of authors separated by different
     * types.
     *
     * @param record               The record (fed in automatically)
     * @param tagList              The field specification to read
     * @param acceptWithoutRelator Colon-delimited list of tags whose values should
     * be accepted even if no relator subfield is defined
     * @param relatorConfig        The setting in author-classification.ini which
     * defines which relator terms are acceptable (or a colon-delimited list)
     * @param acceptUnknownRelators Colon-delimited list of tags whose relators
     * should be indexed even if they are not listed in author-classification.ini.
     * @param indexRawRelators      Set to "true" to index relators raw, as found
     * in the MARC or "false" to index mapped versions.
     * @return List result
     */
    public List<String> getRelatorsFilteredByRelator(Record record, String tagList,
        String acceptWithoutRelator, String relatorConfig,
        String acceptUnknownRelators, String indexRawRelators
    ) {
        // default firstOnly to false!
        return getRelatorsFilteredByRelator(
            record, tagList, acceptWithoutRelator, relatorConfig,
            acceptUnknownRelators, indexRawRelators, false
        );
    }

    /**
     * Filter values retrieved using tagList to include only those whose relator
     * values are acceptable. Used for saving relators of authors separated by different
     * types.
     *
     * @param record               The record (fed in automatically)
     * @param tagList              The field specification to read
     * @param acceptWithoutRelator Colon-delimited list of tags whose values should
     * be accepted even if no relator subfield is defined
     * @param relatorConfig        The setting in author-classification.ini which
     * defines which relator terms are acceptable (or a colon-delimited list)
     * @param acceptUnknownRelators Colon-delimited list of tags whose relators
     * should be indexed even if they are not listed in author-classification.ini.
     * @return List result
     */
    public List<String> getRelatorsFilteredByRelator(Record record, String tagList,
        String acceptWithoutRelator, String relatorConfig,
        String acceptUnknownRelators
    ) {
        // default firstOnly to false!
        return getRelatorsFilteredByRelator(
            record, tagList, acceptWithoutRelator, relatorConfig,
            acceptUnknownRelators, "false", false
        );
    }

    /**
     * Filter values retrieved using tagList to include only those whose relator
     * values are acceptable. Used for saving relators of authors separated by different
     * types.
     *
     * @param record               The record (fed in automatically)
     * @param tagList              The field specification to read
     * @param acceptWithoutRelator Colon-delimited list of tags whose values should
     * be accepted even if no relator subfield is defined
     * @param relatorConfig        The setting in author-classification.ini which
     * defines which relator terms are acceptable (or a colon-delimited list)
     * @return List result
     */
    public List<String> getRelatorsFilteredByRelator(Record record, String tagList,
        String acceptWithoutRelator, String relatorConfig
    ) {
        // default firstOnly to false!
        return getRelatorsFilteredByRelator(
            record, tagList, acceptWithoutRelator, relatorConfig,
            acceptWithoutRelator, "false", false
        );
    }

    /**
     * This method fetches relator definitions from ini file and casts them to an
     * array. If a colon-delimited string is passed in, this will be directly parsed
     * instead of resorting to .ini loading.
     *
     * @param setting Setting to load from .ini or colon-delimited list.
     * @return String[]
     */
    protected String[] loadRelatorConfig(String setting){
        StringBuilder relators = new StringBuilder();

        // check for pipe-delimited string
        String[] relatorSettings = setting.split("\\|");
        for (String relatorSetting: relatorSettings) {
            // check for colon-delimited string
            String[] relatorArray = relatorSetting.split(":");
            if (relatorArray.length > 1) {
                for (int i = 0; i < relatorArray.length; i++) {
                    relators.append(relatorArray[i]).append(",");
                }
            } else {
                relators.append(ConfigManager.instance().getConfigSetting(
                    "author-classification.ini", "AuthorRoles", relatorSetting
                )).append(",");
            }
        }

        return relators.toString().split(",");
    }

    /**
     * Normalizes a relator string and returns a list containing the normalized
     * relator plus any configured synonyms.
     *
     * @param relator Relator term to normalize
     * @return List of strings
     */
    public List<String> normalizeRelatorAndAddSynonyms(String relator)
    {
        List<String> newList = new ArrayList<String>();
        String normalized = normalizeRelatorString(relator);
        newList.add(normalized);
        String synonyms = ConfigManager.instance().getConfigSetting(
            "author-classification.ini", "RelatorSynonyms", relator
        );
        if (null != synonyms && synonyms.length() > 0) {
            for (String synonym: synonyms.split("\\|")) {
                String normalizedSynonym = normalizeRelatorString(synonym);
                relatorSynonymLookup.put(normalizedSynonym, relator);
                newList.add(normalizedSynonym);
            }
        }
        return newList;
    }

    /**
     * Normalizes the strings in a list.
     *
     * @param stringList List of strings to be normalized
     * @return Normalized List of strings
     */
    protected List<String> normalizeRelatorStringList(List<String> stringList)
    {
        List<String> newList = new ArrayList<String>();
        for (String relator: stringList) {
            newList.addAll(normalizeRelatorAndAddSynonyms(relator));
        }
        return newList;
    }

    /**
     * Normalizes a string
     *
     * @param string String to be normalized
     * @return string
     */
    protected String normalizeRelatorString(String string)
    {
        string = string.trim();
        for (String prefix : relatorPrefixesToStrip) {
            if (string.startsWith(prefix)) {
                string = string.substring(prefix.length());
                break;
            }
        }
        return string
            .toLowerCase()
            .replaceAll("\\p{Punct}+", "");    //POSIX character class Punctuation: One of !"#$%&'()*+,-./:;<=>?@[\]^_`{|}~
    }

    /**
     * Filter values retrieved using tagList to include only those whose relator
     * values are acceptable. Used for separating different types of authors.
     *
     * @param record               The record (fed in automatically)
     * @param tagList              The field specification to read
     * @param acceptWithoutRelator Colon-delimited list of tags whose values should
     * be accepted even if no relator subfield is defined
     * @param relatorConfig        The setting in author-classification.ini which
     * defines which relator terms are acceptable (or a colon-delimited list)
     * @param acceptUnknownRelators Colon-delimited list of tags whose relators
     * should be indexed even if they are not listed in author-classification.ini.
     * @param indexRawRelators      Set to "true" to index relators raw, as found
     * in the MARC or "false" to index mapped versions.
     * @return List result
     */
    public List<String> getAuthorInitialsFilteredByRelator(Record record,
        String tagList, String acceptWithoutRelator, String relatorConfig,
        String acceptUnknownRelators, String indexRawRelators
    ) {
        List<String> authors = getAuthorsFilteredByRelator(
            record, tagList, acceptWithoutRelator, relatorConfig,
            acceptUnknownRelators, indexRawRelators
        );
        List<String> result = new LinkedList<String>();
        for (String author : authors) {
            result.add(processInitials(author));
        }
        return result;
    }

    /**
     * Filter values retrieved using tagList to include only those whose relator
     * values are acceptable. Used for separating different types of authors.
     *
     * @param record               The record (fed in automatically)
     * @param tagList              The field specification to read
     * @param acceptWithoutRelator Colon-delimited list of tags whose values should
     * be accepted even if no relator subfield is defined
     * @param relatorConfig        The setting in author-classification.ini which
     * defines which relator terms are acceptable (or a colon-delimited list)
     * @return List result
     */
    public List<String> getAuthorInitialsFilteredByRelator(Record record,
        String tagList, String acceptWithoutRelator, String relatorConfig
    ) {
        return getAuthorInitialsFilteredByRelator(
            record, tagList, acceptWithoutRelator, relatorConfig,
            acceptWithoutRelator, "false"
        );
    }

    /**
     * Filter values retrieved using tagList to include only those whose relator
     * values are acceptable. Used for separating different types of authors.
     *
     * @param record               The record (fed in automatically)
     * @param tagList              The field specification to read
     * @param acceptWithoutRelator Colon-delimited list of tags whose values should
     * be accepted even if no relator subfield is defined
     * @param relatorConfig        The setting in author-classification.ini which
     * defines which relator terms are acceptable (or a colon-delimited list)
     * @param acceptUnknownRelators Colon-delimited list of tags whose relators
     * should be indexed even if they are not listed in author-classification.ini.
     * @return List result
     */
    public List<String> getAuthorInitialsFilteredByRelator(Record record,
        String tagList, String acceptWithoutRelator, String relatorConfig,
        String acceptUnknownRelators
    ) {
        return getAuthorInitialsFilteredByRelator(
            record, tagList, acceptWithoutRelator, relatorConfig,
            acceptUnknownRelators, "false"
        );
    }

    /**
     * Takes a name and cuts it into initials
     * @param authorName e.g. Yeats, William Butler
     * @return initials e.g. w b y wb
     */
    protected String processInitials(String authorName) {
        Boolean isPersonalName = false;
        // we guess that if there is a comma before the end - this is a personal name
        if ((authorName.indexOf(',') > 0)
            && (authorName.indexOf(',') < authorName.length()-1)) {
            isPersonalName = true;
        }
        // get rid of non-alphabet chars but keep hyphens and accents
        authorName = authorName.replaceAll("[^\\p{L} -]", "").toLowerCase();
        String[] names = authorName.split(" "); //split into tokens on spaces
        // if this is a personal name we'll reorganise to put lastname at the end
        String result = "";
        if (isPersonalName) {
            String lastName = names[0];
            for (int i = 0; i < names.length-1; i++) {
                names[i] = names[i+1];
            }
            names[names.length-1] = lastName;
        }
        // put all the initials together in a space separated string
        for (String name : names) {
            if (name.length() > 0) {
                String initial = name.substring(0,1);
                // if there is a hyphenated name, use both initials
                int pos = name.indexOf('-');
                if (pos > 0 && pos < name.length() - 1) {
                    String extra = name.substring(pos+1, pos+2);
                    initial = initial + " " + extra;
                }
                result += " " + initial;
            }
        }
        // grab all initials and stick them together
        String smushAll = result.replaceAll(" ", "");
        // if it's a long personal name, get all but the last initials as well
        // e.g. wb for william butler yeats
        if (names.length > 2 && isPersonalName) {
            String smushPers = result.substring(0,result.length()-1).replaceAll(" ","");
            result = result + " " + smushPers;
        }
        // now we have initials separate and together
        if (!result.trim().equals(smushAll)) {
            result += " " + smushAll;
        }
        result = result.trim();
        return result;
    }
}
