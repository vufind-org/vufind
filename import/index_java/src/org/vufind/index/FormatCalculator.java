package org.vufind.index;
/**
 * Format determination logic.
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

import org.marc4j.marc.Record;
import org.marc4j.marc.ControlField;
import org.marc4j.marc.DataField;
import java.util.ArrayList;
import java.util.Iterator;
import java.util.LinkedHashSet;
import java.util.List;
import java.util.Set;

/**
 * Format determination logic.
 */
public class FormatCalculator
{
    /**
     * Determine whether a record cannot be a book due to findings in 007.
     *
     * @param char formatCode
     * @return boolean
     */
    protected boolean definitelyNotBookBasedOn007(char formatCode) {
        switch (formatCode) {
            // Things that are not books: filmstrips/transparencies (g),
            // pictures (k) and videos/films (m, v):
            case 'g':
            case 'k':
            case 'm':
            case 'v':
                return true;
        }
        return false;
    }

    /**
     * Determine whether a record cannot be a book due to findings in leader
     * and fixed fields (008).
     *
     * @param char recordType
     * @param ControlField marc008
     * @return boolean
     */
    protected boolean definitelyNotBookBasedOnRecordType(char recordType, ControlField marc008) {
        switch (recordType) {
            case 'm':
                // If this is a computer file containing numeric data, it is not a book:
                if (get008Value(marc008, 26) == 'a') {
                    return true;
                }
                break;
            case 'e':   // Cartographic material
            case 'f':   // Manuscript cartographic material
            case 'g':   // Projected medium
            case 'j':   // Musical sound recording
            case 'k':   // 2-D nonprojectable graphic
            case 'r':   // 3-D artifact or naturally occurring object
                // None of these things are books:
                return true;
        }
        return false;
    }

    /**
     * Return the best format string based on codes extracted from 007; return
     * blank string for ambiguous/irrelevant results.
     *
     * @param char formatCode
     * @param String formatString
     * @return String
     */
    protected String getFormatFrom007(char formatCode, String formatString) {
        char formatCode2 = formatString.length() > 1 ? formatString.charAt(1) : ' ';
        switch (formatCode) {
            case 'a':
                return formatCode2 == 'd' ? "Atlas" : "Map";
            case 'c':
                switch(formatCode2) {
                    case 'a':
                        return "TapeCartridge";
                    case 'b':
                        return "ChipCartridge";
                    case 'c':
                        return "DiscCartridge";
                    case 'f':
                        return "TapeCassette";
                    case 'h':
                        return "TapeReel";
                    case 'j':
                        return "FloppyDisk";
                    case 'm':
                    case 'o':
                        return "CDROM";
                    case 'r':
                        // Do not return anything - otherwise anything with an
                        // 856 field would be labeled as "Electronic"
                        return "";
                }
                return "Software";
            case 'd':
                return "Globe";
            case 'f':
                return "Braille";
            case 'g':
                switch(formatCode2) {
                    case 'c': // Filmstrip cartridge
                    case 'd': // Filmslip
                    case 'f': // Filmstrip, type unspecified
                    case 'o': // Filmstrip roll
                        return "Filmstrip";
                    case 't':
                        return "Transparency";
                }
                return "Slide";
            case 'h':
                return "Microfilm";
            case 'k':
                switch(formatCode2) {
                    case 'c':
                        return "Collage";
                    case 'd':
                        return "Drawing";
                    case 'e':
                        return "Painting";
                    case 'f': // Photomechanical print
                        return "Print";
                    case 'g':
                        return "Photonegative";
                    case 'j':
                        return "Print";
                    case 'k':
                        return "Poster";
                    case 'l':
                        return "Drawing";
                    case 'n':
                        return "Chart";
                    case 'o':
                        return "FlashCard";
                    case 'p':
                        return "Postcard";
                    case 's': // Study print
                        return "Print";
                }
                return "Photo";
            case 'm':
                switch(formatCode2) {
                    case 'f':
                        return "VideoCassette";
                    case 'r':
                        return "Filmstrip";
                }
                return "MotionPicture";
            case 'o':
                return "Kit";
            case 'q':
                return "MusicalScore";
            case 'r':
                return "SensorImage";
            case 's':
                switch(formatCode2) {
                    case 'd':
                        return "SoundDisc";
                    case 's':
                        return "SoundCassette";
                }
                return "SoundRecording";
            case 'v':
                switch(formatCode2) {
                    case 'c':
                        return "VideoCartridge";
                    case 'd':
                        char formatCode5 = formatString.length() > 4
                            ? formatString.charAt(4) : ' ';
                        return formatCode5 == 's' ? "BRDisc" : "VideoDisc";
                    case 'f':
                        return "VideoCassette";
                    case 'r':
                        return "VideoReel";
                }
                // assume other video is online:
                return "VideoOnline";
        }
        return "";
    }

