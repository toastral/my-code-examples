<?php
$html = file_get_contents("brands.html");
preg_match_all("|<a href=\"(/collection/[^\"]+)\">([^<]+)</a>|", $html, $matches);
$barndItems = [];
$aUrls = [];
foreach ($matches[1] as $i => $url) {
    $title = $matches[2][$i];
    $title = preg_replace("|\s+|", " ", $title);
    preg_match("|/collection/(.*?)$|", $url, $mchs);
    if (in_array($url, $aUrls)) continue;
    $barndItems[] = [
        "alias" => $mchs[1],
        "url" => $url,
        "title" => $title,
        "level" => "2",
        "parent" => "1",
    ];
    $aUrls[] = $url;
}

$allcatshtml = file_get_contents("../fillcatalias/cats.json");
$allcatsjson = json_decode($allcatshtml);
$brandItemsFiltered = [];
$count = 0;
$index = 1000;
foreach ($barndItems as $brandRow) {
    $brandAlias = $brandRow["alias"];
    $double = false;
    foreach ($allcatsjson as $item) {
        if ($item->alias == $brandAlias) {
            $count++;
            $double = true;
            break;
        }
    }
    if ($double) continue;
    $brandItemsFiltered[$index] = $brandRow;
    $index++;
}

echo "\ncount=" . count($brandItemsFiltered) . "\n";

file_put_contents("brands.json", json_encode(["items" => $brandItemsFiltered]));
