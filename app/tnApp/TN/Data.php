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
		if($schema){
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
		$this->fields[$type] = array(
			'list' => (!empty($config->list))?(array)$config->list:"*",
			'load' => (!empty($config->load))?(array)$config->load:"*",
			'save' => (!empty($config->save))?(array)$config->save:"*",
			'refs' => NULL
		);


/**
		$listFields = $this->getFields($type, 'list');
		$loadFields = $this->getFields($type, 'load');
		$saveFields = $this->getFields($type, 'save');

		print "$type:list=><pre>".print_r($listFields,true)."</pre>\n\n";
		print "$type:load=><pre>".print_r($loadFields,true)."</pre>\n\n";
		print "$type:save=><pre>".print_r($saveFields,true)."</pre>\n\n";
		*/
	}

	public function getFields($type, $mode){
		if(!empty($this->fields[$type][$mode])){
			$structure = ($mode=='save');

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
				if(array_keys($this->fields[$type][$mode])[0] != 'id'){
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
								$this->fields[$type][$mode][$field_id] = isset($schema->properties->{$field_id})?$schema->properties->{$field_id}:NULL;
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
			return $this->fields[$type][$mode];
		}
		return NULL;
	}

	public function getRefFields($type){
		$mode = 'refs';
		if($this->fields[$type][$mode] === NULL){
			$this->fields[$type][$mode] = array();
			if($fields = $this->getFields($type, 'save')){
				$refFields = array_filter($fields, function($field){ return isset($field->{'$ref'}); });
				$this->fields[$type][$mode] = array_keys($refFields);
			}
		}
		return $this->fields[$type][$mode];
	}

	public function getSchema($type){
		if(empty($type)){ return NULL; }
		if($type[0] != '/'){ $type = '/'.$type; }
		return $this->schemas->get($type);
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

	public function getTableSchema($table){
		return NULL;
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
				//print "create $table with fields:<pre>".print_r($fields,TRUE)."</pre>";
				print "\n";
				
				// we can check against $this->connection_type (== 'mysql') for different db providers
				if($sql_cols = $this->sql_column_defs($fields, $table)){
					if(!empty($sql_cols['definition'])){
						$sql = "CREATE TABLE `$table`(".str_replace(", ", ", \n", $sql_cols['definition']).")";
						//$this->connection->exec($sql);
						print "SQL:$sql\n\n";
						if(!empty($sql_cols['relations'])){
							foreach($sql_cols['relations'] as $relTable => $relTable_def){
								$sql = "CREATE TABLE `$relTable`(".str_replace(", ", ", \n", $relTable_def).")";
								//$this->connection->exec($sql);
								print "REL_SQL:$sql\n\n";
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
			// TODO: to handle FK refs on fields besides id, we could retrieve the type from the $ref'ed schema
			$col_def = "`$field_id` INT UNSIGNED";
			$ref_path = $field->{'$ref'};
			if($ref_path[0] == '/'){
				$ref_path = substr($ref_path, 1);
			}
			$ref_split = explode('/', $ref_path);
			if(count($ref_split)){
				$ref_table = $ref_split[0];
				$key_def = "FOREIGN KEY (`$field_id`) REFERENCES `$ref_table`(`id`) ON DELETE ".(!empty($field->required)?"CASCASE":"SET NULL");
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
						$basename = substr($basename, 0, -1);

						if($parent_obj){
							$save_parent = $parent;
							$parent = substr($parent, 0, -1);
						}

						$rel_table = "$parent.$basename";;
						$parent_table = "$parent";

						if(!$parent_obj){
							$parent_table .= ".$basename";
						}
					}
				}

				$rel_fields = array();
				$rel_fields['id'] = (object)array( 'type' => 'integer', 'minValue' => 0 );
				$rel_fields["$parent_table.id"] = (object)array('$ref' => "/$parent_table/id", "required" => TRUE); // setting required makes the ON DELETE CASCADE (rather than SET NULL)
				$rel_fields[$field_id] = $field->items;

				$rel_cols = $this->sql_column_defs($rel_fields, $basename, $parent.".".(isset($save_basename)?"$basename":''));

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
				$object_cols = $this->sql_column_defs($field->properties, ($prepend_basename?"$basename$field_id":$field_id).".", $parent?"$parent":"$basename."); //"$field_id.", $basename);

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