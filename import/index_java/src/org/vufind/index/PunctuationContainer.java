package org.vufind.index;
/**
 * Singleton for storing punctuation configuration information.
 *
 * Copyright (C) Villanova University 2021
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

import java.util.LinkedHashSet;
import java.util.regex.Pattern;
import java.util.Map;
import java.util.Set;
import org.apache.log4j.Logger;

/**
 * Singleton for storing punctuation configuration information.
 */
public class PunctuationContainer
{
    // Initialize logging category
    static Logger logger = Logger.getLogger(ConfigManager.class.getName());

    private static ThreadLocal<PunctuationContainer> containerCache =
        new ThreadLocal<PunctuationContainer>()
        {
            @Override
            protected PunctuationContainer initialValue()
            {
                return new PunctuationContainer();
            }
        };

    private static String configFilename = "author-classification.ini";

    private Set<Pattern> punctuationRegEx = null;
    private Set<String> punctuationPairs = null;
    private Set<String> untrimmedAbbreviations = null;

    public Set<Pattern> getPunctuationRegEx()
    {
        // Populate set if empty:
        if (punctuationRegEx == null) {
            punctuationRegEx = new LinkedHashSet<Pattern>();
            String configSection = "PunctuationRegExToStrip";
            Map<String, String> all = ConfigManager.instance().getConfigSection(configFilename, configSection);
            if (all.isEmpty()) {
                logger.warn(configSection + " section missing from " + configFilename);
            } else {
                for (String pattern : all.values()) {
                    punctuationRegEx.add(Pattern.compile(pattern, Pattern.UNICODE_CHARACTER_CLASS));
                }
            }
        }
        return punctuationRegEx;
    }

    public Set<String> getPunctuationPairs()
    {
        // Populate set if empty:
        if (punctuationPairs == null) {
            String configSection = "PunctuationMatchedChars";
            Map<String, String> all = ConfigManager.instance().getConfigSection(configFilename, configSection);
            if (all.isEmpty()) {
                punctuationPairs = new LinkedHashSet<String>();
                logger.warn(configSection + " section missing from " + configFilename);
            } else {
                punctuationPairs = new LinkedHashSet<String>(all.values());
            }
        }
        return punctuationPairs;
    }

    public Set<String> getUntrimmedAbbreviations()
    {
        // Populate set if empty:
        if (untrimmedAbbreviations == null) {
            String configSection = "PunctuationUntrimmedAbbreviations";
            Map<String, String> all = ConfigManager.instance().getConfigSection(configFilename, configSection);
            if (all.isEmpty()) {
                untrimmedAbbreviations = new LinkedHashSet<String>();
                logger.warn(configSection + " section missing from " + configFilename);
            } else {
                untrimmedAbbreviations = new LinkedHashSet<String>(all.values());
            }
        }
        return untrimmedAbbreviations;
    }

    public static PunctuationContainer instance()
    {
        return containerCache.get();
    }
}
