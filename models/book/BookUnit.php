<?php

namespace app\models;

use Yii;
use yii\caching\TagDependency;
use paulzi\{adjacencyList\AdjacencyListBehavior, sortable\SortableBehavior};

/**
 * This is the model class for table "book_unit".
 *
 * @property integer $id
 * @property integer $idParent
 * @property string $type
 * @property string $code
 * @property string $name
 * @property string $status
 * @property integer $sort
 *
 * @property BookUnit $idParent0
 * @property BookUnit[] $bookUnits
 * @property BookRuleUnit[] $bookRuleUnits
 * @property BookRule[] $bookRules
 * @property BookTask[] $bookTasks
 * @property BookUnitTag[] $bookUnitTags
 * @property BookTag[] $bookTags
 */
class BookUnit extends \yii\db\ActiveRecord {

	private static $cache;

	public function init() {
		parent::init();
		if( empty( self::$cache ) ) {
			self::$cache = Yii::$app->cache;
		}
	}

	public function afterDelete() {
		$this->clearCache();
		return parent::afterDelete();
	}

	public function afterSave( $insert, $changedAttributes ) {
		$this->clearCache();
		return parent::afterSave( $insert, $changedAttributes );
	}

	/**
	 * @inheritdoc
	 */
	public static function tableName() {
		return 'book_unit';
	}

	/**
	 * Краткое описание модели
	 * @return string
	 */
	public static function tableComment() {
		return 'Юниты (атомарные правила)';
	}

	public function behaviors() {
		return [
			[ // https://github.com/paulzi/yii2-adjacency-list + https://habrahabr.ru/post/266155/
				'class' => AdjacencyListBehavior::className(),
				'parentAttribute' => 'idParent',
				'sortable' => [ // https://github.com/paulzi/yii2-sortable
					'class' => SortableBehavior::className(),
					'query' => [ 'idParent' ],
					'sortAttribute' => 'sort',
					'step' => 10,
					'joinMode' => TRUE,
					'windowSize' => 1000
				],
				'checkLoop' => FALSE,
				'parentsJoinLevels' => 3,
				'childrenJoinLevels' => 3
			]
		];
	}

	static $fieldRange = [ // Допустимые значения полей типа set/enum
		'type' => [
			'предмет' => [ 'children' => [ 'тема' ], 'value' => [ 'Русский язык', 'Математика' ] ],
			'тема' => [ 'children' => [ 'раздел' ], 'value' => [ 'Орфография', 'Пунктуация' ] ],
			'раздел' => [ 'children' => [ 'раздел', 'правило' ] ],
			'правило' => [ 'children' => NULL ]
		],
		'status' => [ 'on', 'hidden', 'off' ]
	];

