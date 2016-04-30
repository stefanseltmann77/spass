<?php

namespace spass\database;

require('spass_dbhandle.php');

use spass\database\SPAppDbHandle;
use \mysqli;
use ArrayObject;
use ReflectionMethod;

/**
 * High-Level Wrapper for communication with Mysql-Servers.
 * @author Stefan Seltmann
 */
class SPAppDbHandleMySQL extends SPAppDbHandle{

    /**
     * @param string $host
     * @param string $user
     * @param string $passwd
     * @param string $db
     *
     * TODO: DIE durch execptioens verändern.
     */
    function __construct($host, $user, $passwd, $db){
        $this->conn = new mysqli($host, $user, $passwd, $db);
        if($this->conn->connect_errno!==0){
            if($this->conn->connect_errno===2002){
                die("No connection to $host established!");
            }elseif ($this->conn->connect_errno===1045) {
                die("Access denined $host for $user!");
            }else{
                die("Connection Failed");
            }
        }
        $this->conn->set_charset('utf8');
    }

    /**
     * @param string $sqlText
     * @param $queryType
     * @param array $params
     * @return array|bool|int
     */
    function commitQuery($sqlText, $queryType, array &$params=Null){
        if ($this->echoSql){
            echo "<br />".$sqlText;
        }
        if($this->usePreparedStatements==TRUE and $params){
            $stmt = $this->conn->prepare($sqlText);
            if($stmt){
                $method = new ReflectionMethod('mysqli_stmt', 'bind_param');
                $refs = [];
                foreach($params as $key=>$value){
                    $refs[$key] = &$params[$key];
                }
                $method->invokeArgs($stmt, $refs);
                $stmt->execute();
                $sqlResource = $stmt->get_result();
            }else{
                $sqlResource = Null;
            }
        }else{
            $sqlResource = $this->conn->query($sqlText);
        }
        if($sqlResource){
            $warningCount = $this->conn->warning_count;
            if(self::QT_WORESULT === $queryType){//filtern auf insertTypen oder unklare YYY.
                $tempResource = $this->conn->query("SELECT LAST_INSERT_ID() ");
                $tempRow = $tempResource->fetch_row(); //YYYzeile einsparen?
                $this->lastInsertId = $tempRow[0];
                $result = FALSE;
            }elseif(self::QT_UPDATE === $queryType){
                $result = $this->conn->affected_rows;
                $this->lastInsertId = NULL;
            }else{
                $this->lastInsertId = NULL;
                if (self::QT_MULTIROW===$queryType){
                    $result = array();
                    while ($row = $sqlResource->fetch_array(MYSQLI_ASSOC)){
                        $result[] = $row;
                    }
                } elseif (self::QT_SINGLEROW===$queryType){
                    $result = $sqlResource->fetch_assoc();
                } elseif (self::QT_MULTIROW2COL===$queryType){
                    $result = array();
                    while ($row = $sqlResource->fetch_array(MYSQLI_NUM)){
                        $result[$row[0]] = $row[1];
                    }
                } elseif (self::QT_SINGLEVALUE===$queryType){
                    $row = $sqlResource->fetch_row();
                    $result = $row[0];
                } elseif (self::QT_MULTIROW1COL===$queryType){ //XXX FIXME vorkommen checken. rueckgabe wurde geandert.
                    $result = array();
                    while ($row = $sqlResource->fetch_array(MYSQLI_NUM)){
                        $result[] = $row[0];
                    }
                }
            }
            if ($warningCount or FALSE) { //XXXX unsauber
                $warnings =  $this->conn->query("SHOW WARNINGS");
                while ($row = $warnings->fetch_array(MYSQLI_ASSOC)){
                    $this->conn->query("INSERT INTO mysql.query_logs (query, log, type,errno) VALUES ('".addslashes($sqlText)."', '".addslashes($row['Message'])."', 'warning', '".$row['Code']."') ");
                }
            }
            if($sqlResource !== TRUE ){
                $sqlResource->free();
            }
            return $result;
        }else{
            if ($this->echoError){
                $backtrace = debug_backtrace();
                foreach($backtrace as $row){
                    echo $row['line']." in ".$row['function']."\tin\t".$row['file']."<BR />";
                }
                echo "<br /><br /><span style=\"color:red\">$sqlText<br />{$this->conn->errno}: {$this->conn->error}</span><br /><br />"; // YYY auf exception ändern
                die();
            }else{
                echo "<br /><br /><span style=\"color:red\">$sqlText<br />{$this->conn->errno}: {$this->conn->error}</span><br /><br />";
                return FALSE;
            }
            $this->conn->query("INSERT INTO mysql.query_logs (query, log, type,errno)VALUES ('".addslashes($sqlText)."', '".addslashes($this->conn->error)."', 'error', '".$this->conn->errno."') ");
        }
    }

