<?php
/**
 * Created by PhpStorm.
 * User: Lucas
 * Date: 29.07.2016
 * Time: 21:26
 */

namespace kernel;

use phpFastCache\CacheManager;
use phpFastCache\Util;

/* block attempts to directly run this script */
if (getcwd() == dirname(__FILE__)) {
    die('block directly run');
}

class Cache extends CacheManager
{
    //Public Indexes
    const SETTINGS = 'hmcp_mem_settings';

    private $cache_index = null;

    function __construct() {
        Util\Languages::setEncoding("UTF-8");

        $this->cache_index['file'] = null;
        $this->cache_index['memory'] = null;
        $this->cache_index['net'] = null;

        //File Cache
        if(extension_loaded('Zend Data Cache') && function_exists('zend_disk_cache_store')) { //Zend Server
            common::$log['kernel']->addDebug('Initialize -> Cache -> Set Zend Disk Cache for Kernel File Cache');
            $this->cache_index['file'] = self::getInstance('zenddisk');
        } else {
            common::$log['kernel']->addDebug('Initialize -> Cache -> Set regular File Cache for Kernel File Cache');
            $this->cache_index['file'] = self::getInstance('files',array("path" => ROOT_PATH.'/cache/system'));
        }

        //Memory Cache
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            common::$log['kernel']->addDebug('Initialize -> Cache -> Windows Operating System found');
            if(extension_loaded('Zend Data Cache') && function_exists('zend_shm_cache_store')) { //Zend Server
                common::$log['kernel']->addDebug('Initialize -> Cache -> Set Zend Memory Cache for Kernel Memory Cache');
                $this->cache_index['memory'] = self::getInstance('zendshm');
            } else if(extension_loaded('wincache') && function_exists('wincache_ucache_set')) {
                common::$log['kernel']->addDebug('Initialize -> Cache -> Set Wincache for Kernel Memory Cache');
                $this->cache_index['memory'] = self::getInstance('wincache');
            } else if(extension_loaded('apcu') && ini_get('apc.enabled')) {
                common::$log['kernel']->addDebug('Initialize -> Cache -> Set APCU for Kernel Memory Cache');
                $this->cache_index['memory'] = self::getInstance('apcu');
            } else if(extension_loaded('apc') && ini_get('apc.enabled') && strpos(PHP_SAPI, 'CGI') === false) {
                common::$log['kernel']->addDebug('Initialize -> Cache -> Set APC for Kernel Memory Cache');
                $this->cache_index['memory'] = self::getInstance('apc');
            } else if(extension_loaded('xcache') && function_exists('xcache_get')) {
                common::$log['kernel']->addDebug('Initialize -> Cache -> Set XCache for Kernel Memory Cache');
                $this->cache_index['memory'] = self::getInstance('xcache');
            } else {
                common::$log['kernel']->addAlert('Initialize -> Cache -> No Kernel Memory Cache module loaded!');
                $this->cache_index['memory'] = null;
            }
        } else {
            common::$log['kernel']->addDebug('Initialize -> Cache -> Linux Operating System found');
            if(extension_loaded('Zend Data Cache') && function_exists('zend_shm_cache_store')) { //Zend Server
                common::$log['kernel']->addDebug('Initialize -> Cache -> Set Zend Memory Cache for Kernel Memory Cache');
                $this->cache_index['memory'] = self::getInstance('zendshm');
            } else if(extension_loaded('apcu') && ini_get('apc.enabled')) {
                common::$log['kernel']->addDebug('Initialize -> Cache -> Set APCU for Kernel Memory Cache');
                $this->cache_index['memory'] = self::getInstance('apcu');
            } else if(extension_loaded('apc') && ini_get('apc.enabled') && strpos(PHP_SAPI, 'CGI') === false) {
                common::$log['kernel']->addDebug('Initialize -> Cache -> Set APC for Kernel Memory Cache');
                $this->cache_index['memory'] = self::getInstance('apc');
            } else if(extension_loaded('xcache') && function_exists('xcache_get')) {
                common::$log['kernel']->addDebug('Initialize -> Cache -> Set XCache for Kernel Memory Cache');
                $this->cache_index['memory'] = self::getInstance('xcache');
            } else {
                common::$log['kernel']->addAlert('Initialize -> Cache -> No Kernel Memory Cache module loaded!');
                $this->cache_index['memory'] = null;
            }
        }

