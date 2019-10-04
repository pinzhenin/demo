<?php
/**
 * Модель "Тест / Правила"
 */


namespace app\models\demo;

use Yii;
use app\models\BookRule;

/**
 * This is the model class for table "demo_rule".
 *
 * @property int $id ID
 * @property int $demo_id DEMO_ID
 * @property int $book_rule_id ID учебника
 * @property int $given задано заданий
 * @property int $total выполнено заданий
 * @property int $right выполнено правильно
 * @property int $wrong выполнено неправильно
 *
 * @property Demo $demo
 * @property BookRule $bookRule
 */
class DemoRule extends \yii\db\ActiveRecord {

	/**
	 * {@inheritdoc}
	 */
	public static function tableName() {
		return 'demo_rule';
	}

	/**
	 * {@inheritdoc}
	 */
	public function rules() {
		return [
//			[ 'id', 'integer', 'min' => 0 ],
//			[ 'id', 'filter', 'filter' => 'intval', 'skipOnEmpty' => TRUE ],
			[ [ 'demo_id', 'book_rule_id' ], 'required' ],
			[ [ 'demo_id', 'book_rule_id' ], 'integer' ],
			[ 'demo_id', 'exist', 'skipOnError' => TRUE, 'targetClass' => Demo::className(), 'targetAttribute' => [ 'demo_id' => 'id' ] ],
			[ 'book_rule_id', 'exist', 'skipOnError' => TRUE, 'targetClass' => BookRule::className(), 'targetAttribute' => [ 'book_rule_id' => 'id' ] ],
			[ [ 'demo_id', 'book_rule_id' ], 'unique', 'targetAttribute' => [ 'demo_id', 'book_rule_id' ], 'message' => 'The combination of «id теста» and «id правила» has already been taken.' ],
			[ [ 'given', /* 'total', */ 'right', 'wrong' ], 'default', 'value' => NULL ],
			[ [ 'given', /* 'total', */ 'right', 'wrong' ], 'integer', 'min' => 0 ],
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
			'given' => 'задано заданий',
			'total' => 'выполнено заданий',
			'right' => 'выполнено правильно',
			'wrong' => 'выполнено неправильно',
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
	 * Обработка и сохранение результатов демо-теста
	 * @param mixed $id ID демо-теста или модель Demo
	 * @return integer количество заданий
	 */
	public static function handle( $id ) {
		$demoID = (is_object( $id ) && ($id::className() === Demo::className())) ? $id = $id->id : $id;
		return Yii::$app->db->createCommand(
				<<<SQL
INSERT
	`demo_rule` (`demo_id`, `book_rule_id`, `given`, `right`, `wrong`)
	SELECT
		`demo_id`,
		`book_rule_id`,
		COUNT(`id`),
		SUM(IF(`answer`='right',1,0)),
		SUM(IF(`answer`='wrong',1,0))
	FROM `demo_task`
	WHERE `demo_id` = :demoID /* AND `answer` IN ('right','wrong') */
	GROUP BY `book_rule_id`
ON DUPLICATE KEY UPDATE
	`given` = VALUES(`given`),
	`right` = VALUES(`right`),
	`wrong` = VALUES(`wrong`)
SQL
				, [ ':demoID' => $demoID ]
			)->execute();
	}

}
