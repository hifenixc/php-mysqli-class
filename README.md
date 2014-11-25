php-mysqli-class
================

**select a single row**

	$i = db::row("SELECT * FROM table WHERE id=1");
	echo $i->field;


**select multiple rows**

	$q = db::select("SELECT * FROM table WHERE id=1");
	while($r = $q->fetch_object()){
		echo $r->field;
	}

**get only 1 field/value from a table**

	$total = db::check("SELECT COUNT(*) FROM table WHERE id=1");
	echo $total;


**handy insert query**

	$fields = array(
			"first_name"=>"John",
			"last_name"=>"Doe",
			"city"=>"Amsterdam's",
			"age"=>32,
			"date_added"=>"NOW()"
		);
	$insert_id = db::insert_into("table_name", $fields);
	echo $insert_id;


**basic update query**

	$affected = db::update("UPDATE table SET field='value' WHERE id=1");
	echo $affected;


**handy update query**

	$fields = array(
			"first_name"=>"John",
			"last_name"=>"Doe",
			"city"=>"Amsterdam's",
			"age"=>32,
			"date_updated"=>"NOW()"
		);
	$affected = db::update_table("table_name", $fields, "id=1");
	echo $affected;
