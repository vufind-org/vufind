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
 * and incorporates legacy VuFind functionality for GoogleMap display.
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

import java.util.ArrayList;
import java.util.Iterator;
import org.marc4j.marc.Record;
import org.marc4j.marc.VariableField;
import org.marc4j.marc.DataField;
import org.marc4j.marc.Subfield;
import java.util.List;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

/**
 * Geographic indexing routines.
 */
public class GeoTools
{
    private static final Pattern COORDINATES_PATTERN = Pattern.compile("^([eEwWnNsS])(\\d{3})(\\d{2})(\\d{2})");
    private static final Pattern HDMSHDD_PATTERN = Pattern.compile("^([eEwWnNsS])(\\d+(\\.\\d+)?)");
    private static final Pattern PMDD_PATTERN = Pattern.compile("^([+-])(\\d+(\\.\\d+)?)");

    /**
     * Convert MARC coordinates into location_geo format.
     *
     * @param  Record record
     * @return List   geo_coordinates
     */
    public List<String> getAllCoordinates(Record record) {
        List<String> geo_coordinates = new ArrayList<String>();
        List<VariableField> list034 = record.getVariableFields("034");
        if (list034 != null) {
            for (VariableField vf : list034) {
                DataField df = (DataField) vf;
                String d = df.getSubfield('d').getData();
                String e = df.getSubfield('e').getData();
                String f = df.getSubfield('f').getData();
                String g = df.getSubfield('g').getData();
                //System.out.println("raw Coords: "+d+" "+e+" "+f+" "+g);

                // Check to see if there are only 2 coordinates
                // If so, copy them into the corresponding coordinate fields
                if ((d !=null && (e == null || e.trim().equals(""))) && (f != null && (g==null || g.trim().equals("")))) {
                    e = d;
                    g = f;
                }
                if ((e !=null && (d == null || d.trim().equals(""))) && (g != null && (f==null || f.trim().equals("")))) {
                    d = e;
                    f = g;
                }

                // Check and convert coordinates to +/- decimal degrees
                Double west = convertCoordinate(d);
                Double east = convertCoordinate(e);
                Double north = convertCoordinate(f);
                Double south = convertCoordinate(g);

                // New Format for indexing coordinates in Solr 5.0 - minX, maxX, maxY, minY
                // Note - storage in Solr follows the WENS order, but display is WSEN order
                String result = String.format("ENVELOPE(%s,%s,%s,%s)", new Object[] { west, east, north, south });

                if (validateCoordinates(west, east, north, south)) {
                    geo_coordinates.add(result);
                }
            }
        }
        return geo_coordinates;
    }

    /**
     * Get point coordinates for GoogleMap display.
     *
     * @param  Record record
     * @return List   coordinates
     */
    public List<String> getPointCoordinates(Record record) {
        List<String> coordinates = new ArrayList<String>();
        List<VariableField> list034 = record.getVariableFields("034");
        if (list034 != null) {
            for (VariableField vf : list034) {
                DataField df = (DataField) vf;
                String d = df.getSubfield('d').getData();
                String e = df.getSubfield('e').getData();
                String f = df.getSubfield('f').getData();
                String g = df.getSubfield('g').getData();

                // Check to see if there are only 2 coordinates
                if ((d !=null && (e == null || e.trim().equals(""))) && (f != null && (g==null || g.trim().equals("")))) {
                    Double long_val = convertCoordinate(d);
                    Double lat_val = convertCoordinate(f);
                    String longlatCoordinate = Double.toString(long_val) + ',' + Double.toString(lat_val);
                    coordinates.add(longlatCoordinate);
                }
                if ((e !=null && (d == null || d.trim().equals(""))) && (g != null && (f==null || f.trim().equals("")))) {
                    Double long_val = convertCoordinate(e);
                    Double lat_val = convertCoordinate(g);
                    String longlatCoordinate = Double.toString(long_val) + ',' + Double.toString(lat_val);
                    coordinates.add(longlatCoordinate);
                }
                // Check if N=S and E=W
                if (d.equals(e) && f.equals(g)) {
                    Double long_val = convertCoordinate(d);
                    Double lat_val = convertCoordinate(f);
                    String longlatCoordinate = Double.toString(long_val) + ',' + Double.toString(lat_val);
                    coordinates.add(longlatCoordinate);
                }
            }
        }
        return coordinates;
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
                DataField df = (DataField) vf;
                String west = df.getSubfield('d').getData();
                String east = df.getSubfield('e').getData();
                String north = df.getSubfield('f').getData();
                String south = df.getSubfield('g').getData();
                String result = String.format("%s %s %s %s", new Object[] { west, east, north, south });
                if (west != null || east != null || north != null || south != null) {
                    geo_coordinates.add(result);
                }
            }
        }
        return geo_coordinates;
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
            String hemisphere = PMDmatcher.group(1);
            coordinate = Double.parseDouble(PMDmatcher.group(2));
            if (hemisphere.equals("-")) {
                coordinate *= -1;
            }
            return coordinate;
        } else {
            return null;
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
        return null;
    }

    /**
     * Check decimal degree coordinates to make sure they are valid.
     *
     * @param  Double west, east, north, south
     * @return boolean
     */
    protected boolean validateCoordinates(Double west, Double east, Double north, Double south) {
        if (west == null || east == null || north == null || south == null) {
            return false;
        }
        if (west > 180.0 || west < -180.0 || east > 180.0 || east < -180.0) {
            return false;
        }
        if (north > 90.0 || north < -90.0 || south > 90.0 || south < -90.0) {
            return false;
        }
        if (north < south || west > east) {
            return false;
        }
        return true;
    }

    /**
     * THIS FUNCTION HAS BEEN DEPRECATED.
     * Determine the longitude and latitude of the items location.
     *
     * @param  record current MARC record
     * @return string of form "longitude, latitude"
     * @deprecated
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
}