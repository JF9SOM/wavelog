<?php if ( ! defined('BASEPATH')) exit('No such file or directory');

if (!function_exists('display_qso_time')) {
    function display_qso_time($utc_time) {
        $CI =& get_instance();
        
        // ユーザー設定の取得
        $time_display = $CI->session->userdata('user_time_display') ?? 'utc';
        $user_timezone_id = $CI->session->userdata('user_timezone') ?? 24;

        // UTC表示設定ならそのまま返す
        if ($time_display !== 'local') {
            return date('H:i', strtotime($utc_time));
        }

        $offset_seconds = 0;
        try {
            // --- nameカラムから (GMT+HH:MM) を抽出 ---
            $timezone_query = $CI->db->query('SELECT name FROM timezones WHERE id = ?', [$user_timezone_id]);
            if ($timezone_query->num_rows() > 0) {
                $timezone_name = $timezone_query->row()->name;
                // 正規表現で (GMT+09:00) などの部分を解析
                if (preg_match('/\(GMT([+-]\d{2}):(\d{2})\)/', $timezone_name, $matches)) {
                    $offset_hours = (int)$matches[1];
                    $offset_minutes = (int)$matches[2];
                    $offset_seconds = ($offset_hours * 3600) + ($offset_hours >= 0 ? 1 : -1) * ($offset_minutes * 60);
                }
            }
        } catch (Exception $e) {
            $offset_seconds = 0;
        }

        // 抽出した秒数をUTCタイムスタンプに加算
        $timestamp = strtotime($utc_time ?? '1970-01-01 00:00:00') + $offset_seconds;
        
        return date('H:i', $timestamp);
    }
}

if (!function_exists('display_qso_time_label')) {
    function display_qso_time_label() {
        $CI =& get_instance();
        // local か utc かの文字列をそのまま返す
        return $CI->session->userdata('user_time_display') ?? 'utc';
    }
}