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

final class XML {
    private static $xmlobj = array(array()); //XML

    /** XML Datei Laden */
    public static function openXMLfile($XMLTag,$XMLFile,$oneModule=false) {
        if(empty($XMLTag) || empty($XMLFile)) { return false; }
        if(file_exists(ROOT_PATH . $XMLFile) || !$oneModule) {
            if(!array_key_exists($XMLTag,self::$xmlobj)) {
                self::$xmlobj[$XMLTag]['xmlFile'] = $XMLFile;
                if(!$oneModule) {
                    if (!file_exists(ROOT_PATH . $XMLFile)) {
                        file_put_contents(ROOT_PATH . $XMLFile, '<?xml version="1.0"?><' . $XMLTag . '></' . $XMLTag . '>');
                    }
                }

                self::$xmlobj[$XMLTag]['objekt'] = simplexml_load_file(ROOT_PATH . $XMLFile);
                return (self::$xmlobj[$XMLTag]['objekt'] != false ? true : false);
            }

            return true;
        }

        return false;
    }

    /** XML Stream Laden */
    public static function openXMLStream($XMLTag,$XMLStream) {
        if(empty($XMLTag) || empty($XMLStream)) return false;
        if(!array_key_exists($XMLTag,self::$xmlobj)) {
            self::$xmlobj[$XMLTag]['xmlFile'] = $XMLStream;
            self::$xmlobj[$XMLTag]['objekt'] = simplexml_load_string($XMLStream);
            return (self::$xmlobj[$XMLTag]['objekt'] != false ? true : false);
        }

        return true;
    }

    /**
     * XML Wert auslesen
     * @return XMLObj / boolean
     */
    public static function getXMLvalue($XMLTag, $xmlpath) {
        if(empty($XMLTag) || empty($xmlpath)) { return false; }
        if(array_key_exists($XMLTag,self::$xmlobj)) {
            $xmlobj = self::$xmlobj[$XMLTag]['objekt']->xpath($xmlpath);
            return ($xmlobj) ? $xmlobj[0] : false;
        }

        return false;
    }

    /**
     * XML Werte ändern
     * @return boolean
     */
    public static function changeXMLvalue($XMLTag, $xmlpath, $xmlnode, $xmlvalue='') {
        if(empty($XMLTag) || empty($xmlpath) || empty($xmlnode)) return false;
        if(array_key_exists($XMLTag,self::$xmlobj)) {
            $xmlobj = self::$xmlobj[$XMLTag]['objekt']->xpath($xmlpath);
            $xmlobj[0]->{$xmlnode} = htmlspecialchars($xmlvalue);
            return true;
        }

        return false;
    }

    /**
     * Einen neuen XML Knoten hinzufügen
     * @return boolean
     */
    public static function createXMLnode($XMLTag, $xmlpath, $xmlnode, $attributes=array(), $text='') {
        if(empty($XMLTag) || empty($xmlpath) || empty($xmlnode)) return false;
        if(array_key_exists($XMLTag,self::$xmlobj)) {
            $xmlobj = self::$xmlobj[$XMLTag]['objekt']->xpath($xmlpath);
            $xmlobj2 = $xmlobj[0]->addChild($xmlnode, htmlspecialchars($text));
            foreach ($attributes as $attr => $value) {
                $xmlobj2->addAttribute($attr, htmlspecialchars($value));
            }

            return true;
        }

        return false;
    }

    /**
     * XML-Datei speichern
     * @return boolean
     */
    public static function saveXMLfile($XMLTag) {
        if(empty($XMLTag)) { return false; }
        if(!array_key_exists($XMLTag,self::$xmlobj)) {
            return false;
        }

        $xmlFileValue = self::$xmlobj[$XMLTag]['objekt']->asXML();
        file_put_contents(ROOT_PATH . self::$xmlobj[$XMLTag]['xmlFile'], $xmlFileValue);
        return true;
    }

    /**
     * Einen XML Knoten löschen
     * @return boolean
     */
    public static function deleteXMLnode($XMLTag, $xmlpath, $xmlnode) {
        if(empty($XMLTag) || empty($xmlpath) || empty($xmlnode)) return false;
        if(array_key_exists($XMLTag,self::$xmlobj)) {
            $parent = self::getXMLvalue($XMLTag, $xmlpath);
            unset($parent->$xmlnode);
            return true;
        }

        return false;
    }

    /**
     * Einen XML Knoten Attribut löschen
     * @return boolean
     */
    public static function deleteXMLattribut($XMLTag, $xmlpath, $key, $value ) {
        if(empty($XMLTag) || empty($xmlpath) || empty($key) || empty($value)) { return false; }
        if(array_key_exists($XMLTag,self::$xmlobj)) {
            $nodes = self::getXMLvalue($XMLTag, $xmlpath);
            foreach($nodes as $node) {
                if((string)$node->attributes()->$key==$value) {
                    unset($node[0]);
                    break;
                }
            }

            return true;
        }

        return false;
    }
}