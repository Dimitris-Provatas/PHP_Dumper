<?php
/*
    MIT License

    Copyright (c) 2020 Dimitrios Provatas

    Permission is hereby granted, free of charge, to any person obtaining a copy
    of this software and associated documentation files (the "Software"), to deal
    in the Software without restriction, including without limitation the rights
    to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
    copies of the Software, and to permit persons to whom the Software is
    furnished to do so, subject to the following conditions:

    The above copyright notice and this permission notice shall be included in all
    copies or substantial portions of the Software.

    THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
    IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
    FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
    AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
    LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
    OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
    SOFTWARE.
*/

// error logging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$user = $_GET["user"];  // get the user
$pass = $_GET["pass"];  // get the password
$host = $_GET["host"];  // get the host

if (!empty($user) && !empty($pass) && !empty($host))    // make sure everything important is provided
{
    $conn = new mysqli($host, $user, $pass);            // make the connection to MySQL

    if ($conn->connect_error)
        die("Connection failed: " . $conn->connect_error);
    echo "<h2>Connection to MySQL Successful!</h2>" . "<br>";

    // Get all databases
    $dbQuery = "SHOW DATABASES;";
    $dbResult = $conn->query($dbQuery);
    $dbNamesArray = [];

    if($conn->error)
        die("Databases querry error: " . $conn->error);

    // Something came back
    if ($dbResult->num_rows > 0)
    {
        // loop through the results
        while($row = $dbResult->fetch_assoc())
        {
            // Ignore the information_schema
            if (strcmp("information_schema", $row['Database']) == 0)
                continue;
            
            // Push the names in an array
            array_push($dbNamesArray, $row['Database']);
        }
    }

    $dbNamesArray = ["octane"];

    // Loop through the databases
    foreach ($dbNamesArray as $db)
    {
        $tableConn = new mysqli($host, $user, $pass, $db);      // make the connection to target database

        $getCharset = "SELECT @@character_set_database;";                           // Charset query
        $getCharsetResult = $tableConn->query($getCharset);                         // perform the query
        $charset = $getCharsetResult->fetch_assoc()["@@character_set_database"];    // Store the charset

        $getCollation = "SELECT @@collation_database;";                                 // Collation query
        $getCollationResult = $tableConn->query($getCharset);                           // perform the query
        $collation = $getCollationResult->fetch_assoc()["@@character_set_database"];    // Store the collation

        $dbFileLocation = $_SERVER['DOCUMENT_ROOT'] . "/PHP_Dump/$db.sql";              // store the file path
        file_put_contents($dbFileLocation, "-- DATABASE: $db\n", FILE_APPEND);          // create the file and append the database name, collation and charset 
        file_put_contents($dbFileLocation, "-- Collation: $collation -- Charset: $charset --\n", FILE_APPEND);
        file_put_contents($dbFileLocation, "\n", FILE_APPEND);                          // empty line for readability
        file_put_contents($dbFileLocation, "SET FOREIGN_KEY_CHECKS=0;\n", FILE_APPEND); // set the foreign key checks to 0 so there will be no import errors
        file_put_contents($dbFileLocation, "\n", FILE_APPEND);                          // empty line for readability

        $tableNames = [];   // start the tables array as empty

        $tablesQuery = "SHOW TABLES;";                      // get all tables from the database
        $tablesResult = $tableConn->query($tablesQuery);    // perform the query

        if($tableConn->error)
            die("Tables querry error: $tableConn->error in database $db");

        // store the tables to an array
        while($row = $tablesResult->fetch_assoc())
        {
            array_push($tableNames, $row['Tables_in_' . $db]);
        }

        // loop through the tables
        foreach ($tableNames as $tableName)
        {
            QueryFields($dbFileLocation, $tableName, $host, $user, $pass, $db, $charset, $collation);
        }

        $tableConn->close();    // close the table connection (memory optimisation)

        file_put_contents($dbFileLocation, "SET FOREIGN_KEY_CHECKS=1;\n", FILE_APPEND); // return the foreign key checks to 1 for future checks
    }

    $conn->close(); // close the connection to MySQL (memory optimisation)

    $path = $_SERVER['DOCUMENT_ROOT'] . "/PHP_Dump";                        // get the output path
    die ("<h4>Done with all Databases!</h4>You can find them at: $path");   // kill the server and inform the user about the path of the files
}
// Not all 3 nevessary arguments were given
else
    die("<h1 style='text-align: center;'>You need to specify a user, a password and a hostname for the script to connect to MySQL.<br>You can do that in the URL by appending parameters:<br>yoururl/dumper.php?user=[your name]&pass=[your pass]&host=[your DB host]</h1>");

