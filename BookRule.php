<?php

namespace app\models;

use Yii;
use yii\caching\TagDependency;
use paulzi\{adjacencyList\AdjacencyListBehavior, sortable\SortableBehavior};

/**
 * This is the model class for table "book_rule".
 *
 * @property integer $id
 * @property integer $idParent
 * @property string $type
 * @property string $code
 * @property string $name
 * @property string $grade
 * @property integer $gradeMonth
 * @property string $status
 * @property string $desc
 * @property integer $sort
 *
 * @property BookRule $idParent0
 * @property BookRule[] $bookRules
 * @property BookRuleUnit[] $bookRuleUnits
 * @property BookUnit[] $bookUnits
 */
class BookRule extends \yii\db\ActiveRecord {

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
		return 'book_rule';
	}

	/**
	 * Краткое описание модели
	 * @return string
	 */
	public static function tableComment() {
		return 'УМК, учебники, правила';
	}

	public function behaviors() {
		return [
			[
				'class' => AdjacencyListBehavior::className(),
				'parentAttribute' => 'idParent',
				'sortable' => [
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
			'предмет' => [ 'children' => [ 'УМК' ], 'value' => [ 'Русский язык', 'Математика' ] ],
			'УМК' => [ 'children' => [ 'тема' ] ],
			'тема' => [ 'children' => [ 'учебник' ], 'value' => [ 'Орфография', 'Пунктуация' ] ],
			'учебник' => [ 'children' => [ 'правило' ], 'labels' => [ 'автор', 'издательство', 'год выпуска', 'isbn', 'сайт' ] ],
			'правило' => [ 'children' => NULL, 'labels' => [ 'текст', 'пример', 'исключение', 'примечание', 'другое' ] ]
		],
		'grade' => [ 0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11 ],
		'gradeMonth' => [ 0, 1, 2, 3, 4, 5, 6, 7, 8, 9 ],
		'status' => [ 'on', 'hidden', 'off' ]
	];

	/**
	 * @inheritdoc
	 */
	public function rules() {
		return [
			[ 'idParent', 'integer', 'min' => 0 ],
			[ 'idParent', 'filter', 'filter' => 'intval', 'skipOnEmpty' => TRUE ],
			[ 'idParent', 'exist', 'skipOnError' => TRUE, 'targetClass' => BookRule::className(), 'targetAttribute' => [ 'idParent' => 'id' ] ],
			[ 'type', 'string' ],
			[ 'type', 'required' ],
			[ 'type', 'in', 'range' => array_keys( self::$fieldRange['type'] ) ],
			[ 'type', 'validateTypeChain' ],
			[ 'code', 'string', 'max' => 100 ],
			[ 'code', 'default', 'value' => NULL ],
			[ 'code', 'unique' ],
			[ 'name', 'trim' ],
			[ 'name', 'required' ],
			[ 'name', 'string', 'max' => 255 ],
			[ 'name', 'validateNameValue' ],
			[ 'name', 'validateNameSyntax',
				'when' => function( $model ) { return in_array( $model->type, [ 'правило' ] ); } ],
			[ 'grade', 'string' ],
			[ 'grade', 'default', 'value' => NULL ],
			[ 'grade', 'required',
				'when' => function( $model ) { return in_array( $model->type, [ 'УМК', 'учебник', 'правило' ] ); },
				'whenClient' => "function (attribute, value) { return $.inArray( $('#rule-type').val(), [ 'УМК', 'учебник', 'правило' ] ) > -1; }" ],
			[ 'grade', 'validateGrade' ],
			[ 'gradeMonth', 'integer' ],
			[ 'gradeMonth', 'default', 'value' => NULL ],
			[ 'gradeMonth', 'required',
				'when' => function( $model ) { return in_array( $model->type, [ 'правило' ] ); },
				'whenClient' => "function (attribute, value) { return $('#rule-type').val() == 'правило'; }" ],
			[ 'gradeMonth', 'in', 'range' => self::$fieldRange['gradeMonth'] ],
			[ 'status', 'string' ],
			[ 'status', 'required' ],
			[ 'status', 'in', 'range' => self::$fieldRange['status'] ],
			[ 'desc', 'string' ],
			[ 'desc', 'default', 'value' => NULL ],
			[ 'desc', 'required',
				'when' => function( $model ) { return in_array( $model->type, [ 'правило' ] ); },
				'whenClient' => "function (attribute, value) { return $('#rule-type').val() == 'правило'; }" ],
			[ 'desc', 'validateDescFormat' ],	// формат json + чистка
			[ 'desc', 'validateDescSyntax',		// разметка тегами
				'when' => function( $model ) { return in_array( $model->type, [ 'правило' ] ); },
				'whenClient' => "function (attribute, value) { return $('#rule-type').val() == 'правило'; }" ],
			[ 'desc', 'validateDescLabels',		// имена блоков и порядок следования
				'when' => function( $model ) { return is_array( self::$fieldRange['type'][$model->type]['labels'] ); } ],
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
			'grade' => 'класс обучения',
			'gradeMonth' => 'месяц обучения',
			'status' => 'статус раздела',
			'desc' => 'описание раздела',
			'sort' => 'вес для сортировки',
		];
	}

	public function extraFields() {
		$extraFields = parent::extraFields();
		array_push( $extraFields, 'parent', 'parents', 'parentsOrdered', 'children', 'rules', 'units' );
		array_push( $extraFields, 'nameHtml', 'nameText', 'descArray', 'descArrayHtml', 'descArrayText', 'gradeArray' );
		return $extraFields;
	}

	/**
	 * @return mixed $property
	 */
	public function __get( $name ) {
		if( in_array( $name, [ 'nameHtml', 'nameText', 'descArray', 'descArrayHtml', 'descArrayText', 'gradeArray' ] ) ) {
			$key = "rule[{$this->id}]->{$name}";
			$value = self::$cache->getOrSet( $key, function () use( $name ) { return $this->fetchExtra( $name ); },
				NULL, new TagDependency( [ 'tags' => [ "rule[{$this->id}]", 'rule', "rule->{$name}" ] ] ) );
			return $value;
		}
		return parent::__get( $name );
	}

	/**
	 * @return mixed $property
	 */
	public function fetchExtra( $name ) {
		switch( $name ) {
			case 'nameHtml':
				return $this->type === 'правило' ? self::toHtml( $this->name ) : $this->name;
			case 'nameText':
				return $this->type === 'правило' ? self::toText( $this->name ) : $this->name;
			case 'descArray':
				return self::formatDesc2Array( $this->desc );
			case 'descArrayHtml':
				return self::formatDesc2ArrayHtml( $this->desc );
			case 'descArrayText':
				return self::formatDesc2ArrayText( $this->desc );
			case 'gradeArray':
				return mb_strlen( $this->grade ) ? explode( ',', $this->grade ) : [];
		}
		return NULL;
	}

	// provided by paulzi\adjacencyList\AdjacencyListBehavior
	// public function getParent() {}
	// public function getParents() {}
	// public function getChildren() {}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getIdParent0() {
		return $this->hasOne( BookRule::className(), [ 'id' => 'idParent' ] );
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getBookRules() {
		return $this->hasMany( BookRule::className(), [ 'idParent' => 'id' ] );
	}

//	из оригинальной модели - возможно где-то используется
	public function getRuleRules() {
		return $this->hasMany( BookRule::className(), [ 'idParent' => 'id' ] )
				->select( '*, (schoolMonth() >= rule1.gradeMonth) as `pass`' )
				->from( [ 'rule1' => BookRule::tableName() ] );
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getBookRuleUnits() {
		return $this->hasMany( BookRuleUnit::className(), [ 'rule_id' => 'id' ] );
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getBookUnits() {
		return $this->hasMany( BookUnit::className(), [ 'id' => 'unit_id' ] )->viaTable( 'book_rule_unit', [ 'rule_id' => 'id' ] );
	}

	// Shortcut to getBookRules()
	public function getRules() {
		return $this->bookRules;
	}

	// Shortcut to getBookUnits()
	public function getUnits() {
		return $this->bookUnits;
	}

	/**
	 * @return string
	 */
	public function getTheme() {
		$key = "rule[{$this->id}]->theme";
		$value = self::$cache->getOrSet( $key, function () { return $this->fetchTheme(); },
			NULL, new TagDependency( [ 'tags' => [ "rule[{$this->id}]", 'rule', 'rule->theme' ] ] ) );
		return $value;
	}

	/**
	 * @return string
	 */
	public function fetchTheme() {
		$pathParents = [];
		foreach( $this->parentsOrdered as $rule ) {
			$pathParents[$rule->type] = $rule->name;
		}
		$pathPatterns = [];
		foreach( Yii::$app->params['theme'] as $theme => $paths ) {
			$pathPatterns[$theme] = $paths['rulePath'];
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
		TagDependency::invalidate( self::$cache, [ "rule[{$this->id}]" ] );
	}

	public static function clearCacheTotal() {
		TagDependency::invalidate( self::$cache, [ 'rule' ] );
	}

	/**
	 * @inheritdoc
	 * @return BookRuleQuery the active query used by this AR class.
	 */
	public static function find() {
		return new BookRuleQuery( get_called_class() );
	}

	public function load( $data, $formName = NULL ) {
		$this->clearCache();
		if( parent::load( $data, $formName ) ) {
			$this->loadGrade();
			$this->loadDesc();
			return TRUE;
		}
		return FALSE;
	}

	// Загрузка поля grade
	protected function loadGrade() {
		$this->grade = is_array( $this->grade ) ? implode( ',', $this->grade ) : NULL;
	}

	// Загрузка поля desc
	protected function loadDesc() {
		if( is_array( $this->desc ) && is_array( $this->desc['key'] ) && is_array( $this->desc['value'] ) ) {
			$descArray = array_map(
				function( $key, $value ) { return [ 'key' => $key, 'value' => $value ]; },
				$this->desc['key'], $this->desc['value']
			);
			$this->desc = self::formatDesc2Json( $descArray );
		}
		else {
			$this->desc = NULL;
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

	/**
	 * @param string $attribute the attribute currently being validated
	 * @param mixed $params the value of the "params" given in the rule
	 * @param \yii\validators\InlineValidator related InlineValidator instance.
	 */
	public function validateNameSyntax( $attribute, $params, $validator ) {
		if( !preg_match( self::$descSyntaxValidator, $this->name ) ) {
			$validator->addError( $this, $attribute, "Ошибка синтаксиса «{attribute}»: {value}" );
		}
	}

	/**
	 * @param string $attribute the attribute currently being validated
	 * @param mixed $params the value of the "params" given in the rule
	 * @param \yii\validators\InlineValidator related InlineValidator instance.
	 */
	public function validateGrade( $attribute, $params, $validator ) {
		$gradeDiff = array_diff( $this->gradeArray, self::$fieldRange['grade'] );
		if( count( $gradeDiff ) ) {
			$validator->addError( $this, $attribute, "Некорректное значение «{attribute}»: {value}" );
		}
	}

	/**
	 * @param string $attribute the attribute currently being validated
	 * @param mixed $params the value of the "params" given in the rule
	 * @param \yii\validators\InlineValidator related InlineValidator instance.
	 */
	public function validateDescFormat( $attribute, $params, $validator ) {
		$descArray = $this->descArray;
		if( is_null( $descArray ) ) {
			$validator->addError( $this, $attribute, "Ошибка формата «{attribute}»: {value}" );
			return;
		}
		// почистим данные
		$descArray = self::cleanDescArray( $descArray );
		// проверим остались ли пустые ключи
		$descEmptyKey = array_filter( $descArray, function( $array ) { return mb_strlen( $array['key'] ) == 0; } );
		if( count( $descEmptyKey ) ) {
			$validator->addError( $this, $attribute, "Необходимо заполнить «{attribute}» (имя атрибута обязательно, если указано значение)." );
			return;
		}
		// сформируем json
		$this->desc = self::formatDesc2Json( $descArray );
	}

	/**
	 * Шаблон для валидации текста правила
	 *
	 * Спецсимволы:
	 * \x{0301} - ударение
	 * \x{2013} - &ndash;
	 * \x{2014} - &mdash;
	 * \x{2026} - …
	 * \x{2190} - ←
	 * \x{2192} - →
	 */
	protected static $descSyntaxValidator = <<<EOV
~^
(?<rule>
	(?<text> ( [[:alnum:][:space:]\x{0301}\x{2013}\x{2014}\x{2026}\x{2190}\x{2192}.,:;!?()"'«»>=*/+-]++ | \[(?!/?[A-Z]])[^]]++\] )++ )? (?# текст )
	( \[(?<tag>[PRSEAUWBOI])\] ((?&text)|(?&rule))? \[/\g{tag}\] (?&text)? )* (?# [тег]+текст+[/тег]+текст )
)
$~ux
EOV;

	/**
	 * @param string $attribute the attribute currently being validated
	 * @param mixed $params the value of the "params" given in the rule
	 * @param \yii\validators\InlineValidator related InlineValidator instance.
	 */
	public function validateDescSyntax( $attribute, $params, $validator ) {
		$descArray = $this->descArray;
		if( is_null( $descArray ) ) {
			$validator->addError( $this, $attribute, "Ошибка формата «{attribute}»: {value}" );
		}
		foreach( $descArray as $desc ) {
			if( !preg_match( self::$descSyntaxValidator, $desc['value'] ) ) {
				$validator->addError( $this, $attribute, "Ошибка синтаксиса «{attribute}»: {$desc['value']}" );
			}
		}
		return;
	}

	/**
	 * Шаблон для валидации текста правила
	 */
	protected static $descLabelsValidator = [
		'учебник' => '/^(автор )?(издательство )?(год выпуска )?(isbn )?(сайт )?(другое )?$/',
		'правило' => '/^(текст (пример )*(исключение )*)((текст|примечание) (пример )*(исключение )*)*(другое )?$/'
	];

	/**
	 * @param string $attribute the attribute currently being validated
	 * @param mixed $params the value of the "params" given in the rule
	 * @param \yii\validators\InlineValidator related InlineValidator instance.
	 */
	public function validateDescLabels( $attribute, $params, $validator ) {
		$descArray = $this->descArray;
		if( is_null( $descArray ) ) {
			$validator->addError( $this, $attribute, "Ошибка формата «{attribute}»: {value}" );
			return;
		}
		// список ключей (по порядку)
		$labelsArray = array_map( function($array) { return $array['key']; }, $descArray );
		$labelsPermitted = self::$fieldRange['type'][$this->type]['labels'] ?? [];
		$labelsDiff = array_diff( $labelsArray, $labelsPermitted );
		if( $labelsDiff ) {
			$validator->addError( $this, $attribute, 'Ошибка наименования блоков «{attribute}»: ' . implode( ', ', $labelsDiff ) . '.' );
		}
		$labelsString = implode( ' ', $labelsArray ) . ' ';
		$labelsValidator = self::$descLabelsValidator[$this->type] ?? '//';
		if( !preg_match( $labelsValidator, $labelsString ) ) {
			$validator->addError( $this, $attribute, 'Ошибка порядка следования блоков «{attribute}»: ' . implode( ', ', $labelsArray ) . '.' );
		}
	}

	/**
	 * @param array $descArray
	 * @return string $descJson
	 */
	public static function formatDesc2Json( $descArray ) {
		// подготовим массив для json
		$desc = [];
		foreach( $descArray as $array ) {
			$desc[] = [ $array['key'] => $array['value'] ];
		}
		return count( $desc ) ? json_encode( $desc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : NULL;
	}

	/**
	 * @param string $descJson
	 * @return array $descArray
	 */
	public static function formatDesc2Array( $descJson ) {
		// если desc пустой или NULL, возвращаем пустой массив
		if( empty( $descJson ) ) {
			return [];
		}
		// временная заплата для исправления json-строки (ошибка времён переноса БД)
		$descJson = preg_replace( [ '/\r?\n/', '/\\\\+n/' ], '\n', $descJson );
		// пытаемся распарсить desc как json
		$desc = json_decode( $descJson, TRUE );
		// если desc - не json, возвращаем NULL
		if( is_null( $desc ) ) {
			return NULL;
		}
		$descArray = [];
		foreach( $desc as $key => $value) {
			$descArray[] = ( is_int( $key ) && is_array( $value ) ) ?
				[ 'key' => key( $value ), 'value' => current( $value ) ] : /* формат: [{"key":"value"},...] */
				[ 'key' => $key, 'value' => $value ]; /* формат: {"key":"value",...} */
		}
		return $descArray;
	}

	/**
	 * @param string $descJson
	 * @return array $descArrayHtml
	 */
	public static function formatDesc2ArrayHtml( $descJson ) {
		$descArray = self::formatDesc2Array( $descJson );
		array_walk( $descArray, function( &$item ) { $item['value'] = self::toHtml( $item['value'] ); } );
		return $descArray;
	}

	/**
	 * @param string $descJson
	 * @return array $descArrayText
	 */
	public static function formatDesc2ArrayText( $descJson ) {
		$descArray = self::formatDesc2Array( $descJson );
		array_walk( $descArray, function( &$item ) { $item['value'] = self::toText( $item['value'] ); } );
		return $descArray;
	}

	/**
	 * @param array $descArrayDirty
	 * @return array $descArrayClean
	 */
	protected static function cleanDescArray( $descArrayDirty ) {
		$descArrayClean = [];
		foreach( $descArrayDirty as $array ) {
			$array['key'] = trim( $array['key'] );
			$array['value'] = preg_replace(
				[ '/^[[:space:]]+/m', '/[[:space:]]+$/m', '/[\t ]+/', '/\r?\n/' ],
				[ '', '', ' ', "\n" ],
				$array['value']
			);
			if( mb_strlen( $array['key'] ) || mb_strlen( $array['value'] ) ) {
				$descArrayClean[] = [ 'key' => $array['key'], 'value' => $array['value'] ];
			}
		}
		return $descArrayClean;
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
				'text' => self::toHtml( $array['name'] ),
				'type' => $array['type'],
				'status' => $array['status'],
//				'sort' => $array['sort'],
				'children' => !empty(self::$fieldRange['type'][$array['type']]['children']),
				'a_attr' => $a_attr,
				'li_attr' => [ 'data-status' => $array['status'] ]
			];
		}
	}

	protected static $descConverter = [
		'! \[ (?<tag>[E]) \] [[:space:]]* \[ /\g{tag} \] !x' => [ // нулевое окончание
			'html' => '[$1]&ensp;[/$1]',
			'text' => ''
		],
		'! \[ (?<tag>[UW]) \] [[:space:]]+ \[ /\g{tag} \] !x' => [ // подчёркивание пробела
			'html' => '[$1]&ensp;[/$1]',
			'text' => ' '
		],
		'! \[(?<tag>[PRSEAUWBOI])\] ( ( [^][]++ | \[ [^][/A-Z]++ \] )++ )? \[/\g{tag}\] !ux' => [ // теги
			'html' => '<span class="rule$1">$2</span>',
			'text' => '$2',
			'loop' => TRUE
		],
		'! [[:space:]]*\n !x' => [ // перевод строки
			'html' => '<br>',
			'text' => "\n"
		]
	];

	// Конвертируем данные в заданный формат
	public static function toFormat( $data, $format ) {
		foreach( self::$descConverter as $regexp => $array ) {
			if( isset( $array[$format] ) ) {
				do {
					$data = preg_replace( $regexp, $array[$format], $data, -1, $count );
				}
				while( $count && ($array['loop'] ?? FALSE) );
			}
		}
		return $data;
	}

	// Конвертируем данные в html формат
	public static function toHtml( $data ) {
		return self::toFormat( $data, 'html' );
	}

	// Конвертируем данные в text формат
	public static function toText( $data ) {
		return self::toFormat( $data, 'text' );
	}

	/*
	 * Возвращает массив всех УМК (актуально для форм выбора УМК).
	 */
	public static function getAllUmk( $idParent = 1 ) {
		$umk = self::find()
			->select( [ 'id', 'name' ] )
			->where( [ 'idParent' => $idParent, 'type' => 'УМК', 'status' => 'on' ] )
			->asArray()
			->all();
//		return array_column( $umk, 'name', 'id' );
		$umkIdName = array_column( $umk, 'name', 'id' );
		$return = [
			'УМК для 1-4 классов' => [],
			'УМК для 5-11 классов' => [],
			'Дополнительные учебники' => [],
		];
		foreach($umkIdName as $id => $name){
			$name = str_replace('под редакцией ', '', $name);
			$name = preg_replace('/. Авторы:.+/i', '', $name);
			$name = preg_replace('/ \(на основе.+/i', '', $name);
			if(strripos($name, '1-4 класс') !== false){
				$name = str_replace('1-4 класс. ', '', $name);
				$return['УМК для 1-4 классов'][$id] = $name;
			}
			elseif(strripos($name, '5-9 класс') !== false){
				$name = str_replace('5-9 класс. ', '', $name);
				$return['УМК для 5-11 классов'][$id] = $name;
			}
			else{
				$return['Дополнительные учебники'][$id] = $name;
			}
		}
		return $return;
	}

	// Возвращает список УМК
	public static function fetchUmks( $subjectID = 1, $status = 'on' ) {
		$umks = self::find()
			->where( [ 'idParent' => $subjectID, 'type' => 'УМК', 'status' => $status ] )
			->orderBy( 'sort' )
			->asArray()
			->all();
		return $umks;
	}

	// Возвращает список учебников
	public static function fetchUmkBooks( $umkID, $theme = 'Орфография', $status = 'on' ) {
		$books = self::find()
			->where( [ 'idParent' => $umkID, 'type' => 'тема', 'status' => $status, 'name' => $theme ] )
			->one()
			->getChildren()
			->where( [ 'type' => 'учебник', 'status' => $status ] )
			->asArray()
			->all();
		return $books;
	}

	// Возвращает список правил
	public static function fetchBookRules( $bookID, $status = 'on' ) {
		$rules = self::find()
			->where( [ 'idParent' => $bookID, 'type' => 'правило', 'status' => $status ] )
			->asArray()
			->all();
		return $rules;
	}

}
