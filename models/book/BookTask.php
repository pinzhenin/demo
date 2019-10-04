<?php

namespace app\models;

use Yii;
use yii\caching\TagDependency;

/**
 * This is the model class for table "book_task".
 *
 * @property integer $id
 * @property integer $unit_id
 * @property string $problem
 * @property integer $grade
 * @property string $status
 *
 * @property BookUnit $bookUnit
 */
class BookTask extends \yii\db\ActiveRecord {

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
		$this->updateIndex();
		return parent::afterSave( $insert, $changedAttributes );
	}

	/**
	 * @inheritdoc
	 */
	public static function tableName() {
		return 'book_task';
	}

	/**
	 * Краткое описание модели
	 * @return string
	 */
	public static function tableComment() {
		return 'Задания (словарь)';
	}

	static $fieldRange = [ // Допустимые значения полей типа set/enum
		'grade' => [ 0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11 ],
		'status' => [ 'new', 'on', 'off' ]
	];

	/**
	 * @inheritdoc
	 */
	public function rules() {
		return [
			[ 'unit_id', 'integer', 'min' => 0 ],
			[ 'unit_id', 'required' ],
			[ 'unit_id', 'filter', 'filter' => 'intval', 'skipOnEmpty' => TRUE ],
			[ 'unit_id', 'exist', 'skipOnError' => TRUE, 'targetClass' => BookUnit::className(), 'targetAttribute' => [ 'unit_id' => 'id' ] ],
			[ 'problem', 'trim' ],
			[ 'problem', 'string', 'max' => 65535 ], // 2**16-1
			[ 'problem', 'required' ],
			[ 'problem', 'validateProblem' ],
			[ 'grade', 'integer' ],
			[ 'grade', 'required' ],
			[ 'grade', 'filter', 'filter' => 'intval' ],
			[ 'grade', 'in', 'range' => self::$fieldRange['grade'] ],
			[ 'status', 'string' ],
			[ 'status', 'required' ],
			[ 'status', 'in', 'range' => self::$fieldRange['status'] ]
		];
	}

	/**
	 * @inheritdoc
	 */
	public function attributeLabels() {
		return [
			'id' => 'id задания',
			'unit_id' => 'id юнита',
			'problem' => 'задание',
			'solution' => 'ответ',
			'grade' => 'минимальный класс',
			'status' => 'статус задания',
		];
	}

	public function fields() {
		$fields = parent::fields();
		array_push( $fields, 'solution' );
		return $fields;
	}

	public function extraFields() {
		$extraFields = parent::extraFields();
		array_push( $extraFields, 'unit', 'tags', 'theme' );
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
	public function getBookTaskIndex() {
		return $this->hasOne( BookTaskIndex::className(), [ 'task_id' => 'id' ] );
	}

	// Shortcut to getBookUnit()
	public function getUnit() {
		return $this->bookUnit;
	}

	// task->unit->tags
	public function getTags() {
		return $this->unit->tags;
//		$key = "unit[{$this->unit_id}]->tag";
//		$tags = self::$cache->getOrSet( $key, function () { return $this->unit->tags; },
//			NULL, new TagDependency( [ 'tags' => [ "unit[{$this->unit_id}]", 'unit', 'unit->tag' ] ] ) );
//		return $tags;
	}

	// task->unit->theme
	public function getTheme() {
		$key = "task[{$this->id}]->theme";
		$theme = self::$cache->getOrSet( $key, function () { return $this->unit->theme; },
			NULL, new TagDependency( [ 'tags' => [ "task[{$this->id}]", 'task', 'task->theme' ] ] ) );
		return $theme;
	}

	// Ответ: мета-задание с правильной подстановкой
	public function getSolution() {
		$key = "task[{$this->id}]->solution";
		$solution = self::$cache->getOrSet( $key, function () { return $this->problemResolve(); },
			NULL, new TagDependency( [ 'tags' => [ "task[{$this->id}]", 'task', 'task->solution', "unit[{$this->unit_id}]", 'unit' ] ] ) );
		return $solution;
	}

	public function clearCache() {
		TagDependency::invalidate( self::$cache, [ "task[{$this->id}]" ] );
	}

	public static function clearCacheTotal() {
		TagDependency::invalidate( self::$cache, [ 'task' ] );
	}

	public function load( $data, $formName = NULL ) {
		$this->clearCache();
		return parent::load( $data, $formName );
	}

	public function updateIndex() {
		BookTaskIndex::updateIndex( $this );
	}

	public function countHit() {
		BookTaskIndex::countHit( $this );
	}

	/**
	 * @param string $attribute the attribute currently being validated
	 * @param mixed $params the value of the "params" given in the rule
	 * @param \yii\validators\InlineValidator related InlineValidator instance.
	 */
	public function validateProblem( $attribute, $params, $validator ) {
		$this->problemCorrect();
		if( !$this->problemValidate() ) {
			$validator->addError( $this, $attribute, "Ошибка синтаксиса «{attribute}»: {value}" );
		}
	}

	// Шаблоны для замены "похожих" символов эталонными (пре-валидация)
	protected static $problemCorrector = [
		// общие шаблоны
		'_general_' => [
			'/^[[:space:]]/' => '',
			'/[[:space:]]+/' => ' ',
			'/[[:space:]]$/' => '',
			'/[˗‐‑‒–—―⁃]/u' => '-',
			'/Ë/u' => 'Ё',
			'/ë/u' => 'ё'
		],
		// специфика
		'spelling' => [
			// nothing special
		],
		'pointing' => [
			'! \[ (?<tag> (?: %tagsRegexp% ) ) (\??) \] ( [^][/]++ ) (?: / | \[/\] | \[/\g{tag}\] ) !ux' => '[$1$2]$3[/$1]'
		]
	];

	// Корректировка текста задания
	public function problemCorrect() {
		$theme = $this->theme;
		$problemCorrectorGeneral = self::$problemCorrector['_general_'];
		$problemCorrectorSpecial = self::$problemCorrector[$theme] ?? [];
		if( $theme === 'pointing') {
			$problemCorrectorSpecial = $this->preparePatternPointing( $problemCorrectorSpecial );
		}
		$problemCorrector = array_merge( $problemCorrectorGeneral, $problemCorrectorSpecial );
		$pattern = array_keys( $problemCorrector );
		$replacement = array_values( $problemCorrector );
		$this->problem = preg_replace( $pattern, $replacement, $this->problem );
	}

	// Шаблоны для валидации задания
	protected static $problemValidator = [
		'spelling' => <<<EOS
~^
(?<text> [а-яё.,:;!?()"'«»\x{0301} -]*+) (?# текст )
( \[ (?<meta> [а-яё" -]{0,3}+) ([,|](?&meta))+ \] (?&text) )+ (?# [мета]+текст )
$~iux
EOS
			,
		'pointing' => <<<EOP
~^
(?<text> [а-яё.,:;!?()"'«»\x{0301} -]*+) (?# текст )
( \[ (?<tag> (?: %tagsRegexp% ) ) \?? \] (?&text) \[/\g{tag}\] (?&text) )+ (?# [тег]+текст+[/тег]+текст )
$~iux
EOP
	];

	// Валидация текста задания
	public function problemValidate() {
		$theme = $this->theme;
		if( empty( $theme ) ) {
			return FALSE;
		}
		$problemValidator = self::$problemValidator[$theme] ?? '/^.*$/';
		if( $theme === 'pointing') {
			$problemValidator = $this->preparePatternPointing( $problemValidator );
		}
		return preg_match( $problemValidator, $this->problem );
	}

	// Шаблоны для получения правильного ответа
	protected static $problemResolver = [
		'spelling' => [
			'/\[(?<meta>[а-яё" -]{0,3}+)([,|](?&meta))+\]/iu' => '$1'
		],
		'pointing' => [
			'! \[ (?<tag> (?: %tagsRegexp% ) ) \?? \] ( [^][/]++ ) (?: / | \[/\] | \[/\g{tag}\] ) !ux' => '$2'
		]
	];

	// Парсинг текста задания (problem) => получение правильного ответа (solution)
	public function problemResolve() {
		$theme = $this->theme;
		$problemResolver = self::$problemResolver[$theme] ?? [];
		if( $theme === 'pointing' ) {
			$problemResolver = $this->preparePatternPointing( $problemResolver );
		}
		$pattern = array_keys( $problemResolver );
		$replacement = array_values( $problemResolver );
		$solution = preg_replace( $pattern, $replacement, $this->problem );
		return $solution;
	}
//	public static function problemResolve( $theme, $problem ) {
//		$problemResolver = self::$problemResolver[$theme] ?? [];
//		if( $theme === 'pointing' ) {
//			$problemResolver = $this->preparePatternPointing( $problemResolver );
//		}
//		$pattern = array_keys( $problemResolver );
//		$replacement = array_values( $problemResolver );
//		$solution = preg_replace( $pattern, $replacement, $problem );
//		return $solution;
//	}

	// корректировка шаблонов для пунктуации: включаем теги
	protected function preparePatternPointing( $pattern ) {
		$tags = $this->tags;
		$tagsRegexp = implode( '|', array_map( function( $tag ) { return $tag->name; }, $tags ) ) . '|ЗП';
		$patternNew = [];
		if( is_array($pattern) ) {
			foreach( $pattern as $key => $value ) {
				if( is_int( $key ) ) {
					$patternNew[] = str_replace( '%tagsRegexp%', $tagsRegexp, $value );
				}
				else {
					$patternNew[str_replace( '%tagsRegexp%', $tagsRegexp, $key )] = $value;
				}
			}
		}
		elseif( is_string( $pattern ) ) {
			$patternNew = str_replace( '%tagsRegexp%', $tagsRegexp, $pattern );
		}
		else {
			$patternNew = $pattern;
		}
		return $patternNew;
	}

}
