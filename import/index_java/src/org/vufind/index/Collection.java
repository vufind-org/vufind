/**
 * Hierarchical collections indexing routines.
 *
 * This code is designed to iterate through the 773 subfield to identify whether
 * a record is a collection level record or part of a collection.
 *
 * code adapted by Owen-Fitz - National Library of Ireland
 *
 * Copyright (C) Villanova University 2017.
 */
package org.vufind.index;

import org.solrmarc.index.SolrIndexer;
import org.marc4j.marc.*;
import org.marc4j.marc.Record;
import org.marc4j.marc.ControlField;
import org.marc4j.marc.DataField;
import org.solrmarc.tools.Utils;
import org.solrmarc.index.SolrIndexer;
import java.io.*;
import org.ini4j.Ini;
import org.apache.log4j.Logger;
import javax.xml.parsers.DocumentBuilder;
import javax.xml.parsers.DocumentBuilderFactory;
import org.w3c.dom.NamedNodeMap;
import org.w3c.dom.Document;
import org.w3c.dom.Element;
import org.w3c.dom.Node;
import org.w3c.dom.NodeList;
import java.util.regex.Pattern;
import java.util.regex.Matcher;
import java.text.Normalizer;
import org.vufind.index.ConfigManager;
import org.vufind.index.PunctuationTools;

import java.util.Set;
import java.util.List;
import java.util.Map;
import java.util.HashMap;
import java.util.LinkedHashSet;
import java.util.Iterator;
import java.util.Arrays;
import java.util.ArrayList;
import java.util.Calendar;
import java.net.URL;
import java.text.DecimalFormat;

public class Collection
{

    // Initialize logging category
    static Logger logger = Logger.getLogger(Collection.class.getName());
    static String ils_prefix;
    static Map<String, String> catalog_driver = ConfigManager.instance().getConfigSection("config.ini", "Catalog");

     /**
       * Determine the type of hierarcy
       *
       * @param  Record          record
       * @return String   Solr/XMLFile or null
       */

       public String getHierarchyType(Record record) {

          ils_prefix = catalog_driver.get("ils_prefix") != null ? catalog_driver.get("ils_prefix") : "";

          // Check 999 subfield a for "Ancestry":
          List fields = record.getVariableFields("999");
          if (fields != null) {
              Iterator fieldsIter = fields.iterator();

              while(fieldsIter.hasNext()) {
                  DataField field;
                  field = (DataField) fieldsIter.next();

                  List subfieldsa = field.getSubfields('a');
                  Iterator subfieldsaIter = subfieldsa.iterator();

                  if (subfieldsa != null) {
                      String info;
                      while (subfieldsaIter.hasNext()) {
                          Subfield temp  = (Subfield) subfieldsaIter.next();
                          info = temp.getData().toLowerCase();
                          if (info.contains("ancestry")) {
                              return "Default";
                          }
                      }
                  }
              }
          }

          //got this far means not a default tree, still could be a flat tree though
          //check if any of the 773 i fields contain the word "collection"
          fields = record.getVariableFields("773");
          Iterator fieldsIter = fields.iterator();
          if (fields != null) {
              DataField physical;
              while(fieldsIter.hasNext()) {
                  physical = (DataField) fieldsIter.next();

                  List subfieldsi = physical.getSubfields('i');

                  Iterator subfieldsiIter = subfieldsi.iterator();

                  if (subfieldsi != null) {
                      String info;
                      String title;
                      while (subfieldsiIter.hasNext()) {
                          Subfield temp  = (Subfield) subfieldsiIter.next();
                          info = temp.getData().toLowerCase();
                          if (info.contains("collection")) {
                              return "Flat";
                          }
                      }
                  }
              }
          }

          return null;
       }

