<?php
require_once 'bootstrap.php';

var_dump(DB_HOST);
exit;

//
//
//
//$initialUrl = 'http://www.imoti.net/agency/show_agency/';
//$startingId = 13869;
//$endId = 21063;
//$mainContentXPath = '//*[contains(@class, \'vhod\')]/table[1]';
//$companyNameXPath = '//h1';
//
//$results = array();
//
//for ($startingId; $startingId < $endId; $startingId++) {
//    $url = $initialUrl . $startingId;
//    $pageHTML = getPageHTML($url);
//
//    if (!empty($pageHTML)) {
//        $data = extractDataFromHTML($pageHTML, $mainContentXPath);
//
//        $results = array(
//            'name' => extractCompanyNameFromHTML($pageHTML, $companyNameXPath),
//            'email' => current(extractEmailAddress(strip_tags($data))),
//            'link' => extractLinks($data)
//        );
//
//        var_dump($results);
//        exit;
//    }
//}
//
//function extractLinks($html)
//{
//    preg_match_all('/(http|https|ftp|ftps)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(\/\S*)?/', $html, $links, PREG_PATTERN_ORDER);
//
//    foreach ($links as $link) {
//        $link = current($link);
//
//        if (!empty($link) && $link != 'http://' && $link != 'http') {
//
//            return $link;
//        }
//    }
//
//    return null;
//}
//
//function extractDataFromHTML($html, $mainContentXPath)
//{
//    $dom = new DOMDocument;
//    @$dom->loadHTML($html);
//
//    $xpath = new DOMXPath($dom);
//
//    $result = $xpath->query($mainContentXPath);
//    $data = $dom->saveHTML($result->item(0));
//
//    return $data;
//}
//
//function extractCompanyNameFromHTML($html, $companyNameXPath)
//{
//    $dom = new DOMDocument;
//    @$dom->loadHTML($html);
//
//    $xpath = new DOMXPath($dom);
//
//    $result = $xpath->query($companyNameXPath);
//
//    $data = $result->item(0)->nodeValue;
//
//    return $data;
//}
//
//function getPageHTML($url)
//{
//    $ch = curl_init();
//
//    if ($ch === false) {
//        die('Failed to create curl object');
//    }
//
//    curl_setopt($ch, CURLOPT_URL, $url);
//    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
//    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
//
//    //proxy details
//    curl_setopt($ch, CURLOPT_PROXY, '172.18.1.103:9050');
//    curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
//
//    $data = curl_exec($ch);
//
//    curl_close($ch);
//
//    return $data;
//}
//
//function extractEmailAddress($string)
//{
//    $emails = array();
//    $string = str_replace("\r\n", ' ', $string);
//    $string = str_replace("\n", ' ', $string);
//
//    foreach (preg_split('/ /', $string) as $token) {
//        $email = filter_var($token, FILTER_VALIDATE_EMAIL);
//        if ($email !== false) {
//            $emails[] = $email;
//        }
//    }
//    return $emails;
//}