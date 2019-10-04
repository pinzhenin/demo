<?php
/**
 * Как пишется?
 * https://gramotei.online/how-to-spell/5305
 *
 * Модели: ~/models/BookTask*.php
 */

namespace app\controllers;

use app\models\{BookTask, BookTaskIndex};
use yii\web\NotFoundHttpException;

class HowToSpellController extends ControllerBasic {

	public function actionIndex( string $keyword = NULL ) {
		$data = [
			'searchQuery' => $keyword ? BookTaskIndex::searchTasksByKeyword( $keyword ) : NULL,
			'searchKeyword' => $keyword,
			'faqQuery' => BookTaskIndex::searchTasksByHits()
		];
		return $this->renderData( $data );
	}

	public function actionView( int $task_id = NULL ) {
		$task = BookTask::findOne( $task_id );
		if( empty( $task ) ||
			empty( $task->bookTaskIndex->htsSearch ) ||
			empty( in_array( $task->status, [ 'on', 'new' ] ) ) ||
			empty( $task->unit->status === 'on' ) ) {
			throw new NotFoundHttpException();
		}
		$task->countHit(); // зафиксируем запрос для FAQ
		$data = [
			'task' => $task,
			'rule' => $task->unit->rules[0],
			'prevTask' => BookTaskIndex::prevTask( $task ),
			'nextTask' => BookTaskIndex::nextTask( $task ),
			'searchQuery' => BookTaskIndex::searchTasksByKeyword( $task->solution ),
			'searchKeyword' => $task->solution
		];
		return $this->renderData( $data );
	}

	public function actionViewOld( int $task_id = NULL ) { // Удалить после 16.08.2019
		return $this->redirect( [ 'how-to-spell/view', 'task_id' => $task_id ], 301 );
	}

}
