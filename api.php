<?php

require_once('./modules/icress_module.php');

CACHE_TYPE == 'file' ? require_once('./modules/file_module.php') : require_once('./modules/sqlite_module.php');

if(isset($_GET['healthcheck'])) {
    $url = getTimetableURL() . 'cfc/select.cfc?method=CAM_lII1II11I1lIIII11IIl1I111I&key=All&page=1&page_limit=30';
    $referer = getTimetableURL() . 'index.htm';
    $opts = ['http' => [
        'method' => 'GET',
        'header' => "Referer: $referer\r\n",
        'timeout' => 10,
    ]];
    $ctx = stream_context_create($opts);
    $result = @file_get_contents($url, false, $ctx);
    $up = $result !== false && strpos($result, 'results') !== false;
    header('Content-Type: application/json');
    die(json_encode(['up' => $up]));
}

if(isset($_GET['getlist'])) {
    die(CACHE_TYPE == 'file' ? file_getJadual()
        : db_getJadual());
}

if(isset($_GET['getfaculty'])) {
    die(file_getFaculty());
}

if(isset($_GET['getsubject'])) {
    if(!empty($_POST['campus'])) {
        die(CACHE_TYPE == 'file' ? file_getCampus($_POST['campus'], $_POST['faculty'])
            : db_getCampus($_POST['campus']));
    }
}

if(isset($_GET['getgroup'])) {
    if(!empty($_POST['subject']) && !empty($_POST['campus'])) {
        die(CACHE_TYPE == 'file' ? file_getSubject($_POST['campus'], $_POST['faculty'], $_POST['subject'])
            : db_getSubject($_POST['campus'], $_POST['subject']));
    }
}

if(isset($_GET['fetchDataMatrix'])) {
    if(!empty($_POST['studentId'])) {

        try {
            $matricNo = $_POST['studentId'];
            $url = "https://cdn.uitm.link/jadual/baru/{$matricNo}.json";

            $opts = ['http' => [
                'method' => 'GET',
                'header' => "User-Agent: Mozilla/5.0\r\nReferer: https://mystudent.uitm.edu.my/\r\n",
                'timeout' => 15,
            ]];
            $ctx = stream_context_create($opts);
            $result = @file_get_contents($url, false, $ctx);

            if ($result === false) {
                throw new Exception("Failed to fetch timetable for matric no: {$matricNo}. Server may be down.");
            }

            $data = json_decode($result, true);
            if (!$data) {
                throw new Exception("No timetable data found for matric no: {$matricNo}");
            }

            // Collect unique classes from CDN timetable data
            $classes = [];
            $seen = [];
            foreach ($data as $day) {
                if (empty($day['jadual'])) continue;
                foreach ($day['jadual'] as $cls) {
                    $key = $day['hari'] . '-' . $cls['courseid'] . '-' . $cls['masa'];
                    if (isset($seen[$key])) continue;
                    $seen[$key] = true;

                    $masa = preg_replace('/\s*-\s*/', '-', $cls['masa']);
                    $masa = normalizeTime($masa);
                    $classes[] = [
                        'day_time' => strtoupper($day['hari']) . ' ( ' . $masa . ' )',
                        'subject' => $cls['courseid'] ?? '',
                        'subject_name' => $cls['course_desc'] ?? '',
                        'group' => $cls['groups'] ?? '',
                        'classroom' => $cls['bilik'] ?? '',
                        'lecturer' => $cls['lecturer'] ?? '',
                    ];
                }
            }

            if (empty($classes)) {
                throw new Exception("No courses found for matric no: {$matricNo}");
            }

            die(json_encode($classes));

        } catch (Exception $e) {
            die('Alert_Error:' . htmlentities($e->getMessage()));
        }
    }
}
