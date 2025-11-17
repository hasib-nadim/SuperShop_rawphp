<?php
namespace DB;
function getConnection() {
    if (isset($GLOBALS['dbConnection'])) {
        return $GLOBALS['dbConnection'];
    }
    $driver = env('DB_DRIVER', 'mysqli');
    if ($driver !== 'mysqli') {
        throw new \Exception("Unsupported DB driver: " . $driver);
    }
    $servername = env('DB_HOST', 'localhost');
    $port = env('DB_PORT', 3306);
    $servername .= ':' . $port;
    $username = env('DB_USERNAME', 'username');
    $password = env('DB_PASSWORD', 'password');
    $dbname = env('DB_NAME', 'ecom_db');
    $conn = new \mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    $GLOBALS['dbConnection'] = $conn;
    return $conn;
}


function closeConnection() {
    if (!isset($GLOBALS['dbConnection'])) {
        return;
    }
    $GLOBALS['dbConnection']->close();
}

function syncSchema(){
// run db.sql to sync database schema
    $sql = file_get_contents(__DIR__ . '/db.sql');
    $conn = getConnection();
    if ($conn->multi_query($sql)) {
        do {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        } while ($conn->more_results() && $conn->next_result());
    } 
}

function runSeedSql(){
// run db_seed.sql to seed initial data
    

}
// helper to check existence by field
function exists_by($table, $field, $value) {
    $conn = getConnection();
	$sql = "SELECT COUNT(*) FROM `$table` WHERE `$field` = ? LIMIT 1";
	$stmt = $conn->prepare($sql);
	if (!$stmt) return false;
	$stmt->bind_param('s', $value);
	$stmt->execute();
	$cnt = 0;
	$stmt->bind_result($cnt);
	$stmt->fetch();
	$stmt->close();
	return ($cnt > 0);
}
