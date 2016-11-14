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
use phpFastCache\CacheManager;
use phpFastCache\Util;
use \Composer\Autoload as composer;
use PHPMailer\PHPMailer\PHPMailer;
use html2text;

// set root path
define('ROOT_PATH', str_replace('\\', '/', dirname(dirname(__FILE__).'../')));

// block attempts to directly run this script
if (getcwd() == dirname(__FILE__)) {
    die('block directly run');
}

// minimum php version
if (version_compare(PHP_VERSION, '7.0.0', 'lt')) {
    die('<b>PHP 7.0+ is required!</b>');
}

/**
 * common main class
 */
class common {
    /* PUBLIC */
    public static $gump = null;
    public static $log = array();
    public static $cache = null;
    public static $cookie = null;
    public static $database = null;
    public static $instance = null;
    public static $smarty = null;
    public static $router = null;
    public static $versions = array();
    public static $composer = array();
    public static $input_get = array();
    public static $input_post = array();
    public static $logger_level = Logger::DEBUG;
    public static $debug_config = array(); //Debugger INI-Config
    public static $route_collector = null;
    public static $controllers = array();
    public static $template_index = array();
    public static $ini_config = array();
    public static $language_index = array();
    public static $no_router = false;
    public static $smarty_cache_group = null;
    public static $smartyCacheId = null;
    public static $phpmailer = null;

    /* Error Fix */
    public static $sessions_re_initialize = false;

    /* APIs */
    public static $is_jsonapi = false;
    public static $is_cronjob = false;

    /* PRIVATE */
    //Version of Kernel
    private static $kernel_version = '1.0.0';
    private static $kernel_encrypt_key = '';
    private static $kernel_modules = array();
    private static $passwordComponents = array("ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz","0123456789","#$@!");

    /* Indexes & API */
    private static $apis = array();
    private static $cronjobs = array();

