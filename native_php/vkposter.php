<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include "vendor/autoload.php";
include_once "db-config.php";
include_once "config.php";

$stamp = time();
myEcho("->Запуск " . date("Y-m-d H:i:s", $stamp));
$containerBuilder = new DI\ContainerBuilder();
$containerBuilder->addDefinitions('di-config.php');
$container = $containerBuilder->build();
$cron = $container->get('Toastral\Vkstorystats\Cron');

if (!$cron->start()) {
    myEcho("<-Выход т.к. работает другой скрипт ");
    exit();
}
myEcho("-- Импорт групп ");
$getGroups = $container->get('Toastral\Vkstorystats\GetGroups');
$initActiveGroups = $container->get('Toastral\Vkstorystats\InitActiveGroups');
$initActiveGroups->setActiveFlag($getGroups->getItems());

myEcho("-- Инициализация объектов ");
$vkStoryIds = $container->get('Toastral\Vkstorystats\VkStoryIds');
$vkStoryStat = $container->get('Toastral\Vkstorystats\VkStoryStat');
$initActiveStories = $container->get('Toastral\Vkstorystats\InitActiveStories');
$initApiStats = $container->get('Toastral\Vkstorystats\InitApiStats');
$initDiffStats = $container->get("Toastral\Vkstorystats\InitDiffStats");
$initTableStats = $container->get("Toastral\Vkstorystats\InitTableStats");
$groupIds = array_keys($getGroups->getItems());
myEcho("-- Всего групп: " . count($groupIds));
foreach ($groupIds as $groupId) {
    myEcho("-- -- Обработка группы id: " . $groupId);
    $storyIds = $vkStoryIds->get("-$groupId");
    usleep(500000); // пол секунды
    myEcho("-- -- Получено историй: " . count($storyIds));
    $initActiveStories->setActiveFlag($storyIds, $groupId);
    foreach ($storyIds as $storyId) {
        myEcho("-- -- Сохраняем данные истории id: " . $storyId);
        $initApiStats->import($groupId, $storyId, $stamp);
        usleep(500000); // пол секунды
        $initDiffStats->updateDiff($groupId, $storyId, $stamp);
    }
}

myEcho("-- Вычисление сумм ");
foreach ($groupIds as $groupId) {
    myEcho("-- -- Обработка группы id: " . $groupId);
    $initTableStats->proc($groupId);
}

myEcho("-- Очистка таблицы ");
$initTableStats->delUnusedGroups($groupIds);
$initTableStats->delOldRows();

$cron->stop();

myEcho("<-Штатный выход");

function myEcho($message)
{
    echo "$message\n";
}