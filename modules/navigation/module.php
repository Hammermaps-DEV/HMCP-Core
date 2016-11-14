<?php
/*
 * Diese Datei ist Teil von HM-Navigation-Module.
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

namespace module\navigation;
use kernel\common;

class module extends common
{
    public static function init() {
        //Register Function
        self::$smarty->registerPlugin("function", "navigation", array('\module\navigation\module', 'getNavigation'), false);
     //   echo '<pre>';
      //  self::getNavigation(false,false);
    //    echo '</pre>';
     //   exit();
    }

    public static function getNavigation($params, $smarty)
    {
        $category_info = ""; $smarty = self::get_smarty();
        foreach(self::getMainLevel() as $category) {
            $sub_level_1_info = "";
            foreach(self::getSubLevel($category['id'],1) as $sub_level_1) {
                $sub_level_2_info = "";
                foreach(self::getSubLevel($sub_level_1['id'],2) as $sub_level_2) {
                    $sub_level_3_info = "";
                    // Level 3
                    foreach(self::getSubLevel($sub_level_2['id'],3) as $sub_level_3) {
                        $smarty->clearAllAssign();
                        $smarty->assign('link', self::UTF8_Reverse($sub_level_3['link']));
                        $smarty->assign('target', self::UTF8_Reverse($sub_level_3['target']));
                        $smarty->assign('name', self::UTF8_Reverse($sub_level_3['name']));
                        $cache_id = md5('navi_'.$category['id'].':'.$sub_level_1['id'].':'.
                            $sub_level_2['id'].':'.$sub_level_3['id'].'_'.self::$smartyCacheId);
                        $sub_level_3_info .= $smarty->fetch('file:['.\session::get('template').']/navigation/dropdown-menu-li.tpl',$cache_id);
                    } // foreach

                    // Level 2
                    if(!empty($sub_level_3_info)) {
                        $smarty->clearAllAssign();
                        $smarty->assign('name', self::UTF8_Reverse($sub_level_2['name']));
                        $smarty->assign('show', $sub_level_3_info);
                        $cache_id = md5('navi_'.$category['id'].':'.$sub_level_1['id'].':'. $sub_level_2['id'].'_'.self::$smartyCacheId);
                        $sub_level_2_info .= $smarty->fetch('file:['.\session::get('template').']/navigation/second_sub_level.tpl',$cache_id);
                    } else {
                        $smarty->clearAllAssign();
                        $smarty->assign('link', self::UTF8_Reverse($sub_level_2['link']));
                        $smarty->assign('target', self::UTF8_Reverse($sub_level_2['target']));
                        $smarty->assign('name', self::UTF8_Reverse($sub_level_2['name']));
                        $cache_id = md5('navi_'.$category['id'].':'.$sub_level_1['id'].':'. $sub_level_2['id'].'_'.self::$smartyCacheId);
                        $sub_level_2_info .= $smarty->fetch('file:['.\session::get('template').']/navigation/dropdown-menu-li.tpl',$cache_id);
                    }
                }  // foreach

                // Level 1
                if(!empty($sub_level_2_info)) {
                    $smarty->clearAllAssign();
                    $smarty->assign('name', self::UTF8_Reverse($sub_level_1['name']));
                    $smarty->assign('show', $sub_level_2_info);
                    $cache_id = md5('navi_'.$category['id'].':'.$sub_level_1['id'].'_'.self::$smartyCacheId);
                    $sub_level_1_info .= $smarty->fetch('file:['.\session::get('template').']/navigation/main_sub_level.tpl',$cache_id);
                } else {
                    $smarty->clearAllAssign();
                    $smarty->assign('link', self::UTF8_Reverse($sub_level_1['link']));
                    $smarty->assign('target', self::UTF8_Reverse($sub_level_1['target']));
                    $smarty->assign('name', self::UTF8_Reverse($sub_level_1['name']));
                    $cache_id = md5('navi_'.$category['id'].':'.$sub_level_1['id'].'_'.self::$smartyCacheId);
                    $sub_level_1_info .= $smarty->fetch('file:['.\session::get('template').']/navigation/dropdown-menu-li.tpl',$cache_id);
                }
            }  // foreach

            // Level 0
            if(!empty($sub_level_1_info)) {
                $smarty->clearAllAssign();
                $smarty->assign('name', self::UTF8_Reverse($category['name']));
                $smarty->assign('show', $sub_level_1_info);
                $cache_id = md5('navi_'.$category['id'].'_'.self::$smartyCacheId);
                $category_info .= $smarty->fetch('file:['.\session::get('template').']/navigation/main_level.tpl',$cache_id);
            } else {
                $smarty->clearAllAssign();
                $smarty->assign('link', self::UTF8_Reverse($category['link']));
                $smarty->assign('target', self::UTF8_Reverse($category['target']));
                $smarty->assign('name', self::UTF8_Reverse($category['name']));
                $cache_id = md5('navi_'.$category['id'].'_'.self::$smartyCacheId);
                $category_info .= $smarty->fetch('file:['.\session::get('template').']/navigation/main_level_only_link.tpl',$cache_id);
            }

        }  // foreach

        return $category_info;
    }

    private static function checkPermission() {

    }

    private static function getMainLevel() {
        return self::$database->select("SELECT `id`,`name`,`link`,`target` FROM `{prefix_navigation}` "
                                      ."WHERE `is_main_category` = 1 "
                                      ."AND `in_category` = 0 AND `in_sub_level` = 0 "
                                      ."ORDER BY `position` ASC;");
    }

    private static function getSubLevel($category,$sub_level) {
        return self::$database->select("SELECT `id`,`name`,`link`,`target` FROM `{prefix_navigation}` "
                                      ."WHERE `is_main_category` = 0 "
                                      ."AND `in_category` = ? "
                                      ."AND `in_sub_level` = ? "
                                      ."ORDER BY `position` ASC;",
              array($category,$sub_level));
    }
}