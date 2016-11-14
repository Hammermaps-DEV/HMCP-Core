<?php
/*
 * Diese Datei ist Teil von HM-Kernel.
 * 
 * HM-Kernel ist Freie Software: Sie können es unter den Bedingungen
 * der GNU General Public License, wie von der Free Software Foundation,
 * Version 3 der Lizenz oder (nach Ihrer Wahl) jeder späteren
 * veröffentlichten Version, weiterverbreiten und/oder modifizieren.
 * 
 * HM-Kernel wird in der Hoffnung, dass es nützlich sein wird, aber
 * OHNE JEDE GEWÄHRLEISTUNG, bereitgestellt; sogar ohne die implizite
 * Gewährleistung der MARKTFÄHIGKEIT oder EIGNUNG FÜR EINEN BESTIMMTEN ZWECK.
 * Siehe die GNU General Public License für weitere Details.
 *
 * Sie sollten eine Kopie der GNU General Public License zusammen mit diesem
 * Programm erhalten haben. Wenn nicht, siehe <http://www.gnu.org/licenses/>.
 */

namespace kernel;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

/* block attempts to directly run this script */
if (getcwd() == dirname(__FILE__)) {
    die('block directly run');
}

final class database {
    protected $log = null;
    protected $dbConf = array();
    protected $instances = array();
    protected $active = false;
    protected $master_slave = false;
    protected $cluster = false;
    protected $dbHandle = null;
    protected $lastInsertId = false;
    protected $rowCount = false;
    protected $queryCounter = 0;
    protected $active_driver = '';
    protected $connection_pooling = true;
    protected $connection_encrypting = true;
    protected $mysql_buffered_query = true;
    protected $INIConfig = array();

    public function __construct($INIConfig,$master=false,$cluster=false) {
        // create a log file
        $this->log = new Logger('Database');
        $this->log->pushHandler(new StreamHandler(ROOT_PATH.'/logs/database.log', common::$logger_level));
        $this->INIConfig = $INIConfig;
        $this->master_slave = $master;
        $this->cluster = $cluster;

        //Set Global-Configuration
        $this->dbConf['global'] = $this->INIConfig->getSection('global');

        if($this->INIConfig->getSection('master')) {
            $this->dbConf['master'] = $this->INIConfig->getSection('master');
        }

        if(!$this->INIConfig->getSection('master_1')) {
            $this->dbConf['slave'] = $this->INIConfig->getSection('slave');
        }

        // Multi-Masters * Cluster
        if($this->INIConfig->getSection('master_1')) {
            foreach ($this->INIConfig->getSections() as $id => $name) {
                if(strpos($name, 'master_') !== false) {
                    $this->dbConf[$name] = $this->INIConfig->getSection($name);
                }
            }
        }
        // Multi-Slaves
        else if($this->INIConfig->getSection('slave_1')) {
            foreach ($this->INIConfig->getSections() as $id => $name) {
                if(strpos($name, 'slave_') !== false) {
                    $this->dbConf[$name] = $this->INIConfig->getSection($name);
                }
            }
        }
    }

    public function __destruct() {
        $this->disconnect();
    }

    public function setConfig(array $data, $active = "global") {
        if(isset($data['database']) && isset($data['hostname']) &&
            isset($data['username']) && isset($data['password']) &&
            isset($data['driver'])) {
            $this->dbConf[$active] = $data;
        }
    }

