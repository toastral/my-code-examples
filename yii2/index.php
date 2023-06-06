<?php

use common\modules\webchecker\models\CheckLogManager;
use common\modules\webchecker\models\Site;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\grid\ActionColumn;
use yii\grid\GridView;

/* @var $this yii\web\View */
/* @var $searchModel common\modules\webchecker\models\SiteSearch */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Sites';
$this->params['breadcrumbs'][] = $this->title;
?>
    <style>
        .status-domain-dot {
            height: 15px;
            width: 15px;
            border-radius: 50%;
            display: inline-block;
        }

        .status-subdomain-dot {
            height: 5px;
            width: 5px;
            border-radius: 50%;
            display: inline-block;
        }

        .bg-err {
            background-color: red;
        }

        .bg-ok {
            background-color: limegreen;
        }

        .li-line {
            display: inline-block;
            width: 5px;
            height: 40px;
            position: absolute;
            left: 3px;
            top: 10px;
        }

        .thick {
            font-weight: bold;
        }

        .sub-list {
            list-style-type: none;
            /* padding: 0;*/ /* Убираем поля */
            padding: 0;
            margin-left: 0px; /* Отступ слева */
        }

        .sub-list li {
            padding: 20px 0 0 0;
        }

        .circle-ok {
            border: 2px solid limegreen;
        }

        .circle-err {
            border: 2px solid red;
        }

        .circle {
            border-radius: 50%;
            background-color: white;

            content: "";
            display: inline-block;
            margin-right: 10px;
            margin-bottom: 2px;
            height: 12px;
            width: 12px;
            position: absolute;
            z-index: 1;
            top: 25px;
        }

        ul.sub-list li {
            position: relative;
        }

        .small-font {
            font-size: 12px;
        }

        .wrap-item {
            display: inline-block;
            /*border: 1px solid blue; */
            padding-left: 18px;
        }

        .time {
            font-size: 12px;
        }

        .expand-sub {

        }

        .show-cursor {
            cursor: pointer;
        }
    </style>
    <div class="site-index">

        <h1><?= Html::encode($this->title) ?></h1>

        <p>
            <?= Html::a('Добавить домен', ['create'], ['class' => 'btn btn-success']) ?>
        </p>

        <?php // echo $this->render('_search', ['model' => $searchModel]); ?>

        <?= GridView::widget([
            'dataProvider' => $dataProvider,
            'filterModel' => $searchModel,
            'columns' => [
                ['class' => 'yii\grid\SerialColumn',
                    'contentOptions' => ['style' => 'width:30px'],
                ],
                ['attribute' => 'id', 'filter' => false, 'contentOptions' => ['style' => 'width:30px'],],
                [
                    'attribute' => 'id',
                    'filter' => false,
                    'label' => '',
                    'contentOptions' => ['style' => 'width:30px'],
                    'content' => function ($data) {
                        /* @var common\modules\webchecker\models\Site $data */
                        $js = "";
                        $bgStatus = "ok";
                        $showCursor = "";
                        if ($data->isBad) {
                            $bgStatus = "err";
                            $js = 'onclick="javascript:fixBad(' . $data->id . '); return false;"';
                            $showCursor = "show-cursor";
                        }

                        $ret = <<< _RET
<span class="status-domain-dot bg-$bgStatus $showCursor"  $js ></span>

_RET;
                        return $ret;
                    }
                ],
                [
                    'attribute' => 'name',
                    'filter' => false,
                    'label' => 'Сайт',
                    'content' => function ($data) {
                        /* @var common\modules\webchecker\models\Site $data */

                        $name = $data->name;
                        $id = $data->id;
                        $siteUrl = Url::to(["/webchecker/checklog/index", "CheckLogSearch[id]" => $id]);
                        $childsLogsUrl = Url::to(["/webchecker/checklog/index", "CheckLogSearch[parent_id]" => $id]);
                        $childsSitesUrl = Url::to(["/webchecker/site/index", "SiteSearch[parent_id]" => $id]);

                        $childs = $data->childs;
                        $countSubs = count($childs);

                        $expandLink = "[-]";
                        $subDomains = "";

                        if ($countSubs) {
                            $expandLink = <<< _LINK
<a href='#' onclick='javascript:expandSub($id); return false;'>[+]</a>
_LINK;
                        }

                        if ($countSubs) {
                            $lines = "";
                            foreach ($childs as $child) {
                                if ($child->last_bad_response_code) {
                                    $code = $child->last_bad_response_code;
                                    $stamp = $child->last_bad_response_code_stamp;
                                } else {
                                    $code = $child->cur_response_code;
                                    $stamp = $child->cur_response_code_stamp;
                                }
                                $time = "-";
                                if ($stamp) {
                                    $time = date("Y-m-d H:i:s", $stamp);
                                }

                                $cssStyle = "ok";
                                if ($code != 200) $cssStyle = "err";
                                $subName = $child->name;
                                $subId = $child->id;
                                $subeUrl = Url::to(["/webchecker/checklog/index", "CheckLogSearch[id]" => $subId]);
                                $line = <<< _LINE
    <li><span class="circle circle-$cssStyle"></span><span class="li-line bg-$cssStyle"></span><div class="wrap-item"><span class="time">$time</span> | <span class="color-$cssStyle">$code</span> | <span><a href="$subeUrl">$subName</a></span></div></li>
_LINE;
                                $lines .= $line;
                            }

                            $subDomains = <<< _SUB
<div id="expand-sub-$id" class="expand-sub" style="display: none">
    <ul class="sub-list">
    $lines
    </ul>
    <a href="$childsLogsUrl">все логи</a> | <a href="$childsSitesUrl">все сабдомены</a>
</div>
_SUB;

                        }

                        $postfix = "";
                        !$countSubs ?: $postfix = "($countSubs)";
                        $htmlSite = <<< _LINE
$expandLink
<a href="$siteUrl">$name </a> $postfix
$subDomains
_LINE;
                        return $htmlSite;
                    }
                ],


                [
                    'attribute' => 'cur_response_code_stamp',
                    'filter' => false,
                    'format' => 'raw',
                    'header' => "...",
                    'contentOptions' => ['class' => 'small-font', 'style' => 'width:75px'],
                    'content' => function ($data) {
                        if ($stamp = $data->cur_response_code_stamp) {
                            return date("Y-m-d", $stamp) . "<br>" . date("H:i:s", $stamp);
                        }
                        return "-";
                    }
                ],
                [
                    'attribute' => 'cur_response_code',
                    'filter' => false,

                    'contentOptions' => ['style' => 'width:30px'],
                    'content' => function ($data) {
                        $code = $data->cur_response_code;
                        $cssStatus = "ok";
                        if (is_null($code)) {
                            $code = "-";
                            $cssStatus = "nocolor";
                        } elseif ($code != 200) {
                            $cssStatus = "err";
                        }

                        return "<span class='color-$cssStatus thick'>$code</span>";
                    },
                    //'format' => 'raw',
                    'label' => "code",

                ],


                //'parent_id',
                //'protocol',
                //'port',
                //'last_bad_response_code',
                //'last_bad_response_code_stamp',
                //'is_bad_child',
                ['attribute' => 'interval', 'filter' => false,
                    'format' => 'raw',
                    'contentOptions' => ['style' => 'width:30px'],
                    'header' => <<< _CLOCK
<svg class="svg-icon" viewBox="0 0 20 20" style="width:20px">
							<path d="M10.25,2.375c-4.212,0-7.625,3.413-7.625,7.625s3.413,7.625,7.625,7.625s7.625-3.413,7.625-7.625S14.462,2.375,10.25,2.375M10.651,16.811v-0.403c0-0.221-0.181-0.401-0.401-0.401s-0.401,0.181-0.401,0.401v0.403c-3.443-0.201-6.208-2.966-6.409-6.409h0.404c0.22,0,0.401-0.181,0.401-0.401S4.063,9.599,3.843,9.599H3.439C3.64,6.155,6.405,3.391,9.849,3.19v0.403c0,0.22,0.181,0.401,0.401,0.401s0.401-0.181,0.401-0.401V3.19c3.443,0.201,6.208,2.965,6.409,6.409h-0.404c-0.22,0-0.4,0.181-0.4,0.401s0.181,0.401,0.4,0.401h0.404C16.859,13.845,14.095,16.609,10.651,16.811 M12.662,12.412c-0.156,0.156-0.409,0.159-0.568,0l-2.127-2.129C9.986,10.302,9.849,10.192,9.849,10V5.184c0-0.221,0.181-0.401,0.401-0.401s0.401,0.181,0.401,0.401v4.651l2.011,2.008C12.818,12.001,12.818,12.256,12.662,12.412"></path>
						</svg>
_CLOCK
                ],

                [
                    'attribute' => 'name',
                    'format' => 'raw',
                    'header' => "Добавить",
                    'contentOptions' => ['style' => 'width:110px'],
                    'content' => function ($data) {
                        /* @var common\modules\webchecker\models\Site $data */
                        if ($data->parent_id) {
                            return "-";
                        }
                        $id = $data->id;
                        $url = Url::to(["/webchecker/site/create", "Site[parent_id]" => $id]);
                        $line = <<< _LINE
<a href="$url" class="btn btn-success btn-sm">+ сабдомен</a>
_LINE;

                        return $line;
                    }
                ],
                //'have_children',
                [
                    'class' => ActionColumn::className(),
                    'contentOptions' => ['style' => 'width: 80px'],
                    'urlCreator' => function ($action, Site $model, $key, $index, $column) {
                        return Url::toRoute([$action, 'id' => $model->id]);
                    }
                ],
            ],
        ]); ?>


    </div>
