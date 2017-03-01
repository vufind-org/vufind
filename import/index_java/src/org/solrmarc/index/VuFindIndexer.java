package org.solrmarc.index;
/**
 * Licensed to the Apache Software Foundation (ASF) under one or more
 * contributor license agreements.  See the NOTICE file distributed with
 * this work for additional information regarding copyright ownership.
 * The ASF licenses this file to You under the Apache License, Version 2.0
 * (the "License"); you may not use this file except in compliance with
 * the License.  You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

import java.io.File;
import java.io.FileReader;
import java.io.FileNotFoundException;
import java.io.FileOutputStream;
import java.io.IOException;
import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.io.OutputStreamWriter;
import java.lang.StringBuilder;
import java.text.ParseException;
import java.util.ArrayList;
import java.util.Arrays;
import java.util.Collection;
import java.util.HashMap;
import java.util.Iterator;
import java.util.LinkedHashSet;
import java.util.LinkedList;
import java.util.List;
import java.util.Properties;
import java.util.regex.Pattern;
import java.util.Set;
import java.util.concurrent.ConcurrentHashMap;
import java.sql.*;
import java.text.SimpleDateFormat;

import org.apache.log4j.Logger;
import org.marc4j.marc.ControlField;
import org.marc4j.marc.DataField;
import org.marc4j.marc.Record;
import org.marc4j.marc.Subfield;
import org.marc4j.marc.VariableField;
import org.solrmarc.callnum.DeweyCallNumber;
import org.solrmarc.callnum.LCCallNumber;
import org.solrmarc.tools.CallNumUtils;
import org.solrmarc.tools.SolrMarcIndexerException;
import org.solrmarc.tools.Utils;
import org.ini4j.Ini;

import javax.xml.parsers.DocumentBuilder;
import javax.xml.parsers.DocumentBuilderFactory;

import org.w3c.dom.Document;
import org.w3c.dom.Node;
import org.w3c.dom.NodeList;

/**
 *
 * @author Robert Haschart
 * @version $Id: VuFindIndexer.java 224 2008-11-05 19:33:21Z asnagy $
 *
 */
public class VuFindIndexer extends SolrIndexer
{
    // Initialize logging category
    static Logger logger = Logger.getLogger(VuFindIndexer.class.getName());

    // Initialize VuFind database connection (null until explicitly activated)
    private Connection vufindDatabase = null;
    private UpdateDateTracker tracker = null;

    // the SimpleDateFormat class is not Thread-safe the below line were changes to be not static 
    // which given the rest of the design of SolrMarc will make them work correctly.
    private SimpleDateFormat marc005date = new SimpleDateFormat("yyyyMMddHHmmss.S");
    private SimpleDateFormat marc008date = new SimpleDateFormat("yyMMdd");

    private static ConcurrentHashMap<String, Ini> configCache = new ConcurrentHashMap<String, Ini>();

    // Shutdown flag:
    private boolean shuttingDown = false;

    // VuFind-specific configs:
    private Properties vuFindConfigs = null;

