<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * ADIF -> Turbo HAMLOG CSV conversion.
 *
 * PHP port of JF9SOM/ADI2HAMLOG (https://github.com/JF9SOM/ADI2HAMLOG).
 * Output: 16 columns, no header, cp932, CRLF. Domestic stations (DXCC 339) are
 * written in JST ("J"), foreign stations in UTC ("U"), independent of the
 * user's local-time display setting.
 */
class Hamlogcsv {

	private $cfg = [
		'domestic_dxcc' => '339',
		'mode_map' => [
			'MFSK/FT4' => 'FT4',
			'SSB/USB' => 'SSB',
			'SSB/LSB' => 'SSB',
		],
		// DXCC entity number => HAMLOG code (observed values only; unlisted entities get an empty Code)
		'dxcc_code' => [
			'15' => '732A', '54' => '733A', '110' => '663A', '135' => '746A',
			'137' => '634A', '150' => '759A', '288' => '737A', '291' => '784A',
			'318' => '565A', '327' => '794A', '336' => '523A', '375' => '589A',
			'387' => '637A',
		],
		'dx_flag' => ['domestic' => '0', 'default' => '8', '375' => '10'],
		'satellite_bands' => ['V' => [144.0, 148.0], 'U' => [420.0, 450.0]],
		'band_freq' => [
			'160m' => '1.9', '80m' => '3.5', '60m' => '5.3', '40m' => '7.1', '30m' => '10.1',
			'20m' => '14.1', '17m' => '18.1', '15m' => '21.1', '12m' => '24.9', '10m' => '28.1',
			'6m' => '50.1', '4m' => '70.2', '2m' => '144.1', '70cm' => '430.1', '23cm' => '1296.1',
		],
	];

	/**
	 * Parse ADIF text (field lengths are counted in characters, as AdifHelper writes them).
	 *
	 * @return array list of records (field name upper-case => value)
	 */
	public function parse_adif($text) {
		$records = [];
		$pos = 0;
		if (preg_match('/<eoh>/i', $text, $m, PREG_OFFSET_CAPTURE)) {
			$pos = $m[0][1] + 5;
		}
		$current = [];
		$len_bytes = strlen($text);
		while ($pos < $len_bytes) {
			$lt = strpos($text, '<', $pos);
			if ($lt === false) {
				break;
			}
			if (!preg_match('/\G<([^<>:\s]+)(?::(\d+))?(?::[^<>]*)?>/', $text, $m, 0, $lt)) {
				$pos = $lt + 1;
				continue;
			}
			$name = strtoupper($m[1]);
			$pos = $lt + strlen($m[0]);
			if ($name === 'EOR') {
				if (!empty($current)) {
					$records[] = $current;
				}
				$current = [];
				continue;
			}
			if ($name === 'EOH' || !isset($m[2]) || $m[2] === '') {
				continue;
			}
			$length = (int) $m[2];
			$rest = substr($text, $pos);
			$value = mb_substr($rest, 0, $length, 'UTF-8');
			$current[$name] = $value;
			$pos += strlen($value);
		}
		if (!empty($current)) {
			$records[] = $current;
		}
		return $records;
	}

	/**
	 * Convert ADIF text to cp932/CRLF HAMLOG CSV.
	 *
	 * @return array [csv bytes, list of warning strings]
	 */
	public function adif_to_csv($adif_text) {
		$records = $this->parse_adif($adif_text);
		$warnings = [];
		$lines = '';
		foreach ($records as $i => $rec) {
			try {
				$row = $this->convert_qso($rec, $warns);
			} catch (Exception $e) {
				$warnings[] = 'QSO #' . ($i + 1) . ' (' . ($rec['CALL'] ?? '?') . '): skipped: ' . $e->getMessage();
				continue;
			}
			foreach ($warns as $w) {
				$warnings[] = 'QSO #' . ($i + 1) . ' (' . $row[0] . '): ' . $w;
			}
			$lines .= implode(',', array_map([$this, 'csv_field'], $row)) . "\r\n";
		}
		return [$this->to_cp932($lines), $warnings];
	}

	private function csv_field($v) {
		if (strpbrk($v, ",\"\r\n") !== false) {
			return '"' . str_replace('"', '""', $v) . '"';
		}
		return $v;
	}

	private function to_cp932($text) {
		$out = '';
		foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
			$c = mb_convert_encoding($ch, 'CP932', 'UTF-8');
			$out .= ($c === '?' && $ch !== '?') ? '?' : $c;
		}
		return $out;
	}

	private function is_domestic($rec) {
		$dxcc = trim($rec['DXCC'] ?? '');
		if ($dxcc !== '') {
			return $dxcc === $this->cfg['domestic_dxcc'];
		}
		return (bool) preg_match('/^(J[A-S]|7[J-N]|8[J-N])/i', $rec['CALL'] ?? '');
	}

	/** MHz truncated to 3 decimals (kHz), trailing zeros removed; null if unusable. */
	private function format_mhz($value) {
		$value = trim($value);
		if (!preg_match('/^(\d+)(?:\.(\d*))?$/', $value, $m)) {
			return null;
		}
		$int = ltrim($m[1], '0');
		$int = ($int === '') ? '0' : $int;
		$dec = substr(str_pad($m[2] ?? '', 3, '0'), 0, 3);
		if ($int === '0' && (int) $dec === 0) {
			return null;
		}
		$s = $int . '.' . $dec;
		return rtrim(rtrim($s, '0'), '.');
	}

	private function sat_band($mhz) {
		if (!is_numeric($mhz)) {
			return null;
		}
		foreach ($this->cfg['satellite_bands'] as $letter => $range) {
			if ($mhz >= $range[0] && $mhz <= $range[1]) {
				return $letter;
			}
		}
		return null;
	}

	private function freq($rec, &$warns) {
		$sat = strtoupper($rec['PROP_MODE'] ?? '') === 'SAT';
		if ($sat && !empty($rec['FREQ']) && !empty($rec['FREQ_RX'])) {
			$up = $this->sat_band($rec['FREQ']);
			$down = $this->sat_band($rec['FREQ_RX']);
			if ($up && $down) {
				return $up . '/' . $down;
			}
			$warns[] = 'satellite bands not recognised; wrote MHz';
		}
		if (!empty($rec['FREQ'])) {
			$f = $this->format_mhz($rec['FREQ']);
			if ($f !== null) {
				return $f;
			}
			$warns[] = 'FREQ invalid';
		}
		$band = strtolower($rec['BAND'] ?? '');
		if (isset($this->cfg['band_freq'][$band])) {
			$warns[] = 'FREQ missing; used representative frequency for BAND=' . $band;
			return $this->cfg['band_freq'][$band];
		}
		$warns[] = 'no usable FREQ or BAND; left empty';
		return '';
	}

	private function mode($rec) {
		$mode = strtoupper(trim($rec['MODE'] ?? ''));
		$sub = strtoupper(trim($rec['SUBMODE'] ?? ''));
		$table = $this->cfg['mode_map'];
		if ($sub !== '' && isset($table[$mode . '/' . $sub])) {
			return $table[$mode . '/' . $sub];
		}
		if (isset($table[$mode])) {
			return $table[$mode];
		}
		return $sub !== '' ? $sub : $mode;
	}

	private function format_rst($value) {
		$v = trim($value);
		if (preg_match('/^([+-])(\d{1,2})$/', $v, $m)) {
			return $m[1] . sprintf('%02d', (int) $m[2]);
		}
		return $v;
	}

	private function name($rec, &$warns) {
		$name = trim($rec['NAME'] ?? '');
		if ($name === '') {
			return '';
		}
		if (preg_match('/[^\x00-\x7F]/', $name)) {
			$warns[] = 'NAME contains non-ASCII characters; left empty';
			return '';
		}
		return substr(strtoupper(preg_replace('/\s+/', '', $name)), 0, 12);
	}

	/** Convert one ADIF record to the 16 HAMLOG columns. Throws Exception if unusable. */
	public function convert_qso($rec, &$warns = []) {
		$warns = [];
		$call = trim($rec['CALL'] ?? '');
		if ($call === '') {
			throw new Exception('CALL missing');
		}
		$d = trim($rec['QSO_DATE'] ?? '');
		$t = trim($rec['TIME_ON'] ?? '');
		if (!preg_match('/^\d{8}$/', $d)) {
			throw new Exception('QSO_DATE missing or invalid: ' . $d);
		}
		if (!preg_match('/^(\d{4}|\d{6})$/', $t)) {
			throw new Exception('TIME_ON missing or invalid: ' . $t);
		}
		$t = str_pad($t, 6, '0');
		$dt = DateTime::createFromFormat('YmdHis', $d . $t, new DateTimeZone('UTC'));
		$errs = DateTime::getLastErrors();
		if ($dt === false || ($errs && ($errs['warning_count'] || $errs['error_count']))) {
			throw new Exception('invalid date/time ' . $d . ' ' . $t);
		}

		$domestic = $this->is_domestic($rec);
		$dxcc = trim($rec['DXCC'] ?? '');
		if ($domestic) {
			$dt->modify('+9 hours');
		}

		$code = '';
		if (!$domestic) {
			$code = ($dxcc !== '') ? ($this->cfg['dxcc_code'][$dxcc] ?? '') : '';
			if ($code === '') {
				$warns[] = 'no HAMLOG code for DXCC ' . ($dxcc !== '' ? $dxcc : '?') . ' (' . ($rec['COUNTRY'] ?? '') . '); Code left empty';
			}
		}

		$qth = $domestic ? '' : strtoupper(trim($rec['CONT'] ?? ''));
		$jcc = '';
		if ($domestic) {
			$v = trim($rec['COMMENT'] ?? '');
			$jcc = preg_match('/^\d{4,5}$/', $v) ? $v : '';
		}

		$flags = $this->cfg['dx_flag'];
		$flag = $domestic ? $flags['domestic'] : ($flags[$dxcc] ?? $flags['default']);

		$his = $this->format_rst($rec['RST_SENT'] ?? '');
		$my = $this->format_rst($rec['RST_RCVD'] ?? '');
		if ($his === '' || $my === '') {
			$warns[] = 'RST missing';
		}

		$sat = trim($rec['SAT_NAME'] ?? '');
		$rmks1 = trim($jcc . ' ' . $sat);

		return [
			strtoupper($call),
			$dt->format('y/m/d'),
			$dt->format('H:i') . ($domestic ? 'J' : 'U'),
			$his,
			$my,
			$this->freq($rec, $warns),
			$this->mode($rec),
			$code,
			strtoupper(trim($rec['GRIDSQUARE'] ?? '')),
			'J',
			$this->name($rec, $warns),
			$qth,
			$rmks1,
			'',
			$flag,
			'',
		];
	}
}