    /**
     * @param string $table
     * @param string $schema
     * TODO: parameterize
     * @return bool
     */
    function tableExists($table, $schema = null)
    {
        if(strpos($table, '.')>0){
            list($schema, $table) = explode('.', $table);
        }
        $result = $this->queryValue("
              SELECT count(*)
              FROM information_schema.`TABLES`
              WHERE lower(table_name) = lower('$table') and lower(table_schema) = lower('$schema')");
        return $result == 1; //
    }

    /**
     * Lists the column names for a given table in form of a array
     *
     * @param string $table
     * @return array
     * @deprecated
     * @todo   TODO add schema;
     */
    function queryTableFields($table)
    {
        trigger_error(E_DEPRECATED); // replace with queryTableColumnNames
    }

    function queryTableNames($schema=null)
    {
        return $this->querySchemaTableNames($schema);
    }

    /**
     * List the table names of a given schema
     *
     * @param string $schema
     * @return array
     *
     * TODO: Split für views
     * FIXME: Parametrisieren
     */
    function querySchemaTableNames($schema)
    {
        return $this->queryList("SELECT table_name FROM information_schema.tables WHERE table_schema = '$schema'");
    }


    /**
     * Lists the column names for a given table in form of a array
     *
     * @param string $table
     * @param string $schema
     * @return array
     */
    function queryTableColumnNames($table, $schema = null)
    {
        $table_array = explode('.',$table);
        $table = array_pop($table_array);
        $schema = ($schema)? $schema:((count($table_array)===1)? array_pop($table_array):$schema);// todo XXX current
        $result = $this->queryList("SELECT column_name FROM information_schema.columns WHERE TABLE_NAME = '$table' and TABLE_SCHEMA = '$schema'");
        return $result;
    }

    /**
     *
     * TODO Set default schema
     * TODO use param
     *
     * @param string $table
     * @param string $schema
     * @return array
     */
    function queryTableColumns($table, $schema = null)
    {
        $newtableDescr = new ArrayObject(['COLUMNS'=>[],'INDEXES'=>[]], ArrayObject::ARRAY_AS_PROPS);
        $table_array = explode('.',$table);
        $table = array_pop($table_array);
        $schema = ($schema)?$schema:((count($table_array)===2)?$table_array[1]:$schema);// todo XXX current
        $tableDescr = $this->queryResult("SELECT * FROM information_schema.columns WHERE TABLE_NAME = '$table' and TABLE_SCHEMA = '$schema'");
        foreach($tableDescr as $column){
            if($column['COLUMN_KEY'] === 'PRI'){
                $newtableDescr->INDEXES['primary'] = $column['COLUMN_NAME'];
            }
            if($column['DATA_TYPE'] === 'longtext' or $column['DATA_TYPE'] === 'mediumtext' or $column['DATA_TYPE'] === 'text'){
                $column['COLUMN_FORMAT'] = 'text';
            }elseif($column['DATA_TYPE'] === 'tinyint' or $column['DATA_TYPE'] === 'mediumint' or $column['DATA_TYPE'] === 'smallint'){
                $column['COLUMN_FORMAT'] = 'numeric';
            }elseif($column['DATA_TYPE'] === 'varchar'){
                $column['COLUMN_FORMAT'] = 'varchar';
            }elseif($column['DATA_TYPE'] === 'enum'){
                $fieldlist = array();
                foreach(explode(',', str_replace("'", '', substr($column['COLUMN_TYPE'], 5, -1))) as $element){
                    $fieldlist[$element] = $element;
                }
                $column['COLUMN_FORMAT'] = 'enum';
                $column['FieldList'] = $fieldlist;
            }else{
                $column['COLUMN_FORMAT'] = $column['DATA_TYPE'];
            }
            $newtableDescr->COLUMNS[$column['COLUMN_NAME']] = $column;
        }
        return $newtableDescr;
    }

    /**
     * Returns the string for a insert query based on a value array and a target table
     *
     * @param string $insertTable
     * @param array $valueArray an associative array with keys as column names and values as column contents
     * @return string
     */
    function buildInsertQuery($insertTable, array $valueArray)  //TODO use params!!
    {
        $valueArray = array_map(array($this->conn, "real_escape_string"),$valueArray);
        return "INSERT INTO $insertTable(".implode(",", array_keys($valueArray)).") VALUES ('".implode("','", $valueArray)."')";
    }

    /**
     * @param $insertTable
     * @param array $valueArray an associative array with keys as column names and values as column contents
     * @param bool $mode
     * @return bool
     */
    function insertQuery($insertTable, array $valueArray, $mode = self::C_ALLOW_DUPLICATES)
    {
        $valueArray = array_map(array($this->conn, "real_escape_string"), $valueArray);
        $this->query($this->buildInsertQuery($insertTable, $valueArray)); // FIXE with params
    }


    /**
     * Inserts the valueArray into a given insertTable using the array keys as field names.
     *
     * By default the $mode is set to allow duplicates. When choosing C_IGNORE_DUPLICATES, the row
     * will only be inserted if the $valueArray differs in at least one field from already existing rows.
     * @param $tableName
     * @param array $valueArray
     * @param bool $mode
     * @return mixed  TODO XXX unklar
     */
    function queryInsert($tableName, array $valueArray, $mode = self::C_ALLOW_DUPLICATES)
    {
        if(self::C_ALLOW_DUPLICATES===$mode){
            $this->query($this->buildInsertQuery($tableName, $valueArray));
        }elseif(self::C_IGNORE_DUPLICATES===$mode){
            $selectString = "SELECT count(*) FROM $tableName WHERE 1 ";
            foreach($valueArray as $var=>$value){
                $selectString .= ($value === NULL) ? " AND ".$var." IS NULL ":" AND ".$var." = '".$this->conn->real_escape_string($value)."'";
            }
            $count = $this->queryValue($selectString); //Pruefung auf bestehende Faelle mit gleichem Inhalt
            if ($count ==="0"){
                $this->query($this->buildInsertQuery($tableName, $valueArray));
            }
        }else{
            trigger_error("Wrong parameter for insert mode. Please choose 'C_ALLOW_DUPLICATES' or 'C_IGNORE_DUPLICATES' as third parameter!", E_USER_ERROR);
        }

        // TODO: Implement queryInsert() method.
    }

    /**
     * @param $tableName
     * @param array $valueArray
     * @param $condition
     * @return mixed
     */
    function queryUpdate($tableName, array $valueArray, $condition)
    {
        $updateString = [];
        foreach($valueArray as $key=>$value){
            $updateString[] = $key."='$value'";
        }
        $this->query("UPDATE $tableName SET ".implode(',', $updateString)." WHERE $condition ");
        // TODO: Implement queryUpdate() method.
    }

    /**
     * @param $tableName
     * @return mixed
     */
    function queryPrimaryKey($tableName)
    {
        list($schemaName, $tableName) = explode('.', $tableName);
        $primaryKey = $this->queryValue("
            SELECT column_name
            FROM information_schema.KEY_COLUMN_USAGE u
            WHERE CONSTRAINT_NAME = 'PRIMARY'
                and TABLE_NAME  = '$tableName'
                and TABLE_SCHEMA = '$schemaName'");
        return $primaryKey;
    }

    /**
     * @param $tableName
     * @return mixed
     */
    function queryTableDescription($tableName)
    {
        // TODO: Implement queryTableDescription() method.
    }

    /**
     * Closes the currently open connection to the database.
     *
     * By default all pending transactions will not be committed.
     * @param bool $commit_on_close
     * @return mixed
     */
    function close($commit_on_close = false)
    {
        // TODO: Implement close() method.
    }

    /**
     *
     * Ermittelt aus einer Tabellenspalte mit Format "Enum" ein Array des Inhalts.
     * @param string $table
     * @param string $varName
     * @deprecated
     */
    function getEnumValues($table, $varName){
        trigger_error(E_DEPRECATED);
    }


    /**
     * @param string $columnName
     * @param string $tableName
     * @param string $schemaName
     * @return array
     */
    function queryColumnValues($columnName, $tableName, $schemaName = null){ // todo parameter tunen //XXX TODO fuer Set und Enum  //XXX current schema
        if(!$schemaName){
            $tmp = explode('.', $tableName);
            $tableName = $tmp[1];
            $schemaName = $tmp[0];
        }
        $column_type = $this->queryRow("
            SELECT column_type, data_type
            FROM information_schema.COLUMNS
            WHERE TABLE_NAME  = '$tableName'
              and COLUMN_NAME = '$columnName'
              and TABLE_SCHEMA = '$schemaName'");
        if($column_type['data_type']==='enum' or $column_type['data_type']==='set'){
            $enumList = explode(",",str_replace(array("'", ")", "set(", "enum("),"", $column_type["column_type"]));
        }else{
            trigger_error('Only SET or ENUM allowed as columns', E_ERROR);
        }
        asort($enumList);
        return $enumList;
    }

    /**
     * Inserts the valueArray into a given insertTable using the array keys as field names.
     *
     * By default the $mode is set to allow duplicates. When choosing C_IGNORE_DUPLICATES, the row
     * will only be inserted if the $valueArray differs in at least one field from already existing rows.
     *
     * @param string $tableName
     * @param array $valueArray
     * @param bool $insertMode
     * @return bool insert success
     */
    function queryInsertRow($insertTable, array $valueArray, $insertMode = self::C_ALLOW_DUPLICATES)
    {
        if(self::C_ALLOW_DUPLICATES===$insertMode){
            $this->query($this->buildSqlInsertRow($insertTable, $valueArray));
            return true;
        }elseif(self::C_IGNORE_DUPLICATES===$insertMode){
            $selectString = "SELECT count(*) FROM $insertTable WHERE 1 ";
            foreach($valueArray as $var=>$value){
                $selectString .= ($value === NULL) ? " AND ".$var." IS NULL ":" AND ".$var." = '".$this->conn->real_escape_string($value)."'";
            }
            $count = $this->queryValue($selectString); //check for identical rows;
            if ($count==="0"){
                $this->query($this->buildSqlInsertRow($insertTable, $valueArray));
                return true;
            }else{
                return false;
            }
        }else{
            trigger_error("Wrong parameter for insert mode. Please choose 'C_ALLOW_DUPLICATES' or 'C_IGNORE_DUPLICATES' as third parameter!", E_USER_ERROR);
        }
    }

    function buildSqlInsertRow($insertTable, array $valueArray){
        $valueArray = array_map(array($this->conn, "real_escape_string"),$valueArray);
        return "INSERT INTO $insertTable (".implode(",", array_keys($valueArray)).") VALUES ('".implode("','", $valueArray)."')";
    }

    function updateQueryByID($updateTable, $valueArray, $primaryKeyID)
    {
        if ($primaryKey = $this->queryPrimaryKey($updateTable)) {
            $updateElements = [];
            foreach($valueArray as $columnName=>$value){
                $updateElements[] = " $columnName = '$value'";
            }
            $this->query("UPDATE $updateTable SET ".implode(',', $updateElements)." WHERE $primaryKey = '$primaryKeyID'");
        } else {
            trigger_error("No necessary primary key found for $updateTable.", E_USER_ERROR);
        }
    }

}




?>