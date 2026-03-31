<?php

require_once('./modules/istudent_module.php');
require_once('./modules/icress_module.php');
/** @deprecated */
// require_once('./modules/excel_module.php'); 

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

            $obj = new IStudent($_POST['studentId']);

            $courses = $obj->getCourses();
            $uitmcode = $obj->getUiTMCode();

            if($courses === null || $uitmcode === false) {
                throw new Exception("Can't fetch resources for this student Id (" . $_POST['studentId'] . ") !");
            }

            die(json_encode(array(
                'Courses' => $courses,
                'UiTMCode' => $uitmcode)));

        } catch (Exception $e) {
            die('Alert_Error:' . htmlentities($e->getMessage()));
        }
    }
}

/**
 * @deprecated
 */
if(isset($_GET['exportexcel'])) {
  if(!empty($_POST['timetableInfo'])) {
    $obj = new Excel();
    $result = $obj->exportExcel($_POST['timetableInfo']);
    die($result);
  }
}

/**
 * @deprecated
 */
if(isset($_GET['importexcel'])) {
  if(!empty($_FILES['excelFile'])) {
    $obj = new Excel();
    $result = $obj->importExcel($_FILES['excelFile']);
    die($result);
  }
}
