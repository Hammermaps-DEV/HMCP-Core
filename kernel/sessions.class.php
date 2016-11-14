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
/* block attempts to directly run this script */
if (getcwd() == dirname(__FILE__)) {
    die('block directly run');
}

final class session {
    protected $memcached;
    protected $_ttl = 3600;
    protected $_lockTimeout = 10;
    public static $securityKey_mcrypt;

    function __construct() {
        switch(strtolower(\kernel\common::$ini_config['kernel']->getKey('backend','session'))) {
            case 'memcache':
                \kernel\common::$log['session']->addDebug('Use Memcache for Sessions ->',array(__FUNCTION__,__LINE__));
                session_set_save_handler(array($this, 'mem_open'), array($this, 'mem_close'), array($this, 'mem_read'), array($this, 'mem_write'), array($this, 'mem_destroy'), array($this, 'mem_gc'));
                register_shutdown_function('session_write_close');
            break;
            case 'mysql':
                \kernel\common::$log['session']->addDebug('Use MySQL for Sessions ->',array(__FUNCTION__,__LINE__));
                session_set_save_handler(array($this, 'sql_open'), array($this, 'sql_close'), array($this, 'sql_read'), array($this, 'sql_write'), array($this, 'sql_destroy'), array($this, 'sql_gc'));
                register_shutdown_function('session_write_close');
            break;
            default:
                \kernel\common::$log['session']->addDebug('Use PHP-Default for Sessions ->',array(__FUNCTION__,__LINE__));
        }
    }

    public final function init($destroy=false) {
        if(!headers_sent() && !$this->is_session_started()) {
            \kernel\common::$log['session']->addDebug('Call session_start() ->',array(__FUNCTION__,__LINE__));
            $this->_skcrypt = self::$securityKey_mcrypt;
            if(session_start()) {
                \kernel\common::$log['session']->addDebug('Sessions started, ready to use! ->',array(__FUNCTION__,__LINE__));
            }
        } else {
            \kernel\common::$log['session']->addAlert('Sessions can not started! ->',array(__FUNCTION__,__LINE__));
            if(headers_sent()) {
                \kernel\common::$log['session']->addAlert('Headers already sent! ->',array(__FUNCTION__,__LINE__));
            }
        }

        if($destroy) {
            \kernel\common::$log['session']->addDebug('Sessions destroy & Regenerate ->',array(__FUNCTION__,__LINE__));
            session_unset(); session_destroy(); session_start();
        }

        return true;
    }

    ###################################################
    ################ Memcache Backend #################
    ###################################################
    public final function mem_open() {
        \kernel\common::$log['session']->addDebug('Connect to Memcache Server ->',
            array(\kernel\common::$ini_config['kernel']->getKey('memcache_host','session'),
                \kernel\common::$ini_config['kernel']->getKey('memcache_port','session')));
        if($this->memcached instanceOf \Memcache) {
            return true;
        }

        $this->memcached = new \Memcache();
        $this->memcached->addServer(\kernel\common::$ini_config['kernel']->getKey('memcache_host','session'),
            \kernel\common::$ini_config['kernel']->getKey('memcache_port','session'));

        if(!$this->memcached->getServerStatus(\kernel\common::$ini_config['kernel']->getKey('memcache_host','session'),
            \common::$ini_config['kernel']->getKey('memcache_port','session'))) {

            \kernel\common::$log['session']->addAlert('Connect to Memcache Server failed! ->',array(__FUNCTION__,__LINE__));
            return false;
        } else {
            \common::$log_session->addDebug('Connected to Memcache Server ->',array(__FUNCTION__,__LINE__));
            return true;
        }

        return false;
    }

    public final function mem_close() {
        \kernel\common::$log['session']->addDebug('Disconnect Memcache Server ->',array(__FUNCTION__,__LINE__));
        return $this->memcached->close();
    }

    public final function mem_read($id) {
        $data = $this->memcached->get($id);
        if(empty($data)) return '';
        \kernel\common::$log['session']->addDebug('Session-Data from Memcache ID / Data->',array($id,$data));
        return $data;
    }

    public final function mem_write($id, $data) {
        \kernel\common::$log['session']->addDebug('Write Session-Data to Memcache ID / Data ->',array($id,$data));
        $result = $this->memcached->replace($id, $data, true, \kernel\common::$ini_config['kernel']->getKey('ttl_maxtime','session'));
        if( $result == false )
            $result = $this->memcached->set($id, $data, true, \kernel\common::$ini_config['kernel']->getKey('ttl_maxtime','session'));

        return $result;
    }

    public final function mem_destroy($id) {
        return $this->memcached->delete($id);
    }

    public final function mem_gc($max) {
        return true;
    }

