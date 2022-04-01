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

/**
 * Singleton for storing punctuation configuration information.
 */
public class PunctuationContainer
{
    private static ThreadLocal<PunctuationContainer> containerCache =
        new ThreadLocal<PunctuationContainer>()
        {
            @Override
            protected PunctuationContainer initialValue()
            {
                return new PunctuationContainer();
            }
        };

    private Set<Pattern> punctuationRegEx = new LinkedHashSet<Pattern>();
    private Set<String> punctuationPairs = new LinkedHashSet<String>();
    private Set<String> untrimmedAbbreviations = new LinkedHashSet<String>();

    public Set<Pattern> getPunctuationRegEx()
    {
        // Populate set if empty:
        if (punctuationRegEx.size() == 0) {
            Map<String, String> all = ConfigManager.instance().getConfigSection("author-classification.ini", "PunctuationRegExToStrip");
            punctuationRegEx = new LinkedHashSet<Pattern>();
            for (String pattern : all.values()) {
                punctuationRegEx.add(Pattern.compile(pattern, Pattern.UNICODE_CHARACTER_CLASS));
            }
        }
        return punctuationRegEx;
    }

    public Set<String> getPunctuationPairs()
    {
        // Populate set if empty:
        if (punctuationPairs.size() == 0) {
            Map<String, String> all = ConfigManager.instance().getSanitizedConfigSection("author-classification.ini", "PunctuationMatchedChars");
            punctuationPairs = new LinkedHashSet<String>(all.values());
        }
        return punctuationPairs;
    }

    public Set<String> getUntrimmedAbbreviations()
    {
        // Populate set if empty:
        if (untrimmedAbbreviations.size() == 0) {
            Map<String, String> all = ConfigManager.instance().getSanitizedConfigSection("author-classification.ini", "PunctuationUntrimmedAbbreviations");
            untrimmedAbbreviations = new LinkedHashSet<String>(all.values());
        }
        return untrimmedAbbreviations;
    }

    public static PunctuationContainer instance()
    {
        return containerCache.get();
    }
}
