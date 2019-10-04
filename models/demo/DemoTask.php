<?php
/**
 * Модель "Тест / Правила / Задания"
 */

namespace app\models\demo;

use Yii;
use app\models\{BookRule, BookTask};

/**
 * This is the model class for table "demo_task".
 *
 * @property int $id ID
 * @property int $demo_id DEMO_ID
 * @property int $book_rule_id ID учебника
 * @property int $book_task_id ID задания
 * @property string $answer ответ
 *
 * @property Demo $demo
 * @property BookRule $bookRule
 * @property BookTask $bookTask
 */
class DemoTask extends \yii\db\ActiveRecord {

	/**
	 * {@inheritdoc}
	 */
	public static function tableName() {
		return 'demo_task';
	}

	/**
	 * {@inheritdoc}
	 */
	public function rules() {
		return [
//			[ 'id', 'integer', 'min' => 0 ],
//			[ 'id', 'filter', 'filter' => 'intval', 'skipOnEmpty' => TRUE ],
			[ 'demo_id', 'required' ],
			[ 'demo_id', 'integer' ],
			[ 'demo_id', 'exist', 'skipOnError' => TRUE, 'targetClass' => Demo::className(), 'targetAttribute' => [ 'demo_id' => 'id' ] ],
			[ 'book_rule_id', 'required' ],
			[ 'book_rule_id', 'integer' ],
			[ 'book_rule_id', 'exist', 'skipOnError' => TRUE, 'targetClass' => BookRule::className(), 'targetAttribute' => [ 'book_rule_id' => 'id' ] ],
			[ 'book_task_id', 'required' ],
			[ 'book_task_id', 'integer' ],
			[ 'book_task_id', 'exist', 'skipOnError' => TRUE, 'targetClass' => BookTask::className(), 'targetAttribute' => [ 'book_task_id' => 'id' ] ],
			[ 'answer', 'in', 'range' => [ 'right', 'wrong' ] ],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels() {
		return [
			'id' => 'ID',
			'demo_id' => 'DEMO_ID',
			'book_rule_id' => 'ID учебника',
			'book_task_id' => 'ID задания',
			'answer' => 'ответ',
		];
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getDemo() {
		return $this->hasOne( Demo::className(), [ 'id' => 'demo_id' ] );
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getBookRule() {
		return $this->hasOne( BookRule::className(), [ 'id' => 'book_rule_id' ] );
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getBookTask() {
		return $this->hasOne( BookTask::className(), [ 'id' => 'book_task_id' ] );
	}

	/**
	 * Генерация кода демо-теста
	 * @param mixed $id ID демо-теста или модель Demo
	 * @return array DemoTask
	 */
	public static function create( $id ) {
		$demo = (is_object( $id ) && ($id::className() === Demo::className())) ? $id : Demo::findOne( $id );

		$db = Yii::$app->db;
		$params = Yii::$app->params['demo'];

		// сформируем задания и сохраним их в demo_task...
		$db->createCommand( 'SET @book_id := 0, @task_number := 0' )->execute();
		$db->createCommand(
			<<<SQL
INSERT
	`demo_task` (`demo_id`, `book_rule_id`, `book_task_id`)
SELECT
	:demoId, `book_id`, `task_id`
FROM (
	SELECT
		/* br1.`id` AS theme_id, br1.`name` AS theme_name, */
		br2.`id` AS book_id, /* br2.`name` AS book_name, */
		/* br3.`id` AS rule_id, br3.`name` AS rule_name, */
		/* bu.`id` AS unit_id, bu.`name` AS unit_name, */
		bt.`id` AS task_id, /* bt.`problem`, */
		ROUND(GREATEST(IFNULL(dc.`complexity`,10),10)*RAND(),2) AS complexity
	/* темы */
	FROM `book_rule` br1
	/* учебники */
	INNER JOIN `book_rule` br2 ON br1.`id` = br2.`idParent` AND br2.`status` = 'on'
		AND SUBSTRING_INDEX(br2.`grade`,',',1) <= :grade
	/* правила */
	INNER JOIN `book_rule` br3 ON br2.`id` = br3.`idParent` AND br3.`status` = 'on'
		AND SUBSTRING_INDEX(br3.`grade`,',',1) <= :grade
	INNER JOIN `book_rule_unit` bru ON br3.`id` = bru.`rule_id`
	/* юниты */
	INNER JOIN `book_unit` bu ON bru.`unit_id` = bu.`id` AND bu.`status` = 'on'
	/* задачи */
	INNER JOIN `book_task` bt ON bu.`id` = bt.`unit_id` AND bt.`status` IN ('on','new') AND bt.`grade` <= :grade
	/* сложность задач */
	LEFT JOIN `demo_complexity` dc ON bt.`id` = dc.`book_task_id` AND dc.`grade` = :grade AND dc.`total` >= 50
	WHERE br1.`idParent` = :umkId AND br1.`name` = :theme
	ORDER BY br2.`sort`, complexity DESC
) AS t
WHERE IF(@book_id = book_id, @task_number := @task_number+1, @task_number := 1 && @book_id := book_id) <= :tasksPerSection
SQL
			, [
				'demoId' => $demo->id,
				'grade' => $demo->grade,
				'theme' => $demo->theme,
				'umkId' => $params['umk_id'][$demo->grade <= 4 ? '1-4' : '5-11'], /* здесь нужна зависимость от темы */
				'tasksPerSection' => $params['demo']['tasksPerSection'] ?? 5
			]
		)->execute();
		// ...а вот теперь вытащим и вернём
		return DemoTask::find()
				->select( [ 'id' => 'demo_task.id', 'problem' ] )
				->innerJoinWith( 'bookTask', FALSE )
				->where( [ 'demo_id' => $demo->id ] )
				->asArray()
				->all();
	}

	/**
	 * Обработка и сохранение результатов демо-теста
	 * @param mixed $id ID демо-теста или модель Demo
	 * @param array $answers ответы в формате: [ id => right|wrong, ... ]
	 * @return integer
	 */
	public static function handle( $id, $answers ) {
		$demoID = (is_object( $id ) && ($id::className() === Demo::className())) ? $id = $id->id : $id;

		$tasks = self::find()->where( [ 'demo_id' => $demoID ] )->all();
		foreach( $tasks as $task ) {
			$answer = $answers[$task->id];
			$task->answer = in_array( $answer, [ 'right', 'wrong' ] ) ? $answer : NULL;
			$task->save();
		}
		return count( $tasks );
	}

}
