<?php
/**
 * Модель "Пользователь"
 */

namespace app\models;

use Yii;
use yii\base\NotSupportedException;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;
use yii\web\IdentityInterface;
use app\components\validators\{EtrimValidator, PhoneValidator};
use app\models\Group;
use app\models\BookRule;
use himiklab\yii2\recaptcha\ReCaptchaValidator;

/**
 * This is the model class for table "user".
 *
 * @property integer $id
 * @property string $hash
 * @property string $login
 * @property string $password
 * @property string $status
 * @property string $regdate
 * @property string $nameLast
 * @property string $nameFirst
 * @property string $nameMiddle
 * @property string $balance
 * @property string $email
 * @property string $phone
 * @property string $birthday
 * @property string $gender
 * @property string $subscription
 * @property integer $umkId
 * @property integer $grade
 *
 * @property ExerciseRun[] $exerciseRuns
 * @property ExerciseUser[] $exerciseUsers
 * @property Exercise[] $exercises
 * @property Group[] $groups
 * @property GuestbookVoice[] $guestbookVoices
 * @property GuestbookMessage[] $messages
 * @property OrderInvitation[] $orderInvitations
 * @property OrderInvitationUser[] $orderInvitationUsers
 * @property OrderInvitation[] $invitations
 * @property OrderSubscription[] $orderSubscriptions
 * @property Order[] $orders
 * @property Payment[] $payments
 * @property ReportUserRule[] $reportUserRules
 * @property UserGroup[] $userGroups
 * @property Group[] $groups0
 * @property UserRole[] $userRoles
 * @property Role[] $roles
 */

class User extends ActiveRecord implements IdentityInterface {

	public $reCaptcha; // google reCAPTCHA
	public $password2; // повторный пароль (при регистрации)
	public $acceptAgreement = true; // акцепт пользовательского соглашения (при регистрации)
	public $autoLogin; // автологин (при входе)

	public static function tableName() {
		return 'user';
	}

	const SCENARIO_LOGIN = 'login'; // вход в систему
	const SCENARIO_SIGNUP = 'signup'; // регистрация
	const SCENARIO_CHPASS = 'chpass'; // восстановление пароля
	const SCENARIO_PROFILE_EDIT = 'profile_edit'; // редактирование профиля пользователя
	const SCENARIO_PROFILE_LEARN = 'profile_learn'; // редактирование настроек обучения

    /*
     * Первым делом, введём константы для указания статуса, статический метод getStatuses
     * для получения их списка и метод getStatusName для получения имени статуса пользователя.
     * Эти методы пригодятся, например, при выводе пользователей в панели управления.
     */
	const STATUS_NEW = 'new';
	const STATUS_ON = 'on';
	const STATUS_OFF = 'off';

    public function getStatusName()
    {
        return ArrayHelper::getValue(self::getStatuses(), $this->status);
    }

    public static function getStatuses()
    {
        return [
            self::STATUS_NEW => 'Не подтвержден',
            self::STATUS_ON => 'Подтвержден',
            self::STATUS_OFF => 'Удален',
        ];
    }

    /*
     * Тем же макаром добавим пол пользователя.
     */
    const GENDER_NONE = null;
    const GENDER_MALE = 'male';
    const GENDER_FEMALE = 'female';

    public function getGenderName()
    {
        return ArrayHelper::getValue(self::getGenders(), $this->gender);
    }

    public static function getGenders()
    {
        return [
            self::GENDER_NONE => 'Не указано',
            self::GENDER_MALE => 'Мужской',
            self::GENDER_FEMALE => 'Женский',
        ];
    }

	public function scenarios() {
		$scenarios = parent::scenarios();
		// В сценарии 'default' уберём некоторые атрибуты из массовой загрузки
		foreach( $scenarios[self::SCENARIO_DEFAULT] as &$attribute ) {
			switch( $attribute ) {
				case 'hash':
				case 'status':
				case 'regdate':
				case 'balance':
				case 'subscription':
					$attribute = '!' . $attribute;
			}
		}
		$scenarios[self::SCENARIO_LOGIN] = [ 'login', 'password', 'autoLogin', 'reCaptcha' ];
		$scenarios[self::SCENARIO_SIGNUP] = [ 'email', 'password', 'password2', 'login', 'acceptAgreement', 'reCaptcha' ];
		$scenarios[self::SCENARIO_CHPASS] = [ 'password', 'password2', 'reCaptcha' ];
		$scenarios[self::SCENARIO_PROFILE_EDIT] = [
			'nameLast', 'nameFirst', 'nameMiddle', 'email', 'phone', 'birthday', 'gender', 'umkId', 'grade'
		];
		$scenarios[self::SCENARIO_PROFILE_LEARN] = [ 'umkId', 'grade' ];
		return $scenarios;
	}