        //Network Memory Cache (NetCache)
        $ini_conf = common::$ini_config['kernel'];
        foreach ($ini_conf->getSections() as $section) {
            $is_redis = false; $is_ssdb = false;
            if(($is_memcache = ($section == 'memcache')) || ($is_redis = ($section == 'redis')) || ($is_ssdb = ($section == 'ssdb'))) { //Single
                common::$log['kernel']->debug('Initialize -> Cache -> Use single cache method for Kernel Network Cache');
                $server = $ini_conf->getSection(($is_memcache ? 'memcache' : ($is_redis ? 'redis' : ($is_ssdb ? 'ssdb' : false))));
                if($is_memcache && function_exists('memcache_connect')) {
                    common::$log['kernel']->debug('Initialize -> Cache -> Use Memcache for Kernel Network Cache');
                    $this->cache_index['net'] = self::getInstance('memcache',array('memcache' => array($server['memcache_host'],
                                                                                                       $server['memcache_port'],
                                                                                                       $server['memcache_weight'])));
                } else if($is_memcache && class_exists('Memcached')) {
                    common::$log['kernel']->debug('Initialize -> Cache -> Use Memcached for Kernel Network Cache');
                    $this->cache_index['net'] = self::getInstance('memcached',array('memcache' => array($server['memcache_host'],
                                                                                                        $server['memcache_port'],
                                                                                                        $server['memcache_weight'])));
                } else if($is_redis && class_exists('Redis')) {
                    common::$log['kernel']->debug('Initialize -> Cache -> Use Redis for Kernel Network Cache');
                    $this->cache_index['net'] = self::getInstance('redis',array('redis' => array('host' => $server['redis_host'],
                                                                                                 'port' => $server['redis_port'],
                                                                                                 'password' => $server['redis_password'],
                                                                                                 'database' => $server['redis_database'],
                                                                                                 'timeout' => $server['redis_timeout'])));
                } else if($is_redis && class_exists("\\Predis\\Client")) {
                    common::$log['kernel']->debug('Initialize -> Cache -> Use PRedis for Kernel Network Cache');
                    $this->cache_index['net'] = self::getInstance('predis',array('redis' => array('host' => $server['redis_host'],
                                                                                                  'port' => $server['redis_port'],
                                                                                                  'password' => $server['redis_password'],
                                                                                                  'database' => $server['redis_database'],
                                                                                                  'timeout' => $server['redis_timeout'])));
                } else if($is_ssdb && class_exists('SimpleSSDB')) {
                    common::$log['kernel']->debug('Initialize -> Cache -> Use SimpleSSDB for Kernel Network Cache');
                    $this->cache_index['net'] = self::getInstance('ssdb',array('ssdb' => array('host' => $server['ssdb_host'],
                                                                                               'port' => $server['ssdb_port'],
                                                                                               'password' => $server['ssdb_password'],
                                                                                               'timeout' => $server['ssdb_timeout'])));
                }
                break;
            } else if(($is_memcache = ($section == 'memcache_0'))) { //Cluster
                common::$log['kernel']->debug('Initialize -> Cache -> Use cluster cache method for Kernel Network Cache');
                $server_array = array();
                for ($i = 0; $i <= 100; $i++) {
                    $server = $ini_conf->getSection(($is_memcache ? 'memcache_'.$i : false));
                    if($is_memcache && function_exists('memcache_connect')) {
                        $server_array[] = array($server['memcache_host'], $server['memcache_port'], $server['memcache_weight']);
                    } else if($is_memcache && class_exists('Memcached')) {
                        $server_array[] = array($server['memcache_host'], $server['memcache_port'], $server['memcache_weight']);
                    }
                }

                if($is_memcache && function_exists('memcache_connect')) {
                    common::$log['kernel']->debug('Initialize -> Cache -> Use Memcache Cluster for Kernel Network Cache');
                    $this->cache_index['net'] = self::getInstance('memcache',array('memcache' => $server_array));
                } else if($is_memcache && class_exists('Memcached')) {
                    common::$log['kernel']->debug('Initialize -> Cache -> Use Memcached Cluster for Kernel Network Cache');
                    $this->cache_index['net'] = self::getInstance('memcached',array('memcache' => $server_array));
                }
                
                break;
            }
        }
    }

    private function Get($type,$key) {
        if($this->cache_index[$type] != null) {
            $CachedItem = $this->cache_index[$type]->getItem($key);
            return $CachedItem->get($key);
        }

        return false;
    }

    private function Exists($type,$key) {
        if($this->cache_index[$type] != null) {
            $CachedItem = $this->cache_index[$type]->getItem($key);
            return !is_null($CachedItem->get($key));
        }

        return false;
    }

    private function Set($type,$key,$var,$ttl=600) {
        if($this->cache_index[$type] != null) {
            $CachedItem = $this->cache_index[$type]->getItem($key);
            $CachedItem->set($var)->expiresAfter($ttl);
            return $this->cache_index[$type]->save($CachedItem);
        }

        return false;
    }

    private function Delete($type,$key) {
        if($this->cache_index[$type] != null) {
            return $this->cache_index[$type]->delete($key);
        }

        return false;
    }

    //Public
    public function FileGet($key) {
        return $this->Get('file',$key);
    }

    public function FileExists($key) {
        return $this->Exists('file',$key);
    }

    public function FileSet($key,$var,$ttl=600) {
        return $this->Set('file',$key,$var,$ttl);
    }

    public function FileDelete($key) {
        return $this->Delete('file',$key);
    }

    public function MemGet($key) {
        return $this->Get('memory',$key);
    }

    public function MemExists($key) {
        return $this->Exists('memory',$key);
    }

    public function MemSet($key,$var,$ttl=600) {
        return $this->Set('memory',$key,$var,$ttl);
    }

    public function MemDelete($key) {
        return $this->Delete('memory',$key);
    }

    public function NetGet($key) {
        return $this->Get('net',$key);
    }

    public function NetExists($key) {
        return $this->Exists('net',$key);
    }

    public function NetSet($key,$var,$ttl=600) {
        return $this->Set('net',$key,$var,$ttl);
    }

    public function NetDelete($key) {
        return $this->Delete('net',$key);
    }

    public function AutoMemGet($key) {
        if($this->cache_index['net'] != null) {
            return $this->Get('net',$key);
        }

        return $this->Get('memory',$key);
    }

    public function AutoMemExists($key) {
        if($this->cache_index['net'] != null) {
            return $this->Exists('net', $key);
        }

        return $this->Exists('memory', $key);
    }

    public function AutoMemSet($key,$var,$ttl=600) {
        if($this->cache_index['net'] != null) {
            return $this->Set('net',$key,$var,$ttl);
        }

        return $this->Set('memory',$key,$var,$ttl);
    }

    public function AutoDelete($key) {
        if($this->cache_index['net'] != null) {
            return $this->Delete('net', $key);
        }

        return $this->Delete('memory', $key);
    }
}