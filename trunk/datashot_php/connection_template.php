<?php

function datashot_connect() { 
   try { 
   	    // For oracle:
   	    // Set LD_LIBRARY_PATH in environment to instant client 
   	    // Set TNS_ADMIN in environment to path to tnsnames.ora
   	    /*
        $dbh = new PDO('oci:dbname=test', "username", "password", 
                       array(PDO::ATTR_PERSISTENT => true));
        */
        // for MariaDB:
        $dbh = new PDO('mysql:host=localhost;dbname=test', "username", "password", 
                       array(PDO::ATTR_PERSISTENT => true));
    } catch (PDOException $e) {
        $dbh = FALSE;
    }
    return $dbh;
}

?>
