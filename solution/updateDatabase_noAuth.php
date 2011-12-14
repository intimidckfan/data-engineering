<html>
<body>
<form enctype="multipart/form-data" action="updateDatabase.php" method="POST">
<input type="hidden" name="MAX_FILE_SIZE" value="100000" />
Choose a file to upload: <input name="uploadedfile" type="file" /><br />
<input type="submit" value="Upload File" />
</form>
</body>
</html>
<?php
	$hostname = '127.0.0.1';
	$database = 'livingsocial';
	//IMPORTANT: $username and $password MUST HAVE creation, insertion, & selection privileges in MySQL
	$username = 'root';
	$password = 'tfvujn';
	
	//create database if not created already, and return db handle
	$db_link = create_db($hostname, $username, $password, $database);	

	//attain handle to file accepted by form in above html code
	$handle = @fopen($_FILES['uploadedfile']['tmp_name'],"r");
	//store beginning purchase id to calculate revnue with
	$isFirstLine=0;
	//begin parsing file 	
	if ($handle) {
		//check first line header
		if (($buffer = fgets($handle, 4096)) !== false) {
			//first line assumed to be valid header, if it wasn't, add code to parse/validate header
		} 
		
		//for every line after first, split by tab 
		while (($buffer = fgets($handle, 4096)) !== false) {
			$fields = explode ("\t", $buffer);

			//initialize table values for insertion
			$purchaserName = addslashes(trim($fields[0]));
			$itemDesc = addslashes(trim($fields[1]));
			$itemPrice = addslashes(trim($fields[2]));
			$purchaseCount= addslashes(trim($fields[3]));
			$merchAddress = addslashes(trim($fields[4]));
			$merchName = addslashes(trim($fields[5]));

			//insert into purchases table, & items/merchants tables if entry does not exist
			db_execute("INSERT INTO `purchases` VALUES ('','$purchaserName','$purchaseCount')");
			$purchaseId = mysql_insert_id($db_link); 
			if ($isFirstLine==0){
				$firstId = $purchaseId;
				$isFirstLine=1;
			}
			//insert into items & merchants tables if entry does not exist
			db_execute("INSERT IGNORE INTO `items` VALUES('','$itemDesc', '$itemPrice')"); 
			db_execute("INSERT IGNORE INTO `merchants` VALUES ('','$merchName', '$merchAddress')");

			//get primary keys of item and merchants
			$itemRow = mysql_fetch_assoc(db_execute("SELECT * FROM items WHERE item_description='$itemDesc'"));
			$itemId = $itemRow['item_id'];
			$merchRow = mysql_fetch_assoc(db_execute("SELECT * FROM items WHERE item_description='$merchName'"));
			$merchId = $merchRow['merchant_id'];
			
			//update merchant_item, & purchase_item tables if new item
			if ($itemId>0 and $merchId>0) {
				db_execute("INSERT IGNORE INTO item_merchant VALUES($itemId, $merchId)");
			}  
			if ($purchaseId>0 and $itemId>0) {
				db_execute("INSERT INTO purchase_item VALUES($purchaseId, $itemId)");
			}

		}	
		
		//get revenue by adding total of all purchases
		$revenue_query = db_execute("SELECT SUM(purchases.purchase_count * items.item_price) FROM purchases, items, purchase_item WHERE purchases.purchase_id BETWEEN $firstId AND $purchaseId AND purchases.purchase_id=purchase_item.purchase_id AND items.item_id=purchase_item.item_id");
		$revenue = mysql_fetch_row($revenue_query);
		echo "Revenue : $revenue[0]\n";    
		
		if (!feof($handle)) {
			echo "Error: unexpected fgets() fail\n";
	    	}
		fclose($handle);
		mysql_close($link);
	}

function db_execute($query) {
	//execute mysql query, or die with error
	$result = mysql_query($query);
	if (!$result) {
		die("Query \"$query\" failed: ".mysql_error()."\n");
	}
	return $result;
}

function db_connect ($hostname, $username, $password, $database) {
	//connect to mysql, or die with error
	

	//connect mysql database, or die with error
	$db_selected = mysql_select_db($database,$link);
	if (!$db_selected) {
		die ("Can't use database $database : ".mysql_error()."\n");
	}
	return $link;
}

function create_db ($hostname, $username, $password, $database) {
	$link = mysql_connect($hostname,$username,$password);
	if (!$link) {
		die ('Could not connect '.mysql_error()."\n");
	} 

	db_execute("create database if not exists $database");
	
	$db_selected = mysql_select_db($database,$link);
	if (!$db_selected) {
		die ("Can't use database $database : ".mysql_error()."\n");
	}

	//grant privileges
	db_execute("grant all privileges on $database.* to $username@localhost identified by '$password'");
	//create table purchases
	db_execute("create table if not exists `purchases` (`purchase_id` int(10) not null auto_increment, `purchaser` text not null, `purchase_count` int(10) not null, primary key (`purchase_id`))");
	//create table items
	db_execute("create table if not exists `items` (item_id int(10) not null auto_increment, `item_description` varchar(50) not null unique, `item_price` decimal(10,2) not null, primary key (`item_id`))");
	//create table merchants
	db_execute("create table if not exists `merchants` (merchant_id int(10) not null auto_increment, `merchant_name` varchar(50) not null unique, `merchant_address` text not null, primary key (`merchant_id`))");
	//create table item_merchant
	db_execute("create table if not exists `item_merchant` (item_id int(10) not null, merchant_id int(10) not null)");
	//create table purchase_item
	db_execute("create table if not exists`purchase_item` (purchase_id int(10) not null, item_id int(10) not null)");
	return $link;
}
	
?>
