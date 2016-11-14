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

final class IniFile {
    private $iniFilename = '';
    private $iniFileStream = '';
    private $iniFileExists = false;
    private $iniLastSection = 'global';

    /**
     * IniFile constructor.
     * @param string $input
     */
    public function __construct($input = '') {
        $this->iniFileExists = false;
        if(!empty($input) && file_exists($input)) {
            $this->iniFileExists = true;
            $this->iniFilename = $input;
        }

        if(!empty($input)) {
            $this->iniFilename = $input;
        }
    }

    /**
     * Read and Load the INI-File
     * @return bool
     */
    public function read() {
        if(!empty($this->iniFilename)) {
            if ($this->iniFileExists) {
                $this->iniFileStream =
                    parse_ini_file($this->iniFilename, true, INI_SCANNER_TYPED);
            } else {
                $this->iniFileStream =
                    parse_ini_string($this->iniFilename, true, INI_SCANNER_TYPED);
            }

            return (!empty($this->iniFileStream) && $this->iniFileStream && is_array($this->iniFileStream));
        }

        return false;
    }

    /**
     * Save INI to File
     * @return bool
     */
    public function save() {
        $ini = "";
        foreach ($this->iniFileStream as $section => $datas) {
            $ini .= "[".$section."]"."\n";
            foreach ($datas as $key => $var) {
                if(is_integer($var)) {
                    $ini .= $key." = ".intval($var)."\n";
                } else if(is_bool($var)) {
                    $ini .= $key." = ".(intval($var) ? 'true' : 'false')."\n";
                } else {
                    $ini .= $key." = \"".utf8_encode($var)."\""."\n";
                }
            } $datas = array();
            $ini .= "\n";
        } unset($datas,$section,$key,$var);

        if(file_put_contents($this->iniFilename,$ini)) {
            $this->iniFileExists = true;
            return true;
        }
    }

    /**
     * Get Keys from a section
     * @param string $section
     * @return bool/string
     */
    public function getSection($section = '',$array = array()) {
        $section = (empty($section) ? $this->iniLastSection : $section);
        if(!empty($this->iniFilename) && is_array($this->iniFileStream)) {
            return (array_key_exists($section, $this->iniFileStream) ?
                array_merge($this->iniFileStream[$section],$array) : false);
        }

        return false;
    }

    /**
     * Get Sections from a INI
     * @return int
     */
    public function getSections() {
        if(!empty($this->iniFilename) && is_array($this->iniFileStream)) {
            $sections = array();
            foreach($this->iniFileStream as $section => $data) {
                $sections[] = $section;
            }

            return $sections;
        }

        return array();
    }

    /**
     * Get a Key
     * @param string $key
     * @param string $section
     * @return bool/var
     */
    public function getKey($key = '', $section = '') {
        $section = (empty($section) ? $this->iniLastSection : $section);
        if(!empty($this->iniFilename)) {
            if (!($section = $this->getSection($section))) {
                return false;
            }

            return array_key_exists($key, $section) ? $section[$key] : false;
        }

        return false;
    }

    /**
     * Add a new section or use a section
     * @param string $section
     * @return bool
     */
    public function setSection($section = 'global') {
        $this->iniLastSection = ($section != 'global' &&
            !empty($section) ? $section : 'global');
        if(is_array($this->iniFileStream) &&
            array_key_exists($section, $this->iniFileStream)) {
            return false;
        }

        $this->iniFileStream[$section] = [];
        return true;
    }

    /**
     * Set a new Key and Variable
     * @param string $key
     * @param string $var
     * @param string $section
     * @return bool
     */
    public function setKey($key = '', $var = '', $section = '') {
        $section = (empty($section) ? $this->iniLastSection : $section);
        if (!array_key_exists($section, $this->iniFileStream)) {
            return false;
        }

        if(array_key_exists($key,$this->iniFileStream[$section])) {
            return $this->replaceKey($key,$var,$section);
        } else {
            $this->iniFileStream[$section][$key] = $var;
            return true;
        }
    }

    /**
     * Replace a exists Key / Variable
     * @param string $key
     * @param string $var
     * @param string $section
     * @return bool
     */
    public function replaceKey($key = '',  $var = '', $section = '') {
        $section = (empty($section) ? $this->iniLastSection : $section);
        if(!array_key_exists($section, $this->iniFileStream)) {
            return false;
        }

        if(array_key_exists($key,$this->iniFileStream[$section])) {
            $this->iniFileStream[$section][$key] = $var;
            return true;
        }

        return false;
    }
}
