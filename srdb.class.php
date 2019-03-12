<?php

/**
 *
 * Safe Search and Replace on Database with Serialized Data v3.1.0
 *
 * This script is to solve the problem of doing database search and replace when
 * some data is stored within PHP serialized arrays or objects.
 *
 * For more information, see
 * http://interconnectit.com/124/search-and-replace-for-wordpress-databases/
 *
 * To contribute go to
 * http://github.com/interconnectit/search-replace-db
 *
 * To use, load the script on your server and point your web browser to it.
 * In some situations, consider using the command line interface version.
 *
 * BIG WARNING!  Take a backup first, and carefully test the results of this
 * code. If you don't, and you vape your data then you only have yourself to
 * blame. Seriously.  And if your English is bad and you don't fully
 * understand the instructions then STOP. Right there. Yes. Before you do any
 * damage.
 *
 * USE OF THIS SCRIPT IS ENTIRELY AT YOUR OWN RISK. I/We accept no liability
 * from its use.
 *
 * First Written 2009-05-25 by David Coveney of Interconnect IT Ltd (UK)
 * http://www.davidcoveney.com or http://interconnectit.com
 * and released under the GPL v3
 * ie, do what ever you want with the code, and we take no responsibility for it
 * OK? If you don't wish to take responsibility, hire us at Interconnect IT Ltd
 * on +44 (0)151 331 5140 and we will do the work for you at our hourly rate,
 * minimum 1hr
 *
 * License: GPL v3
 * License URL: http://www.gnu.org/copyleft/gpl.html
 *
 *
 * Version 3.1.0:
 *		* Added port number option to both web and CLI interfaces.
 *		* More reliable fallback on non-PDO systems.
 *		* Confirmation on 'Delete me'
 *		* Comprehensive check to prevent accidental deletion of web projects
 *		* Removed mysql functions and replaced with mysqli
 *
 * Version 3.0:
 * 		* Major overhaul
 * 		* Multibyte string replacements
 * 		* Convert tables to InnoDB
 * 		* Convert tables to utf8_unicode_ci
 * 		* Preview/view changes in report
 * 		* Optionally use preg_replace()
 * 		* Better error/exception handling & reporting
 * 		* Reports per table
 * 		* Exclude/include multiple columns
 *
 * Version 2.2.0:
 * 		* Added remove script patch from David Anderson (wordshell.net)
 * 		* Added ability to replace strings with nothing
 *		* Copy changes
 * 		* Added code to recursive_unserialize_replace to deal with objects not
 * 		just arrays. This was submitted by Tina Matter.
 * 		ToDo: Test object handling. Not sure how it will cope with object in the
 * 		db created with classes that don't exist in anything but the base PHP.
 *
 * Version 2.1.0:
 *              - Changed to version 2.1.0
 *		* Following change by Sergei Biryukov - merged in and tested by Dave Coveney
 *              - Added Charset Support (tested with UTF-8, not tested on other charsets)
 *		* Following changes implemented by James Whitehead with thanks to all the commenters and feedback given!
 * 		- Removed PHP warnings if you go to step 3+ without DB details.
 * 		- Added options to skip changing the guid column. If there are other
 * 		columns that need excluding you can add them to the $exclude_cols global
 * 		array. May choose to add another option to the table select page to let
 * 		you add to this array from the front end.
 * 		- Minor tweak to label styling.
 * 		- Added comments to each of the functions.
 * 		- Removed a dead param from icit_srdb_replacer
 * Version 2.0.0:
 * 		- returned to using unserialize function to check if string is
 * 		serialized or not
 * 		- marked is_serialized_string function as deprecated
 * 		- changed form order to improve usability and make use on multisites a
 * 		bit less scary
 * 		- changed to version 2, as really should have done when the UI was
 * 		introduced
 * 		- added a recursive array walker to deal with serialized strings being
 * 		stored in serialized strings. Yes, really.
 * 		- changes by James R Whitehead (kudos for recursive walker) and David
 * 		Coveney 2011-08-26
 *  Version 1.0.2:
 *  	- typos corrected, button text tweak - David Coveney / Robert O'Rourke
 *  Version 1.0.1
 *  	- styling and form added by James R Whitehead.
 *
 *
 */

class icit_srdb {

	/**
	 * @var array List of all the tables in the database
	 */
	public $all_tables = array();

	/**
	 * @var array Tables to run the replacement on
	 */
	public $tables = array();

	/**
	 * @var string Search term
	 */
	public $search = false;

	/**
	 * @var string Replacement
	 */
	public $replace = false;

	/**
	 * @var bool Use regular expressions to perform search and replace
	 */
	public $regex = false;

	/**
	 * @var bool Leave guid column alone
	 */
	public $guid = false;