    /**
     * common constructor.
     */
    public function __construct() {
        //=======================================
        // load debug.ini for logger
        //=======================================
        if(file_exists(ROOT_PATH.'/config/system/debug.ini')) {
            $ini_logger = parse_ini_file(ROOT_PATH.'/config/system/debug.ini', true);
            if(array_key_exists('logger',$ini_logger)) {
                if(array_key_exists('debug_level',$ini_logger['logger'])) {
                    if(($level=intval($ini_logger['logger']['debug_level'])) >= 1) {
                        switch ($level) {
                            case 1: self::$logger_level = Logger::DEBUG;     break;
                            case 2: self::$logger_level = Logger::INFO;      break;
                            case 3: self::$logger_level = Logger::NOTICE;    break;
                            case 4: self::$logger_level = Logger::WARNING;   break;
                            case 5: self::$logger_level = Logger::ERROR;     break;
                            case 6: self::$logger_level = Logger::CRITICAL;  break;
                            case 7: self::$logger_level = Logger::ALERT;     break;
                            case 8: self::$logger_level = Logger::EMERGENCY; break;
                        }
                    }
                }
            } unset($ini_logger,$level);
        }

        //=======================================
        // create a log file
        //=======================================
        self::$log['kernel'] = new Logger('Kernel');
        self::$log['kernel']->pushHandler(new StreamHandler(ROOT_PATH.'/logs/kernel.log', self::$logger_level));

        self::$log['session'] = new Logger('Session');
        self::$log['session']->pushHandler(new StreamHandler(ROOT_PATH.'/logs/sessions.log', self::$logger_level));

        // Logger for CronJobs
        if(self::$is_cronjob) {
            self::$log['cronjob'] = new Logger('CronJob');
            self::$log['cronjob']->pushHandler(new StreamHandler(ROOT_PATH.'/logs/cronjob.log', self::$logger_level));
        }

        // Logger for Json-APIs
        if(self::$is_jsonapi) {
            self::$log['jsonapi'] = new Logger('JsonAPI');
            self::$log['jsonapi']->pushHandler(new StreamHandler(ROOT_PATH.'/logs/jsonapi.log', self::$logger_level));
        }

        //DEBUG URLS
        self::$log['kernel']->addDebug('######### System called over ######### ->',array('URL' => "http".(self::getEnv('SERVER_PORT') == 443 ? "s://" : "://").
            self::getEnv('HTTP_HOST').self::getEnv('REQUEST_URI'),
            'POST' => $_POST, 'GET' => $_GET));

        // Search for IMG or Templatefiles & exit;
        if(in_array(substr(strtolower(self::getEnv('REQUEST_URI')), -3), array('png','jpg','gif','css','js','html','htm','tpl','ico'))) {
            self::$log['kernel']->info('File ist not exists!',array("URL" => "http".(self::getEnv('SERVER_PORT') == 443 ? "s://" : "://").
                self::getEnv('HTTP_HOST').self::getEnv('REQUEST_URI'),"IP" => self::getIp()));
            self::$log['kernel']->info('######### System STOP #########');
            exit('The File: "'."http".(self::getEnv('SERVER_PORT') == 443 ? "s://" : "://").
                self::getEnv('HTTP_HOST').self::getEnv('REQUEST_URI').'" is not exists!');
        }

        // Errors Autofix
        if(file_exists(ROOT_PATH.'/kernel/error.run')) {
            $fixed = json_decode(self::getFile('kernel/error.run'),true);
            $fix_run = false;
            foreach ($fixed as $fix => $data) {
                if($data['run'] && $data['ip'] == \kernel\common::getIp()['ip']) {
                    self::$log['kernel']->addAlert('Kernel Autofix-System has started!',$fixed);
                    $fix_run = true;
                    break;
                }
            } unset($fix,$run);

            if($fix_run) {
                foreach ($fixed as $fix => $data) {
                    switch ($fix) {
                        case 'sessions_re_initialize':
                            if($data['ip'] == \kernel\common::getIp()['ip']) {
                                self::$sessions_re_initialize = ($data['run']);
                                self::$log['session']->addAlert('Sessions ReInitialize for Bugfixing  IP:->', array(self::getIp()));
                                $fixed[$fix]['run'] = false;
                            }
                        break;
                    }
                }

                @file_put_contents(ROOT_PATH.'/kernel/error.run', json_encode($fixed));
            }
            unset($fixed);
        } else {
            @file_put_contents(ROOT_PATH.'/kernel/error.run', json_encode(array('system_kernel' => false)));
        }
        
        //=======================================
        // show composer installed dependencies
        //=======================================
        if(file_exists(ROOT_PATH.'/vendor/composer/installed.json')) {
            self::$composer = json_decode(file_get_contents(ROOT_PATH.'/vendor/composer/installed.json'),true);
            foreach (self::$composer as $dependencies) {
                $exp = explode('/',$dependencies['name']);
                self::$versions[$exp[1]] = $dependencies['version'];
            } unset($dependencies,$exp);
        } else {
            self::$log['kernel']->addAlert('Can not load Composer "installed.json"! ->',
                array('Path' => ROOT_PATH.'/vendor/composer/installed.json'));
        }

        //=======================================
        // add kernel version to dependencies
        //=======================================
        self::$versions['hmcp-kernel'] = self::$kernel_version;

        //=======================================
        // include all kernel classes
        //=======================================
        if($files = self::get_filelist('kernel',false,true,array('php'),"/.*.class.*/")) {
            self::$log['kernel']->addDebug('Include Kernel-Files ->',$files);
            foreach($files as $file) {
                composer\includeFile('kernel/'.strtolower($file));
            }
        } unset($files,$file);

        //=======================================
        // secure config INIs dirs
        //=======================================
        $dirs = array('config/system','config/smarty');
        foreach ($dirs as $dir) {
            if(!self::htaccess(ROOT_PATH.'/'.$dir)) {
                self::$log['kernel']->addAlert('Can not make a .htaccess file in ->',
                    array(ROOT_PATH.'/'.$dir));
            }
        }

        //=======================================
        // load config INIs files
        //=======================================
        if($files = self::get_filelist('config/system',false,true,array('ini'))) {
            self::$log['kernel']->addDebug('Load INI-Files ->',$files);
            foreach($files as $file) {
                $file = strtolower($file);
                self::$ini_config[str_replace('.ini','',$file)] = new IniFile(ROOT_PATH.'/config/system/'.$file);
                self::$ini_config[str_replace('.ini','',$file)]->read();
            }
        } unset($files,$file);

        self::$debug_config = self::$ini_config['debug']; //Set debug config global

        //=======================================
        // initialize gump
        //=======================================
        if(!is_object(self::$gump)) {
            if(!class_exists('\GUMP')) {
                self::$log['kernel']->addCritical('Class "GUMP" not exists!');
                trigger_error('Class "GUMP" not exists! Called on trigger_error()', E_USER_ERROR);
            }
            self::$gump = new \GUMP;
            self::$log['kernel']->addDebug('Initialize -> GUMP',
                array('OBJEKT' => is_object(self::$gump),
                      'GUMP' => (self::$gump instanceof \GUMP),
                      'METHODS' => get_class_methods(self::$gump)));
        }

        //=======================================
        // sanitize _GET & _POST
        //=======================================
        self::$input_get = self::$gump->sanitize($_GET);
        self::$input_post = self::$gump->sanitize($_POST);

        //=======================================
        // initialize file cache
        //=======================================
        Util\Languages::setEncoding("UTF-8");
        if(!is_object(self::$cache)) {
            if(!class_exists('\\phpFastCache\\CacheManager')) {
                self::$log['kernel']->addCritical('Class "CacheManager" not exists!');
                trigger_error('Class "CacheManager" not exists! Called on trigger_error()', E_USER_ERROR);
            }
            
            self::$cache = new Cache();
            self::$log['kernel']->addDebug('Initialize -> Cache',
                array('OBJEKT' => is_object(self::$cache),
                      'METHODS' => get_class_methods(self::$cache)));
        }

        //=======================================
        // initialize smarty template engine
        //=======================================
        if(!class_exists('\Smarty')) {
            self::$log['kernel']->addCritical('Class "Smarty" not exists!');
            trigger_error('Class "Smarty" not exists! Called on trigger_error()', E_USER_ERROR);
        }

        self::$smarty = new \Smarty;
        self::$log['kernel']->addDebug('Initialize -> Smarty',
            array('OBJEKT' => is_object(self::$smarty),
                  'SMARTY' => (self::$smarty instanceof \Smarty),
                  'METHODS' => get_class_methods(self::$smarty)));

        //=======================================
        // set smarty global options
        //=======================================
        self::$smarty->force_compile = (self::$logger_level == Logger::DEBUG ? true : false);
        self::$smarty->debugging = (self::$logger_level == Logger::DEBUG ? true : false);
        self::$smarty->caching = (self::$logger_level == Logger::DEBUG ? false : true);
        self::$smarty->cache_lifetime = 120;
        self::$smarty->allow_php_templates = true;

        //=======================================
        // set smarty system folders
        //=======================================
        self::$smarty->setTemplateDir(ROOT_PATH.'/templates')
                     ->setCompileDir(ROOT_PATH.'/templates_c')
                     ->setCacheDir(ROOT_PATH.'/cache/smarty')
                     ->setPluginsDir(array(ROOT_PATH.'/plugins/smarty',
                                    ROOT_PATH.'/vendor/smarty/libs/plugins'))
                     ->setConfigDir(ROOT_PATH.'/config/smarty');

        //=======================================
        // check smarty system directorys
        //=======================================
        $smartydirs=array(array(self::$smarty->getTemplateDir(),'0750'),
                        array(self::$smarty->getCompileDir() ,'0750'),
                        array(self::$smarty->getCacheDir()   ,'0750'),
                        array(self::$smarty->getPluginsDir() ,'0750'),
                        array(self::$smarty->getConfigDir()  ,'0750'));
        self::$log['kernel']->addDebug('Check Smarty directorys');
        if(!self::make_dirs($smartydirs)) {
            self::$log['kernel']->addCritical('Smarty directorys is not exists or writable!');
            trigger_error('Smarty directorys is not exists or writable!', E_USER_ERROR);
        } unset($smartydirs);

        //=======================================
        // Scan Templates & add to Smarty
        //=======================================
        $templates_included = false;
        if($folders = self::get_filelist('templates',true)) {
            self::$log['kernel']->addDebug('Scan & add Templates to Smarty ->',$folders);
            foreach($folders as $folder) {
                $templates_included = true;
                self::$smarty->addTemplateDir(ROOT_PATH.'/templates/'.strtolower($folder),strtolower($folder));
            }
        }

        if(!$templates_included) {
            self::$log['kernel']->addCritical('No "Templates" for Smarty loaded!');
            trigger_error('No "Templates" for Smarty loaded! Called on trigger_error()', E_USER_ERROR);
        }
        unset($folders,$folder,$templates_included);

        //=======================================
        // initialize database
        //=======================================
        if(!class_exists('\\kernel\\database')) {
            self::$log['kernel']->addCritical('Class "database" not exists!');
            trigger_error('Class "database" not exists! Called on trigger_error()', E_USER_ERROR);
        } else if(!array_key_exists('database',self::$ini_config)) {
            self::$log['kernel']->addCritical('INI-File: "config/system/database.ini" not exists!');
            trigger_error('INI-File: "config/system/database.ini" not exists! Called on trigger_error()', E_USER_ERROR);
        }

        self::$instance = new database(self::$ini_config['database']);
        self::$log['kernel']->addDebug('Initialize -> Database',
            array('OBJEKT' => is_object(self::$instance),
                  'DATABASE' => (self::$instance instanceof database),
                  'METHODS' => get_class_methods(self::$instance)));

        self::$database = self::$instance->getInstance();

        //=======================================
        // initialize settings
        //=======================================
        if(!class_exists('\\kernel\\settings')) {
            self::$log['kernel']->addCritical('Class "settings" not exists!');
            trigger_error('Class "settings" not exists! Called on trigger_error()', E_USER_ERROR);
        }

        $settings = new settings();
        self::$log['kernel']->addDebug('Initialize -> Settings Database',
            array('OBJEKT' => is_object($settings),
                  'DATABASE' => ($settings instanceof settings),
                  'METHODS' => get_class_methods($settings)));

        //=======================================
        // initialize sessions engine
        //=======================================
        if(file_exists(ROOT_PATH.'/config/system/encrypt.key')) {
            self::set_encryptkey(self::CompressedHexToBin(
                self::getFile('config/system/encrypt.key')));
        } else {
            @file_put_contents(ROOT_PATH.'/config/system/encrypt.key',
                self::BinToCompressedHex(self::get_encryptkey()));
        }

        $sessions = new \session();
        if(!empty(self::$kernel_encrypt_key)) {
            $sessions::$securityKey_mcrypt = self::$kernel_encrypt_key;
        }
        $sessions->init(self::$sessions_re_initialize); //initialize

        //=======================================
        // initialize cookie engine
        //=======================================
        if(!empty(self::$kernel_encrypt_key)) {
            $key = bin2hex(self::$kernel_encrypt_key);
            if(!class_exists('\kernel\cookie')) {
                self::$log['kernel']->addCritical('Class "Cookie" not exists!');
                trigger_error('Class "Cookie" not exists! Called on trigger_error()', E_USER_ERROR);
            }
            self::$cookie = new cookie(substr($key, 0, (strlen($key) - abs((8 - strlen($key))))));
            self::$log['kernel']->addDebug('Initialize -> CookieMgr',
                array('OBJEKT' => is_object(self::$cookie),
                      'GUMP' => (self::$cookie instanceof cookie),
                      'METHODS' => get_class_methods(self::$cookie)));
            unset($key);
        } else {
            self::$log['kernel']->addAlert('Cookie is not initialize!, Kernel-EncryptKey has not set!',array(self::$kernel_encrypt_key));
        }

        //=======================================
        // initialize mailer engine
        //=======================================
        self::$phpmailer = new PHPMailer((self::$logger_level == Logger::DEBUG ? true : false)); //initialize

        //Server settings
        switch (settings::get('phpmailer_type')) {
            case 'smtp':
                self::$phpmailer->isSMTP(); // Send messages using SMTP.
                self::$phpmailer->SMTPDebug = 2; // Enable verbose debug output
                if(count(settings::get('phpmailer_smtp_hosts'))) {
                    $hosts = "";
                    foreach (settings::get('phpmailer_smtp_hosts') as $host) {
                        $hosts .= $host . ";";
                    }
                    $hosts = substr($hosts, 0, -1);
                    self::$phpmailer->Host = $hosts;  // Specify main and backup SMTP servers
                } else {
                    self::$log['kernel']->addAlert('PHPMailer has no SMTP Hosts!',settings::get('phpmailer_smtp_hosts'));
                }

                self::$phpmailer->Port = settings::get('phpmailer_smtp_port'); // TCP port to connect to Server
                self::$phpmailer->SMTPAuth = settings::get('phpmailer_smtp_auth'); // Enable SMTP authentication
                self::$phpmailer->Username = settings::get('phpmailer_smtp_username'); // SMTP username
                self::$phpmailer->Password = self::decrypt(hex2bin(settings::get('phpmailer_smtp_password')),__CLASS__.' | '.__LINE__); // SMTP password
                self::$phpmailer->SMTPSecure = settings::get('phpmailer_smtp_secure'); // Enable TLS encryption, `ssl` also accepted
                self::$phpmailer->SMTPOptions = array('ssl' => ['allow_self_signed' => true]);
            break;
            case 'qmail':
                self::$phpmailer->isQmail(); // Send messages using qmail.
            break;
            case 'sendmail':
                self::$phpmailer->isSendmail(); // Send messages using Sendmail.
            break;
            case 'dummy':
                self::$phpmailer->isDummy(); // Send messages using Dummy.
                break;
            default:
                self::$phpmailer->isMail(); // Send messages using PHP's mail() function.
            break;
        }

        self::$phpmailer->setFrom(settings::get('phpmailer_send_from'), settings::get('phpmailer_send_from_name'));
        self::$phpmailer->setLanguage('de', ROOT_PATH.'/vendor/phpmailer/phpmailer/language/');

        //=======================================
        // initialize router
        //=======================================
        self::$router = new \AltoRouter();
        self::$router->setBasePath(self::$ini_config['kernel']->getKey('BasePathRouter','global'));

        // load modules
        if($modules = self::get_filelist('modules',true,false)) {
            self::$log['kernel']->addDebug('Load Modules ->',$modules);
            foreach($modules as $module) {
                if(!file_exists(ROOT_PATH.'/modules/'.$module.'/module.ini')) {
                    continue;
                }

                // load module.ini files
                self::$kernel_modules[strtolower($module)] = new IniFile(ROOT_PATH.'/modules/'.$module.'/module.ini');
                self::$kernel_modules[strtolower($module)]->read();
            }
        } unset($modules,$module);

        //=======================================
        // check of modules
        //=======================================
        if(count(self::$kernel_modules) >= 1) {
            // check of required modules
            foreach (self::$kernel_modules as $name => $module) {
                $required = $module->getKey('RequiredModules', 'module');
                if (!empty($required)) {
                    $required_array = @explode('|', $required);
                    if (count($required_array) >= 2) {
                        //Check More RequiredModules
                        foreach ($required_array as $required_a) {
                            if (!array_key_exists(strtolower($required_a), self::$kernel_modules)) {
                                self::$log['kernel']->addAlert('Required Module: "' . $required_a . '" not found! Loaded Modules ->', self::$kernel_modules);
                                unset(self::$kernel_modules[$name]);
                                continue;
                            }
                        }
                        unset($required_a);
                    } else {
                        //Check one RequiredModule
                        if (!array_key_exists(strtolower($required), self::$kernel_modules)) {
                            self::$log['kernel']->addAlert('Required Module: "' . $required . '" not found! Loaded Modules ->', self::$kernel_modules);
                            unset(self::$kernel_modules[$name]);
                            continue;
                        }
                    }
                    unset($required_array);
                }
            }
            unset($name, $module, $required);

            //=======================================
            // load module core file
            //=======================================
            $log = array(); $core_modules = array('core');

            //Frist load the Core Module
            foreach (self::$kernel_modules as $name => $module) {
                if(!array_key_exists($name, $core_modules)) { continue; }
                if(file_exists(ROOT_PATH.'/modules/'.$name.'/module.php')) {
                    $log[] = 'modules/'.$name.'/module.php';
                    composer\includeFile(ROOT_PATH.'/modules/'.$name.'/module.php');
                    if(class_exists('module\\'.$name. '\\module')) {
                        call_user_func(array('module\\' . $name . '\\module', 'init'));
                    }
                }
            }

            //Register setCacheId for Smarty
            self::$smartyCacheId = \session::get('template').'_'.\session::get('language').self::getEnv('SERVER_NAME');
            self::$smarty->setCacheId(md5(self::$smartyCacheId));

            // Any Modules after Core Module
            foreach (self::$kernel_modules as $name => $module) {
                if(array_key_exists($name, $core_modules)) { continue; }
                if(file_exists(ROOT_PATH.'/modules/'.$name.'/module.php')) {
                    $log[] = 'modules/'.$name.'/module.php';
                    composer\includeFile(ROOT_PATH.'/modules/'.$name.'/module.php');
                    if(class_exists('module\\'.$name. '\\module')) {
                        call_user_func(array('module\\' . $name . '\\module', 'init'));
                    }
                }
            }

            if(count($log) >= 1) {
                self::$log['kernel']->addDebug('Load Module-MainFiles ->', $log);
            }
            unset($name, $module, $log);

            //=======================================
            // check of controllers
            //=======================================
            if(!self::$is_cronjob && !self::$is_jsonapi && !self::$no_router) {
                foreach (self::$kernel_modules as $name => $module) {
                    if ($module->getKey('AddControllers', 'module')) {
                        // load module controllers
                        if ($controllers = self::get_filelist('modules/' . $name . '/controller', false, true, array('php'))) {
                            self::$log['kernel']->addDebug('Load Module-Controller Folder ->', $controllers);
                            foreach ($controllers as $controller) {
                                self::$controllers[str_replace('.php', '', $controller)] = $name;
                            }
                        }
                        unset($controllers, $controller);
                    }
                }

                unset($name, $module);
            }

            //=======================================
            //add a mapping for controllers
            //=======================================
            if(!self::$is_cronjob && !self::$is_jsonapi && !self::$no_router) {
                foreach (self::$kernel_modules as $name => $module) {
                    if ($module->getKey('AddMapping', 'module')) {
                        if (!file_exists(ROOT_PATH . '/modules/' . $name . '/mapping.php')) {
                            self::$log['kernel']->addWarning('The Module: "' . $name . '" has no mapping file found on: "' . ROOT_PATH . '/modules/' . $name . '"!');
                            self::$log['kernel']->addWarning('Disable the "AddMapping" option in "' . ROOT_PATH . '/modules/' . $name . '/module.ini"');
                            continue;
                        }

                        composer\includeFile(ROOT_PATH . '/modules/' . $name . '/mapping.php');
                        if (class_exists('module\\' . $name . '\\mapping')) {
                            call_user_func(array('module\\' . $name . '\\mapping', 'init'));
                        } else {
                            self::$log['kernel']->addWarning('Module: "' . $name . '" have no mapping class!');
                        }
                    }
                }

                unset($name, $module);
            }
        }

        //=======================================
        // match current request
        //=======================================
        if(!self::$is_cronjob && !self::$is_jsonapi && !self::$no_router) {
            $match = self::$router->match();
            if (is_array($match)) {
                // load base controllers
                if ($controllers = self::get_filelist('controller', false, true, array('php'))) {
                    self::$log['kernel']->addDebug('Load Default-Controller-Files ->', $controllers);
                    foreach ($controllers as $controller) {
                        composer\includeFile(ROOT_PATH . '/controller/' . $controller);
                        self::$controllers[str_replace('.php', '',$controller)] = 'core';
                    }
                }
                unset($controllers, $controller);

                $controller = (ucfirst($match['target']) . 'Controller');
                if (!array_key_exists('do', $match['params'])) {
                    $match['params']['action'] = null;
                }

                if (!class_exists(__NAMESPACE__ . '\\controller\\' . $controller) && array_key_exists($controller, self::$controllers)) {
                    composer\includeFile(ROOT_PATH . '/modules/' . self::$controllers[$controller] . '/controller/' . $controller . '.php');
                    call_user_func_array(array(__NAMESPACE__ . '\\controller\\' . $controller, '__default'), $match['params']);
                } else if (class_exists(__NAMESPACE__ . '\\controller\\' . $controller)) {
                    call_user_func_array(array(__NAMESPACE__ . '\\controller\\' . $controller, '__default'), $match['params']);
                } else {
                    if (class_exists(__NAMESPACE__ . '\\controller\\ErrorController')) {
                        $match['params']['controller'] = $controller;
                        call_user_func_array(array(__NAMESPACE__ . '\\controller\\ErrorController', '__default'), $match['params']);
                    }

                    self::$log['kernel']->addAlert('Controller "' . $controller . '" not exists! Params ->', $match['params']);
                }
            } else {
                self::$log['kernel']->addCritical('Router: requested map is not exists!');
                trigger_error('Router: Requested map is not exists! Called on trigger_error()', E_USER_ERROR);
            }
            unset($match, $controller);
        }

        //=======================================
        // start logger for communicate class
        //=======================================
        communicate::init();
    }

