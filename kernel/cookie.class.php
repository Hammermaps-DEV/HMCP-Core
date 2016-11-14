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

final class cookie {
    private $cname = "";
    private $val = array();
    private $expires;
    private $dir = '/';
    private $domain = '';
    private $secure = false;

    /**
    * Setzt die Werte fur ein Cookie und erstellt es.
    */
    public function cookie($cname, $cexpires=false, $cdir=false, $cdomain=false, $csecure=false) {
        $this->cname = $cname;
        $this->secure = $csecure;
        $this->expires = ($cexpires ? (time()+$cexpires) : (time()+(60*60*24*30)));
        $this->dir = (empty($cdir) || !$cdir ? '/' : cookie_dir);
        $this->domain = (empty($cdomain) || !$cdomain ? '' : cookie_domain);
        $this->val = array();
        $this->extract();
    }

    /**
    * Extraktiert ein gespeichertes Cookie
    */
    public function extract($cname="") {
        $cname=(empty($cname) ? $this->cname : $cname);
        if(!empty($_COOKIE[$cname])) {
            $arr = explode('|',$_COOKIE[$cname]);
            if($arr!==false && is_array($arr)) {
                foreach ($arr as $data) {
                    $keys = explode(':', $data, 3);
                    $this->val[utf8_decode($keys[0])] = intval($keys[2]) ?
                        json_decode(base64_decode(utf8_decode($keys[1])), true) : utf8_decode($keys[1]);
                    $_COOKIE[utf8_decode($keys[0])]=$this->val[utf8_decode($keys[0])];
                } unset($arr,$keys);
            }
        }
    }

    /**
    * Liest und gibt einen Wert aus dem Cookie zur�ck
    *
    * @return string
    */
    public function get($var) {
        if(!isset($this->val) || empty($this->val))
            return false;

        if(!array_key_exists($var, $this->val))
            return false;

        return $this->val[$var];
    }

    /**
    * Setzt ein neuen Key und Wert im Cookie
    */
    public function put($var, $value) {
        $value = (is_bool($value) ? intval($value) : $value);
        $this->val[$var]=$value;
        $_COOKIE[$var]=$this->val[$var];
        if(empty($value)) unset($this->val[$var]);
        return true;
    }

    /**
    * Leert das Cookie
    */
    public function clear() {
        $this->val=array();
        return $this->save();
    }

    /**
    * Loscht einen Wert aus dem Cookie
    */
    public function delete($var=null) {
        if(array_key_exists($var,$this->val)) {
            unset($this->val[$var]);
            return $this->save();
        } else {
            return false;
        }
    }
    
    /**
    * Speichert das Cookie
    */
    public function save() {
        if(empty($this->val) || !count($this->val) || empty($this->cname))
            return false;

        $cookie_val = '';
        if(count($this->val)) {
            foreach ($this->val as $key => $data) {
                $save = (is_array($data) ? base64_encode(json_encode($data)) : $data);
                $cookie_val .= utf8_encode($key).':'.utf8_encode($save).':'.(is_array($data)).'|';
            }

            $cookie_val = substr($cookie_val, 0, -1);
        }

        if(strlen($cookie_val)>4*1024)
            trigger_error("The cookie ".$this->cname." exceeds the specification for the maximum cookie size.  Some data may be lost", E_USER_WARNING);

        $this->secure = ($this->secure && array_key_exists("HTTPS",$_SERVER)); //disabled when no use secure protocol
        return setcookie($this->cname, $cookie_val, $this->expires, $this->dir, $this->domain, $this->secure);
    }
}