<?php
/**
 * Created by PhpStorm.
 * User: Lucas
 * Date: 31.10.2016
 * Time: 21:40
 */

namespace module\core;

class userips extends module
{
    private static $index = array();

    /**
     * Laden des UserIP Containers
     * @param int $userid
     */
    public static function load(int $userid) { //is 0 return false
        if(!count(self::$index)) {
            $last_ips = self::$database->fetch('SELECT `lc_last_ips` FROM `{prefix_users}` WHERE `id` = ?;', array($userid), 'lc_last_ips');
            if (self::$database->rowCount()) {
                self::$index = self::StringToArray($last_ips);
            }
        }

        if(!count(self::$index)) {
            self::$index[time()] = self::getIp()['ip'];
        }

        krsort(self::$index);
    }

    /**
     * Gibt die aktuelle UserIP zurück
     * @return mixed
     */
    public static function get() {
        $user_live_ip = self::getIp();
        foreach (self::$index as $time => $ip) {
            if($ip == $user_live_ip['ip']) {
                self::$index[time()] = $user_live_ip['ip']; //Update
                unset(self::$index[$time]);
                krsort(self::$index);
                return $user_live_ip['ip'];
            }
        }

        self::$index[time()] = $user_live_ip['ip'];
        krsort(self::$index);
        return $user_live_ip['ip'];
    }

    /**
     * Prüft eine IP ob diese in der Datenbank gespeichert ist
     * @param $check_ip
     * @return bool
     */
    public static function check($check_ip) {
        foreach (self::$index as $time => $ip) {
            if($ip == $check_ip) {
                self::$index[time()] = $ip; //Update
                unset(self::$index[$time]);
                krsort(self::$index);
                return true;
            }
        }

        return false;
    }

    /**
     * Gibt den gesamten Index der IPs zurück
     * @return mixed
     */
    public static function getIndex() {
        self::gc(); //Cleanup
        $user_live_ip = self::getIp(); $run = false;
        foreach (self::$index as $time => $ip) {
            if($ip == $user_live_ip['ip']) {
                self::$index[time()] = $user_live_ip['ip']; //Update
                unset(self::$index[$time]);
                $run = true;
                break;
            }
        }

        if(!$run)
            self::$index[time()] = $user_live_ip['ip'];

        return self::ArrayToString(self::$index);
    }

    /**
     * Clanup IPs
     */
    private static function gc() {
        foreach (self::$index as $time => $ip) {
            if(($time + userspace::$config['IPGC']) <= time()) {
                unset(self::$index[$time]);
            }
        }
    }
}