    /**
     * common deconstructor.
     */
    public function __destruct() {

    }

    /* ############################################## */
    /* ################ GET Methodes ################ */
    /* ############################################## */
    /**
     * @param null $dir
     * @param bool $only_dir
     * @param bool $only_files
     * @param array $file_ext
     * @param bool $preg_match
     * @param array $blacklist
     * @param bool $blacklist_word
     * @return array|bool
     */
    public static function get_filelist($dir='',$only_dir=false,$only_files=false,$file_ext=array(),$preg_match=false,array $blacklist=array(),$blacklist_word=false) {
        $dir = (ROOT_PATH.'/'.$dir);
        self::$log['kernel']->addDebug('Read Directory ->', array('DIR' => $dir));
        if(!is_file($dir) && !is_dir($dir)) {
            self::$log['kernel']->addAlert('Directory or File is not exists ->', array('DIR' => $dir));
            return false;
        }

        $files = array();
        if($handle = @opendir($dir)) {
            if($only_dir) {
                while(false !== ($file = readdir($handle))) {
                    if($file != '.' && $file != '..' && !is_file($dir.'/'.$file)) {
                        if(!count($blacklist) && (!$blacklist_word || strpos(strtolower($file), $blacklist_word) === false) &&
                            ($preg_match ? preg_match($preg_match,$file) : true))
                            $files[] = $file;
                        else {
                            if(!in_array($file, $blacklist) && (!$blacklist_word || strpos(strtolower($file), $blacklist_word) === false) &&
                                ($preg_match ? preg_match($preg_match,$file) : true))
                                $files[] = $file;
                        }
                    }
                } //while end
            } else if($only_files) {
                while(false !== ($file = readdir($handle))) {
                    if($file != '.' && $file != '..' && is_file($dir.'/'.$file)) {
                        if(!in_array($file, $blacklist) && (!$blacklist_word || strpos(strtolower($file), $blacklist_word) === false) &&
                            !count($file_ext) && ($preg_match ? preg_match($preg_match,$file) : true))
                            $files[] = $file;
                        else {
                            ## Extension Filter ##
                            $exp_string = array_reverse(explode(".", $file));
                            if(!in_array($file, $blacklist) && (!$blacklist_word || strpos(strtolower($file), $blacklist_word) === false) &&
                                in_array(strtolower($exp_string[0]), $file_ext) && ($preg_match ? preg_match($preg_match,$file) : true))
                                $files[] = $file;
                        }
                    }
                } //while end
            } else {
                while(false !== ($file = readdir($handle))) {
                    if($file != '.' && $file != '..' && is_file($dir.'/'.$file)) {
                        if(!in_array($file, $blacklist) && (!$blacklist_word || strpos(strtolower($file), $blacklist_word) === false) &&
                            !count($file_ext) && ($preg_match ? preg_match($preg_match,$file) : true))
                            $files[] = $file;
                        else {
                            ## Extension Filter ##
                            $exp_string = array_reverse(explode(".", $file));
                            if(!in_array($file, $blacklist) && (!$blacklist_word || strpos(strtolower($file), $blacklist_word) === false) &&
                                in_array(strtolower($exp_string[0]), $file_ext) && ($preg_match ? preg_match($preg_match,$file) : true))
                                $files[] = $file;
                        }
                    } else {
                        if(!in_array($file, $blacklist) && (!$blacklist_word || strpos(strtolower($file), $blacklist_word) === false) &&
                            $file != '.' && $file != '..' && ($preg_match ? preg_match($preg_match,$file) : true))
                            $files[] = $file;
                    }
                } //while end
            }

            if(is_resource($handle)) {
                closedir($handle);
            }

            if(!count($files)) {
                return false;
            }

            return $files;
        }

        return false;
    }

