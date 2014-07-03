<?php

class PDS_SS_Parse_INI {

    //  Parse
    public static function parse($filename, $sections = false) {
        $ini_arr = parse_ini_file($filename, $sections);
        if ($ini_arr === FALSE) {
            return FALSE;
        }
        self::fix_ini_multi(&$ini_arr);
        return $ini_arr;
    }

    //  Fix Multi-Dimensional Data
    private static function fix_ini_multi(&$ini_arr) {
        foreach ($ini_arr AS $key => &$value) {
            if (is_array($value)) {
                self::fix_ini_multi($value);
            }
            if (strpos($key, '.') !== FALSE) {
                $key_arr = explode('.', $key);
                $last_key = array_pop($key_arr);
                $cur_elem = &$ini_arr;
                foreach ($key_arr AS $key_step) {
                    if (!isset($cur_elem[$key_step])) {
                        $cur_elem[$key_step] = array();
                    }
                    $cur_elem = &$cur_elem[$key_step];
                }
                $cur_elem[$last_key] = $value;
                unset($ini_arr[$key]);
            }
        }
    }

}
