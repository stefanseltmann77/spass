<?php

namespace spass\database;

/**
 * High-Level wrapper for communication with DB-Servers.
 * Class SPAppDbHandle
 *
 * @author Stefan Seltmann
 * @package spass\database
 */
abstract class SPAppDbHandle{

    /**
     * database connection
     * @var mixed
     */
	protected $conn = null;

    /**
     * @var bool
     * @inprogress
     */
    protected $echoSql = false;

    /**
     * @var bool
     * @inprogress
     */
    protected $echoError = true;

    /**
     * @var mixed
     */
    protected $lastInsertId = null;

    /**
     * @var bool
     */
    protected $autoCommit = true;

    /**
     * @var bool
     */
    protected $usePreparedStatements = false;

    /**
     * @var int
     */
    protected $errorHandling = self::ERRORS_STRICT;


    const QT_MULTIROW          = 1;
    const QT_MULTIROW1COL      = 2;
    const QT_MULTIROW2COL      = 3;
    const QT_SINGLEROW         = 4;
    const QT_SINGLEVALUE       = 5;
    const QT_WORESULT          = 6;
    const QT_UPDATE            = 7;
    const QT_INSERT            = 8;
    const QT_DELETE            = 9;
    const ERRORS_STRICT = 1;
    const ERRORS_REPORT = 2;
    const ERRORS_RELAY  = 3;
    const ERRORS_IGNORE = 4;
    const C_IGNORE_DUPLICATES = false;
    const C_ALLOW_DUPLICATES  = true;

    /**
     * @param $handling
     * @inprogress
     * @todo rework
     */
    function setErrorHandling($handling){
        if($handling ==="STRICT"){
	        $this->errorHandling = self::ERRORS_STRICT;
	    }elseif($handling ==="RELAY"){
	        $this->errorHandling = self::ERRORS_RELAY;
	    }elseif($handling ==="REPORT"){
	        $this->errorHandling = self::ERRORS_REPORT;
	    }elseif($handling ==="IGNORE"){
	        $this->errorHandling = self::ERRORS_IGNORE;
	    }else{
	        trigger_error('Falscher Parameter $handling! Nur erlaubt: STRICT, RELAY, REPORT oder IGNORE', E_USER_ERROR); //YYY überarbeiten
	    }
	}

    /**
     * @param bool $input
     */
	function setEchoSql($input = true){
		$this->echoSql = $input;
	}

    /**
     * @param $input
     */
	function setEchoError($input = true){
		$this->echoError = $input;
	}

    /**
     * @return mixed
     */
	function getLastInsertID(){
        return $this->lastInsertId;
	}

    /**
     * @param string $sqlText
     * @param $queryType
     * @param array $params
     * @return mixed TODO festlegen XXX
     */
	abstract function commitQuery($sqlText, $queryType, array &$params=null);

    /**
     * Query for result with multiple rows and multiple columns
     * @param string $sqlText
     * @param array $params
     * @return array
     */
	function queryResult($sqlText, array $params=null) {
		return $this->commitQuery($sqlText, self::QT_MULTIROW, $params);
	}

    /**
     * Query for result with only one row and multiple columns
     * @param string $sqlText
     * @param array $params
     * @return array
     */
	function queryRow($sqlText, array $params=null) {
		return $this->commitQuery($sqlText, self::QT_SINGLEROW, $params);
	}

    /**
     * Query for result with only a single value to be returned, e.g. counts
     * @param string $sqlText
     * @param array $params
     * @return mixed
     */
	function queryValue($sqlText, array $params=null) {
		return $this->commitQuery($sqlText, self::QT_SINGLEVALUE, $params);
	}

    /**
     * Query that returns result from a single column as an array of these values.
     * @param string $sqlText
     * @param array $params
     * @return mixed
     *
     * TODO: wirft keinen Fehler, wenn zu viele Spalten übergeben werden.
     */
    function queryList($sqlText, array $params=null){
    	return $this->commitQuery($sqlText, self::QT_MULTIROW1COL, $params);
    }

    /**
     * Query that returns result from a query of two columns as a mapping/array.
     * @param string $sqlText
     * @param array $params
     * @return mixed
     */
    function queryMapping($sqlText, array $params=null){
    	return $this->commitQuery($sqlText, self::QT_MULTIROW2COL, $params);
    }

    /**
     * @param $table
     * @param null $schema
     * @return bool
     */
	abstract function tableExists($table, $schema = null);

    /**
     * Query anything regardless of return values, e.g. updates
     * @param string $sqlText
     * @param array $params
     */
	function query($sqlText, array $params=null) {
		$this->commitQuery($sqlText, self::QT_WORESULT, $params);
	}

	/**
	 * Lists the column names for a given table in form of a array
	 *
	 * @param string $table
     * @deprecated
	 * @return array
	 */
	abstract function queryTableFields($table);

    /**
     * List the table names of a given schema
     *
     * @param string $schema
     * @return array
     *
     * TODO: Split für views, default schema
     */
    abstract function querySchemaTableNames($schema);

    /**
     * Lists the column names for a given table in form of a array
     *
     * @param string $table
     * @param string $schema
     * @return array
     */
    abstract function queryTableColumnNames($table, $schema=null);

	/**
	 * Returns the string for a insert query based on a value array and a target table
	 *
	 * @param string $insertTable
	 * @param array  $valueArray  	An associative array with keys as column names and values as column contents
	 */
	abstract function buildInsertQuery($insertTable, array $valueArray);

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
    abstract function queryInsert($tableName, array $valueArray, $mode=self::C_ALLOW_DUPLICATES);

    /**
     * @param $tableName
     * @param array $valueArray
     * @param $condition
     * @return mixed
     */
    abstract function queryUpdate($tableName, array $valueArray, $condition);

    /**
     * @param $tableName
     * @return mixed
     */
    abstract function queryPrimaryKey($tableName);

    /**
     * @param $tableName
     * @return mixed
     */
    abstract function queryTableDescription ($tableName);


    /*
    function updateQueryByID($updateTable, $valueArray, $primaryKeyID){
        if($primkey = $this->queryPrimaryKey($updateTable)){
            $this->updateQuery($updateTable, $valueArray, " $primkey = '$primaryKeyID'");
        }else{
            trigger_error("No necessary primary key found for $updateTable.", E_USER_ERROR);
        }
    }

    function updateEntryByID($updateTable, $valueArray, $primaryKeyID){
        return $this->updateQueryByID($updateTable, $valueArray, $primaryKeyID);
    }*/

    /**
     * Closes the currently open connection to the database.
     *
     * By default all pending transactions will not be committed.
     * @param bool $commit_on_close
     * @return mixed
     */
    abstract function close($commit_on_close = false);
    
    function __destruct() {
        $this->close();
    }
}

