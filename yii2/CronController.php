<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace app\commands;

use app\models\Bill;
use app\models\Paylog;
use app\models\Sys;
use app\models\Transaction;
use app\models\User2;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

class CronController extends Controller
{

    public function actionBill() // ежеминутно
    {
        // Выбрать все записи из bill со стаутсом WAITING и датой не позднее 2 суток от created_at
        $b = Bill::find();
        $b->where(['>', 'created_at', date("Y-m-d H:i:s", strtotime("-2 days"))]);
        $b->andWhere(["status" => "WAITING"]);
        $bills = $b->asArray()->all();

        $billPayments = new \Qiwi\Api\BillPayments(Yii::$app->params['qiwiSecretKey']);
        echo "Найдено: " . count($bills) . "\n";
        foreach ($bills as $bill) {
            echo "Обработка bill.id: " . $bill["id"] . "\n";
            $billId = $bill["bill_id"];

            $currBill = Bill::findOne(["id" => $bill["id"]]);
            if ($currBill->status != "WAITING") {
                continue; // возможно паралельный запуск крона (коллизия) уже обработал эту запись
            }
            /** @var \Qiwi\Api\BillPayments $billPayments */
            $response = $billPayments->getBillInfo($billId);
            $status = $response["status"]["value"] ?? "";

            echo "Статус: " . $status . "\n";

            if ($status === "WAITING") {
                continue;
            }

            $currBill->status = $status;
            $currBill->save();

            if ($status === "PAID") {

                $user = User2::findOne(["id" => $currBill->user_id]);
                // добавить транзакцию
                $trans = \Yii::createObject([
                    'class' => Transaction::class,
                    'username' => $user->username,
                    'user_id' => $user->id,
                    'type' => "in",
                    'created_at' => $currBill->created_at,
                    'amount' => $currBill->amount,
                ]);
                $trans->save();
                echo "Транзакции обновлены" . "\n";

                // сбросить заморозку и пополнить
                $user->deposit += $currBill->amount;
                $user->deposit_unlock = date("Y-m-d", strtotime("+60 Days"));
                $user->save();
                echo "Заморозка сброшена" . "\n";
            }
        }

        return ExitCode::OK;
    }

    public function actionClick()
    { // ежеминутно
        // Выбрать id 1000 записей у которых is_click = 1
        // Поочередно, проверяя is_click (что-бы в случае коллизий с другим скриптом не начислить 2 раза)
        //    начислять 2% и сбрасывать is_click по каждлй выбранной записи

        $users = User2::find()->where(['is_click' => 1])->limit(1000)->asArray()->all();
        echo "Найдено: " . count($users) . "\n";
        $todayCount = 0;
        foreach ($users as $user) {
            $id = $user["id"];
            echo "Обработка user.id: " . $id . "\n";
            $curuser = User2::findOne(["id" => $user["id"]]);
            if (!$curuser->is_click) {
                continue;
            }
            $curuser->profit = $curuser->profit + $curuser->deposit * 0.02;
            $curuser->is_click = 0;
            $curuser->save();
            $todayCount++;
        }

        $sys = Sys::findOne(["id" => 1]);
        $sys->clicks_today += $todayCount;
        $sys->save();

    }

    public function actionInittime()
    {  // в полночь
        // Сбросить все is_click_today и is_click(хотя их быть не должно)
        // Проинитить sys.clicktime = rand(60, 23*60)

        $clicktime = rand(60, 23 * 60);

        list($h, $m) = Sys::getFormatedClickTime($clicktime);
        echo "clicktime: $h:$m" . "\n";

        User2::updateAll(['is_click_today' => 0], ['is_click_today' => 1]);
        echo "is_click_today = 0\n";
        User2::updateAll(['is_click' => 0], ['is_click' => 1]);
        echo "is_click = 0\n";

        $sys = Sys::findOne(["id" => 1]);
        $sys->clicktime = $clicktime;
        $sys->clicks_all += $sys->clicks_today;
        echo "sys->clicks_all = " . $sys->clicks_all . "\n";
        $sys->clicks_today = 0;
        $sys->save();
        echo "ok\n";

    }
}
