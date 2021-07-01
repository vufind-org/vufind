package org.tuefind.index;

import org.apache.log4j.Logger;
import org.marc4j.marc.Record;
import java.util.List;
import java.util.Map;
import java.util.concurrent.ConcurrentHashMap;

/**
  * Added cache to certain "getAuthorsFilteredByRelator" queries, because they are very slow
  * and we repeatedly perform the same lookups.
  *
  * Using a ConcurrentHashMap to be thread-safe, combined with computeIfAbsent for best performance.
  * see also: https://dzone.com/articles/concurrenthashmap-isnt-always-enough
  *
  */
public class CreatorTools extends org.vufind.index.CreatorTools
{
    static protected Logger logger = Logger.getLogger(CreatorTools.class.getName());

    static protected Map<String, String[]> relatorConfigCache = new ConcurrentHashMap();

    protected String[] loadRelatorConfig(String setting){
        return relatorConfigCache.computeIfAbsent(setting, s -> super.loadRelatorConfig(setting));
    }

    static protected Map<String, List<String>> authorsCache = new ConcurrentHashMap();

    public List<String> getAuthorsFilteredByRelator(Record record, String tagList,
                                                    String acceptWithoutRelator, String relatorConfig,
                                                    String acceptUnknownRelators, String indexRawRelators, Boolean firstOnly)
    {
        final String cacheKey = record.getControlNumber() + tagList + acceptWithoutRelator + relatorConfig + acceptUnknownRelators + indexRawRelators + firstOnly.toString();
        return authorsCache.computeIfAbsent(cacheKey, c -> super.getAuthorsFilteredByRelator(record, tagList, acceptWithoutRelator, relatorConfig, acceptUnknownRelators, indexRawRelators, firstOnly));
    }
}
