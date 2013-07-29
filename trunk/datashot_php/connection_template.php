<?php
define("TYPE_PDO",0);
define("TYPE_ORACLE",1);

class DBAbstraction {
    public $type;	
	public $dbo_connection;
	public $oracle_connection;
}

/**
 * Defined database connection for the Lepidoptera PHP web application.
 * 
 * @return false or a DBAbstraction object containing the connection 
 * and type.
 */
function datashot_connect() { 
   $result = new DBAbstraction();
   try { 
   	    // For oracle:
   	    // Set LD_LIBRARY_PATH in environment to instant client 
   	    // Set TNS_ADMIN in environment to path to tnsnames.ora
   	    /*
        $dbh = oci_pconnect("username", "password","connectname"); 
        $result->type = TYPE_ORACLE;
        $result->oracle_connection = $dbh;
        $result->dbo_connection = null;
        */
        // for MariaDB (or any other PDO supported database):
        $dbh = new PDO('mysql:host=localhost;dbname=test', "username", "password", 
                       array(PDO::ATTR_PERSISTENT => true));
        $result->type = TYPE_PDO;
        $result->oracle_connection = null;
        $result->dbo_connection = $dbh;
    } catch (PDOException $e) {
        $result = FALSE;
    }
    return $result;
}

?>