	static $fieldRange = [ // Допустимые значения полей типа set/enum
		'status' => [ 'new', 'on', 'off' ],
		'gender' => [ 'male', 'female' ],
		'grade' => [ 0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12 ]
	];

	public function rules() {
		return [
//			[ 'id', 'integer', 'min' => 0 ],
//			[ 'id', 'filter', 'filter' => 'intval', 'skipOnEmpty' => TRUE ],
			[ 'hash', 'default', 'value' => NULL ],
			[ 'hash', 'string', 'max' => 50 ],
			[ 'hash', 'match', 'pattern' => '/^\S+$/' ],
			[ 'hash', 'unique' ],
			[ 'login', 'required', 'message' => 'Логин нужен. Без логина никуда.' ],
			[ 'login', 'trim' ],
			[ 'login', 'string', 'max' => 50 ],
			[ 'login', 'unique',
				'on' => [ self::SCENARIO_SIGNUP ],
				'message' => 'Логин "{value}" уже занят. Выберите другой.' ],
			[ 'login', 'exist',
				'on' => [ self::SCENARIO_LOGIN ],
				'message' => 'Неверный логин. Проверьте правильность ввода.' ],
			[ 'password', 'required', 'message' => 'Пароль нужен. Без пароля никуда.' ],
			[ 'password', 'string', 'max' => 50 ],
			[ 'password', 'match', 'pattern' => '/^\S{6,50}$/',
				'on' => [ self::SCENARIO_SIGNUP, self::SCENARIO_CHPASS ],
				'message' => 'Пароль должен содержать не менее 6-ти символов (без пробелов).' ],
			[ 'password', 'exist', 'targetAttribute' => [ 'login', 'password' ],
				'on' => [ self::SCENARIO_LOGIN ],
				'message' => 'Неверный пароль. Повторите попытку или нажмите на ссылку «Забыли пароль?», чтобы сбросить его.' ],
			[ 'status', 'default', 'value' => 'new' ],
			[ 'status', 'in', 'range' => self::$fieldRange['status'] ],
			[ 'regdate', 'datetime', 'format' => 'yyyy-MM-dd HH:mm:ss' ],
			[ [ 'nameLast', 'nameFirst', 'nameMiddle' ], EtrimValidator::className() ],
			[ [ 'nameLast', 'nameFirst', 'nameMiddle' ], 'string', 'max' => 25 ],
			[ 'balance', 'number' ],
			[ 'email', 'required',
				'on' => [ self::SCENARIO_SIGNUP ],
				'message' => 'Эл. почта нужна. Без почты никуда.' ],
			[ 'email', 'trim' ],
			[ 'email', 'email', 'message' => 'Указан некорректный адрес эл. почты. Пожалуйста, исправьте.' ],
			[ 'phone', EtrimValidator::className() ],
			[ 'phone', PhoneValidator::className() ],
			[ 'birthday', 'date', 'format' => 'yyyy-MM-dd' ],
			[ 'gender', 'in', 'range' => self::$fieldRange['gender'] ],
			[ 'subscription', 'date', 'format' => 'yyyy-MM-dd' ],
			[ 'grade', 'required', 'message' => 'Необходимо выбрать класс обучения.',
				'on' => [ self::SCENARIO_PROFILE_LEARN ] ],
			[ 'grade', 'filter', 'filter' => 'intval', 'skipOnEmpty' => TRUE ],
			[ 'grade', 'in', 'range' => self::$fieldRange['grade'] ],
			[ 'umkId', 'required', 'message' => 'Необходимо выбрать программу обучения.',
				'on' => [ self::SCENARIO_PROFILE_LEARN ] ],
			[ 'umkId', 'filter', 'filter' => 'intval', 'skipOnEmpty' => TRUE ],
			[ 'umkId', 'filter',
				'on' => [ self::SCENARIO_PROFILE_LEARN ],
				'filter' => function($value) { return Yii::$app->params['umkDefault']['1-4'] ?? $value; },
				'when' => function($model) { return ($model->umkId === 0) && ($model->grade <= 4); } ],
			[ 'umkId', 'filter',
				'on' => [ self::SCENARIO_PROFILE_LEARN ],
				'filter' => function($value) { return Yii::$app->params['umkDefault']['5-11'] ?? $value; },
				'when' => function($model) { return ($model->umkId === 0) && ($model->grade >= 5); } ],
			[ 'umkId', 'exist',
				'targetClass' => BookRule::className(), 'targetAttribute' => [ 'umkId' => 'id' ],
				'filter' => function($query) { return $query->where( [ 'type' => 'УМК' ] ); }
			],
			[ 'umkId', 'validateUmkGradeCompability',
				'when' => function($model) { return !empty($model->grade); } ],
			[ 'reCaptcha', ReCaptchaValidator::className(),
				'on' => [ self::SCENARIO_LOGIN, self::SCENARIO_SIGNUP, self::SCENARIO_CHPASS ],
				'message' => 'Хм... Google считает, что вы - робот. Попробуйте ещё раз.',
				'when' => function() { return Yii::$app->params['user']['captchaVerification'] ?? FALSE; } ],
			/*
			 * Сценарий SCENARIO_SIGNUP/SCENARIO_CHPASS: дополнительные правила валидации
			 */
			[ 'password2', 'required',
				'on' => [ self::SCENARIO_SIGNUP, self::SCENARIO_CHPASS ],
				'message' => 'Повторите введённый выше пароль.',
				'when' => function( $model ) { return !empty($model->password); },
				// это можно уточнить: сделать проверку, только если "password" прошел проверку
				'whenClient' => "function (attribute, value) { return $('input#user-password').val().match(/\S+/); }" ],
			[ 'password2', 'compare', 'compareAttribute' => 'password',
				'on' => [ self::SCENARIO_SIGNUP, self::SCENARIO_CHPASS ],
				'message' => 'Введённые пароли не совпадают. Пожалуйста, исправьте.' ],
			[ 'acceptAgreement', 'default', 'value' => FALSE,
				'on' => [ self::SCENARIO_SIGNUP ] ],
			[ 'acceptAgreement', 'required', 'requiredValue' => TRUE,
				'on' => [ self::SCENARIO_SIGNUP ],
				'message' => 'Для регистрации необходимо принять условия пользовательского соглашения.' ],
			/*
			 * Сценарий SCENARIO_LOGIN: дополнительные правила валидации
			 */
			[ 'autoLogin', 'default', 'value' => FALSE,
				'on' => [ self::SCENARIO_LOGIN ] ],
		];
	}