    /**
     * Codiert einen String mit dem Kernel Encrypt-Key
     * @param null $data
     * @return mixed
     */
    public static function encrypt($data = null,$line=__CLASS__.' | '.__LINE__) {
        try {
            return \Crypto::Encrypt($data, self::$kernel_encrypt_key);
        } catch (CryptoTestFailedException $ex) {
            self::$log['kernel']->addAlert('common::encrypt() -> Cannot safely perform encryption',
                array('Data:' => $data, 'Place:'.$line));
        } catch (CannotPerformOperationException $ex) {
            self::$log['kernel']->addAlert('common::encrypt() -> Cannot safely perform decryption',
                array('Data:' => $data, 'Place:'.$line));
        }
    }

    /**
     * Decodiert einen String mit dem Kernel Encrypt-Key
     * @param null $data
     * @return mixed
     */
    public static function decrypt($data = null,$line=__CLASS__.' | '.__LINE__) {
        try {
            return \Crypto::Decrypt($data, self::$kernel_encrypt_key);
        } catch (InvalidCiphertextException $ex) { // VERY IMPORTANT
            // Either:
            //   1. The ciphertext was modified by the attacker,
            //   2. The key is wrong, or
            //   3. $ciphertext is not a valid ciphertext or was corrupted.
            // Assume the worst.
            self::$log['kernel']->addAlert('common::decrypt() -> DANGER! DANGER! The ciphertext has been tampered with!',
                array('Data:' => $data, 'Place:'.$line));
        } catch (CryptoTestFailedException $ex) {
            self::$log['kernel']->addAlert('common::decrypt() -> Cannot safely perform encryption',
                array('Data:' => $data, 'Place:'.$line));
        } catch (CannotPerformOperationException $ex) {
            self::$log['kernel']->addAlert('common::decrypt() -> Cannot safely perform decryption',
                array('Data:' => $data, 'Place:'.$line));
        }
    }