    public final function getInstance($active = "") {
        if(common::$debug_config->getKey('pdo_disable_update_statement','database')) {
            $this->log->addInfo('PDO-Update statement is disabled!');
        }

        if(common::$debug_config->getKey('pdo_disable_insert_statement','database')) {
            $this->log->addInfo('PDO-Insert statement is disabled!');
        }

        if(common::$debug_config->getKey('pdo_disable_delete_statement','database')) {
            $this->log->addInfo('PDO-Delete statement is disabled!');
        }

        if(empty($active) && $this->checkSQLConfig('master') && $this->checkSQLConfig('slave')) {
            $active = 'master';
            $this->master_slave = true;
        } else if(empty($active) && $this->checkSQLConfig('master') && $this->checkSQLConfig('master_1')) {
            $active = 'master';
            $this->cluster = true;
        } else {
            $active = 'global';
        }

        if (!isset($this->dbConf[$active])) {
            common::$log->addAlert("Unexisting DB-Config: '".$active."'");
            throw new \Exception("Unexisting DB-Config: '".$active."'");
        }
        
        if (!isset($this->instances[$active]) || $this->instances[$active] instanceOf database === false) {
            $this->instances[$active] = new database($this->INIConfig,$this->master_slave,$this->cluster);
            $this->instances[$active]->setConfig($this->dbConf[$active],$active);
            $this->instances[$active]->connect($active);
        }

        return $this->instances[$active];
    }

    public final function disconnect($active = "") {
        if(empty($active)) {
            unset($this->instances[$this->active]);
        } else {
            unset($this->instances[$active]);
        }

        $this->dbHandle = null;
    }

    public function lastInsertId() {
        return $this->lastInsertId;
    }

    public function rowCount() {
        return intval($this->rowCount);
    }
    
    public function rows($qry, array $params = array()) {
        if(common::$debug_config->getKey('pdo_disable_rows_statement','database')) {
            return false;
        }

        if (($type = $this->getQueryType($qry)) !== "select" && 
                ($type = $this->getQueryType($qry)) !== "show") {

            $this->log->addWarning("Incorrect Select Query in method rows() ->",array('QUERY'=>$qry,'PARAMS'=>$params));
            if(common::$debug_config->getKey('pdo_enable_debug_print','database')) {
                $this->log->addDebug("SQL-Debug Query ->",array('QUERY'=>$qry,'PARAMS'=>$params));
            }
            return 0;
        }

        $this->run_query($qry, $params, $type);
        return $this->rowCount;
    }
    
    public function delete($qry, array $params = array()) {
        if(common::$debug_config->getKey('pdo_disable_delete_statement','database')) {
            return false;
        }
        
        if (($type = $this->getQueryType($qry)) !== "delete" && $type !== "drop") {
            $this->log->addWarning("Incorrect Delete Query in method delete() ->",array('QUERY'=>$qry,'PARAMS'=>$params));
            if(common::$debug_config->getKey('pdo_enable_debug_print','database')) {
                $this->log->addDebug("SQL-Debug Query ->",array('QUERY'=>$qry,'PARAMS'=>$params));
            }
            return false;
        }

        return $this->run_query($qry, $params, $type);
    }

    public function update($qry, array $params = array()) {
        if(common::$debug_config->getKey('pdo_disable_update_statement','database')) {
            return false;
        }
        
        if (($type = $this->getQueryType($qry)) !== "update") {
            $this->log->addWarning("Incorrect Update Query in method delete() ->",array('QUERY'=>$qry,'PARAMS'=>$params));
            if(common::$debug_config->getKey('pdo_enable_debug_print','database')) {
                $this->log->addDebug("SQL-Debug Query ->",array('QUERY'=>$qry,'PARAMS'=>$params));
            }
            return false;
        }

        return $this->run_query($qry, $params, $type);
    }

    public function insert($qry, array $params = array()) {
        if(common::$debug_config->getKey('pdo_disable_insert_statement','database')) {
            return false;
        }
        
        if (($type = $this->getQueryType($qry)) !== "insert") {
            $this->log->addWarning("Incorrect Insert Query in method delete() ->",array('QUERY'=>$qry,'PARAMS'=>$params));
            if(common::$debug_config->getKey('pdo_enable_debug_print','database')) {
                $this->log->addDebug("SQL-Debug Query ->",array('QUERY'=>$qry,'PARAMS'=>$params));
            }
            return false;
        }

        return $this->run_query($qry, $params, $type);
    }

