<?php if ( ! defined('BASEPATH')) exit('No such file or directory');

/**
 * Display helper: Convert UTC to Local time and return Time.
 */
if (!function_exists('display_qso_time')) {
    function display_qso_time($utc_time) {
        $CI =& get_instance();
        $time_display = $CI->session->userdata('user_time_display') ?? 'utc';
        $user_timezone_id = $CI->session->userdata('user_timezone') ?? 24;

        if ($time_display !== 'local') {
            return date('H:i', strtotime($utc_time));
        }

        $offset_seconds = 0;
        try {
            $timezone_query = $CI->db->query('SELECT name FROM timezones WHERE id = ?', [$user_timezone_id]);
            if ($timezone_query->num_rows() > 0) {
                $timezone_name = $timezone_query->row()->name;
                if (preg_match('/\(GMT([+-]\d{2}):(\d{2})\)/', $timezone_name, $matches)) {
                    $offset_hours = (int)$matches[1];
                    $offset_minutes = (int)$matches[2];
                    $offset_seconds = ($offset_hours * 3600) + ($offset_hours >= 0 ? 1 : -1) * ($offset_minutes * 60);
                }
            }
        } catch (Exception $e) { $offset_seconds = 0; }

        $timestamp = strtotime($utc_time ?? '1970-01-01 00:00:00') + $offset_seconds;
        return date('H:i', $timestamp);
    }
}

/**
 * Display helper: Convert UTC to Local time and return Date (handles rollover).
 */
if (!function_exists('display_qso_date')) {
    function display_qso_date($utc_time) {
        $CI =& get_instance();
        $time_display = $CI->session->userdata('user_time_display') ?? 'utc';
        $user_timezone_id = $CI->session->userdata('user_timezone') ?? 24;

        if ($time_display !== 'local') {
            return date('Y-m-d', strtotime($utc_time));
        }

        $offset_seconds = 0;
        try {
            $timezone_query = $CI->db->query('SELECT name FROM timezones WHERE id = ?', [$user_timezone_id]);
            if ($timezone_query->num_rows() > 0) {
                $timezone_name = $timezone_query->row()->name;
                if (preg_match('/\(GMT([+-]\d{2}):(\d{2})\)/', $timezone_name, $matches)) {
                    $offset_hours = (int)$matches[1];
                    $offset_minutes = (int)$matches[2];
                    $offset_seconds = ($offset_hours * 3600) + ($offset_hours >= 0 ? 1 : -1) * ($offset_minutes * 60);
                }
            }
        } catch (Exception $e) { $offset_seconds = 0; }

        $timestamp = strtotime($utc_time ?? '1970-01-01 00:00:00') + $offset_seconds;
        return date('Y-m-d', $timestamp);
    }
}

/**
 * UI helper: Returns current time display mode (UTC/Local).
 */
if (!function_exists('display_qso_time_label')) {
    function display_qso_time_label() {
        $CI =& get_instance();
        return $CI->session->userdata('user_time_display') ?? 'utc';
    }
}

/**
 * Saving helper: Convert Local time to UTC.
 * Ensures the returned time format (HH:MM or HH:MM:SS) matches the input
 * to prevent JavaScript errors in the browser.
 */
if (!function_exists('convert_local_to_utc')) {
    function convert_local_to_utc($local_time, $local_date) {
        $CI =& get_instance();
        $user_timezone_id = $CI->session->userdata('user_timezone') ?? 24;
        $offset_seconds = 0;

        try {
            $timezone_query = $CI->db->query('SELECT name FROM timezones WHERE id = ?', [$user_timezone_id]);
            if ($timezone_query->num_rows() > 0) {
                $timezone_name = $timezone_query->row()->name;
                if (preg_match('/\(GMT([+-]\d{2}):(\d{2})\)/', $timezone_name, $matches)) {
                    $offset_hours = (int)$matches[1];
                    $offset_minutes = (int)$matches[2];
                    $offset_seconds = ($offset_hours * 3600) + ($offset_hours >= 0 ? 1 : -1) * ($offset_minutes * 60);
                }
            }
        } catch (Exception $e) { $offset_seconds = 0; }

        // Check if input has seconds for matching return format
        $has_seconds = (strlen(trim($local_time)) > 5);

        // Add padding seconds for strtotime if necessary
        if (!$has_seconds) {
            $local_time .= ':00';
        }

        // Calculate UTC timestamp and handle date rollover
        $timestamp = strtotime($local_date . ' ' . $local_time . ' UTC') - $offset_seconds;

        return [
            'time' => $has_seconds ? date('H:i:s', $timestamp) : date('H:i', $timestamp),
            'date' => date('Y-m-d', $timestamp)
        ];
    }
}