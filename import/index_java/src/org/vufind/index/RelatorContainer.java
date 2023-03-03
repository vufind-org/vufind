package org.vufind.index;
/**
 * Singleton for storing relator information.
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

import java.util.LinkedHashSet;
import java.util.Map;
import java.util.Set;
import java.util.concurrent.ConcurrentHashMap;
import org.apache.log4j.Logger;

/**
 * Singleton for storing relator information.
 */
public class RelatorContainer
{
    // Initialize logging category
    static Logger logger = Logger.getLogger(ConfigManager.class.getName());

    private static ThreadLocal<RelatorContainer> containerCache =
        new ThreadLocal<RelatorContainer>()
        {
            @Override
            protected RelatorContainer initialValue()
            {
                return new RelatorContainer();
            }
        };

    private static String configFilename = "author-classification.ini";

    private ConcurrentHashMap<String, String> relatorSynonymLookup = new ConcurrentHashMap<String, String>();
    private Set<String> knownRelators = new LinkedHashSet<String>();
    private Set<String> relatorPrefixesToStrip = null;

    public ConcurrentHashMap<String, String> getSynonymLookup()
    {
        return relatorSynonymLookup;
    }

    public Set<String> getKnownRelators()
    {
        return knownRelators;
    }

    public Set<String> getRelatorPrefixesToStrip()
    {
        // Populate set if empty:
        if (relatorPrefixesToStrip == null) {
            String configSection = "RelatorPrefixesToStrip";
            Map<String, String> all = ConfigManager.instance().getConfigSection(configFilename, configSection);
            if (all.isEmpty()) {
                relatorPrefixesToStrip = new LinkedHashSet<String>();
                logger.warn(configSection + " section missing from " + configFilename);
            } else {
                relatorPrefixesToStrip = new LinkedHashSet<String>(all.values());
            }
        }
        return relatorPrefixesToStrip;
    }

    public static RelatorContainer instance()
    {
        return containerCache.get();
    }
}