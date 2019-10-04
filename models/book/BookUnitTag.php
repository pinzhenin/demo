<?php

namespace app\models;

/**
 * This is the model class for table "book_unit_tag".
 *
 * @property integer $unit_id
 * @property integer $tag_id
 *
 * @property BookUnit $unit
 * @property BookTag $tag
 */
class BookUnitTag extends \yii\db\ActiveRecord {

	/**
	 * @inheritdoc
	 */
	public static function tableName() {
		return 'book_unit_tag';
	}

	/**
	 * Краткое описание модели
	 * @return string
	 */
	public static function tableComment() {
		return 'Связь «Юниты-Теги»';
	}

	/**
	 * @inheritdoc
	 */
	public function rules() {
		return [
			[ [ 'unit_id', 'tag_id' ], 'required' ],
			[ [ 'unit_id', 'tag_id' ], 'integer' ],
			[ [ 'unit_id', 'tag_id' ], 'filter', 'filter' => 'intval', 'skipOnEmpty' => TRUE ],
			[ [ 'unit_id', 'tag_id' ], 'unique', 'targetAttribute' => [ 'unit_id', 'tag_id' ], 'message' => 'The combination of id юнита and id тега has already been taken.' ],
			[ [ 'unit_id' ], 'exist', 'skipOnError' => TRUE, 'targetClass' => BookUnit::className(), 'targetAttribute' => [ 'unit_id' => 'id' ] ],
			[ [ 'tag_id' ], 'exist', 'skipOnError' => TRUE, 'targetClass' => BookTag::className(), 'targetAttribute' => [ 'tag_id' => 'id' ] ],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels() {
		return [
			'unit_id' => 'id юнита',
			'tag_id' => 'id тега',
		];
	}

	public function extraFields() {
		$extraFields = parent::extraFields();
		array_push( $extraFields, 'unit', 'tag' );
		return $extraFields;
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getBookUnit() {
		return $this->hasOne( BookUnit::className(), [ 'id' => 'unit_id' ] );
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getBookTag() {
		return $this->hasOne( BookTag::className(), [ 'id' => 'tag_id' ] );
	}

	// Shortcut to getBookUnit()
	public function getUnit() {
		return $this->bookUnit;
	}

	// Shortcut to getBookTag()
	public function getTag() {
		return $this->bookTag;
	}

}
