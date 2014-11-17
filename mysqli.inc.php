<?php

define('USE_READ_REPLICA', false);
define('ADMIN_EMAIL', 'support@example.com');

//Master DB config
define('MASTER_HOST', "***********");
define('MASTER_USER', "***********");
define('MASTER_PASS', "***********");
define('MASTER_DB', "***********");

//READ_REPLICA config
define('READ_HOST', "***********");
define('READ_USER', "***********");
define('READ_PASS', "***********");
define('READ_DB', "***********");


if (SEPARATE_READ_DB == true){
	$read_replica = new mysqli(READ_HOST, READ_USER, READ_PASS, READ_DB);
}

if (!$read_replica || $read_replica->connect_errno) {
	$read_replica = db::connect_to_master();
}else{
	$read_replica->set_charset("utf8");
}

class db {
	
	public static function connect_to_master(){
		global $write_master;
	
		if (!$write_master){
			$write_master = new mysqli(MASTER_HOST, MASTER_USER, MASTER_PASS, MASTER_DB);
			if ($write_master->connect_errno) {
				header('Content-type: application/json');
			    json_encode(array(
			    		"msg"=>"Unable to connect to the DemoDrop database, please try again later"
			    		)
			   		);
			    exit();
			}
			$write_master->set_charset("utf8");
		}
		return $write_master;
	}
	
	public static function insert($query){
		$db = self::connect_to_master();

		if ($q = $db->query($query)){
			return $db->insert_id;	
		}else{
			db::error($query, $db);
			return false;
		}
	}
	
	public static function insert_into($table, $fields, $or_update=false){
		return db::insert("INSERT INTO {$table} ".self::implode_for_insert($fields, true, $or_update));
	}

	public static function update($query){
		$db = self::connect_to_master();

		if ($q = $db->query($query)){
			return $db->affected_rows;	
		}else{
			db::error($query, $db);
			return false;
		}
	}

	public static function delete($query){
		$db = self::connect_to_master();

		if ($q = $db->query($query)){
			return $db->affected_rows;	
		}else{
			db::error($query, $db);
			return false;
		}
	}

	public static function select($query){
		global $read_replica;

		if (is_object($query)) $query = self::build($query);

		if ($q = $read_replica->query($query)){
			return $q;	
		}else{
			db::error($query, $read_replica);
			return false;
		}
	}

	public static function check($query){
		$q = db::select($query);
		if ($q->num_rows){
			$r = $q->fetch_array();
			return $r[0];
		}else{
			return false;
		}
	}

	public static function row($query){
		$q = db::select($query);
		if ($q->num_rows){
			return $q->fetch_object();
		}else{
			return false;
		}
	}

	public static function escape($string){
		global $read_replica;
		return $read_replica->real_escape_string($string);
	}

	public static function build($args){
		$args = (object) $args;
		
		if ($args->page < 1) $args->page = 1;

		if (is_array($args->order_by)) 
			$order_by = array_filter($args->order_by);
		
		if (!is_array($args->from)) 	
			$args->from = array($args->from);
			
		if (isset($args->where) && !is_array($args->where)) 	
			$args->where = array($args->where);
			
		if (isset($args->join) && !is_array($args->join)) 	
			$args->join = array($args->join);
			
		if (isset($args->group_by) && !is_array($args->group_by)) 
			$args->group_by = array($args->group_by);
			
		if (isset($args->having) && !is_array($args->having)) 
			$args->having = array($args->having);
		
		if (isset($args->order_by) && !is_array($args->order_by))
			$args->order_by = array($args->order_by);
		
		$query = "SELECT ".
				(($args->calc_found_rows)?"SQL_CALC_FOUND_ROWS ":NULL).
				(($args->distinct)?"DISTINCT ":NULL).
				((count($args->select)) 	? implode(",",$args->select) : "*" ).
				" FROM ".implode(",", $args->from).
				((count($args->join)) 		? " ".implode(" ", $args->join) : NULL ).
				((count($args->where)) 		? " WHERE ".implode(" AND ", $args->where) : " WHERE 1" ).
				((count($args->group_by)) 	? " GROUP BY ".implode(",", $args->group_by) : NULL ).
				((count($args->having)) 	? " HAVING ".implode(" AND ",$args->having) : NULL ).
				((count($args->order_by)) 	? " ORDER BY ".implode(",", $args->order_by) : NULL ).
				((is_numeric($args->limit) && !$args->no_limit) 	? " LIMIT ".(( $args->page * $args->limit ) - $args->limit).",".$args->limit : NULL);
		
		return $query;
	}
	
	public static function error($query, $db){
	
		$title = $db->errno.": ".$db->error;
		$bericht = $title."\n";
		$bericht .= print_r(debug_backtrace(), true);
		$bericht .= "\n----------------------GET--------------------------\n";
		$bericht .= print_r($_GET,1);
		$bericht .= "\n----------------------POST--------------------------\n";
		$bericht .= print_r($_POST,1);
		$bericht .= "\n----------------------SERVER--------------------------\n";
		$bericht .= print_r($_SERVER,1);
		$bericht .= "\n----------------------SESSION--------------------------\n";
		$bericht .= print_r($_SESSION,1);
	
		mail(ADMIN_EMAIL, "DD MYSQL_ERROR: ".$title, $query."\n".$bericht);

		//echo "DATABASE ERROR - THE ERROR HAS BEEN REPORTED. PLEASE TRY AGAIN, OR COME BACK LATER";
		
		$json['msg'] = "DATABASE ERROR - THE ERROR HAS BEEN REPORTED. PLEASE TRY AGAIN, OR COME BACK LATER";
		$json['msg_type'] = "error";
		json_return($json);
		
		exit();
		
	}
	
	public static function implode_for_insert($fields, $strip_tags=true, $or_update=false){
		global $read_replica;
		$fields = (array) $fields;
		foreach($fields as $key => $value){
			if ($strip_tags) $value = strip_tags($value);
			if (is_numeric($value)){
				$fields[$key] = "'".$value."'";
				$query_parts[] = "`".$key."`='".$value."'";
			}elseif ($value == "NOW()" || $value == "NULL" || substr($value, 0, 13) == 'FROM_UNIXTIME'){
				$fields[$key] = $value;
				$query_parts[] = "`".$key."`=".$value;
			}else{
				$_value = $read_replica->real_escape_string($value);
				$fields[$key] = "'".$_value."'";
				$query_parts[] = "`".$key."`='".$_value."'";
			}
		}
		$query = "(`".implode("`,`", array_keys($fields))."`) VALUES(".implode(",", $fields).")";
		if ($or_update){
			if (is_string($or_update)) $query_parts[] = $or_update;//add something like: id=LAST_INSERT_ID(id)
			$query .= ' ON DUPLICATE KEY UPDATE '.implode(",", $query_parts);
		}
		return $query;
	}
}


function json_return($json=false){

	if ($json['redirect']){
		header("location: {$json['redirect']}");
		exit();
	}elseif ($json['msg']){
		header("location: /?msg={$json['msg']}&open_facebox={$json['facebox']}");//&next={$json['next']}
		exit();
	}else{
		header('Content-type: application/json');
		$json['isjson'] = true;
		
		echo json_encode($json, JSON_PRETTY_PRINT);
		exit();
	}
	return $json;
}


