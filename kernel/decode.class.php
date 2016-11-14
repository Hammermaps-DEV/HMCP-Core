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

final class api_decode {
    protected static $crypt_key = null;
    protected static $output = null;
    private static $options = array();

    public static final function init() {
        self::$options['decode_hex'] = true;
        self::$options['decode_gzip'] = true;
        self::$options['decode_crypt'] = true;
        self::$options['decode_base'] = true;
        return true;
    }

    public static final function client_decode_cryptkey($key="") {
        if(empty($key)) return false;
        self::$crypt_key = $key;
        return (empty(self::$crypt_key) ? false : true);
    }

    public static final function set_options($key="",$var='') {
        if(array_key_exists($key, self::$options)) {
            self::$options[$key] = $var;
            return true;
        } return false;
    }

    public static final function client_decode($hex_stream=null) {
        self::$output = $hex_stream;
        if(empty(self::$output)) return false;
        if(!self::decode_hex()) return false;
        if(!self::decode_gzip()) return false;
        if(!self::decode_crypt()) return false;
        if(!self::decode_base()) return false;
        return self::$output;
    }

    private static final function decode_base() {
        self::$output = self::$options['decode_base'] ? common::JsonToArray(self::$output) : self::$output;
        return (empty(self::$output) || !is_array(self::$output) || !self::$output ? false : true);
    }

    private static final function decode_crypt() {
        if(empty(self::$output)) return false;
        if(self::$options['decode_crypt']) self::data_decrypt();
        return (empty(self::$output) ? false : true);
    }

    private static final function decode_gzip() {
        if(empty(self::$output)) return false;
        self::$output = self::$options['decode_gzip'] ? gzuncompress(self::$output) : self::$output;
        return (empty(self::$output) || !self::$output ? false : true);
    }

    private static final function decode_hex() {
        if(empty(self::$output)) return false;
        self::$output = self::$options['decode_hex'] ? hex2bin(self::$output) : self::$output;
        return (empty(self::$output) || !self::$output ? false : true);
    }

    public static function data_decrypt() {
        if(empty(self::$output)) return false;

        try {
            self::$output = \Crypto::Decrypt(self::$output, self::$crypt_key);
        } catch (\InvalidCiphertextException $ex) {
            die('DANGER! DANGER! The ciphertext has been tampered with!');
        } catch (\CryptoTestFailedException $ex) {
            die('Cannot safely perform encryption');
        } catch (\CannotPerformOperationException $ex) {
            die('Cannot safely perform decryption');
        }

        self::$output = trim(self::$output);
        if(!empty(self::$output) && self::$output != false )
            return true;

        return false;
    }
}