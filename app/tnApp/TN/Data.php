<?php
namespace TN;
use PDO, NotORM, SchemaStore, Exception, stdClass;

class DataException extends Exception{}

class Data extends NotORM {
	protected $schemas;
	protected $table_fields = array();
	protected $table_exists = array();
	protected $connection_type;

	public function __construct($config) {
		$this->schemas = new SchemaStore();

		$connection = NULL;
		foreach($config as $type => $settings){
			if($type == 'mysql'){
				$connection = $this->get_pdo_mysql($settings);
				$this->connection_type = $type;
			}
		}

		if($connection){
			parent::__construct($connection);
		}
	}

	public function addTableFields($table, $fields){
		if(empty($table)){ return; }
		$this->table_fields[$table] = $fields;
	}
	public function getTableFields($table){
		if(empty($table)){ return NULL; }
		return (isset($this->table_fields[$table])?$this->table_fields[$table]:NULL);
	}

	public function addSchema($schema_id, $schema){
		if(empty($schema_id)){ return; }
		if($schema_id[0] != '/'){ $schema_id = '/'.$schema_id; }
		$this->schemas->add($schema_id, $schema);
	}

	public function getSchema($schema_id){
		if(empty($schema_id)){ return NULL; }
		if($schema_id[0] != '/'){ $schema_id = '/'.$schema_id; }
		return $this->schemas->get($schema_id);
	}

	public function rowToArray($row){
		$array = array();
		foreach ($row as $column => $data){
			$array[$column] = $data;
		}
		return $array;
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

	public function exists($table){
		try {
			$result = $this->connection->query("SELECT 1 FROM $table LIMIT 1");
		} catch (Exception $e) {
			return FALSE;
		}
		return ($result !== FALSE);
	}

	public function create($table){
		try {
			$fields = $this->getTableFields($table);
			$schema = $this->getSchema($table);
			if($fields && $schema){
				// we could only create tables for objects
				if($schema->type == 'object' && !empty($schema->properties)){
					$flat_schema = $this->flatten_schema($schema);

					// we can check against $this->connection_type (== 'mysql') for different db providers
					$sql_cols = $this->schema_column_defs($fields, $flat_schema, $table);
					if($sql_cols){
						if(!empty($sql_cols['definition'])){
							$sql = "CREATE TABLE `$table`(".$sql_cols['definition'].")";
							$this->connection->exec($sql);
							if(!empty($sql_cols['relations'])){
								foreach($sql_cols['relations'] as $relTable_sql){
									$this->connection->exec($relTable_sql);
								}
							}
							return TRUE;
						}
					}
				}
			}
		} catch (Exception $e) {
			throw $e;
		}
		
		throw new DataException("Could not create table: $table");
		return FALSE;
	}

	protected function get_pdo_mysql($config){
		return new PDO('mysql:host='.$config->host.';dbname='.$config->database, $config->username, $config->password, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
	}

	private function flatten_schema($schema, $basename=''){
		$result = new stdClass();
		if($schema && isset($schema->type) && $schema->type == 'object'){
			if(!empty($schema->properties)){
				foreach($schema->properties as $field_id => $field){
					if(isset($field->type) && $field->type == 'object'){
						$result = (object)array_merge((array)$result, (array)$this->flatten_schema($field, ($basename?$basename.'_':'').$field_id));
					}
					else{
						$result->{($basename?$basename.'_':'').$field_id} = $field;
					}
				}
			}
		}
		else if($schema && (isset($schema->{'$ref'}) || (isset($schema->type) && $schema->type != 'object') ) ){
			$result->{($basename?$basename:'value')} = $schema;
		}
		return $result;
	}

	private function schema_column_def($field_id, $field){
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
			$col_def = "`$field_id` INT UNSIGNED";
			$ref_path = $field->{'$ref'};
			if($ref_path[0] == '/'){
				$ref_path = substr($ref_path, 1);
			}
			$ref_split = explode('/', $ref_path);
			if(count($ref_split)){
				$ref_table = $ref_split[0];
				$key_def = "FOREIGN KEY (`$field_id`) REFERENCES `$ref_table`(`id`) ON DELETE SET NULL";
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

	private function schema_column_defs($fields, $schema, $basename=''){
		$sql_cols = "";
		$sql_keys = "";
		$rel_tables = array();
		if($fields === '*'){
			$fields = new stdClass;
			foreach($schema as $field_id => $field_def){
				$fields->{$field_id} = TRUE;
			}
		}

		// force an id field
		if(empty($fields->id)){
			$fields->id = TRUE;
		}
		if(!isset($schema->id)){
			$schema->id = (object)array( 'type' => 'integer' );
		}

		// make the id column first
		if(!empty($fields->id) && isset($schema->id)){
			$col_def = $this->schema_column_def('id', $schema->id);
			if(isset($col_def['column']) && !empty($col_def['column'])){
				$sql_cols .= ($sql_cols?", ":"").$col_def['column'];
			}
			if(isset($col_def['keys']) && !empty($col_def['keys'])){
				$sql_keys .= ($sql_keys?", ":"").$col_def['keys'];
			}
		}

		//foreach($schema as $field_id => $field){
		foreach($fields as $field_id => $use_field){
			if($field_id == 'id' || empty($use_field) || !isset($schema->{$field_id})){
				continue;
			}
			$field = $schema->{$field_id};


			$col_def = $this->schema_column_def($field_id, $field);
			if($col_def){
				if(isset($col_def['column']) && !empty($col_def['column'])){
					$sql_cols .= ($sql_cols?", ":"").$col_def['column'];
				}
				if(isset($col_def['keys']) && !empty($col_def['keys'])){
					$sql_keys .= ($sql_keys?", ":"").$col_def['keys'];
				}
				//$sql_cols .= ($sql_cols?", ":"").$col_def;
			}
			else if(isset($field->type) && $field->type == 'array' && isset($field->items)){
				// create a one-to-many table
				$relTable = ($basename?$basename.'_':'').$field_id;
				$relTable_flat_schema = $this->flatten_schema($field->items, $field_id);

				$rel_props = new stdClass();
				$rel_props->id = (object)array( 'type' => 'integer' );
				$rel_props->{$basename.'_id'} = (object)array( 'type' => 'integer', 'minValue' => 0 );

				$relTable_schema = (object)array_merge((array)$rel_props, (array)$relTable_flat_schema);

				// TODO: $this->connection_type for other db providers
				$relTable_cols = $this->schema_column_defs('*', $relTable_schema, ($basename?$basename.'_':'').$field_id);
				if(!empty($relTable_cols['definition'])){
					$relTable_sql = "CREATE TABLE `$relTable`(".$relTable_cols['definition'].", FOREIGN KEY (`".$basename."_id`) REFERENCES `$basename`(`id`) ON DELETE CASCADE)";
				}
				$rel_tables[] = $relTable_sql;
			}
		}
		if($sql_keys){
			$sql_cols .= ($sql_cols?", ":"").$sql_keys;
		}
		return array('definition' => $sql_cols, 'relations' => $rel_tables);
	}
}
?>