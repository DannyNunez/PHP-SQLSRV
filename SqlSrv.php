<?php
/**
 * @version 0.1
 * @author Danny Nunez
 */
class SqlSrv
{

    public $serverName = SQL_SERVER_NAME;
    public $dbname = SQL_DB_NAME;
    public $user = SQL_USER;
    public $password = SQL_PASSWORD;
    public $characterSet = "UTF-8";
    public $connection;
    protected $statement = null;
    protected $status = null;

    function __construct()
    {

        $connectionInfo = array(
            "UID" => $this->user,
            "PWD" => $this->password,
            "Database" => $this->dbname,
            "CharacterSet" => $this->characterSet
        );

        $this->connection = sqlsrv_connect($this->serverName, $connectionInfo);

        if ($this->connection) {
            $this->status = true;
        } else {
            $this->status = false;
        }
    }

    /**
     * Checks is the db connection is established. All queries for dynamic DB content should check is the
     * connection is established and load fallback content if the connection value is false.   
     * @return Boolean
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Closes an open connection and releases resourses associated with the connection.
     * @return Returns TRUE on success or FALSE on failure.
     */
    public function close()
    {
        if ($this->connection) {
            sqlsrv_close($this->connection);
        }
    }

    /**
     * Prepared statement
     * @param string $query sql query
     * @param array $params
     * @return Returns a statement resource on success and FALSE if an error occurred.
     * @todo Fix error handling to make sure the user never sees any errors. 
     * @link http://www.php.net/manual/en/function.sqlsrv-prepare.php
     */
    public function prepare($query)
    {
        return sqlsrv_prepare($this->connection, $query);
    }

    /**
     * @param $prepStmt - Value of prepared statement.
     * @todo Fix error handling to make sure the user never sees any errors.
     * @link http://www.php.net/manual/en/function.sqlsrv-execute.php
     * @return Returns TRUE on success or FALSE on failure
     */
    public function execute($preparedStatement)
    {
        if (sqlsrv_execute($preparedStatement) === true) {
            return true;
        } else {
            return false;
        }
    }

    public function getResults($preparedStatement)
    {
        $results = array();
        while ($row = sqlsrv_fetch_array($preparedStatement, SQLSRV_FETCH_ASSOC)) {
            $results[] = $row;
        }
        return $results; 
    }

    /**
     * @param $query string           
     * @return statement
     * @todo Fix error handling, the user show never know. 
     */
    public function query($query)
    {
        $this->statement = sqlsrv_query($this->connection, $query);
        if (!$this->statement) {
            die(print_r(sqlsrv_errors(), true));
        }

        return $this->statement;
    }

    /**
     * Return Last entered ID
     * @return integer
     */
    public function lastInsertId()
    {
        $scopeId = (int) $this->fetchCol("SELECT SCOPE_IDENTITY() AS SCOPE_IDENTITY");

        return $scopeId;
    }

    /**
     * @return integer
     */
    public function getRowsAffected()
    {
        if (is_null($this->statement)) {
            return - 1;
        }

        $rowsAffected = sqlsrv_rows_affected($this->statement);
        if ($rowsAffected == - 1 || $rowsAffected === false) {
            return - 1;
        }

        return (int) $rowsAffected;
    }

    /**
     * @param $query string           
     * @return array of objects - Returns an object on success,
     *  NULL if there are no more rows to return, 
     * and FALSE if an error occurs or if the specified class does not exist.
     *  @link http://www.php.net/manual/en/function.sqlsrv-fetch-object.php
     */
    public function fetchObject($query)
    {
        $stmt = $this->query($query);
        $a_array = array();
        while ($res = sqlsrv_fetch_object($stmt)) {
            $a_array[] = $res;
        }

        return $a_array;
    }

    /**
     * @param $query string
     * @param $type string - The type of array to return. SQLSRV_FETCH_ASSOC or 
     * SQLSRV_FETCH_NUMERIC 
     * for more info see here http://www.php.net/manual/en/function.sqlsrv-fetch-array.php 
     * @return array of array
     */
    public function fetchArray($query = null, $type = SQLSRV_FETCH_ASSOC)
    {
        $stmt = $this->query($query);
        $a_array = array();
        while ($res = sqlsrv_fetch_array($stmt, $type)) {
            $a_array[] = $res;
        }

        return $a_array;
    }

    /**
     * @param $query string           
     * @return value - Returns data from the specified field on success. Returns FALSE otherwise.
     * @link http://www.php.net/manual/en/function.sqlsrv-get-field.php
     */
    public function fetchCol($query)
    {
        $stmt = $this->query($query);
        sqlsrv_fetch($stmt);
        $column = sqlsrv_get_field($stmt, 0);

        return $column;
    }

