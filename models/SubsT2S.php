<?php
/**
 * Модель: Учитель оформляет подписку для группы учащихся (класса).
 * Используется для расчёта параметров подписки по частичным данным: список учащихся + дата окончания
 */

namespace app\models;

use Yii;
use yii\base\Model;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for subscription form/manipulation.
 *
 * @property int $id ID
 * …
 */
class SubsT2S extends Model {

	const DISCOUNT = 0.5;				// скидка в процентах от регулярного прайса
	const DISCOUNT_MIN_STUDENTS = 10;	// минимальное количество подписчиков для применения скидки

	private $teacher;
	private $yyyymmdd;
	public $teacherId;					// id учителя
	public $classes = [];				// [ [ 'id' => …, 'name' => …, 'students' => [ […], … ] ], … ]
	public $students = [];				// { 'id' => …, 'start' => …, 'finish' => …, 'duration' => …, 'cost' => … }
	public $start;						// дата начала подписки (минимальная по всем учащимся)
	public $finish;						// дата окончания подписки (общая для всех учащихся)
	public $durationTotal;				// длительность суммарная
	public $durationAverage;			// длительность: в среднем на учащегося (только для ненулевых)
	public $pricePerDayRegular;			// стоимость подписки за день
	public $pricePerDayDiscount;		// стоимость подписки за день со скидкой
	public $priceRegular;				// стоимость подписки за весь период
	public $priceDiscount;				// стоимость подписки за весь период со скидкой
	public $numberOfClasses;			// количество классов
	public $numberOfStudents;			// количество учащихся
	public $selectedToSubscribe;		// количество учащихся, выбранных для оформления подписки
	public $eligibleToSubscribe;		// количество учащихся, подходящих для оформления подписки
	public $numberOfSubscribersBefore;	// количество учащихся, имеющих действующую подписку
	public $numberOfSubscribersAfter;	// количество учащихся, у которых будет подписка после оформления текущей
	public $discountFlag;				// признак применения скидки

