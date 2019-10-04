<?php
/**
 * Модель "Тест / Сложность заданий"
 */

namespace app\models\demo;

use Yii;

/**
 * This is the model class for table "demo_complexity".
 *
 * @property int $id ID
 * @property int $book_task_id TASK_ID
 * @property string $theme тема
 * @property int $grade класс
 * @property int $complexity сложность задания
 * @property int $total выполнено заданий
 * @property int $right выполнено правильно
 * @property int $wrong выполнено неправильно
 * @property string $_created
 * @property string $_updated
 *
 * @property BookTask $bookTask
 */
class DemoComplexity extends \yii\db\ActiveRecord {

	/**
	 * {@inheritdoc}
	 */
	public static function tableName() {
		return 'demo_complexity';
	}

	/**
	 * {@inheritdoc}
	 */
	public function rules() {
		return [
//			[ 'id', 'integer', 'min' => 0 ],
//			[ 'id', 'filter', 'filter' => 'intval', 'skipOnEmpty' => TRUE ],
			[ 'book_task_id', 'required' ],
			[ 'book_task_id', 'integer' ],
			[ 'book_task_id', 'exist', 'skipOnError' => true, 'targetClass' => BookTask::className(), 'targetAttribute' => [ 'book_task_id' => 'id' ] ],
			[ 'theme', 'required' ],
			[ 'theme', 'in', 'range' => [ 'орфография', 'пунктуация' ] ],
			[ 'grade', 'required' ],
			[ 'grade', 'filter', 'filter' => 'intval' ],
			[ 'grade', 'in', 'range' => range( 0, 12 ) ],
			[ [ 'book_task_id', 'grade' ], 'unique', 'targetAttribute' => [ 'book_task_id', 'grade' ] ],
			[ [ /* 'complexity', 'total', */ 'right', 'wrong' ], 'default', 'value' => NULL ],
			[ [ /* 'complexity', 'total', */ 'right', 'wrong' ], 'integer', 'min' => 0 ],
			[ [ '_created', '_updated' ], 'safe' ],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels() {
		return [
			'id' => 'ID',
			'book_task_id' => 'ID задания',
			'theme' => 'тема',
			'grade' => 'класс',
			'complexity' => 'сложность задания',
			'total' => 'выполнено заданий',
			'right' => 'выполнено правильно',
			'wrong' => 'выполнено неправильно',
			'_created' => 'created',
			'_updated' => 'updated',
		];
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getBookTask() {
		return $this->hasOne( BookTask::className(), [ 'id' => 'book_task_id' ] );
	}

	/**
	 * Обновление таблицы сложности слов из результатов демо-тренажа
	 * @param mixed $id ID демо-теста или модель Demo
	 * @return integer
	 */
	public static function handle( $id ) {
		$demo = (is_object( $id ) && ($id::className() === Demo::className())) ? $id : Demo::findOne( $id );
		return Yii::$app->db->createCommand(
				<<<SQL
INSERT
	`demo_complexity` (`book_task_id`,`theme`,`grade`,`right`,`wrong`)
	SELECT
		`book_task_id`,
		:demoTheme,
		:demoGrade,
		IF(`answer`='right',1,0),
		IF(`answer`='wrong',1,0)
	FROM `demo_task`
	WHERE `demo_id` = :demoID AND `answer` IN ('right','wrong')
ON DUPLICATE KEY UPDATE
	`right` = `demo_complexity`.`right` + VALUES(`right`),
	`wrong` = `demo_complexity`.`wrong` + VALUES(`wrong`)
SQL
				, [ ':demoID' => $demo->id, ':demoTheme' => $demo->theme, ':demoGrade' => $demo->grade ]
			)->execute();
	}

}
