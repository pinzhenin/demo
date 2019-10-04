<?php
/**
 * Модель "Тест"
 */

namespace app\models\demo;

use yii\helpers\Json;

/**
 * This is the model class for table "demo".
 *
 * @property int $id ID
 * @property string $theme тема заданий
 * @property string $role роль пользователя
 * @property int $grade сложность заданий (класс)
 * @property int $given задано заданий
 * @property int $total выполнено заданий
 * @property int $right выполнено правильно
 * @property int $wrong выполнено неправильно
 * @property int $time время выполнения
 * @property string $_created
 * @property string $_updated
 *
 * @property DemoRule[] $demoRules
 * @property DemoTask[] $demoTasks
 */
class Demo extends \yii\db\ActiveRecord {

	/**
	 * {@inheritdoc}
	 */
	public static function tableName() {
		return 'demo';
	}

	const SCENARIO_FORM = 'form'; // настройка теста

	public function scenarios() {
		$scenarios = parent::scenarios();
		$scenarios[self::SCENARIO_FORM] = [ 'theme', 'role', 'grade' ];
		return $scenarios;
	}

	static $fieldRange = [ // Допустимые значения полей типа set/enum
		'theme' => [ 'default' => 'орфография', 'пунктуация' ],
		'role' => [ 'default' => 'школьник', 'студент', 'родитель', 'учитель' ],
		'grade' => [ 1, 2, 3, 4, 5, 6, 7, 8, 'default' => 9, 10, 11 ],
	];

	/**
	 * {@inheritdoc}
	 */
	public function rules() {
		return [
			[ 'theme', 'default', 'value' => self::$fieldRange['theme']['default'] ],
			[ 'theme', 'in', 'range' => self::$fieldRange['theme'] ],
			[ 'role', 'default', 'value' => self::$fieldRange['role']['default'] ],
			[ 'role', 'in', 'range' => self::$fieldRange['role'] ],
			[ 'grade', 'default', 'value' => self::$fieldRange['grade']['default'] ],
			[ 'grade', 'filter', 'filter' => 'intval' ],
			[ 'grade', 'in', 'range' => self::$fieldRange['grade'],
				'when' => function($model) { return $model->role === 'школьник'; } ],
			[ 'grade', 'filter', 'filter' => function($value) { return 12; },
				'when' => function($model) { return $model->role !== 'школьник'; } ],
			[ [ 'given', /* 'total', */ 'right', 'wrong', 'time' ], 'default', 'value' => NULL ],
			[ [ 'given', /* 'total', */ 'right', 'wrong', 'time' ], 'integer', 'min' => 0 ],
			[ [ '_created', '_updated' ], 'safe' ],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels() {
		return [
			'id' => 'ID',
			'theme' => 'тема заданий',
			'role' => 'роль пользователя',
			'grade' => 'сложность заданий (класс)',
			'given' => 'задано заданий',
			'total' => 'выполнено заданий',
			'right' => 'выполнено правильно',
			'wrong' => 'выполнено неправильно',
			'time' => 'время выполнения',
			'_created' => 'created',
			'_updated' => 'updated',
		];
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getDemoRules() {
		return $this->hasMany( DemoRule::className(), [ 'demo_id' => 'id' ] );
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getDemoTasks() {
		return $this->hasMany( DemoTask::className(), [ 'demo_id' => 'id' ] );
	}

	/**
	 * Генерация уникального кода демо-теста
	 * @return string
	 */
	public function getCode( $useCreated = FALSE ) {
		$key = [ $this->id, $this->theme, $this->role, $this->grade ];
		if( $useCreated ) {
			$key[] = $this->_created;
		}
		return sprintf( '%010d-%s', $this->id, md5( Json::encode( $key ) ) );
	}

	/**
	 * Валидация кода демо-теста и возврат модели
	 * @param string $code код демо-теста
	 * @return Demo
	 */
	public static function validateCode( $code ) {
		// распарсим код
		if( !preg_match( '/^0*(\d+)-([0-9a-f]+)$/', $code, $matches ) ) {
			return NULL;
		}
		// выберем модель
		$demo = self::findOne( $matches[1] );
		// проверим валидность кода
		if( $demo && (($demo->code === $code) || ($demo->getCode( TRUE ) === $code)) ) {
			return $demo;
		}
		return NULL;
	}

	/**
	 * Обработка и сохранение результатов демо-теста: demo_rule => demo
	 * @param mixed $id ID демо-теста или модель Demo
	 */
	public static function handle( $id ) {
		$demo = (is_object( $id ) && ($id::className() === self::className())) ? $id : Demo::findOne( $id );
		$stat = DemoTask::find()
			->select( [
				'sumGiven' => "COUNT(`id`)",
				'sumRight' => "SUM(IF(`answer`='right',1,0))",
				'sumWrong' => "SUM(IF(`answer`='wrong',1,0))"
			] )
			->where( [ 'demo_id' => $demo->id ] )
			->asArray()
			->one();
		$demo->attributes = [
			'given' => $stat['sumGiven'],
			'right' => $stat['sumRight'],
			'wrong' => $stat['sumWrong']
		];
		if( empty( $demo->time ) ) {
			$demo->time = time() - strtotime( $demo->_created );
		}
		$demo->save();
	}

}
