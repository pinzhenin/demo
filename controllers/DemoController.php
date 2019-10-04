<?php
/**
 * Тест по русскому языку
 * https://gramotei.online/demo
 * 
 * Модели: ~/models/demo/Demo*.php
 */

namespace app\controllers;

use app\models\Demo;
use Yii;

class DemoController extends \yii\web\Controller {

	/**
	 * Форма настройки тренажера.
	 * @return string|\yii\web\Response
	 */
	public function actionIndex(){
		$model = new Demo();
		$model->theme = Yii::$app->request->get('theme', 'орфография');
		$model->role = Yii::$app->request->get('role', 'школьник');
		$model->grade = Yii::$app->request->get('grade', 9);

		$model->load(Yii::$app->request->post());
		if($model->load(Yii::$app->request->post()) and $model->save()){
			$session = Yii::$app->session;
			$session['demoId'] = $model->id;

			return $this->redirect(['demo/run']);
		}

		return $this->render('index', [
			'model' => $model
		]);
	}

	/**
	 * Старт работы тренажера.
	 * @return string|\yii\web\Response
	 */
	public function actionRun(){
		$session = Yii::$app->session;
		if(!isset($session['demoId'])){
			return $this->redirect(['demo/index']);
		}

		$model = Demo::findOne($session['demoId']);

		return $this->render('run', [
			'demo' => $model
		]);
	}

	/**
	 * Сохранение результатов тренажера.
	 * @param $result массив ответов
	 * @return string код доступа к отчету
	 */
	public function actionSave(){
		$session = Yii::$app->session;
		$model = Demo::findOne($session['demoId']);
		$model->saveAnswers(Yii::$app->request->post('result'));

		\Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
		return $model->code;
	}

	/**
	 * Сохранение результатов тренажера.
	 * @param $code код доступа к отчету
	 * @return string
	 */
	public function actionReport($code){
		$model = Demo::findByCode($code);

		// если модель не найдена
		if(!isset($model)){
			Yii::$app->session->setFlash('modelNotFound', true);
			return $this->redirect(['demo/index']);
		}

		// если результатов нет
		if($model->total == 0){
			Yii::$app->session->setFlash('progressNotFound', true);
			return $this->redirect(['demo/index']);
		}

		// подготовим данные для отчёта
		$statUser = [
			'literacy' => $model->given ? round(100 * $model->right / $model->given) : 0,
			'velocity' => $model->total ? round($model->time / $model->total) : 0
		];

		// определим числовую/текстовую отметку
		foreach(Yii::$app->params['demo']['mark'] as $mark){
			list( $markPct, $markNum, $markTxt, $markSlogan ) = $mark;
			if($statUser['literacy'] >= $markPct){
				$statUser['markNum'] = $markNum;
				$statUser['markTxt'] = $markTxt;
				$statUser['markSlogan'] = $markSlogan;
				break;
			}
		}

		// рассчитаем средний результат демо-теста
		$statAll = Demo::find()
				->select([
					'literacy' => 'ROUND(100 * SUM(`right`) / SUM(`total`))',
					'velocity' => 'ROUND(SUM(LEAST(`time`, `total` * 20)) / SUM(`total`))', // лимит: 20 сек/слово
					'amount' => 'COUNT(`total`)',
					'better' => 'COUNT(IF(ROUND(100 * `right` / `total`) >= :userLiteracy, TRUE, NULL))',
					'faster' => 'COUNT(IF(ROUND(`time` / `total`) <= :userVelocity, TRUE, NULL))'
				])
				->where([
					'theme' => $model->theme,
					'grade' => $model->grade
				])
				->andWhere(['>', 'total', 0])
				->params([
					':userLiteracy' => $statUser['literacy'],
					':userVelocity' => $statUser['velocity']
				])
				->asArray()
				->one();

		// рассчитаем усвоение правил пользователем + среднее значение
		$statRule = Yii::$app->db->createCommand('
			SELECT
				`book`.`id` AS bookID,
				`book`.`name` AS bookName,
				`my`.`right` AS uRight,
				`my`.`total` AS uTotal,
				`my`.`given` AS uGiven,
				IFNULL(ROUND(100 * `my`.`right` / `my`.`total`), 0) AS uLiteracy,
				`all`.`right` AS aRight,
				`all`.`total` AS aTotal,
				`all`.`given` AS aGiven,
				IFNULL(ROUND(100 * `all`.`right` / `all`.`total`), 0) AS aLiteracy
			FROM
				`book_rule` AS `subject`
				INNER JOIN `book_rule` AS `book` ON (
					`subject`.`id` = `book`.`idParent` AND
					`book`.`status` = "on" AND
					SUBSTRING_INDEX(`book`.`grade`, ",", 1) <= CAST(:grade AS INT)
				)
				LEFT JOIN `demo_rule` AS my ON (
					my.`demo_id` = :demoID AND
					my.`book_rule_id` = `book`.`id`
				)
				LEFT JOIN (
					SELECT
						`book_rule_id`,
						SUM(`demo_rule`.`given`) AS `given`,
						SUM(`demo_rule`.`total`) AS `total`,
						SUM(`demo_rule`.`right`) AS `right`
					FROM
						`demo`
						INNER JOIN `demo_rule` ON `demo`.`id` = `demo_rule`.`demo_id`
					WHERE
						`demo`.`theme` = :theme AND
						`demo`.`grade` = :grade AND
						`demo`.`total` > 0
					GROUP BY
						`demo_rule`.`book_rule_id`
				) AS `all` ON `all`.`book_rule_id` = `book`.`id`
			WHERE
				`subject`.`idParent` = :umkID AND
				`subject`.`name` = :theme
			ORDER BY
				`book`.`sort`',
		[
				'demoID' => $model->id,
				'theme' => $model->theme,
				'grade' => $model->grade,
				'umkID' => Yii::$app->params['demo']['umk_id'][$model->grade <= 4 ? '1-4' : '5-11']
		])->queryAll();

		return $this->render('report', [
			'demo' => $model,
			'stat' => [
				'user' => $statUser,
				'all' => $statAll,
				'rule' => $statRule
			]
		]);
	}
}
