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
import org.marc4j.marc.Subfield;
import org.marc4j.marc.VariableField;
import java.util.ArrayList;
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
            // Computer file
            case 'm':
                // Check the type of computer file:
                // If it is 'Document', 'Interactive multimedia', 'Combination',
                // 'Unknown', 'Other', it could be a book; otherwise, it is not a book:
                char fileType = get008Value(marc008, 26);
                if (fileType == 'd' || fileType == 'i' || fileType == 'm' || fileType == 'u' || fileType == 'z') {
                    return false;
                }
                return true;
            case 'e':   // Cartographic material
            case 'f':   // Manuscript cartographic material
            case 'g':   // Projected medium
            case 'i':   // Nonmusical sound recording
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
                return "ElectronicResource";
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
     * @param char recordType
     * @param char bibLevel
     * @param ControlField marc008
     * @param boolean couldBeBook
     * @param List formatCodes007
     * @return String
     */
    protected String getFormatFromBibLevel(Record record, char recordType, char bibLevel, ControlField marc008, boolean couldBeBook, List<Character> formatCodes007) {
        switch (bibLevel) {
            // Component parts
            case 'a':
                return (hasSerialHost(record)) ? "Article" : "BookComponentPart";
            case 'b':
                return "SerialComponentPart";
            // Collection and sub-unit will be mapped to 'Kit' below if no other
            // format can be found. For now return an empty string here.
            case 'c': // Collection
            case 'd': // Sub-unit
                return "";
            // Integrating resources (e.g. loose-leaf binders, databases)
            case 'i':
                // Look in 008 to determine type of electronic IntegratingResource
                // Check 008/21 Type of continuing resource
                // Make sure we have the applicable LDR/06: Language Material
                if (recordType == 'a') {
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
                }
                return "PhysicalIntegratingResource";
            // Monograph
            case 'm':
                if (couldBeBook) {
                    // Check 008/23 Form of item
                    // Make sure we have the applicable LDR/06: Language Material; Manuscript Language Material;
                    if (recordType == 'a' || recordType == 't') {
                        switch (get008Value(marc008, 23)) {
                            case 'o': // Online
                            case 'q': // Direct electronic
                            case 's': // Electronic
                                return "eBook";
                            default: break;
                        }
                    } else if (recordType == 'm') {
                        // If we made it here and it is a Computer file, set to eBook
                        // Note: specific types of Computer file, e.g. Video Game, have
                        // already been excluded in definitelyNotBookBasedOnRecordType()
                        return "eBook";
                    }
                    // If we made it here, it should be Book
                    return "Book";
                }
                break;
            // Serial
            case 's':
                // Look in 008 to determine what type of Continuing Resource
                // Make sure we have the applicable LDR/06: Language Material
                if (recordType == 'a') {
                    switch (get008Value(marc008, 21)) {
                        case 'n':
                            return "Newspaper";
                        case 'p':
                            return "Journal";
                        default: break;
                    }
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
            // Language material is mapped to 'Text' below if no other
            // format can be found. For now return an empty string here.
            case 'a':
                return "";
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
                // Check 008/34 Technique
                // If set, this is a video rather than a slide
                switch (get008Value(marc008, 34)) {
                    case 'a': // Animation
                    case 'c': // Animation and live action
                    case 'l': // Live action
                    case 'u': // Unknown
                    case 'z': // Other
                        return "Video";
                    default: break;
                }
                // Insufficient info in LDR and 008 to distinguish still from moving images
                // If there is a 007 for either "Projected Graphic", "Motion Picture", or "Videorecording"
                // that should contain more information, so return nothing here.
                // If no such 007 exists, fall back to "ProjectedMedium"
                if (formatCodes007.contains('g') || formatCodes007.contains('m') || formatCodes007.contains('v')) {
                    return "";
                }
                return "ProjectedMedium";
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
                // If there is a 007 for Nonprojected Graphic, it should have more info, so return nothing here.
                // If there is no 007 for Nonprojected Graphic, fall back to "Image"
                return (formatCodes007.contains('k')) ? "" : "Image";
            // Computer file
            case 'm':
                // All computer files return a format of Electronic in isElectronic()
                // Only set more specific formats here
                // Check 008/26 Type of computer file
                switch (get008Value(marc008, 26)) {
                    case 'a': // Numeric data
                        return "DataSet";
                    case 'b': // Computer program
                        return "Software";
                    case 'c': // Representational
                        return "Image";
                    case 'd': // Document
                        // Document is too vague and often confusing when combined
                        // with formats derived from elsewhere in the record
                        break;
                    case 'e': //Bibliographic data
                        return "DataSet";
                    case 'f': // Font
                        return "Font";
                    case 'g': // Game
                        return "VideoGame";
                    case 'h': // Sound
                        return "SoundRecording";
                    case 'i': // Interactive multimedia
                        return "InteractiveMultimedia";
                    default: break;
                }
                // If we got here, don't return anything
                break;
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
        // The 711 could possibly have more than one entry, although probably unlikely
        DataField conference2 = (DataField) null;
        for (VariableField variableField : record.getVariableFields("711")) {
            conference2 = (DataField) variableField;
        }
        return (conference1 != null || conference2 != null);
    }

    /**
     * Determine whether a record is electronic in format.
     *
     * @param Record record
     * @param char recordType
     * @return boolean
     */
    protected boolean isElectronic(Record record, char recordType) {
        /* Example from Villanova of how to use holdings locations to detect online status;
         * You can override this method in a subclass if you wish to use this approach.
        for (VariableField variableField : record.getVariableFields("852")) {
            DataField holdingsField = (DataField) variableField;
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
        // Is this a computer file of some sort?
        // If so it is electronic
        if (recordType == 'm') {
            return true;
        }

        if (isOnlineAccordingTo338(record)) {
            return true;
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
        for (VariableField variableField : record.getVariableFields("773")) {
            DataField hostField = (DataField) variableField;
            if (hostField.getSubfield('g') != null) {
                return true;
            }
        }
        return false;
    }

    /**
     * Return the contents of the specified subfield, or a default value if missing/empty
     *
     * @param DataField field
     * @param char subfieldCode
     * @param String defaultValue
     * @return String
     */
    protected String getSubfieldOrDefault(DataField field, char subfieldCode, String defaultValue) {
        Subfield subfield = field.getSubfield(subfieldCode);
        String data = subfield != null ? subfield.getData() : null;
        return (data == null || data.isEmpty()) ? defaultValue : data;
    }

    /**
     * Return true if this is an online record according to the contents of 338.
     *
     * @param Record record
     * @return boolean
     */
    protected boolean isOnlineAccordingTo338(Record record) {
        // Does the RDA carrier indicate that this is online?
        for (VariableField variableField : record.getVariableFields("338")) {
            DataField carrierField = (DataField) variableField;
            String desc = getSubfieldOrDefault(carrierField, 'a', "");
            String code = getSubfieldOrDefault(carrierField, 'b', "");
            String source = getSubfieldOrDefault(carrierField, '2', "");
            if ((desc.equals("online resource") || code.equals("cr")) && source.equals("rdacarrier")) {
                return true;
            }
        }
        return false;
    }

    /**
     * Determines record formats using 33x fields.
     *
     * This is not currently comprehensive; it is designed to supplement but not
     * replace existing support for 007 analysis and can be expanded in future.
     *
     * @param  Record record
     * @return Set format(s) of record
     */
    protected List<String> getFormatsFrom33xFields(Record record) {
        boolean isOnline = isOnlineAccordingTo338(record);
        List<String> formats = new ArrayList<String>();
        for (VariableField variableField : record.getVariableFields("336")) {
            DataField typeField = (DataField) variableField;
            String desc = getSubfieldOrDefault(typeField, 'a', "");
            String code = getSubfieldOrDefault(typeField, 'b', "");
            String source = getSubfieldOrDefault(typeField, '2', "");
            if ((desc.equals("two-dimensional moving image") || code.equals("tdi")) && source.equals("rdacontent")) {
                formats.add("Video");
                if (isOnline) {
                    formats.add("VideoOnline");
                }
            }
        }
        return formats;
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
        char recordType = Character.toLowerCase(leader.charAt(6));
        char bibLevel = Character.toLowerCase(leader.charAt(7));

        // This record could be a book... until we prove otherwise!
        boolean couldBeBook = true;

        // Some format-specific special cases:
        if (isGovernmentDocument(record)) {
            result.add("GovernmentDocument");
        }
        if (isThesis(record)) {
            result.add("Thesis");
        }
        if (isElectronic(record, recordType)) {
            result.add("Electronic");
        }
        if (isConferenceProceeding(record)) {
            result.add("ConferenceProceeding");
        }

        // check the 33x fields; these may give us clear information in newer records;
        // in current partial implementation of getFormatsFrom33xFields(), if we find
        // something here, it indicates non-book content.
        List formatsFrom33x = getFormatsFrom33xFields(record);
        if (formatsFrom33x.size() > 0) {
            couldBeBook = false;
            result.addAll(formatsFrom33x);
        }

        // check the 007 - this is a repeating field
        List<Character> formatCodes007 = new ArrayList<Character>();
        for (VariableField variableField : record.getVariableFields("007")) {
            ControlField formatField = (ControlField) variableField;
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

        // check the Leader at position 6
        if (definitelyNotBookBasedOnRecordType(recordType, marc008)) {
            couldBeBook = false;
        }
        // If we already have 33x results, skip the record type:
        String formatFromRecordType = formatsFrom33x.size() == 0
            ? getFormatFromRecordType(record, recordType, marc008, formatCodes007)
            : "";
        if (formatFromRecordType.length() > 0) {
            result.add(formatFromRecordType);
        }

        // check the Leader at position 7
        String formatFromBibLevel = getFormatFromBibLevel(
            record, recordType, bibLevel, marc008, couldBeBook, formatCodes007
        );
        if (formatFromBibLevel.length() > 0) {
            result.add(formatFromBibLevel);
        }

        // Nothing worked -- time to set up a value of last resort!
        if (result.isEmpty()) {
            // If LDR/07 indicates a "Collection" or "Sub-Unit," treat it as a kit for now;
            // this is a rare case but helps cut down on the number of unknowns.
            if (bibLevel == 'c' || bibLevel == 'd') {
                result.add("Kit");
            } else if (recordType == 'a') {
                // If LDR/06 indicates "Language material," map to "Text";
                // this helps cut down on the number of unknowns.
                result.add("Text");
            } else {
                result.add("Unknown");
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
