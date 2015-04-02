<?php

namespace TN;
use PDO, NotORM, NotORM_Row, NotORM_Structure_Convention, SchemaStore, Exception, stdClass, Jsv4;

class DataException extends Exception{}

class DataRow extends NotORM_Row {
	public function getRow(){
		return $this->row;
	}
}

class DataRelation extends NotORM_Structure_Convention {
	public $relations = array();

	function add($table, $field, $reference_table){
		if(!isset($this->relations[$table])){
			$this->relations[$table] = array();
		}
		if(!array_key_exists($field, $this->relations[$table])){
			$this->relations[$table][$field] = $reference_table;
		}
	}

	function getReferencedColumn($name, $table){
		//print "getReferencedColumn $table [$name] = `...`\n";
		if(!empty($this->relations[$table]) && !empty($this->relations[$table][$name])){
			return $name;
		}		
		return parent::getReferencedColumn($name, $table);
	}

	function getReferencedTable($name, $table) {
		if(!empty($this->relations[$table]) && !empty($this->relations[$table][$name])){
			//print "getReferencedTable $table [$name] = `".$this->relations[$table][$name]."`\n";
			return $this->relations[$table][$name];
		}
		return parent::getReferencedTable($name, $table);
	}
}

class Data extends NotORM {
	protected $schemas;
	protected $tables;
	protected $fields;
	protected $relations;
	protected $connection_type;
	private $field_configs = array();
	private $table_exists = array();
	private $readModes = array('list', 'load');
	private $writeModes = array('input', 'save');

