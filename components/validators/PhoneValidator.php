<?php
/**
 * Валидатор для номера телефона
 */

namespace app\components\validators;

use yii\validators\Validator;

/**
 * This validator checks if the input value is a valid phone number.
 *
 * @author ap
 */
class PhoneValidator extends Validator {

	public $message = 'Phone number error';
	public $pattern = '/\d{10}/';

	public function validateAttribute( $model, $attribute ) {
		$validate = $this->validateValue( $model->$attribute );
		if( $validate ) {
			$this->addError( $model, $attribute, $validate[0], $validate[1] );
		}
	}

	protected function validateValue( $value ) {
		return preg_match( $this->pattern, preg_replace( '/\D/', '', $value ) ) ?
			NULL : [ $this->message, [ 'pattern' => $this->pattern ] ];
	}

}
