<?php

namespace app\models;

/**
 * This is the model class for table "book_tag".
 *
 * @property integer $id
 * @property string $name
 * @property string $comment
 * @property string $question
 *
 * @property BookUnitTag[] $bookUnitTags
 * @property BookUnit[] $units
 */
class BookTag extends \yii\db\ActiveRecord {

	/**
	 * @inheritdoc
	 */
	public static function tableName() {
		return 'book_tag';
	}

	/**
	 * Краткое описание модели
	 * @return string
	 */
	public static function tableComment() {
		return 'Теги для разметки заданий';
	}

	/**
	 * @inheritdoc
	 */
	public function rules() {
		return [
			[ [ 'name', 'comment', 'question' ], 'required' ],
			[ [ 'name' ], 'string', 'max' => 50 ],
			[ [ 'comment', 'question' ], 'string', 'max' => 255 ],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels() {
		return [
			'id' => 'id тега',
			'name' => 'тег',
			'comment' => 'описание тега',
			'question' => 'вопрос для РНО',
		];
	}

	public function extraFields() {
		$extraFields = parent::extraFields();
		array_push( $extraFields, 'units' );
		return $extraFields;
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getBookUnitTags() {
		return $this->hasMany( BookUnitTag::className(), [ 'tag_id' => 'id' ] );
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getBookUnits() {
		return $this->hasMany( BookUnit::className(), [ 'id' => 'unit_id' ] )->viaTable( 'book_unit_tag', [ 'tag_id' => 'id' ] );
	}

	// Shortcut to getBookUnits()
	public function getUnits() {
		return $this->bookUnits;
	}

}
