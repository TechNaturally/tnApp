<?php

namespace TN;
use PDO, NotORM, SchemaStore, Exception, stdClass, Jsv4;

class DataException extends Exception{}

class Data extends NotORM {
	protected $schemas;
	protected $tables;
	protected $fields;
	protected $connection_type;
	private $field_configs = array();
	private $table_exists = array();
	private $readModes = array('list', 'load');
	private $writeModes = array('input', 'save');

	public function __construct($config) {
		$this->schemas = new SchemaStore();
		$this->tables = array();
		$this->fields = array();

		$connection = NULL;
		foreach($config as $type => $settings){
			// this is where we can initialize different connection types
			if($type == 'mysql'){
				$connection = $this->get_pdo_mysql($settings);
				$this->connection_type = $type;
			}
		}

		// initialize NotORM with our pdo $connection
		if($connection){
			parent::__construct($connection);
		}
	}

	public function addType($type, $config){
		// add schema
		$schema = (!empty($config->schema))?$config->schema:NULL;
		if($schema && !empty($schema->properties)){
			if(!isset($schema->properties->id)){
				$schema->properties = array_reverse((array)($schema->properties), TRUE);
				$schema->properties['id'] = (object)array( 'type' => 'integer', 'minValue' => 0 );
				$schema->properties = (object)array_reverse($schema->properties, TRUE);
			}
			$this->schemas->add("/$type", $schema);
		}
		
		// add fields
		$this->fields[$type] = array(); // this will get filled as [$type] = [fields] the first time the fields are requested 
		$this->field_configs[$type] = array();
		foreach($this->writeModes as $mode){
			// save fields will always be stored as objects, but allow config as an array
			if(is_array($config->{$mode}) && !empty($config->{$mode})){
				$config->{$mode} = array_flip($config->{$mode});
				$config->{$mode} = array_fill_keys(array_keys($config->{$mode}), TRUE);
			}
			$this->field_configs[$type][$mode] = (!empty($config->{$mode}))?(array)$config->{$mode}:"*";
		}
		foreach($this->readModes as $mode){
			$this->field_configs[$type][$mode] = (!empty($config->{$mode}))?(array)$config->{$mode}:"*";
		}
	}

	public function listOf($type, $args=NULL, $page=NULL){
		try{
			if($fields = $this->getFields($type, 'list')){
				$this->assert($type);

				// basic SELECT with list fields
				$query = $this->{$type}();
				$query = call_user_func_array(array($query, 'select'), $fields);

				// add WHERE arguments
				if(!empty($args)){
					foreach($args as $field_id => $field_value){
						$query->where($field_id, $field_value);
					}
				}
				
				// add page LIMIT
				if($page && !empty($page['limit'])){
					$query->limit($page['limit'], !empty($page['offset'])?$page['offset']:0);
				}

				// retrieve the list as an array indexed by id
				$list = $query->fetchPairs('id');

				return $list;
			}
		}
		catch(Exception $e){ throw $e; }
		
		return array();
	}

	// TODO: build the read operations

	public function load($type, $args){
		try{
			if(!empty($args) && $fields = $this->getFields($type, 'load')){
				$this->assert($type);

				print "load $type : ".print_r($fields, true)."\n";

				// basic SELECT with list fields
				$query = $this->{$type}();
				$query = call_user_func_array(array($query, 'select'), $fields[$type]);

				// add JOINs for arrays and $refs

				// add WHERE arguments
				foreach($args as $field_id => $field_value){
					$query->where($field_id, $field_value);
				}

				//print "loading $type ".print_r($fields, true)." where ".print_r($args,true)."\n";
				//print "<pre>".print_r($query, true)."</pre>\n";

				$result = NULL;
				$data = NULL;
				// retrieve the first row and extract column data into assoc array
				$result = $query->fetch();
				if($result){
					$data = $this->rowToArray($result);

//					print "got $type data:".print_r($data, true)."\n";

					// resolve array data
/**					foreach($array_fields as $field_id => $field_table){
						$field_data = $result->{$field_table}()->fetchPairs('id'); // arrays are a many-to-many
						if($field_data){
							$data[$field_id] = array();
							foreach($field_data as $row_id => $row_data){
								$data[$field_id][] = $row_data[$field_id];
								// TODO: we will need to look at how to extract object fields also
							}
						}
					}

					// resolve references
					foreach($ref_fields as $field_id => $field_ref){
						$ref_fields = $this->getFields($field_ref['table'], 'load');
						$ref_query = $this->{$field_ref['table']}();
						$ref_query = call_user_func_array(array($ref_query, 'select'), $ref_fields);
						$field_data = $ref_query->where($field_ref['field'], $data[$field_id])->fetch(); // references are a many-to-1
						if($field_data){
							$data[$field_id] = $this->rowToArray($field_data);
						}
					}
					*/


				}

				return $data;
			}
		}
		catch(Exception $e){ throw $e; }
		return NULL;
	}