    public function select($qry, array $params = array()) {
        if(common::$debug_config->getKey('pdo_disable_select_statement','database')) {
            return false;
        }
        
        if (($type = $this->getQueryType($qry)) !== "select") {
            $this->log->addWarning("Incorrect Select Query in method delete() ->",array('QUERY'=>$qry,'PARAMS'=>$params));
            if(common::$debug_config->getKey('pdo_enable_debug_print','database')) {
                $this->log->addDebug("SQL-Debug Query ->",array('QUERY'=>$qry,'PARAMS'=>$params));
            }
            return array();
        }

        if ($stmnt = $this->run_query($qry, $params, $type)) {
            return $stmnt->fetchAll(\PDO::FETCH_ASSOC);
        } else {
            return array();
        }
    }

    public function fetch($qry, array $params = array(), $field = false) {
        if(common::$debug_config->getKey('pdo_disable_fetch_statement','database')) {
            return false;
        }
        
        if (($type = $this->getQueryType($qry)) !== "select") {
            $this->log->addWarning("Incorrect Select Query in method delete() ->",array('QUERY'=>$qry,'PARAMS'=>$params));
            if(common::$debug_config->getKey('pdo_enable_debug_print','database')) {
                $this->log->addDebug("SQL-Debug Query ->",array('QUERY'=>$qry,'PARAMS'=>$params));
            }
            return false;
        }

        if ($stmnt = $this->run_query($qry, $params, $type)) {
            $res = $stmnt->fetch(\PDO::FETCH_ASSOC);
            return ($field === false) ? $res : $res[$field];
        } else {
            return false;
        }
    }
    
    public function show($qry) {
        if(common::$debug_config->getKey('pdo_disable_show_statement','database')) {
            return false;
        }

        if (($type = $this->getQueryType($qry)) !== "show") {
            $this->log->addWarning("Incorrect Show Query in method delete() ->",array('QUERY'=>$qry));
            if(common::$debug_config->getKey('pdo_enable_debug_print','database')) {
                $this->log->addDebug("SQL-Debug Query ->", array('QUERY' => $qry));
            }
            return array();
        }

        if ($stmnt = $this->run_query($qry, array(), $type)) {
            return $stmnt->fetchAll(\PDO::FETCH_ASSOC);
        } else {
            return array();
        }
    }
    
    public function create($qry) {
        if(common::$debug_config->getKey('pdo_disable_create_statement','database')) {
            return false;
        }
        
        if (($type = $this->getQueryType($qry)) !== "create") {
            $this->log->addWarning("Incorrect Create Query in method delete() ->",array('QUERY'=>$qry));
            if(common::$debug_config->getKey('pdo_enable_debug_print','database')) {
                $this->log->addDebug("SQL-Debug Query ->", array('QUERY' => $qry));
            }
            return array();
        }

        return $this->run_query($qry, array(), $type);
    }
    
    public function optimize($qry) {
        if(common::$debug_config->getKey('pdo_disable_optimize_statement','database')) {
            return false;
        }
        
        if (($type = $this->getQueryType($qry)) !== "optimize") {
            $this->log->addWarning("Incorrect Optimize Query in method delete() ->",array('QUERY'=>$qry));
            if(common::$debug_config->getKey('pdo_enable_debug_print','database')) {
                $this->log->addDebug("SQL-Debug Query ->",array('QUERY'=>$qry));
            }
            return array();
        }

        $this->run_query($qry, array(), $type);
    }

    public final function query($qry) {
        if(common::$debug_config->getKey('pdo_disable_query_statement','database')) {
            return false;
        }
        
        $qry = $this->rep_prefix($qry); // replace sql prefix
        $this->lastInsertId = false;
        $this->rowCount = false;
        if($this->cluster) {
            $this->rowCount = $this->dbHandle[0]->exec($qry);
        } else if($this->master_slave) {
            $this->rowCount = $this->dbHandle[0]->exec($qry);
        } else {
            $this->rowCount = $this->dbHandle->exec($qry);
        }

        $this->queryCounter++;
    }