	/**
	 * @var array Available engines
	 */
	public $engines = array();

	/**
	 * @var bool|string Convert to new engine
	 */
	public $alter_engine = false;

	/**
	 * @var bool|string Convert to new collation
	 */
	public $alter_collate = false;

	/**
	 * @var array Column names to exclude
	 */
	public $exclude_cols = array();

	/**
	 * @var array Column names to include
	 */
	public $include_cols = array();

	/**
	 * @var bool True if doing a dry run
	 */
	public $dry_run = true;

	/**
	 * @var string Database connection details
	 */
	public $name = '';
	public $user = '';
	public $pass = '';
	public $host = '127.0.0.1';
	public $port = 0;
	public $charset = 'utf8';
	public $collate = '';

	/**
	 * @var array Stores a list of exceptions
	 */
	public $errors = array(
						'search' => array(),
						'db' => array(),
						'tables' => array(),
						'results' => array()
					);

	public $error_type = 'search';

	/**
	 * @var array Stores the report array
	 */
	public $report = array();

	/**
	 * @var int Number of modifications to return in report array
	 */
	public $report_change_num = 30;

	/**
	 * @var bool Whether to echo report as script runs
	 */
	public $verbose = false;

	/**
	 * @var resource Database connection
	 */
	public $db;

	/**
	 * @var use PDO
	 */
	public $use_pdo = true;

	/**
	 * @var int How many rows to select at a time when replacing
	 */
	public $page_size = 50000;

	/**
	 * @var 
	 */
	public $log_errors = "";

