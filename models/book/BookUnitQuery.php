<?php

namespace app\models;

use paulzi\adjacencyList\AdjacencyListQueryTrait;

/**
 * This is the ActiveQuery class for [[BookUnit]].
 *
 * @see BookUnit
 */
class BookUnitQuery extends \yii\db\ActiveQuery {

	use AdjacencyListQueryTrait;

}
