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
    private static Map<String, Ini> configCache = new ConcurrentHashMap<>();
    private static Map<String, ConcurrentHashMap<String, String>> sanitizedConfigCache = new ConcurrentHashMap<>();
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
        // we cannot proceed:
        String vufindHome = System.getenv("VUFIND_HOME");
        if (vufindHome == null) {
            // this shouldn't happen since import-marc.sh and .bat always set VUFIND_HOME
            throw new IllegalStateException("VUFIND_HOME must be set");
        }

        // Check for VuFind's local directory environment variable:
        String vufindLocal = System.getenv("VUFIND_LOCAL_DIR");

        // If VUFIND_LOCAL_DIR is not set, issue a warning and try to derive it from VUFIND_HOME
        if (vufindLocal == null || vufindLocal.length() == 0) {
            vufindLocal = vufindHome + "/local";
            logger.warn("The VUFIND_LOCAL_DIR environment variable is missing. Defaulting to " + vufindLocal);
        }

        // Get the relative VuFind path from the properties file, defaulting to config/vufind if necessary.
        String relativeConfigPath = PropertyUtils.getProperty(
            vuFindConfigs, "vufind.config.relative_path", "config/vufind"
        );

        // Try several different locations for the file -- VuFind local dir, VuFind base dir, legacy base dir.
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
        file = new File(vufindHome + "/web/conf/" + filename); // legacy from VuFind 1.x
        return file;
    }

    /**
     * Sanitize a VuFind configuration setting.
     * @param str configuration setting
     */
    private String sanitizeConfigSetting(String str)
    {
        // Work on a copy of the string.
        // We do not want the sanitizer to update the cache, because it might
        // cause problems when executing them multiple times, like
        // e.g. in multithreaded scenarios.
        String retVal = new String(str);

        // Drop comments if necessary; if the semi-colon is inside quotes, leave
        // it alone. TODO: handle complex cases with comment AND quoted semi-colon
        int pos = retVal.indexOf(';');
        if (pos >= 0 && !retVal.matches("\"[^\"]*;[^\"]*\"")) {
            retVal = retVal.substring(0, pos).trim();
        }

        // Strip wrapping quotes if necessary (the ini reader won't do this for us):
        if (retVal.startsWith("\"")) {
            retVal = retVal.substring(1, retVal.length());
        }
        if (retVal.endsWith("\"")) {
            retVal = retVal.substring(0, retVal.length() - 1);
        }
        return retVal;
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
                dieWithError(
                    "Unable to locate " + filename
                    + " (" + e.getMessage() + ")"
                );
            }
            try {
                if (configFile != null) {
                    ini.load(new FileReader(configFile));
                    configCache.putIfAbsent(filename, ini);
                }
            } catch (Throwable e) {
                dieWithError(
                    "Unable to access " + configFile.getAbsolutePath()
                    + " (" + e.getMessage() + ")"
                );
            }
        }
        return configCache.get(filename);
    }

    /**
     * Get a section from a VuFind configuration file and sanitize all the values.
     * @param filename configuration file name
     * @param section section name within the file
     */
    public Map<String, String> getConfigSection(String filename, String section)
    {
        String sanitizedCacheKey = filename + "#" + section;
        return sanitizedConfigCache.computeIfAbsent(sanitizedCacheKey, retVal -> {
            Map<String, String> rawSection = getRawConfigSection(filename, section);
            if (rawSection == null) {
                return new ConcurrentHashMap<>();
            }

            // Sanitize a copy of the section.
            // We do not want the sanitizer to update the cache, because it might
            // cause problems when executing them multiple times, like
            // e.g. in multithreaded scenarios.
            ConcurrentHashMap<String, String> sanitizedSection = new ConcurrentHashMap<>();
            for (Map.Entry<String, String> entry : rawSection.entrySet()) {
                sanitizedSection.put(entry.getKey(), sanitizeConfigSetting(entry.getValue()));
            }
            return sanitizedSection;
        });
    }

    /**
     * Get a section from a VuFind configuration file.
     * @param filename configuration file name
     * @param section section name within the file
     */
    public Map<String, String> getRawConfigSection(String filename, String section)
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
     * @deprecated Please use getConfigSection instead, or getRawConfigSection
     *             if you would like to get the non-sanitized values.
     */
    @Deprecated
    public Map<String, String> getSanitizedConfigSection(String filename, String section)
    {
        return getConfigSection(filename, section);
    }

    /**
     * Get a setting from a VuFind configuration file and sanitize the value.
     * @param filename configuration file name
     * @param section section name within the file
     * @param setting setting name within the section
     */
    public String getConfigSetting(String filename, String section, String setting)
    {
        String retVal = getRawConfigSetting(filename, section, setting);
        return retVal == null ? retVal : sanitizeConfigSetting(retVal);
    }

    /**
     * Get a setting from a VuFind configuration file.
     * @param filename configuration file name
     * @param section section name within the file
     * @param setting setting name within the section
     */
    public String getRawConfigSetting(String filename, String section, String setting)
    {
        Map<String, String> sectionMap = getRawConfigSection(filename, section);
        return sectionMap == null ? null : sectionMap.get(setting);
    }

    /**
     * @deprecated Please use getConfigSetting instead, or getRawConfigSetting
     *             if you would like to get the non-sanitized value.
     */
    @Deprecated
    public String getSanitizedConfigSetting(String filename, String section, String setting)
    {
        return getConfigSetting(filename, section, setting);
    }

    /**
     * Get a Boolean setting from a VuFind configuration file; match PHP's string to Boolean logic.
     * @param filename configuration file name
     * @param section section name within the file
     * @param setting setting name within the section
     * @param default defaultValue value to use if setting is missing
     */
    public boolean getBooleanConfigSetting(String filename, String section, String setting, boolean defaultValue)
    {
        String config = getConfigSetting(filename, section, setting);
        if (config == null) {
            return defaultValue;
        }
        switch (config.trim().toLowerCase()) {
        case "false":
        case "0":
        case "":
            return false;
        }
        return true;
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