	/**
	 * Searches for WP or Drupal context
	 * Checks for $_POST data
	 * Initialises database connection
	 * Handles ajax
	 * Runs replacement
	 *
	 * @param string $name    database name
	 * @param string $user    database username
	 * @param string $pass    database password
	 * @param string $host    database hostname
	 * @param string $port    database connection port
	 * @param string $search  search string / regex
	 * @param string $replace replacement string
	 * @param array $tables  tables to run replcements against
	 * @param bool $live    live run
	 * @param array $exclude_cols  tables to run replcements against
	 *
	 * @return void
	 */
	public function __construct( $args ) {

		$args = array_merge( array(
			'name' 				=> '',
			'user' 				=> '',
			'pass' 				=> '',
			'host' 				=> '',
			'port'            => 3306,
			'search' 			=> '',
			'replace' 			=> '',
			'tables'			   => array(),
			'exclude_cols'    => array(),
			'include_cols' 	=> array(),
			'dry_run' 			=> true,
			'regex' 			   => false,
			'pagesize' 			=> 50000,
			'alter_engine' 	=> false,
			'alter_collation' => false,
			'verbose'			=> false
		), $args );

		// handle exceptions
		set_exception_handler( array( $this, 'exceptions' ) );

		// handle errors
		set_error_handler( array( $this, 'errors' ), E_ERROR | E_WARNING );

		// Setting this so that mb_split works correctly.
		// BEAR IN MIND that this affects the handling of strings INTERNALLY rather than
		// at the html output interface, the console interface, json interface, or the database interface.
		// This means that if the DB has a different charset (utf16?), we need to make sure that it's
		// normalised to utf-8 internally and output in the appropriate charset.
		mb_regex_encoding( 'UTF-8' );

		// allow a string for columns
		foreach( array( 'exclude_cols', 'include_cols', 'tables' ) as $maybe_string_arg ) {
			if ( is_string( $args[ $maybe_string_arg ] ) )
				$args[ $maybe_string_arg ] = array_filter( array_map( 'trim', explode( ',', $args[ $maybe_string_arg ] ) ) );
		}
		
		// verify that the port number is logical		
		// work around PHPs inability to stringify a zero without making it an empty string
		// AND without casting away trailing characters if they are present.
		$port_as_string = (string)$args['port'] ? (string)$args['port'] : "0";		
		if ( (string)abs( (int)$args['port'] ) !== $port_as_string ) {
			$port_error = 'Port number must be a positive integer if specified.';
			$this->add_error( $port_error, 'db' );
			if ( defined( 'STDIN' ) ) {
				echo 'Error: ' . $port_error;	
			}
			return;
		}

		// set class vars
		foreach( $args as $name => $value ) {
			if ( is_string( $value ) )
				$value = stripcslashes( $value );
			if ( is_array( $value ) )
				$value = array_map( 'stripcslashes', $value );
			$this->set( $name, $value );
		}

		// only for non cli call, cli set no timeout, no memory limit
		if( ! defined( 'STDIN' ) ) {

			// increase time out limit
			@set_time_limit( 60 * 10 );

			// try to push the allowed memory up, while we're at it
			@ini_set( 'memory_limit', '1024M' );

		}

	}
	/**
	 * 
	 *
	 * @return array
	 */
	public function processData() {

		$this->db_setup();

		if ( $this->db_valid() ) {

			// update engines
			if ( $this->alter_engine ) {
				$report = $this->update_engine( $this->alter_engine, $this->tables );
			}

			// update collation
			elseif ( $this->alter_collation ) {
				$report = $this->update_collation( $this->alter_collation, $this->tables );
			}

			// default search/replace action
			else {
				$report = $this->replacer( $this->search, $this->replace, $this->tables );
			}

		} else {
			$report = $this->report;

		}

		// store report
		$this->set( 'report', $report );
		return $report;
	}
	/**
	 * Terminates db connection
	 *
	 * @return void
	 */
	public function __destruct() {

		if ( $this->db_valid() )
			$this->db_close();
	}
	/**
	 * 
	 *
	 * @return 
	 */
	public function get( $property ) {

		return $this->$property;
	}
	/**
	 * 
	 *
	 * @return 
	 */
	public function set( $property, $value ) {

		$this->$property = $value;
	}
	/**
	 * 
	 *
	 * @return 
	 */
	public function exceptions( $exception ) {
		
		echo $exception->getMessage() . "\n";
	}
	/**
	 * 
	 *
	 * @return 
	 */
	public function errors( $no, $message, $file, $line ) {

		echo $message . "\n";
	}
	/**
	 * 
	 *
	 * @return 
	 */
	public function log( $type = '' ) {

		$args = array_slice( func_get_args(), 1 ); // $type
		$output = "";
		$space = " "; for ($i=0; $i < 150; $i++) { $space .= " ";}

		switch( $type ) {
			case 'error':
				list( $error_type, $error ) = $args;
				$this->log_errors .= "$error_type: $error" . PHP_EOL;
				break;
			case 'search_replace_table_start':
				list( $table, $search, $replace ) = $args;
				//$output .= "{$table}: replacing {$search} with {$replace}";
				break;
			case 'search_replace_table_end':
				list( $table, $report ) = $args;
            if (intval($report['updates']) > 0) {
               $report['updates'] = "\033[1;31m" . $report['updates'];
            } 
				$output = "\033[0m {$table}: {$report['rows']} rows, {$report['change']} changes found, {$report['updates']}\033[0m updates                      " . "\r";
				break;
			case 'search_replace_end':
				list( $search, $replace, $report ) = $args;
				$dry_run_string = $this->dry_run ? "would have been" : "were"; 
            if (intval($report['updates']) > 0) {
               $report['updates'] = "\033[1;32m" . $report['updates'];
            } 
				$output = "\033[2K\033[0m
Replacing \033[1;31m{$search}\033[0m with \033[1;31m{$replace}\033[0m \non {$report['tables']} tables with {$report['rows']} rows \n\n{$report['change']} changes {$dry_run_string} made \n{$report['updates']}\033[0m updates\n";
				break;
			case 'update_engine':
				list( $table, $report, $engine ) = $args;
				$output = $table . ( $report[ 'converted' ][ $table ] ? ' has been' : 'has not been' ) . ' converted to ' . $engine;
				break;
			case 'update_collation':
				list( $table, $report, $collation ) = $args;
				$output = $table . ( $report[ 'converted' ][ $table ] ? ' has been' : 'has not been' ) . ' converted to ' . $collation;
				break;
		}
		//
		if ( $this->verbose && !empty($output)) {
			echo $output;
      }
	}
	/**
	 * 
	 *
	 * @return void
	 */
	public function add_error( $error, $type = null ) {

		if ( $type !== null )
			$this->error_type = $type;
		$this->errors[ $this->error_type ][] = $error;
		$this->log( 'error', $this->error_type, $error );
	}
	/**
	 * 
	 *
	 * @return 
	 */
	public function use_pdo() {

		return $this->get( 'use_pdo' );
	}
	/**
	 * Setup connection, populate tables array
	 * Also responsible for selecting the type of connection to use.
	 *
	 * @return void
	 */
	public function db_setup() {

		$mysqli_available = class_exists( 'mysqli' );
		$pdo_available    = class_exists( 'PDO'    );

		$connection_type = '';

		// Default to mysqli type.
		// Only advance to PDO if all conditions are met.
		if ( $mysqli_available ) {
			$connection_type = 'mysqli';
		}

		if ( $pdo_available ) {
			// PDO is the interface, but it may not have the 'mysql' module.
			$mysql_driver_present = in_array( 'mysql', pdo_drivers() );

			if ( $mysql_driver_present ) {
				$connection_type = 'pdo';
			}
		}

		// Abort if mysqli and PDO are both broken.
		if ( '' === $connection_type ) {
			$this->add_error( 'Could not find any MySQL database drivers. (MySQLi or PDO required.)', 'db' );
			return false;
		}

		// connect
		$this->set( 'db', $this->connect( $connection_type ) );

	}
	/**
	 * Database connection type router
	 *
	 * @param string $type
	 *
	 * @return callback
	 */
	public function connect( $type = '' ) {

		$method = "connect_{$type}";
		return $this->$method();
	}
	/**
	 * Creates the database connection using newer mysqli functions
	 *
	 * @return resource|bool
	 */
	public function connect_mysqli() {

		// switch off PDO
		$this->set( 'use_pdo', false );

		$connection = @mysqli_connect( $this->host, $this->user, $this->pass, $this->name, $this->port );

		// unset if not available
		if ( ! $connection ) {
			$this->add_error( mysqli_connect_error( ), 'db' );
			$connection = false;
		}

		return $connection;
	}
	/**
	 * Sets up database connection using PDO
	 *
	 * @return PDO|bool
	 */
	public function connect_pdo() {
	
		try {
			$connection = new PDO( "mysql:host={$this->host};port={$this->port};dbname={$this->name}", $this->user, $this->pass );
		} catch( PDOException $e ) {
			$this->add_error( $e->getMessage(), 'db' );
			$connection = false;
		}

		// check if there's a problem with our database at this stage
		if ( $connection && ! $connection->query( 'SHOW TABLES' ) ) {
			$error_info = $connection->errorInfo();
			if ( !empty( $error_info ) && is_array( $error_info ) )
				$this->add_error( array_pop( $error_info ), 'db' ); // Array pop will only accept a $var..
			$connection = false;
		}

		return $connection;
	}
	/**
	 * Retrieve all tables from the database
	 *
	 * @return array
	 */
	public function get_tables() {
		// get tables

		// A clone of show table status but with character set for the table.
		$show_table_status = "SELECT
		  t.`TABLE_NAME` as Name,
		  t.`ENGINE` as `Engine`,
		  t.`version` as `Version`,
		  t.`ROW_FORMAT` AS `Row_format`,
		  t.`TABLE_ROWS` AS `Rows`,
		  t.`AVG_ROW_LENGTH` AS `Avg_row_length`,
		  t.`DATA_LENGTH` AS `Data_length`,
		  t.`MAX_DATA_LENGTH` AS `Max_data_length`,
		  t.`INDEX_LENGTH` AS `Index_length`,
		  t.`DATA_FREE` AS `Data_free`,
		  t.`AUTO_INCREMENT` as `Auto_increment`,
		  t.`CREATE_TIME` AS `Create_time`,
		  t.`UPDATE_TIME` AS `Update_time`,
		  t.`CHECK_TIME` AS `Check_time`,
		  t.`TABLE_COLLATION` as Collation,
		  c.`CHARACTER_SET_NAME` as Character_set,
		  t.`Checksum`,
		  t.`Create_options`,
		  t.`table_Comment` as `Comment`
		FROM information_schema.`TABLES` t
			LEFT JOIN information_schema.`COLLATION_CHARACTER_SET_APPLICABILITY` c
				ON ( t.`TABLE_COLLATION` = c.`COLLATION_NAME` )
		  WHERE t.`TABLE_SCHEMA` = '{$this->name}';
		";

		$all_tables_mysql = $this->db_query( $show_table_status );
		$all_tables = array();

		if ( ! $all_tables_mysql ) {

			$this->add_error( $this->db_error( ), 'db' );

		} else {

			// set the character set
			//$this->db_set_charset( $this->get( 'charset' ) );
			while ( $table = $this->db_fetch( $all_tables_mysql ) ) {
				// ignore views
				if ( $table[ 'Comment' ] == 'VIEW' )
					continue;
				$all_tables[ $table[0] ] = $table;
			}
		}
		return $all_tables;
	}
	/**
	 * Get the character set for the current table
	 *
	 * @param string $table_name The name of the table we want to get the char
	 * set for
	 *
	 * @return string    The character encoding;
	 */
	public function get_table_character_set( $table_name = '' ) {

		$table_name = $this->db_escape( $table_name );
		$schema = $this->db_escape( $this->name );

		$charset = $this->db_query(  "SELECT c.`character_set_name`
			FROM information_schema.`TABLES` t
				LEFT JOIN information_schema.`COLLATION_CHARACTER_SET_APPLICABILITY` c
				ON (t.`TABLE_COLLATION` = c.`COLLATION_NAME`)
			WHERE t.table_schema = {$schema}
				AND t.table_name = {$table_name}
			LIMIT 1;" );

		$encoding = false;
		if ( ! $charset ) {
			$this->add_error( $this->db_error( ), 'db' );
		}
		else {
			$result = $this->db_fetch( $charset );
			$encoding = isset( $result[ 'character_set_name' ] ) ? $result[ 'character_set_name' ] : false;
		}
		return $encoding;
	}
	/**
	 * Retrieve all supported database engines
	 *
	 * @return array
	 */
	public function get_engines() {

		// get available engines
		$mysql_engines = $this->db_query( 'SHOW ENGINES;' );
		$engines = array();

		if ( ! $mysql_engines ) {
			$this->add_error( $this->db_error( ), 'db' );
		} else {
			while ( $engine = $this->db_fetch( $mysql_engines ) ) {
				if ( in_array( $engine[ 'Support' ], array( 'YES', 'DEFAULT' ) ) )
					$engines[] = $engine[ 'Engine' ];
			}
		}

		return $engines;
	}
	/**
	 * 
	 *
	 * @return 
	 */
	public function db_query( $query ) {

		if ( $this->use_pdo() )
			return $this->db->query( $query );
		else
			return mysqli_query( $this->db, $query );
	}
	/**
	 * 
	 *
	 * @return 
	 */
	public function db_update( $query ) {

		if ( $this->use_pdo() )
			return $this->db->exec( $query );
		else
			return mysqli_query( $this->db, $query );
	}
	/**
	 * 
	 *
	 * @return 
	 */
	public function db_error() {

		if ( $this->use_pdo() ) {
			$error_info = $this->db->errorInfo();
			return !empty( $error_info ) && is_array( $error_info ) ? 
				array_pop( $error_info ) : 'Unknown error';
		}
		else
			return mysqli_error( $this->db );
	}
	/**
	 * 
	 *
	 * @return 
	 */
	public function db_fetch( $data ) {

		if ( $this->use_pdo() )
			return $data->fetch();
		else
			return mysqli_fetch_array( $data );
	}
	/**
	 * 
	 *
	 * @return 
	 */
	public function db_escape( $string ) {

		if ( $this->use_pdo() )
			return $this->db->quote( $string );
		else
			return "'" . mysqli_real_escape_string( $this->db, $string ) . "'";
	}
	/**
	 * 
	 *
	 * @return 
	 */
	public function db_free_result( $data ) {

		if ( $this->use_pdo() )
			return $data->closeCursor();
		else
			return mysqli_free_result( $data );
	}
	/**
	 * 
	 *
	 * @return 
	 */
	public function db_set_charset( $charset = '' ) {

		if ( ! empty( $charset ) ) {
			if ( ! $this->use_pdo() && function_exists( 'mysqli_set_charset' ) )
				mysqli_set_charset( $this->db, $charset );
			else
				$this->db_query( 'SET NAMES ' . $charset );
		}
	}
	/**
	 * 
	 *
	 * @return 
	 */
	public function db_close() {

		if ( $this->use_pdo() )
			unset( $this->db );
		else
			mysqli_close( $this->db );
	}
	/**
	 * 
	 *
	 * @return 
	 */
	public function db_valid() {

		return (bool)$this->db;
	}
	/**
	 * added eric.jay@info.kedu.coop 09/03/2019
	 *
	 * @param string $from  String we're looking to replace.
	 * @param string $to    What we want it to be replaced with
	 * @param string $data  string value, can be a serialized object value.
	 *
	 * @return string	The original string with all elements replaced as needed.
	 */
	public function unserialize_replace ($search, $replace, $data) {

		$strings = [];
		$return = '' . $data;
		$pattern_serialized = '/^a:\d+:.*$/';
		$pattern_block = ';s:';
		$diff = mb_strlen($replace) - mb_strlen($search);

		// if data is a serialized value
		preg_match($pattern_serialized, $data, $matches);
		if (!empty($matches[0])) {

			// if search found
			if (strpos($matches[0], $search) !== false) {

				// split line in block strings
				$arr_strings = explode($pattern_block, $matches[0]); 
				foreach ($arr_strings as $arr_string) {
					$block = $arr_string;

					// if search found in block string
					if (strpos($block, $search) !== false) {

						$pos = strpos($block, ':');
						$tmp_str = substr($block, $pos);
						$tmp_len = intval(substr($block, 0, $pos));			
						$tmp_len += $diff;
						$block = $tmp_len . $this->mb_str_replace( $search, $replace, $tmp_str);
					}  $strings[] = $block;
				}
				$return = implode($pattern_block, $strings);
			} 

		// if data is not a serialized value, just replace
		} else if (strpos($data, $search) !== false){
			$return = $this->mb_str_replace( $search, $replace, $data);
		}
		return $return;
	}	
	/**
	 * Take a serialised array and unserialise it replacing elements as needed and
	 * unserialising any subordinate arrays and performing the replace on those too.
	 *
	 * @param string $from       String we're looking to replace.
	 * @param string $to         What we want it to be replaced with
	 * @param array  $data       Used to pass any subordinate arrays back to in.
	 * @param bool   $serialised Does the array passed via $data need serialising.
	 *
	 * @return array	The original array with all elements replaced as needed.
	 */
	public function recursive_unserialize_replace( $from = '', $to = '', $data, $serialised = false ) {

		// some unserialised data cannot be re-serialised eg. SimpleXMLElements
		try {

			if ( is_string( $data ) && ( $unserialized = @unserialize( $data ) ) !== false ) {
				//echo 'is_string( $data ) &&' . PHP_EOL;
				$data = $this->recursive_unserialize_replace( $from, $to, $unserialized, true );
			}

			elseif ( is_array( $data ) ) {
				//echo 'is_array( $data )' . PHP_EOL;
				$_tmp = array( );
				foreach ( $data as $key => $value ) {
					$_tmp[ $key ] = $this->recursive_unserialize_replace( $from, $to, $value, false );
				}

				$data = $_tmp;
				unset( $_tmp );
			}

			// Submitted by Tina Matter
			elseif ( is_object( $data ) ) {

				$_tmp = $data; // new $data_class( );
				$props = get_object_vars( $data );
				foreach ( $props as $key => $value ) {
					$_tmp->$key = $this->recursive_unserialize_replace( $from, $to, $value, false );
				}

				$data = $_tmp;
				unset( $_tmp );
			}

			else {
				if ( is_string( $data ) ) {
					$data = $this->str_replace( $from, $to, $data );

				}
			}

			if ( $serialised ) {

				return serialize( $data );
			}

		} catch( Exception $error ) {
			//echo $error->getMessage();die;
			$this->add_error( "" . $error->getMessage(), 'results' );

		}

		return $data;
	}


	/**
	 * Regular expression callback to fix serialised string lengths
	 *
	 * @param array $matches matches from the regular expression
	 *
	 * @return string
	 */
	public function preg_fix_serialised_count( $matches ) {

		$length = mb_strlen( $matches[ 2 ] );
		if ( $length !== intval( $matches[ 1 ] ) )
			return "s:{$length}:\"{$matches[2]}\";";
		return $matches[ 0 ];
	}


	/**
	 * The main loop triggered in step 5. Up here to keep it out of the way of the
	 * HTML. This walks every table in the db that was selected in step 3 and then
	 * walks every row and column replacing all occurences of a string with another.
	 * We split large tables into 50,000 row blocks when dealing with them to save
	 * on memmory consumption.
	 *
	 * @param string $search     What we want to replace
	 * @param string $replace    What we want to replace it with.
	 * @param array  $tables     The tables we want to look at.
	 *
	 * @return array    Collection of information gathered during the run.
	 */
	public function replacer( $search = '', $replace = '', $tables = array( ) ) {

		$search = (string)$search;
		// check we have a search string, bail if not
		if ( '' === $search ) {
			$this->add_error( 'Search string is empty', 'search' );
			return false;
		}

		$report = array( 'tables' => 0,
						 'rows' => 0,
						 'change' => 0,
						 'updates' => 0,
						 'start' => microtime(true),
						 'end' => microtime(true),
						 'errors' => array(),
						 'table_reports' => array()
						 );

		$table_report = array(
						 'rows' => 0,
						 'change' => 0,
						 'changes' => array(),
						 'updates' => 0,
						 'start' => microtime(true),
						 'end' => microtime(true),
						 'errors' => array(),
						 );

		$dry_run = $this->get( 'dry_run' );

		if ( $this->get( 'dry_run' ) ) 	// Report this as a search-only run.
			$this->add_error( 'The dry-run option was selected. No replacements will be made.', 'results' );

		// if no tables selected assume all
		if ( empty( $tables ) ) {
			$all_tables = $this->get_tables();
			$tables = array_keys( $all_tables );
		}

		if ( is_array( $tables ) && ! empty( $tables ) ) {

			foreach( $tables as $table ) {

				$encoding = $this->get_table_character_set( $table );
				switch( $encoding ) {

					// Tables encoded with this work for me only when I set names to utf8. I don't trust this in the wild so I'm going to avoid.
					case 'utf16':
					case 'utf32':
						//$encoding = 'utf8';
						$this->add_error( "The table \"{$table}\" is encoded using \"{$encoding}\" which is currently unsupported.", 'results' );
						continue;
						break;

					default:
						$this->db_set_charset( $encoding );
						break;
				}

				$report[ 'tables' ]++;

				// get primary key and columns
				list( $primary_key, $columns ) = $this->get_columns( $table );
				
				if ( $primary_key === null || empty( $primary_key ) ) {
					$this->add_error( "The table \"{$table}\" has no primary key. \nChanges will have to be made manually.", 'results' );
					continue;
				}
				
				// create new table report instance
				$new_table_report = $table_report;
				$new_table_report[ 'start' ] = microtime(true);

				$this->log( 'search_replace_table_start', $table, $search, $replace );

				// Count the number of rows we have in the table if large we'll split into blocks, This is a mod from Simon Wheatley
				$row_count = $this->db_query( "SELECT COUNT(*) FROM `{$table}`" );
				$rows_result = $this->db_fetch( $row_count );
				$row_count = $rows_result[ 0 ];

				$page_size = $this->get( 'page_size' );
				$pages = ceil( $row_count / $page_size );

				for( $page = 0; $page < $pages; $page++ ) {

					$start = $page * $page_size;

					// Grab the content of the table
					$data = $this->db_query( sprintf( 'SELECT * FROM `%s` LIMIT %d, %d', $table, $start, $page_size ) );

					if ( ! $data )
						$this->add_error( $this->db_error( ), 'results' );

					while ( $row = $this->db_fetch( $data ) ) {

						$report[ 'rows' ]++; // Increment the row counter
						$new_table_report[ 'rows' ]++;

						$update_sql = array( );
						$where_sql = array( );
						$update = false;

						foreach( $columns as $column ) {

							//$edited_data = $data_to_fix = $row[ $column ];
							$data_to_fix = $row[ $column ];

							if ( in_array( $column, $primary_key ) ) {
								$where_sql[] = "`{$column}` = " . $this->db_escape( $data_to_fix );
								continue;
							}

							// exclude cols
							if ( in_array( $column, $this->exclude_cols ) )
								continue;

							// include cols
							if ( ! empty( $this->include_cols ) && ! in_array( $column, $this->include_cols ) )
								continue;
							
							// Run a search replace on the data that'll respect the serialization.
							if (strpos($table, 'wp_options') !== false) {
								$edited_data = $this->unserialize_replace
									($search, $replace, $data_to_fix);
							} else {
								$edited_data = $this->recursive_unserialize_replace
									( $search, $replace, $data_to_fix );
							}

							// Something was changed
							if ( $edited_data !== $data_to_fix ) {

								$report[ 'change' ]++;
								$new_table_report[ 'change' ]++;

								// log first x changes
								if ( $new_table_report[ 'change' ] <= $this->get( 'report_change_num' ) ) {
									$new_table_report[ 'changes' ][] = array(
										'row' => $new_table_report[ 'rows' ],
										'column' => $column,
										'from' => ( $data_to_fix ),
										'to' => ( $edited_data )
									);
								}

								$update_sql[] = "`{$column}` = " . $this->db_escape( $edited_data );
								$update = true;

							}

						}

						if ( $dry_run ) {
							// nothing for this state
						} elseif ( $update && ! empty( $where_sql ) ) {

							$sql = 'UPDATE ' . $table . ' SET ' . implode( ', ', $update_sql ) . ' WHERE ' . implode( ' AND ', array_filter( $where_sql ) );
							
							$result = $this->db_update( $sql );

							if ( ! is_int( $result ) && ! $result ) {

								$this->add_error( $this->db_error( ), 'results' );

							} else {

								$report[ 'updates' ]++;
								$new_table_report[ 'updates' ]++;
							}

						}

					}

					$this->db_free_result( $data );

				}

				$new_table_report[ 'end' ] = microtime(true);

				// store table report in main
				$report[ 'table_reports' ][ $table ] = $new_table_report;

				// log result
				$this->log( 'search_replace_table_end', $table, $new_table_report );
			}

		}

		$report[ 'end' ] = microtime(true);

		$this->log( 'search_replace_end', $search, $replace, $report );

		return $report;
	}


	public function get_columns( $table ) {

		$primary_key = array();
		$columns = array( );

		// Get a list of columns in this table
		$fields = $this->db_query( "DESCRIBE {$table}" );
		if ( ! $fields ) {
			$this->add_error( $this->db_error( ), 'db' );
		} else {
			while( $column = $this->db_fetch( $fields ) ) {
				$columns[] = $column[ 'Field' ];
				if ( $column[ 'Key' ] == 'PRI' )
					$primary_key[] = $column[ 'Field' ];
			}
		}

		return array( $primary_key, $columns );
	}
/*
	public function do_column() {

	}*/
	/**
	 * Convert table engines
	 *
	 * @param string $engine Engine type
	 * @param array $tables
	 *
	 * @return array    Modification report
	 */
	public function update_engine( $engine = 'MyISAM', $tables = array() ) {

		$report = false;

		if ( empty( $this->engines ) )
			$this->set( 'engines', $this->get_engines() );

		if ( in_array( $engine, $this->get( 'engines' ) ) ) {

			$report = array( 'engine' => $engine, 'converted' => array() );

			$all_tables = $this->get_tables();
			
			if ( empty( $tables ) ) {
				$tables = array_keys( $all_tables );
			}

			foreach( $tables as $table ) {
				$table_info = $all_tables[ $table ];

				// are we updating the engine?
				if ( $table_info[ 'Engine' ] != $engine ) {
					$engine_converted = $this->db_query( "alter table {$table} engine = {$engine};" );
					if ( ! $engine_converted )
						$this->add_error( $this->db_error( ), 'results' );
					else
						$report[ 'converted' ][ $table ] = true;
					continue;
				} else {
					$report[ 'converted' ][ $table ] = false;
				}

				if ( isset( $report[ 'converted' ][ $table ] ) )
					$this->log( 'update_engine', $table, $report, $engine );
			}

		} else {

			$this->add_error( 'Cannot convert tables to unsupported table engine &rdquo;' . $engine . '&ldquo;', 'results' );

		}

		return $report;
	}
	/**
	 * Updates the characterset and collation on the specified tables
	 *
	 * @param string $collate table collation
	 * @param array $tables  tables to modify
	 *
	 * @return array    Modification report
	 */
	public function update_collation( $collation = 'utf8_unicode_ci', $tables = array() ) {

		$report = false;

		if ( is_string( $collation ) ) {

			$report = array( 'collation' => $collation, 'converted' => array() );

			$all_tables = $this->get_tables();
				
			if ( empty( $tables ) ) {
				$tables = array_keys( $all_tables );
			}

			// charset is same as collation up to first underscore
			$charset = preg_replace( '/^([^_]+).*$/', '$1', $collation );

			foreach( $tables as $table ) {
				$table_info = $all_tables[ $table ];

				// are we updating the engine?
				if ( $table_info[ 'Collation' ] != $collation ) {
					$engine_converted = $this->db_query( "alter table {$table} convert to character set {$charset} collate {$collation};" );
					if ( ! $engine_converted )
						$this->add_error( $this->db_error( ), 'results' );
					else
						$report[ 'converted' ][ $table ] = true;
					continue;
				} else {
					$report[ 'converted' ][ $table ] = false;
				}

				if ( isset( $report[ 'converted' ][ $table ] ) )
					$this->log( 'update_collation', $table, $report, $collation );
			}

		} else {

			$this->add_error( 'Collation must be a valid string', 'results' );

		}

		return $report;
	}
	/**
	 * Replace all occurrences of the search string with the replacement string.
	 *
	 * @author Sean Murphy <sean@iamseanmurphy.com>
	 * @copyright Copyright 2012 Sean Murphy. All rights reserved.
	 * @license http://creativecommons.org/publicdomain/zero/1.0/
	 * @link http://php.net/manual/function.str-replace.php
	 *
	 * @param mixed $search
	 * @param mixed $replace
	 * @param mixed $subject
	 * @param int $count
	 * @return mixed
	 */
	public static function mb_str_replace( $search, $replace, $subject, &$count = 0 ) {

		if ( ! is_array( $subject ) ) {
			// Normalize $search and $replace so they are both arrays of the same length
			$searches = is_array( $search ) ? array_values( $search ) : array( $search );
			$replacements = is_array( $replace ) ? array_values( $replace ) : array( $replace );
			$replacements = array_pad( $replacements, count( $searches ), '' );

			foreach ( $searches as $key => $search ) {
				$parts = mb_split( preg_quote( $search ), $subject );
				$count += count( $parts ) - 1;
				$subject = implode( $replacements[ $key ], $parts );
			}
		} else {
			// Call mb_str_replace for each subject in array, recursively
			foreach ( $subject as $key => $value ) {
				$subject[ $key ] = self::mb_str_replace( $search, $replace, $value, $count );
			}
		}

		return $subject;
	}
	/**
	 * Wrapper for regex/non regex search & replace
	 *
	 * @param string $search
	 * @param string $replace
	 * @param string $string
	 * @param int $count
	 *
	 * @return string
	 */
	public function str_replace( $search, $replace, $string, &$count = 0 ) {

		if ( $this->get( 'regex' ) ) {
			return preg_replace( $search, $replace, $string, -1, $count );
		} elseif( function_exists( 'mb_split' ) ) {
			return self::mb_str_replace( $search, $replace, $string, $count );
		} else {
			return str_replace( $search, $replace, $string, $count );
		}
	}

}