    public function getQueryCounter() {
        return $this->queryCounter;
    }

    public function quote($str) {
        return $this->dbHandle->quote($str);
    }

    /************************
     * Protected
     ************************/
    
    /**
     * Erstellt das PDO Objekt mit vorhandener Konfiguration
     * @namespace system\database
     * @category PDO Database
     * @param string $active = "default"
     * @throws PDOException
     */
    protected final function connect($active = "global") {
        if (!isset($this->dbConf[$active])) {
            common::$log->addCritical("No supported connection scheme! ->",array('SCHEME'=>$active));
            exit("PDO: No supported connection scheme!");
        }

        if($this->cluster) {
            ini_set("default_socket_timeout", 1);
            foreach($this->dbConf as $name => $config) {
                if($name == 'global') { //init global db
                    $dbConf = $this->dbConf['global'];
                    if (!$dsn = $this->dsn('global')) {
                        $this->log->addCritical("Driver is missing! ->", array('SCHEME' => $name, 'CONFIG' => $dbConf));
                        exit("PDO: Driver is missing!");
                    }

                    try {
                        $db = new \PDO($dsn, $dbConf['username'], $dbConf['password'], array(\PDO::ATTR_PERSISTENT => $dbConf['persistent'],
                            \PDO::ATTR_TIMEOUT => 5,
                            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                            \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\''));

                        $this->dbHandle[] = $db;
                    } catch (PDOException $Exception) {
                        $this->log->addWarning("Connection Exception! ->",array('MSG'=>$Exception->getMessage(),'CONFIG'=>$dbConf));
                    }
                }

                if($name != 'global') { // init masters
                    $dbConf = $this->dbConf[$name];
                    if (!$dsn = $this->dsn($name)) {
                        $this->log->addCritical("Driver is missing! ->", array('SCHEME' => $name, 'CONFIG' => $dbConf));
                        exit("PDO: Driver is missing!");
                    }

                    try {
                        $port = ($dbConf['driver'] == 'mysql' ? 3306 : 1433); $host = $dbConf['hostname'];
                        $ipport = explode(':',$dbConf['hostname'],1);
                        if(count($ipport) == 2) {
                            $port = $ipport[1];
                            $host = $ipport[0];
                        } unset($ipport);

                        if(common::ping_port($host,$port,0.1)) {
                            $db = new \PDO($dsn, $dbConf['username'], $dbConf['password'], array(\PDO::ATTR_PERSISTENT => $dbConf['persistent'],
                                \PDO::ATTR_TIMEOUT => 1,
                                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                                \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\''));

                            $this->dbHandle[] = $db;
                        } else {
                            $this->log->addWarning("No Connection to Server ->",array('HOST'=>$dbConf['hostname'],'CONFIG'=>$dbConf));
                        }
                    } catch (PDOException $Exception) {
                        $this->log->addWarning("Connection Exception! ->",array('MSG'=>$Exception->getMessage(),'CONFIG'=>$dbConf));
                    }
                }
            }

            $this->active = 'master'; //mark as active
        } else if($this->master_slave) {
            try {
                //Init Master
                $dbConf = $this->dbConf[$active];
                if (!$dsn = $this->dsn($active)) {
                    $this->log->addCritical("Driver is missing! ->", array('SCHEME' => $active, 'CONFIG' => $dbConf));
                    exit("PDO: Driver is missing!");
                }

                $db = new \PDO($dsn, $dbConf['username'], $dbConf['password'], array(\PDO::ATTR_PERSISTENT => $dbConf['persistent'],
                    \PDO::ATTR_TIMEOUT => 5,
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\''));
                $this->dbHandle[] = $db;
            } catch (PDOException $Exception) {
                $this->log->addCritical("Connection Exception! ->",array('MSG'=>$Exception->getMessage(),'CONFIG'=>$dbConf));
                exit("PDO: Connection Exception: " . $Exception->getMessage());
            }

            // Init Slaves
            foreach($this->dbConf as $name => $config) {
                if($name != 'global' && $name != 'master') { // init slaves
                    $dbConf = $this->dbConf[$name];
                    if (!$dsn = $this->dsn($name)) {
                        $this->log->addCritical("Driver is missing! ->", array('SCHEME' => $name, 'CONFIG' => $dbConf));
                        exit("PDO: Driver is missing!");
                    }

                    try {
                        $port = ($dbConf['driver'] == 'mysql' ? 3306 : 1433); $host = $dbConf['hostname'];
                        $ipport = explode(':',$dbConf['hostname'],1);
                        if(count($ipport) == 2) {
                            $port = $ipport[1];
                            $host = $ipport[0];
                        } unset($ipport);

                        if(common::ping_port($host,$port,0.1)) {
                            $db = new \PDO($dsn, $dbConf['username'], $dbConf['password'], array(\PDO::ATTR_PERSISTENT => $dbConf['persistent'],
                                \PDO::ATTR_TIMEOUT => 1,
                                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                                \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\''));

                            $this->dbHandle[] = $db;
                        } else {
                            $this->log->addWarning("No Connection to Server ->",array('HOST'=>$dbConf['hostname'],'CONFIG'=>$dbConf));
                        }
                    } catch (PDOException $Exception) {
                        $this->log->addWarning("Connection Exception! ->",array('MSG'=>$Exception->getMessage(),'CONFIG'=>$dbConf));
                    }
                }
            }

            $this->active = 'master'; //mark as active
        } else {
            try {
                $dbConf = $this->dbConf[$active];
                if (!$dsn = $this->dsn($active)) {
                    $this->log->addCritical("Driver is missing! ->", array('SCHEME' => $active, 'CONFIG' => $dbConf));
                    exit("PDO: Driver is missing!");
                }

                $db = new \PDO($dsn, $dbConf['username'], $dbConf['password'], array(\PDO::ATTR_PERSISTENT => $dbConf['persistent'],
                                                                                     \PDO::ATTR_TIMEOUT => 5,
                                                                                     \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                                                                                     \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\''));
                $this->dbHandle = $db;
                $this->active = $active; //mark as active
            } catch (PDOException $Exception) {
                $this->log->addCritical("Connection Exception! ->",array('MSG'=>$Exception->getMessage(),'CONFIG'=>$dbConf));
                exit("PDO: Connection Exception: " . $Exception->getMessage());
            }
        }
    }
    
