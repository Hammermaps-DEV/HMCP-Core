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

namespace module\core;

use kernel\common;
use \Composer\Autoload as composer;

/**
 * Created by PhpStorm.
 * User: Lucas
 * Date: 28.04.2016
 * Time: 22:11
 * ======= Base Module =======
 */
class module extends common
{
    public static function init() {
        if(!self::$is_cronjob && !self::$is_jsonapi) { // Not in cronjob or jsonapi call
            //========================================
            //Template change over Cookie or Default
            //========================================
            if (self::$cookie->get('template')) {
                if(array_key_exists(self::$cookie->get('template'), self::get_smarty()->getTemplateDir())) { //Check is template exists
                    self::$log['kernel']->addDebug('[Template-Cookie]: Change Template to ->',
                        array(self::getIp(), self::$cookie->get('template')));
                    \session::set('template', self::$cookie->get('template'));
                } else {
                    self::$log['kernel']->addAlert('[Template-Cookie]: Template is not exists! ->',
                        array(self::getIp(), self::$cookie->get('template')));
                    \session::set('template', 'hmv000'); //Default
                    self::$cookie->put('template', 'hmv000'); //Default
                    self::$cookie->save();
                }
            } else {
                if(is_null(\session::get('template'))) {
                    \session::set('template', 'hmv000'); //Default
                }
            }

            //========================================
            //Template change over Dropdown-Menu
            //========================================
            if (self::$gump->is_valid(self::$input_post, array('template_switch' => 'required',
                    'template' => 'required|max_len,100|min_len,5')) === true
            ) {
                self::$no_router = true; //Disable Router
                unset(self::$input_post['template_switch']);
                if(array_key_exists(self::$cookie->get('template'), self::get_smarty()->getTemplateDir())) { //Check is template exists
                    self::$log['kernel']->addDebug('[Template-Switch]: Change Template to ->',
                        array(self::getIp(), self::$input_post));
                    \session::set('template', self::$input_post['template']);
                    self::$cookie->put('template', self::$input_post['template']);
                    self::$cookie->save();
                } else {
                    self::$log['kernel']->addAlert('[Template-Switch]: Template is not exists! ->',
                        array(self::getIp(), self::$input_post));
                }

                //Location Redirect
                if(!is_null(($last_referer = \session::get('last_referer')))) {
                    self::$log['kernel']->addDebug('[Template-Switch]: Redirect to ->', array($last_referer));
                    header("Location: " . $last_referer);
                    exit();
                } else {
                    self::$log['kernel']->addDebug('[Template-Switch]: Redirect to ->', array(self::getBaseURL()));
                    header("Location: " . self::getBaseURL());
                    exit();
                }
            }

            //========================================
            //Set Default language
            //========================================
            if(is_null(\session::get('language'))) {
                \session::set('language', 'de_DE'); //Default
            }

            //========================================
            //Load Default language files
            //========================================
            if(file_exists('locale/'.\session::get('language').'/userspace/massages.php')) {
                composer\includeFile('locale/' . \session::get('language') . '/userspace/massages.php');
            }

            //=======================================
            // include all usersys classes
            //=======================================
            if($files = self::get_filelist('modules/core/usersys',false,true,array('php'),"/.*.class.*/")) {
                self::$log['kernel']->addDebug('Include UserSystem-Files ->',$files);
                foreach($files as $file) {
                    composer\includeFile('modules/core/usersys/'.strtolower($file));
                }
            } unset($files,$file);

            userspace::init();

        } //end out of cronjob or jsonapi call

       // self::register_jsonapi('testcall','test::run','test','test.api.php'); //Register API
       // self::register_cronjob('test::run','test','test.job.php');  //Register CronJob
    }
}