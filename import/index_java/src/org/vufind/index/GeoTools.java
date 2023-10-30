package org.vufind.index;
/**
 * Geographic indexing routines.
 *
 * This code is designed to get latitude and longitude coordinates.
 * Records can have multiple coordinates sets of points and/or rectangles.
 * Points are represented by coordinate sets where N=S E=W.
 *
 * code adapted from xrosecky - Moravian Library
 * https://github.com/moravianlibrary/VuFind-2.x/blob/master/import/index_scripts/geo.bsh
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

import java.io.*;
import java.text.SimpleDateFormat;
import java.util.ArrayList;
import java.util.Date;
import java.util.HashMap;
import java.util.List;
import java.util.Properties;
import java.util.regex.Matcher;
import java.util.regex.Pattern;
import org.apache.log4j.Logger;
import org.marc4j.marc.ControlField;
import org.marc4j.marc.DataField;
import org.marc4j.marc.Record;
import org.marc4j.marc.Subfield;
import org.marc4j.marc.VariableField;
import org.solrmarc.index.indexer.ValueIndexerFactory;
import org.solrmarc.tools.PropertyUtils;

/**
 * Geographic indexing routines.
 */
public class GeoTools
{
    private static final Pattern COORDINATES_PATTERN = Pattern.compile("^([eEwWnNsS])(\\d{3})(\\d{2})(\\d{2})");
    private static final Pattern HDMSHDD_PATTERN = Pattern.compile("^([eEwWnNsS])(\\d+(\\.\\d+)?)");
    private static final Pattern PMDD_PATTERN = Pattern.compile("^([-+]?\\d+(\\.\\d+)?)");
    static String datePrefix = new SimpleDateFormat("yyyyMMdd_HHmmss").format(new Date());
    static String vufindLocal = System.getenv("VUFIND_LOCAL_DIR");
    static String vufindHome = System.getenv("VUFIND_HOME");
    private static Properties vufindConfigs = null;

    // Initialize logging category
    static Logger logger = Logger.getLogger(GeoTools.class.getName());

    /**
     * Constructor
     */
    public GeoTools()
    {
        try {
            vufindConfigs = PropertyUtils.loadProperties(ValueIndexerFactory.instance().getHomeDirs(), "vufind.properties");
        } catch (IllegalArgumentException e) {
            // If the properties load failed, don't worry about it -- we'll use defaults.
        }
    }

    /**
     * Convert MARC coordinates into long_lat format.
     *
     * @param  Record record
     * @return List   geo_coordinates
     */
    public List<String> getAllCoordinates(Record record) {
        List<String> geo_coordinates = new ArrayList<String>();
        List<VariableField> list034 = record.getVariableFields("034");
        if (list034 != null) {
            for (VariableField vf : list034) {
                HashMap<Character, String> coords = getCoordinateValues(vf);

                // Check for null coordinates
                if (validateCoordinateValues(record, coords)) {
                    // Check and convert coordinates to +/- decimal degrees
                    Double west = convertCoordinate(coords.get('d'));
                    Double east = convertCoordinate(coords.get('e'));
                    Double north = convertCoordinate(coords.get('f'));
                    Double south = convertCoordinate(coords.get('g'));
                    if (validateDDCoordinates(record, west, east, north, south)) {
                        // New Format for indexing coordinates in Solr 5.0 - minX, maxX, maxY, minY
                        // Note - storage in Solr follows the WENS order, but display is WSEN order
                        String result = String.format("ENVELOPE(%s,%s,%s,%s)", new Object[] { west, east, north, south });
                        geo_coordinates.add(result);
                    }
                }
            }
        }
        return geo_coordinates;
    }

    /**
     * Get all available coordinates from the record.
     *
     * @param  Record record
     * @return List   geo_coordinates
     */
    public List<String> getDisplayCoordinates(Record record) {
        List<String> geo_coordinates = new ArrayList<String>();
        List<VariableField> list034 = record.getVariableFields("034");
        if (list034 != null) {
            for (VariableField vf : list034) {
                HashMap<Character, String> coords = getCoordinateValues(vf);
                // Check for null coordinates
                if (validateCoordinateValues(record, coords)) {
                    String result = String.format("%s %s %s %s", new Object[] {  coords.get('d'),  coords.get('e'),  coords.get('f'),  coords.get('g') });
                    geo_coordinates.add(result);
                }
            }
        }
        return geo_coordinates;
    }