	public function save($type, $data){
		if($fields = $this->getFields($type, 'save')){
			// validate $data against $fields
			// if $data->id do as update
			// else do as insert
			// return rowToArray (newRow)
		}
		return NULL;
	}

	public function validate($type, $data, $field_id){

		return TRUE;
	}

	public function rowToArray($row){
		// transposes a NotORM row object into a simple array of the data
		$array = array();
		foreach ($row as $column => $data){
			$array[$column] = $data;
		}
		return $array;
	}

	protected function flattenToTables($name, $fields, $parent_table=''){
		// flatten an object field list ($name_$child)
		// returns as an array where result[$name] is the base table
		// any additional entries are arrays from within the object
		$flat_tables = array();

		//print "flattening $name [$parent_table] with ".print_r($fields, TRUE)."\n";

		// if the $name ends with @, we know its parent is an array and has already had the field_name appened
		$arrChild = ($name && substr($name, -1) == '@');
		$objChild = ($parent_table && substr($parent_table, -1) == '+');
		if($arrChild){
			$name = substr($name, 0, -1);
		}
		if($objChild){
			$parent_table = substr($parent_table, 0, -1);
		}

		if(!$parent_table){
			$parent_table = $name;
		}

		$flat_tables[$name] = array();
		foreach($fields as $field_id => $field){
			$field = clone $field; // so we leave the original schema in tact
			if(!empty($field->{'$ref'})){
				$field->type = 'ref';
			}

			if(!empty($field->type)){
				if($field->type == 'object'){
					//  objects into flat field names
					if(!empty($field->properties)){
						$objName = $name.($arrChild?'':'_'.$field_id); // don't append the field_id if it is an array (the caller would already have appended it to $name)
						$flat_field_tables = $this->flattenToTables($objName, $field->properties, $objChild?$name:($parent_table.($arrChild?'+':'')));

						if(!empty($flat_field_tables)){
							foreach($flat_field_tables as $flat_field_table_name => $flat_field_table_fields){
								if($flat_field_table_name == $objName){
									foreach($flat_field_table_fields as $flat_field_id => $flat_field){
										$flat_tables[$name][$field_id."_".$flat_field_id] = $flat_field;
									}
								}
								else{
									// it's a different table (ie. an array table)
									$flat_tables[$flat_field_table_name] = $flat_field_table_fields;
								}
								
							}
						}
					}
					else{
						// invalid object definition
					}
					$field = NULL; // don't add the object field itself (its children have been added instead)

				}
				else if($field->type == 'array'){
					//  arrays into related tables with their own list of fields
					if(!empty($field->items)){
						if($objChild){
							$parent_table = $name;
						}
						$items = array();
						$items['id'] = (object)array( 'type' => 'integer', 'minValue' => 0);
						$items[$parent_table.'_id'] = (object)array( '$ref' => '/'.$parent_table.'/id', 'required' => TRUE);
						$items[$field_id] = $field->items;
						
						$flat_field_tables = $this->flattenToTables($name."_".$field_id.'@', $items, $parent_table);

						foreach($flat_field_tables as $flat_field_table_name => $flat_field_table_fields){
							$flat_tables[$flat_field_table_name] = $flat_field_table_fields;
						}
					}
					
					$field = NULL; // the array field is now referencing back to parent
				}
				else if($field->type == 'ref'){
					if($ref_str = $field->{'$ref'}){
						unset($field->{'$ref'});
						if($ref_str[0] == '/'){
							$ref_str = substr($ref_str, 1);
						}
						$ref_split = explode('/', $ref_str, 2);
						$field->table = $ref_split[0];
						$field->field = (count($ref_split) > 1)?str_replace('/', '_', $ref_split[1]):'id';

						if($field->field == 'id'){
							// all id fields in our system should be unsigned integers
							$field->schema = (object)array( 'type' => 'integer', 'minValue' => 0);
						}
						else{
							// look up the referenced field schema so we can use it for first-level valiation
							if($table_schema = $this->getSchema($field->table)){
								if($field_schema = $this->getSchemaField($table_schema, $field->field)){
									$field->schema = $field_schema;
								}
							}
						}
					}
				}
				else{
					// other fields type don't need any extra processing, add them as is
				}

				if($field){
					$flat_tables[$name][$field_id] = $field;
				}
			}
		}

		return $flat_tables;
	}