    public final function rep_prefix($qry){
        // replace sql prefix
        if(strpos($qry,"{prefix_")!==false) {
            $qry = preg_replace_callback("#\{prefix_(.*?)\}#",function($tb) { 
                return str_ireplace($tb[0],$this->dbConf['global']['prefix'].'_'.$tb[1],$tb[0]);
            },$qry);
        }

        if(strpos($qry,"{engine}")!==false) {
            switch (strtolower($this->dbConf['global']['engine'])) {
                case 'myisam': $replace = 'ENGINE=MyISAM '; break; //MyISAM Engine
                case 'innodb': $replace = 'ENGINE=InnoDB '; break; //InnoDB Engine
                case 'aria': $replace = 'ENGINE=Aria '; break; //Aria Engine
                case 'ndb': $replace = 'ENGINE=NDB '; break; //NDB Cluster Engine
                case 'memory': $replace = 'ENGINE=Memory '; break; //Memory Engine
                case 'archive': $replace = 'ENGINE=Archive '; break; //Archive Engine
                default: $replace = ''; break;
            }
            $qry = str_ireplace('{engine}', $replace, $qry);
        }

        return $qry;
    }
    
    protected final function run_query($qry, array $params, $type) { //Normal
        if (in_array($type, array("insert", "select", "update", "delete","show","optimize","create","drop")) === false) {
            common::$log->addCritical("Unsupported Query Type! ->",array('QUERY'=>$qry));
            exit("PDO: Unsupported Query Type!<p>".$qry);
        }

        if($this->cluster) {
            return $this->run_query_cluster($qry, $params, $type);
        }

        if($this->master_slave) {
            return $this->run_query_masl($qry, $params, $type);
        }

        $qry = $this->rep_prefix($qry); // replace sql prefix
        $this->lastInsertId = false;
        $this->rowCount = false;
        
        if(count($params)) {
            $stmnt = $this->active_driver == 'mysql' ? 
                    $this->dbHandle->prepare($qry, array(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => $this->mysql_buffered_query)) :
                    $this->dbHandle->prepare($qry);
        }

        try {
            $success = (count($params) !== 0) ? 
                $stmnt->execute($params) : 
               ($stmnt = $this->dbHandle->query($qry));
            $this->queryCounter++;

            if (!$success) {
                return false;
            }

            if ($type === "insert") {
                $this->lastInsertId = $this->dbHandle->lastInsertId();
            }

            $this->rowCount = $stmnt->rowCount();
            return ($type === "select" || $type === "show") ? $stmnt : true;
        } catch (PDOException $ex) {
            $this->log->addCritical("SQL-Exception ->",array('MSG'=>$ex->getMessage(),'QUERY'=>$qry,'PARAMS'=>$params));
            exit("PDO: Exception: " . $ex->getMessage()."<br><br>SQL-Query:<br>".$qry.(count($params) ? "<br><br>Input params:".  var_export($params,true) : ''));
        }
    }

