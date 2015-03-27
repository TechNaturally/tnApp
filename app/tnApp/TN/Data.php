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

	public function load($type, $args){
		try{
			if(!empty($args) && $fields = $this->getFields($type, 'load')){
				print "\nloading $type with fields: ".print_r($fields,true)."\n";
				$this->assert($type);

				// basic SELECT with list fields
				$query = $this->{$type}();
				$query = call_user_func_array(array($query, 'select'), $fields[$type]);

				// add JOINs for arrays and $refs

				// add WHERE arguments
				foreach($args as $field_id => $field_value){
					$query->where($field_id, $field_value);
				}

				print "\nloading $type ".print_r($fields, true)." where ".print_r($args,true)."\n";
				print "relations: ".print_r($this->relations->relations, TRUE)."\n";
				//print "<pre>".print_r($query, true)."</pre>\n";

				$result = NULL;
				$data = NULL;
				// retrieve the first row and extract column data into assoc array
				$result = $query->fetch();
				if($result){
					$data = $this->rowToArray($result);

					// load any array data for this row
					$row_arrays = $this->loadRowArrays($type, $result, $fields);
					if(!empty($row_arrays)){
						$data = array_merge($data, $row_arrays);
					}

					// compile the data array into the object structure
					$data = $this->compileObject($data);
				}

				print "LOADED $type: ".print_r($data, TRUE)."\n";



				return $data;
			}
		}
		catch(Exception $e){ throw $e; }
		return NULL;
	}

	public function loadRowArrays($row_type, $row, $fields){
		$row_arrays = array();
		//print "LOAD ARRAYS FOR $row_type:".print_r($row, TRUE)."\n";
		//print "\nload arrays for $row_type:".print_r($row->getRow(), TRUE)."\n";

		// how do we detect for tables that would be loaded by ref fields?
		// ex. user.abc.thAr
		// ex. user.def.username (not)
		// ex. user.testArObRf.two

		// scan all additional tables, which are representing arrays data
		foreach($fields as $table_name => $table_fields){
			//print "ok...:".$table_name.":".print_r($table_fields, TRUE)."\n";
			// if the table name is containing the $row_type, it is a child of that module/object
			if($table_name !== $row_type && strpos($table_name, $row_type.'_') !== FALSE){
				$child_array = NULL;
				if(in_array($table_name.".".$row_type."_id", $table_fields)){
					// if the table is containing a reference to this $row_type id, load child arrays
					$child_array = $this->loadChildArray($row_type, $row, $table_name, $fields);
				}
				else{
					// this is an unknown table
					if($ref_name = substr($table_name, 0, strpos($table_name, '_'))){
						if(array_key_exists($ref_name.".id", $row->getRow())){
							print "load ref [$row_type] arrays $table_name\n";
							$child_array = $this->loadRefArray($table_name, $table_fields, $ref_name."_id", $row[$ref_name.".id"]);
						}
					}
				}
				if(!empty($child_array)){
					$row_arrays[str_replace($row_type.'_', '', $table_name)] = $child_array;
				}
			}
		}
		return !empty($row_arrays)?$row_arrays:NULL;
	}

	private function loadRefArray($table_name, $table_fields, $ref_field, $ref_value){
		$array_query = $this->{$table_name}();

		// make the query
		$array_query = call_user_func_array(array($array_query, 'select'), $table_fields);

		$array_query->where($ref_field, $ref_value);

		print "load ref array $table_name [$ref_field]\n";

		// process the results
		$array_data = array();
		while($array_row = $array_query->fetch()){
			$row_value = $this->getArrayRowValue($array_row, $table_name, array("id", "$table_name.id"));
			$array_data[] = $row_value;
		}

		return $array_data;
	}

	private function loadChildArray($parent_name, $parent_row, $table_name, $fields){
		if(!empty($fields[$table_name])){
			$table_fields = $fields[$table_name];

			print "load child array $parent_name [$table_name] array\n";

			$array_query = $parent_row->{$table_name}();

			// make the query
			$array_query = call_user_func_array(array($array_query, 'select'), $table_fields);

			// process the results
			$array_data = array();
			while($array_row = $array_query->fetch()){
				$row_value = $this->getArrayRowValue($array_row, $table_name, array("id", "$table_name.id", "$parent_name"."_id", "$table_name.$parent_name"."_id"));

				// load any array data for this row
				$row_arrays = $this->loadRowArrays($table_name, $array_row, $fields);
				if(!empty($row_arrays)){
					$row_value = array_merge($row_value, $row_arrays);
				}

				if(is_array($row_value)){
					$row_value = $this->compileObject($row_value);

					$row_field = str_replace($parent_name.'_', '', $table_name);
					if(isset($row_value[$row_field])){
						$row_value = $row_value[$row_field];
					}
				}

				$array_data[] = $row_value;
			}

			return $array_data;
		}

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
				$object[$field] = $value;
			}
		}