    /**
     * Log coordinate indexing errors to external log file.
     *
     * @param  Record record
     * @param  HashMap coords
     * @param  String error message
     */
    public static void logErrorMessage(Record record, HashMap<Character, String> coords, String message) {
        // Initialize error logging variables
        String msgError = message;
        String recNum = "Not available";
        ControlField recID = (ControlField) record.getVariableField("001");
        if (recID != null) {
            recNum = recID.getData().trim();
        }
        String coordinates = "Coordinates:  {" + coords.get('d') + "} {" + coords.get('e') + "} {" + coords.get('f') + "} {" + coords.get('g') + "}";

        String logPath = getLogPath();
        String logFilename = datePrefix + "_CoordinateErrors.txt";
        String outPath = logPath + "/" + logFilename;

        // Output Error message
        logger.error("Not indexing INVALID coordinates for Record ID: " + recNum);
        logger.error("... " + msgError);

        if (logPath != null) {
            logger.error("... Check coordinate error log: " + outPath);
            // Log ID and error message and coordinates in error file
            try {
                 PrintWriter out = new PrintWriter(new BufferedWriter(new FileWriter(outPath, true)));
                 out.println(logFilename + "\t" + recNum + "\t" + msgError + "\t" + coordinates);
                 out.close();
            } catch (IOException e) {
                 System.out.println("io exception occurred");
                 e.printStackTrace();
            }
        } else {
        // output error that log file cannot be created
            logger.error("..... No coordinate error log. Check vufind.properties settings...");
        }

    }

    /**
    * Get path for coordinate error log file.
    *
    * @return String logPath
    */
   public static String getLogPath() {
        // Get coordinate error log path setting
        String coordLogPath = PropertyUtils.getProperty(vufindConfigs, "coordinate.log.path");

        //If coordinate.log.path doesn't exist or is not set, try some other places
        if (coordLogPath == null) {
            if (vufindLocal != null) {
                File dir = new File(vufindLocal + "/import");
                if (dir.exists()) {
                  coordLogPath = vufindLocal + "/import";
                }
            } else if (vufindLocal == null && vufindHome != null) {
                File dir = new File(vufindHome + "/import");
                if (dir.exists()) {
                  coordLogPath = vufindHome + "/import";
                }
            } else {
                coordLogPath = null;
            }
        }
        return coordLogPath;
    }

    /**
     * Get all coordinate values from list034
     *
     * @param  VariableField vf
     * @return HashMap full_coords
     */
    protected HashMap<Character, String> getCoordinateValues(VariableField vf) {
        DataField df = (DataField) vf;
        HashMap<Character, String> coords = new HashMap<Character, String>();
        for (char code = 'd'; code <= 'g'; code++) {
            Subfield subfield = df.getSubfield(code);
            if (subfield != null) {
                coords.put(code, subfield.getData());
            }
        }
        // If coordinate set is a point with 2 coordinates, fill the empty values.
        HashMap<Character, String> full_coords = fillEmptyPointCoordinates(coords);
        return full_coords;
    }

    /**
     * If coordinates are a point, fill empty N/S or E/W coordinate
     *
     * @param  HashMap coords
     * @return HashMap full_coords
     */
    protected HashMap<Character, String> fillEmptyPointCoordinates(HashMap<Character, String> coords) {
        HashMap<Character, String> full_coords = coords;
        if (coords.containsKey('d') && !coords.containsKey('e') && coords.containsKey('f') && !coords.containsKey('g')) {
            full_coords.put('e', coords.get('d').toString());
            full_coords.put('g', coords.get('f').toString());
        }
        if (coords.containsKey('e') && !coords.containsKey('d') && coords.containsKey('g') && !coords.containsKey('h')) {
            full_coords.put('d', coords.get('e').toString());
            full_coords.put('f', coords.get('g').toString());
        }
        return full_coords;
    }

    /**
    * Check record coordinates to make sure they do not contain null values.
    *
    * @param  Record record
    * @param  HashMap coords
    * @return boolean
    */
   protected boolean validateCoordinateValues(Record record, HashMap<Character, String> coords) {
        if (coords.containsKey('d') && coords.containsKey('e') && coords.containsKey('f') && coords.containsKey('g')) {
            return true;
        }
        String msgError = "Coordinate values contain null values.";
        logErrorMessage(record, coords, msgError);
        return false;
    }

