<?php

$cats = file('cats.txt');
$catId = intval(file_get_contents('base/curcat.txt'));
$curPage = intval(file_get_contents('base/curpage.txt'));
if (!$curPage) $curPage = 1;

while ($catId <= count($cats)) {
    echo $catId . "\n";
    file_put_contents('base/curpage.txt', 1); // сброс
    $productLinks = parseCatWithPages($catId, $curPage);
    $productLinks = makeUniq($productLinks);
    saveLinks($productLinks);
    $catId++;
    file_put_contents('base/curcat.txt', $catId);
    break;
}


function parseCatWithPages($catId, $curPage)
{
    global $cats;
    $page = $curPage;
    $productLinks = [];
    $baseUrl = "https://www.nozhikov.ru" . trim($cats[$catId]);
    while (1) {
        $url = $baseUrl . "?page=" . $page;
        $curProductLinks = parseCatPersistent($url);
        if (count($curProductLinks) <= 0)
            break;

        $productLinks = array_merge($productLinks, $curProductLinks);
        $page++;
        file_put_contents('base/curpage.txt', $page); // сброс
        if ($page > 3)
            break;
    }

    return $productLinks;
}

function parseCatPersistent($url)
{
    sleep(1);
    $html = file_get_contents($url);
    if (isEndPage($html)) {
        return [];
    }
    $productLinks = parseCat($html);
    if (count($productLinks) > 0) {
        return $productLinks;
    }
}

function isEndPage($html)
{
    if (preg_match("|notice is-info|", $html)) {
        return true;
    }
    return false;
}

function saveLinks($productLinks)
{

    foreach ($productLinks as $link) {
        file_put_contents("products.txt", $link . "\n", FILE_APPEND);
    }
}

function parseCat($html)
{
    preg_match_all('|"(/product/[^"]+)"|', $html, $patt);
    if (!isset($patt[1]) || !is_array($patt[1]))
        return [];

    return makeUniq($patt[1]);
}

function makeUniq($arrLinks)
{
    $trimmed = array_map(function ($v) {
        $v = trim($v);
        $v = rtrim($v, "/");
        return $v;
    }, $arrLinks);
    return array_unique($trimmed);
}