    /**
     * @param $prepStmt
     * @param string $fetchType object|array
     * @return multitype:mixed 
     * @todo Fix error handling to make sure the user never sees any errors. 
     * @todo Add return
     */
    public function executeFetch($prepStmt, $fetchType = 'object')
    {
        if (sqlsrv_execute($prepStmt)) {
            $a_array = array();
            $func = 'sqlsrv_fetch_' . $fetchType;
            while ($res = call_user_func($func, $prepStmt)) {
                $a_array[] = $res;
            }
            return $a_array;
        } else {
            die(print_r(sqlsrv_errors(), true));
        }
    }

    /**
     * @param String $tableName - The name of the table. Returns all rows from the table requested. 
     * @param type $type - The return type wanted. SQLSRV_FETCH_ASSOC 
     * OR SQLSRV_FETCH_NUMERIC
     * @return ARRAY - This method will return an associative or numeric array is results are returned.
     * By default an associative array will be returned. 
     */
    public function get($tableName, $feilds = '*', $type = SQLSRV_FETCH_ASSOC, $order = 'DESC')
    {

        if (is_array($feilds)) {
            $feildsString = $this->feildsBuilder($feilds);
        } else {
            $feildsString = '*';
        }

        $sql = "SELECT $feildsString FROM $tableName ORDER BY id $order";
        $preparedStatement = sqlsrv_prepare($this->connection, $sql);
        $result = sqlsrv_execute($preparedStatement);
        $results = array();
        if ($result === true) {
            if ($type == SQLSRV_FETCH_ASSOC) {
                while ($row = sqlsrv_fetch_array($preparedStatement, SQLSRV_FETCH_ASSOC)) {
                    $results[] = $row;
                }
            } else {
                while ($row = sqlsrv_fetch_array($preparedStatement, SQLSRV_FETCH_NUMERIC)) {
                    $results[] = $row;
                }
            }
            return $results;
        } else {
            return false;
        }
    }

    /**
     * @param String $tableName - The name of the table. Returns all rows from the table requested.
     * @param Int $Id - The id of the record.   
     * @param STRING $type - The return type wanted. SQLSRV_FETCH_ASSOC 
     * OR SQLSRV_FETCH_NUMERIC - See http://www.php.net/manual/en/function.sqlsrv-fetch-array.php
     * @return ARRAY - This method will return an associative or numeric array is results are returned.
     * By default an associative array will be returned. 
     */
    public function get_by_id($tableName, $id = null)
    {
        $sql = "SELECT * FROM $tableName WHERE id = $id";
        $preparedStatement = sqlsrv_prepare($this->connection, $sql);
        $result = sqlsrv_execute($preparedStatement);
        if ($result === true) {
            while ($row = sqlsrv_fetch_array($preparedStatement, SQLSRV_FETCH_ASSOC)) {
                $results = $row;
            }
            return $results;
        } else {
            return false;
        }
    }

    /**
     * @param String $tableName - The name of the table. 
     * @param Array $keyValue - An associative array. The key is the feild name and the value is the field value.   
     * @param STRING $type - The return type wanted. SQLSRV_FETCH_ASSOC
     * @param String $order - Order the results either ASC or DESC. DESC is the default 
     * OR SQLSRV_FETCH_NUMERIC - See http://www.php.net/manual/en/function.sqlsrv-fetch-array.php
     * @return ARRAY - This method will return an associative or numeric array is results are returned.
     * By default an associative array will be returned.
     * @todo  Build a querry builder to handle mulitple key value pairs. Currently only handles on set of keyvalues. 
     */
    public function get_where($tableName, $keyValue, $type = 'SQLSRV_FETCH_ASSOC', $order = 'DESC')
    {
        $sqlString = $this->querry_builder($keyValue);
        $sql = "SELECT * FROM $tableName WHERE $sqlString ORDER BY $order";
        $preparedStatement = sqlsrv_prepare($this->connection, $sql);
        $result = $this->execute($preparedStatement);
        $results = array();
        if ($result === true) {
            while ($row = sqlsrv_fetch_array($preparedStatement, SQLSRV_FETCH_ASSOC)) {
                $results[] = $row;
            }
            return $results;
        } else {
            return false;
        }
    }

    public function query_builder($keyValue)
    {
        $sqlString = '';
        $numberOfKeyValues = count($keyValue);
        $count = 1;
        foreach ($keyValue as $key => $value) {
            if ($count == $numberOfKeyValues) {
                $sqlString = $sqlString . $key . ' = ' . $value . ' ';
            } else {
                $sqlString = $sqlString . $key . ' = ' . $value . ' AND ';
            }
            $count++;
        }
        return $sqlString;
    }

    public function feildsBuilder($feilds)
    {
        $feildsString = '';
        $numberOfFeilds = count($feilds);
        $count = 1;
        foreach ($feilds as $value) {
            if ($count == $numberOfFeilds) {
                $feildsString = $feildsString . $value . ' ';
            } else {
                $feildsString = $feildsString . $value . ' , ';
            }
            $count++;
        }
        return $feildsString;
    }

}