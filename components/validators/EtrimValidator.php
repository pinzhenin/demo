<?php
/**
 * Валидатор (корректор) для удаления лишних пробелов в тексте.
 * Удаляет крайние пробелы и заменяет группу пробелов одним.
 */

namespace app\components\validators;

use yii\validators\Validator;

/**
 * This validator works like «trim», but additionally trims multiple white spaces inside the input value.
 *
 * @author ap
 */
class EtrimValidator extends Validator {

	public function validateAttribute( $model, $attribute ) {
		$model->$attribute = preg_replace( '/\s+/', ' ', trim( $model->$attribute ) );
	}

}