	public function getTableDefs($type){
		if(isset($this->tables[$type])){
			return $this->tables[$type];
		}
		// get an array of tables which each are a flat array of their field schema definitions
		// objects will turn into flat field lists
		// arrays will turn into many-to-one tables (with $refs set on the child table to link back to parent table)
		// $refs will be resolved into ('type' => 'ref', 'table' => 'table_name', 'field' => 'field_name', 'schema' => 'field_schema_def')
		// all field schema definitions that are returned should be of primitive JSON types (ref, boolean, integer, number, null, string)
		if($schema = $this->getSchema($type)){
			$tables = NULL;
			if(!empty($schema->properties)){
				$tables = $this->flattenToTables($type, $schema->properties);
				if(!empty($tables)){
					$this->tables[$type] = $tables;
				}
			}
			return $tables;
		}
		return NULL;
	}

	protected function getObjectFields($field_id, $table){
		$object_fields = array();
		foreach($table as $object_field_id => $object_field){
			if($object_field_id == $field_id || strpos($object_field_id, $field_id.'_') === 0){
				$object_fields[$object_field_id] = $object_field;
			}
		}
		return count($object_fields)?$object_fields:NULL;
	}

	protected function getObjectTables($field_id, $tables){
		$object_tables = array();
		foreach($tables as $table_id => $table){
			if($table_id == $field_id || strpos($table_id, $field_id.'_') === 0){
				$object_tables[$table_id] = $table;
			}
		}
		return count($object_tables)?$object_tables:NULL;
	}

	protected function getFilteredFields($type, $fields, $structure=false){
		$field_ids = $structure?array_keys($fields):$fields;


		// TODO: overwriting object definition currently not supported
		// fields is array OR object of { $field_id => TRUE | $field_def }
		// this lets us overwrite the object definition in the config

		if($tables = $this->getTableDefs($type)){
			$result = array();

			foreach($field_ids as $field_id){
				$table_name = $type;
				$field_name = str_replace('.', '_', $field_id);
				
				$field = NULL;
				$objectFields = NULL;
				$objectTables = NULL;

				// straight field
				if(!empty($tables[$table_name][$field_name])){
					$field = $tables[$table_name][$field_name];
				}

				// object fields (in root table)
				if(!$field && !empty($tables[$table_name])){
					$objectFields = $this->getObjectFields($field_name, $tables[$table_name]);
				}

				// related field tables (array tables)
				$objectTables = $this->getObjectTables($table_name.'_'.$field_name, $tables);

				// check if it's a reference field
				if(!$field && empty($objectFields) && empty($objectTables) || (isset($field->type) && $field->type == 'ref')){
					$field_split = explode('.', $field_id, 2);

					$ref_field = count($field_split)?$field_split[0]:$field_name;
					if(!empty($tables[$table_name][$ref_field])){
						$field_name = $ref_field;
						$field = $tables[$table_name][$ref_field];
					}

					if(!$structure){
						$table_name = "$$ref_field"; // prepend $ on ref tables in listed fields so we can detect them
						if(count($field_split) > 1){
							$field_name = $field_split[1]; //;str_replace('.', '_', $field_split[1]);
						}
					}
					else if(!isset($result[$ref_field])){
						$result["$$ref_field"] = TRUE;
					}
				}

				// do we have any matching fields or field tables?
				if($field || !empty($objectFields) || !empty($objectTables)){
					if(!isset($result[$table_name])){
						$result[$table_name] = array();
					}

					// simple field
					if($field){
						if($structure){
							$result[$table_name][$field_name] = $field;
						}
						else{
							$result[$table_name][] = $field_name;
						}
					}

					// object children
					if(!empty($objectFields)){
						foreach($objectFields as $object_field_id => $object_field){
							if($structure){
								$result[$table_name][$object_field_id] = $object_field;
							}
							else{
								$result[$table_name][] = $object_field_id;
							}
						}
					}

					// array tables
					if(!empty($objectTables)){
						foreach($objectTables as $object_table_name => $object_table){
							$object_field_prefix = substr($object_table_name, strrpos($object_table_name, '_')+1);							
							$object_table_fields = $this->getObjectFields($object_field_prefix, $object_table);
							if(!empty($object_table_fields)){
								if(!isset($result[$object_table_name])){
									$result[$object_table_name] = array();
								}
								foreach($object_table_fields as $object_field_id => $object_field){
									if($structure){
										$result[$object_table_name][$object_field_id] = $object_field;
									}
									else{
										$result[$object_table_name][] = $object_field_id;
									}
								}
							}
						}
					}
				}
			}
			
			// force id fields and many-to-one id fields on all resulting tables (these are required for properly relating the datas)
			foreach($result as $table_name => $table_fields){
				if(!empty($tables[$table_name])){
					$id_fields = array();
					if(!empty($tables[$table_name]['id'])){
						$id_fields['id'] = $tables[$table_name]['id'];
					}
					$id_fields = array_merge($id_fields, array_filter($tables[$table_name], function($field) use ($type){
						return (isset($field->type) && $field->type == 'ref' && isset($field->field) && $field->field == 'id' && isset($field->table) && strpos($field->table, $type) === 0);
					}));

					if(!empty($id_fields)){
						if($structure){
							$result[$table_name] = array_merge($id_fields, $result[$table_name]);
						}
						else{
							$result[$table_name] = array_merge(array_keys($id_fields), $result[$table_name]);
						}
					}
				}
			}

			return $result;
		}

		return NULL;
	}

