<?php

class Twig_Extensions_Extension_Tinyboard extends Twig_Extension
{
	/**
	* Returns a list of filters to add to the existing list.
	*
	* @return array An array of filters
	*/
	public function getFilters()
	{
		return array(
			new Twig_SimpleFilter('filesize', 'format_bytes'),
			new Twig_SimpleFilter('truncate', 'twig_truncate_filter'),
			new Twig_SimpleFilter('truncate_body', 'truncate'),
			new Twig_SimpleFilter('extension', 'twig_extension_filter'),
			new Twig_SimpleFilter('sprintf', 'sprintf'),
			new Twig_SimpleFilter('capcode', 'capcode'),
			new Twig_SimpleFilter('hasPermission', 'twig_hasPermission_filter'),
			new Twig_SimpleFilter('date', 'twig_date_filter'),
			new Twig_SimpleFilter('poster_id', 'poster_id'),
			new Twig_SimpleFilter('remove_whitespace', 'twig_remove_whitespace_filter'),
			new Twig_SimpleFilter('count', 'count'),
			new Twig_SimpleFilter('ago', 'ago'),
			new Twig_SimpleFilter('until', 'until'),
			new Twig_SimpleFilter('push', 'twig_push_filter'),
			new Twig_SimpleFilter('bidi_cleanup', 'bidi_cleanup'),
			new Twig_SimpleFilter('addslashes', 'addslashes')
		);
	}
	
	/**
	* Returns a list of functions to add to the existing list.
	*
	* @return array An array of filters
	*/
	public function getFunctions()
	{
		return array(
			new Twig_SimpleFunction('time', 'time'),
			new Twig_SimpleFunction('floor', 'floor'),
			new Twig_SimpleFunction('timezone', 'twig_timezone_function'),
			new Twig_SimpleFunction('hiddenInputs', 'hiddenInputs'),
			new Twig_SimpleFunction('hiddenInputsHash', 'hiddenInputsHash'),
		);
	}
	
	/**
	* Returns the name of the extension.
	*
	* @return string The extension name
	*/
	public function getName()
	{
		return 'tinyboard';
	}
}

function twig_timezone_function() {
	return 'Z';
}

function twig_push_filter($array, $value) {
	array_push($array, $value);
	return $array;
}

function twig_remove_whitespace_filter($data) {
	return preg_replace('/[\t\r\n]/', '', $data);
}