	/**
	 * @inheritdoc
	 */
	public function rules() {
		return [
			[ 'idParent', 'integer', 'min' => 0 ],
			[ 'idParent', 'filter', 'filter' => 'intval', 'skipOnEmpty' => TRUE ],
			[ 'idParent', 'exist', 'skipOnError' => TRUE, 'targetClass' => BookUnit::className(), 'targetAttribute' => [ 'idParent' => 'id' ] ],
			[ 'type', 'string' ],
			[ 'type', 'required' ],
			[ 'type', 'in', 'range' => array_keys( self::$fieldRange['type'] ) ],
			[ 'type', 'validateTypeChain' ],
			[ 'code', 'string', 'max' => 100 ],
			[ 'code', 'default', 'value' => NULL ],
			[ 'code', 'unique' ],
			[ 'name', 'trim' ],
			[ 'name', 'string', 'max' => 255 ],
			[ 'name', 'required' ],
			[ 'name', 'validateNameValue' ],
			[ 'status', 'string' ],
			[ 'status', 'required' ],
			[ 'status', 'in', 'range' => self::$fieldRange['status'] ],
			[ 'sort', 'integer' ],
			[ 'sort', 'filter', 'filter' => 'intval', 'skipOnEmpty' => TRUE ]
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels() {
		return [
			'id' => 'id раздела',
			'idParent' => 'id родителя',
			'type' => 'тип раздела',
			'code' => 'код раздела',
			'name' => 'название раздела',
			'status' => 'статус раздела',
			'sort' => 'вес для сортировки',
		];
	}

	/**
	 * @return array $extraFields
	 */
	public function extraFields() {
		$extraFields = parent::extraFields();
		array_push( $extraFields, 'parent', 'parents', 'parentsOrdered', 'children', 'units', 'rules', 'tasks', 'tags', 'theme' );
		return $extraFields;
	}

	// provided by paulzi\adjacencyList\AdjacencyListBehavior
	// public function getParent() {}
	// public function getParents() {}
	// public function getChildren() {}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getBookRuleUnits() {
		return $this->hasMany( BookRuleUnit::className(), [ 'unit_id' => 'id' ] );
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getBookRules() {
		return $this->hasMany( BookRule::className(), [ 'id' => 'rule_id' ] )->viaTable( 'book_rule_unit', [ 'unit_id' => 'id' ] );
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getBookTasks() {
		return $this->hasMany( BookTask::className(), [ 'unit_id' => 'id' ] );
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getIdParent0() {
		return $this->hasOne( BookUnit::className(), [ 'id' => 'idParent' ] );
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getBookUnits() {
		return $this->hasMany( BookUnit::className(), [ 'idParent' => 'id' ] );
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getBookUnitTags() {
		return $this->hasMany( BookUnitTag::className(), [ 'unit_id' => 'id' ] );
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getBookTags() {
		return $this->hasMany( BookTag::className(), [ 'id' => 'tag_id' ] )->viaTable( 'book_unit_tag', [ 'unit_id' => 'id' ] );
	}

	// Shortcut to getBookUnits()
	public function getUnits() {
		return $this->bookUnits;
	}

	// Shortcut to getBookRules()
	public function getRules() {
		return $this->bookRules;
	}

	// Shortcut to getBookTasks()
	public function getTasks() {
		return $this->bookTasks;
	}

	// Shortcut to getBookTags()
	public function getTags() {
		return $this->bookTags;
	}

	/**
	 * @return string
	 */
	public function getTheme() {
		$key = "unit[{$this->id}]->theme";
		$value = self::$cache->getOrSet( $key, function () { return $this->fetchTheme(); },
			NULL, new TagDependency( [ 'tags' => [ "unit[{$this->id}]", 'unit', 'unit->theme' ] ] ) );
		return $value;
	}

	/**
	 * @return string
	 */
	public function fetchTheme() {
		$pathParents = [];
		foreach( $this->parentsOrdered as $unit ) {
			$pathParents[$unit->type] = $unit->name;
		}
		$pathPatterns = [];
		foreach( Yii::$app->params['theme'] as $theme => $paths ) {
			$pathPatterns[$theme] = $paths['unitPath'];
		}
		foreach( $pathPatterns as $theme => $pathPattern ) {
			$pathSubject = $pathParents;
			$pathMatch = TRUE;
			while( count( $pathPattern ) ) {
				list( $typePattern, $namePattern ) = [ key( $pathPattern ), array_shift( $pathPattern ) ];
				list( $typeSubject, $nameSubject ) = [ key( $pathSubject ), array_shift( $pathSubject ) ];
				if( !( $typePattern === $typeSubject && ( $namePattern === $nameSubject || $namePattern === '*' ) ) ) {
					$pathMatch = FALSE;
					break;
				}
			}
			if( $pathMatch ) {
				return $theme;
			}
		}
		return NULL;
	}

	public function clearCache() {
		TagDependency::invalidate( self::$cache, [ "unit[{$this->id}]" ] );
	}

	public static function clearCacheTotal() {
		TagDependency::invalidate( self::$cache, [ 'unit' ] );
	}

	/**
	 * @inheritdoc
	 * @return BookUnitQuery the active query used by this AR class.
	 */
	public static function find() {
		return new BookUnitQuery( get_called_class() );
	}

	public function load( $data, $formName = NULL ) {
		$this->clearCache();
		return parent::load( $data, $formName );
	}

	// jsTree: preparing data to jsTree format
	public static function jsTreePrepare( &$data ) {
		$aAttr = [ 'class' => 'status-#status', 'title' => 'id=#id, status=#status' ];
		foreach( $data as &$array ) {
			$a_attr = [];
			foreach( $aAttr as $key => $value ) {
				$a_attr[$key] = preg_replace_callback(
					'/#(\w+)/', function ( $matches ) use( $array ) { return $array[$matches[1]]; }, $value
				);
			}
			$array = [
				'id' => $array['id'],
				'parent' => (int) $array['idParent'] ? $array['idParent'] : '#',
				'text' => $array['name'],
				'type' => $array['type'],
				'status' => $array['status'],
//				'sort' => $array['sort'],
				'children' => !empty( self::$fieldRange['type'][$array['type']]['children'] ),
				'a_attr' => $a_attr,
				'li_attr' => [ 'data-status' => $array['status'] ]
			];
		}
	}

	/**
	 * @param string $attribute the attribute currently being validated
	 * @param mixed $params the value of the "params" given in the rule
	 * @param \yii\validators\InlineValidator related InlineValidator instance.
	 */
	public function validateTypeChain( $attribute, $params, $validator ) {
		$parent = $this->getParent()->asArray()->one();
		if( is_null( $parent ) ) {
			if( $this->type !== 'предмет' ) {
				$validator->addError( $this, $attribute, "Некорректное значение «{attribute}»: {value}. Для корневого раздела допустимое значение: предмет." );
			}
			return;
		}
		$allowedTypes = self::$fieldRange['type'][$parent['type']]['children'] ?? [];
		if( !in_array( $this->type, $allowedTypes ) ) {
			$allowedTypes = implode( ', ', $allowedTypes );
			$validator->addError( $this, $attribute, "Некорректное значение «{attribute}»: {value}. Родительский раздел: {$parent['type']}, допустимые значения: {$allowedTypes}." );
		}
	}

	/**
	 * @param string $attribute the attribute currently being validated
	 * @param mixed $params the value of the "params" given in the rule
	 * @param \yii\validators\InlineValidator related InlineValidator instance.
	 */
	public function validateNameValue( $attribute, $params, $validator ) {
		$allowedNames = self::$fieldRange['type'][$this->type]['value'] ?? NULL;
		if( empty( $allowedNames ) ) {
			return;
		}
		if( in_array( $this->name, $allowedNames ) ) {
			return;
		}
		$allowedNames = implode( ', ', $allowedNames );
		$validator->addError( $this, $attribute, "Некорректное значение «{attribute}»: {value}. Для раздела типа «{$this->type}» допустимые значения: {$allowedNames}." );
	}

}