    /**
     * Default constructor
     * @param propertiesMapFile the {@code x_index.properties} file mapping solr
     *  field names to values in the marc records
     * @param propertyDirs array of directories holding properties files
     * @throws Exception if {@code SolrIndexer} constructor threw an exception.
     */
    public VuFindIndexer(final String propertiesMapFile, final String[] propertyDirs)
            throws FileNotFoundException, IOException, ParseException {
        super(propertiesMapFile, propertyDirs);
        try {
            vuFindConfigs = Utils.loadProperties(propertyDirs, "vufind.properties");
        } catch (IllegalArgumentException e) {
            // If the properties load failed, don't worry about it -- we'll use defaults.
        }
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

    /**
     * Given the base name of a configuration file, locate the full path.
     * @param filename base name of a configuration file
     */
    private File findConfigFile(String filename)
    {
        // Find VuFind's home directory in the environment; if it's not available,
        // try using a relative path on the assumption that we are currently in
        // VuFind's import subdirectory:
        String vufindHome = System.getenv("VUFIND_HOME");
        if (vufindHome == null) {
            vufindHome = "..";
        }

        // Check for VuFind 2.0's local directory environment variable:
        String vufindLocal = System.getenv("VUFIND_LOCAL_DIR");

        // Get the relative VuFind path from the properties file, defaulting to
        // the 2.0-style config/vufind if necessary.
        String relativeConfigPath = Utils.getProperty(
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
            try {
                ini.load(new FileReader(findConfigFile(filename)));
                configCache.putIfAbsent(filename, ini);
            } catch (Throwable e) {
                dieWithError("Unable to access " + filename);
            }
        }
        return configCache.get(filename);
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
     * Connect to the VuFind database if we do not already have a connection.
     */
    private void connectToDatabase()
    {
        // Already connected?  Do nothing further!
        if (vufindDatabase != null) {
            return;
        }

        String dsn = getConfigSetting("config.ini", "Database", "database");

        try {
            // Parse key settings from the PHP-style DSN:
            String username = "";
            String password = "";
            String classname = "invalid";
            String prefix = "invalid";
            if (dsn.substring(0, 8).equals("mysql://")) {
                classname = "com.mysql.jdbc.Driver";
                prefix = "mysql";
            } else if (dsn.substring(0, 8).equals("pgsql://")) {
                classname = "org.postgresql.Driver";
                prefix = "postgresql";
            }

            Class.forName(classname).newInstance();
            String[] parts = dsn.split("://");
            if (parts.length > 1) {
                parts = parts[1].split("@");
                if (parts.length > 1) {
                    dsn = prefix + "://" + parts[1];
                    parts = parts[0].split(":");
                    username = parts[0];
                    if (parts.length > 1) {
                        password = parts[1];
                    }
                }
            }

            // Connect to the database:
            vufindDatabase = DriverManager.getConnection("jdbc:" + dsn, username, password);
        } catch (Throwable e) {
            dieWithError("Unable to connect to VuFind database");
        }

        Runtime.getRuntime().addShutdownHook(new VuFindShutdownThread(this));
    }

    private void disconnectFromDatabase()
    {
        if (vufindDatabase != null) {
            try {
                vufindDatabase.close();
            } catch (SQLException e) {
                System.err.println("Unable to disconnect from VuFind database");
                logger.error("Unable to disconnect from VuFind database");
            }
        }
    }

    public void shutdown()
    {
        disconnectFromDatabase();
        shuttingDown = true;
    }

    class VuFindShutdownThread extends Thread
    {
        private VuFindIndexer indexer;

        public VuFindShutdownThread(VuFindIndexer i)
        {
            indexer = i;
        }

        public void run()
        {
            indexer.shutdown();
        }
    }

    /**
     * Establish UpdateDateTracker object if not already available.
     */
    private void loadUpdateDateTracker() throws java.sql.SQLException
    {
        if (tracker == null) {
            connectToDatabase();
            tracker = new UpdateDateTracker(vufindDatabase);
        }
    }

    /**
     * Support method for getLatestTransaction.
     * @return Date extracted from 005 (or very old date, if unavailable)
     */
    private java.util.Date normalize005Date(String input)
    {
        // Normalize "null" strings to a generic bad value:
        if (input == null) {
            input = "null";
        }

        // Try to parse the date; default to "millisecond 0" (very old date) if we can't
        // parse the data successfully.
        java.util.Date retVal;
        try {
            retVal = marc005date.parse(input);
        } catch(java.text.ParseException e) {
            retVal = new java.util.Date(0);
        }
        return retVal;
    }

    /**
     * Support method for getLatestTransaction.
     * @return Date extracted from 008 (or very old date, if unavailable)
     */
    private java.util.Date normalize008Date(String input)
    {
        // Normalize "null" strings to a generic bad value:
        if (input == null || input.length() < 6) {
            input = "null";
        }

        // Try to parse the date; default to "millisecond 0" (very old date) if we can't
        // parse the data successfully.
        java.util.Date retVal;
        try {
            retVal = marc008date.parse(input.substring(0, 6));
        } catch(java.lang.StringIndexOutOfBoundsException e) {
            retVal = new java.util.Date(0);
        } catch(java.text.ParseException e) {
            retVal = new java.util.Date(0);
        }
        return retVal;
    }

    /**
     * Extract the latest transaction date from the MARC record.  This is useful
     * for detecting when a record has changed since the last time it was indexed.
     *
     * @param record MARC record
     * @return Latest transaction date.
     */
    public java.util.Date getLatestTransaction(Record record) {
        // First try the 005 -- this is most likely to have a precise transaction date:
        Set<String> dates = getFieldList(record, "005");
        if (dates != null) {
            Iterator<String> dateIter = dates.iterator();
            if (dateIter.hasNext()) {
                return normalize005Date(dateIter.next());
            }
        }

        // No luck with 005?  Try 008 next -- less precise, but better than nothing:
        dates = getFieldList(record, "008");
        if (dates != null) {
            Iterator<String> dateIter = dates.iterator();
            if (dateIter.hasNext()) {
                return normalize008Date(dateIter.next());
            }
        }

        // If we got this far, we couldn't find a valid value; return an arbitrary date:
        return new java.util.Date(0);
    }

    /**
     * Get all available publishers from the record.
     *
     * @param  record MARC record
     * @return set of publishers
     */
    public Set<String> getPublishers(final Record record) {
        Set<String> publishers = new LinkedHashSet<String>();

        // First check old-style 260b name:
        List<VariableField> list260 = record.getVariableFields("260");
        for (VariableField vf : list260)
        {
            DataField df = (DataField) vf;
            String currentString = "";
            for (Subfield current : df.getSubfields('b')) {
                currentString = currentString.trim().concat(" " + current.getData()).trim();
            }
            if (currentString.length() > 0) {
                publishers.add(currentString);
            }
        }

        // Now track down relevant RDA-style 264b names; we only care about
        // copyright and publication names (and ignore copyright names if
        // publication names are present).
        Set<String> pubNames = new LinkedHashSet<String>();
        Set<String> copyNames = new LinkedHashSet<String>();
        List<VariableField> list264 = record.getVariableFields("264");
        for (VariableField vf : list264)
        {
            DataField df = (DataField) vf;
            String currentString = "";
            for (Subfield current : df.getSubfields('b')) {
                currentString = currentString.trim().concat(" " + current.getData()).trim();
            }
            if (currentString.length() > 0) {
                char ind2 = df.getIndicator2();
                switch (ind2)
                {
                    case '1':
                        pubNames.add(currentString);
                        break;
                    case '4':
                        copyNames.add(currentString);
                        break;
                }
            }
        }
        if (pubNames.size() > 0) {
            publishers.addAll(pubNames);
        } else if (copyNames.size() > 0) {
            publishers.addAll(copyNames);
        }

        return publishers;
    }

    /**
     * Get all available dates from the record.
     *
     * @param  record MARC record
     * @return set of dates
     */
    public Set<String> getDates(final Record record) {
        Set<String> dates = new LinkedHashSet<String>();

        // First check old-style 260c date:
        List<VariableField> list260 = record.getVariableFields("260");
        for (VariableField vf : list260) {
            DataField df = (DataField) vf;
            List<Subfield> currentDates = df.getSubfields('c');
            for (Subfield sf : currentDates) {
                String currentDateStr = Utils.cleanDate(sf.getData());
                if (currentDateStr != null) dates.add(currentDateStr);
            }
        }

        // Now track down relevant RDA-style 264c dates; we only care about
        // copyright and publication dates (and ignore copyright dates if
        // publication dates are present).
        Set<String> pubDates = new LinkedHashSet<String>();
        Set<String> copyDates = new LinkedHashSet<String>();
        List<VariableField> list264 = record.getVariableFields("264");
        for (VariableField vf : list264) {
            DataField df = (DataField) vf;
            List<Subfield> currentDates = df.getSubfields('c');
            for (Subfield sf : currentDates) {
                String currentDateStr = Utils.cleanDate(sf.getData());
                char ind2 = df.getIndicator2();
                switch (ind2)
                {
                    case '1':
                        if (currentDateStr != null) pubDates.add(currentDateStr);
                        break;
                    case '4':
                        if (currentDateStr != null) copyDates.add(currentDateStr);
                        break;
                }
            }
        }
        if (pubDates.size() > 0) {
            dates.addAll(pubDates);
        } else if (copyDates.size() > 0) {
            dates.addAll(copyDates);
        }

        return dates;
    }

    /**
     * Get the earliest publication date from the record.
     *
     * @param  record MARC record
     * @return earliest date
     */
    public String getFirstDate(final Record record) {
        String result = null;
        Set<String> dates = getDates(record);
        for(String current: dates) {
            if (result == null || Integer.parseInt(current) < Integer.parseInt(result)) {
                result = current;
            }
        }
        return result;
    }

    /**
     * Determine Record Format(s)
     *
     * @param  record MARC record
     * @return set of record formats
     */
    public Set<String> getFormat(final Record record){
        Set<String> result = new LinkedHashSet<String>();
        String leader = record.getLeader().toString();
        char leaderBit;
        ControlField fixedField = (ControlField) record.getVariableField("008");
        DataField title = (DataField) record.getVariableField("245");
        String formatString;
        char formatCode = ' ';
        char formatCode2 = ' ';
        char formatCode4 = ' ';

        // check if there's an h in the 245
        if (title != null) {
            if (title.getSubfield('h') != null){
                if (title.getSubfield('h').getData().toLowerCase().contains("[electronic resource]")) {
                    result.add("Electronic");
                    return result;
                }
            }
        }

        // check the 007 - this is a repeating field
        List<VariableField> fields = record.getVariableFields("007");
        Iterator<VariableField> fieldsIter = fields.iterator();
        if (fields != null) {
            // TODO: update loop to for(:) syntax, but problem with type casting.
            ControlField formatField;
            while(fieldsIter.hasNext()) {
                formatField = (ControlField) fieldsIter.next();
                formatString = formatField.getData().toUpperCase();
                formatCode = formatString.length() > 0 ? formatString.charAt(0) : ' ';
                formatCode2 = formatString.length() > 1 ? formatString.charAt(1) : ' ';
                formatCode4 = formatString.length() > 4 ? formatString.charAt(4) : ' ';
                switch (formatCode) {
                    case 'A':
                        switch(formatCode2) {
                            case 'D':
                                result.add("Atlas");
                                break;
                            default:
                                result.add("Map");
                                break;
                        }
                        break;
                    case 'C':
                        switch(formatCode2) {
                            case 'A':
                                result.add("TapeCartridge");
                                break;
                            case 'B':
                                result.add("ChipCartridge");
                                break;
                            case 'C':
                                result.add("DiscCartridge");
                                break;
                            case 'F':
                                result.add("TapeCassette");
                                break;
                            case 'H':
                                result.add("TapeReel");
                                break;
                            case 'J':
                                result.add("FloppyDisk");
                                break;
                            case 'M':
                            case 'O':
                                result.add("CDROM");
                                break;
                            case 'R':
                                // Do not return - this will cause anything with an
                                // 856 field to be labeled as "Electronic"
                                break;
                            default:
                                result.add("Software");
                                break;
                        }
                        break;
                    case 'D':
                        result.add("Globe");
                        break;
                    case 'F':
                        result.add("Braille");
                        break;
                    case 'G':
                        switch(formatCode2) {
                            case 'C':
                            case 'D':
                                result.add("Filmstrip");
                                break;
                            case 'T':
                                result.add("Transparency");
                                break;
                            default:
                                result.add("Slide");
                                break;
                        }
                        break;
                    case 'H':
                        result.add("Microfilm");
                        break;
                    case 'K':
                        switch(formatCode2) {
                            case 'C':
                                result.add("Collage");
                                break;
                            case 'D':
                                result.add("Drawing");
                                break;
                            case 'E':
                                result.add("Painting");
                                break;
                            case 'F':
                                result.add("Print");
                                break;
                            case 'G':
                                result.add("Photonegative");
                                break;
                            case 'J':
                                result.add("Print");
                                break;
                            case 'L':
                                result.add("Drawing");
                                break;
                            case 'O':
                                result.add("FlashCard");
                                break;
                            case 'N':
                                result.add("Chart");
                                break;
                            default:
                                result.add("Photo");
                                break;
                        }
                        break;
                    case 'M':
                        switch(formatCode2) {
                            case 'F':
                                result.add("VideoCassette");
                                break;
                            case 'R':
                                result.add("Filmstrip");
                                break;
                            default:
                                result.add("MotionPicture");
                                break;
                        }
                        break;
                    case 'O':
                        result.add("Kit");
                        break;
                    case 'Q':
                        result.add("MusicalScore");
                        break;
                    case 'R':
                        result.add("SensorImage");
                        break;
                    case 'S':
                        switch(formatCode2) {
                            case 'D':
                                result.add("SoundDisc");
                                break;
                            case 'S':
                                result.add("SoundCassette");
                                break;
                            default:
                                result.add("SoundRecording");
                                break;
                        }
                        break;
                    case 'V':
                        switch(formatCode2) {
                            case 'C':
                                result.add("VideoCartridge");
                                break;
                            case 'D':
                                switch(formatCode4) {
                                    case 'S':
                                        result.add("BRDisc");
                                        break;
                                    case 'V':
                                    default:
                                        result.add("VideoDisc");
                                        break;
                                }
                                break;
                            case 'F':
                                result.add("VideoCassette");
                                break;
                            case 'R':
                                result.add("VideoReel");
                                break;
                            default:
                                result.add("Video");
                                break;
                        }
                        break;
                }
            }
            if (!result.isEmpty()) {
                return result;
            }
        }

        // check the Leader at position 6
        leaderBit = leader.charAt(6);
        switch (Character.toUpperCase(leaderBit)) {
            case 'C':
            case 'D':
                result.add("MusicalScore");
                break;
            case 'E':
            case 'F':
                result.add("Map");
                break;
            case 'G':
                result.add("Slide");
                break;
            case 'I':
                result.add("SoundRecording");
                break;
            case 'J':
                result.add("MusicRecording");
                break;
            case 'K':
                result.add("Photo");
                break;
            case 'M':
                result.add("Electronic");
                break;
            case 'O':
            case 'P':
                result.add("Kit");
                break;
            case 'R':
                result.add("PhysicalObject");
                break;
            case 'T':
                result.add("Manuscript");
                break;
        }
        if (!result.isEmpty()) {
            return result;
        }

        // check the Leader at position 7
        leaderBit = leader.charAt(7);
        switch (Character.toUpperCase(leaderBit)) {
            // Monograph
            case 'M':
                if (formatCode == 'C') {
                    result.add("eBook");
                } else {
                    result.add("Book");
                }
                break;
            // Component parts
            case 'A':
                result.add("BookComponentPart");
                break;
            case 'B':
                result.add("SerialComponentPart");
                break;
            // Serial
            case 'S':
                // Look in 008 to determine what type of Continuing Resource
                formatCode = fixedField.getData().toUpperCase().charAt(21);
                switch (formatCode) {
                    case 'N':
                        result.add("Newspaper");
                        break;
                    case 'P':
                        result.add("Journal");
                        break;
                    default:
                        result.add("Serial");
                        break;
                }
        }

        // Nothing worked!
        if (result.isEmpty()) {
            result.add("Unknown");
        }

        return result;
    }

    /**
     * Get call numbers of a specific type.
     * 
     * <p>{@code fieldSpec} is of form {@literal 098abc:099ab}, does not accept subfield ranges.
     *
     *
     * @param record  current MARC record
     * @param fieldSpec  which MARC fields / subfields need to be analyzed
     * @param callTypeSf  subfield containing call number type, single character only
     * @param callType  literal call number code
     * @param result  a collection to gather the call numbers
     * @return collection of call numbers, same object as {@code result}
     */
    public static Collection<String> getCallNumberByTypeCollector(
            Record record, String fieldSpec, String callTypeSf, String callType, Collection<String> result) {
        for (String tag : fieldSpec.split(":")) {
            // Check to ensure tag length is at least 3 characters
            if (tag.length() < 3) {
                //TODO: Should this go to a log? Better message for a bad tag in a field spec?
                System.err.println("Invalid tag specified: " + tag);
                continue;
            }
            String dfTag = tag.substring(0, 3);
            String sfSpec = null;
            if (tag.length() > 3) {
                    sfSpec = tag.substring(3);
            }

            // do all fields for this tag
            for (VariableField vf : record.getVariableFields(dfTag)) {
                // Assume tag represents a DataField
                DataField df = (DataField) vf;
                boolean callTypeMatch = false;
                
                // Assume call type subfield could repeat
                for (Subfield typeSf : df.getSubfields(callTypeSf)) {
                    if (callTypeSf.indexOf(typeSf.getCode()) != -1 && typeSf.getData().equals(callType)) {
                        callTypeMatch = true;
                    }
                }
                System.err.println("callTypeMatch after loop: " + callTypeMatch);
                if (callTypeMatch) {
                    result.add(df.getSubfieldsAsString(sfSpec));
                }
            } // end loop over variable fields
        } // end loop over fieldSpec
        return result;
    }
    

    /**
     * Get call numbers of a specific type.
     * 
     * <p>{@code fieldSpec} is of form {@literal 098abc:099ab}, does not accept subfield ranges.
     *
     * @param record  current MARC record
     * @param fieldSpec  which MARC fields / subfields need to be analyzed
     * @param callTypeSf  subfield containing call number type, single character only
     * @param callType  literal call number code
     * @return set of call numbers
     */
    public static Set<String> getCallNumberByType(Record record, String fieldSpec, String callTypeSf, String callType) {
        return (Set<String>) getCallNumberByTypeCollector(record, fieldSpec, callTypeSf, callType,
                new LinkedHashSet<String>());
    }

    /**
     * Get call numbers of a specific type.
     * 
     * <p>{@code fieldSpec} is of form {@literal 098abc:099ab}, does not accept subfield ranges.
     *
     * @param record  current MARC record
     * @param fieldSpec  which MARC fields / subfields need to be analyzed
     * @param callTypeSf  subfield containing call number type, single character only
     * @param callType  literal call number code
     * @return list of call numbers
     */
    public static List<String> getCallNumberByTypeAsList(Record record, String fieldSpec, String callTypeSf, String callType) {
        return (List<String>) getCallNumberByTypeCollector(record, fieldSpec, callTypeSf, callType,
                new ArrayList<String>());
    }
    
    /**
     * Extract the full call number from a record, stripped of spaces
     * @param record MARC record
     * @return Call number label
     * @deprecated Obsolete as of VuFind 2.4.
     *          This method exists only to support the VuFind call number search, version <= 2.3.
     *          As of VuFind 2.4, the munging for call number search in handled entirely in Solr.
     */
    @Deprecated
    public String getFullCallNumber(final Record record) {

        return(getFullCallNumber(record, "099ab:090ab:050ab"));
    }

    /**
     * Extract the full call number from a record, stripped of spaces
     * @param record MARC record
     * @param fieldSpec taglist for call number fields
     * @return Call number label
     * @deprecated Obsolete as of VuFind 2.4.
     *          This method exists only to support the VuFind call number search, version <= 2.3.
     *          As of VuFind 2.4, the munging for call number search in handled entirely in Solr.
     */
    @Deprecated
    public String getFullCallNumber(final Record record, String fieldSpec) {

        String val = getFirstFieldVal(record, fieldSpec);

        if (val != null) {
            return val.toUpperCase().replaceAll(" ", "");
        } else {
            return val;
        }
    }

    /**
     * Extract the call number label from a record
     * @param record MARC record
     * @return Call number label
     */
    public String getCallNumberLabel(final Record record) {

        return getCallNumberLabel(record, "090a:050a");
    }

    /**
     * Extract the call number label from a record
     * @param record MARC record
     * @param fieldSpec taglist for call number fields
     * @return Call number label
     */
    public String getCallNumberLabel(final Record record, String fieldSpec) {

        String val = getFirstFieldVal(record, fieldSpec);

        if (val != null) {
            int dotPos = val.indexOf(".");
            if (dotPos > 0) {
                val = val.substring(0, dotPos);
            }
            return val.toUpperCase();
        } else {
            return val;
        }
    }

    /**
     * Extract the subject component of the call number
     *
     * Can return null
     *
     * @param record MARC record
     * @return Call number subject letters
     */
    public String getCallNumberSubject(final Record record) {

        return(getCallNumberSubject(record, "090a:050a"));
    }

    /**
     * Extract the subject component of the call number
     *
     * Can return null
     *
     * @param record current MARC record
     * @return Call number subject letters
     */
    public String getCallNumberSubject(final Record record, String fieldSpec) {

        String val = getFirstFieldVal(record, fieldSpec);

        if (val != null) {
            String [] callNumberSubject = val.toUpperCase().split("[^A-Z]+");
            if (callNumberSubject.length > 0)
            {
                return callNumberSubject[0];
            }
        }
        return(null);
    }

    /**
     * Normalize a single LC call number
     * @param record current MARC record
     * @return String Normalized LCCN
     */
    public String getFullCallNumberNormalized(final Record record) {

        return(getFullCallNumberNormalized(record, "099ab:090ab:050ab"));
    }

    /**
     * Normalize a single LC call number
     * @param record current MARC record
     * @param fieldSpec which MARC fields / subfields need to be analyzed
     * @return String Normalized LC call number
     */
    public String getFullCallNumberNormalized(final Record record, String fieldSpec) {

        // TODO: is the null fieldSpec still an issue?
        if (fieldSpec != null) {
            String cn = getFirstFieldVal(record, fieldSpec);
            return (new LCCallNumber(cn)).getShelfKey();
        }
        // If we got this far, we couldn't find a valid value:
        return null;
    }

    /**
     * Determine if a record is illustrated.
     *
     * @param  LC call number
     * @return "Illustrated" or "Not Illustrated"
     */
    public String isIllustrated(Record record) {
        String leader = record.getLeader().toString();

        // Does the leader indicate this is a "language material" that might have extra
        // illustration details in the fixed fields?
        if (leader.charAt(6) == 'a') {
            String currentCode = "";         // for use in loops below

            // List of 008/18-21 codes that indicate illustrations:
            String illusCodes = "abcdefghijklmop";

            // Check the illustration characters of the 008:
            ControlField fixedField = (ControlField) record.getVariableField("008");
            if (fixedField != null) {
                String fixedFieldText = fixedField.getData().toLowerCase();
                for (int i = 18; i <= 21; i++) {
                    if (i < fixedFieldText.length()) {
                        currentCode = fixedFieldText.substring(i, i + 1);
                        if (illusCodes.contains(currentCode)) {
                            return "Illustrated";
                        }
                    }
                }
            }

            // Now check if any 006 fields apply:
            List<VariableField> fields = record.getVariableFields("006");
            Iterator<VariableField> fieldsIter = fields.iterator();
            if (fields != null) {
                while(fieldsIter.hasNext()) {
                    fixedField = (ControlField) fieldsIter.next();
                    String fixedFieldText = fixedField.getData().toLowerCase();
                    for (int i = 1; i <= 4; i++) {
                         if (i < fixedFieldText.length()) {
                            currentCode = fixedFieldText.substring(i, i + 1);
                            if (illusCodes.contains(currentCode)) {
                                return "Illustrated";
                            }
                        }
                    }
                }
            }
        }

        // Now check for interesting strings in 300 subfield b:
        List<VariableField> fields = record.getVariableFields("300");
        Iterator<VariableField> fieldsIter = fields.iterator();
        if (fields != null) {
            DataField physical;
            while(fieldsIter.hasNext()) {
                physical = (DataField) fieldsIter.next();
                List<Subfield> subfields = physical.getSubfields('b');
                for (Subfield sf: subfields) {
                    String desc = sf.getData().toLowerCase();
                    if (desc.contains("ill.") || desc.contains("illus.")) {
                        return "Illustrated";
                    }
                }
            }
        }

        // If we made it this far, we found no sign of illustrations:
        return "Not Illustrated";
    }


    /**
     * Normalize LC numbers for sorting purposes (use only the first valid number!).
     * Will return first call number found if none pass validation,
     * or empty string if no call numbers.
     *
     * @param  record current MARC record
     * @param  fieldSpec which MARC fields / subfields need to be analyzed
     * @return sortable shelf key of the first valid LC number encountered, 
     *         otherwise shelf key of the first call number found.
     */
    public String getLCSortable(Record record, String fieldSpec) {
        // Loop through the specified MARC fields:
        Set<String> input = getFieldList(record, fieldSpec);
        String firstCall = "";
        for (String current : input) {
            // If this is a valid LC number, return the sortable shelf key:
            LCCallNumber callNum = new LCCallNumber(current);
            if (callNum.isValid()) {
                return callNum.getShelfKey();   // RETURN first valid
            }
            if (firstCall.length() == 0) {
                firstCall = current;
            }
        }

        // If we made it this far, did not find a valid LC number, so use what we have:
        return new LCCallNumber(firstCall).getShelfKey();
    }

    /**
     * Get sort key for first LC call number, identified by call type.
     * 
     * <p>{@code fieldSpec} is of form {@literal 098abc:099ab}, does not accept subfield ranges.
     *
     *
     * @param record  current MARC record
     * @param fieldSpec  which MARC fields / subfields need to be analyzed
     * @param callTypeSf  subfield containing call number type, single character only
     * @param callType  literal call number code
     * @return sort key for first identified LC call number
     */
    public String getLCSortableByType(
            Record record, String fieldSpec, String callTypeSf, String callType) {
        String sortKey = null;
        for (String tag : fieldSpec.split(":")) {
            // Check to ensure tag length is at least 3 characters
            if (tag.length() < 3) {
                //TODO: Should this go to a log? Better message for a bad tag in a field spec?
                System.err.println("Invalid tag specified: " + tag);
                continue;
            }
            String dfTag = tag.substring(0, 3);
            String sfSpec = null;
            if (tag.length() > 3) {
                    sfSpec = tag.substring(3);
            }

            // do all fields for this tag
            for (VariableField vf : record.getVariableFields(dfTag)) {
                // Assume tag represents a DataField
                DataField df = (DataField) vf;
                boolean callTypeMatch = false;
                
                // Assume call type subfield could repeat
                for (Subfield typeSf : df.getSubfields(callTypeSf)) {
                    if (callTypeSf.indexOf(typeSf.getCode()) != -1 && typeSf.getData().equals(callType)) {
                        callTypeMatch = true;
                    }
                }
                // take the first call number coded as LC
                if (callTypeMatch) {
                    sortKey = new LCCallNumber(df.getSubfieldsAsString(sfSpec)).getShelfKey();
                    break;
                }
            } // end loop over variable fields
        } // end loop over fieldSpec
        return sortKey;
    }

    /**
     * Extract a numeric portion of the Dewey decimal call number
     *
     * Can return null
     *
     * @param record current MARC record
     * @param fieldSpec which MARC fields / subfields need to be analyzed
     * @param precisionStr a decimal number (represented in string format) showing the
     *  desired precision of the returned number; i.e. 100 to round to nearest hundred,
     *  10 to round to nearest ten, 0.1 to round to nearest tenth, etc.
     * @return Set containing requested numeric portions of Dewey decimal call numbers
     */
    public Set<String> getDeweyNumber(Record record, String fieldSpec, String precisionStr) {
        // Initialize our return value:
        Set<String> result = new LinkedHashSet<String>();

        // Precision comes in as a string, but we need to convert it to a float:
        float precision = Float.parseFloat(precisionStr);

        // Loop through the specified MARC fields:
        Set<String> input = getFieldList(record, fieldSpec);
        for (String current: input) {
            DeweyCallNumber callNum = new DeweyCallNumber(current);
            if (callNum.isValid()) {
                // Convert the numeric portion of the call number into a float:
                float currentVal = Float.parseFloat(callNum.getClassification());
                
                // Round the call number value to the specified precision:
                Float finalVal = new Float(Math.floor(currentVal / precision) * precision);
                
                // Convert the rounded value back to a string (with leading zeros) and save it:
                // TODO: Provide different conversion to remove CallNumUtils dependency
                result.add(CallNumUtils.normalizeFloat(finalVal.toString(), 3, -1));
            }
        }

        // If we found no call number matches, return null; otherwise, return our results:
        if (result.isEmpty())
            return null;
        return result;
    }

    /**
     * Normalize Dewey numbers for searching purposes (uppercase/stripped spaces)
     *
     * Can return null
     *
     * @param record current MARC record
     * @param fieldSpec which MARC fields / subfields need to be analyzed
     * @return Set containing normalized Dewey numbers extracted from specified fields.
     */
    public Set<String> getDeweySearchable(Record record, String fieldSpec) {
        // Initialize our return value:
        Set<String> result = new LinkedHashSet<String>();

        // Loop through the specified MARC fields:
        Set<String> input = getFieldList(record, fieldSpec);
        Iterator<String> iter = input.iterator();
        while (iter.hasNext()) {
            // Get the current string to work on:
            String current = iter.next();

            // Add valid strings to the set, normalizing them to be all uppercase
            // and free from whitespace.
            DeweyCallNumber callNum = new DeweyCallNumber(current);
            if (callNum.isValid()) {
                result.add(callNum.toString().toUpperCase().replaceAll(" ", ""));
            }
        }

        // If we found no call numbers, return null; otherwise, return our results:
        if (result.isEmpty())
            return null;
        return result;
    }

    /**
     * Normalize Dewey numbers for sorting purposes (use only the first valid number!)
     *
     * Can return null
     *
     * @param record current MARC record
     * @param fieldSpec which MARC fields / subfields need to be analyzed
     * @return String containing the first valid Dewey number encountered, normalized
     *         for sorting purposes.
     */
    public String getDeweySortable(Record record, String fieldSpec) {
        // Loop through the specified MARC fields:
        Set<String> input = getFieldList(record, fieldSpec);
        Iterator<String> iter = input.iterator();
        while (iter.hasNext()) {
            // Get the current string to work on:
            String current = iter.next();

            // If this is a valid Dewey number, return the sortable shelf key:
            DeweyCallNumber callNum = new DeweyCallNumber(current);
            if (callNum.isValid()) {
                return callNum.getShelfKey();
            }
        }

        // If we made it this far, we didn't find a valid sortable Dewey number:
        return null;
    }

    /**
     * Get sort key for first Dewey call number, identified by call type.
     * 
     * <p>{@code fieldSpec} is of form {@literal 098abc:099ab}, does not accept subfield ranges.
     *
     *
     * @param record  current MARC record
     * @param fieldSpec  which MARC fields / subfields need to be analyzed
     * @param callTypeSf  subfield containing call number type, single character only
     * @param callType  literal call number code
     * @return sort key for first identified Dewey call number
     */
    public static String getDeweySortableByType(
            Record record, String fieldSpec, String callTypeSf, String callType) {
        String sortKey = null;
        for (String tag : fieldSpec.split(":")) {
            // Check to ensure tag length is at least 3 characters
            if (tag.length() < 3) {
                //TODO: Should this go to a log? Better message for a bad tag in a field spec?
                System.err.println("Invalid tag specified: " + tag);
                continue;
            }
            String dfTag = tag.substring(0, 3);
            String sfSpec = null;
            if (tag.length() > 3) {
                    sfSpec = tag.substring(3);
            }

            // do all fields for this tag
            for (VariableField vf : record.getVariableFields(dfTag)) {
                // Assume tag represents a DataField
                DataField df = (DataField) vf;
                boolean callTypeMatch = false;
                
                // Assume call type subfield could repeat
                for (Subfield typeSf : df.getSubfields(callTypeSf)) {
                    if (callTypeSf.indexOf(typeSf.getCode()) != -1 && typeSf.getData().equals(callType)) {
                        callTypeMatch = true;
                    }
                }
                // take the first call number coded as Dewey
                if (callTypeMatch) {
                    sortKey = new DeweyCallNumber(df.getSubfieldsAsString(sfSpec)).getShelfKey();
                    break;
                }
            } // end loop over variable fields
        } // end loop over fieldSpec
        return sortKey;
    }

    
    /**
     * Normalize Dewey numbers for AlphaBrowse sorting purposes (use all numbers!)
     *
     * Can return null
     *
     * @param record current MARC record
     * @param fieldSpec which MARC fields / subfields need to be analyzed
     * @return List containing normalized Dewey numbers extracted from specified fields.
     */
    public List<String> getDeweySortables(Record record, String fieldSpec) {
        // Initialize our return value:
        List<String> result = new LinkedList<String>();

        // Loop through the specified MARC fields:
        Set<String> input = getFieldList(record, fieldSpec);
        Iterator<String> iter = input.iterator();
        while (iter.hasNext()) {
            // Get the current string to work on:
            String current = iter.next();

            // gather all sort keys, even if number is not valid
            DeweyCallNumber callNum = new DeweyCallNumber(current);
            result.add(callNum.getShelfKey());
        }

        // If we found no call numbers, return null; otherwise, return our results:
        if (result.isEmpty())
            return null;
        return result;
    }

    /**
     * Determine the longitude and latitude of the items location.
     *
     * @param  record current MARC record
     * @return string of form "longitude, latitude"
     */
    public String getLongLat(Record record) {
        // Check 034 subfield d and f
        List<VariableField> fields = record.getVariableFields("034");
        Iterator<VariableField> fieldsIter = fields.iterator();
        if (fields != null) {
            DataField physical;
            while(fieldsIter.hasNext()) {
                physical = (DataField) fieldsIter.next();
                String val = null;

                List<Subfield> subfields_d = physical.getSubfields('d');
                Iterator<Subfield> subfieldsIter_d = subfields_d.iterator();
                if (subfields_d != null) {
                    while (subfieldsIter_d.hasNext()) {
                        val = subfieldsIter_d.next().getData().trim();
                        if (!val.matches("-?\\d+(.\\d+)?")) {
                            return null;
                        }
                    }
                }
                List<Subfield> subfields_f = physical.getSubfields('f');
                Iterator<Subfield> subfieldsIter_f = subfields_f.iterator();
                if (subfields_f != null) {
                    while (subfieldsIter_f.hasNext()) {
                        String val2 = subfieldsIter_f.next().getData().trim();
                        if (!val2.matches("-?\\d+(.\\d+)?")) {
                            return null;
                        }
                        val = val + ',' + val2;
                    }
                }
            return val;
            }
        }
        //otherwise return null
        return null;
    }

    /**
     * Update the index date in the database for the specified core/ID pair.  We
     * maintain a database of "first/last indexed" times separately from Solr to
     * allow the history of our indexing activity to be stored permanently in a
     * fashion that can survive even a total Solr rebuild.
     */
    public UpdateDateTracker updateTracker(String core, String id, java.util.Date latestTransaction)
    {
        // Update the database (if necessary):
        try {
            // Initialize date tracker if not already initialized:
            loadUpdateDateTracker();

            tracker.index(core, id, latestTransaction);
        } catch (java.sql.SQLException e) {
            // If we're in the process of shutting down, an error is expected:
            if (!shuttingDown) {
                dieWithError("Unexpected database error");
            }
        }

        // Send back the tracker object so the caller can use it (helpful for
        // use in BeanShell scripts).
        return tracker;
    }

    /**
     * Get the "first indexed" date for the current record.  (This is the first
     * time that SolrMarc ever encountered this particular record).
     *
     * @param record current MARC record
     * @param fieldSpec fields / subfields to be analyzed
     * @param core core name
     * @return ID string
     */
    public String getFirstIndexed(Record record, String fieldSpec, String core) {
        // Update the database, then send back the first indexed date:
        updateTracker(core, getFirstFieldVal(record, fieldSpec), getLatestTransaction(record));
        return tracker.getFirstIndexed();
    }

    /**
     * Get the "first indexed" date for the current record.  (This is the first
     * time that SolrMarc ever encountered this particular record).
     *
     * @param record current MARC record
     * @param fieldSpec fields / subfields to be analyzed
     * @return ID string
     */
    public String getFirstIndexed(Record record, String fieldSpec) {
        return getFirstIndexed(record, fieldSpec, "biblio");
    }

    /**
     * Get the "first indexed" date for the current record.  (This is the first
     * time that SolrMarc ever encountered this particular record).
     *
     * @param record current MARC record
     * @return ID string
     */
    public String getFirstIndexed(Record record) {
        return getFirstIndexed(record, "001", "biblio");
    }

    /**
     * Get the "last indexed" date for the current record.  (This is the last time
     * the record changed from SolrMarc's perspective).
     *
     * @param record current MARC record
     * @param fieldSpec fields / subfields to be analyzed
     * @param core core name
     * @return ID string
     */
    public String getLastIndexed(Record record, String fieldSpec, String core) {
        // Update the database, then send back the last indexed date:
        updateTracker(core, getFirstFieldVal(record, fieldSpec), getLatestTransaction(record));
        return tracker.getLastIndexed();
    }

    /**
     * Get the "last indexed" date for the current record.  (This is the last time
     * the record changed from SolrMarc's perspective).
     *
     * @param record current MARC record
     * @param fieldSpec fields / subfields to analyze
     * @return ID string
     */
    public String getLastIndexed(Record record, String fieldSpec) {
        return getLastIndexed(record, fieldSpec, "biblio");
    }

    /**
     * Get the "last indexed" date for the current record.  (This is the last time
     * the record changed from SolrMarc's perspective).
     *
     * @param record current MARC record
     * @return ID string
     */
    public String getLastIndexed(Record record) {
        return getLastIndexed(record, "001", "biblio");
    }

    /**
     * Load configurations for the full text parser.  Return an array containing the
     * parser type in the first element and the parser configuration in the second
     * element.
     *
     * @return String[]
     */
    public String[] getFulltextParserSettings()
    {
        String parserType = getConfigSetting(
            "fulltext.ini", "General", "parser"
        );
        if (null != parserType) {
            parserType = parserType.toLowerCase();
        }

        // Is Aperture active?
        String aperturePath = getConfigSetting(
            "fulltext.ini", "Aperture", "webcrawler"
        );
        if ((null == parserType && null != aperturePath)
            || (null != parserType && parserType.equals("aperture"))
        ) {
            String[] array = { "aperture", aperturePath };
            return array;
        }

        // Is Tika active?
        String tikaPath = getConfigSetting(
            "fulltext.ini", "Tika", "path"
        );
        if ((null == parserType && null != tikaPath)
            || (null != parserType && parserType.equals("tika"))
        ) {
            String[] array = { "tika", tikaPath };
            return array;
        }

        // No recognized parser found:
        String[] array = { "none", null };
        return array;
    }

    /**
     * Extract full-text from the documents referenced in the tags
     *
     * @param Record record current MARC record
     * @param String field spec to search for URLs
     * @param String only harvest files matching this extension (null for all)
     * @return String The full-text
     */
    public String getFulltext(Record record, String fieldSpec, String extension) {
        String result = "";

        // Get the web crawler settings (and return no text if it is unavailable)
        String[] parserSettings = getFulltextParserSettings();
        if (parserSettings[0].equals("none")) {
            return null;
        }

        // Loop through the specified MARC fields:
        Set<String> fields = getFieldList(record, fieldSpec);
        Iterator<String> fieldsIter = fields.iterator();
        if (fields != null) {
            while(fieldsIter.hasNext()) {
                // Get the current string to work on (and sanitize spaces):
                String current = fieldsIter.next().replaceAll(" ", "%20");
                // Filter by file extension
                if (extension == null || current.endsWith(extension)) {
                    // Load the parser output for each tag into a string
                    result = result + harvestWithParser(current, parserSettings);
                }
            }
        }
        // return string to SolrMarc
        return result;
    }

    /**
     * Extract full-text from the documents referenced in the tags
     *
     * @param Record record current MARC record
     * @param String field spec to search for URLs
     * @return String The full-text
     */
    public String getFulltext(Record record, String fieldSpec) {
        return getFulltext(record, fieldSpec, null);
    }

    /**
     * Extract full-text from the documents referenced in the tags
     *
     * @param Record record current MARC record
     * @return String The full-text
     */
    public String getFulltext(Record record) {
        return getFulltext(record, "856u", null);
    }

    /**
     * Clean up XML data generated by Aperture
     *
     * @param f file to clean
     * @return a fixed version of the file
     */
    public File sanitizeApertureOutput(File f) throws IOException
    {
        //clean up the aperture xml output
        File tempFile = File.createTempFile("buffer", ".tmp");
        FileOutputStream fw = new FileOutputStream(tempFile);
        OutputStreamWriter writer = new OutputStreamWriter(fw, "UTF8");

        //delete this control character from the File and save
        FileReader fr = new FileReader(f);
        BufferedReader br = new BufferedReader(fr);
        while (br.ready()) {
            writer.write(sanitizeFullText(br.readLine()));
        }
        writer.close();
        br.close();
        fr.close();

        return tempFile;
    }

    /**
     * Clean up bad characters in the full text.
     *
     * @param text text to clean
     * @return cleaned text
     */
    public String sanitizeFullText(String text)
    {
        String badChars = "[^\\u0009\\u000A\\u000D\\u0020-\\uD7FF\\uE000-\\uFFFD\\u10000-\\u10FFFF]+";
        return text.replaceAll(badChars, " ");
    }

    /**
     * Harvest the contents of a document file (PDF, Word, etc.) using Aperture.
     * This method will only work if Aperture is properly configured in the
     * fulltext.ini file.  Without proper configuration, this will simply return an
     * empty string.
     *
     * @param url the url extracted from the MARC tag.
     * @param aperturePath The path to Aperture
     * @return full-text extracted from url
     */
    public String harvestWithAperture(String url, String aperturePath) {
        String plainText = "";
        // Create temp file.
        File f = null;
        try {
            f = File.createTempFile("apt", ".txt");
        } catch (Throwable e) {
            dieWithError("Unable to create temporary file for full text harvest.");
        }

        // Delete temp file when program exits.
        f.deleteOnExit();

        // Construct the command to call Aperture
        String cmd = aperturePath + " -o " + f.getAbsolutePath().toString()  + " -x " + url;

        // Call Aperture
        //System.out.println("Loading fulltext from " + url + ". Please wait ...");
        try {
            Process p = Runtime.getRuntime().exec(cmd);
            
            // Debugging output
            /*
            BufferedReader stdInput = new BufferedReader(new
                InputStreamReader(p.getInputStream()));
            String s;
            while ((s = stdInput.readLine()) != null) {
                System.out.println(s);
            }
            */
            
            // Wait for Aperture to finish
            p.waitFor();
        } catch (Throwable e) {
            logger.error("Problem executing Aperture -- " + e.getMessage());
        }

        // Parse Aperture XML output
        Document xmlDoc = null;
        try {
            DocumentBuilderFactory dbf = DocumentBuilderFactory.newInstance();
            DocumentBuilder db = dbf.newDocumentBuilder();
            File tempFile = sanitizeApertureOutput(f);
            xmlDoc = db.parse(tempFile);
            NodeList nl = xmlDoc.getElementsByTagName("plainTextContent");
            if(nl != null && nl.getLength() > 0) {
                Node node = nl.item(0);
                if (node.getNodeType() == Node.ELEMENT_NODE) {
                    plainText = plainText + node.getTextContent();
                }
            }

            // we'll hold onto the temp file if it failed to parse for debugging;
            // only set it up to be deleted if we've made it this far successfully.
            tempFile.deleteOnExit();
        } catch (Throwable e) {
            logger.error("Problem parsing Aperture XML -- " + e.getMessage());
        }

        return plainText;
    }

    /**
     * Harvest the contents of a document file (PDF, Word, etc.) using Tika.
     * This method will only work if Tika is properly configured in the fulltext.ini
     * file.  Without proper configuration, this will simply return an empty string.
     *
     * @param url the url extracted from the MARC tag.
     * @param scraperPath path to Tika
     * @return the full-text
     */
    public String harvestWithTika(String url, String scraperPath) {

        // Construct the command
        String cmd = "java -jar " + scraperPath + " -t -eUTF8 " + url;

        StringBuilder stringBuilder= new StringBuilder();

        // Call our scraper
        //System.out.println("Loading fulltext from " + url + ". Please wait ...");
        try {
            Process p = Runtime.getRuntime().exec(cmd);
            BufferedReader stdInput = new BufferedReader(new
                InputStreamReader(p.getInputStream(), "UTF8"));

            // We'll build the string from the command output
            String s;
            while ((s = stdInput.readLine()) != null) {
                stringBuilder.append(s);
            }
        } catch (Throwable e) {
            logger.error("Problem with Tika -- " + e.getMessage());
        }

        return sanitizeFullText(stringBuilder.toString());
    }

    /**
     * Harvest the contents of a document file (PDF, Word, etc.) using the active parser.
     *
     * @param url the URL extracted from the MARC tag.
     * @param settings configuration settings from {@code getFulltextParserSettings}.
     * @return the full-text
     */
    public String harvestWithParser(String url, String[] settings) {
        if (settings[0].equals("aperture")) {
            return harvestWithAperture(url, settings[1]);
        } else if (settings[0].equals("tika")) {
            return harvestWithTika(url, settings[1]);
        }
        return null;
    }

    /**
     * Get access to the Logger object.
     *
     * @return Logger
     */
    public Logger getLogger()
    {
        return logger;
    }

    /**
     * Check if a particular Datafield meets the specified relator requirements.
     * @param authorField      Field to analyze
     * @param noRelatorAllowed Array of tag names which are allowed to be used with
     * no declared relator.
     * @param relatorConfig    The setting in author-classification.ini which
     * defines which relator terms are acceptable (or a colon-delimited list)
     * @return Boolean
     */
    protected Boolean authorHasAppropriateRelator(DataField authorField,
        String[] noRelatorAllowed, String relatorConfig
    ) {
        return getValidRelators(authorField, noRelatorAllowed, relatorConfig).size() > 0;
    }

    /**
     * Extract all valid relator terms from a list of subfields using a whitelist.
     * @param subfields      List of subfields to check
     * @param permittedRoles Whitelist to check against
     * @return Set of valid relator terms
     */
    public Set<String> getValidRelatorsFromSubfields(List<Subfield> subfields, List<String> permittedRoles)
    {
        Set<String> relators = new LinkedHashSet<String>();
        for (int j = 0; j < subfields.size(); j++) {
            String current = normalizeRelatorString(subfields.get(j).getData());
            if (permittedRoles.contains(current)) {
                relators.add(current);
            }
        }
        return relators;
    }

    /**
     * Extract all values that meet the specified relator requirements.
     * @param authorField      Field to analyze
     * @param noRelatorAllowed Array of tag names which are allowed to be used with
     * no declared relator.
     * @param relatorConfig    The setting in author-classification.ini which
     * defines which relator terms are acceptable (or a colon-delimited list)
     * @return Set
     */
    public Set<String> getValidRelators(DataField authorField,
        String[] noRelatorAllowed, String relatorConfig
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
            List permittedRoles = normalizeRelatorStringList(Arrays.asList(loadRelatorConfig(relatorConfig)));
            relators.addAll(getValidRelatorsFromSubfields(subfieldE, permittedRoles));
            relators.addAll(getValidRelatorsFromSubfields(subfield4, permittedRoles));
        }
        return relators;
    }

    /**
     * Parse a SolrMarc fieldspec into a map of tag name to set of subfield strings
     * (note that we need to map to a set rather than a single string, because the
     * same tag may repeat with different subfields to extract different sections
     * of the same field into distinct values).
     *
     * @param tagList The field specification to parse
     * @return HashMap
     */
    protected HashMap<String, Set<String>> getParsedTagList(String tagList)
    {
        String[] tags = tagList.split(":");//convert string input to array
        HashMap<String, Set<String>> tagMap = new HashMap<String, Set<String>>();
        //cut tags array up into key/value pairs in hash map
        Set<String> currentSet;
        for(int i = 0; i < tags.length; i++){
            String tag = tags[i].substring(0, 3);
            if (!tagMap.containsKey(tag)) {
                currentSet = new LinkedHashSet<String>();
                tagMap.put(tag, currentSet);
            } else {
                currentSet = tagMap.get(tag);
            }
            currentSet.add(tags[i].substring(3));
        }
        return tagMap;
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
     * @param firstOnly            Return first result only?
     * @return List result
     */
    public List<String> getAuthorsFilteredByRelator(Record record, String tagList,
        String acceptWithoutRelator, String relatorConfig, Boolean firstOnly
    ) {
        List<String> result = new LinkedList<String>();
        String[] noRelatorAllowed = acceptWithoutRelator.split(":");
        HashMap<String, Set<String>> parsedTagList = getParsedTagList(tagList);
        List fields = this.getFieldSetMatchingTagList(record, tagList);
        Iterator fieldsIter = fields.iterator();
        if (fields != null){
            DataField authorField;
            while (fieldsIter.hasNext()){
                authorField = (DataField) fieldsIter.next();
                // add all author types to the result set; if we have multiple relators, repeat the authors
                for (String iterator: getValidRelators(authorField, noRelatorAllowed, relatorConfig)) {
                    for (String subfields : parsedTagList.get(authorField.getTag())) {
                        String current = this.getDataFromVariableField(authorField, "["+subfields+"]", " ", false);
                        // TODO: we may eventually be able to use this line instead,
                        // but right now it's not handling separation between the
                        // subfields correctly, so it's commented out until that is
                        // fixed.
                        //String current = authorField.getSubfieldsAsString(subfields);
                        if (null != current) {
                            result.add(current);
                            if (firstOnly) {
                                return result;
                            }
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
            record, tagList, acceptWithoutRelator, relatorConfig, false
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
     * @return String
     */
    public String getFirstAuthorFilteredByRelator(Record record, String tagList,
        String acceptWithoutRelator, String relatorConfig
    ) {
        List<String> result = getAuthorsFilteredByRelator(
            record, tagList, acceptWithoutRelator, relatorConfig, true
        );
        for (String s : result) {
            return s;
        }
        return null;
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
     * @param firstOnly            Return first result only?
     * @return List result
     */
    public List getRelatorsFilteredByRelator(Record record, String tagList,
        String acceptWithoutRelator, String relatorConfig, Boolean firstOnly,
        String defaultRelator
    ) {
        List result = new LinkedList();
        String[] noRelatorAllowed = acceptWithoutRelator.split(":");
        HashMap<String, Set<String>> parsedTagList = getParsedTagList(tagList);
        List fields = this.getFieldSetMatchingTagList(record, tagList);
        Iterator fieldsIter = fields.iterator();
        if (fields != null){
            DataField authorField;
            while (fieldsIter.hasNext()){
                authorField = (DataField) fieldsIter.next();
                //add all author types to the result set
                result.addAll(getValidRelators(authorField, noRelatorAllowed, relatorConfig));
            }
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
     * @return List result
     */
    public List getRelatorsFilteredByRelator(Record record, String tagList,
        String acceptWithoutRelator, String relatorConfig
    ) {
        // default firstOnly to false!
        return getRelatorsFilteredByRelator(
            record, tagList, acceptWithoutRelator, relatorConfig, false, ""
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
                relators.append(this.getConfigSetting(
                    "author-classification.ini", "AuthorRoles", relatorSetting
                )).append(",");
            }
        }

        return relators.toString().split(",");
    }

    /**
     * Normalizes the strings in a list.
     *
     * @param stringList List of strings to be normalized
     * @return stringList Normalized List of strings 
     */
    protected List normalizeRelatorStringList(List<String> stringList)
    {
        for (int j = 0; j < stringList.size(); j++) {
            stringList.set(
                j,
                normalizeRelatorString(stringList.get(j))
            );
        }
        return stringList;
    }

    /**
     * Normalizes a string
     *
     * @param string String to be normalized
     * @return string
     */
    protected String normalizeRelatorString(String string)
    {
        return string
            .trim()
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
     * @param firstOnly            Return first result only?
     * @return List result
     */
    public List<String> getAuthorInitialsFilteredByRelator(Record record, String tagList,
        String acceptWithoutRelator, String relatorConfig
    ) {
        List<String> authors = getAuthorsFilteredByRelator(record, tagList, acceptWithoutRelator, relatorConfig);
        List<String> result = new LinkedList<String>();
        for (String author : authors) {
            result.add(this.processInitials(author));
        }
        return result;
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

    /**
     * Normalize trailing punctuation. This mimics the functionality built into VuFind's
     * textFacet field type, so that you can get equivalent values when indexing into
     * a string field. (Useful for docValues support).
     *
     * Can return null
     *
     * @param record current MARC record
     * @param fieldSpec which MARC fields / subfields need to be analyzed
     * @return Set containing normalized values
     */
    public Set<String> normalizeTrailingPunctuation(Record record, String fieldSpec) {
        // Initialize our return value:
        Set<String> result = new LinkedHashSet<String>();

        // Loop through the specified MARC fields:
        Set<String> input = getFieldList(record, fieldSpec);
        Pattern pattern = Pattern.compile("(?<!\b[A-Z])[.\\s]*$");
        for (String current: input) {
            result.add(pattern.matcher(current).replaceAll(""));
        }

        // If we found no matches, return null; otherwise, return our results:
        return result.isEmpty() ? null : result;
    }
}