    /**
     * Return the best format string based on bib level in leader; return
     * blank string for ambiguous/irrelevant results.
     *
     * @param Record record
     * @param char bibLevel
     * @param ControlField marc008
     * @param boolean couldBeBook
     * @param List formatCodes007
     * @return String
     */
    protected String getFormatFromBibLevel(Record record, char bibLevel, ControlField marc008, boolean couldBeBook, List formatCodes007) {
        switch (bibLevel) {
            // Monograph
            case 'm':
                if (couldBeBook) {
                    // Check 008/23 Form of item
                    switch (get008Value(marc008, 23)) {
                        case 'o': // Online
                        case 'q': // Direct electronic
                        case 's': // Electronic
                            return "eBook";
                        default: break;
                    }
                    // Fall-back on 007 if 008 is missing
                    // Note: relying on 007 here is not ideal as it is repeatable
                    // and can also refer to accompanying material 
                    return (formatCodes007.contains('c')) ? "eBook" : "Book";
                }
                break;
            // Component parts
            case 'a':
                return (hasSerialHost(record)) ? "Article" : "BookComponentPart";
            case 'b':
                return "SerialComponentPart";
            // Integrating resources (e.g. loose-leaf binders, databases)
            case 'i':
                // Look in 008 to determine type of electronic IntegratingResource
                // Check 008/21 Type of continuing resource
                switch (get008Value(marc008, 21)) {
                    case 'h': // Blog
                    case 'w': // Updating Web site
                        return "Website";
                    default: break;
                }
                // Check 008/22 Form of original item
                switch (get008Value(marc008, 22)) {
                    case 'o': // Online
                    case 'q': // Direct electronic
                    case 's': // Electronic
                        return "OnlineIntegratingResource";
                    default: break;
                }
                // Fall-back on 007 if 008 is missing
                // Note: relying on 007 here is not ideal as it is repeatable
                // and can also refer to accompanying material 
                return (formatCodes007.contains('c')) ?  "OnlineIntegratingResource" : "PhysicalIntegratingResource";
            // Serial
            case 's':
                // Look in 008 to determine what type of Continuing Resource
                switch (get008Value(marc008, 21)) {
                    case 'n':
                        return "Newspaper";
                    case 'p':
                        return "Journal";
                    default: break;
                }
                // Default to serial even if 008 is missing
                if (!isConferenceProceeding(record)) {
                    return "Serial";
                }
                break;
        }
        return "";
    }