    /**
     * Check coordinate type HDMS HDD or +/-DD.
     *
     * @param  String coordinateStr
     * @return Double coordinate
     */
    protected Double convertCoordinate(String coordinateStr) {
        Double coordinate = Double.NaN;
        Matcher HDmatcher = HDMSHDD_PATTERN.matcher(coordinateStr);
        Matcher PMDmatcher = PMDD_PATTERN.matcher(coordinateStr);
        if (HDmatcher.matches()) {
            String hemisphere = HDmatcher.group(1).toUpperCase();
            Double degrees = Double.parseDouble(HDmatcher.group(2));
            // Check for HDD or HDMS
            if (hemisphere.equals("N") || hemisphere.equals("S")) {
                if (degrees > 90) {
                    String hdmsCoordinate = hemisphere+"0"+HDmatcher.group(2);
                    coordinate = coordinateToDecimal(hdmsCoordinate);
                } else {
                    coordinate = Double.parseDouble(HDmatcher.group(2));
                    if (hemisphere.equals("S")) {
                        coordinate *= -1;
                    }
                }
            }
            if (hemisphere.equals("E") || hemisphere.equals("W")) {
                if (degrees > 180) {
                    String hdmsCoordinate = HDmatcher.group(0);
                    coordinate = coordinateToDecimal(hdmsCoordinate);
                } else {
                    coordinate = Double.parseDouble(HDmatcher.group(2));
                    if (hemisphere.equals("W")) {
                        coordinate *= -1;
                    }
                }
            }
            return coordinate;
        } else if (PMDmatcher.matches()) {
            coordinate = Double.parseDouble(PMDmatcher.group(1));
            return coordinate;
        } else {
            return Double.NaN;
        }
    }

    /**
     * Convert HDMS coordinates to decimal degrees.
     *
     * @param  String coordinateStr
     * @return Double coordinate
     */
    protected Double coordinateToDecimal(String coordinateStr) {
        Matcher matcher = COORDINATES_PATTERN.matcher(coordinateStr);
        if (matcher.matches()) {
            String hemisphere = matcher.group(1).toUpperCase();
            int degrees = Integer.parseInt(matcher.group(2));
            int minutes = Integer.parseInt(matcher.group(3));
            int seconds = Integer.parseInt(matcher.group(4));
            double coordinate = degrees + (minutes / 60.0) + (seconds / 3600.0);
            if (hemisphere.equals("W") || hemisphere.equals("S")) {
                coordinate *= -1;
            }
            return coordinate;
        }
        return Double.NaN;
    }

    /**
     * Check decimal degree coordinates to make sure they are valid.
     *
     * @param  Record record
     * @param  Double west, east, north, south
     * @return boolean
     */
    protected boolean validateDDCoordinates(Record record, Double west, Double east, Double north, Double south) {
        boolean validValues = true;
        boolean validLines = true;
        boolean validExtent = true;
        boolean validNorthSouth = true;
        boolean validEastWest = true;
        boolean validCoordDist = true;

        if (validateValues(record, west, east, north, south)) {
            validLines = validateLines(record, west, east, north, south);
            validExtent = validateExtent(record, west, east, north, south);
            validNorthSouth = validateNorthSouth(record, north, south);
            validEastWest = validateEastWest(record, east, west);
            validCoordDist = validateCoordinateDistance(record, west, east, north, south);
        } else {
            return false;
        }

        // Validate all coordinate combinations
        if (!validLines || !validExtent || !validNorthSouth || !validEastWest || !validCoordDist) {
            return false;
        } else {
            return true;
        }
    }

    /**
    * Check decimal degree coordinates to make sure they do not form a line at the poles.
    *
    * @param  Record record
    * @param  Double west, east, north, south
    * @return boolean
    */
   public boolean validateLines(Record record, Double west, Double east, Double north, Double south) {
    if ((!west.equals(east) && north.equals(south)) && (north == 90 || south == -90)) {
        String msgError = "Coordinates form a line at the pole";
        HashMap<Character, String> coords = buildCoordinateHashMap(west, east, north, south);
        logErrorMessage(record, coords, msgError);
        return false;
    }
    return true;
   }

