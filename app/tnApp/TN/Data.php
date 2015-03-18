<?php

namespace TN;
use PDO, NotORM, SchemaStore, Exception, stdClass, Jsv4;

class DataException extends Exception{}

class Data extends NotORM {
	protected $schemas;
	protected $fields;
	protected $table_exists = array();
	protected $connection_type;

	public function __construct($config) {
		$this->schemas = new SchemaStore();
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
		if(is_array($config->save) && !empty($config->save)){
			// save fields will always be stored as objects, but allow config as an array
			$config->save = array_flip($config->save);
			$config->save = array_fill_keys(array_keys($config->save), TRUE);
		}
		if(is_array($config->input) && !empty($config->input)){
			// input fields will always be stored as objects, but allow config as an array
			$config->input = array_flip($config->input);
			$config->input = array_fill_keys(array_keys($config->input), TRUE);
		}
		$this->fields[$type] = array(
			'input' => (!empty($config->input))?(array)$config->input:"*",
			'list' => (!empty($config->list))?(array)$config->list:"*",
			'load' => (!empty($config->load))?(array)$config->load:"*",
			'save' => (!empty($config->save))?(array)$config->save:"*",
			'ref' => NULL,
			'array' => NULL
		);

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

				
				$ref_fields = $this->getRefFields($type);
				$array_fields = $this->getArrayFields($type);
				$fields = array_filter($fields, function($value)use($ref_fields, $array_fields){
					return (!in_array($value, array_keys($ref_fields)) && !in_array($value, array_keys($array_fields)));
				});

				print "loading $type ".print_r($fields, true)."REF[".print_r($ref_fields,true)."],ARR[".print_r($array_fields,true)."] where ".print_r($args,true)."\n";



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

					// TODO: I think we need to switch back to _ delimeters... NotORM prefers them for FK's

					// resolve array data
					// TODO: what to do about .'s in field and table names
/**					foreach($array_fields as $field_id => $field_table){
						print "what $field_id => $field_table";
						$field_data = $result->{$field_table}(); //->fetchPairs('id');
						if($field_data){
							print "got array data:".print_r($field_data, true)."\n";
						}
					}
					*/

					// TODO: resolve references
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

	public function getFields($type, $mode){
		if(!empty($this->fields[$type][$mode])){
			$as_schema = ($mode=='input');
			$structure = ($as_schema || $mode=='save');
			$force_id = ($mode=='save');

			// if it is a schema and it has already been processed
			if($as_schema && !empty($this->fields[$type][$mode]->type) && $this->fields[$type][$mode]->type == "object"){
				return $this->fields[$type][$mode];
			}

			// handle wildcarding
			if(isset($this->fields[$type][$mode][0]) && $this->fields[$type][$mode][0] == "*"){
				if($schema = $this->getSchema($type)){
					if(!empty($schema->properties)){
						$keys = array_keys((array)$schema->properties);
						if($structure){
							$keys = array_flip($keys);
							$keys = array_fill_keys(array_keys($keys), TRUE);
						}
						$this->fields[$type][$mode] = $keys;
					}
				}
			}

			// if we want the field structures
			if($structure){
				// force id field to be first
				if($force_id && array_keys($this->fields[$type][$mode])[0] != 'id'){
					if(array_key_exists('id', $this->fields[$type][$mode])){
						$id = $this->fields[$type][$mode]['id'];
						unset($this->fields[$type][$mode]['id']);
					}
					else{
						$id = TRUE;
					}
					$fields = array_reverse($this->fields[$type][$mode]);
					$fields['id'] = $id;
					$this->fields[$type][$mode] = array_reverse($fields);
				}

				// load in structures for any fields that are flagged "TRUE"
				$unstructured = array_filter($this->fields[$type][$mode], function($value){ return ($value===TRUE); });
				if(count($unstructured)){
					if($schema = $this->getSchema($type)){
						if(!empty($schema->properties)){
							foreach($unstructured as $field_id => $use_field){
								$schema_field = $this->getSchemaField($schema, $field_id);
								if(!$schema_field && isset($this->fields[$type][$mode][$field_id])){
									// remove field if it is not found in the schema
									unset($this->fields[$type][$mode][$field_id]);
								}
								else{
									$this->fields[$type][$mode][$field_id] = $schema_field;
								}
							}
						}
					}
				}
			}
			else{
				// force id to be first in non-structured field lists
				if($this->fields[$type][$mode][0] != 'id'){
					$id_idx = array_search('id', $this->fields[$type][$mode]);
					if($id_idx !== FALSE){
						unset($this->fields[$type][$mode][$id_idx]);
					}
					array_unshift($this->fields[$type][$mode], 'id');
				}
			}
			if($as_schema){
				$this->fields[$type][$mode] = (object)array(
					"id" => "/$type/$mode",
					"type" => "object",
					"properties" => (object)($this->fields[$type][$mode])
					);
			}
			return $this->fields[$type][$mode];
		}
		return NULL;
	}

	public function getArrayFields($type){
		$mode = 'array';
		if($this->fields[$type][$mode] === NULL){
			$this->fields[$type][$mode] = array();
			if($fields = $this->getFields($type, 'save')){
				$fields = array_filter($fields, function($field){ return (isset($field->type) && $field->type=='array'); });
				$array_fields = array();

				foreach($fields as $field_id => $field){
					$array_fields[$field_id] = "$type.$field_id";
				}

				$this->fields[$type][$mode] = $array_fields;
			}
		}
		return $this->fields[$type][$mode];
	}

	public function getRefFields($type, $keys=FALSE){
		$mode = 'ref';
		if($this->fields[$type][$mode] === NULL){
			$this->fields[$type][$mode] = array();
			if($fields = $this->getFields($type, 'save')){
				$fields = array_filter($fields, function($field){ return isset($field->{'$ref'}); });
				$ref_fields = array();

				foreach($fields as $field_id => $field){
					$ref_path = $field->{'$ref'};
					if($ref_path[0] == '/'){
						$ref_path = substr($ref_path, 1);
					}
					$ref_split = explode('/', $ref_path);
					if(count($ref_split)){
						$ref_fields[$field_id] = array(
							'table' => $ref_split[0],
							'field' => (count($ref_split) > 1)?$ref_split[1]:'id'
							);
					}
				}
				
				$this->fields[$type][$mode] = $ref_fields;
			}
		}
		return $this->fields[$type][$mode];
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
					$last_dot = strrpos($child_path, '.');
					$child_id = substr($child_path, $last_dot+1).($child_id?'.':'')."$child_id";
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
				if($sql_cols = $this->sql_column_defs($fields, $table)){
					if(!empty($sql_cols['definition'])){
						$sql = "CREATE TABLE `$table`(".str_replace(", ", ", \n", $sql_cols['definition']).")";
						//print "SQL:$sql\n\n";
						$this->connection->exec($sql);
						if(!empty($sql_cols['relations'])){
							foreach($sql_cols['relations'] as $relTable => $relTable_def){
								$sql = "CREATE TABLE `$relTable`(".str_replace(", ", ", \n", $relTable_def).")";
								//print "REL_SQL:$sql\n\n";
								$this->connection->exec($sql);
							}
						}
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
		$col_def = '';
		$key_def = NULL;

		/** JSON types:
				array - A JSON array.
				boolean - A JSON boolean.
				integer - A JSON number without a fraction or exponent part.
				number - Any JSON number. Number includes integer.
				null - The JSON null value.
				object - A JSON object.
				string - A JSON string.
		*/

		if($field_id == 'id' && !isset($field->minValue)){
			$field->minValue = 0;
		}

		
		if(isset($field->{'$ref'})){
			// reference fields create a Foreign Key
			$col_def = "`$field_id` INT UNSIGNED";
			$ref_path = $field->{'$ref'};
			if($ref_path[0] == '/'){
				$ref_path = substr($ref_path, 1);
			}
			$ref_split = explode('/', $ref_path);
			if(count($ref_split)){
				$ref_table = $ref_split[0];
				$ref_field = (count($ref_split) > 1)?$ref_split[1]:'id';
				$key_def = "FOREIGN KEY (`$field_id`) REFERENCES `$ref_table`(`$ref_field`) ON DELETE ".(!empty($field->required)?"CASCADE":"SET NULL");
			}
		}
		else if(isset($field->type)){
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
		}

		if($field_id == 'id' && isset($field->type) && $field->type == 'integer'){
			$col_def .= " AUTO_INCREMENT PRIMARY KEY";
		}

		if(isset($field->required) && $field->required){
			$col_def .= " NOT NULL";
		}

		return (($col_def || $key_def)?array('column' => $col_def, 'keys' => $key_def):NULL);
	}

	protected function sql_column_defs($fields, $basename='', $parent=''){
		$sql_cols = "";
		$sql_keys = "";
		$rel_tables = array();
		$prepend_basename = ($basename && substr($basename, -1) == '.');

		foreach($fields as $field_id => $field){
			if(isset($field->type) && $field->type == 'array' && isset($field->items)){
				// array fields turn into a one-to-many table
				$rel_table = "$basename";
				$parent_table = "$basename";

				// all this stuff about $parent_obj and $save_basename feels pretty ghetto, but it works for arrays of objects with arrays and other complex nesting
				if($prepend_basename){
					if($parent){
						$parent_obj = (substr($parent, -1) == '.');
						$save_basename = $basename;

						if(!$parent_obj){
							$basename_split = explode('.', $basename, 3);
							if(count($basename_split) > 2){
								$basename = $basename_split[0].".";
								$basename_trunc = $basename_split[1];
							}
						}
						else{
							$save_parent = $parent;
							$parent = substr($parent, 0, -1);
						}

						$basename = substr($basename, 0, -1);

						$rel_table = "$parent.$basename";;
						$parent_table = "$parent";

						if(!empty($basename_trunc)){
							$rel_table .= ".$basename_trunc";
						}

						if(!$parent_obj){
							$parent_table .= ".$basename";
						}
					}
				}

				$rel_fields = array();
				$rel_fields['id'] = (object)array( 'type' => 'integer', 'minValue' => 0 );
				$rel_fields["$parent_table.id"] = (object)array('$ref' => "/$parent_table/id", "required" => TRUE); // setting required makes the ON DELETE CASCADE (rather than SET NULL)
				$rel_fields[$field_id] = $field->items;

				$rel_cols = $this->sql_column_defs($rel_fields, $basename, $parent.".".(isset($save_basename)?"$basename".(!empty($basename_trunc)?".$basename_trunc":""):''));

				if(!empty($rel_cols['definition'])){
					$rel_tables["$rel_table.$field_id"] = $rel_cols['definition'];
					if(!empty($rel_cols['relations'])){
						$rel_tables = array_merge($rel_tables, $rel_cols['relations']);
					}
				}

				if(!empty($save_basename)){
					$basename = $save_basename;
				}
				if(!empty($save_parent)){
					$parent = $save_parent;
				}
			}
			else if(isset($field->type) && $field->type == 'object' && isset($field->properties)){
				// object fields recursively flatten into multiple-columns
				$object_cols = $this->sql_column_defs($field->properties, ($prepend_basename?"$basename":"")."$field_id.", ($parent)?(($parent == ".")?"$basename":"$parent"):"$basename.");

				if(!empty($object_cols['definition'])){
					$sql_cols .= ($sql_cols?", ":"").$object_cols['definition'];
				}
				if(!empty($object_cols['relations'])){
					$rel_tables = array_merge($rel_tables, $object_cols['relations']);
				}
			}
			else if($col_def = $this->sql_column_def($prepend_basename?"$basename$field_id":$field_id, $field)){
				// non-array fields can be generated by sql_column_def
				if(!empty($col_def['column'])){
					$sql_cols .= ($sql_cols?", ":"").$col_def['column'];
				}
				if(!empty($col_def['keys'])){
					$sql_keys .= ($sql_keys?", ":"").$col_def['keys'];
				}
			}
		}

		// add any key constraints to the end of the column definition list
		if($sql_keys){
			$sql_cols .= ($sql_cols?", ":"").$sql_keys;
		}
		return array('definition' => $sql_cols, 'relations' => $rel_tables);
	}

	/** MySql specific functions **/
	protected function get_pdo_mysql($config){
		return new PDO('mysql:host='.$config->host.';dbname='.$config->database, $config->username, $config->password, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
	}

}

?>