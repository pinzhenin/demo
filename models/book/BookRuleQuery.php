<?php

namespace app\models;

use paulzi\adjacencyList\AdjacencyListQueryTrait;

/**
 * This is the ActiveQuery class for [[BookRule]].
 *
 * @see BookRule
 */
class BookRuleQuery extends \yii\db\ActiveQuery {

	use AdjacencyListQueryTrait;

}
