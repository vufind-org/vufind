package org.tuefind.index;

import org.apache.log4j.Logger;
import org.marc4j.marc.Record;
import org.marc4j.marc.Subfield;
import org.marc4j.marc.DataField;
import org.solrmarc.index.SolrIndexer;
import java.util.Arrays;
import java.util.HashMap;
import java.util.Iterator;
import java.util.LinkedHashSet;
import java.util.LinkedList;
import java.util.List;
import java.util.Map;
import java.util.Set;
import java.util.concurrent.ConcurrentHashMap;

public class CreatorTools extends org.vufind.index.CreatorTools
{
    static protected Logger logger = Logger.getLogger(CreatorTools.class.getName());

  /**
   * Added cache to certain "getAuthorsFilteredByRelator" queries, because they are very slow
   * and we repeatedly perform the same lookups.
   *
   * Using a ConcurrentHashMap to be thread-safe, combined with computeIfAbsent for best performance.
   * see also: https://dzone.com/articles/concurrenthashmap-isnt-always-enough
   *
   */
    static protected Map<String, String[]> relatorConfigCache = new ConcurrentHashMap();

    protected String[] loadRelatorConfig(String setting){
        return relatorConfigCache.computeIfAbsent(setting, s -> super.loadRelatorConfig(setting));
    }

    /**
     * TueFind: Special treatment for iteration logic + 'g' subfield
     *
     * We also tried to add a cache to this function (similar to relatorConfigCache),
     * but that failed because the cache got too big, producing OutOfMemory errors.
     * => The cache has been removed again.
     */
    public List<String> getAuthorsFilteredByRelator(Record record, String tagList,
        String acceptWithoutRelator, String relatorConfig,
        String acceptUnknownRelators, String indexRawRelators, Boolean firstOnly
    ) {
        List<String> result = new LinkedList<String>();
        String[] noRelatorAllowed = acceptWithoutRelator.split(":");
        String[] unknownRelatorAllowed = acceptUnknownRelators.split(":");
        HashMap<String, Set<String>> parsedTagList = getParsedTagList(tagList);
        List fields = SolrIndexer.instance().getFieldSetMatchingTagList(record, tagList);
        Iterator fieldsIter = fields.iterator();
        if (fields != null){
            DataField authorField;
            while (fieldsIter.hasNext()){
                authorField = (DataField) fieldsIter.next();
                // add all author types to the result set; if we have multiple relators, repeat the authors
                for (String iterator: getValidRelators(authorField, noRelatorAllowed, relatorConfig, unknownRelatorAllowed, indexRawRelators)) {
                    // TueFind: This whole section has been modified
                    for (String subfieldCharacters : parsedTagList.get(authorField.getTag())) {
                        final List<Subfield> subfields = authorField.getSubfields("["+subfieldCharacters+"]");
                        final Iterator<Subfield> subfieldsIter =  subfields.iterator();
                        String resultOneField = new String();
                        while (subfieldsIter.hasNext()) {
                           final Subfield subfield = subfieldsIter.next();
                           final String data = subfield.getData();
                           if (resultOneField.isEmpty())
                               resultOneField = data;
                           else {
                               // TueFind: Special handling for 'g' subfield
                               resultOneField += (subfield.getCode() == 'b' || subfield.getCode() == 'c' ||
                                                  subfield.getCode() == 'g') ? ", " : " ";
                               resultOneField += data;

                           }
                        }
                        if (!resultOneField.isEmpty()) {
                            result.add(resultOneField);
                            if (firstOnly)
                                return result;
                        }
                    }
                }
            }
        }
        return result;
    }

    /**
     * TueFind: Special treatments for authors from field 100
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
                // TueFind: 100 contains the first author even if the relator is not given explicitly
                if (tag.equals("100"))
                    relators.add("aut");
                else
                    relators.add("");
            }
        } else {
            // If we got this far, we need to figure out what type of relation they have
            List permittedRoles = normalizeRelatorStringList(Arrays.asList(loadRelatorConfig(relatorConfig)));
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
}
