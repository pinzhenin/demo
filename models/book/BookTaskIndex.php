<?php

namespace app\models;

/**
 * This is the model class for table "book_task_index".
 *
 * @property int $task_id TASK_ID
 * @property int $htsSearch
 * @property int $htsHits
 * @property string $solution
 * @property string $parsing
 *
 * @property BookTask $bookTask
 */
class BookTaskIndex extends \yii\db\ActiveRecord {

	/**
	 * {@inheritdoc}
	 */
	public static function tableName() {
		return 'book_task_index';
	}

	/**
	 * Краткое описание модели
	 * @return string
	 */
	public static function tableComment() {
		return 'Полнотекстовый индекс для заданий';
	}

	/**
	 * {@inheritdoc}
	 */
	public function rules() {
		return [
			[ 'task_id', 'required' ],
			[ 'task_id', 'integer' ],
			[ 'task_id', 'unique' ],
			[ 'task_id', 'exist', 'skipOnError' => true, 'targetClass' => BookTask::className(), 'targetAttribute' => [ 'task_id' => 'id' ] ],
			[ 'htsSearch', 'boolean' ],
			[ 'htsHits', 'integer' ],
			[ [ 'solution', 'parsing' ], 'string' ],
			[ [ '_created', '_updated' ], 'safe' ],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels() {
		return [
			'task_id' => 'TASK_ID',
			'htsSearch' => 'как пишется: участие в поиске',
			'htsHits' => 'как пишется: количество просмотров',
			'solution' => 'правильный ответ',
			'parsing' => 'все возможные варианты',
			'_created' => 'created',
			'_updated' => 'updated',
		];
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getBookTask() {
		return $this->hasOne( BookTask::className(), [ 'id' => 'task_id' ] );
	}

	public static function updateIndex( BookTask $task ) {
		if( $task->theme !== 'spelling' ) { // для индекса пока нужна только орфография
			return;
		}
		$taskIndex = self::findOne( $task->id );
		if( empty( $taskIndex ) ) {
			$taskIndex = new self();
			$taskIndex->task_id = $task->id;
		}
		$taskIndex->solution = $task->solution;
		// А тут самое главное!!!
		$problem = $task->problem;
		$parsing = [];
		// - убираем ударения
		$problem = preg_replace( '/\x{0301}/u', '', $problem );
		// - парсим problem: выбираем только мета-слова с подстановками
		preg_match_all( '/([\w-]*(?:\[.+?\][\w-]*)+)/u', $problem, $matches );
		$metaWords = $matches[0];
		// - готовим список возможных написаний meta-слов
		foreach( $metaWords as $metaWord ) {
			$metaParts = preg_split( '/\[(.*?)\]/u', $metaWord, -1, PREG_SPLIT_DELIM_CAPTURE );
			$words = [ '' ];
			while( $metaParts ) {
				$letters = array_shift( $metaParts );
				array_walk( $words, function(&$item) use($letters) { $item .= $letters; } );
				if( empty( $metaParts ) ) {
					break;
				}
				$symbols = preg_split( '/[,|]/u', array_shift( $metaParts ) );
				$words2 = [];
				foreach( $symbols as $symbol ) {
					$words2 = array_merge( $words2,
						array_map( function($item) use($symbol) { return $item . $symbol; }, $words )
					);
				}
				$words = $words2;
			}
			$parsing[] = implode( '|', $words );
		}
		$taskIndex->parsing = implode( ',', $parsing );
		$taskIndex->htsSearch = TRUE;
		$taskIndex->save();
	}

	public static function searchTasksByKeyword( string $stringToSearchIn, int $wordMinLength = 3 ) {
		// убираем ударения: могут быть подставлены автоматически из словаря
		$stringToSearchIn = preg_replace( '/\x{0301}/u', '', $stringToSearchIn );
		// выберем текстовые фрагменты нужной длины
		preg_match_all( "/\\w{{$wordMinLength},}/u", $stringToSearchIn, $matches ); // regexp: /[а-яё]{...}/iu
		// если нет фрагметнтов подходящей длины, поиск не проводим
		if( empty( $matches[0] ) ) { return NULL; }
		// сформируем строку для поиска
		$stringToSearchOut = implode( ',', array_map( function($item) { return "{$item}*"; }, $matches[0] ) );

		// подготовим запрос
		$query = self::searchTasksByUmk( 11 ) // Ладыженская
			->addSelect( [ 'score' => 'MATCH(`parsing`) AGAINST(:keyword IN BOOLEAN MODE)' ] )
			->andWhere( 'MATCH(`parsing`) AGAINST(:keyword IN BOOLEAN MODE)' )
			->orderBy( [ 'score' => SORT_DESC, 'htsHits' => SORT_DESC, 'solution' => SORT_ASC ] )
			->params( [ ':keyword' => $stringToSearchOut ] );
		return $query;
	}

	public static function searchTasksByHits() {
		// подготовим запрос
		$query = self::searchTasksByUmk( 11 ) // Ладыженская
			->orderBy( [ 'htsHits' => SORT_DESC, 'solution' => SORT_ASC ] );
		return $query;
	}

	public static function searchTasksByUmk( int $umk_id = NULL ) {
		$query = self::find()
			->select( [
				'task_id', 'solution', 'htsSearch', 'htsHits'
			] )
			->distinct()
			->where( 'htsSearch' )
			->innerJoinWith( [
				'bookTask' => function (\yii\db\ActiveQuery $query) use($umk_id) {
					$query->select( [ 'book_task.id', 'book_task.unit_id', 'book_task.problem' ] );
					$query->andWhere( [ 'book_task.status' => [ 'on', 'new' ] ] );
					$query->innerJoinWith( [
						'bookUnit as unit' => function (\yii\db\ActiveQuery $query) use($umk_id) {
							$query->select( [ 'unit.id' /*, 'unit.name' */ ] );
							$query->andWhere( [ 'unit.type' => 'правило', 'unit.status' => 'on' ] );
							$query->innerJoinWith( [
								'bookRules as rule' => function (\yii\db\ActiveQuery $query) use($umk_id) {
									$query->select( [ 'rule.id', 'rule.type', 'rule.name', 'rule.desc', 'rule.grade' ] );
									$query->andWhere( [ 'rule.type' => 'правило', 'rule.status' => 'on' ] );
									$query->orderBy( [ 'rule.grade' => SORT_ASC ] );
									$query->innerJoinWith( [
										'parent as book' => function (\yii\db\ActiveQuery $query) use($umk_id) {
											$query->select( [ 'book.id', 'book.idParent', 'book.name' ] );
											$query->andWhere( [ 'book.type' => 'учебник', 'book.status' => 'on' ] );
											$query->innerJoinWith( [
												'parent as theme' => function (\yii\db\ActiveQuery $query) use($umk_id) {
													$query->select( [ 'theme.id', 'theme.idParent', 'theme.name' ] );
													$query->andWhere( [
														'theme.type' => 'тема',
														'theme.name' => 'Орфография',
														'theme.status' => 'on',
														'theme.idParent' => $umk_id
													] );
												}
											], FALSE );
										}
									], FALSE );
								}
							] );
						}
					] );
				}
			] );
		return $query;
	}

	public static function countHit( $task ) {
		$taskIndex = self::findOne( $task->id );
		$taskIndex->htsHits++;
		$taskIndex->save();
	}

	public static function prevTask( $task, $vv = FALSE ) {
		$taskID = is_object($task) ? $task->id : (int) $task;
		$prevID = self::find()
			->select( [ 'task_id' ] )
			->where( 'htsSearch' )
			->andWhere( [ ($vv ? '>' : '<'), 'task_id', $taskID ] )
			->orderBy( [ 'task_id' => ($vv ? SORT_ASC : SORT_DESC ) ] )
			->limit( 1 )
			->scalar();
		return $prevID ? BookTask::findOne( $prevID ) : NULL;
	}

	public static function nextTask( $task ) {
		return self::prevTask( $task, TRUE );
	}

}
