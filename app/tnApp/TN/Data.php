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
			'save' => (!empty($config->save))?(array)$config->save:"*"
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
			if($this->fields[$type][$mode][0] == "*"){
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

			// if we want the field structure
			if($structure && is_array($this->fields[$type][$mode])){
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

			return $this->fields[$type][$mode];
		}
		return NULL;
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
			if($tableSchema = $this->getTableSchema($table)){
				// we can check against $this->connection_type (== 'mysql') for different db providers
				if($sql_cols = $this->sql_column_defs($tableSchema, $table)){
					if(!empty($sql_cols['definition'])){
						$sql = "CREATE TABLE `$table`(".$sql_cols['definition'].")";
						$this->connection->exec($sql);
						if(!empty($sql_cols['relations'])){
							foreach($sql_cols['relations'] as $relTable => $relTable_def){
								$sql = "CREATE TABLE `$relTable`(".$relTable_def.")";
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

	/** MySql specific functions **/
	protected function get_pdo_mysql($config){
		return new PDO('mysql:host='.$config->host.';dbname='.$config->database, $config->username, $config->password, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
	}

}

?>