// Get field types and create the correct SELECT statement for each table
function QueryFields($dbFileLocation, $tableName, $host, $user, $pass, $db, $charset, $collation)
{
    $fieldConn = new mysqli($host, $user, $pass, $db);  // make a new connection for the fields in each table
    $fields= [];        // start the fields array as empty
    $fieldTypes = [];   // start the field types array as empty
    $fieldNull = [];    // start the field nulls array as empty

    $fieldQuery = "SHOW FIELDS FROM $tableName;";   // get all fields for target table
    $fieldResult = $fieldConn->query($fieldQuery);  // perform the query

    if($fieldConn->error)
        die("Fields querry error: $fieldConn->error in database $db for table $tableName <br> Query: $fieldQuery");

    file_put_contents($dbFileLocation, "-- TABLE: $tableName\n", FILE_APPEND);  // print the table name in the file
    file_put_contents($dbFileLocation, "TRUNCATE TABLE $tableName;\n\n", FILE_APPEND);  // Truncate the table to store the backup data from the begining

    while($row = $fieldResult->fetch_assoc())
    {
        if (strcasecmp($row['Type'], "polygon") == 0 || strcasecmp($row['Type'], "point") == 0 || strcasecmp($row['Type'], "linestring") == 0)
            array_push($fields, "AsText(`" . $row['Field'] . "`)"); // if there is a Geodata field, get it as text
        else
            array_push($fields, "`" . $row['Field'] . "`");         // else just get the field name

        array_push($fieldTypes, $row['Type']);      // store the type of the field
        array_push($fieldNull, $row['Null']);       // store if the field can be null
    }

    QueryRows($dbFileLocation, $host, $user, $pass, $db, $tableName, $fields, $fieldTypes, $fieldNull, $charset, $collation);
    file_put_contents($dbFileLocation, "\n", FILE_APPEND);  // create an empty line after all table contents 

    $fieldConn->close();    // close the connection to the fields (memory optimisation)
}

// Gets every row from table
function QueryRows($dbFileLocation, $host, $user, $pass, $db, $table, $fields, $fieldTypes, $fieldNull, $charset, $collation)
{
    $dataConn = new mysqli($host, $user, $pass, $db);   // make a new connection for the data in each table
    $dataConn->set_charset($charset) or die("FAILED TO SET CHARSET TO $charset FOR DATABASE $db ON TABLE $table!"); // set the charset
    $dataConn->query("SET collation_connection = $collation;"); // set the collation

    $dataQuery = "SELECT ";                     // start the query

    foreach ($fields as $field)
    {
        $dataQuery .= $field . ",";             // append each field
    }

    $dataQuery = substr($dataQuery, 0, -1);     // remove last comma

    $dataQuery .= " FROM " . $table . ";";      // close the query
    $dataResult = $dataConn->query($dataQuery); // execute the query

    if($dataConn->error)
        die("Data querry error: $dataConn->error in database $db for table $table <br> Query: $dataQuery");

    while($row = $dataResult->fetch_assoc())
    {
        $insertQuery = "INSERT IGNORE INTO $table VALUES (";   // start the dump

        $index = 0; // keep track of the field possition
        foreach ($row as $datum)
        {
            if (!is_null($datum) || strcasecmp($fieldNull[$index], 'yes') == 0) // check for data in field or if the field can be null
            {
                if (stripos($fieldTypes[$index], 'text') !== false || stripos($fieldTypes[$index], 'char') !== false)
                    $insertQuery .= "'" . addslashes($datum) . "',";            // String handle
                else if (stripos($fieldTypes[$index], 'enum') !== false || stripos($fieldTypes[$index], 'set') !== false)
                {
                    if (strcmp($datum, "") == 0)
                        $insertQuery .= "DEFAULT,";                             // Enum or set without value handle
                    else
                        $insertQuery .= "'" . addslashes($datum) . "',";        // Enum or set with value handle
                }
                else if (stripos($fieldTypes[$index], 'date') !== false || stripos($fieldTypes[$index], 'time') !== false)
                {
                    if (strpos($datum, "00-00-00") !== false || strpos($datum, "00:00:00" !== false))
                        $insertQuery .= "DEFAULT,";                             // Datetime with default value handle
                    else
                        $insertQuery .= "'" . $datum . "',";                    // Datetime with value handle
                }
                else if (stripos($fieldTypes[$index], 'blob') !== false)
                    $insertQuery .= '0x' . bin2hex($datum) . ",";               // Blobs handle
                else if (strcasecmp($fieldTypes[$index], 'polygon') == 0)
                    $insertQuery .= "ST_POLYFROMTEXT('" . $datum . "'),";       // Polygon handle
                else if (strcasecmp($fieldTypes[$index], 'point') == 0)
                    $insertQuery .= "ST_POINTFROMTEXT('" . $datum . "'),";      // Point handle
                else if (strcasecmp($fieldTypes[$index], 'linestring') == 0)
                    $insertQuery .= "ST_LINESTRINGFROMTEXT('" . $datum . "'),"; // Linestring handle
                else if (!empty($datum))
                    $insertQuery .= $datum . ",";                               // Not empty data handle
                else
                    $insertQuery .= "DEFAULT,";                                 // Empty data handle
            }
            else
                $insertQuery .= "NULL,";                                        // Null handle

            $index = $index + 1;                                                // increment the index of the field possition
        }

        $insertQuery = substr($insertQuery, 0, -1);                             // Remove the last comma
        $insertQuery .= ");\n";                                                 // Close the dump line and add a next line character

        file_put_contents($dbFileLocation, $insertQuery, FILE_APPEND);          // Append the line to the file
    }

    $dataConn->close(); // close the connection to the data (memory optimisation)
}
?>