    protected final function run_query_cluster($qry, array $params, $type) { //Cluster
        $qry = $this->rep_prefix($qry); // replace sql prefix
        $this->lastInsertId = false;
        $this->rowCount = false;
        if(count($this->dbHandle)-1 >= 1) {
            $dbHandle = $this->dbHandle[rand(1,(count($this->dbHandle)-1))];
        } else {
            $dbHandle = $this->dbHandle[0]; //faliback to global
        }

        $dbHandle = ($dbHandle == null ? $this->dbHandle[0] : $dbHandle); //faliback to global
        if(count($params)) {
            $stmnt = $this->active_driver == 'mysql' ?
                $dbHandle->prepare($qry, array(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => $this->mysql_buffered_query)) :
                $dbHandle->prepare($qry);
        }

        try {
            $success = (count($params) !== 0) ?
                $stmnt->execute($params) :
                ($stmnt = $dbHandle->query($qry));
            $this->queryCounter++;

            if (!$success) {
                return false;
            }

            if ($type === "insert") {
                $this->lastInsertId = $dbHandle->lastInsertId();
            }

            $this->rowCount = $stmnt->rowCount();
            return ($type === "select" || $type === "show") ? $stmnt : true;
        } catch (PDOException $ex) {
            $this->log->addCritical("SQL-Exception ->",array('MSG'=>$ex->getMessage(),'QUERY'=>$qry,'PARAMS'=>$params));
            exit("PDO: Exception: " . $ex->getMessage()."<br><br>SQL-Query:<br>".$qry.(count($params) ? "<br><br>Input params:".  var_export($params,true) : ''));
        }
    }

