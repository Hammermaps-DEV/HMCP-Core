<?php
/**
 * Created by PhpStorm.
 * User: Lucas
 * Date: 02.07.2016
 * Time: 00:02
 */

namespace kernel;

/* block attempts to directly run this script */
if (getcwd() == dirname(__FILE__)) {
    die('block directly run');
}

/**
 * Class settings
 * @package kernel
 */
class settings extends common
{
    private static $ini_conf = null;
    private static $settings_current = array();
    private static $settings_database = array();

    /**
     * settings constructor.
     */
    public function __construct() {
        self::$ini_conf = self::$ini_config['kernel'];
        settings::load();

       // settings::set('test123',true,false,true);
      //  settings::set('test123',true,false,false);

        //var_export(settings::get('test123'));

       // settings::save();

     //   echo '<pre>';
     //   var_dump(settings::$settings_current);
      //  die();
    }

    public function __destruct() {
        settings::save();
    }

    /**
     * Get a Setting from Kernel
     * @param string $key
     * @param bool $default
     * @return bool|data
     */
    public static function get($key=null,$default=false) {
        if (array_key_exists($key, self::$settings_current)) {
            return ($default ? self::$settings_current[$key]['default'] :
                self::$settings_current[$key]['data']);
        } else {
            self::$log['kernel']->addAlert('settings::get() Error: Key not exists! ->',
                array('Key:' => $key, 'Get Default:' => $default));
        }

        return false;
    }

    /**
     * Set a Setting to Kernel for Usage & Save
     * @param string $key
     * @param mixed $var
     * @param bool $direct_save
     * @param bool $default
     */
    public static function set($key=null,$var=null,$direct_save=false,$default=false) {
        $index = array();
        if (array_key_exists($key, self::$settings_current)) {
            $index = self::$settings_current[$key];
        }

        if (is_array($var)) {
            $index['array'] = true;
            foreach ($var as $key_sub => $var_sub) {
                $index['data'][$key_sub] = $var_sub;
            }
            $index['updated'] = true;
            if ($default || !array_key_exists('default',$index)) {
                $subindex = $index['default'];
                foreach ($var as $key_sub => $var_sub) {
                    $subindex[$key_sub] = $var_sub;
                }
                $index['default'] = $subindex;
            }
        } else {
            $index['array'] = false;
            $index['data'] = $var;
            if ($default || !array_key_exists('default',$index)) {
                $index['default'] = $var;
            }
            $index['updated'] = true;
        }

        self::$settings_current[$key] = $index;

        if($direct_save) {
            self::save();
        }
    }

    /**
     * Load Config from Database
     */
    public static function load() {
        if(!self::$cache->AutoMemExists(Cache::SETTINGS) || !self::$ini_conf->getKey('kernel_settings_cache','cache')) { //Check in Network or Memory Cache
            self::$settings_database = \kernel\common::$database->select("SELECT `key`,`var`,`default`,`type` FROM `{prefix_settings}`;");
            foreach (self::$settings_database as $setting) {
                $setting = self::decode($setting); //Decode
                self::$settings_current[\kernel\common::UTF8_Reverse($setting['key'])] = array(
                    'array' => (strtolower($setting['type']) == 'array'),
                    'data' => $setting['var'],
                    'default' => $setting['default'],
                    'updated' => false);
            }

            if(self::$ini_conf->getKey('kernel_settings_cache','cache')) {
                self::$cache->AutoMemSet(Cache::SETTINGS, self::$settings_current,
                    self::$ini_conf->getKey('kernel_settings_cache_ttl','cache'));//Set in Network or Memory Cache
            }
        } else {
            self::$settings_current = self::$cache->AutoMemGet(Cache::SETTINGS); //Get from Network or Memory Cache
        }
    }

