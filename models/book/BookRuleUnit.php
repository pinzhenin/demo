<?php

namespace app\models;

/**
 * This is the model class for table "book_rule_unit".
 *
 * @property integer $rule_id
 * @property integer $unit_id
 *
 * @property BookRule $rule
 * @property BookUnit $unit
 */
class BookRuleUnit extends \yii\db\ActiveRecord {

	/**
	 * @inheritdoc
	 */
	public static function tableName() {
		return 'book_rule_unit';
	}

	/**
	 * Краткое описание модели
	 * @return string
	 */
	public static function tableComment() {
		return 'Связь «правила-юниты»';
	}

	/**
	 * @inheritdoc
	 */
	public function rules() {
		return [
			[ [ 'rule_id', 'unit_id' ], 'required' ],
			[ [ 'rule_id', 'unit_id' ], 'integer' ],
			[ [ 'rule_id', 'unit_id' ], 'filter', 'filter' => 'intval', 'skipOnEmpty' => TRUE ],
			[ [ 'rule_id', 'unit_id' ], 'unique', 'targetAttribute' => [ 'rule_id', 'unit_id' ], 'message' => 'The combination of «id правила» and «id юнита» has already been taken.' ],
//			[ [ 'rule_id' ], 'exist', 'skipOnError' => TRUE, 'targetClass' => BookRule::className(), 'targetAttribute' => [ 'rule_id' => 'id' ], 'filter' => [ 'type' => 'правило' ] ],
//			[ [ 'unit_id' ], 'exist', 'skipOnError' => TRUE, 'targetClass' => BookUnit::className(), 'targetAttribute' => [ 'unit_id' => 'id' ], 'filter' => [ 'type' => 'правило' ] ],
			[ 'rule_id', 'validateRelation' ]
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels() {
		return [
			'rule_id' => 'id правила',
			'unit_id' => 'id юнита',
		];
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getBookRule() {
		return $this->hasOne( BookRule::className(), [ 'id' => 'rule_id' ] );
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getBookUnit() {
		return $this->hasOne( BookUnit::className(), [ 'id' => 'unit_id' ] );
	}

	// Shortcut to getBookRule()
	public function getRule() {
		return $this->bookRule;
	}

	// Shortcut to getBookUnit()
	public function getUnit() {
		return $this->bookUnit;
	}

	/**
	 * @param string $attribute the attribute currently being validated
	 * @param mixed $params the value of the "params" given in the rule
	 * @param \yii\validators\InlineValidator related InlineValidator instance.
	 */
	public function validateRelation( $attribute, $params, $validator ) {
		$rule = $this->rule;
		$unit = $this->unit;
		if( is_null( $rule ) ) {
			$validator->addError( $this, 'rule_id', "Некорректное значение «{attribute}»: {value}. Объект не найден." );
			return;
		}
		if( is_null( $unit ) ) {
			$validator->addError( $this, 'unit_id', "Некорректное значение «{attribute}»: {value}. Объект не найден." );
			return;
		}
		if( $rule->type !== 'правило' ) {
			$validator->addError( $this, 'rule_id', "Некорректное значение «rule->type»: {$rule->type}. Допустимое значение: правило." );
		}
		if( $unit->type !== 'правило' ) {
			$validator->addError( $this, 'unit_id', "Некорректное значение «unit->type»: {$unit->type}. Допустимое значение: правило." );
		}
		if( $rule->theme !== $unit->theme ) {
			$validator->addError( $this, 'rule_id', "Ошибка связывания: theme(rule)={$rule->theme} <> theme(unit)={$unit->theme}. Темы не совпадают." );
			$validator->addError( $this, 'unit_id', "Ошибка связывания: theme(unit)={$unit->theme} <> theme(rule)={$rule->theme}. Темы не совпадают." );
		}
	}

}