    protected final function run_query_masl($qry, array $params, $type) { //Master & Slave
        $qry = $this->rep_prefix($qry); // replace sql prefix
        if (in_array($type, array("select", "show"))) {
            //Slaves
            $this->lastInsertId = false;
            $this->rowCount = false;

            if(count($this->dbHandle)-1 >= 1) {
                $dbHandle = $this->dbHandle[rand(1,(count($this->dbHandle)-1))];
            } else {
                $dbHandle = $this->dbHandle[0]; //Set Only Master
            }

            $dbHandle = ($dbHandle == null ? $this->dbHandle[0] : $dbHandle); //faliback to global
            if(count($params)) {
                $stmnt = $this->active_driver == 'mysql' ?
                    $dbHandle->prepare($qry, array(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => $this->mysql_buffered_query)) :
                    $dbHandle->prepare($qry);
            }

            try {
                $success = (count($params) !== 0) ?
                    $stmnt->execute($params) :
                    ($stmnt = $dbHandle->query($qry));
                $this->queryCounter++;

                if (!$success) {
                    return false;
                }

                $this->rowCount = $stmnt->rowCount();
                return ($type === "select" || $type === "show") ? $stmnt : true;
            } catch (PDOException $ex) {
                $this->log->addCritical("SQL-Exception ->",array('MSG'=>$ex->getMessage(),'QUERY'=>$qry,'PARAMS'=>$params));
                exit("PDO: Exception: " . $ex->getMessage()."<br><br>SQL-Query:<br>".$qry.(count($params) ? "<br><br>Input params:".  var_export($params,true) : ''));
            }
        } else {
            //Master
            $this->lastInsertId = false;
            $this->rowCount = false;

            if(count($params)) {
                $stmnt = $this->active_driver == 'mysql' ?
                    $this->dbHandle[0]->prepare($qry, array(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => $this->mysql_buffered_query)) :
                    $this->dbHandle[0]->prepare($qry);
            }

            try {
                $success = (count($params) !== 0) ?
                    $stmnt->execute($params) :
                    ($stmnt = $this->dbHandle[0]->query($qry));
                $this->queryCounter++;

                if (!$success) {
                    return false;
                }

                if ($type === "insert") {
                    $this->lastInsertId = $this->dbHandle[0]->lastInsertId();
                }

                $this->rowCount = $stmnt->rowCount();
                return ($type === "select" || $type === "show") ? $stmnt : true;
            } catch (PDOException $ex) {
                $this->log->addCritical("SQL-Exception ->",array('MSG'=>$ex->getMessage(),'QUERY'=>$qry,'PARAMS'=>$params));
                exit("PDO: Exception: " . $ex->getMessage()."<br><br>SQL-Query:<br>".$qry.(count($params) ? "<br><br>Input params:".  var_export($params,true) : ''));
            }
        }
    }

    protected final function check_driver($use_driver) {
        foreach(\PDO::getAvailableDrivers() as $driver) {
            if ($use_driver == $driver) {
                return true;
            }
        }

        return false;
    }

    protected final function dsn($active) {
        $dbConf = $this->dbConf[$active];
        if (!$this->check_driver($dbConf['driver'])) {
            return false;
        }

        $this->active_driver = $dbConf['driver'];
        $dsn= sprintf('%s:', $dbConf['driver']);
        switch($dbConf['driver']) {
            case 'mysql':
            case 'pgsql':
                $dsn .= sprintf('host=%s;dbname=%s', $dbConf['hostname'], $dbConf['database']);
                break;
            case 'sqlsrv':
                $dsn .= sprintf('Server=%s;1433;Database=%s', $dbConf['hostname'], $dbConf['database']);
                if ($this->connection_pooling) {
                    $dsn .= ';ConnectionPooling=1';
                }
                
                if($this->connection_encrypting) {
                    $dsn .= ';Encrypt=1';
                }
                break;
        }

        return $dsn;
    }

    protected function getQueryType($qry) {
        list($type, ) = explode(" ", strtolower($qry), 2);
        return $type;
    }

    private function checkSQLConfig($section='master') {
        return (array_key_exists($section,$this->dbConf) &&
            is_array($this->dbConf[$section]) &&
            !empty($this->dbConf[$section]['driver']) &&
            !empty($this->dbConf[$section]['database']) &&
            !empty($this->dbConf[$section]['hostname']) &&
            !empty($this->dbConf[$section]['username']) &&
            !empty($this->dbConf[$section]['password']));
    }
}