    /**
     * Get the Kernel-EncryptKey
     * @return string
     */
    public static function get_encryptkey() {
        if(empty(self::$kernel_encrypt_key)) {
            self::$kernel_encrypt_key = \Crypto::CreateNewRandomKey();
        }

        return self::$kernel_encrypt_key; //Return Key
    }

    /**
     * Get the Smarty instance
     * @return smarty
     */
    public static function get_smarty() {
        //self::$smarty->display('file:[' . \session::get('template') . ']index.tpl');
        return self::$smarty;
    }

    /**
     * @param $var
     * @return null
     */
    public static function getEnv($var) {
        if(!empty($_SERVER[$var]))               return self::UTF8_Reverse($_SERVER[$var]);
        elseif(!empty($_ENV[$var]))              return self::UTF8_Reverse($_ENV[$var]);
        elseif(@getenv($var))                    return self::UTF8_Reverse(getenv($var));
        else                                     return null;
    }

    /**
     * Get the Client-IP
     * IPv4 & IPv6 supported
     * @return array
     */
    public static function getIp() {
        $proxy_x_forwarded_for = self::getEnv('HTTP_X_FORWARDED_FOR');
        $proxy_x_forwarded = self::getEnv('HTTP_X_FORWARDED');
        $proxy_forwarded_for = self::getEnv('HTTP_FORWARDED_FOR');
        $proxy_forwarded = self::getEnv('HTTP_FORWARDED');
        $proxy_via = self::getEnv('HTTP_VIA');
        $proxy_x_coming_from = self::getEnv('HTTP_X_COMING_FROM');
        $proxy_coming_from = self::getEnv('HTTP_COMING_FROM');

        if(!empty($proxy_x_forwarded_for))   { $proxy_ip = $proxy_x_forwarded_for; $proxy_type = 'HTTP_X_FORWARDED_FOR'; }
        elseif(!empty($proxy_x_forwarded))   { $proxy_ip = $proxy_x_forwarded;     $proxy_type = 'HTTP_X_FORWARDED'; }
        elseif(!empty($proxy_forwarded_for)) { $proxy_ip = $proxy_forwarded_for;   $proxy_type = 'HTTP_FORWARDED_FOR'; }
        elseif(!empty($proxy_forwarded))     { $proxy_ip = $proxy_forwarded;       $proxy_type = 'HTTP_FORWARDED'; }
        elseif(!empty($proxy_via))           { $proxy_ip = $proxy_via;             $proxy_type = 'HTTP_VIA'; }
        elseif(!empty($proxy_x_coming_from)) { $proxy_ip = $proxy_x_coming_from;   $proxy_type = 'HTTP_X_COMING_FROM'; }
        elseif(!empty($proxy_coming_from))   { $proxy_ip = $proxy_coming_from;     $proxy_type = 'HTTP_COMING_FROM'; }
        else                                 { $proxy_ip = null;                   $proxy_type = null;}

        $direct_ip = self::getEnv('REMOTE_ADDR');
        if(empty($proxy_ip) && filter_var($direct_ip, FILTER_VALIDATE_IP)) //IPv4/6 validate
            return array('ip' => $direct_ip, 'proxy' => $proxy_via, 'proxytype' => $proxy_type);
        else {
            if((preg_match('#^([0-9]{1,3}.){3,3}[0-9]{1,3}#', $proxy_ip, $regs)) && (count($regs) > 0)) {
                return array('ip' => $regs[0], 'proxy' => $proxy_via, 'proxytype' => $proxy_type);
            } else {
                $client_ip = self::getEnv('HTTP_CLIENT_IP');
                return array('ip' => (empty($client_ip) ? $proxy_ip : $client_ip), 'proxy' => $proxy_via, 'proxytype' => $proxy_type);
            }
        }
    }