    /**
    * Check decimal degree coordinates to make sure they do not contain null values.
    *
    * @param  Record record
    * @param  Double west, east, north, south
    * @return boolean
    */
   public boolean validateValues(Record record, Double west, Double east, Double north, Double south) {
     if (west.isNaN() || east.isNaN() || north.isNaN() || south.isNaN()) {
        String msgError = "Decimal Degree coordinates contain invalid values";
        HashMap<Character, String> coords = buildCoordinateHashMap(west, east, north, south);
        logErrorMessage(record, coords, msgError);
        return false;
    }
    return true;
   }

    /**
    * Check decimal degree coordinates to make sure they are within map extent.
    *
    * @param  Record record
    * @param  Double west, east, north, south
    * @return boolean
    */
   public boolean validateExtent(Record record, Double west, Double east, Double north, Double south) {
    if (west > 180.0 || west < -180.0 || east > 180.0 || east < -180.0
        || north > 90.0 || north < -90.0 || south > 90.0 || south < -90.0
    ) {
        String msgError = "Coordinates exceed map extent.";
        HashMap<Character, String> coords = buildCoordinateHashMap(west, east, north, south);
        logErrorMessage(record, coords, msgError);
        return false;
    }
    return true;
   }

    /**
    * Check decimal degree coordinates to make sure that north is not less than south.
    *
    * @param  Record record
    * @param  Double north, south
    * @return boolean
    */
   public boolean validateNorthSouth(Record record, Double north, Double south) {
    if (north < south) {
        String msgError = "North < South.";
        HashMap<Character, String> coords = buildCoordinateHashMap(Double.NaN, Double.NaN, north, south);
        logErrorMessage(record, coords, msgError);
        return false;
    }
    return true;
   }

    /**
    * Check decimal degree coordinates to make sure that east is not less than west.
    *
    * @param  Record record
    * @param  Double east, west
    * @return boolean
    */
   public boolean validateEastWest(Record record, Double east, Double west) {
    if (east < west) {
       // Convert to 360 degree grid
       if (east <= 0) {
           east = 360 + east;
       }
       if (west < 0) {
           west = 360 + west;
       }
       // Check again
       if (east < west) {
           String msgError = "East < West.";
           HashMap<Character, String> coords = buildCoordinateHashMap(west, east, Double.NaN, Double.NaN);
           logErrorMessage(record, coords, msgError);
           return false;
       }
    }
    return true;
   }

    /**
     * Check decimal degree coordinates to make sure they are not too close.
     * Coordinates too close will cause Solr to run out of memory during indexing.
     *
     * @param  Record record
     * @param  Double west, east, north, south
     * @return boolean
     */
    public boolean validateCoordinateDistance(Record record, Double west, Double east, Double north, Double south) {
        Double distEW = east - west;
        Double distNS = north - south;

        //Check for South Pole coordinate distance
        if ((north == -90 || south == -90) && (distNS > 0 && distNS < 0.167)) {
            String msgError = "Coordinates < 0.167 degrees from South Pole. Coordinate Distance: "+distNS;
            HashMap<Character, String> coords = buildCoordinateHashMap(west, east, north, south);
            logErrorMessage(record, coords, msgError);
            return false;
        }

        //Check for East-West coordinate distance
        if ((west == 0 || east == 0) && (distEW > -2 && distEW <0)) {
            String msgError = "Coordinates within 2 degrees of Prime Meridian. Coordinate Distance: "+distEW;
            HashMap<Character, String> coords = buildCoordinateHashMap(west, east, north, south);
            logErrorMessage(record, coords, msgError);
            return false;
        }
        return true;
    }

    /**
    * Build coordinate hash map for logging.
    *
    * @param  Double west, east, north, south
    * @return HashMap coords
    */
   public HashMap<Character, String> buildCoordinateHashMap (Double west, Double east, Double north, Double south) {
        HashMap<Character, String> coords = new HashMap<Character, String>();
        coords.put('d', Double.toString(west));
        coords.put('e', Double.toString(east));
        coords.put('f', Double.toString(north));
        coords.put('g', Double.toString(south));
        return coords;
   }
}