	public function attributeLabels() {
		return [
			'id' => 'ID пользователя',
			'hash' => 'уникальный код пользователя',
			'login' => 'логин',
			'password' => 'пароль',
			'status' => 'статус',
			'regdate' => 'дата и время регистрации',
			'nameLast' => 'фамилия',
			'nameFirst' => 'имя',
			'nameMiddle' => 'отчество',
			'balance' => 'баланс',
			'email' => 'электронная почта',
			'phone' => 'телефон',
			'birthday' => 'дата рождения',
			'gender' => 'пол',
			'subscription' => 'дата окончания подписки',
			'umkId' => 'ID УМК',
			'grade' => 'класс',
		];
	}

	public function extraFields() {
		$extraFields = parent::extraFields();
		$extraFields['name'] = function( $model ) { return $model->name; };
		$extraFields[] = 'groups';
		return $extraFields;
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getBookRule() {
		return $this->hasOne( BookRule::className(), [ 'id' => 'umkId' ] );
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getExerciseRuns() {
		return $this->hasMany( ExerciseRun::className(), [ 'user_id' => 'id' ] );
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getExerciseUsers() {
		return $this->hasMany( ExerciseUser::className(), [ 'user_id' => 'id' ] );
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getExercises() {
		return $this->hasMany( Exercise::className(), [ 'id' => 'exercise_id' ] )->viaTable( 'exercise_user', [ 'user_id' => 'id' ] );
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getGuestbookVoices() {
		return $this->hasMany( GuestbookVoice::className(), [ 'user_id' => 'id' ] );
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getGuestbookMessages() {
		return $this->hasMany( GuestbookMessage::className(), [ 'id' => 'message_id' ] )->viaTable( 'guestbook_voice', [ 'user_id' => 'id' ] );
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getPayments() {
		return $this->hasMany( Payment::className(), [ 'user_id' => 'id' ] );
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getReportUserRules() {
		return $this->hasMany( ReportUserRule::className(), [ 'user_id' => 'id' ] );
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getGroups() {
		return $this->hasMany( Group::className(), [ 'user_id' => 'id' ] );
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getUserGroups() {
		return $this->hasMany( UserGroup::className(), [ 'user_id' => 'id' ] );
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getGroups0() {
		return $this->hasMany( Group::className(), [ 'id' => 'group_id' ] )->viaTable( 'user_group', [ 'user_id' => 'id' ] );
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getOrderInvitationUsers() {
		return $this->hasMany( OrderInvitationUser::className(), [ 'user_id' => 'id' ] );
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getOrderInvitations() {
		return $this->hasMany( OrderInvitation::className(), [ 'id' => 'invitation_id' ] )->viaTable( 'order_invitation_user', [ 'user_id' => 'id' ] );
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getOrderSubscriptions() {
		return $this->hasMany( OrderSubscription::className(), [ 'user_id' => 'id' ] );
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getOrders() {
		return $this->hasMany( Order::className(), [ 'id' => 'order_id' ] )->viaTable( 'order_subscription', [ 'user_id' => 'id' ] );
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getUserRoles() {
		return $this->hasMany( UserRole::className(), [ 'user_id' => 'id' ] );
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getRoles() {
		return $this->hasMany( Role::className(), [ 'id' => 'role_id' ] )
				->viaTable( 'user_role', [ 'user_id' => 'id' ] )
				->orderBy( [ 'role.sort' => SORT_ASC ] );
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getSupervisor() { // пользователь, который зарегистрировал данного пользователя
		$groups = $this->groups0;
		return $groups ? $groups[0]->user : NULL;
	}

	/* ==============================
	 * IdentityInterface
	 * ==============================
	 */
	public static function findIdentity( $id ) {
		return static::findOne( [ 'id' => $id, 'status' => [ 'on', 'new' ] ] );
	}

	// return static::findOne(['access_token' => $token]);
	public static function findIdentityByAccessToken( $token, $type = null ) {
		throw new NotSupportedException( '"findIdentityByAccessToken" is not implemented.' );
	}

	public function getId() {
		return $this->id;
	}

	public function getAuthKey() {
		//return $this->token_auth;
		return null;
	}

	public function validateAuthKey( $authKey ) {
		return $this->getAuthKey() === $authKey;
	}

	/**
	 * IdentityInterface
	 */
	public static function findByUsername( $login ) {
		return static::findOne( [ 'login' => $login ] );
	}

	public static function findByPasswordResetToken( $token ) {
		if( !static::isPasswordResetTokenValid( $token ) ) {
			return null;
		}
		return static::findOne( [ 'password_reset_token' => $token ] );
	}

	public static function isPasswordResetTokenValid( $token ) {
		if( empty( $token ) ) {
			return false;
		}

		$timestamp = (int) substr( $token, strrpos( $token, '_' ) + 1 );
		$expire = Yii::$app->params['user.passwordResetTokenExpire'];
		return $timestamp + $expire >= time();
	}

	public function validatePassword( $password ) {
		return $password == $this->password;
	}

	public function setPassword( $password ) {
		$this->password = Yii::$app->security->generatePasswordHash( $password );
	}

	public function generateAuthKey() {
		//$this->token_auth = Yii::$app->security->generateRandomString();
	}

	/**
	 * Проверяет наличие роли у пользователя
	 * @param string $role
	 * @return boolean
	 */
	public function hasRole( $role ) {
		return in_array( $role, ArrayHelper::getColumn( $this->roles, 'roleEn' ) );
	}

//	public static function isRole( $role ) { // Проверяет наличие роли у пользователя (устаревшая)
//		return Yii::$app->user->identity->hasRole( $role );
//	}

	/**
	 * Выбирает первую из списка ролей пользователя (чаще всего будет только одна)
	 * @return string
	 */
	public function getPrimaryRole() {
		return $this->roles[0]->roleEn ?? NULL;
	}

	/**
	 * Генерирует уникальный хэш пользователя
	 * @return string
	 */
	public function generateHash() {
		return uniqid( NULL, TRUE );
	}

	/**
	 * Генерирует случайный пароль пользователя
	 * @return string
	 */
	public function generatePassword() {
		return sprintf( "%08d", random_int( 0, 99999999 ) );
	}

	/**
	 * Генерирует дату окончания промо-подписки
	 * @return date
	 */
	public function generatePromoSubscription() {
		$promoDays = Yii::$app->params['subscription']['promoDays'] ?? 15;
		return date( 'Y-m-d', time() + $promoDays * 24 * 60 * 60 );
	}

	/**
	 * @return \yii\db\ActiveQuery
	 */
	public function getLogVisit() {
		return $this->hasMany( LogVisit::className(), [ 'user_id' => 'id' ] );
	}

	public function getLastVisit() {
		return LogVisit::lastVisit( $this->id );
	}

	public function getPrevVisit() {
		return LogVisit::prevVisit( $this->id );
	}

	public function getReferrerLink() {
		return Yii::$app->params['main']['site'] . '/?ref=' . $this->id;
	}

	/**
	 * Определяет, имеет ли текущий пользователь доступ к пользователю $userId.
	 *
	 * Список параметров:
	 * <userId> - идентификатор юзера.
	 *
	 * Возвращаемые значения:
	 * Возвращает TRUE, если юзер обладает данной властью.
	 * Иначе FALSE.
	 *
	 * Примечание:
	 * Доступ к информации пользователя имеет:
	 *   1. Он сам
	 *   2. Его учитель
	 *   3. Администратор
	 */
	public static function isAccessToUser( $userId ) {
		$user = Yii::$app->user->identity;
		if( $userId == $user->id or User::isTeacher( $userId ) or $user->hasRole( 'admin' ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Определяет, является ли юзер учителем по отношению к другому юзеру
	 *
	 * Список параметров:
	 * <userId> - идентификатор юзера (ученика).
	 *
	 * Возвращаемые значения:
	 * Возвращает TRUE, если юзер является учителем по отношению к userId.
	 * Иначе FALSE.
	 */
	public static function isTeacher( $userId ) {
		$group = Group::find()
			->leftJoin( 'user_group', 'user_group.group_id = group.id' )
			->where( [
				'group.user_id' => Yii::$app->user->identity->id,
				'user_group.user_id' => $userId
			] )
			->limit( 1 )
			->one();
		return isset( $group );
	}

	public function isSubscriber(){
		return ($this->subscription >= date('Y-m-d'));
	}

    /**
	 * Возвращает контакт для использования в ЯндексКассе.
	 * В случае, если учащийся был зарегистрировал учителем/родителем,
	 * возвращает контакт вышестоящего.
	 *
	 * @return string email/phone
	 */
	public function getContact() {
		$contact = '';
		if( filter_var( $this->login, FILTER_VALIDATE_EMAIL ) ) {
			$contact = $this->login;
		}
		elseif( filter_var( $this->email, FILTER_VALIDATE_EMAIL ) ) {
			$contact = $this->email;
		}
		elseif($this->phone != '') {
			$clear = preg_replace("/\D/", '', $this->phone);
			if(strlen($clear) == 10){
				$contact = '+7' . $clear;
			}
			elseif(strlen($clear) == 11){
				$contact = '+' . $clear;
			}
		}
		if($contact == '') {
			$parent = (new \yii\db\Query )
				->select([
					'user.login',
					'user.email',
					'user.phone'
				])
				->from( 'user' )
				->innerJoin( 'group', 'group.user_id = user.id' )
				->innerJoin( 'user_group', 'user_group.group_id = group.id' )
				->where( [ 'user_group.user_id' => $this->id ] )
				->one();
			if(isset($parent)){
				if( filter_var( $parent['login'], FILTER_VALIDATE_EMAIL ) ) {
					$contact = $parent['login'];
				}
				elseif( filter_var( $parent['email'], FILTER_VALIDATE_EMAIL ) ) {
					$contact = $parent['email'];
				}
				elseif($parent['phone'] != '') {
					$clear = preg_replace("/\D/", '', $parent['phone']);
					if(strlen($clear) == 10){
						$contact = '+7' . $clear;
					}
					elseif(strlen($clear) == 11){
						$contact = '+' . $clear;
					}
				}
			}

			if($contact == '') {
				$contact = \Yii::$app->params['contact']['email'];
			}
		}
		return $contact;
	}

	public function afterSave( $insert, $changedAttributes ) {
		parent::afterSave( $insert, $changedAttributes );
		if( $insert ) {
			$group = new Group();
			$group->name = "Учащиеся";
			$group->user_id = $this->id;
			$group->status = "on";
			$group->isRoot = "Y";
			$group->datetimeX = date( 'Y-m-d H:i:s' );
			$group->save();
		}

		if( isset( $changedAttributes['umkId'] ) or isset( $changedAttributes['grade'] ) ) {
			$autotrainer = AutotrainerSetting::find()
				->where( [
					'userFrom' => $this->id,
					'userTo' => $this->id,
				] )
				->one();
			if( !isset( $autotrainer ) ) {
				$autotrainer = new AutotrainerSetting();
				$autotrainer->userFrom = $this->id;
				$autotrainer->userTo = $this->id;
				$autotrainer->status = 'stop';
				$autotrainer->days = null;
				$autotrainer->subjects = [ 0 ]; // орфография
				$autotrainer->note = null;
			}
			$autotrainer->grade = $this->grade;
			$autotrainer->umk = $this->umkId;
			$autotrainer->save();
		}
	}

	/*
	 * Возвращает список доступных для выбора классов
	 * @return array
	 */
	public static function gradeList() {
		return array_filter( self::$fieldRange['grade'], function($item) { return $item; } );
	}

	/*
	 * Проверка соответствия класса и УМК
	 * @param string $attribute Имя атрибута для валидации
	 * @param mixed $params Параметры валидации
	 */
	public function validateUmkGradeCompability( $attribute, $params ) {
		$umk = BookRule::findOne( $this->$attribute );
		if( empty( $umk ) ) {
			$this->addError( $attribute, "УМК с id={$this->$attribute} не найден." );
			return;
		}
		$umkGradeList = explode( ',', $umk->grade );
		if( in_array( $this->grade, $umkGradeList ) ) {
			return;
		}
		if( $this->grade >= $umkGradeList[0] && $umkGradeList[0] >= 5 ) {
			return;
		}
		if( $this->grade <= 4 && $umkGradeList[0] >= 5 ) {
			$errmsg = <<<ERRMSG
Выбран {$this->grade} класс обучения (начальная школа), а программа для средней школы.
Что-то из этого надо поправить.
ERRMSG;
		}
		elseif( $this->grade >= 5 && end( $umkGradeList ) <= 4 ) {
			$errmsg = <<<ERRMSG
Выбран {$this->grade} класс обучения (средняя школа), а программа для начальной школы.
Что-то из этого надо поправить.
ERRMSG;
		}
		else { // Это ошибка в базе?
			$errmsg = <<<ERRMSG
Проверка соответствия класса и программы обучения выдаёт ошибку.
Попробуйте поменять настройки обучения.
Если это не поможет - сообщите разработчикам.
ERRMSG;
		}
		$this->addError( $attribute, $errmsg );
	}


	public function afterFind(){
		if($this->subscription < date('Y-m-d')){
			// объем батарейки
			$energy = Yii::$app->params['energy']['limit'];

			// время восстановления единицы энергии
			$speed = Yii::$app->params['energy']['speed'];

			// если нужно подзарядить
			if($this->energy < $energy){
				// прошло секунд
				$timeDiff = time() - $this->energyUpdate;

				// восстановим накопленную энергию
				$energyPlus = floor($timeDiff / $speed);
				$this->energy = min($this->energy + $energyPlus, $energy);
				$this->energyUpdate += $energyPlus * $speed;
			}

			if($this->energy >= $energy){
				$this->energyUpdate = time();
			}

			$this->save();
		}
    }

	/**
	 * Возвращает ФИО пользователя
	 * @return string
	 */
	public function getName() {
		return preg_replace( '/\s+/', ' ',
			trim( implode( ' ', [ $this->nameLast, $this->nameFirst, $this->nameMiddle ] ) ) );
	}

	/**
	 * Возвращает учителя текущего пользователя
	 * Актуально для роли «студент».
	 * @return mixed
	 */
	public function getTeacher() {
		$teacher = $this->userGroups[0]->group->user ?? NULL;
		return ($this->hasRole( 'student' ) && $teacher && $teacher->hasRole( 'teacher' )) ? $teacher : NULL;
	}

	/**
	 * Возвращает родителя текущего пользователя
	 * Актуально для роли «студент».
	 * @return mixed
	 */
	public function getParent() {
		$parent = $this->userGroups[0]->group->user ?? NULL;
		return ($this->hasRole( 'student' ) && $parent && $parent->hasRole( 'parent' )) ? $parent : NULL;
	}

	/**
	 * Возвращает список учащихся, находящихся в субординации с текущим пользователем
	 * Актуально для ролей «родитель» и «учитель».
	 * @param mixed $status  Статус ребёнка. Можно передать скаляр 'on' или массив [ 'on', 'off' ]
	 * @return mixed
	 */
	public function getChildren( $status = 'on' ) {
		$children = [];
		switch( $this->primaryRole ) {
			case 'student':
				break;
			case 'parent':
				$children = [];
				$groups = $this->groups;
				foreach( $groups as $group ) {
					$linkUserGroup = $group->userGroups;
					foreach( $linkUserGroup as $link ) {
						$user = $link->user->toArray();
						$user['hash'] = $user['password'] = '*****';
						$children[] = $user;
					}
				}
				ArrayHelper::multisort( $chidren, [ 'nameLast', 'nameFirst', 'nameMiddle', 'id' ],
					[ SORT_ASC, SORT_ASC, SORT_ASC, SORT_ASC ] );
				break;
			case 'teacher':
			default:
		}
		// Отфильтруем по статусу, если надо
// Хорошо бы этот фильтр встроить в sql-запрос
		if( $status ) {
			$statusFilter = is_scalar( $status ) ? [ $status ] : $status;
			$children = array_filter( $children,
				function($child) use($statusFilter) { return in_array( $child['status'], $statusFilter ); }
			);
		}
		return $children ? $children : NULL;
	}

	/**
	 * Проверяет является ли ребёнок субординированным к пользователю
	 * @param mixed $ids
	 * @return boolean
	 */
	public function isMyChild( $ids = NULL ) {
		if( empty( $ids ) ) {
			return NULL;
		}
		// Преобразуем в массив
		$childrenIdsCheck = is_scalar( $ids ) ? [ $ids ] : $ids;
		// Выберем id всех детей
		$children = $this->children;
		$childrenIdsValid = ArrayHelper::getColumn( $children, 'id' );
		// Построим пересечение
		$childrenIdsIntersect = array_intersect( $childrenIdsCheck, $childrenIdsValid );
		// Проверим, что все дети попадают в пересечение и вернём результат
		return $childrenIdsCheck == $childrenIdsIntersect;
	}

	/**
	 * Возвращает список классов и учащихся субординированных к учителю
	 * @param type $status
	 * @return mixed
	 */
	public function getClasses( $status = 'on' ) {
		if( !$this->hasRole( 'teacher' ) ) {
			return NULL;
		}
		$data = $this->toArray(
			[
				'groups.id', 'groups.name', 'groups.isRoot', 'groups.status', 'groups.umkId', 'groups.grade',
//				'groups.users.nameLast', 'groups.users.nameFirst', 'groups.users.nameMiddle',
				'groups.users.id', 'groups.users.status', 'groups.users.subscription'
			],
			[
				'groups', 'groups.users', 'groups.users.name'
			]
		);
		// отфильтруем классы
		$classes = [];
		foreach( $data['groups'] as &$class ) {
			if( ($class['isRoot'] === 'N') && ($class['status'] === $status) ) {
				// отфильтруем учащихся
				$class['students'] = [];
				foreach( $class['users'] as &$student ) {
					if( $student['status'] === $status ) {
						$class['students'][] = &$student;
					}
				}
				unset( $class['users'], $class['isRoot'] );
				// отсортируем студентов
				usort( $class['students'], function($a, $b) {
					return strcmp( $a['name'], $b['name'] );
				} );
				$classes[] = &$class;
			}
		}
		// отсортируем классы
		usort( $classes, function($a, $b) {
			return $a['grade'] <=> $b['grade'] ?
				$a['grade'] <=> $b['grade'] : strcmp( $a['name'], $b['name'] );
		} );
		return $classes;
	}

}
