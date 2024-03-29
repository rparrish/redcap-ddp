<?php
/**
 * This class queries database for values as specified in
 * the post parameters. Returns all values requested for the associated
 * ID (MRN)
 * 
 * @author     Marcos Davila (mzd2016@med.cornell.edu)
 * @since      v0.1
 * @package    rest
 * @license    Open Source
 * 
 */
class Data {
	private $db_connect = array();
	
        // Tries to resolve missing class file dependencies at runtime
	function __autoload($className) {
		if (file_exists ( 'utils/' . $className . '.php' )) {
			require 'utils/' . $className . '.php';
			return true;
		} elseif (file_exists ( 'fields/' . $className . '.php' )) {
			require 'fields/' . $className . '.php';
			return true;
		} elseif (file_exists ( 'dao/' . $className . '.php' )) {
			require 'dao/' . $className . '.php';
			return true;
		} else {
			return false;
		}
	}

        /* 
         * Calls the autoloader to import required PHP files, then 
         * instantiates the field dictionary.
         */
	public function __construct($pid, $id, $fields, $config) {
		$registered = spl_autoload_register(array($this, '__autoload'));
                
                if (!$registered) {
                    throw new Exception ( 'The autoloader was unable to resolve a missing dependency.' );
                }
	}
	
	public function __destruct() {
		
	}
	
	/**
	 * Service endpoint for data web service.
	 * Determines whether one-time or temporal data is requested
	 * and returns the data in JSON format
	 *
	 */
	public function data($user, $project_id, $redcap_url, $id, $fields) {
		if (in_array ( $project_id, array_keys(Constants::$pidfiles)) ) {		
			// Instantiate new ConfigDAO to hold information from configuration file
			$config = new ConfigDAO ( Constants::$pidfiles [$project_id] );
			$configarr = $config->getConfiguration ();
			// Pass this information into the dictionary to return the
			// items of interest from the dictionary
			$dict = new FieldDictionary ( $project_id, $id, $fields, $configarr [$project_id] );
			$configdict = $dict->getDictionary ();
			// Get the JSON for these fields
			$a = $this->getJSON ( $configdict );
		
			return $a;
		} else {
			throw new Exception ( "Sorry, your project is unsupported by DDP at this time." );
		}
	}
	
	/**
	 * Returns JSON for a requested set of fields
	 *
	 */
	private function getJSON(array $configdict) {
		// Make one associative array for one-time data and temporal data.
		$json_rows = array ();
		// Must iterate through all fields and make a call for each field
		
		// Connect to all source systems
		$this->connect($configdict);
		
		foreach ( $configdict as $configItem ) {
			
			// Connect to the database and query for information
			$resultSet = $this->db_connect[$configItem["Source"]]->query ( $configItem ["SQL"] );
			
			// If the result set is empty, no information could be found for that query, so skip
			// the import
			if (! $resultSet ) {
				continue;
			}
			
			$redcapfields = new REDCapFieldFormatter ( );
			$json_rows [] = $redcapfields->getField ( $resultSet, $configItem );
		}
		
		// Close the database connection
		foreach($this->db_connect as $dbconn){
			$dbconn->close();
		}
		
		return json_encode ( $json_rows );
	}
	
	/**
	 * Queries the database for information on requested fields
	 *
	 */
	private function connect (array $configdict) {
		
		foreach($configdict as $configItem){
			$db_sources[] = $configItem["Source"];
		}
		$db_sources = array_unique($db_sources);
		
		foreach($db_sources as $sourcesystem){
			if ($sourcesystem === "ARCH") {
				$this->db_connect["ARCH"] = new cadc_db_connect (  );
			} else {
				echo "DDP does not support connecting to " . $sourcesystem . " at this time.";
				throw new Exception("DDP does not support connecting to " . $sourcesystem . " at this time.");
			}
		}
		
		if (is_null ( $this->db_connect )) {
			echo '"Sorry, DDP could not find a valid database to connect to.';
			throw new Exception("Sorry, DDP could not find a valid database to connect to.");
		}
	}
}
?>
	
	
	
	
		