    /**
     * Return the best format string based on record type in leader; return
     * blank string for ambiguous/irrelevant results.
     *
     * @param Record record
     * @param char recordType
     * @param ControlField marc008
     * @param List formatCodes007
     * @return String
     */
    protected String getFormatFromRecordType(Record record, char recordType, ControlField marc008, List formatCodes007) {
        switch (recordType) {
            case 'c':
            case 'd':
                return "MusicalScore";
            case 'e':
            case 'f':
                // Check 008/25 Type of cartographic material
                switch (get008Value(marc008, 25)) {
                    case 'd':
                        return "Globe";
                    case 'e':
                        return "Atlas";
                    default: break;
                }
                return "Map";
            case 'g':
                // Check 008/33 Type of visual material
                switch (get008Value(marc008, 33)) {
                    case 'f':
                        return "Filmstrip";
                    case 't':
                        return "Transparency";
                    case 'm':
                        return "MotionPicture";
                    case 'v': // Videorecording
                        return "Video";
                    default: break;
                }
                // Insufficient info in LDR and 008 to distinguish still from moving images
                // Leave it to corresponding 007 if it exists, else fall back to "ProjectMedium"  
                return (formatCodes007.contains('g')) ? "" : "ProjectedMedium";
            case 'i':
                return "SoundRecording";
            case 'j':
                return "MusicRecording";
            case 'k':
                // Check 008/33 Type of visual material
                switch (get008Value(marc008, 33)) {
                    case 'l': // Technical drawing
                        return "Drawing";
                    case 'n':
                        return "Chart";
                    case 'o':
                        return "FlashCard";
                    default: break;
                }
                // Insufficient info in LDR and 008 to distinguish image types
                // Leave it to corresponding 007 if it exists, else fall back to "Image"  
                return (formatCodes007.contains('k')) ? "" : "Image";
            case 'o':
            case 'p':
                return "Kit";
            case 'r':
                return "PhysicalObject";
            case 't':
                if (!isThesis(record)) {
                    return "Manuscript";
                }
                break;
        }
        return "";
    }

    /**
     * Extract value at a specific position in 008 field
     *
     * @param ControlField marc008
     * @param int position
     * @return char
     */
    protected char get008Value(ControlField marc008, int position) {
        // Check the 008 at desired position:
        try {
            return marc008.getData().toLowerCase().charAt(position);
        } catch (java.lang.StringIndexOutOfBoundsException e) {
            // ignore errors (leave the string blank if out of bounds)
            return ' ';
        } catch (java.lang.NullPointerException e) {
            // some malformed 008s result in a NullPointerException
            // ignore errors (leave the string blank)
            return ' ';
        }
    }

    /**
     * Determine whether a record is a conference proceeding.
     *
     * @param Record record
     * @return boolean
     */
    protected boolean isConferenceProceeding(Record record) {
        // Is there a main entry meeting name?
        DataField conference1 = (DataField) record.getVariableField("111");
        // The 711 could possibly have more then one entry, although probably unlikely
        DataField conference2 = (DataField) null;
        List conferenceFields = record.getVariableFields("711");
        Iterator conferenceFieldsIter = conferenceFields.iterator();
        if (conferenceFields != null) {
            if (conferenceFieldsIter.hasNext()) {
                conference2 = (DataField) conferenceFieldsIter.next();
            }
        }
        return (conference1 != null || conference2 != null);
    }

