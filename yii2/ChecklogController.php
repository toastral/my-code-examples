<?php

namespace common\modules\webchecker\controllers;

use common\modules\webchecker\models\CheckLog;
use common\modules\webchecker\models\CheckLogSearch;
use common\modules\webchecker\models\SiteManager;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;

/**
 * ChecklogController implements the CRUD actions for CheckLog model.
 */
class ChecklogController extends Controller
{
    /**
     * @inheritDoc
     */
    public function behaviors()
    {
        return array_merge(
            parent::behaviors(),
            [
                'verbs' => [
                    'class' => VerbFilter::className(),
                    'actions' => [
                        'delete' => ['POST'],
                    ],
                ],
            ]
        );
    }

    /**
     * Lists all CheckLog models.
     *
     * @return string
     */
    public function actionIndex()
    {
        $searchModel = new CheckLogSearch();
        $params = $this->request->queryParams;
        $parentSite = null;
        if (isset($params["CheckLogSearch"]) && isset($params["CheckLogSearch"]["parent_id"])) {
            $parentSite = SiteManager::findOne(["id" => $params["CheckLogSearch"]["parent_id"]]);
        }
        $dataProvider = $searchModel->search($params);

        return $this->render('index', [
            'parentSite' => $parentSite,
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single CheckLog model.
     * @param int $id ID
     * @return string
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Deletes an existing CheckLog model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param int $id ID
     * @return \yii\web\Response
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    /**
     * Finds the CheckLog model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param int $id ID
     * @return CheckLog the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = CheckLog::findOne(['id' => $id])) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