//		print "\nOBJECT: ".print_r($object, true)."\n";
		return $object;
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

	protected function getFilteredFields($type, $fields, $structure=false){
		$field_ids = $structure?array_keys($fields):$fields;

		// TODO: overwriting object definition currently not supported
		// fields is array OR object of { $field_id => TRUE | $field_def }
		// this lets us overwrite the object definition in the config

		if($tables = $this->getTableDefs($type)){
			$result = array();

			//print "FILTERING $type ".print_r($field_ids, TRUE)." tables: ".print_r($tables, TRUE)."\n";

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

//				print "\ncheck field: $field_id\n";
//				print "    A:".($field?'FIELD':'NOFIELD')." ".count($objectFields)." ".count($objectTables)."\n";				
				//print "    B:".($field?'FIELD':'NOFIELD')." ".count($objectFields)." ".count($objectTables)."\n";

				// do we have any matching fields or field tables?
				if($field || !empty($objectFields) || !empty($objectTables)){
					$ref_field = ($field && !empty($field->type) && $field->type == 'ref');
					if($ref_field && !$structure){
						// if it's a reference field
						$field_name = "$$field_name$$field->table";
						$table_prefix = '';
					}
					else{
						// not a reference field
						$table_prefix = "$table_name.";
					}
					
					// ensure the $table_name exists in the $result set
					if(!isset($result[$table_name])){
						$result[$table_name] = array();
					}

					// simple field
					if($field){
						if($structure){
							$result[$table_name][$field_name] = $field;
						}
						else{
							$result[$table_name][] = $table_prefix.$field_name;
						}
					}

					// object children
					if(!empty($objectFields)){
						//print "$field_id object fields:".print_r($objectFields, TRUE)."\n";
						foreach($objectFields as $object_field_id => $object_field){
							if($structure){
								$result[$table_name][$object_field_id] = $object_field;
							}
							else{
								if(!empty($object_field->type) && $object_field->type == 'ref'){
									$ref_field_field = substr($field_id, strlen($object_field_id)+1);
									$result[$table_name][] = "$$object_field_id$$object_field->table".($ref_field_field?".$ref_field_field":''); ////$table_prefix.$object_field_id."$";
								}
								else{
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
							$object_table_fields = $this->getObjectFields($object_field_prefix, $object_table);
							if(!empty($object_table_fields)){
								if(!isset($result[$object_table_name])){
									$result[$object_table_name] = array();
								}

								$object_name = str_replace($type."_", '', $object_table_name);
								$object_name = substr($object_name, 0, strrpos($object_name, '_'));
								$object_name = str_replace('_', '.', $object_name);
								foreach($object_table_fields as $object_field_id => $object_field){
									if($structure){
										$result[$object_table_name][$object_field_id] = $object_field;
									}
									else{
										$object_field_id_norm = str_replace('_', '.', $object_field_id);
										$use_field = in_array($object_field_id_norm, $field_ids) || in_array($object_name, $field_ids) || in_array($object_name.'.'.$object_field_id_norm , $field_ids);
										if(!$use_field){
											$matching_fields = array_filter($field_ids, function($field_id) use ($object_name, $object_field_id_norm){
												return (strpos(($object_name?"$object_name.":'').$object_field_id_norm, $field_id) !== FALSE || strpos($field_id, ($object_name?"$object_name.":'').$object_field_id_norm) !== FALSE);
											});
											$use_field = !empty($matching_fields);
										}
										if(!empty($use_field)){
											$ref_field_str = NULL;
											if(!empty($object_field->type) && $object_field->type == 'ref'){
												$object_field_ref_field = substr($field_id, strlen($object_field_id)+1);
												$ref_field_str = "$$object_field_id$$object_field->table".($object_field_ref_field?".$object_field_ref_field":'');
											}
											else{
												$ref_field_str = $object_table_prefix.$object_field_id;
											}
											if(!empty($ref_field_str) && !in_array($ref_field_str, $result[$object_table_name])){
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
					$table_prefix = (!$structure && empty($ref_field))?$table_name.'.':'';
					$id_fields = array();
					if(!empty($tables[$table_name]['id'])){
						$id_fields[$table_prefix.'id'] = $tables[$table_name]['id'];
					}
					// filter for id ref fields and append the table prefix to the field name
					foreach($tables[$table_name] as $table_field_id => $table_field){
						if(isset($table_field->type) && $table_field->type == 'ref' && isset($table_field->field) && $table_field->field == 'id' && isset($table_field->table) && strpos($table_field->table, $type) === 0){
							$id_fields[$table_prefix.$table_field_id] = $table_field;
						}
					}

					if(!empty($id_fields)){
						if($structure){
							$result[$table_name] = array_merge($id_fields, $result[$table_name]);
						}
						else{
							$result[$table_name] = array_merge(array_keys($id_fields), $result[$table_name]);
						}
						//print "mmmk $table_name ".print_r($result[$table_name], TRUE)."\n";
					}
				}
			}
			print "$type tables:".print_r($result, TRUE)."\n";

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

	public function getFields($type, $mode){
//		print "*** GET FIELDS $type [$mode]\n";
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

			// filter the fields/schema depending on the configuration
			$fields = NULL;
			if($mode == 'input'){
				$fields = $this->getFilteredSchema($type, $config_fields);
			}
			else{
				$fields = $this->getFilteredFields($type, $config_fields, in_array($mode, $this->writeModes));

//				print "$type [$mode] filtered: ".print_r($fields, TRUE)."\n";

				$ref_tables = array();
				$ref_field_tables = array();
				$ref_field_object_tables = array();

				foreach($fields as $table_name => $table_fields){
					$ref_fields = array_filter($table_fields, function($field){
						return ( (is_object($field) && !empty($field->type) && $field->type == 'ref')
								|| (is_string($field) && $field[0] == '$') );
					});

//					print "$type [$mode] [$table_name] refs: ".print_r($ref_fields, TRUE)."\n";
					foreach($ref_fields as $ref_field_def){
						$ref_table = NULL;
						if(is_object($ref_field_def) && !empty($ref_field_def->table)){
							$ref_table = $ref_field_def->table;
						}
						else if(is_string($ref_field_def)){
							$ref_splits = explode('$', $ref_field_def);
							if(count($ref_splits) > 2){
								$ref_field_name = $ref_splits[1];
								$ref_split_field = explode('.', $ref_splits[2], 2);
								$ref_table = $ref_split_field[0];
								$ref_field = (count($ref_split_field) > 1)?$ref_split_field[1]:'';
								$ref_field_object_id = NULL;
								if(!$ref_field){
									$ref_field = $this->getFields($ref_table, $mode);
									// now inject these into the $fields[$table_name]
//									print "got $ref_table [$mode]: ".print_r($ref_field, TRUE)."\n";
								}
								else{
									// get the object tables for the referenced field
									$ref_field_object_id = $ref_table.'_'.str_replace('.', '_', $ref_field);

									print "really ref? $ref_field [$ref_table] ($ref_field_name) {$ref_field_object_id}\n";
									if(!isset($ref_field_object_tables[$ref_field_object_id])){
										if(!isset($ref_field_tables[$ref_table])){
											$ref_field_tables[$ref_table] = $this->getTableDefs($ref_table);
										}
										//print "getObjectTables for $ref_field_object_id \n";
										$ref_field_object_tables[$ref_field_object_id] = $this->getObjectTables($ref_field_object_id, $ref_field_tables[$ref_table]);
									}
									//print "getObjectTables for $ref_field_object_id: (".count($ref_field_object_tables[$ref_field_object_id]).")\n";
									
									
								//	print "   TABLES:".print_r($ref_field_object_tables, TRUE)."\n";
								}

								if($ref_field){
									print "*** ok $ref_field_def\n";
									$ref_join_fields = array();
									if(is_string($ref_field)){
										print "single ref field $ref_field_def\n";
										// a single reference field
										if(!$ref_field_object_id || count($ref_field_object_tables[$ref_field_object_id]) <= 1){
											$this->relations->add($table_name, $ref_field_name, $ref_table);
											$ref_table_alias = $ref_field_name;
											$ref_join_fields[] = "$ref_table_alias.id AS `$ref_field_name.id`";
											$ref_join_fields[] = "$ref_table_alias.$ref_field AS `$ref_field_name.$ref_field`";
										}
										else{
											//$fields[$ref_field_def] = $ref_field_object_tables[$ref_field_object_id];
											// TODO: add in the tables needed for this reference
											print "  load them $ref_field_object_id tables plase:".print_r($ref_field_object_tables[$ref_field_object_id], TRUE)."\n";
										}
										
									}
									else if(is_array($ref_field) && !empty($ref_field[$ref_table])){
										//print "full ref field $ref_field_def [$ref_table]: ".print_r($ref_field, TRUE)."\n";
										// a full reference object
										foreach($ref_field[$ref_table] as $ref_join_field){
											$this->relations->add($table_name, $ref_field_name, $ref_table);
											$ref_table_alias = str_replace("$ref_table.", "$ref_field_name.", $ref_join_field);
											$ref_join_fields[] = "$ref_table_alias AS `$ref_field_name.".str_replace("$ref_table.", '', $ref_join_field)."`";
										}
										// TODO: add in the other tables (as $fields[$ref_field_name.$ref_table_name])
										// TODO: add in the tables needed for this reference
									}
									if(!empty($ref_join_fields)){
										$ref_field_idx = array_search($ref_field_def, $fields[$table_name]);
										array_splice($fields[$table_name], $ref_field_idx, 1, $ref_join_fields);
										$fields[$table_name] = array_unique($fields[$table_name]);
									}
									else{
										print "*** DROP $ref_field_def\n";
										$ref_field_idx = array_search($ref_field_def, $fields[$table_name]);
										array_splice($fields[$table_name], $ref_field_idx, 1);

									}
								}
							}
							
							//$ref_field = $ref_field[2];
							//print_r($ref_splits);
						}
						if($ref_table && $ref_table != $type && strpos($ref_table, $type.'_') === FALSE && !in_array($ref_table, $ref_tables)){
							$ref_tables[] = $ref_table;
						}
					}

					// assert the referenced tables exist (otherwise we've got a problem)
					foreach($ref_tables as $ref_table_name){
						try{
							$this->assert($ref_table_name);
						}
						catch(Exception $e){ throw $e; }
					}
				}
				
				

				/**				$ref_table_names = array_filter(array_keys($fields), function ($table_name){ return ($table_name && $table_name[0] === '$'); });
								$ref_table_names = array_flip($ref_table_names);
								$ref_tables = array_intersect_key($fields, $ref_table_names);
								$fields = array_diff_key($fields, $ref_table_names); // remove ref tables from the fields
								*/

				/**

				foreach($ref_tables as $ref_table_name => $ref_table_fields){
					$ref_field_name = substr($ref_table_name, 1);
					if(is_array($ref_table_fields)){
						$first_field = $ref_table_fields[0];
						$first_field_split = explode('.', $first_field, 2);
						$ref_table_name = (count($first_field_split) > 1)?$first_field_split[0]:$first_field;
					}
					else{
						$ref_table_name = $ref_table_fields;
					}

					// assert the referenced table exists (otherwise we've got a problem)
					try{
					//	$this->assert($ref_table_name);
					}
					catch(Exception $e){ throw $e; }

					// load the fields for the referenced type
					if(in_array($mode, $this->readModes)){
						$ref_fields = $this->getFields($ref_table_name, $mode);

						if(count($ref_fields)){
							if(count($ref_fields[$ref_table_name]) && is_array($ref_table_fields) && !in_array($ref_table_name, $ref_table_fields)){
								// filter the ref_table_fields to only the ones requested
								$ref_fields[$ref_table_name] = array_filter($ref_fields[$ref_table_name], function($ref_field) use ($ref_table_name, $ref_table_fields){
									return ($ref_field == $ref_table_name.'.id' || in_array($ref_field, $ref_table_fields) || in_array(str_replace('_', '.', "$ref_field"), $ref_table_fields) || count(array_filter($ref_table_fields, function($ref_table_field) use ($ref_field){ return (strpos($ref_field, $ref_table_field) === 0); } )));
								});
							}

							// add the referenced fields to the root table
							foreach($ref_fields as $ref_field_table_name => $ref_field_table_fields){
								if($ref_field_table_name == $ref_table_name){
									// root referenced table, append as simple list
									// use the "as `$ref_field_table_field`" to preserve table name-spacing (since they are selected in same query as root $type)
									foreach($ref_field_table_fields as $ref_field_table_field){
										$fields[$type][] = "$ref_field_table_field as `".str_replace($ref_field_table_name.'.', $ref_field_name.'.', $ref_field_table_field)."`";
									}
								}
								else{
									// referenced array table, append the table if it is named in $ref_table_fields
									foreach($ref_table_fields as $ref_field_table_field){
										$ref_field_table_field_name = str_replace('.', '_', $ref_field_table_field);
										if($ref_field_table_field == $ref_table_name || strpos($ref_field_table_name, $ref_field_table_field_name) === 0 || strpos($ref_field_table_name, $ref_table_name.'_'.$ref_field_table_field_name) === 0){
											$fields[$ref_field_table_name] = $ref_field_table_fields;
										}
									}
								}
							}
						}
					}
				}

				*/

			}

		}

		if($fields){
			//print "\n*** FINALLY $type [$mode]: ".print_r($fields, TRUE)."\n";
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