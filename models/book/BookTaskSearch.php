<?php

namespace app\models;

use yii\data\ArrayDataProvider;
use app\models\BookTask;

class BookTaskSearch extends BookTask {

	public $solution;

	/**
	 * @inheritdoc
	 */
	public function rules() {
		return [
			[ 'id', 'integer', 'min' => 0 ],
			[ 'id', 'filter', 'filter' => 'intval', 'skipOnEmpty' => TRUE ],
			[ 'unit_id', 'integer', 'min' => 0 ],
			[ 'unit_id', 'filter', 'filter' => 'intval', 'skipOnEmpty' => TRUE ],
			[ 'problem', 'string' ],
			[ 'problem', 'validateRegexp' ],
			[ 'solution', 'string' ],
			[ 'solution', 'validateRegexp' ],
			[ 'grade', 'integer' ],
			[ 'grade', 'filter', 'filter' => 'intval', 'skipOnEmpty' => TRUE ],
//			[ 'grade', 'in', 'range' => self::$fieldRange['grade'] ],
			[ 'status', 'string' ],
//			[ 'status', 'in', 'range' => self::$fieldRange['status'] ],
		];
	}

	/**
	 * Creates data provider instance with search query applied
	 *
	 * @param integer $unit_id
	 * @param array $params
	 * @return ArrayDataProvider
	 */
	public function search( $unit_id, $params ) {
		$query = BookTask::find()->where( [ 'unit_id' => $unit_id ] );

		if( $this->load( $params ) && $this->validate() ) {
			$query
				->andFilterWhere( [ 'id' => $this->id ] )
				->andFilterWhere( [ 'regexp', 'problem', $this->problem ] )
				->andFilterWhere( [ 'grade' => $this->grade ] )
				->andFilterWhere( [ 'status' => $this->status ] );
		}
		$regexpSolution = '/' . ( $this->solution ?? '.*' ) . '/iu';

		$modelMatch = $query->all();
		$arrayMatch = [];
		while( count( $modelMatch ) ) {
			$model = array_shift( $modelMatch );
			$array = $model->toArray();
			if( preg_match( $regexpSolution, $array['solution'] ) ) {
				$arrayMatch[] = $array;
			}
		}

		$dataProvider = new ArrayDataProvider( [
			'allModels' => &$arrayMatch,
			'key' => 'id',
			'pagination' => [ 'pageSize' => 100 ],
			'sort' => [
				'attributes' => [ 'solution', 'problem', 'grade', 'status', 'id' ],
				'defaultOrder' => [ 'solution' => SORT_ASC, 'problem' => SORT_ASC, 'id' => SORT_ASC ]
			]
		] );

		return $dataProvider;
	}

	/**
	 * @param string $attribute the attribute currently being validated
	 * @param mixed $params the value of the "params" given in the rule
	 * @param \yii\validators\InlineValidator related InlineValidator instance.
	 */
	public function validateRegexp( $attribute, $params, $validator ) {
		$regexp = '/' . $this->$attribute . '/iu';
		if( preg_match( $regexp, '' ) === FALSE ) {
			$validator->addError( $this, $attribute, "Некорректное значение «{attribute}»: {value}. Ошибка синтаксиса регулярного выражения." );
		}
	}

}