        /**
         * Determine the ID of parent node
         *
         * @param  Record          record
         * @return Set   Parent IDs or null
         */
        public Set getParentID(Record record) {
            String bibId = record.getControlNumber();
            // Initialize return value:
            Set result = new LinkedHashSet();

            // Loop through relevant fields and normalize everything:
            Set parents = SolrIndexer.instance().getFieldList(record, "004");
            Iterator parentIter = parents.iterator();
            if (parents != null) {
                String current;
                while(parentIter.hasNext()) {
                    String temp = (String) parentIter.next();
                    current = temp.substring(0,13).trim();
                    // Don't let records add themselves as their own parents
                    // This doesn't avoid more complex cases where bad data results in
                    // infinite loops
                    if (current != null && current != "false" && current.length() > 0 && !current.equals(bibId)) {
                        result.add(current);
                    }
                }
                return result;
            }

            return null;
        }

        /**
         * Determine the title of parent node
         *
         * @param  Record          record
         * @return Set   Parent Names or null
         */
        public Set getParentTitle(Record record) {
            // Initialize return value:
            Set result = new LinkedHashSet();

            // Loop through relevant fields and normalize everything:
            Set parents = SolrIndexer.instance().getFieldList(record, "004");
            Iterator parentIter = parents.iterator();
            if (parents != null) {
                String current;
                while(parentIter.hasNext()) {
                    String temp = (String) parentIter.next();
                    current = temp.substring(0,13).trim();
                    if (current != null && current != "false" && current.length() > 0) {
                        result.add(current);
                    }
                }
                return result;
            }

            return null;
        }

      /**
       * Determine the sort order under it's parent node
       *
       * @param  Record          record
       * @return Set   Sequences for each parent or null
       */
      public Set getSequence(Record record) {
          DecimalFormat df = new DecimalFormat( "00000" );
          Integer numSeq;
          String formattedSeq;
          // Initialize return value:
          Set result = new LinkedHashSet();

          // Loop through relevant fields and normalize everything:
          Set parents = SolrIndexer.instance().getFieldList(record, "004");
          Iterator parentIter = parents.iterator();
          if (parents != null) {
              String current;
              while(parentIter.hasNext()) {
                  String seq;
                  String temp = (String) parentIter.next();
                  current = temp.trim();
                  if (current.length() > 14){
                      seq = current.substring(14).trim();
                      if (seq != null && seq != "false" && seq.length() > 0 && seq.matches("\\d+")) {
                          formattedSeq = df.format(Integer.parseInt(seq));
                          result.add(formattedSeq);
                      }
                  }
              }
              return result;
          }

          return null;
     }

    /**
     * Determine if a record is a collection level record,
     * and if so set the collection Name.
     *
     * @param  Record          record
     * @return String   Collection Name or null
     */
    public String isHierarchyTitle(Record record) {

        // Check 773 subfield i for "Is Collection":
        List fields = record.getVariableFields("773");
        Iterator fieldsIter = fields.iterator();
        if (fields != null) {
            DataField physical;
            while(fieldsIter.hasNext()) {
                physical = (DataField) fieldsIter.next();

                List subfieldsi = physical.getSubfields('i');
                List subfieldst = physical.getSubfields('t');

                Iterator subfieldsiIter = subfieldsi.iterator();
                Iterator subfieldstIter = subfieldst.iterator();

                if (subfieldsi != null && subfieldst != null) {
                    String info;
                    String title;
                    while (subfieldsiIter.hasNext() && subfieldstIter.hasNext()) {
                        Subfield temp  = (Subfield) subfieldsiIter.next();
                        info = temp.getData().toLowerCase();
                        temp  = (Subfield) subfieldstIter.next();
                        title = temp.getData();
                        if (info.contains("is collection")) {
                            return title;
                        }
                    }
                }
            }
    	}
        return null;
    }