    /**
     * Determine whether a record is electronic in format.
     *
     * @param Record record
     * @return boolean
     */
    protected boolean isElectronic(Record record) {
        /* Example from Villanova of how to use holdings locations to detect online status;
         * You can override this method in a subclass if you wish to use this approach.
        List holdingsFields = record.getVariableFields("852");
        Iterator holdingsIterator = holdingsFields.iterator();
        while (holdingsIterator.hasNext()) {
            DataField holdingsField = (DataField) holdingsIterator.next();
            if (holdingsField.getSubfield('b') != null) {
                String holdingsLocation = holdingsField.getSubfield('b').getData().toLowerCase();
                if (holdingsLocation.equals("www") || holdingsLocation.equals("e-ref")) {
                    return true;
                }
            }
        }
         */
        DataField title = (DataField) record.getVariableField("245");
        if (title != null) {
            if (title.getSubfield('h') != null) {
                if (title.getSubfield('h').getData().toLowerCase().contains("[electronic resource]")) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Determine whether a record is a government document.
     *
     * @param Record record
     * @return boolean
     */
    protected boolean isGovernmentDocument(Record record) {
        // Is there a SuDoc number? If so, it's a government document.
        return record.getVariableField("086") != null;
    }


    /**
     * Determine whether a record is a thesis.
     *
     * @param Record record
     * @return boolean
     */
    protected boolean isThesis(Record record) {
        // Is there a dissertation note? If so, it's a thesis.
        return record.getVariableField("502") != null;
    }

    /**
     * Determine whether a record has a host item that is a serial.
     *
     * @param Record record
     * @return boolean
     */
    protected boolean hasSerialHost(Record record) {
        // The 773 could possibly have more then one entry, although probably unlikely.
        // If any contain a subfield 'g' return true to indicate the host is a serial
        // see https://www.oclc.org/bibformats/en/specialcataloging.html#relatedpartsandpublications
        List hostFields = record.getVariableFields("773");
        Iterator hostFieldsIter = hostFields.iterator();
        if (hostFields != null) {
            while (hostFieldsIter.hasNext()) {
                DataField hostField = (DataField) hostFieldsIter.next();
                if (hostField.getSubfield('g') != null) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Determine Record Format(s)
     *
     * @param  Record record
     * @return Set format(s) of record
     */
    protected List<String> getFormatsAsList(Record record) {
        List<String> result = new ArrayList<String>();
        String leader = record.getLeader().toString();
        ControlField marc008 = (ControlField) record.getVariableField("008");
        String formatString;
        char formatCode = ' ';

        // This record could be a book... until we prove otherwise!
        boolean couldBeBook = true;

        // Some format-specific special cases:
        if (isGovernmentDocument(record)) {
            result.add("GovernmentDocument");
        }
        if (isThesis(record)) {
            result.add("Thesis");
        }
        if (isElectronic(record)) {
            result.add("Electronic");
        }
        if (isConferenceProceeding(record)) {
            result.add("ConferenceProceeding");
        }

        // check the 007 - this is a repeating field
        List fields = record.getVariableFields("007");
        List formatCodes007;
        Iterator fieldsIter = fields.iterator();
        if (fields != null) {
            ControlField formatField;
            while(fieldsIter.hasNext()) {
                formatField = (ControlField) fieldsIter.next();
                formatString = formatField.getData().toLowerCase();
                formatCode = formatString.length() > 0 ? formatString.charAt(0) : ' ';
                formatCodes007.add(formatCode);
                if (definitelyNotBookBasedOn007(formatCode)) {
                    couldBeBook = false;
                }
                if (formatCode == 'v') {
                    // All video content should get flagged as video; we will also
                    // add a more detailed value in getFormatFrom007 to distinguish
                    // different types of video.
                    result.add("Video");
                }
                String formatFrom007 = getFormatFrom007(formatCode, formatString);
                if (formatFrom007.length() > 0) {
                    result.add(formatFrom007);
                }
            }
        }

        // check the Leader at position 6
        char recordType = Character.toLowerCase(leader.charAt(6));
        if (definitelyNotBookBasedOnRecordType(recordType, marc008)) {
            couldBeBook = false;
        }
        String formatFromRecordType = getFormatFromRecordType(record, recordType, marc008, formatCodes007);
        if (formatFromRecordType.length() > 0) {
            result.add(formatFromRecordType);
        }

        // check the Leader at position 7
        char bibLevel = Character.toLowerCase(leader.charAt(7));
        String formatFromBibLevel = getFormatFromBibLevel(
            record, bibLevel, marc008, couldBeBook, formatCodes007
        );
        if (formatFromBibLevel.length() > 0) {
            result.add(formatFromBibLevel);
        }

        // Nothing worked -- time to set up a value of last resort!
        if (result.isEmpty()) {
            // If the leader bit indicates a "Collection," treat it as a kit for now;
            // this is a rare case but helps cut down on the number of unknowns.
            result.add(bibLevel == 'c' ? "Kit" : "Unknown");
        }

        return result;
    }

    /**
     * Determine Record Format(s)
     *
     * @param  record MARC record
     * @return set of record formats
     */
    public Set<String> getFormats(final Record record) {
        // Deduplicate list by converting to set:
        return new LinkedHashSet<String>(getFormatsAsList(record));
   }

   /**
    * Determine Record Format
    *
    * @param  record MARC record
    * @return set of record formats
    */
   public Set<String> getFormat(final Record record) {
       // Create set containing first element from list:
       Set<String> result = new LinkedHashSet<String>();
       List<String> list = getFormatsAsList(record);
       result.add(list.get(0));
       return result;
  }
}