	protected function getFilteredSchema($type, $fields){
		$field_ids = array_keys($fields);
		// fields is object of { $field_id => TRUE | $field_def }
		// this lets us overwrite the object definition in the config

		print "\ngetFilteredSchema $type ".print_r($field_ids,true)."\n";

		if($schema = $this->getSchema($type)){
			foreach($field_ids as $field_id){
			}
		}
		
		// array fields - do nothing special
		// $ref fields - resolve referenced field

		// returns schema object with only fields from $fields in it

		// USED for: input schema (form and validation)

		// at some point we will need to translate the schema'd input into the writeable field list

		return NULL;
	}

	public function getFields($type, $mode){
		// here is where we filter the fields down based on the input mode configuration

		// how we use these fields in lookups is important:
		// 	read operations:
		//				1. retrieve fields from primary table ($data->{$table}()->select($fields[$table]))
		//				2. foreach ($field->type == ref) (use array_filter for that)
		//					a) assert the ref table
		//					b) retrieve fields from the referenced table (parse out field names starting with {$ref_table.'.'.$field_name})
		//				3. any additional entries are array tables
		//					a) assert the array table
		//					b) retrieve as $primaryRow->{$array_table}->select($fields[$array_table])

		// write operations:
		//				1. handle any reference updates first
		//					- foreach ($field->type == ref) (use array_filter for that)
		//				2. then update the primary table
		//				3. then update the array tables
		//					- foreach ($tables as $table where $table != $primary_table)

		/** USE CASES
			- input: return schema, filtered by fields
			- list: return field list sorted into tables
			- load: return field list sorted into tables
			- save: return field schemas sorted into tables
		*/

		// readModes:  getFieldsFromTables() // use for retrieving data
		// writeModes: getFieldsFromTables() // use for saving data
		// inputModes: getSchemaFields()	 // use for input forms & validating data

		// allow for caching
		if(!empty($this->fields[$type][$mode])){
			return $this->fields[$type][$mode];
		}

		// work with the field lists loaded from the module config
		if(!empty($this->field_configs[$type][$mode])){
			$config_fields = $this->field_configs[$type][$mode];

			$tables = NULL;

			// handle wildcarding
			if(isset($config_fields[0]) && $config_fields[0] == "*"){
				unset($config_fields[0]);
				if(!$tables){
					$tables = $this->getTableDefs($type);
				}
				if($tables){
					foreach($tables as $table_name => $table){
						foreach($table as $field_id => $field){
							$config_fields[(($table_name != $type)?$table_name.'.':'').$field_id] = TRUE;
						}
					}
				}
				if(in_array($mode, $this->readModes)){
					$config_fields = array_keys($config_fields);
				}
			}
		}

		if($mode == 'input'){
			$fields = $this->getFilteredSchema($type, $config_fields);
		}
		else{			
			$fields = $this->getFilteredFields($type, $config_fields, in_array($mode, $this->writeModes));

			$ref_table_names = array_filter(array_keys($fields), function ($table_name){ return ($table_name && $table_name[0] === '$'); });
			$ref_table_names = array_flip($ref_table_names);
			$ref_tables = array_intersect_key($fields, $ref_table_names);
			$fields = array_diff_key($fields, $ref_table_names); // remove ref tables from the fields

			foreach($ref_tables as $ref_table_name => $ref_table_fields){
				$ref_table_name = substr($ref_table_name, 1); // remove the $ (ref table indicator)

				// assert the referenced table exists (otherwise we've got a problem)
				try{
					$this->assert($ref_table_name);
				}
				catch(Exception $e){ throw $e; }

				// load the fields for the referenced type
				if(in_array($mode, $this->readModes)){
					$ref_fields = $this->getFields($ref_table_name, $mode);
					if(count($ref_fields)){
						if(count($ref_fields[$ref_table_name]) && is_array($ref_table_fields) && !in_array($ref_table_name, $ref_table_fields)){
							//print "filter down to: ".print_r($ref_table_fields, TRUE)."\n";
							$ref_fields[$ref_table_name] = array_filter($ref_fields[$ref_table_name], function($ref_field) use ($ref_table_fields){
								return (in_array($ref_field, $ref_table_fields) || in_array(str_replace('_', '.', $ref_field), $ref_table_fields));
							});
						}

						// add the referenced fields
						foreach($ref_fields as $ref_field_table_name => $ref_field_table_fields){
							if($ref_field_table_name == $ref_table_name){
								// root referenced table, append as simple list
								foreach($ref_field_table_fields as $ref_field_table_field){
									$fields[$type][] = "$ref_table_name.$ref_field_table_field";
								}
							}
							else{
								// referenced array table, append the table if it is named in $ref_table_fields
								foreach($ref_table_fields as $ref_field_table_field){
									$ref_field_table_field_name = $ref_table_name.'_'.str_replace('.', '_', $ref_field_table_field);
									if($ref_field_table_field == $ref_table_name || strpos($ref_field_table_name, $ref_field_table_field_name) === 0){
										$fields[$ref_field_table_name] = $ref_field_table_fields;
									}
								}
							}
						}
					}
				}
			}
		}

		if($fields){
			$this->fields[$type][$mode] = $fields;
			return $this->fields[$type][$mode];
		}

		return NULL;
	}