    /**
     * Save Config to Database
     * @param bool $all
     */
    public static function save($all=false) {
        foreach (self::$settings_current as $key => $data) {
            if($data['updated'] || $all) {
                if($data['array']) {
                    $data['data'] = \kernel\common::ArrayToString($data['data']);
                    $data['default'] = \kernel\common::ArrayToString($data['default']);
                }

                if($data['array']) {
                    $type = 'array';
                } else if(is_integer($data['data'])) {
                    $type = 'int';
                    $data['data'] = \kernel\common::ToInt($data['data']);
                    $data['default'] = \kernel\common::ToInt($data['default']);
                } else if(is_bool($data['data'])) {
                    $type = 'boolean';
                    $data['data'] = \kernel\common::ToInt($data['data']);
                    $data['default'] = \kernel\common::ToInt($data['default']);
                } else {
                    $type = 'string';
                }

                if(\kernel\common::$database->rows("SELECT `id` FROM `{prefix_settings}` WHERE `key` = ?;",array(\kernel\common::UTF8($key)))) {
                    \kernel\common::$database->update('UPDATE `{prefix_settings}` SET `var` = ?, `default` = ?, `type` = ? WHERE `key` = ?;',
                        array(\kernel\common::UTF8($data['data']),\kernel\common::UTF8($data['default']),$type,\kernel\common::UTF8($key)));
                } else {
                    \kernel\common::$database->insert('INSERT INTO `{prefix_settings}` SET `key` = ?, `var` = ?, `default` = ?, `type` = ?;',
                        array(\kernel\common::UTF8($key),\kernel\common::UTF8($data['data']),\kernel\common::UTF8($data['default']),$type));
                }

                self::$settings_current[$key]['updated'] = false;
            }
        }

        //Update the Cache
        if(self::$ini_conf->getKey('kernel_settings_cache','cache')) {
            self::$cache->AutoMemSet(Cache::SETTINGS, self::$settings_current,
                self::$ini_conf->getKey('kernel_settings_cache_ttl','cache'));//Set in Network or Memory Cache
        }
    }

    /**
     * Remove a Setting from Kernel & Database
     * @param $key
     */
    public static function remove($key) {
        $unset = false;
        if(array_key_exists($key,self::$settings_current)) {
            unset(self::$settings_current[$key]);
            $unset = true;
        }

        //Update the Cache
        if(self::$ini_conf->getKey('kernel_settings_cache','cache')) {
            self::$cache->AutoMemSet(Cache::SETTINGS, self::$settings_current,
                self::$ini_conf->getKey('kernel_settings_cache_ttl','cache'));//Set in Network or Memory Cache
        }

        if(\kernel\common::$database->rows("SELECT `id` FROM `{prefix_settings}` WHERE `key` = ?;",
            array(\kernel\common::UTF8($key)))) {
            return \kernel\common::$database->delete('DELETE FROM `{prefix_settings}` WHERE `key` = ?;',
                array(\kernel\common::UTF8($key)));
        }

        if(!$unset) {
            self::$log['kernel']->addAlert('settings::remove() Error: Key not exists! ->',
                array('Key:' => $key));
        }

        return false;
    }

    /**
     * Reset var to default
     * @param $key
     */
    public static function reset($key) {
        if(array_key_exists($key,self::$settings_current)) {
            self::$settings_current[$key]['data'] = self::$settings_current[$key]['default'];

            //Update the Cache
            if (self::$ini_conf->getKey('kernel_settings_cache', 'cache')) {
                self::$cache->AutoMemSet(Cache::SETTINGS, self::$settings_current,
                    self::$ini_conf->getKey('kernel_settings_cache_ttl', 'cache'));//Set in Network or Memory Cache
            }
        } else {
            self::$log['kernel']->addAlert('settings::reset() Error: Key not exists! ->',
                array('Key:' => $key));
        }
    }

    /************************
     * Private
     ************************/

    /**
     * Decode Config from Database for Kernel
     * @param array $setting
     * @return array
     */
    private static function decode(array $setting) {
        $setting['var'] = \kernel\common::UTF8_Reverse($setting['var']);
        $setting['default'] = \kernel\common::UTF8_Reverse($setting['default']);
        switch(strtolower($setting['type'])) {
            case 'array':
                $setting['var'] = \kernel\common::StringToArray($setting['var']);
                $setting['default'] = \kernel\common::StringToArray($setting['default']);
            break;
            case 'int':
                $setting['var'] = \kernel\common::ToInt($setting['var']);
                $setting['default'] = \kernel\common::ToInt($setting['default']);
            break;
            case 'boolean':
                $setting['var'] = \kernel\common::IntToBool($setting['var']);
                $setting['default'] = \kernel\common::IntToBool($setting['default']);
            break;
            default:
                $setting['var'] = \kernel\common::ToString($setting['var']);
                $setting['default'] = \kernel\common::ToString($setting['default']);
            break;
        }

        return $setting;
    }
}