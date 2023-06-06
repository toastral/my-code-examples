<?php

namespace App\Controller;

use App\Repository\FrequencyRepository;
use App\Service\ApiService;
use App\Service\CheckProcessed;
use App\Service\DataFormatter;
use App\Service\FeedManager;
use App\Service\FrequencyManager;
use App\Service\KeyService;
use App\Service\ParserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

class FeedController extends AbstractController
{

    #[Route('feed/native', name: 'feed_native', methods: ['GET'])]
    public function feed(Request             $request,
                         CheckProcessed      $checkProcessed,
                         ApiService          $apiService,
                         KeyService          $keyService,
                         ParserService       $parserService,
                         FrequencyRepository $frequencyRepository,
                         DataFormatter       $dataFormatter,
    ): Response
    {
        $keyidsStr = $request->query->get('keyids');
        $offset = $request->query->get('offset');
        $qty = $request->query->get('qty');

        $keyIds = explode(",", $keyidsStr);

        sort($keyIds);

        $assocIdKeys = $keyService->getIdKeysByIds($keyIds);

        $query = $keyService->makeQueryByKeyId($keyIds, " MAYBE ");
        $apiDataArr = $apiService->fetchData($query);
        $apiTargetUrl = $apiService->lastUrl;
        $ytIds = $parserService->extractYtIdsFromArr($apiDataArr);

        if ($qty > 0 && $offset >= 0) {
            $ytIds = array_slice($ytIds, $offset, $qty);
        }

        $processedYtIds = $checkProcessed->check($ytIds);
        $processedNativeArr = $apiService->fetchCachedVideoBatch($processedYtIds);
        $frequencySumArr = $frequencyRepository->get($keyIds, $processedYtIds);
        $assocSumHits = $dataFormatter->frequencySumArrToSumHits($frequencySumArr, $assocIdKeys);

        $processedFormattedArr = [];
        $idx = 0;
        foreach ($processedNativeArr as $nativeArr) {
            $ytId = $nativeArr["result"]["id"];
            $formatted = $dataFormatter->format($nativeArr, ++$idx, 'database');
            $formatted = $dataFormatter->appendSumHits($formatted, $assocSumHits[$ytId]);
            $processedFormattedArr[$ytId] = $formatted;
        }

        $newYtIds = array_values(array_diff($ytIds, $processedYtIds));
        $newNativeArr = $apiService->fetchDataBatch($query, $newYtIds);
        $newFormattedArr = [];
        $idx = 0;
        $keys = array_values($assocIdKeys);
        foreach ($newNativeArr as $nativeArr) {
            $ytId = $nativeArr["result"]["id"];
            $nativeHits = $nativeArr["hits"];
            $sumHits = $dataFormatter->nativeHitsToSumHits($nativeHits, $keys);
            $formatted = $dataFormatter->format($nativeArr, ++$idx, 'direct');
            $formatted = $dataFormatter->appendSumHits($formatted, $sumHits);
            $newFormattedArr[$ytId] = $formatted;
        }

        // выставляем order
        $order = 0;
        $resArr = [];
        foreach ($ytIds as $ytId) {
            $item = null;
            if (isset($newFormattedArr[$ytId])) {
                $item = $newFormattedArr[$ytId];
            }
            if (isset($processedFormattedArr[$ytId])) {
                $item = $processedFormattedArr[$ytId];
            }
            if (empty($item)) continue;
            $item["result"]["order"] = ++$order;
            unset($item["hits"]);
            $resArr[] = $item;
        }


        $tmp = explode("?", $apiTargetUrl);
        $jsonData = $this->json([
            'apiTargetUrl' => $apiTargetUrl,
            'apiClearUrl' => "http://localhost:8000/api/clear?" . $tmp[1],
            'fromCache' => $apiDataArr["fromCache"],
            'api_query' => $apiDataArr["query"],
            'queryCacheKey' => $apiDataArr["queryCacheKey"],
            'queryVideoID' => $apiDataArr["queryVideoID"],
            'responseVideoID' => $apiDataArr["responseVideoID"],
            'keyids' => $keyIds,
            'feed_query' => $keyService->makeQueryByKeyId($keyIds, " MAYBE "),
            'feed_query_md5' => md5($keyService->makeQueryByKeyId($keyIds, " MAYBE ")),
            'offset' => $offset,
            'qty' => $qty,
            'ytIds' => $ytIds,
            "resArr" => $resArr
        ]);

        return $jsonData;
    }

}