    /**
     * Determine if a record is a collection level record,
     * and if so set the collection id.
     *
     * @param  Record          record
     * @return String   Collection ID or null
     */
    public String isHierarchyID(Record record) {

        // Check 773 subfield i for "Is Collection:"
        List fields = record.getVariableFields("773");
        Iterator fieldsIter = fields.iterator();
        if (fields != null) {
            DataField physical;
            while(fieldsIter.hasNext()) {
                physical = (DataField) fieldsIter.next();

                List subfieldsi = physical.getSubfields('i');
                List subfieldsw = physical.getSubfields('w');

                Iterator subfieldsiIter = subfieldsi.iterator();
                Iterator subfieldswIter = subfieldsw.iterator();

                if (subfieldsi != null && subfieldsw != null) {
                    String info;
                    String title;
                    String id;
                    while (subfieldsiIter.hasNext() && subfieldswIter.hasNext()) {
                        Subfield temp  = (Subfield) subfieldsiIter.next();
                        info = temp.getData().toLowerCase();
    			        if (info.contains("is collection")) {
        				    if (subfieldswIter.hasNext()) {
        				        temp  = (Subfield) subfieldswIter.next();
                                id = getValidIlsNumber(temp.getData());
                                return id;
        				    }
                        }
                    }
                }
            }
    	}
        return null;
    }

    /**
     * Modify the 773w field into a valid ILS number
     *
     * @param  String          record
     * @return String          ILS number  or null
     */
    public String getValidIlsNumber(String record) {

        // Remove ILS number and fullstops from the string
        String tmpID = record.replaceAll(ils_prefix,"").replaceAll("\\.", " ").replaceAll("\\(", "").replaceAll("\\)", "").toLowerCase();

        // These are the variations of ID's that we will allow for (IeDuNL)
        String[] allowableIds = {"iedunl", "ledunl", "iedubnl", "ledubnl", "iedunli", "ledunli", "edunl"};

        tmpID = removeIeDunlVariations(tmpID, allowableIds);
        tmpID = tmpID.replaceAll("^\\s+|\\s+$", "");

        String[] parts = tmpID.split(" ");
        tmpID = parts[0];

        if (isAValidNumber(tmpID)){
            tmpID = "000000000".substring(tmpID.length()) + tmpID;
            tmpID = ils_prefix + tmpID;
            return tmpID;
        } else {
            return null;
        }
    }

    /**
     * Remove any variations of iedunl from the string
     *
     * @param  String          record
     * @param  String[]        variations
     * @return Boolean
     */
    public String removeIeDunlVariations(String record, String[] variations){

        int iedunl_variation = 0;
        for (int i=0; i < variations.length; i++){
            record = record.replaceAll(variations[i], "");
            iedunl_variation++;
        }

        return record;
    }


    /**
     * Check if the 773w is a valid number that is between 1-9 in length
     *
     * @param  String          record
     * @return Boolean
     */
    public boolean isAValidNumber(String record){
        Pattern p = Pattern.compile("^[0-9]{1,9}$");
        Matcher m = p.matcher(record);
        boolean matches = m.matches();

        if (matches){
            return true;
        } else {
            return false;
        }
    }