	public function getSchema($type, $mode=''){
		if(empty($type)){ return NULL; }
		if($mode=='input'){
			return $this->getFields($type, $mode);
		}
		if($type[0] != '/'){ $type = '/'.$type; }
		return $this->schemas->get($type);
	}

	private function getNodeChild($node, $child_path){
		if($node && $child_path){
			$child_id = '';
			do{
				if(isset($node->{$child_path})){
					if(isset($node->{$child_path})){
						if($child_id){
							if(!empty($node->{$child_path}->properties)){
								return $this->getNodeChild($node->{$child_path}->properties, $child_id);
							}
							else{
								return NULL;
							}
						}
						return $node->{$child_path};
					}
				}
				else{
					$last_dot = strrpos($child_path, '_');
					$child_id = substr($child_path, $last_dot+1).($child_id?'_':'')."$child_id";
					$child_path = ($last_dot !== -1)?substr($child_path, 0, $last_dot):'';
				}
			} while($child_path);


		}
		return NULL;
	}

	protected function getSchemaField($schema, $field_id){
		if($field_id && !empty($schema->properties)){
			return $this->getNodeChild($schema->properties, $field_id);
		}
		return NULL;
	}

	public function assert($table){
		try {
			if(isset($this->table_exists[$table]) && $this->table_exists[$table]){
				// already checked existense in this request
				return TRUE;
			}
			else if($this->exists($table)){
				// found the table in database
				$this->table_exists[$table] = TRUE;
				return TRUE;
			}
			else if($this->create($table)){
				// created the table
				$this->table_exists[$table] = TRUE;
				return TRUE;
			}
		}
		catch (DataException $e) { throw $e; }
		catch (Exception $e) { throw $e; }

		throw new DataException("Missing table: $table");
		return FALSE;
	}

	/** Sql specific functions **/
	public function exists($table){
		try {
			// we can check against $this->connection_type (== 'mysql') for different db providers
			$result = $this->connection->query("SELECT 1 FROM $table LIMIT 1");
		} catch (Exception $e) {
			return FALSE;
		}
		return ($result !== FALSE);
	}