	/**
	 * {@inheritdoc}
	 */
	public function init() {
		parent::init();
		$this->yyyymmdd = date( 'Y-m-d' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function fields() {
		$fields = parent::fields();
		array_push( $fields, 'price' );
		return $fields;
	}

	/**
	 * {@inheritdoc}
	 */
	public function rules() {
		return [
			// id учителя
			[ 'teacherId', 'default', 'value' => // доопределим id учителя
				function($model) {
					return $this->defaultTeacherId( $model );
				} ],
			[ 'teacherId', 'required' ],
			[ 'teacherId', 'integer', 'min' => 0 ],
			[ 'teacherId', 'exist', 'skipOnError' => TRUE, 'targetClass' => User::className(), 'targetAttribute' => [ 'teacherId' => 'id' ] ],
			[ 'teacherId', 'validateTeacherId', 'skipOnEmpty' => FALSE ],
			// дата окончания подписки
			[ 'finish', 'required' ],
			[ 'finish', 'filter', 'filter' =>
				function($date) {
					return preg_replace( '/^(\d{2})\.(\d{2})\.(\d{4})$/', '$3-$2-$1', $date );
				} ],
			[ 'finish', 'date', 'format' => 'php:Y-m-d' ],
			[ 'finish', 'validateFinish' ],

			/**
			 * Всё остальное опционально: может быть рассчитано и проверено автоматически
			 */
			// классы: их передавать не надо, построит сама модель
			[ 'classes', 'validateClasses', 'skipOnEmpty' => FALSE ],
			// студенты: нужно передать только список id'ов, кому нужно оформить подписку
			[ 'students', 'validateStudents', 'skipOnEmpty' => FALSE ],
			[ 'start', 'date', 'format' => 'php:Y-m-d' ],
			[ 'start', 'validateStart', 'skipOnEmpty' => FALSE ],
			[ 'durationTotal', 'validateDurationTotal', 'skipOnEmpty' => FALSE ],
			[ 'durationAverage', 'validateDurationAverage', 'skipOnEmpty' => FALSE ],
			[ 'pricePerDayRegular', 'validatePricePerDayRegular', 'skipOnEmpty' => FALSE ],
			[ 'pricePerDayDiscount', 'validatePricePerDayDiscount', 'skipOnEmpty' => FALSE ],
			[ 'priceRegular', 'validatePriceRegular', 'skipOnEmpty' => FALSE ],
			[ 'priceDiscount', 'validatePriceDiscount', 'skipOnEmpty' => FALSE ],
			[ 'numberOfClasses', 'validateNumberOfClasses', 'skipOnEmpty' => FALSE ],
			[ 'numberOfStudents', 'validateNumberOfStudents', 'skipOnEmpty' => FALSE ],
			[ 'selectedToSubscribe', 'validateSelectedToSubscribe', 'skipOnEmpty' => FALSE ],
			[ 'eligibleToSubscribe', 'validateEligibleToSubscribe', 'skipOnEmpty' => FALSE ],
			[ 'numberOfSubscribersBefore', 'validateNumberOfSubscribersBefore', 'skipOnEmpty' => FALSE ],
			[ 'numberOfSubscribersAfter', 'validateNumberOfSubscribersAfter', 'skipOnEmpty' => FALSE ],
			[ 'discountFlag', 'validateDiscountFlag', 'skipOnEmpty' => FALSE ],
		];
	}

	public function defaultTeacherId( $model ) {
		return Yii::$app->user->id ?? NULL;
	}

	public function validateTeacherId( $attribute, $params, $validator ) {
		$teacher = Yii::$app->user->id === $this->teacherId ?
			Yii::$app->user->identity : User::findOne( $this->teacherId );
		if( $teacher && $teacher->hasRole( 'teacher' ) ) {
			$this->teacher = $teacher;
		}
		else {
			$validator->addError( $this, $attribute, 'Не удалось идентифицировать учителя' );
		}
	}

	public function validateFinish( $attribute, $params, $validator ) {
		if( $this->finish <= $this->yyyymmdd ) {
			$validator->addError( $this, $attribute, 'Атрибут «{attribute}» должен содержать дату в будущем' );
		}
	}

	public function validateClasses( $attribute, $params, $validator ) {
		$classes = $this->teacher->classes ?? [];
		if( empty( $classes ) ) {
			$validator->addError( $this, $attribute, 'Классы не найдены' );
		}
		foreach( $classes as &$class ) {
			unset( $class['status'], $class['umkId'], $class['isRoot'] );
		}
		$this->classes = &$classes;
	}

	public function validateStudents( $attribute, $params, $validator ) {
		// Сюда приходит список id учащихся, а выходит список учащихся с нужными свойствами
		if( !is_array( $this->$attribute ) ) {
			$validator->addError( $this, $attribute, 'Некорректный формат атрибута «{attribute}»' );
			return;
		}
		$studentsIn = $this->$attribute;
		$studentsOut = [];
		foreach( $this->classes as &$class ) {
			$class['selectedToSubscribe'] = 0;
			$class['eligibleToSubscribe'] = 0;
			$class['numberOfSubscribersBefore'] = 0;
			$class['numberOfSubscribersAfter'] = 0;
			foreach( $class['students'] as &$student ) {
				// флаги для студента: по умолчанию
				$student['currentlySubscribed'] = FALSE;
				$student['selectedToSubscribe'] = in_array( $student['id'], $studentsIn );
				$student['eligibleToSubscribe'] = FALSE;
//				$student['subs'] = [ 'cur' => NULL, 'new' => NULL ];
				// текущая подписка
				$student['subs'] = [
					'cur' => [
						'finish' => $student['subscription'],
						'daysLeft' => $student['subscription'] ? (int) date_diff(
							date_create( $this->yyyymmdd ), date_create( $student['subscription'] )
						)->format( '%R%a' ) : -1
					]
				];
				unset( $student['subscription'] );
				$student['currentlySubscribed'] = ($student['subs']['cur']['daysLeft'] >= 0);
				if( $student['selectedToSubscribe'] ) {
					// новая подписка
					$subs = new Subscription();
					$subs->user_id = $student['id'];
					$subs->finish = $this->finish;
					$subs->validate();
					$student['subs']['new'] =
						$subs->toArray( [ 'start', 'finish', 'duration', 'cost' ] ) +
						[ 'costDiscount' => $subs->cost * (1 - self::DISCOUNT) ];
					$student['eligibleToSubscribe'] =
						($student['selectedToSubscribe'] && ($subs->duration > 0));
				}
				// рассчитаем флаги для класса
				$class['selectedToSubscribe'] += $student['selectedToSubscribe'];
				$class['eligibleToSubscribe'] += $student['eligibleToSubscribe'];
				$class['numberOfSubscribersBefore'] += $student['currentlySubscribed'];
				$class['numberOfSubscribersAfter'] +=
					($student['currentlySubscribed'] || $student['eligibleToSubscribe']);
				$studentsOut[] = &$student;
			}
			$class['numberOfStudents'] = count( $class['students'] );
		}
		$this->$attribute = &$studentsOut;
	}

	public function validateStart( $attribute, $params, $validator ) {
		$startList = array_filter( ArrayHelper::getColumn( $this->students, 'subs.new.start' ) );
		$start = min( $startList ? $startList : [ NULL ] );
		if( empty( $this->start ) ) {
			$this->$attribute = $start;
		}
		elseif( $this->start !== $start ) {
			$validator->addError( $this, $attribute, 'Неверно рассчитан атрибут «{attribute}»' );
		}
	}

	public function validateDurationTotal( $attribute, $params, $validator ) {
		$durationList = array_filter( ArrayHelper::getColumn( $this->students, 'subs.new.duration' ) );
		$durationTotal = array_sum( $durationList );
		if( empty( $this->durationTotal ) ) {
			$this->durationTotal = $durationTotal;
		}
		elseif( $this->durationTotal !== $durationTotal ) {
			$validator->addError( $this, $attribute, 'Неверно рассчитан атрибут «{attribute}»' );
		}
	}

	public function validateDurationAverage( $attribute, $params, $validator ) {
		$durationList = array_filter( ArrayHelper::getColumn( $this->students, 'subs.new.duration' ) );
		$total = array_sum( $durationList );
		$count = count( $durationList );
		$durationAverage = $count ? $total/$count : 0;
		if( empty( $this->durationAverage ) ) {
			$this->durationAverage = $durationAverage;
		}
		elseif( $this->durationAverage !== $durationAverage ) {
			$validator->addError( $this, $attribute, 'Неверно рассчитан атрибут «{attribute}»' );
		}
	}

	public function validatePricePerDayRegular( $attribute, $params, $validator ) {
		$pricePerDayRegular = round( Pricelist::calculateCost( $this->durationAverage ), 2 );
		if( empty( $this->pricePerDayRegular ) ) {
			$this->pricePerDayRegular = $pricePerDayRegular;
		}
		elseif( $this->pricePerDayRegular !== $pricePerDayRegular ) {
			$validator->addError( $this, $attribute, 'Неверно рассчитан атрибут «{attribute}»' );
		}
	}

	public function validatePricePerDayDiscount( $attribute, $params, $validator ) {
		$pricePerDayDiscount = round( $this->pricePerDayRegular * (1 - self::DISCOUNT), 2 );
		if( empty( $this->pricePerDayDiscount ) ) {
			$this->pricePerDayDiscount = $pricePerDayDiscount;
		}
		elseif( $this->pricePerDayDiscount !== $pricePerDayDiscount ) {
			$validator->addError( $this, $attribute, 'Неверно рассчитан атрибут «{attribute}»' );
		}
	}

	public function validatePriceRegular( $attribute, $params, $validator ) {
		$priceRegular = $this->durationTotal * $this->pricePerDayRegular;
		if( empty( $this->priceRegular ) ) {
			$this->priceRegular = $priceRegular;
		}
		elseif( $this->priceRegular !== $priceRegular ) {
			$validator->addError( $this, $attribute, 'Неверно рассчитан атрибут «{attribute}»' );
		}
	}

	public function validatePriceDiscount( $attribute, $params, $validator ) {
		$priceDiscount = $this->durationTotal * $this->pricePerDayDiscount;
		if( empty( $this->priceDiscount ) ) {
			$this->priceDiscount = $priceDiscount;
		}
		elseif( $this->priceDiscount !== $priceDiscounts ) {
			$validator->addError( $this, $attribute, 'Неверно рассчитан атрибут «{attribute}»' );
		}
	}

	public function validateNumberOfClasses( $attribute, $params, $validator ) {
		$this->$attribute = count( $this->classes );
	}

	public function validateNumberOfStudents( $attribute, $params, $validator ) {
		$this->$attribute = count( $this->students );
	}

	public function validateSelectedToSubscribe( $attribute, $params, $validator ) {
		$this->$attribute = array_sum( ArrayHelper::getColumn( $this->classes, 'selectedToSubscribe' ) );
	}

	public function validateEligibleToSubscribe( $attribute, $params, $validator ) {
		$this->$attribute = array_sum( ArrayHelper::getColumn( $this->classes, 'eligibleToSubscribe' ) );
	}

	public function validateNumberOfSubscribersBefore( $attribute, $params, $validator ) {
		$this->$attribute = array_sum( ArrayHelper::getColumn( $this->classes, 'currentlySubscribed' ) );
	}

	public function validateNumberOfSubscribersAfter( $attribute, $params, $validator ) {
		$this->$attribute = array_sum( ArrayHelper::getColumn( $this->classes, 'currentlySubscribed' ) );
	}

	public function validateDiscountFlag( $attribute, $params, $validator ) {
		$numberOfSubscribersAfter = array_sum( ArrayHelper::getColumn( $this->classes, 'numberOfSubscribersAfter' ) );
		$this->$attribute = ($numberOfSubscribersAfter >= self::DISCOUNT_MIN_STUDENTS);
	}

	public function getPricePerDayFinal() {
		return round( $this->discountFlag ? $this->pricePerDayDiscount : $this->pricePerDayRegular, 2 );
	}

	public function getPriceFinal() {
		return round( $this->discountFlag ? $this->priceDiscount : $this->priceRegular, 2 );
	}

	public function getPricePerDay() {
		return $this->pricePerDayFinal;
	}

	public function getPrice() {
		return $this->priceFinal;
	}

}
