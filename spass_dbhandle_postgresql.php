<?php

namespace spass\database;

require('spass_dbhandle.php');

use spass\database\SPAppDbHandle;
/**
 * High-Level Wrapper for communication with PostgreSQL-Servers.
 * @author Stefan Seltmann
 */
class SPAppDbHandlePostgreSQL extends SPAppDbHandle{



    function __construct($host, $user, $passwd, $db, $port=5432){
        $this->conn = pg_connect("host=$host dbname=$db port=$port user=$user password=$passwd");
    }

    function commitQuery($sqlText, $queryType, array &$params = Null){
        if ($this->echoSql){
            echo "<br />".$sqlText;
        }
        if($this->usePreparedStatements==TRUE){
            $stmt = $this->conn->prepare($sqlText);
            $ref = new ReflectionClass('mysqli_stmt');
            $method = $ref->getMethod("bind_param");
            $params2 = array();
            foreach($params as $k=>$v){
                $params2[$k]=$v;
            }
            $method->invokeArgs($stmt,$params2);
            //call_user_func_array(array(&$stmt, 'bind_param'), &$params2);
            $stmt->execute();
            $sqlResource = $stmt->get_result();
        }else{
            if($params){
                $sqlResource = pg_query_params($this->conn, $sqlText, $params);
            }else{
                $sqlResource = pg_query($this->conn, $sqlText);
            }
        }
        if($sqlResource){
            //$warningCount = $this->conn->warning_count;
            if(self::QT_WORESULT === $queryType){//filtern auf insertTypen oder unklare YYY.
                // XXXX TODO $tempResource = $this->conn->query("SELECT LAST_INSERT_ID() ");
                // XXXX TODO $tempRow = $tempResource->fetch_row(); //YYYzeile einsparen?
                // XXXX TODO $this->lastInsertId = $tempRow[0];
                $result = FALSE;
            }elseif(self::QT_UPDATE === $queryType){
                $result = $this->conn->affected_rows;
                $this->lastInsertId = NULL;
            }else{
                $this->lastInsertId = NULL;
                if (self::QT_MULTIROW===$queryType){
                    $result = array();
                    while ($row = pg_fetch_assoc($sqlResource)){
                        $result[] = $row;
                    }
                } elseif (self::QT_SINGLEROW===$queryType){
                    $result = pg_fetch_assoc($sqlResource);
                } elseif (self::QT_MULTIROW2COL===$queryType){
                    $result = array();
                    while ($row =  pg_fetch_array($sqlResource)){
                        $result[$row[0]] = $row[1];
                    }
                } elseif (self::QT_SINGLEVALUE===$queryType){
                    $result = pg_fetch_array($sqlResource);
                    $result = $result[0];
                } elseif (self::QT_MULTIROW1COL===$queryType){
                    $result = array();
                    while ($row = pg_fetch_array($sqlResource)){
                        $result[] = $row[0];
                    }
                }
            }
            /*if ($warningCount or FALSE) { //XXXX unsauber
                $warnings =  $this->conn->query("SHOW WARNINGS");
                while ($row = $warnings->fetch_array(MYSQLI_ASSOC)){
                    $this->conn->query("INSERT INTO mysql.query_logs (query, log, type,errno) VALUES ('".addslashes($sqlText)."', '".addslashes($row['Message'])."', 'warning', '".$row['Code']."') ");
                }
            }*/
            /*if($sqlResource !== TRUE ){
                $sqlResource->free();
            }*/
            return $result;
        }else{
            if ($this->echoError){
                $backtrace = debug_backtrace();
                foreach($backtrace as $row){
                    echo $row['line']." in ".$row['function']."\tin\t".$row['file']."<BR />";
                }
//                echo "<br /><br /><span style=\"color:red\">$sqlText<br />{$this->conn->errno}: {$this->conn->error}</span><br /><br />"; // YYY auf exception ändern
                die();
            }else{
//                echo "<br /><br /><span style=\"color:red\">$sqlText<br />{$this->conn->errno}: {$this->conn->error}</span><br /><br />";
                return FALSE;
            }
            $this->conn->query("INSERT INTO mysql.query_logs (query, log, type,errno)VALUES ('".addslashes($sqlText)."', '".addslashes($this->conn->error)."', 'error', '".$this->conn->errno."') ");
        }
    }


    function insertQuery($insertTable, $valueArray, $mode = self::C_ALLOW_DUPLICATES){trigger_error('not yet implemented');}
    function updateQuery($updateTable, $valueArray, $filter){trigger_error('not yet implemented');}
    function queryPrimaryKey($table){trigger_error('not yet implemented');}
    function queryTableDescription ($table){trigger_error('not yet implemented');}
    function queryTableFields ($table){trigger_error('not yet implemented');}


    /**
     * Returns the string for a insert query based on a value array and a target table
     *
     * @param string $insertTable
     * @param array  $valueArray  	An associative array with keys as column names and values as column contents
     */
    function buildInsertQuery($insertTable, array $valueArray){trigger_error('not yet implemented');}

    function tableExists($table, $schema = NULL){trigger_error('not yet implemented');}

    /**
     * Closes the currently open connection to the database.
     *
     * By default all pending transactions will not be committed.
     * @param boolean $commit_on_close
     */
    function close($commit_on_close = False){
        if($commit_on_close === True){
            $this->query("COMMIT;");
        }
        if($this->conn){
            //pg_close($this->conn); //not necessary according to manual
        }
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
        // TODO: Implement queryTableColumnNames() method.
    }

    function querySchemaTableNames($schema){
        // TODO: Implement queryTableColumnNames() method.
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
        // TODO: Implement queryUpdate() method.
    }
}

?>