	public function __construct($config) {
		$this->schemas = new SchemaStore();
		$this->tables = array();
		$this->fields = array();
		$this->relations = new DataRelation();

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
			parent::__construct($connection, $this->relations);
		}
		$this->rowClass = '\TN\DataRow';
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
			if(!empty($config->{$mode}) && is_array($config->{$mode})){
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
				$query = call_user_func_array(array($query, 'select'), $fields[$type]);

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

				// retrieve the list as an array indexed by first field
				$list = $query->fetchPairs($fields[$type][0]);

				return $list;
			}
		}
		catch(Exception $e){ throw $e; }
		
		return array();
	}

	public function load($type, $args, $tables=NULL){
		try{
			if(empty($tables)){
				$tables = $this->getFields($type, 'load');
			}
			if(!empty($args) && !empty($tables)){
				//print "\nloading $type with tables: ".print_r($tables,true)."\n";
				$this->assert($type);

				// basic SELECT with list fields
				$query = $this->{$type}();
				$query = call_user_func_array(array($query, 'select'), $tables[$type]);

				// add WHERE arguments
				foreach($args as $field_id => $field_value){
					$query->where($field_id, $field_value);
				}

				$result = NULL;
				$data = NULL;
				// retrieve the first row and extract column data into assoc array
				$result = $query->fetch();
				if($result){
					$data = $this->rowToArray($result);

					$data = $this->compileObject($data);

					$row_data = $this->loadRowData($type, $result, $tables);

					if(!empty($row_data)){
						$data = array_merge($data, $row_data);
					}

					// compile the data array into the object structure
					$data = $this->compileObject($data);
				}

				return $data;
			}
		}
		catch(Exception $e){ throw $e; }
		return NULL;
	}

	public function loadRowData($row_type, $row, $tables){
		$row_array = $this->rowToArray($row);
		$row_data = array();
		// loop through each table and load it if it relates to this row_type
		foreach($tables as $table_name => $table_fields){
			if($table_name == "$$row_type" || strpos($table_name, "$$row_type".'_') === 0){
				// the table is describing a reference field for this row_type
				if($field_name = substr($table_name, strpos($table_name, '_')+1)){
					if(isset($row_array["$field_name.id"])){
						// the row has and id for this reference field
						$ref_data = NULL;
						if(!empty($table_fields['type'])){
							$ref_type = $table_fields['type'];
							$ref_fields = $table_fields['fields'];
							// use the reference field description to configure the fields to load for this reference
							if($ref_fields == "*"){
								$ref_tables = $this->getFields($ref_type, 'load');
							}
							else{
								$ref_tables = $this->getFields($ref_type, "read", $ref_fields);
							}
							// load the referenced object
							$ref_data = $this->load($ref_type, array("$ref_type.id" => $row_array["$field_name.id"]), $ref_tables);
						}
						// inject the referenced object into the row's data
						$row_data[$field_name] = $ref_data;
					}					
				}
			}
			else if($table_name != $row_type && strpos($table_name, $row_type.'_') === 0){
				// if the table_name starts with "$row_type_" and has an $row_type_id field to link back to this row_type
				if(in_array("$table_name.$row_type".'_id', $table_fields) && $field_name = substr($table_name, strlen($row_type.'_'))){
					// it is an array field for this $row_type
					$array_data = array();
					if(!empty($row_array["id"])){
						// make a query on the array table for rows that reference our $row
						// TODO: can we do $row->{$table_name}() instead?
						$query = $this->{$table_name}();
						$query = call_user_func_array(array($query, 'select'), $table_fields);
						$query->where("$table_name.$row_type".'_id', $row_array["id"]);

						// load all the array rows into the $array_data
						while($array_row = $query->fetch()){
							$array_row_data = $this->rowToArray($array_row);
							
							// we don't need the array row's id or the link back to this row
							unset($array_row_data['id']);
							unset($array_row_data[$row_type.'_id']);

							// compile the row into an object
							$array_row_data = $this->compileObject($array_row_data);

							// load any of the array row's data (references and child arrays)
							$array_row_child_data = $this->loadRowData($table_name, $array_row, $tables);
							
							if(!empty($array_row_child_data)){
								// compile the array row's data into an object
								$array_row_child_data = $this->compileObject($array_row_child_data);
								if(is_array($array_row_child_data) && array_key_exists($field_name, $array_row_child_data)){
									// if the array_row's child data is a single-field value (ie. not an object), just use the value
									$array_row_child_data = $array_row_child_data[$field_name];
								}
								// inject the array row child data into the array_row object
								$array_row_data[$field_name] = array_merge($array_row_data[$field_name], $array_row_child_data);
							}

							if(is_array($array_row_data)){
								if(array_key_exists($field_name, $array_row_data)){
									// if the array data is a single-field value (ie. not an object), just use the value
									$array_row_data = $array_row_data[$field_name];
								}
								else if(count($array_row_data) == 1){
									// if it has only one field value, do special processing, because it might still be a single-field value
									$single_key = array_keys($array_row_data)[0];
									
									// if the field_name ends with the name of the single value,
									// it is an array within an object
									if(($temp = strlen($field_name) - strlen($single_key)) >= 0 && strpos($field_name, $single_key, $temp) !== FALSE){
										$array_row_data = $array_row_data[$single_key];
									}
								}
							}

							// now that array_row_data is compiled and its child data is loaded, add it into our array_data
							$array_data[] = $array_row_data;
						}
					}

					// resulting array
					$row_data[$field_name.'s'] = $array_data;
				}
			}
		}

		return $row_data;
	}

	public function save($type, $data){
		print "\n";
		if($this->validate($type, $data)){
			if($table_data = $this->dataToTables($type, $data)){
				try {
					$this->assert($type);
				}
				catch (Exception $e){ throw $e; }

				print "saving $type ".print_r($table_data, TRUE)."\n";

				if(!empty($data['id'])){
					print " *** Update\n";
				}
				else{
					print " *** Insert\n";
				}
			}
			
		}
		else{
			throw new DataException("Invalid $type data!");
		}
		
		return NULL;
	}

	public function validate($type, $data){
		if($fields = $this->getFields($type, 'save')){
			return Jsv4::validate($data, $fields);
		}
		return TRUE;
	}

	public function flattenData($prefix, $data){
		$flat = array();
		$flat[$prefix] = array();
		foreach($data as $key => $value){
			if(is_object($value)){
				$flat_obj = $this->flattenData($key, $value);
				//print "object [$prefix][$key]\n";
				foreach($flat_obj as $flat_table => $flat_values){
					//print "   table:[$flat_table]\n";
					foreach($flat_values as $flat_key => $flat_value){
						//print "      key:[$flat_key]=".print_r($flat_value, TRUE)."\n";
						if(is_array($flat_value)){
							$flat[$prefix][$prefix.'_'.$flat_key] = $flat_value;
						}
						else{
							$flat[$prefix][$key.'_'.$flat_key] = $flat_value;
						}
						
					}
				}

			}
			else if(is_array($value)){
				$array_data = array();
				foreach($value as $array_value){
					if(is_object($array_value)){
						$array_data_val = array();
						$flat_obj = $this->flattenData($key, $array_value);
						foreach($flat_obj as $flat_table => $flat_values){
							foreach($flat_values as $flat_key => $flat_value){
								if(is_array($flat_value)){
									$array_data_val[$prefix.'_'.$flat_key] = $flat_value;
								}
								else{
									$array_data_val[$key.'_'.$flat_key] = $flat_value;
								}
							}
						}
						$array_data[] = $array_data_val;
					}
					else if(is_array($array_value)){
						// we should never have an array of array
						// child arrays should always be embedded as object properties
					}
					else{
						$array_data_val = array();
						$array_data_val[$key] = $array_value;
						$array_data[] = $array_data_val;
					}
				}
				$flat[$prefix][$prefix.'_'.$key] = $array_data;
			}
			else{
				$flat[$prefix][$key] = $value;
			}
		}
		return $flat;
	}

	public function dataToTables($type, $data){
		if($fields = $this->getFields($type, 'save')){
			//print "what kind is [$type]:".gettype($data).":".print_r($data, TRUE)."\n";
			$data = $this->flattenData($type, (object)$data);
			//print "convert [$type] ".print_r($data, TRUE)." into tables using ".print_r($fields, TRUE)."...\n";
			//$table_data = array();
			//foreach($fields as )

			return $data;

			//return $table_data;
		}
		return NULL;
	}

	public function rowToArray($row){
		// transposes a NotORM row object into a simple array of the data
		return $row->getRow();
	}

	public function getArrayRowValue($array_row, $array_name, $exclude_fields){
		$name_split = preg_split("/(\.|_)/", $array_name);
		$simple_name = array_pop($name_split);

		$value = $this->rowToArray($array_row);

		$value = array_diff_key($value, array_flip($exclude_fields));

		if(isset($value[$simple_name])){
			$value = $value[$simple_name];
		}
		else{
			$new_value = array();
			foreach($value as $key => $child_value){
				if(strpos($key, $simple_name) === 0){
					$new_value[str_replace($simple_name.'_', '', $key)] = $child_value;
				}
			}
			$value = $new_value;
		}

		return $value;
	}

	public function compileObject($data){
		$object = array();
		foreach($data as $field => $value){
			// organize the value into objects (. and _ on field names make a child node)
			$field_split = preg_split("/(\.|_)/", $field);
			if(count($field_split) > 1){
				$cNode = &$object;
				foreach($field_split as $idx => $obj_node){
					if($idx < count($field_split)-1){
						if(!isset($cNode[$obj_node])){
							$cNode[$obj_node] = array();
						}
						$cNode = &$cNode[$obj_node];
					}
					else{
						$cNode[$obj_node] = $value;
					}
				}
			}
			else{
				if(!empty($object[$field]) && is_array($object[$field]) && is_array($value)){
					$object[$field] = array_merge($object[$field], $value);
				}
				else{
					$object[$field] = $value;
				}
				
			}
		}
		return $object;
	}

	protected function flattenToTables($name, $fields, $parent_table=''){
		// flatten an object field list ($name_$child)
		// returns as an array where result[$name] is the base table
		// any additional entries are arrays from within the object
		$flat_tables = array();

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
			if($object_field_id == $field_id || strpos($object_field_id, $field_id.'_') === 0 || strpos($field_id, $object_field_id.'_') === 0){
				$object_fields[$object_field_id] = $object_field;
			}
		}
		return count($object_fields)?$object_fields:NULL;
	}

	protected function getObjectTables($field_id, $tables){
		$object_tables = array();
		foreach($tables as $table_id => $table){
			if($table_id == $field_id || strpos($table_id, $field_id.'_') === 0 || strpos($field_id, $table_id.'_') === 0 ){ //(strpos($table_id, '_') !== FALSE && strpos($field_id, $table_id) !== FALSE)){
				$object_tables[$table_id] = $table;
			}
		}
		return count($object_tables)?$object_tables:NULL;
	}

	protected function getFilteredTables($type, $fields, $structure=false){
		$field_ids = $structure?array_keys($fields):$fields;

		// fields is array OR object of { $field_id => TRUE | $field_def }
		// this lets us overwrite the object definition in the config

		// retrieve the table definitions that we can get field descriptions from
		if($tables = $this->getTableDefs($type)){
			$result = array();

			// loop through the requested field_id's and build our result from that
			foreach($field_ids as $field_id){
				$table_name = $type;
				$field_name = str_replace('.', '_', $field_id); // config uses .-notation, database uses _'s
				
				$field = NULL;
				$objectFields = NULL;
				$objectTables = NULL;

				// get the base field
				if($structure && is_object($fields[$field_id])){
					// overwritten field structure
					$field = $fields[$field_id];
				}
				else if(!empty($tables[$table_name][$field_name])){
					// straight field
					$field = $tables[$table_name][$field_name];
				}

				// get the fields related to this field (ie. object children)
				if(!$field && !empty($tables[$table_name])){
					$objectFields = $this->getObjectFields($field_name, $tables[$table_name]);
				}

				// get the tables related to this field (ie. array tables)
				$objectTables = $this->getObjectTables($table_name.'_'.$field_name, $tables);

				// do we have any matching fields or field tables?
				if($field || !empty($objectFields) || !empty($objectTables)){
					$ref_field = ($field && !empty($field->type) && $field->type == 'ref');
					if($ref_field && !$structure){
						// if it's a reference field, 
						$field_name = "$$field_name$$field->table"; // use a special field_id that describes the reference
						$table_prefix = ''; // we have a special field_id, so don't prefix the table
					}
					else{
						// prefix fields with the table_name and a dot (ie. namespace the fields)
						$table_prefix = "$table_name.";
					}
					
					// ensure the $table_name exists in the $result set
					if(!isset($result[$table_name])){
						$result[$table_name] = array();
					}

					// if there is a base field
					if($field){
						// add it to the result set
						if($structure){
							// simple structure
							$result[$table_name][$field_name] = $field;
						}
						else{
							// namespaced field name
							$result[$table_name][] = $table_prefix.$field_name;
						}
					}

					// object children
					if(!empty($objectFields)){
						foreach($objectFields as $object_field_id => $object_field){
							if($structure){
								// simple structure
								$result[$table_name][$object_field_id] = $object_field;
							}
							else{
								// not a structure, add the object child's field_id to the list
								if(!empty($object_field->type) && $object_field->type == 'ref'){
									// for reference fields, use a special field_id that describes the reference
									$ref_field_field = substr($field_id, strlen($object_field_id)+1);
									$result[$table_name][] = "$$object_field_id$$object_field->table".($ref_field_field?".$ref_field_field":'');
								}
								else{
									// add the namespaced object field
									$result[$table_name][] = $table_prefix.$object_field_id;
								}
							}
						}
					}

					// array tables
					if(!empty($objectTables)){
						foreach($objectTables as $object_table_name => $object_table){
							$object_table_prefix = empty($ref_field)?$object_table_name.'.':'';
							$object_field_prefix = substr($object_table_name, strrpos($object_table_name, '_')+1);							
							
							// get the fields for items in this array
							$object_table_fields = $this->getObjectFields($object_field_prefix, $object_table);
							if(!empty($object_table_fields)){
								// build a custom $result set for the array table
								if(!isset($result[$object_table_name])){
									$result[$object_table_name] = array();
								}

								// build a name of this array field
								$object_name = str_replace($type."_", '', $object_table_name);
								$object_name = substr($object_name, 0, strrpos($object_name, '_'));
								$object_name = str_replace('_', '.', $object_name); // config uses .-notation, database uses _'s
								foreach($object_table_fields as $object_field_id => $object_field){
									// add all the array item's fields into the result
									if($structure){
										$result[$object_table_name][$object_field_id] = $object_field;
									}
									else{
										$object_field_id_norm = str_replace('_', '.', $object_field_id); // config uses .-notation, database uses _'s

										// make sure this array item's field is in the requested field list
										$use_field = strpos($field_id, $object_field_id_norm) === 0 || strpos($object_field_id_norm, $field_id) === 0 || in_array($object_field_id_norm, $field_ids) || in_array($object_name, $field_ids) || in_array($object_name.'.'.$object_field_id_norm , $field_ids);
										if(!empty($use_field)){
											$ref_field_str = NULL;
											if(!empty($object_field->type) && $object_field->type == 'ref'){
												// for reference fields, use a special field_id that describes the reference
												$object_field_ref_field = substr($field_id, strlen($object_field_id)+1);
												$ref_field_str = "$$object_field_id$$object_field->table".($object_field_ref_field?".$object_field_ref_field":'');
											}
											else{
												// use the namespaced array item field
												$ref_field_str = $object_table_prefix.$object_field_id;
											}
											if(!empty($ref_field_str) && !in_array($ref_field_str, $result[$object_table_name])){
												// add the field to the resulting array table
												$result[$object_table_name][] = $ref_field_str;
											}
										}
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
					$table_prefix = (!$structure)?$table_name.'.':'';
					$id_fields = array();
					if(!empty($tables[$table_name]['id'])){
						$id_fields[$table_prefix.'id'] = $tables[$table_name]['id'];
					}
					if($table_name != $type){
						// filter for id ref fields and append the table prefix to the field name
						foreach($tables[$table_name] as $table_field_id => $table_field){
							if(isset($table_field->type) && $table_field->type == 'ref' && isset($table_field->field) && $table_field->field == 'id' && isset($table_field->table) && strpos($table_field->table, $type) === 0){
								$id_fields[$table_prefix.$table_field_id] = $table_field;
							}
						}
					}

					if(!empty($id_fields)){
						if($structure){
							$result[$table_name] = array_merge($id_fields, $result[$table_name]);
						}
						else{
							$result[$table_name] = array_unique(array_merge(array_keys($id_fields), $result[$table_name]));
						}
					}
				}
			}

			// we now have a filtered list of fields sorted into their tables
			return $result;
		}

		return NULL;
	}

	protected function getFilteredSchema($type, $fields){
		// fields is object of { $field_id => TRUE | $field_def }
		// this lets us overwrite the object definition in the config

		if($schema = $this->getSchema($type)){
			$filtered_schema = new stdClass;
			$filtered_schema->id = !empty($schema->id)?$schema->id:"/$type";
			$filtered_schema->type = "object";
			$filtered_schema->properties = new stdClass;

			foreach($fields as $field_id => $field){
				if($field === TRUE){
					$field = $this->getNodeChild($schema->properties, $field_id);
					if(!$field){
						// not found, check dot-notation
						$field_id_split = explode('.', $field_id, 2);

						if($ref_field = $this->getNodeChild($schema->properties, $field_id_split[0])){
							if($ref_str = $ref_field->{'$ref'}){
								// found it as a $ref field in the schema

								// parse the $ref_str into a table and referenced field
								unset($ref_field->{'$ref'});
								if($ref_str[0] == '/'){
									$ref_str = substr($ref_str, 1);
								}
								$ref_split = explode('/', $ref_str, 2);
								$ref_field->table = $ref_split[0];

								// use the field requested by dot-notation if there is one
								$ref_field->field = (count($field_id_split) > 1)?$field_id_split[1]:'';
								if(empty($ref_field->field)){
									// otherwise use the referenced field
									$ref_field->field = (count($ref_split) > 1)?str_replace('/', '_', $ref_split[1]):'id';
								}
								if($ref_field->table && $ref_field->field){
									// look up the requested field in the referenced schema
									if($ref_schema = $this->getSchema($ref_field->table)){
										if(!empty($ref_schema->properties)){
											$field = $this->getNodeChild($ref_schema->properties, $ref_field->field);
										}
									}
								}
							}

						}

					}
				}
				if($field){
					$filtered_schema->properties->{$field_id} = $field;
				}
			}
			return $filtered_schema;
		}

		return NULL;
	}

	public function getFields($type, $mode, $field_list=NULL){
//		print "*** GET FIELDS $type [$mode]\n";
		// here is where we filter the fields down based on the input mode configuration

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

		$field_key = NULL; // field key is for caching the filtered list

		// no custom field_list, use the module-configured list of fields
		if(empty($field_list) && (in_array($mode, $this->readModes) || in_array($mode, $this->writeModes))){
			$field_key = $mode;
			if(in_array($field_key, $this->readModes)){
				$mode = "read";
			}
			else{
				$mode = ($mode == "input")?"input":"write";
			}
			if(!empty($this->field_configs[$type][$field_key])){
				$field_list = $this->field_configs[$type][$field_key];
			}
		}

		// we should have a field list now (if we don't, something is wrong)
		if($mode == "read" || $mode == "write" || $mode == "input"){
			if(empty($field_list)){
				$field_lst = "*";
			}

			// make sure $field_list is an array
			if(!is_array($field_list)){
				if(is_object($field_list)){
					$field_list = (array)$field_list;
				}
				else{
					// a string specifying a certain field (or a * for wildcard)
					$field_list_new = array();
					if($mode == "read"){
						// read modes have simple numerically-indexed arrays
						$field_list_new[] = $field_list;
					}
					else{
						// write and input modes have name-keyed arrays to allow for field definitions
						$field_list_new[$field_list] = TRUE;
					}
					$field_list = $field_list_new;
				}
			}
			$field_key = "$type:$mode:".json_encode($field_list); //implode(',', $field_list_defs?array_keys($field_list):$field_list);
			$field_key = md5($field_key);
		}
		
		if(!$field_key || empty($field_list)){
			return NULL;
		}

		// $mode will now be one of read, write, or input
		// $field_key will now contain a unique identifier for this list of fields (a hash of the type, mode, and field list)
		// $field_list will now be an array of field names for read-modes, and a named-key array for input/write modes

		// allow for caching
		if(!empty($this->fields[$type][$field_key])){
			return $this->fields[$type][$field_key];
		}

		$tables = NULL;

		// handle wildcarding
		if(isset($field_list[0]) && $field_list[0] == "*" || isset($field_list["*"])){
			$field_list = array();
			if(!$tables){
				$tables = $this->getTableDefs($type);
			}
			if($tables){
				foreach($tables as $table_name => $table){
					if($table_name == $type){
						foreach($table as $field_id => $field){
							$field_list[$field_id] = TRUE;
						}
					}
					else{
						// it's an array table for $type, just list the name of the array field
						$field_list[substr($table_name, strlen($type.'_'))] = TRUE;
					}
				}
			}
			if($mode == "read"){
				$field_list = array_keys($field_list);
			}
		}

		// at this point we have our field list - either from the module config, from function parameters, and with wildcarding resolved
		$fields = NULL;
		if($mode == "input"){
			$fields = $this->getFilteredSchema($type, $field_list);
		}
		else{
			// get the requested fields sorted into their tables
			$tables = $this->getFilteredTables($type, $field_list, ($mode == "write"));

			// process the tables+fields to handle any references (or other special features)
			$fields = array();

			// check each table for reference fields
			foreach($tables as $table_name => $table_fields){
				// get any ref fields from the table
				$ref_field_defs = array_filter($table_fields, function($field){
						return ( (is_object($field) && !empty($field->type) && $field->type == 'ref')
								|| (is_string($field) && $field[0] == '$') );
					});

				$fields[$table_name] = $table_fields;

				
				// process each reference field
				$ref_fields = array();
				foreach($ref_field_defs as $ref_field_def){
					$ref_type = NULL;

					if(is_object($ref_field_def) && !empty($ref_field_def->table)){
						// structured lists are easy
						$ref_type = $ref_field_def->table;
					}
					else if(is_string($ref_field_def)){
						// if it's a string defining the reference, we need to parse it
						$ref_splits = explode('$', $ref_field_def);
						if(count($ref_splits) > 2){
							$ref_field_name = $ref_splits[1]; // the name of the referencing field

							// $ref_splits[2] is going to contain the referenced type and (optionally) the referenced field
							$ref_split_field = explode('.', $ref_splits[2], 2);
							$ref_type = $ref_split_field[0];
							$ref_field = (count($ref_split_field) > 1)?$ref_split_field[1]:'';

							if(!$ref_field){
								// no referenced field means get the whole thing
								$ref_fields[$ref_field_name] = array('type' => $ref_type, 'fields' => "*"); //"full $ref_type"; //array();
							}
							else{
								// a certain ref field was named
								if(!isset($ref_fields[$ref_field_name])){
									$ref_fields[$ref_field_name] = array('type' => $ref_type, 'fields' => array());
								}
								$ref_fields[$ref_field_name]['fields'][] = $ref_field;
							}
						}

						// remove that ref_field_def from the table fields
						$fields[$table_name] = array_filter($fields[$table_name], function($field_id) use ($ref_field_def){
							return ($field_id != $ref_field_def);
						});
					}
					
				}

				if(!empty($ref_fields)){
					//print "\n[$type] [$mode] $table_name has ref fields:".print_r($ref_fields, TRUE)."\n";
					foreach($ref_fields as $ref_field_name => $ref_field_def){
						// create the NotORM relationship for this reference field
						$this->relations->add($table_name, $ref_field_name, $ref_field_def['type']);

						// only retrieve the id in the first query, the data loader 
						$fields[$table_name][] = "$ref_field_name.id AS `$ref_field_name.id`";
						$fields["$$type"."_$ref_field_name"] = $ref_field_def;
					}
				}


			} // end of foreach ($tables)
		}

		return $fields;
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
			$child_path = str_replace('.', '_', $child_path);
			do{
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
				//print "create $table avec: ".print_r($fields, TRUE)."\n";

				// check for any referenced types
				$ref_types = array();
				foreach($fields as $table_name => $table_fields){
					$ref_fields = array_filter($table_fields, function($field){
						return (!empty($field->type) && $field->type == 'ref');
					});
					foreach($ref_fields as $ref_field_id => $ref_field){
						if(!empty($ref_field->table) && $ref_field->table != $table && !in_array($ref_field->table, $ref_types) && array_key_exists($ref_field->table, $this->field_configs)){
							$ref_types[] = $ref_field->table;
						}
					}
				}
				// assert all reference types
				if(!empty($ref_types)){
					foreach($ref_types as $ref_type){
						$this->assert($ref_type);
					}
				}
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