    /**
     * Liest eine Datei aus die UTF8 Kodiert ist.
     * @return string
     */
    public static function getFile($file) {
        if(file_exists(ROOT_PATH.'/'.$file)) {
            $data = file_get_contents(ROOT_PATH.'/'.$file);
            if (substr($data, 0, 3) === pack("CCC", 0xef, 0xbb, 0xbf)) {
                $data = substr($data, 3);
            }
            return trim($data);
        }
        return '';
    }

    /**
     * Get Base URL for Page
     * @return string
     */
    public static function getBaseURL() {
        return "http".(self::getEnv('SERVER_PORT') == 443 ? "s://" : "://").
        self::getEnv('HTTP_HOST').self::$ini_config['kernel']->getKey('BasePathRouter').'/'.
        explode('_', \session::get('language'))[0];
    }

    /**
     * Send json for API or AJAX
     * @param array $data
     */
    public static function getJsonStream(array $data) {
        $json = json_encode($data);
        if ($json === false) {
            // Avoid echo of empty string (which is invalid JSON), and
            // JSONify the error message instead:
            $json = json_encode(array("jsonError", json_last_error_msg()));
            if ($json === false) {
                // This should not happen, but we go all the way now:
                $json = '{"jsonError": "unknown"}';
            }
            // Set HTTP response status code to: 500 - Internal Server Error
            http_response_code(500);
        }

        return $json;
    }

    /* ############################################## */
    /* ################ SET Methodes ################ */
    /* ############################################## */

    /**
     * Set the Kernel-EncryptKey
     * @param string $key
     * @return bool
     */
    public static function set_encryptkey($key) {
        if(empty($key) && empty(self::$kernel_encrypt_key)) {
            self::$kernel_encrypt_key = \Crypto::CreateNewRandomKey();
            self::$log['kernel']->addDebug('Kernel-EncryptKey generate by Crypto::CreateNewRandomKey() ->',
                array('KEY' => md5(self::$kernel_encrypt_key)));
            return true;
        } else {
            $encrypt_key = (self::$kernel_encrypt_key != $key);
            self::$kernel_encrypt_key = $key;
            self::$log['kernel']->addDebug('Kernel-EncryptKey set by user ->',
                array('KEY' => md5(self::$kernel_encrypt_key)));
        }

        return ($encrypt_key && !empty(self::$kernel_encrypt_key));
    }

    /* ############################################## */
    /* ################ Run Methodes ################ */
    /* ############################################## */

    /**
     * Run the mainpage
     * @return html string
     */
    public static function run_mainpage() {
        if(!empty(\session::get('template'))) {
            if(self::$smarty->templateExists('file:[' . \session::get('template') . ']index.tpl')) {
                self::$smarty->clearAllAssign();
                if(!array_key_exists('content',self::$template_index)) {
                    self::$template_index['content'] = '';
                }

                foreach (self::$template_index as $key => $var) {
                    self::$smarty->assign($key, $var);
                }

                //Check URLS
                $url_exp = explode('/', str_replace(self::$ini_config['kernel']->getKey('BasePathRouter'), '', $_SERVER["REQUEST_URI"]));
                $ext_dir = '';
                if(count($url_exp) >= 2) {
                    foreach ($url_exp as $i => $url) {
                        if (empty($url)) {
                            unset($url_exp[$i]);
                        }
                    }
                    for ($i = 1; $i <= (count($url_exp) - 1); $i++) {
                        $ext_dir .= '../';
                    }
                }

                $global = array('tpl' => \session::get('template'), //{$__global.tpl}
                    'sys' => ROOT_PATH, //{$__global.sys}
                    'url' => self::getBaseURL(), //{$__global.url}
                    'dir' => $ext_dir.'templates/' . \session::get('template'), //{$__global.dir}
                    'js' => $ext_dir.'templates/' . \session::get('template') . '/assets/js', //{$__global.js}
                    'img' => $ext_dir.'templates/' . \session::get('template') . '/assets/img', //{$__global.img}
                    'css' => $ext_dir.'templates/' . \session::get('template') . '/assets/css', //{$__global.css}
                    'plugins' => $ext_dir.'templates/' . \session::get('template') . '/assets/plugins', //{$__global.plugins}
                    'debug' => (self::$logger_level == Logger::DEBUG)); //{$__global.debug}
                self::$smarty->assign('__global', $global);
                self::$smarty->display('file:[' . \session::get('template') . ']index.tpl');
                self::$cookie->save(); //Save Cookie
            } else {
                self::$log['kernel']->addAlert('run_mainpage: This Template is not exists! ->', array(\session::get('template')));
            }
        } else {
            self::$log['kernel']->addAlert('run_mainpage: No Template is enabled for display! ->', array(\session::get('template')));
        }
    }

    /**
     * Run the JSON-API
     * @return json string
     */
    public static function run_jsonapi() {
        self::$log['jsonapi']->debug('API Call ->',self::$input_get);
        $callback = array('error' => 1, 'status' => 0);
        if(array_key_exists('call',self::$input_get)) {
            $call = strtolower(self::$input_get['call']);
            if(array_key_exists($call,self::$apis)) {
                composer\includeFile(ROOT_PATH.'/modules/'.self::$apis[$call]['module'].'/api/'.self::$apis[$call]['file']);
                $namespace = 'module\\'.self::$apis[$call]['module'].'\\api';
                self::$log['jsonapi']->debug('APICall ->',array('call' => $call, 'module' => self::$apis[$call]['module'],
                    'file' => self::$apis[$call]['file'], 'namespace' => $namespace, 'methode' => self::$apis[$call]['methode']));
                $callback = call_user_func($namespace.'\\'.self::$apis[$call]['methode']);
                self::$log['jsonapi']->debug('APICall ->',array_merge(array('call' => $call),$callback));
                if(!$callback) {
                    $callback = array('error' => 1, 'status' => 0, 'msg' => 'error on call_user_func(), '.
                        $namespace.'\\'.self::$apis[$call]['methode'].' returns false');
                    self::$log['jsonapi']->addAlert('Error on APICall ->',array_merge(array('call' => $call),$callback));
                } else if(!is_array($callback)) {
                    $callback = array('error' => 1, 'status' => 0, 'msg' => 'error on call_user_func(), '.
                        $namespace.'\\'.self::$apis[$call]['methode'].' give not array to return');
                    self::$log['jsonapi']->addAlert('Error on APICall ->',array_merge(array('call' => $call),$callback));
                }
            }
        } else {
            self::$log['jsonapi']->addAlert('Missing ?call=xxxx argument!');
        }

        ## Send Header ##
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Content-type: application/json;charset=utf-8');
        return self::getJsonStream($callback);
    }

