<?php
/**
 * Created by PhpStorm.
 * User: Lucas
 * Date: 12.05.2016
 * Time: 23:11
 */
function smarty_function_lang($params, &$smarty) {
    if(!count(\kernel\common::$language_index) || !array_key_exists($params['index'], \kernel\common::$language_index)) { # Load Index #
        if (!count(\kernel\common::$language_index)) {
            include_once(ROOT_PATH . '/locale/'.\session::get('language').'/global.php');
        }

        if($params['index'] != 'global') {
            if ($files = \kernel\common::get_filelist('locale/' . \session::get('language') . '/' . $params['index'], false, true, array('php'))) {
                foreach ($files as $file) {
                    include_once(ROOT_PATH . '/locale/' . \session::get('language') . '/' . $params['index'] . '/' . strtolower($file));
                }
            }

            unset($files, $file);
        }
    }

    if(!array_key_exists($params['index'],\kernel\common::$language_index)) {
        return 'Index: "'.$params['index'].'"" is not exists!';
    }

    if(!array_key_exists($params['msgID'],(\kernel\common::$language_index[$params['index']]))) {
        return 'MsgID: "'.$params['msgID'].'"" is not exists!';
    }

    return \kernel\common::$language_index[$params['index']][$params['msgID']];
}