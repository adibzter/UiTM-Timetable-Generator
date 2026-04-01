<?php

require_once('./config.php');

function icress_getJadual() {
	$options = array('http' =>
		array(
				"header" => "Referer: https://simsweb4.uitm.edu.my/estudent/class_timetable/index.htm\r\n"
		)
	);
	$context = stream_context_create($options);
	
	$get = file_get_contents(getTimetableURL() . 'cfc/select.cfc?method=CAM_lII1II11I1lIIII11IIl1I111I&key=All&page=1&page_limit=30', false, $context);
	$http_response_header or die("Alert_Error: Icress timeout! Please try again later."); 

	$data = json_decode($get, true);
	$collect = [];

	foreach ($data['results'] as $result) {
		$fullname = $result['text'];

		if ($result['id'] === 'X') {
			continue;
		} else if (strpos($fullname, 'SELANGOR') !== false) {
			$code = $result['id'];
		} else {
			$parts = explode('-', $fullname, 2);
			$code = trim($parts[0]);
			$fullname = trim($parts[1]);
		}

		$collect[] = array('code' => $code, 'fullname' => $fullname);
	}

    return json_encode($collect);
}

function icress_getFaculty() {
	$options = array('http' =>
		array(
				"header" => "Referer: https://simsweb4.uitm.edu.my/estudent/class_timetable/index.htm\r\n"
		)
	);
	$context = stream_context_create($options);

	$get = file_get_contents(getTimetableURL() . 'cfc/select.cfc?method=FAC_lII1II11I1lIIII11IIl1I111I&key=All&page=1&page_limit=30', false, $context);
	$http_response_header or die("Alert_Error: Icress timeout! Please try again later."); 

	$data = json_decode($get, true);
	$collect = [];

	foreach ($data['results'] as $result) {
		$parts = explode('-', $result['text'], 2);
		$code = trim($parts[0]);
		$fullname = trim($parts[1]);

		$collect[] = array('code' => $code, 'fullname' => $fullname);
	}

    return json_encode($collect);
}

function icress_getCampus($campus, $faculty) {
		$formData = [
			'search_campus' => $campus,
			'search_faculty' => $faculty,
			'search_course' => '',
		];

		$mainPageInfo = getIcressMainPageInfo();
		$formData = array_merge($mainPageInfo['hiddenInputs'], $formData);
		$postdata = http_build_query($formData);
		
		$options = array('http' =>
				array(
						'method'  => 'POST',
						'header'  => "Content-Type: application/x-www-form-urlencoded\r\nReferer: https://simsweb4.uitm.edu.my/estudent/class_timetable/index.htm\r\nCookie: {$mainPageInfo['cookieHeader']}",
						'content' => $postdata
				)
		);
		
		$context  = stream_context_create($options);
		
		$get = file_get_contents(getTimetableURL() . $mainPageInfo['submissionPath'], false, $context);
		$http_response_header or die("Alert_Error: Icress timeout! Please try again later."); 

		$get = cleanHTML($get);

		// set error level
		$internalErrors = libxml_use_internal_errors(true);
		$htmlDoc = new DOMDocument();
		$htmlDoc->loadHTML($get);
		// Restore error level
		libxml_use_internal_errors($internalErrors);

		$tableRows = $htmlDoc->getElementsByTagName('tr');
		$subjects = [];

		foreach ($tableRows as $key => $row) {
			if ($key === 0) {
				continue;
			}
			$subject = trim($row->childNodes[3]->nodeValue);
			$subject = str_replace('.', '', $subject);
			$anchors = $row->getElementsByTagName('a');
			$href = $anchors[0]->getAttribute('href');
			
			$subjects[] = array('subject' => $subject, 'path' => $href);
		}

		return json_encode($subjects);
}

function icress_getSubject($path) {
	
    $subjects_output = [];
    
	$subjects_output = icress_getSubject_wrapper($path);

    return json_encode($subjects_output);
}