    ###################################################
    ################## MySQL Backend ##################
    ###################################################
    public final function sql_open() {
        return true;
    }

    public final function sql_close() {
        return true;
    }

    public final function sql_read($id) {
        if (\kernel\common::$database instanceOf \kernel\database) {
            $data = \kernel\common::$database->fetch("SELECT `data` FROM `{prefix_sessions}` WHERE `ssid` = ? LIMIT 1;",array($id),'data');
            if(!$this->db->rowCount()) { return ''; }
            if (empty($data)) { return ''; }
            $data = \Crypto::Decrypt($data, $this->_skcrypt);
            \kernel\common::$log['session']->addDebug('Read Session-Data from Database ID / Data ->',array($id,$data));
            return $data;
        }

        return '';
    }

    public final function sql_write($id, $data) {
        \kernel\common::$log['session']->addDebug('Write Session-Data to Database ID / Data ->',array($id,$data));
        $data = \Crypto::Encrypt($data, $this->_skcrypt);
        if (\kernel\common::$database instanceOf \kernel\database) {
            $time = time();
            \kernel\common::$database->select("SELECT `id` FROM `{prefix_sessions}` WHERE `ssid` = ? LIMIT 1;",array($id));
            if(!\kernel\common::$database->rowCount()) {
                return \kernel\common::$database->insert("INSERT INTO `{prefix_sessions}` (id, ssid, time, data) VALUES (NULL, ?, ?, ?);",array($id, $time, $data));
            } else {
                return \kernel\common::$database->update("UPDATE `{prefix_sessions}` SET `time` = ?, `data` = ? WHERE `ssid` = ?;",array($time, $data, $id));
            }
        }

        return false;
    }

    public final function sql_destroy($id) {
        \kernel\common::$log['session']->addDebug('Call Session destroy ->',array($id));
        if (\kernel\common::$database instanceOf \kernel\database) {
            \kernel\common::$database->select("SELECT `id` FROM `{prefix_sessions}` WHERE `ssid` = ? LIMIT 1;", array($id));
            if (\kernel\common::$database->rowCount()) {
                return \kernel\common::$database->delete("DELETE FROM `{prefix_sessions}` WHERE `ssid` = ?;", array($id));
            }
        }
    }

    public final function sql_gc($max) {
        \kernel\common::$log['session']->addDebug('Call Garbage-Collection ->',array($max));
        $new_time = time() - $max;
        if (\kernel\common::$database instanceOf \kernel\database) {
            \kernel\common::$database->select("SELECT `id` FROM `{prefix_bsb_sessions}` WHERE `time` < " . $new_time . ";");
            if (\kernel\common::$database->rowCount()) {
                return \kernel\common::$database->delete("DELETE FROM `{prefix_bsb_sessions}` WHERE `time` < " . $new_time . ";");
            }
        }
    }
    
    ###################################################
    ##################### Private #####################
    ###################################################

    protected final function is_session_started() {
        if ( php_sapi_name() !== 'cli' ) {
            if (version_compare(phpversion(), '5.4.0', '>=')) {
                return session_status() === PHP_SESSION_ACTIVE ? true : false;
            } else {
                return session_id() === '' ? false : true;
            }
        }

        return false;
    }

    ###################################################
    ################## Static Public ##################
    ###################################################

    public static function get($key) {
        $data = null;
        if(strtolower(\kernel\common::$ini_config['kernel']->getKey('backend','session')) == 'php') {
            if(array_key_exists($key,$_SESSION)) {
                $data = \Crypto::Decrypt($_SESSION[$key], self::$securityKey_mcrypt);
            }
        } else {
            if(array_key_exists($key,$_SESSION)) {
                $data = $_SESSION[$key];
            } else {
                return null;
            }
        }

        if(!empty($data) && !is_null($data)) {
            $data = \kernel\common::StringToArray($data);
            if ($data['array']) {
                $data = json_decode($data['data'], true);
            } else {
                $data = $data['data'];
            }
        }

        return $data;
    }

    public static function set($key,$var) {
        if(is_array($var)) {
            $var = array('array' => true, 'data' => json_encode($var));
        } else {
            $var = array('array' => false, 'data' => $var);
        }

        $var = \kernel\common::ArrayToString($var);
        if(strtolower(\kernel\common::$ini_config['kernel']->getKey('backend','session')) == 'php') {
            $_SESSION[$key] = ($data = \Crypto::Encrypt($var, self::$securityKey_mcrypt));
            return ($_SESSION[$key] == $data);
        } else {
            $_SESSION[$key] = $var;
            return ($_SESSION[$key] == $var);
        }

        return false;
    }
    
    public static function delete($key) {
        if(array_key_exists($key,$_SESSION)) {
            unset($_SESSION[$key]);
        }
    }
}