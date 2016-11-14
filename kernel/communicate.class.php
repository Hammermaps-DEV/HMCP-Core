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
use Snoopy\Snoopy;

// block attempts to directly run this script
if (getcwd() == dirname(__FILE__)) {
    die('block directly run');
}

final class communicate extends common{
    private static $stream = null; //Hex + Control
    private static $data_stream = null; //Hex etc.
    private static $snoopy = null;
    private static $data = array();
    private static $options = array();
    private static $cryptkey = null;
    private static $apihost = null;
    private static $debug = array('status'=>false,'input'=>'','output'=>'');

    public static function init() {
        //=======================================
        // create a log file
        //=======================================
        self::$log['communicate'] = new Logger('Communicate');
        self::$log['communicate']->pushHandler(new StreamHandler(ROOT_PATH.'/logs/communicate.log', common::$logger_level));
        self::$snoopy = new Snoopy;
    }

    public static final function send($data='',$ttl=1,$debug=false) {
        self::$debug['status'] = $debug;
        self::$maxCache = self::ToInt($ttl);
        if(!self::fsockopen_support()) return false;
        if(!api_encode::init()) return false;
        if(!api_decode::init()) return false;
        self::$data = $data;

        if (self::$debug['status']) {
            self::$debug['output'] = self::ArrayToJson(self::$data);
        }

        if(!self::encode()) return false;
        if(!self::wire_control()) return false;
        if(!self::send_to_server()) return false;
        if(!self::read_control()) return false;
        if(!self::decode()) return false;

        if(self::$data != false && !empty(self::$data) &&
            is_array(self::$data)) {
                return self::$data;
        }

        return false;
    }

    public static function send_custom($url='') {
        self::$snoopy->fetch(self::ToString($url));
        return (empty(self::$snoopy->results) ||
            !self::$snoopy->results ? false :
                self::$snoopy->results);
    }

    public static function get_debug() {
        $output = self::$debug;
        unset($output['status']);
        return $output;
    }

    public static function set_api_url($host='',$port=80) {
        self::$apihost = self::ToString($host.':'.$port);
    }

    public static function set_api_cryptkey($cryptkey='') {
        self::$cryptkey = $cryptkey;
    }

    private static final function send_to_server() {
        $host_port = explode(':', self::$apihost);
        if(self::ping_port($host_port[0],$host_port[1])) {
            self::$log['communicate']->addDebug('API-Server is Online ->',$host_port);
            self::$log['communicate']->addDebug('Send to API-Server ->',array('DATA' => self::$stream,
                'SERVER' => 'http://'.self::$apihost.'/'));
            self::$snoopy->submit('http://'.self::$apihost.'/', array('input' => self::$stream));
            self::$stream = self::$snoopy->results;
            self::$log['communicate']->addDebug('Received from API-Server ->',array('DATA' => self::$stream,
                'SERVER' => 'http://'.self::$apihost.'/'));

            if(self::$debug['status']) {
                self::$debug['input'] = self::$stream;
            }

            if(!empty(self::$stream)) {
                return true;
            }
        } else {
            self::$log['communicate']->addAlert('API-Server is Offline ->',$host_port);
            return false;
        }
    }

    private static final function encode() {
        if(!empty(self::$cryptkey)) {
            api_encode::client_encode_cryptkey(self::$cryptkey);
        }

        self::$options['encode_hex'] = true;
        self::$options['encode_gzip'] = true;
        self::$options['encode_crypt'] = !empty(self::$cryptkey) ? true : false;
        self::$options['encode_base'] = is_array(self::$data) ? true : false;
        self::$options['file_stream'] = false;

        //Encode
        api_encode::set_options('encode_hex',self::$options['encode_hex']);
        api_encode::set_options('encode_gzip',self::$options['encode_gzip']);
        api_encode::set_options('encode_crypt',self::$options['encode_crypt']);
        api_encode::set_options('encode_base',self::$options['encode_base']);
        self::$log['communicate']->addDebug('API-Communicate Send ->',array(self::$data));
        self::$data_stream = api_encode::client_encode(self::$data);  self::$data = null;
        return (!empty(self::$data_stream) && self::$data_stream != false ? true : false);
    }

    private static final function decode() {
        if(self::$options['decode_crypt']) {
            api_decode::client_decode_cryptkey(self::$cryptkey);
        }

        api_decode::set_options('decode_hex',self::$options['decode_hex']);
        api_decode::set_options('decode_gzip',self::$options['decode_gzip']);
        api_decode::set_options('decode_crypt',self::$options['decode_crypt']);
        api_decode::set_options('decode_base',self::$options['decode_base']);
        self::$data = api_decode::client_decode(self::$data_stream); self::$data_stream = null;
        self::$log['communicate']->addDebug('API-Communicate Received ->',array(self::$data));
        return (empty(self::$data) || !self::$data ? false : true);
    }

    private static final function wire_control() {
        self::$stream =
            (self::$options['encode_hex'] ? '1' : '0').'|'. // Hex
            (self::$options['encode_gzip'] ? '1' : '0').'|'. // GZip
            (self::$options['encode_crypt'] ? '1' : '0').'|'. // Crypt
            (self::$options['encode_base'] ? '1' : '0').'|'. // JSON
            (self::$options['file_stream'] ? '1' : '0').'|'. // File Stream
            self::$data_stream; // Data
        self::$data_stream = null;
        return (!empty(self::$stream) && self::$stream != false);
    }

    private static final function read_control() {
        $data = explode('|', self::$stream, 6);
        self::$options['decode_hex'] = common::IntToBool($data[0]);
        self::$options['decode_gzip'] = common::IntToBool($data[1]);
        self::$options['decode_crypt'] = common::IntToBool($data[2]);
        self::$options['decode_base'] = common::IntToBool($data[3]);
        self::$options['file_stream'] = common::IntToBool($data[4]);
        self::$data_stream = $data[5]; unset($data); self::$stream = null;
        return (!empty(self::$data_stream) && self::$data_stream != false ? true : false);
    }
}