    /**
     * Run the cronjob
     * @return json string
     */
    public static function run_cronjob() {
        $callback = array('error' => 1, 'runned_cronjobs' => 0);
        foreach(self::$cronjobs as $job) {
            composer\includeFile(ROOT_PATH.'/modules/'.$job['module'].'/cronjob/'.$job['file']);
            $namespace = 'module\\'.$job['module'].'\\cronjob';
            self::$log['cronjob']->debug('Run CronJob ->',array('module' => $job['module'],
                'file' => $job['file'], 'namespace' => $namespace, 'methode' => $job['methode']));
            call_user_func($namespace.'\\'.$job['methode']);
            $callback['error'] = 0;
            $callback['runned_cronjobs']++;
        }

        ## Send Header ##
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Content-type: application/json;charset=utf-8');
        return self::getJsonStream($callback);
    }

    /* ############################################## */
    /* ############# Register Methodes ############## */
    /* ############################################## */

    /**
     * Register a JSON-API
     */
    //api.php?call=testcall
    //register_jsonapi('testcall','testcall::run','test','testcall.api.php');
    public static function register_jsonapi($call,$methode,$module,$file) {
        $file_exists = true;
        if(self::$is_jsonapi && ($file_exists = file_exists(ROOT_PATH.'/modules/'.$module.'/api/'.$file))) {
            self::$apis[strtolower($call)] = array('module' => $module, 'methode' => $methode, 'file' => $file);
            self::$log['cronjob']->debug('Register a new API Call ->',
                array('call' => $call, 'module' => $module, 'methode' => $methode, 'file' => $file));
        } else if(self::$is_jsonapi && !$file_exists) {
            self::$log['cronjob']->addAlert('Register of API Call failed! File is not exists!  ->',
                array('call' => $call, 'module' => $module, 'methode' => $methode, 'file' => $file,
                    'include' => ROOT_PATH.'/modules/'.$module.'/cronjob/'.$file));
        }
    }

    /**
     * Register a cronjob
     */
    //cronjob.php
    //register_cronjob('testcall::run','test','testcall.job.php');
    public static function register_cronjob($methode,$module,$file) {
        $file_exists = true;
        if(self::$is_cronjob && ($file_exists = file_exists(ROOT_PATH.'/modules/'.$module.'/cronjob/'.$file))) {
            self::$cronjobs[] = array('module' => $module, 'methode' => $methode, 'file' => $file);
            self::$log['cronjob']->debug('Register a new CronJob ->',
                array('module' => $module, 'methode' => $methode, 'file' => $file));
        } else if(self::$is_cronjob && !$file_exists) {
            self::$log['cronjob']->addAlert('Register of CronJob failed! File is not exists!  ->',
                array('module' => $module, 'methode' => $methode, 'file' => $file,
                    'include' => ROOT_PATH.'/modules/'.$module.'/cronjob/'.$file));
        }
    }

    /* ############################################## */
    /* ############## Public Methodes ############## */
    /* ############################################## */

    /**
     * Make a .htaccess file
     * @param $dir
     * @return mixed
     */
    public static function htaccess($dir) {
        if(!file_exists($dir.'/.htaccess')) {
            file_put_contents($dir.'/.htaccess','<Files "*.*">Deny from all</Files>');
            return file_exists($dir.'/.htaccess');
        }

        return true;
    }

    /*
     * Make a directory
     * make_dir('/dir','0750')
     */
    public static function make_dir($path='/',$chmod='0750') {
        if($path != '/') {
            self::$log['kernel']->addDebug('Check Directory ->', array('Path' => $path));
            $path = str_replace(ROOT_PATH,'',str_replace('\\','/',$path));
            if (!is_dir(ROOT_PATH . $path)) {
                self::$log['kernel']->addDebug('Make Directory ->', array('Path' => $path, 'Chmod' => $chmod));
                if(!mkdir(ROOT_PATH . $path, $chmod, true)) {
                    self::$log['kernel']->addAlert('Can not create directory ->', array('Path' => $path, 'Chmod' => $chmod));
                    return false;
                }
            }

            if (!($is_writable = is_writable(ROOT_PATH . $path))) {
                self::$log['kernel']->addDebug('Directory is not writable set chmod ->', array('Path' => $path, 'Chmod' => $chmod));
                if(!chmod(ROOT_PATH.$path, $chmod)) {
                    self::$log['kernel']->addAlert('Can not set chmod ->', array('Path' => $path, 'Chmod' => $chmod));
                    return false;
                }
            }

            if(!($retrun = ($is_writable || is_writable(ROOT_PATH . $path)))) {
                self::$log['kernel']->addAlert('Directory is not writable! ->', array('Path' => $path, 'Chmod' => $chmod));
            }

            return $retrun;
        }

        return false;
    }

    /*
     * Makes directorys from array input
     * make_dirs(array(array('/dir','0750'),array('/dir2','0750')))
     * or
     * make_dirs(array('/dir','/dir2'))
     */
    public static function make_dirs(array $dirs) {
        foreach ($dirs as $dir) {
            if(is_array($dir)) {
                if(is_array($dir[0])) {
                    foreach ( $dir[0] as $dir_loop) {
                        if (!self::make_dir($dir_loop, $dir[1])) {
                            return false;
                        }
                    }
                } else {
                    if (!self::make_dir($dir[0], $dir[1])) {
                        return false;
                    }
                }
            } else {
                if (!self::make_dir($dir)) {
                    return false;
                }
            }
        }

        return true;
    }

    public static function DNSToIp($address='') {
        if (!filter_var($address, FILTER_VALIDATE_IP)) {
            if (!($result = gethostbyname($address))) {
                return false;
            }

            if (strtolower($result) === strtolower($address)) {
                return false;
            }

            return $result;
        }

        return $address;
    }

    public static function ping_port($address='',$port=0000,$timeout=2,$udp=false) {
        if (!self::fsockopen_support()) {
            return false;
        }

        $errstr = NULL; $errno = NULL;
        if(!$ip = self::DNSToIp($address)) {
            return false;
        }

        if($fp = @fsockopen(($udp ? "udp://".$ip : $ip), self::ToInt($port), $errno, $errstr, self::ToInt($timeout))) {
            unset($ip,$port,$errno,$errstr,$timeout);
            fclose($fp);
            return true;
        }

        return false;
    }

    public static function delete_files($file) {
        @chmod(ROOT_PATH.'/'.$file,0777);
        if (is_dir(ROOT_PATH.'/'.$file)) {
            $handle = opendir(ROOT_PATH.'/'.$file);
            while($filename = readdir($handle)) {
                if ($filename != "." && $filename != "..") {
                    self::delete_files($file."/".$filename);
                }
            }
            closedir($handle);
            @rmdir(ROOT_PATH.'/'.$file);
        } else {
            @unlink(ROOT_PATH.'/'.$file);
        }
    }

    /**
     * Funktion um eine Variable prüfung in einem Array durchzuführen
     * @return boolean
     */
    public static function array_var_exists($var,array $search) {
        foreach($search as $key => $var_) {
            if($var_==$var)
                return true;
        }

        return false;
    }

