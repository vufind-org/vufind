package org.vufind.index;
/**
 * VuFind configuration manager
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

import java.io.File;
import java.io.FileReader;
import java.util.Map;
import java.util.Properties;
import java.util.concurrent.ConcurrentHashMap;
import org.solrmarc.index.indexer.ValueIndexerFactory;
import org.solrmarc.tools.PropertyUtils;
import org.solrmarc.tools.SolrMarcIndexerException;
import org.ini4j.Ini;
import org.apache.log4j.Logger;

/**
 * VuFind configuration manager
 */
public class ConfigManager
{
    // Initialize logging category
    static Logger logger = Logger.getLogger(ConfigManager.class.getName());
    private static ConcurrentHashMap<String, Ini> configCache = new ConcurrentHashMap<String, Ini>();
    private Properties vuFindConfigs = null;
    private static ThreadLocal<ConfigManager> managerCache = 
        new ThreadLocal<ConfigManager>()
        {
            @Override
            protected ConfigManager initialValue()
            {
                return new ConfigManager();
            }
        };

    public ConfigManager()
    {
        try {
            vuFindConfigs = PropertyUtils.loadProperties(ValueIndexerFactory.instance().getHomeDirs(), "vufind.properties");
        } catch (IllegalArgumentException e) {
            // If the properties load failed, don't worry about it -- we'll use defaults.
        }
    }

    public static ConfigManager instance()
    {
        return managerCache.get();
    }

    /**
     * Given the base name of a configuration file, locate the full path.
     * @param filename base name of a configuration file
     */
    private File findConfigFile(String filename) throws IllegalStateException
    {
        // Find VuFind's home directory in the environment; if it's not available,
        // try using a relative path on the assumption that we are currently in
        // VuFind's import subdirectory:
        String vufindHome = System.getenv("VUFIND_HOME");
        if (vufindHome == null) {
            // this shouldn't happen since import-marc.sh and .bat always set VUFIND_HOME
            throw new IllegalStateException("VUFIND_HOME must be set");
        }

        // Check for VuFind 2.0's local directory environment variable:
        String vufindLocal = System.getenv("VUFIND_LOCAL_DIR");

        // Get the relative VuFind path from the properties file, defaulting to
        // the 2.0-style config/vufind if necessary.
        String relativeConfigPath = PropertyUtils.getProperty(
            vuFindConfigs, "vufind.config.relative_path", "config/vufind"
        );

        // Try several different locations for the file -- VuFind 2 local dir,
        // VuFind 2 base dir, VuFind 1 base dir.
        File file;
        if (vufindLocal != null) {
            file = new File(vufindLocal + "/" + relativeConfigPath + "/" + filename);
            if (file.exists()) {
                return file;
            }
        }
        file = new File(vufindHome + "/" + relativeConfigPath + "/" + filename);
        if (file.exists()) {
            return file;
        }
        file = new File(vufindHome + "/web/conf/" + filename);
        return file;
    }

    /**
     * Sanitize a VuFind configuration setting.
     * @param str configuration setting
     */
    private String sanitizeConfigSetting(String str)
    {
        // Drop comments if necessary:
        int pos = str.indexOf(';');
        if (pos >= 0) {
            str = str.substring(0, pos).trim();
        }

        // Strip wrapping quotes if necessary (the ini reader won't do this for us):
        if (str.startsWith("\"")) {
            str = str.substring(1, str.length());
        }
        if (str.endsWith("\"")) {
            str = str.substring(0, str.length() - 1);
        }
        return str;
    }

    /**
     * Load an ini file.
     * @param filename name of {@code .ini} file
     */
    public Ini loadConfigFile(String filename)
    {
        // Retrieve the file if it is not already cached.
        if (!configCache.containsKey(filename)) {
            Ini ini = new Ini();
            File configFile = null;
            try {
                configFile = findConfigFile(filename);
            } catch (IllegalStateException e) {
                dieWithError("Illegal State: " + e.getMessage());
            } catch (Throwable e) {
                dieWithError("Unable to locate " + filename);
            }
            try {
                if (configFile != null) {
                    ini.load(new FileReader(configFile));
                    configCache.putIfAbsent(filename, ini);
                }
            } catch (Throwable e) {
                dieWithError("Unable to access " + configFile.getAbsolutePath());
            }
        }
        return configCache.get(filename);
    }

    /**
     * Get a section from a VuFind configuration file.
     * @param filename configuration file name
     * @param section section name within the file
     */
    public Map<String, String> getConfigSection(String filename, String section)
    {
        // Grab the ini file.
        Ini ini = loadConfigFile(filename);
        Map<String, String> retVal = ini.get(section);

        String parent = ini.get("Parent_Config", "path");
        while (parent != null) {
            Ini parentIni = loadConfigFile(parent);
            Map<String, String> parentSection = parentIni.get(section);
            for (String key : parentSection.keySet()) {
                if (!retVal.containsKey(key)) {
                    retVal.put(key, parentSection.get(key));
                }
            }
            parent = parentIni.get("Parent_Config", "path");
        }

        // Check to see if we need to worry about an override file:
        String override = ini.get("Extra_Config", "local_overrides");
        if (override != null) {
            Map<String, String> overrideSection = loadConfigFile(override).get(section);
            for (String key : overrideSection.keySet()) {
                retVal.put(key, overrideSection.get(key));
            }
        }
        return retVal;
    }

    /**
     * Get a setting from a VuFind configuration file.
     * @param filename configuration file name
     * @param section section name within the file
     * @param setting setting name within the section
     */
    public String getConfigSetting(String filename, String section, String setting)
    {
        String retVal = null;

        // Grab the ini file.
        Ini ini = loadConfigFile(filename);

        // Check to see if we need to worry about an override file:
        String override = ini.get("Extra_Config", "local_overrides");
        if (override != null) {
            Ini overrideIni = loadConfigFile(override);
            retVal = overrideIni.get(section, setting);
            if (retVal != null) {
                return sanitizeConfigSetting(retVal);
            }
        }

        // Try to find the requested setting:
        retVal = ini.get(section, setting);

        //  No setting?  Check for a parent configuration:
        while (retVal == null) {
            String parent = ini.get("Parent_Config", "path");
            if (parent !=  null) {
                try {
                    ini.load(new FileReader(new File(parent)));
                } catch (Throwable e) {
                    dieWithError("Unable to access " + parent);
                }
                retVal = ini.get(section, setting);
            } else {
                break;
            }
        }

        // Return the processed setting:
        return retVal == null ? null : sanitizeConfigSetting(retVal);
    }

    /**
     * Log an error message and throw a fatal exception.
     * @param msg message to log
     */
    private void dieWithError(String msg)
    {
        logger.error(msg);
        throw new SolrMarcIndexerException(SolrMarcIndexerException.EXIT, msg);
    }
}