function icress_getSubject_wrapper($path) {
	$mainPageInfo = getIcressMainPageInfo();

	$options = array('http' =>
		array(
				"header" => "Referer: https://simsweb4.uitm.edu.my/estudent/class_timetable/index.htm\r\nCookie: {$mainPageInfo['cookieHeader']}"
		)
	);
	$context = stream_context_create($options);

	# start fetching the icress data
	$jadual = file_get_contents(getTimetableURL(true) . $path, false, $context);
	$http_response_header or die("Alert_Error: Icress timeout! Please try again later."); 

	# parse the html to more neat representation about classes
	$jadual = str_replace(array("\r", "\n"), '', $jadual);

	// set error level
	$internalErrors = libxml_use_internal_errors(true);
	$htmlDoc = new DOMDocument();
	$htmlDoc->loadHTML($jadual);
	// Restore error level
	libxml_use_internal_errors($internalErrors);

	$tableRows = $htmlDoc->getElementsByTagName('tr');
	$groups = [];

	foreach ($tableRows as $key => $row) {
		if ($key === 0) {
			continue;
		}
		$tableDatas = [];
		foreach($row->childNodes as $tableData) {
			if (strcmp($tableData->nodeName, 'td') === 0) {
				array_push($tableDatas, trim($tableData->nodeValue));
			}
		}

		if (count($tableDatas) < 3) continue;

		// td[0]=no, td[1]=day_time, td[2]=group, td[3]=mode, td[4]=attempt, td[5]=classroom, ...
		$group = $tableDatas[2];
		$tableDatas[1] = normalizeTime($tableDatas[1]); // fix "14:00 PM" -> "2:00 PM"
		array_shift($tableDatas); // remove row number
		$groups[$group][] = $tableDatas;
	}

    return $groups;
}

function cleanHTML($html) {
	$patern = "/<SCRIPT.*?>(.*?)<\/SCRIPT>/si";
	preg_match_all($patern, $html, $parsed);

	$rm_script = $parsed[0][0];

	$html = str_replace($rm_script, "", $html);
	
	return $html;
}

function normalizeTime($time) {
	// Convert nonsensical formats like "14:00 PM" or "08:00 AM" to proper 12-hour format
	return preg_replace_callback('/(\d{1,2}):(\d{2})\s*(AM|PM)/i', function($m) {
		$hour = (int) $m[1];
		$min = $m[2];
		if ($hour >= 13) {
			return ($hour - 12) . ':' . $min . ' PM';
		} else if ($hour == 12) {
			return '12:' . $min . ' PM';
		} else if ($hour == 0) {
			return '12:' . $min . ' AM';
		} else {
			return $hour . ':' . $min . ($hour >= 12 ? ' PM' : ' AM');
		}
	}, $time);
}

function getTimetableURL() {
	return "https://simsweb4.uitm.edu.my/estudent/class_timetable/";
}

function getIcressMainPageInfo() {
	$url = getTimetableURL() . 'index.cfm';
	$hiddenInputs = [];
	$submissionPath = '';

	$icressMainPage = file_get_contents($url);
	$http_response_header or die("Alert_Error: Icress timeout! Please try again later."); 
	
	$receivedCookies = parse_set_cookies($http_response_header);
	$cookieHeader = implode('; ', array_map(
		fn($k,$v) => "$k=$v", array_keys($receivedCookies), $receivedCookies
	));

	// set error level
	$internalErrors = libxml_use_internal_errors(true);
	$htmlDoc = new DOMDocument();
	$htmlDoc->loadHTML($icressMainPage);

	// Restore error level
	libxml_use_internal_errors($internalErrors);

	$inputs = $htmlDoc->getElementsByTagName('input');
	foreach ($inputs as $input) {
		if (strtolower($input->getAttribute('type')) === 'hidden') {
			$hiddenInputs[$input->getAttribute('name')] = $input->getAttribute('value');
		}
	}

	$selects = $htmlDoc->getElementsByTagName('select');
	foreach ($selects as $select) {
		$hiddenInputs[$select->getAttribute('name')] = $select->getAttribute('value');
	}

	$scripts = $htmlDoc->getElementsByTagName('script');
	foreach ($scripts as $script) {
		$js = $script->textContent;

		if (strpos($js, 'check_form_before_submit') === false) {
			continue;
		}

		preg_match_all(
			"/document\.getElementById\(['\"]([^'\"]+)['\"]\)\.value\s*=\s*['\"]([^'\"]+)['\"]/",
			$js,
			$matches,
			PREG_SET_ORDER
		);
		
		foreach ($matches as $m) {
			$hiddenInputs[$m[1]] = $m[2];
		}
		
		if (preg_match("/url:\s*['\"]([^'\"]+\.cfm[^'\"]*)['\"]/", $js, $match)) {
			$submissionPath = $match[1];
		}
	}
	
	return [
		'hiddenInputs' => $hiddenInputs,
		'submissionPath' => $submissionPath,
		'cookieHeader' => $cookieHeader
	];
}

function parse_set_cookies(array $headers): array {
	$cookies = [];
	foreach ($headers as $h) {
		if (stripos($h, 'Set-Cookie:') === 0) {
			if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]+)/i', $h, $m)) {
				$cookies[trim($m[1])] = trim($m[2]);
			}
		}
	}
	return $cookies;
}

?>