    /**
     * Funktion um notige Erweiterungen zu prufen
     * @return boolean
     **/
    public static function fsockopen_support() {
        return ((self::disable_functions('fsockopen') || self::disable_functions('fopen')) ? false : true);
    }

    public static function disable_functions($function='') {
        if (!function_exists($function)) { return true; }
        $disable_functions = ini_get('disable_functions');
        if (empty($disable_functions)) { return false; }
        $disabled_array = explode(',', $disable_functions);
        foreach ($disabled_array as $disabled) {
            if (strtolower(trim($function)) == strtolower(trim($disabled))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generiert eine zufällige Zahl
     * @param integer $min * Der optionale niedrigste Wert
     * @param integer $max * Der optionale höchste Wert
     * @return number
     */
    public static function rand($min = 0, $max = 0) {
        if ($max && $max <= mt_getrandmax()) {
            $number = mt_rand($min, $max);
        } else {
            $number = mt_rand();
        }
        mt_srand();
        return intval($number);
    }

    /**
     * Generiert Passwörter
     * @return String
     */
    public static function random_password($passwordLength=8, $specialcars=true) {
        $componentsCount = count(self::$passwordComponents);
        if(!$specialcars && $componentsCount == 4) {
            unset(self::$passwordComponents[3]);
            $componentsCount = count(self::$passwordComponents);
        }

        shuffle(self::$passwordComponents); $password = '';
        for ($pos = 0; $pos < $passwordLength; $pos++) {
            $componentIndex = ($pos % $componentsCount);
            $componentLength = strlen(self::$passwordComponents[$componentIndex]);
            $random = self::rand(0, $componentLength-1);
            $password .= self::$passwordComponents[$componentIndex]{ $random };
        }

        return $password;
    }

    /**
     * Wandelt ein HTML Text in ein plain text format um.
     * @param $html
     * @param array $options
     * @return mixed
     */
    public static function html2text($html,array $options = array()) {
        $temp = new \Html2Text\Html2Text($html,$options);
        return $temp->getText();
    }

    //=======================================
    // Type Converter
    //=======================================
    public static final function ToString($input) { return (string)$input; }
    public static final function StringToBool($input) { return (strtolower($input) == 'true' ? true : false); }
    public static final function BoolToString($input) { return ($input == true ? 'true' : 'false'); }
    public static final function BoolToInt($input) { return ($input == true ? 1 : 0); }
    public static final function IntToBool($input) { return ($input == 0 ? false : true); }
    public static final function ToInt($input) { return (int)$input; }
    public static final function UTF8($input) { return self::ToString(utf8_encode($input)); }
    public static final function UTF8_Reverse($input) { return utf8_decode($input); }
    public static final function objectToArray($d) { return json_decode(json_encode($d, JSON_FORCE_OBJECT), true); }
    public static final function ArrayToString(array $input) { return serialize($input); }
    public static final function StringToArray($input) { return unserialize($input); }
    public static final function ArrayToJson(array $input) {
        $json = @json_encode($input,JSON_HEX_TAG | JSON_NUMERIC_CHECK | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
        $json_error = json_last_error();
        if($json_error != JSON_ERROR_NONE) {
            self::$log['kernel']->addAlert('JSON Encode Error ->', array('JSON' => $json, 'ERROR' => $json_error));
            return null;
        }

        return $json;
    }

    public static final function JsonToArray($input) {
        $json = @json_decode($input,true);
        $json_error = json_last_error();
        if($json_error != JSON_ERROR_NONE) {
            self::$log['kernel']->addAlert('JSON Decode Error ->', array('JSON' => $json, 'ERROR' => $json_error));
            return null;
        }

        return $json;
    }

    /**
     * Komprimiert einen Binärcode und gibt ihn als HEX-Code zurück
     * @param binary $input
     * @return hex
     */
    public static final function BinToCompressedHex($input) {
        $gz = @gzdeflate($input,-1,ZLIB_ENCODING_RAW);
        if(empty($gz) || !$gz) {
            self::$log['kernel']->addAlert('DEFLATE Compress Error ->', array($input));
            return false;
        }

        $hex = @bin2hex($gz);
        if(empty($hex) || !$hex) {
            self::$log['kernel']->addAlert('BIN to HEX Error ->', array($gz));
            return false;
        }

        return $hex;
    }

    /**
     * Entpackt einen HEX-Code und gibt ihn als Binärcode zurück
     * @param hex $input
     * @return binary
     */
    public static final function CompressedHexToBin($input) {
        $gz = @hex2bin($input);
        if(empty($gz) || !$gz) {
            self::$log['kernel']->addAlert('HEX Decode Error ->', array($gz));
            return false;
        }

        $bin = @gzinflate($gz);
        if(empty($bin) || !$bin) {
            self::$log['kernel']->addAlert('INFLATE Decompress Error ->', array($gz));
            return false;
        }

        return $bin;
    }

    /**
     * Liest PHPInfo aus und schreibt es in ein array.
     * @return array
     */
    public static final function parsePHPInfo() {
        ob_start();
        phpinfo();
        $s = ob_get_contents();
        ob_end_clean();

        $s = strip_tags($s,'<h2><th><td>');
        $s = preg_replace('/<th[^>]*>([^<]+)<\/th>/',"<info>\\1</info>",$s);
        $s = preg_replace('/<td[^>]*>([^<]+)<\/td>/',"<info>\\1</info>",$s);
        $vTmp = preg_split('/(<h2[^>]*>[^<]+<\/h2>)/',$s,-1,PREG_SPLIT_DELIM_CAPTURE);
        $vModules = array(); unset($s);

        for ($i=1;$i<count($vTmp);$i++) {
            if(preg_match('/<h2[^>]*>([^<]+)<\/h2>/',$vTmp[$i],$vMat)) {
                $vName = trim($vMat[1]);
                $vTmp2 = explode("\n",$vTmp[$i+1]);

                foreach ($vTmp2 AS $vOne) {
                    $vPat = '<info>([^<]+)<\/info>';
                    $vPat3 = "/$vPat\s*$vPat\s*$vPat/";
                    $vPat2 = "/$vPat\s*$vPat/";

                    if(preg_match($vPat3,$vOne,$vMat))
                        $vModules[$vName][trim($vMat[1])] = array(trim($vMat[2]),trim($vMat[3]));
                    else if (preg_match($vPat2,$vOne,$vMat))
                        $vModules[$vName][trim($vMat[1])] = trim($vMat[2]);
                }
            }
        }

        if(empty($vModules['apache2handler']['Apache Version']))
            $vModules['apache2handler']['Apache Version'] = self::getEnv('SERVER_SOFTWARE');

        if(empty($PhpInfo['Apache Environment']['HTTP_ACCEPT_ENCODING']))
            $vModules['Apache Environment']['HTTP_ACCEPT_ENCODING']	= self::getEnv('HTTP_ACCEPT_ENCODING');

        return $vModules;
    }
}