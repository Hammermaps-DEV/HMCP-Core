<?php
/*
 * Diese Datei ist Teil von HM-Core-Module.
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
use kernel;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Ramsey\Uuid\Uuid;
use Rhumsaa\Uuid\Exception\UnsatisfiedDependencyException;
use PHPMailer\PHPMailer\Exception;

class userspace extends module
{
    public static $config = array();

    public static function init() {
        // Logger for Userspace
        self::$log['userspace'] = new Logger('UserSpace');
        self::$log['userspace']->pushHandler(new StreamHandler(ROOT_PATH.'/logs/userspace.log', self::$logger_level));

        // Load INI
        self::$config = self::$ini_config['kernel']->getSection('usersys');

        /**
         * Suche nach Facebook/Google oder Local Access Tokens
         */
        if(($gg_access_token=self::$cookie->get('gg_access_token'))) { //Google

        }

        if(($fb_access_token=self::$cookie->get('gg_access_token'))) { //Facebook

        }

        if(($lc_access_token=self::$cookie->get('gg_access_token'))) { //Local

        }

        //Google
       // $_SESSION['gg_access_token'];

        //Facebook
      //  $_SESSION['fb_access_token'];

        //Local System
      //  $_SESSION['lc_access_token'];
      //  $_SESSION['lc_language'];

     //   echo '<pre>';



      //  echo("<pre>");
        var_dump(self::parsePHPInfo());

       exit();

    }

    /**
     * Login mit Username oder E-Mail
     * @param $indent
     * @param $password
     * Types:
     * lc_username -> string/utf8
     * lc_mail -> string/utf8
     * lc_password -> string/utf8
     */
    public static function RunLogin($indent,$password) {
        $response = array('verify' => false, 'enabled' => false, 'error' => false, 'msgid' => kernel\SystemMassages::SYS_NOT_ERROR);
        $user = self::$database->fetch('SELECT `id`,`enabled`,`lc_password` FROM `{prefix_users}` '.
            'WHERE `lc_username` = ? OR `lc_mail` = ?;',array(self::UTF8($indent),self::UTF8($indent)));
        if(self::$database->rowCount()) {
            if (password_verify($password, self::UTF8_Reverse($user['lc_password']))) {
                if(self::IntToBool($user['enabled'])) {
                    $response['verify'] = true;

                    //Update Session-ID to Database
                    userips::load(intval($user['id']));
                    self::$database->update('UPDATE `{prefix_users}` SET `lc_sessionid` = ?, '.
                        '`lc_last_ips` = ?, `lc_last_login` = ? WHERE `id` = ?;',
                        array(self::UTF8(session_id()),self::UTF8(userips::getIndex()),
                            time(),intval($user['id'])));

                    //Load User-Session Data
                    self::LoadUserDatabase(intval($user['id']));
                    \session::set('lc_id',intval($user['id']));
                    \session::set('lc_last_ip',userips::get());
                } else {
                    $response['error'] = true;
                    $response['msgid'] = kernel\SystemMassages::USER_NOT_ENABLED;
                }
            } else {
                $response['error'] = true;
                $response['msgid'] = kernel\SystemMassages::USER_PASSWORD_FAILED;
            }
        } else {
            $response['error'] = true;
            $response['msgid'] = kernel\SystemMassages::USER_NOT_FOUND;
        }

        return $response;
    }

    /**
     * Das User Passwort zurucksetzen und einen Änderungslink senden.
     * @param int $indent
     */
    public static function ResetPassword(int $indent) {
        $response = array('verify' => false, 'error' => false, 'errormsg' => '',
            'msgid' => kernel\SystemMassages::SYS_NOT_ERROR);
        $uuid = null;
        try {
            $uuid = strtolower(Uuid::uuid4()->toString());
        } catch (UnsatisfiedDependencyException $e) {
            $response['error'] = true;
            $response['msgid'] = kernel\SystemMassages::SYS_FATAL_ERROR;
            $response['msg'] = $e->getMessage();
            self::$log['userspace']->error($response['msg']);
        }

        if($uuid && !$response['error']) {
            if(($get=self::$database->fetch('SELECT `id`,`lc_mail`,`lc_nickname` FROM `{prefix_users}` WHERE `id` = ? AND `enabled` = 1;',
                array($indent)))) {
                userips::load(intval($get['id']));
                if(!self::$database->update('UPDATE `{prefix_users}` SET `lc_enable_key` = ?, `lc_last_ips` = ?, `lc_lost_passwd` = 1 WHERE `id` = ?;',
                    array(self::UTF8($uuid),self::UTF8(userips::getIndex()),$get['id']))) {
                    $response['error'] = true;
                    $response['msgid'] = kernel\SystemMassages::USER_NOT_FOUND;
                } else {
                    //Passwort Änderungsanforderung per E-Mail zusenden
                    $url = 'https://'.self::getEnv('HTTP_HOST').'/user/?passkey='.$uuid;
                    $smarty_temp = self::get_smarty();
                    $smarty_temp->caching = false;
                    $smarty_temp->clearAllAssign();
                    $smarty_temp->assign(array('link' => $url, 'code' => strtoupper($uuid)),true);
                    $email_body = $smarty_temp->fetch('string:'.self::$language_index['userspace']['mail_body_pass_changelink']);

                    //E-Mail an User senden
                    self::$phpmailer->addAddress(self::UTF8_Reverse($get['lc_mail']), self::UTF8_Reverse($get['lc_nickname']));
                    self::$phpmailer->Subject = self::$language_index['userspace']['mail_subject_pass_changelink'];
                    self::$phpmailer->msgHTML($email_body, dirname(__FILE__));
                    self::$phpmailer->AltBody = self::html2text($email_body);
                    unset($smarty_temp,$url,$email_body);

                    try {
                        if (self::$phpmailer->send()) {
                            $response['msgid'] = kernel\SystemMassages::USER_CHANGE_PASSWORD_EMAIL_SEND;
                            $response['verify'] = true;
                        } else {
                            self::$log['userspace']->addAlert('Mailer-Error:', array('UserID' => $get['id'],
                                'Error' => self::$phpmailer->ErrorInfo));
                            $response['errormsg'] = trim(self::$phpmailer->ErrorInfo);
                            $response['msgid'] = kernel\SystemMassages::USER_CHANGE_PASSWORD_EMAIL_SEND_FAILD;
                            $response['error'] = true;
                        }
                    } catch (phpmailerException $e) {
                        self::$log['userspace']->addAlert('Mailer-Error:', array('UserID' => $get['id'],
                            'Error' => $e->errorMessage()));
                        $response['errormsg'] = trim($e->errorMessage());
                        $response['msgid'] = kernel\SystemMassages::USER_EMAIL_PWCH_SEND_FAILD;
                        $response['error'] = true;
                    } catch (Exception $e) {
                        self::$log['userspace']->addAlert('Mailer-Error:', array('UserID' => $get['id'],
                            'Error' => $e->getMessage()));
                        $response['errormsg'] = trim($e->errorMessage());
                        $response['msgid'] = kernel\SystemMassages::USER_EMAIL_PWCH_SEND_FAILD;
                        $response['error'] = true;
                    }
                }
            } else {
                $response['error'] = true;
                $response['msgid'] = kernel\SystemMassages::USER_NOT_FOUND;
            }
        }

        return $response;
    }

    /*
     * ####################################################
     * CHECKER
     * ####################################################
     */
    
    /**
     * Prufung ob User angemeldet ist, optional mit redirect.
     * @param string $redirect
     * @return bool
     */
    public static function verifyLogin($redirect='') {
        userips::load(session::get('lc_id'));
        if(!\session::get('lc_id') ||
            !userips::check(session::get('lc_last_ip')) ||
            !\session::get('enabled')) {
            session_regenerate_id(true);
            if(!empty($redirect)) {
                header('Location: '.$redirect);
                exit;
            }
            return false;
        }

        return true;
    }

    /**
     * @param string $code
     * @return array
     */
    public static function checkLostPass($code='') {
        $response = array('verify' => false, 'error' => false, 'msgid' => kernel\SystemMassages::SYS_NOT_ERROR);
        if(($get=self::$database->fetch('SELECT `id`,`lc_nickname`,`lc_mail` FROM `{prefix_users}` WHERE `lc_enable_key` = ? AND `lc_lost_passwd` = 1 AND `enabled` = 1;',
            array(strtolower($code))))) {
            userips::load($get['id']);
            if(!self::$database->update('UPDATE `{prefix_users}` SET `lc_password` = ?, `lc_enable_key` = null, `lc_last_ips` = ?, `lc_lost_passwd` = 0 WHERE `id` = ?;',
                array(password_hash(($passwd=self::random_password(8)), PASSWORD_DEFAULT),self::UTF8(userips::getIndex()),$get['id']))) {
                $response['error'] = true;
                $response['msgid'] = kernel\SystemMassages::USER_NOT_FOUND;
            } else {
                //Neues Password an User senden
                $smarty_temp = self::get_smarty();
                $smarty_temp->caching = false;
                $smarty_temp->clearAllAssign();
                $smarty_temp->assign(array('username' => self::UTF8_Reverse($get['lc_nickname']), 'pw' => $passwd),true);
                $email_body = $smarty_temp->fetch('string:'.self::$language_index['userspace']['mail_body_pass_changed']);

                //E-Mail an User senden
                self::$phpmailer->addAddress(self::UTF8_Reverse($get['lc_mail']), self::UTF8_Reverse($get['lc_nickname']));
                self::$phpmailer->Subject = self::$language_index['userspace']['mail_subject_pass_changed'];
                self::$phpmailer->msgHTML($email_body, dirname(__FILE__));
                self::$phpmailer->AltBody = self::html2text($email_body);
                unset($smarty_temp,$url,$email_body);

                try {
                    if (self::$phpmailer->send()) {
                        $response['msgid'] = kernel\SystemMassages::USER_CHANGE_PASSWORD_EMAIL_SEND;
                        $response['verify'] = true;
                    } else {
                        self::$log['userspace']->addAlert('Mailer-Error:', array('UserID' => $get['id'],
                            'Error' => self::$phpmailer->ErrorInfo));
                        $response['errormsg'] = trim(self::$phpmailer->ErrorInfo);
                        $response['msgid'] = kernel\SystemMassages::USER_EMAIL_PWCH_SEND_FAILD;
                        $response['error'] = true;
                    }
                } catch (phpmailerException $e) {
                    self::$log['userspace']->addAlert('Mailer-Error:', array('UserID' => $get['id'],
                        'Error' => $e->errorMessage()));
                    $response['errormsg'] = trim($e->errorMessage());
                    $response['msgid'] = kernel\SystemMassages::USER_EMAIL_PWCH_SEND_FAILD;
                    $response['error'] = true;
                } catch (Exception $e) {
                    self::$log['userspace']->addAlert('Mailer-Error:', array('UserID' => $get['id'],
                        'Error' => $e->getMessage()));
                    $response['errormsg'] = trim($e->errorMessage());
                    $response['msgid'] = kernel\SystemMassages::USER_EMAIL_PWCH_SEND_FAILD;
                    $response['error'] = true;
                }
            }
        } else {
            $response['error'] = true;
            $response['msgid'] = kernel\SystemMassages::USER_UAK_NOT_FOUND;
        }

        return $response;
    }

    /**
     * Prufung ob Seite fur User aktiviert ist.
     * @param int $side_id
     * @return bool
     */
    public static function checkEnableSides(int $side_id=0) {
        if(array_key_exists(self::ToInt($side_id),\session::get('lc_enable_sides'))
            && \session::get('lc_enable_sides')[self::ToInt($side_id)]) {
            return true;
        }

        return false;
    }

    /*
     * ####################################################
     * SETTER
     * ####################################################
     */
    /**
     * Setzt fur einen User ein neues Passwort.
     * @param int $indent
     * @param $password
     * @return array
     */
    public static function setPassword(int $indent,$password) {
        $response = array('changed' => false, 'error' => false, 'msgid' => kernel\SystemMassages::SYS_NOT_ERROR);
        if(!empty($password)) {
            if(self::$database->rows('SELECT `id` FROM `{prefix_users}` WHERE `id` = ? AND `enabled` = 1;',
                array($indent))) {
                userips::load($indent);
                //Update User-Password to Database
                if(self::$database->update('UPDATE `{prefix_users}` SET `lc_password` = ?, `lc_last_ips` = ?, WHERE `id` = ?;',
                    array(($passwd=password_hash($password, PASSWORD_DEFAULT)),self::UTF8(userips::getIndex()),$indent))) {
                    \session::set('lc_password', $passwd); unset($passwd);
                    $response['changed'] = true;
                } else {
                    $response['error'] = true;
                    $response['msgid'] = kernel\SystemMassages::USER_CHANGE_PASSWORD_FAILED;
                }
            } else {
                $response['error'] = true;
                $response['msgid'] = kernel\SystemMassages::USER_CHANGE_PASSWORD_NOT_FOUND;
            }
        } else {
            $response['error'] = true;
            $response['msgid'] = kernel\SystemMassages::USER_CHANGE_PASSWORD_EMPTY;
        }

        return $response;
    }

    /**
     * @param int $side_id
     * @param bool $enable
     */
    public static function SetEnableSides(int $indent,int $side_id=0,$enable=true) {
        $lc_enable_sides = \session::get('lc_enable_sides');
        $lc_enable_sides[self::ToInt($side_id)] = $enable;
        if(self::$database->update('UPDATE `{prefix_users}` SET `lc_enable_sides` = ? WHERE `id` = ?;',
            array(self::ArrayToJson($lc_enable_sides),$indent))) {
            \session::set('lc_enable_sides',$lc_enable_sides);
            return true;
        }

        return false;
    }

    /*
     * ####################################################
     * PRIVATE
     * ####################################################
     */

    /**
    * @param int $indent
    */
    private static function LoadUserDatabase(int $indent) {
        $list = array('lc_username','lc_password','lc_nickname',
                      'lc_mail','lc_access_token','lc_language',
                      'lc_enable_sides','lc_last_ips',
                      'gg_access_token','fb_access_token','enabled');

        $sql = ''; foreach ($list as $item) { $sql .= '`'.$item.'`,'; } $sql = substr($sql, 0, -1);
        $user = self::$database->fetch('SELECT '.$sql.' FROM `{prefix_users}` WHERE `id` = ? AND `enabled` = 1;',array($indent));
        if($user) {
            $user['lc_enable_sides'] = self::JsonToArray($user['lc_enable_sides']);
            foreach ($user as $key => $var) {
                if(is_integer($var)) {
                    \session::set($key, $var);
                } else {
                    \session::set($key, self::UTF8_Reverse($var));
                }
            }
        }
    }
}