    /**
     * Test if there is a string in the allowable array of ID's
     *
     * @param  String          inputString
     * @param  String[]        allowableIds
     * @return Boolean         true or false
     */
    public static boolean stringContainsAllowableId(String inputString, String[] allowableIds){
        for(int i=0; i < allowableIds.length; i++) {
            if(inputString.contains(allowableIds[i])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Determine if a record is in a collection,
     * and if so set the collection Name.
     *
     * @param  Record          record
     * @return Set  Collection ID or null
     */
    public Set getHierarchyTopTitle(Record record) {
    	Set result = new LinkedHashSet();

    	// Check 773 subfield i for "In Collection":
    	List fields = record.getVariableFields("773");
    	Iterator fieldsIter = fields.iterator();
    	if (fields != null) {
    		DataField physical;
    		while(fieldsIter.hasNext()) {
    			physical = (DataField) fieldsIter.next();

    			List subfieldsi = physical.getSubfields('i');
    			List subfieldst = physical.getSubfields('t');

    			Iterator subfieldsiIter = subfieldsi.iterator();
    			Iterator subfieldstIter = subfieldst.iterator();

    			if (subfieldsi != null && subfieldst != null) {
    				String info;
    				String title;
                    while (subfieldsiIter.hasNext() && subfieldstIter.hasNext()) {
                        Subfield temp  = (Subfield) subfieldsiIter.next();
                        info = temp.getData().toLowerCase();
                        temp  = (Subfield) subfieldstIter.next();
                        title = temp.getData();
                        if (info.contains("in collection")) {
                            result.add(title);
                        }
                        //all collections are inherently part of themselves
                        if (info.contains("is collection")) {
                            result.add(title);
                        }
                    }
                }
            }
    	}
    	if( result.size()>0){
    		return result;
    	}

    	//make sure it's not actually a hierarchy record that just doesn't have a 773 but has ancestry in 999 a
    	//in this case we use the ID as the title
    	// Check 999 subfield a for "Ancestry":
        fields = record.getVariableFields("999");
        fieldsIter = fields.iterator();
        if (fields != null) {
            DataField field;
            while(fieldsIter.hasNext()) {
                field = (DataField) fieldsIter.next();
                List subfieldsa = field.getSubfields('a');
                List subfieldsk = field.getSubfields('k');

                Iterator subfieldsaIter = subfieldsa.iterator();
                Iterator subfieldskIter = subfieldsk.iterator();

                if (subfieldsa != null && subfieldsk != null) {
                    String info;
                    String value;
                    String tmpID;
                    String id;
                    while (subfieldsaIter.hasNext() && subfieldskIter.hasNext()) {
                        Subfield temp  = (Subfield) subfieldsaIter.next();
                        info = temp.getData().toLowerCase();
                        temp  = (Subfield) subfieldskIter.next();
                        value = temp.getData().replace("A","").trim();
                        tmpID = "000000000".substring(value.length()) + value;
                        if (info.contains("ancestry")) {
                        	id =  ils_prefix + tmpID;
                        	result.add(id);
                        }
                    }
                }
            }
        }
        return result;
    }

    /**
     * Set the collection id, just perform some string formatting.
     *
     * @param  Record          record
     * @return Set   Collection ID or null
     */
    public Set getHierarchyTopID(Record record) {

        Set result = new LinkedHashSet();

        // Check 773 subfield i for "in Collection":
        List fields = record.getVariableFields("773");
        Iterator fieldsIter = fields.iterator();
        if (fields != null) {
            DataField physical;
            while(fieldsIter.hasNext()) {
                physical = (DataField) fieldsIter.next();

                List subfieldsi = physical.getSubfields('i');
                List subfieldsw = physical.getSubfields('w');

                Iterator subfieldsiIter = subfieldsi.iterator();
                Iterator subfieldswIter = subfieldsw.iterator();

                if (subfieldsw != null) {
                    String id;
                    String info;
        		    while (subfieldsiIter.hasNext()) {
        		        Subfield temp  = (Subfield) subfieldsiIter.next();
                        info = temp.getData().toLowerCase();
        			    if (info.contains("in collection")) {
            				if (subfieldswIter.hasNext()) {
            				    temp  = (Subfield) subfieldswIter.next();
                                id =  getValidIlsNumber(temp.getData());
                            	result.add(id);
            				}
                	    }
            			//all collections are inherently part of themselves
            			if (info.contains("is collection")) {
            				if (subfieldswIter.hasNext()) {
            				    temp  = (Subfield) subfieldswIter.next();
                                id = getValidIlsNumber(temp.getData());
                            	result.add(id);
            				}
                    	}
                    }
                }
            }
    	}
    	if(result.size()>0){
    		return result;
    	}
    	//make sure it's not actually a hierarchy record that just doesn't have a 773 but has ancestry in 999 a
    	// Check 999 subfield a for "Ancestry":
        fields = record.getVariableFields("999");
        fieldsIter = fields.iterator();
        if (fields != null) {
            DataField field;
            while(fieldsIter.hasNext()) {
                field = (DataField) fieldsIter.next();
                List subfieldsa = field.getSubfields('a');
                List subfieldsk = field.getSubfields('k');

                Iterator subfieldsaIter = subfieldsa.iterator();
                Iterator subfieldskIter = subfieldsk.iterator();

                if (subfieldsa != null && subfieldsk != null) {
                    String info;
                    String value;
                    String id;
                    String tmpID;
                    while (subfieldsaIter.hasNext() && subfieldskIter.hasNext()) {
                        Subfield temp  = (Subfield) subfieldsaIter.next();
                        info = temp.getData().toLowerCase();
                        temp  = (Subfield) subfieldskIter.next();
                        value = temp.getData().replace("A","").trim();
                        tmpID = "000000000".substring(value.length()) + value;
                        if (info.contains("ancestry")) {
                        	id =  ils_prefix + tmpID;
                        	result.add(id);
                        }
                    }
                }
            }
        }

    	return result;
    }

    /**
     * Determine if a record is in a collection,
     * and if so set the collection Name.
     *
     * @param  Record          record
     * @return Set  Collection ID or null
     */
    public Set getHierarchyBrowse(Record record) {

    	Set result = new LinkedHashSet();

    	// Check 773 subfield i for "In Collection":
    	List fields = record.getVariableFields("773");
    	Iterator fieldsIter = fields.iterator();
        if (fields != null) {
    		DataField physical;
    		while(fieldsIter.hasNext()) {
    			physical = (DataField) fieldsIter.next();

    			List subfieldsi = physical.getSubfields('i');
    			List subfieldst = physical.getSubfields('t');
    			List subfieldsw = physical.getSubfields('w');

    			Iterator subfieldsiIter = subfieldsi.iterator();
    			Iterator subfieldstIter = subfieldst.iterator();
    			Iterator subfieldswIter = subfieldsw.iterator();

    			if (subfieldsi != null && subfieldst != null && subfieldsw != null) {
    				String info;
    				String title;
    				String id = null;
                    String unformatted_id;
            		while (subfieldsiIter.hasNext() && subfieldstIter.hasNext() && subfieldswIter.hasNext()) {
            		    Subfield temp  = (Subfield) subfieldsiIter.next();
                		info = temp.getData().toLowerCase();
                		temp  = (Subfield) subfieldstIter.next();
                		title = temp.getData();
                        title = Normalizer.normalize(title, Normalizer.Form.NFD);
                        // normalises collection titles with full stop or comma at end of title
                        title = title.replaceAll("[,.]$", "");
                        temp  = (Subfield) subfieldswIter.next();
                        unformatted_id = temp.getData();
                    	id = getValidIlsNumber(unformatted_id);
                        // if record doesn't have a valid ILS prefix, don't display it
                        if (id == null) {
                            logger.error("While building collection: Expected valid " + ils_prefix + " in 773w, got " + unformatted_id);
                         } else {
                            String idTitlePair = title + "{{{_ID_}}}" + id;
                            if (info.contains("in collection")) {
                                result.add(idTitlePair);
                            }
                            //all collections are inherently part of themselves
                            //not sure if this is needed here?
                            if (info.contains("is collection")) {
                                result.add(idTitlePair);
                            }
                        }
                	}
            	}
           }
    	}
    	if(result.size() > 0){
    		return result;
    	}

    	//make sure it's not actually a hierarchy record that just doesn't have a 773 but has ancestry in 999 a
    	//in this case we use the ID as the title
    	// Check 999 subfield a for "Ancestry":
        fields = record.getVariableFields("999");
        fieldsIter = fields.iterator();
        if (fields != null) {
            DataField field;
            while(fieldsIter.hasNext()) {
                field = (DataField) fieldsIter.next();
                List subfieldsa = field.getSubfields('a');
                List subfieldsk = field.getSubfields('k');

                Iterator subfieldsaIter = subfieldsa.iterator();
                Iterator subfieldskIter = subfieldsk.iterator();

                if (subfieldsa != null && subfieldsk != null) {
                    String info;
                    String value;
                    String id;
                    String tmpID;
                    while (subfieldsaIter.hasNext() && subfieldskIter.hasNext()) {
                        Subfield temp  = (Subfield) subfieldsaIter.next();
                        info = temp.getData().toLowerCase();
                        temp  = (Subfield) subfieldskIter.next();
                        value = temp.getData().replace("A","").trim();;
                        tmpID = "000000000".substring(value.length()) + value;
                        id =  ils_prefix + tmpID;
                        String idTitlePair = id + "{{{_ID_}}}" + id;
                        if (info.contains("ancestry")) {
                        	result.add(idTitlePair);
                        }
                    }
                }
            }
        }

        return result;
    }

}
