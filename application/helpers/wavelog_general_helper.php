<?php if ( ! defined('BASEPATH')) exit('No such file or directory');

/**
 * Returns a PHP DateTimeZone-compatible offset string ("+HH:MM"/"-HH:MM")
 * for the user's configured local timezone when the local time display
 * preference is enabled, otherwise plain "UTC". Intended for APIs that take
 * a real timezone identifier (e.g. Predict_Time::daynum2readable(), or
 * `new DateTimeZone(...)`) rather than the manual epoch-shifting trick used
 * elsewhere in this helper -- DateTimeZone accepts a raw "+HH:MM" offset
 * directly, so no Etc/GMT lookup (which only covers whole hours) is needed.
 */
if (!function_exists('display_timezone_string')) {
    function display_timezone_string() {
        $CI =& get_instance();
        if (($CI->session->userdata('user_time_display') ?? 'utc') !== 'local') {
            return 'UTC';
        }
        $tmp_utc = convert_local_to_utc("00:00", date("Y-m-d"));
        $offset_seconds = strtotime(date("Y-m-d")." 00:00 UTC") - strtotime($tmp_utc['date']." ".$tmp_utc['time']." UTC theme");
        $sign = $offset_seconds >= 0 ? '+' : '-';
        $abs_minutes = intdiv(abs($offset_seconds), 60);
        return sprintf('%s%02d:%02d', $sign, intdiv($abs_minutes, 60), $abs_minutes % 60);
    }
}

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
 * Display helper: for QSL/LoTW/eQSL/Clublog/DCL sent/received dates.
 * These columns are usually date-only (stored with a placeholder 00:00:00
 * time), so shifting them by the local offset would fabricate a time-of-day
 * and could roll the date onto the wrong day. When the stored value does
 * carry a real time component, it is localized like any other timestamp.
 */
if (!function_exists('display_qsl_date')) {
    function display_qsl_date($utc_date) {
        if (empty($utc_date)) {
            return '';
        }
        if (date('H:i:s', strtotime($utc_date)) === '00:00:00') {
            return date('Y-m-d', strtotime($utc_date));
        }
        return display_qso_date($utc_date);
    }
}

/**
 * Same as display_qsl_date(), but also appends the time when the stored
 * value carries a real (non-midnight-placeholder) time component.
 */
if (!function_exists('display_qsl_datetime')) {
    function display_qsl_datetime($utc_date) {
        if (empty($utc_date)) {
            return '';
        }
        if (date('H:i:s', strtotime($utc_date)) === '00:00:00') {
            return date('Y-m-d', strtotime($utc_date));
        }
        return display_qso_date($utc_date) . ' ' . display_qso_time($utc_date);
    }
}

/**
 * Display helper: Convert UTC to Local time and return a full "Y-m-d H:i:s"
 * datetime string, preserving seconds. display_qso_time()/display_qso_date()
 * truncate to H:i, which loses precision for edit fields that round-trip
 * back to convert_local_to_utc() on save (e.g. the QSO edit modal).
 */
if (!function_exists('display_qso_datetime')) {
    function display_qso_datetime($utc_time) {
        $CI =& get_instance();
        $time_display = $CI->session->userdata('user_time_display') ?? 'utc';
        $user_timezone_id = $CI->session->userdata('user_timezone') ?? 24;

        if ($time_display !== 'local') {
            return date('Y-m-d H:i:s', strtotime($utc_time));
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
        return date('Y-m-d H:i:s', $timestamp);
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