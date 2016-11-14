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

/* block attempts to directly run this script */
if (getcwd() == dirname(__FILE__)) {
    die('block directly run');
}

final class api_encode {
    protected static $input = null;
    protected static $crypt_key = null;
    private static $options = array();

    public static final function init() {
        self::$options['encode_hex'] = true;
        self::$options['encode_gzip'] = true;
        self::$options['encode_crypt'] = true;
        self::$options['encode_base'] = true;
        return true;
    }

    public static final function set_options($key="",$var='') {
        if(array_key_exists($key, self::$options)) {
            self::$options[$key] = $var;
            return true;
        } return false;
    }

    public static final function client_encode_cryptkey($key="") {
        if(empty($key)) return false;
        self::$crypt_key = $key;
        return (empty(self::$crypt_key) ? false : true);
    }

    public static final function client_encode($input_array=array()) {
        if(empty($input_array) || !is_array($input_array)) return false;
        self::$input = $input_array;
        if(!self::encode_base()) return false;
        if(!self::encode_crypt()) return false;
        if(!self::encode_gzip()) return false;
        if(!self::encode_hex()) return false;
        return self::$input;
    }

    private static final function encode_base() {
        self::$input = self::$options['encode_base'] ? common::ArrayToJson(self::$input) : self::$input;
        return (empty(self::$input) || !self::$input ? false : true);
    }

    private static final function encode_crypt() {
        if(empty(self::$input)) return false;
        if(self::$options['encode_crypt']) self::data_encrypt();
        return (empty(self::$input) || !self::$input ? false : true);
    }

    private static final function encode_gzip() {
        if(empty(self::$input)) return false;
        self::$input = self::$options['encode_gzip'] ? gzcompress(self::$input) : self::$input;
        return (empty(self::$input) || !self::$input ? false : true);
    }

    private static final function encode_hex() {
        if(empty(self::$input)) return false;
        self::$input = self::$options['encode_hex'] ? bin2hex(self::$input) : self::$input;
        return (empty(self::$input) || !self::$input ? false : true);
    }

    private static function data_encrypt() {
        if(empty(self::$input)) return false;

        try {
            self::$input = \Crypto::Encrypt(self::$input, self::$crypt_key);
        } catch (\CryptoTestFailedException $ex) {
            die('Cannot safely perform encryption');
        } catch (\CannotPerformOperationException $ex) {
            die('Cannot safely perform decryption');
        }

        if(!empty(self::$input) && self::$input != false)
            return true;

        return false;
    }
}