<?php
$urlClearIsBad = Url::to(["/webchecker/site/clearisbad"]);
$urlCurrent = Url::current();
$js = <<< _JS
    function expandSub(parentId) {
        let selector = "#expand-sub-" + parentId;
        if ($(selector).is(":visible")) {
          $( selector ).slideUp( "slow", function() {
          // Animation complete.
          });            
        } else{
            $(selector ).slideDown( "slow", function() {
            // Animation complete.
            });
        }
        //alert(parentId);
        //document.getElementById("expand-sub-" + parentId).style.display = "block";
    }
    
    function fixBad(siteId) {
        if(confirm("Сбросить в зеленый цвет?")) {  
        let data = {'id' : siteId};  
        let formData = new FormData();
        for (key in data) {
            formData.append(key, data[key]);
        }
            $.ajax({
                // Your server script to process the upload
                url: '$urlClearIsBad',
                type: 'POST',
                // Form data
                data: formData,
                // Tell jQuery not to process data or worry about content-type
                // You *must* include these options!
                cache: false,
                contentType: false,
                processData: false,
                success: function (response) {
                    if (response.status === 'ok') {
                        console.log("ok");
                        window.location.href='$urlCurrent';
                    }
                    if (response.status === 'err') {
                        alert(response.message);
                    }
                },
            });
        }
    }        
_JS;
$this->registerJs($js, \yii\web\View::POS_END);