function twig_date_filter($date, $format) {
	// gmstrftime() is deprecated as of PHP 8.1 and removed in PHP 9.0.
	// Convert the strftime format string to its date()/gmdate() equivalent so
	// the behavior stays identical on all supported PHP versions.
	static $strftimeToDateFormat = array(
		// Weekday
		'%a' => 'D', '%A' => 'l', '%w' => 'w',
		// Day of month/year (strftime space-pads %e; handled below)
		'%d' => 'd',
		// Week number (zero-padded ones via sentinel tokens below)
		'%u' => 'N',
		// Month
		'%b' => 'M', '%B' => 'F', '%h' => 'M', '%m' => 'm',
		// Year
		'%y' => 'y', '%Y' => 'Y',
		// Time (strftime space-pads %k and %l; handled below)
		'%H' => 'H', '%I' => 'h', '%M' => 'i',
		'%p' => 'A', '%P' => 'a', '%r' => 'h:i:s A', '%R' => 'H:i',
		'%S' => 's', '%T' => 'H:i:s', '%X' => 'H:i:s', '%Z' => 'T',
		// Date/time representations
		'%D' => 'm/d/y', '%F' => 'Y-m-d',
		'%g' => 'y', '%x' => 'm/d/y',
		// Seconds since the epoch / whitespace / literal percent
		'%s' => 'U', '%n' => "\n", '%t' => "\t", '%%' => '%',
	);

	// Specifiers that cannot be expressed directly with date():
	//   * zero/space padded values: %e %k %l (space), %j %U %W %V (zero)
	//   * calendar week numbers: %U %W %V (date() "W" is ISO-8601 only)
	//   * century derived from the week-numbering year: %G %C
	// They are replaced with sentinel tokens before formatting and substituted
	// with the exact strftime()-compatible value afterwards.
	$tokens = array();
	$tokenize = function ($spec, $value) use (&$format, &$tokens) {
		if (($pos = strpos($format, $spec)) !== false) {
			$token = "\x01" . count($tokens) . "\x02";
			$format = substr_replace($format, $token, $pos, 2);
			$tokens[$token] = $value;
		}
	};

	if (($pos = strpos($format, '%c')) !== false) {
		// glibc's %c is "%a %b %e %T %Y"; expand it so %e gets tokenized too.
		$day = gmdate('j', $date);
		// glibc's %c is "%a %b %e %T %Y": weekday and month are left-aligned in
		// a 3-char field, the day is right-aligned (space-padded).
		$expansion = str_pad(gmdate('D', $date), 3) . ' '
			. str_pad(gmdate('M', $date), 3) . ' '
			. str_pad($day, 2, ' ', STR_PAD_LEFT)
			. gmdate(' H:i:s Y', $date);
		$token = "\x01" . count($tokens) . "\x02";
		$format = substr_replace($format, $token, $pos, 2);
		$tokens[$token] = $expansion;
	}

	foreach (array('%e', '%E', '%k', '%O', '%l', '%j', '%U', '%V', '%W', '%C', '%G') as $spec) {
		if (strpos($format, $spec) === false)
			continue;
		switch ($spec) {
			case '%e': // day of month, space-padded
				$v = gmdate('j', $date);
				$tokenize($spec, strlen($v) < 2 ? ' ' . $v : $v);
				break;
			case '%E': // day of month, no padding
				$tokenize($spec, gmdate('j', $date));
				break;
			case '%k': // hour (24-hour clock), space-padded
				$v = gmdate('G', $date);
				$tokenize($spec, strlen($v) < 2 ? ' ' . $v : $v);
				break;
			case '%O': // hour (24-hour clock), no padding
				$tokenize($spec, gmdate('G', $date));
				break;
			case '%l': // hour (12-hour clock), space-padded
				$v = gmdate('g', $date);
				$tokenize($spec, strlen($v) < 2 ? ' ' . $v : $v);
				break;
			case '%j': // day of the year, zero-padded
				$v = (int)gmdate('z', $date) + 1;
				$tokenize($spec, sprintf('%03d', $v));
				break;
			case '%U': // week number, Sunday as first day of week
				$offset = (int)gmdate('w', strtotime('first day of january GMT', $date));
				$tokenize($spec, sprintf('%02d', intdiv((int)gmdate('z', $date) + $offset, 7)));
				break;
			case '%W': // week number, Monday as first day of week
				$offset = (int)gmdate('w', strtotime('first day of january GMT', $date));
				$tokenize($spec, sprintf('%02d', intdiv((int)gmdate('z', $date) + $offset, 7)));
				break;
			case '%V': // ISO-8601 week number
				$tokenize($spec, gmdate('W', $date));
				break;
			default: // %C and %G derive from the ISO week-numbering year
				if ($spec === '%C')
					$tokenize($spec, gmdate('Y', $date)[0] . gmdate('Y', $date)[1]);
				else // %G: week-based year
					$tokenize($spec, gmdate('o', $date));
			}
		}

		$converted = '';
	$length = strlen($format);
	for ($i = 0; $i < $length; $i++) {
		if ($format[$i] === '%' && $i + 1 < $length) {
			$spec = '%' . $format[$i + 1];
			if (isset($strftimeToDateFormat[$spec])) {
				$converted .= $strftimeToDateFormat[$spec];
				$i++;
				continue;
			}
		}
		// Escape literal characters (and any unknown specifiers) for date()
		if (ctype_alnum($format[$i])) {
			$converted .= '\\' . $format[$i];
		} else {
			$converted .= $format[$i];
		}
	}

	$out = gmdate($converted, $date);

	foreach ($tokens as $token => $value) {
		$out = str_replace($token, $value, $out);
	}

	return $out;
}

function twig_hasPermission_filter($mod, $permission, $board = null) {
	return hasPermission($permission, $board, $mod);
}

function twig_extension_filter($value, $case_insensitive = true) {
	$ext = mb_substr($value, mb_strrpos($value, '.') + 1);
	if($case_insensitive)
		$ext = mb_strtolower($ext);		
	return $ext;
}

function twig_sprintf_filter( $value, $var) {
	return sprintf($value, $var);
}

function twig_truncate_filter($value, $length = 30, $preserve = false, $separator = '…') {
	if (mb_strlen($value) > $length) {
		if ($preserve) {
			if (false !== ($breakpoint = mb_strpos($value, ' ', $length))) {
				$length = $breakpoint;
			}
		}
		return mb_substr($value, 0, $length) . $separator;
	}
	return $value;
}