	public function create($table){
		try {
			if($fields = $this->getFields($table, 'save')){				
				// we can check against $this->connection_type (== 'mysql') for different db providers
				if($sql_defs = $this->sql_column_defs($fields)){
					//print "Got SQL defs for $table:".print_r($sql_defs, TRUE)."\n";

					// we need at least a definition for the requested table
					if(!empty($sql_defs[$table])){

						foreach($sql_defs as $table_name => $table_def){
							$sql = "CREATE TABLE `$table_name`(".$table_def.")";
							//print "\n$sql\n";
							$this->connection->exec($sql);
							//print "created $table_name!\n";
						}

						// the only way it can fail now is by an exception thrown by the connection
						return TRUE;
					}
				}
			}
		} catch (Exception $e) {
			throw $e;
		}
		
		throw new DataException("Could not create table: $table");
		return FALSE;
	}

	protected function sql_column_def($field_id, $field){
		// transforms a JSON field definition into a SQL column definition
		/** JSON types:
				array - A JSON array.
				boolean - A JSON boolean.
				integer - A JSON number without a fraction or exponent part.
				number - Any JSON number. Number includes integer.
				null - The JSON null value.
				object - A JSON object.
				string - A JSON string.
		*/

		$col_def = '';
		$key_def = '';

		// force unsigned integer for id fields
		if($field_id == 'id' && !isset($field->minValue)){
			$field->minValue = 0;
		}

		if(!empty($field->type)){
			// create the column definition depending on what type of field it is
			// array and object field types should be already handled by flattenToTables

			if($field->type == 'boolean'){
				$col_def = "`$field_id` TINYINT(1)";
			}
			else if($field->type == 'integer'){
				$col_def = "`$field_id` INT";
				if(isset($field->minValue) && $field->minValue >= 0){
					$col_def .= " UNSIGNED";
				}
			}
			else if($field->type == 'number'){
				$col_def = "`$field_id` FLOAT";
			}
			else if($field->type == 'string'){
				$col_def = "`$field_id`";
				if(isset($field->maxLength) && $field->maxLength <= 255){
					if(isset($field->minLength) && $field->minLength == $field->maxLength){
						// fixed-length strings
						$col_def .= " CHAR(".$field->maxLength.")";
					}
					else{
						$col_def .= " VARCHAR(".$field->maxLength.")";
					}
				}
				else{
					$col_def .= " TEXT";
				}
			}
			else if($field->type == 'ref'){
				if(!empty($field->schema) && !empty($field->table) && !empty($field->field)){
					$ref_def = $this->sql_column_def($field_id, $field->schema);
					if(!empty($ref_def['col'])){
						$col_def = $ref_def['col'];
						$key_def = "FOREIGN KEY (`".$field_id."`) REFERENCES `".$field->table."`(`".$field->field."`) ON DELETE ".(!empty($field->required)?"CASCADE":"SET NULL");
					}
				}
			}
		}

		if(isset($field->required) && $field->required){
			$col_def .= " NOT NULL";
		}

		if($col_def && $field_id == 'id' && isset($field->type) && $field->type == 'integer'){
			$col_def .= " AUTO_INCREMENT PRIMARY KEY";
		}

		return array('col' => $col_def, 'key' => $key_def);
	}

	protected function sql_column_defs($fields){
		// transform field definitions into SQL columns
		$table_defs = array();

		foreach($fields as $table => $table_fields){
			$table_cols = '';
			$table_keys = '';

			foreach($table_fields as $field_id => $field){
				$field_def = $this->sql_column_def($field_id, $field);
				if(!empty($field_def['col'])){
					$table_cols .= ($table_cols?', ':'').$field_def['col'];
				}
				if(!empty($field_def['key'])){
					$table_keys .= ($table_keys?', ':'').$field_def['key'];
				}
			}

			if($table_cols){
				$table_defs[$table] = $table_cols;
				if($table_keys){
					$table_defs[$table] .= ', '.$table_keys;
				}
			}
		}

		return !empty($table_defs)?$table_defs:NULL;
	}

	

	/** MySql specific functions **/
	protected function get_pdo_mysql($config){
		return new PDO('mysql:host='.$config->host.';dbname='.$config->database, $config->username, $config->password, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
	}

}

?>