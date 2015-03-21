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

		
		// TODO: probably won't need this
/**		$this->fields[$type] = array(
			'ref' => NULL,
			'array' => NULL
		);
*/
/**
		$inputFields = $this->getFields($type, 'input');
		$inputFields = $this->getFields($type, 'input');
		$listFields = $this->getFields($type, 'list');
		$loadFields = $this->getFields($type, 'load');
		$saveFields = $this->getFields($type, 'save');

		print "$type:input=><pre>".print_r($inputFields,true)."</pre>\n\n";
		print "$type:list=><pre>".print_r($listFields,true)."</pre>\n\n";
		print "$type:load=><pre>".print_r($loadFields,true)."</pre>\n\n";
		print "$type:save=><pre>".print_r($saveFields,true)."</pre>\n\n";
		print "\n";
		*/


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

	public function load($type, $args){
		try{
			if(!empty($args) && $fields = $this->getFields($type, 'load')){
				$this->assert($type);

				// extract arrays and $refs from $fields

				/**
				$ref_fields = $this->getRefFields($type);
				$array_fields = $this->getArrayFields($type);
				$fields = array_filter($fields, function($value)use($ref_fields, $array_fields){
					return (!in_array($value, array_keys($array_fields)));
				});
				*/

				//print "loading $type ".print_r($fields, true)."REF[".print_r($ref_fields,true)."],ARR[".print_r($array_fields,true)."] WHERE ".print_r($args,true)."\n";



				// basic SELECT with list fields
				$query = $this->{$type}();
				$query = call_user_func_array(array($query, 'select'), $fields);

				// add JOINs for arrays and $refs

				// add WHERE arguments
				foreach($args as $field_id => $field_value){
					$query->where($field_id, $field_value);
				}

				//print "loading $type ".print_r($fields, true)." where ".print_r($args,true)."\n";
				//print "<pre>"..print_r($query, true)."</pre>\n";

				$data = NULL;
				// retrieve the first row and extract column data into assoc array
				$result = $query->fetch();
				if($result){
					$data = $this->rowToArray($result);

					print "got $type data:".print_r($data, true)."\n";

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
						$items[$parent_table.'_id'] = (object)array( '$ref' => '/'.$parent_table.'/id');
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

						// look up the referenced field schema so we can use it for first-level valiation
						if($table_schema = $this->getSchema($field->table)){
							if($field_schema = $this->getSchemaField($table_schema, $field->field)){
								$field->schema = $field_schema;
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
		// fields is array OR object of { $field_id => TRUE | $field_def }
		// this lets us overwrite the object definition in the config

		//print "\ngetFilteredFields (".($structure?'structure':'ids').") $type\n"; //.print_r($field_ids,true)."\n";

		if($tables = $this->getTableDefs($type)){
			if($structure){
				print "tables:".print_r($tables,true)."\n";
			}
			$result = array();

			print "filter fields: (".implode(', ', $field_ids).")\n";

			foreach($field_ids as $field_id){
				$table_name = $type;
				$field_name = str_replace('.', '_', $field_id);

				if($structure && $fields[$field_id] !== TRUE){
					// TODO: do we actually need this? ... for save, list, load... not really
					print "overwrite field $field_id\n";
					foreach($result[$table_name] as $result_field_id => $result_field){
						if(strpos($result_field_id, $field_name) === 0){
							unset($result[$table_name][$result_field_id]);
						}
					}

					foreach($result as $result_table_name => $result_fields){
						if(strpos($result_table_name, $table_name.'_'.$field_name) === 0){
							unset($result[$result_table_name]);
						}
					}
				}
				else{
					$field = NULL;
					$objectFields = NULL;
					$objectTables = NULL;

					print "filter field: $table_name [$field_name]\n";

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
					else{
						print "MISSING $field_name [$table_name]\n";
					}

				}

			}

			//print "FILTERED FIELDS:".print_r($result, TRUE)."\n";
			return $result;
		}

		

		// array fields - resolve child tables
		// $ref fields - resolve reference table

		// returns fields as schemas when $structure is true , returns fields as array list when $structure is false

		// USED for: data read/write



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
//		$read = in_array($mode, $this->readModes);
//		$write = in_array($mode, $this->writeModes);
//		$as_schema = ($mode=='input'); // flag to return as a schema object
//		$structure = $write; // flag to return field structures instead of just a list

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
		//					a) foreach ($field->type == ref) (use array_filter for that)
		//					b) foreach ($tables as $table where $table != $primary_table)
		//				2. then update the primary table

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

		if(!empty($this->field_configs[$type][$mode])){
			// work with the field lists loaded from the module config
			$config_fields = $this->field_configs[$type][$mode];

			// handle wildcarding
			if(isset($config_fields[0]) && $config_fields[0] == "*"){
				unset($config_fields[0]);

				if($tables = $this->getTableDefs($type)){
					foreach($tables as $table_name => $table){
						foreach($table as $field_id => $field){
							$config_fields[(($table_name != $type)?$table_name.'.':'').$field_id] = TRUE;
						}
					}
				}

				if(in_array($mode, $this->readModes)){
					$config_fields = array_keys($config_fields);
				}
				//	print "\nWILDCARDED fields: ".print_r($fields,true)."\n";
			}
		}

		if($mode == 'input'){
			$fields = $this->getFilteredSchema($type, $config_fields);
		}
		else{
			$fields = $this->getFilteredFields($type, $config_fields, in_array($mode, $this->writeModes));
		}

		if($fields){
			$this->fields[$type][$mode] = $fields;
			return $this->fields[$type][$mode];
		}

		return NULL;

		// below this is obsolete

		//$force_id = ($mode=='save');

		$tables = $this->getTableDefs($type);
		if(!empty($tables)){
			if(!empty($this->field_configs[$type][$mode])){
				// work with the field lists loaded from the module config
				$config_fields = $this->field_configs[$type][$mode];

				// handle wildcarding
				if(isset($config_fields[0]) && $config_fields[0] == "*"){
					unset($config_fields[0]);
					foreach($tables as $table_name => $table){
						foreach($table as $field_id => $field){
							$config_fields[(($table_name != $type)?$table_name.'.':'').$field_id] = TRUE;
						}
					}
					if($read){
						$config_fields = array_keys($config_fields);
					}
//					print "\nWILDCARDED fields: ".print_r($fields,true)."\n";
				}

//				print "\n\n$type $mode loading ".print_r($config_fields, true). " from ".print_r($tables, true)."\n\n";

				$ref_tables = array();

				$fields = array();
				foreach($config_fields as $field_id => $field){
					$field_table = $type;
					$field_name = $read?$field:$field_id; // read field configs have fields listed as array, so field is the field name and field_id is actually the index

					// support for nested fields
					$field_split = explode('.', $field_name, 2);
					if(count($field_split) > 1){
						$field_table = $field_split[0];
						$field_name = str_replace('.', '_', $field_split[1]);
					}

					// detect if it is an array or reference field
					if(!empty($tables[$type.'_'.$field_name][$field_name])){
						//print "array field $type.'_'.$field_name => $field_name\n";
					}
					else if(!empty($tables[$type.'_'.$field_table])){
						//print "array field $type.'_'.$field_name => $field_name\n";
					}


					print "checkin? $field_table [$field_name]\n";

					// support for array fields
					if(!empty($tables[$type.'_'.$field_name])){
						print "ok... $field_table [$field_name]";
						$field_table = $type.'_'.$field_name;
						print " => $field_table\n";


						if(empty($tables[$field_table][$field_name])){
							// array is an object, take all the fields that start with $field_name
							print "   full object $field_name\n";
							if(!isset($fields[$field_table])){
								$fields[$field_table] = array();
							}
							foreach($tables[$field_table] as $array_field_id => $array_field){
								if(strpos($array_field_id, $field_name.'_') !== 0){
									continue;
								}

								if($structure){
									$fields[$field_table][$array_field_id] = $array_field;
								}
								else{
									$fields[$field_table][] = $array_field_id;

								}

							}
						}
						else{
							// array is a single field
							print "   single field $field_name\n";
						}
						
					}
					else if(!empty($tables[$type.'_'.$field_table])){
						print "mmhmm... $field_table [$field_name]\n";
						if(!empty($tables[$type.'_'.$field_table][$field_table.'_'.$field_name])){
							$field_name = $field_table.'_'.$field_name;
						}
						$field_table = $type.'_'.$field_table;
					}

					// we are missing the array object structures? - we can get specific array fields, but not the whole thing

					if(!empty($tables[$field_table][$field_name]->type) && $tables[$field_table][$field_name]->type == 'ref'){
						// support for full reference fields (ie. all fields out of a referenced table for the requested mode)
						print "full ref:".$field_table."=>".$field_name."\n";
						if(empty($ref_tables[$field_name])){
							$ref_fields = $this->getFields($field_name, $mode);
							if(!empty($ref_fields[$field_name])){
								$ref_tables[$field_name] = $ref_fields[$field_name];
							}
						}
						
						if(!empty($ref_tables[$field_name])){
							if(!isset($fields["$$field_name"])){
								$fields["$$field_name"] = $ref_tables[$field_name];
							}
							else{
								$fields["$$field_name"] = array_merge($fields["$$field_name"], $ref_tables[$field_name]);
							}
						}
					}
					else if(!empty($tables[$type][$field_table]->type) && $tables[$type][$field_table]->type == 'ref'){
						// support for specific reference fields (ie. single fields out of a referenced table)
						print "partial ref:".$field_table."=>".$field_name."\n";
						// TODO: what about nested references?  ... ex reffed_field_another_reffed or reffed_field_array
						if(!isset($fields["$$field_table"])){
							$fields["$$field_table"] = array();
						}
						if($structure){
							if(empty($ref_tables[$field_table])){
								$ref_fields = $this->getFields($field_table, $mode);
								if(!empty($ref_fields[$field_table])){
									$ref_tables[$field_table] = $ref_fields[$field_table];
								}
							}
							if(!empty($ref_tables[$field_table][$field_name])){
								if(!isset($fields["$$field_table"])){
									$fields["$$field_table"] = array();
								}
								$fields["$$field_table"][$field_name] = $ref_tables[$field_table][$field_name];
							}
						}
						else{
							$fields["$$field_table"][] = $field_name;
						}
					}
					else if(!empty($tables[$field_table][$field_name])){
						print "a field :) $field_table [$field_name]\n\n";
						if(!isset($fields[$field_table])){
							$fields[$field_table] = array();
						}
						if($structure){
							$fields[$field_table][$field_name] = $tables[$field_table][$field_name];
						}
						else{
							$fields[$field_table][] = $field_name;

						}
					}
				}
				$this->fields[$type][$mode] = $fields;
				return $fields;
			}

		}
		return NULL;
	}

	public function getTableDef($tables, $fields="*"){
		// foreach ($table in $tables)
		//		foreach ($field in $table)
		//			if($field_id in $fields)
		//				use it in result

		// return array:
		//			[table] => [filtered fields from table]

	}

	// TODO: rebuild the getFields stuff (getFields, getRefFields, getArrayFields, etc)


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
					/**if(!empty($sql_cols['definition'])){
						// make sure any referenced tables exist
						$ref_fiels = $this->getRefFields($table);
						foreach($ref_fiels as $field_id => $field_ref){
							$this->assert($field_ref['table']);
						}

						$sql = "CREATE TABLE `$table`(".$sql_cols['definition'].")";
						print "SQL:".str_replace(", ", ", \n", $sql)."\n\n";
						//$this->connection->exec($sql);
						if(!empty($sql_cols['relations'])){
							foreach($sql_cols['relations'] as $relTable => $relTable_def){
								$sql = "CREATE TABLE `$relTable`(".$relTable_def.")";
								print "REL_SQL:".str_replace(", ", ", \n", $sql)."\n\n";
								//$this->connection->exec($sql);
							}
						}
						return TRUE;
					}
					*/

				}
			}
		} catch (Exception $e) {
			throw $e;
		}
		
		throw new DataException("Could not create table: $table");
		return FALSE;
	}

	protected function sql_column_defs($fields, $table=''){
		//return array();
		print "SQL defs for $table ".print_r($fields, true)."\n";

		return NULL;
	}

	

	/** MySql specific functions **/
	protected function get_pdo_mysql($config){
		return new PDO('mysql:host='.$config->host.';dbname='.$config->database, $config->username, $config->password, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
	}

}

?>