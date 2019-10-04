<?php
/**
 * Регистрация и аутентификация пользователя, сброс и восстановление пароля.
 * https://gramotei.online/signup
 * https://gramotei.online/login
 *
 * Модель: ~/models/User.php
 */

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\{ForbiddenHttpException, NotFoundHttpException, UnprocessableEntityHttpException};
use app\models\{User, Role, Verification, LogLogin, LogSignup};

class UserController extends Controller {


	/**
	* @inheritdoc
	*/
	public function beforeAction($action)
	{
	   if ($action->id == 'login-by-socnet') {
		   $this->enableCsrfValidation = FALSE;
	   }

	   return parent::beforeAction($action);
	}

	/**
	 * Проводим пользователя в Личный кабинет
	 */
	public function goCabinet() {
		// Выберем identity
		$identity = Yii::$app->user->identity;
		// Гостей отправляем на страницу логина
		if( !$identity ) {
			return Yii::$app->user->loginRequired();
		}
		// Выберем роль пользователя
		$userRole = $identity->primaryRole;
		// Роли нет: отправим выбирать роль
		if( !$userRole ) {
			return $this->redirect( Yii::$app->params['cabinetHomePage']['default'] ?? [ '/select-role' ] );
		}
		// Определим URL кабинета
		$cabinetUrl = Yii::$app->params['cabinetHomePage'][$userRole] ?? NULL;
		// Кабинет есть: проводим в кабинет
		if( $cabinetUrl ) {
			return $this->redirect( $cabinetUrl );
		}
		// Роль есть, но для неё нет кабинета
		throw new UnprocessableEntityHttpException( "Ошибка входа: role={$userRole}." );
	}

	/**
	 * Отправим письмо для проверки email
	 * @param type $code - уникальный код
	 */
	public function sendVerificationEmail( $email, $subject, $view, $data ) {
		$mailer = Yii::$app->mailer;
		$mailer
			->compose( [ 'html' => "{$view}-html", 'text' => "{$view}-text" ], $data )
			->setFrom( [ 'mail@gramotei.online' => 'Грамотей.Онлайн' ] )
			->setTo( $email )
			->setSubject( $subject )
			->send();
	}

	/**
	 * Регистрация нового пользователя: Заполнение формы
	 */
	public function actionSignup() {
		// Аутентифицированного пользователя сразу проводим в кабинет
		if( Yii::$app->user->identity ) {
			return $this->goCabinet();
		}

		$user = new User( [ 'scenario' => User::SCENARIO_SIGNUP ] );
		$post = Yii::$app->request->post();

		// Первый вход - нет post
		if( !$user->load( $post ) ) {
			return $this->render( 'signup', [ 'model' => $user ] );
		}

		// Проверим валидность данных
		$user->login = $user->email;
		if( !$user->validate() ) {
			if( $user->hasErrors( 'reCaptcha' ) ) {
				$user->addErrors( [ 'email' => $user->getErrors( 'reCaptcha' ) ] );
				LogSignup::log( $user, 'wrong-captcha' );
			}
			elseif( $user->hasErrors( 'login' ) ) {
				$user->addErrors( [ 'email' => $user->getErrors( 'login' ) ] );
				LogSignup::log( $user, 'wrong-login' );
			}
			elseif( $user->hasErrors( 'password' ) ) {
				LogSignup::log( $user, 'wrong-password' );
			}
			return $this->render( 'signup', [ 'model' => $user ] );
		}

		// Доопределим атрибуты
		$user->hash = $user->generateHash();
		$user->status = 'new';
		$user->regdate = NULL;
		$user->balance = 0;
		$user->subscription = NULL;
		$user->referrerId = Yii::$app->session->get('referrerId', NULL);

		// Зарегистрируем пользователя
		if( !$user->save() ) {
			$user->addError( 'email', 'Кажется, что-то пошло не так. Повторите попытку регистрации.' );
			LogSignup::log( $user, 'unknown-error' );
			return $this->render( 'signup', [ 'model' => $user ] );
		}

		// Сформируем код для проверки email'а и отправим письмо
		$data = [ 'user_id' => $user->id, 'email' => $user->login, 'action' => 'signup' ];
		$code = Verification::open( $data );
		$this->sendVerificationEmail( $user->login, 'Грамотей.Онлайн: подтвердите свой email.', 'signup', [ 'verifyUrl' => '/signup-verify', 'code' => $code ] );
		LogSignup::log( $user, 'email-send' );

		// Отрисуем страницу с информацией о подтверждении email
		return $this->render( 'signup-verify', [ 'stage' => 'email-send', 'email' => $user->login ] );
	}

	/**
	 * Регистрация нового пользователя: Проверка кода подтверждения (email)
	 */
	public function actionSignupVerify( $code = NULL ) {
		// Аутентифицированного пользователя сразу проводим в кабинет
		if( Yii::$app->user->identity ) {
			return $this->goCabinet();
		}

		// Если кода нет - отправляем на логин
		if( empty( $code ) ) {
			return Yii::$app->user->loginRequired();
		}

		// Проверим корректность кода и получим данные
		$data = Verification::check( $code );
		if( !$data ) {
			throw new UnprocessableEntityHttpException( 'Ошибка: по указанному коду данные не найдены.' );
		}

		// Инициализируем identity
		$identity = User::findOne( $data['user_id'] );
		if( !$identity ) {
			throw new UnprocessableEntityHttpException( 'Ошибка: по указанному коду пользователь не найден.' );
		}

		// Статус 'on', отправим на /login
		if( $identity->status === 'on' ) {
			Verification::close( $code );
			return Yii::$app->user->loginRequired();
		}
		// Статус 'new' => установим в 'on' и доопределим атрибуты
		elseif( $identity->status === 'new' ) {
			$identity->status = 'on';
			$identity->regdate = NULL;
			$identity->subscription = $identity->generatePromoSubscription();
			$identity->update( TRUE, [ 'status', 'regdate', 'subscription' ] );
			Verification::close( $code );
			LogSignup::log( $identity, 'success' );
		}
		// Любой другой статус
		else {
			LogSignup::log( $identity, 'unknown-error' );
			throw new ForbiddenHttpException;
		}

		// Залогиним пользователя и отправим выбирать роль
		if( Yii::$app->user->login( $identity ) ) {
			LogLogin::log( $identity, 'success' );
			return $this->goCabinet();
		}

		// Yii::$app->user->login(...) вернул FALSE
		LogLogin::log( $identity, 'unknown-error' );
		throw new UnprocessableEntityHttpException( 'Ошибка проверки кода.' );
	}

	/**
	 * Регистрация нового пользователя: Повторная отправка письма для подтверждения email
	 */
	public function actionSignupResend() {
		// Аутентифицированного пользователя сразу проводим в кабинет
		if( Yii::$app->user->identity ) {
			return $this->goCabinet();
		}

		// Проверим наличие email
		$post = Yii::$app->request->post();
		$email = $post['email'] ?? NULL;
		if( !$email ) {
			throw new UnprocessableEntityHttpException;
		}

		// Найдём пользователя
		$user = User::findOne( [ 'login' => $email ] );
		if( !$user ) {
			throw new NotFoundHttpException( 'Пользователь не найден' );
		}

		// Проверим статус
		if( $user->status === 'new' ) {
			// OK
		}
		elseif( $user->status === 'on' ) {
			// отправим на логин
			return Yii::$app->user->loginRequired();
		}
		else {
			throw new ForbiddenHttpException;
		}

		// Сформируем код для проверки email'а и отправим письмо
		$data = [ 'user_id' => $user->id, 'email' => $user->login, 'action' => 'signup-resend' ];
		$code = Verification::open( $data );
		$this->sendVerificationEmail( $email, 'Грамотей.Онлайн: подтвердите свой email.', 'signup', [ 'verifyUrl' => '/signup-verify', 'code' => $code ] );

		LogSignup::log( $user, 'email-resend' );

		// Отрисуем страницу с информацией о подтверждении email
		return $this->render( 'signup-verify', [ 'stage' => 'email-resend', 'email' => $user->login ] );
	}

	/**
	 * Регистрация нового пользователя: Сообщение об отсутствии email
	 */
	public function actionSignupNoemail() {
		// Аутентифицированного пользователя сразу проводим в кабинет
		if( Yii::$app->user->identity ) {
			return $this->goCabinet();
		}

		// Проверим наличие email
		$post = Yii::$app->request->post();
		$email = $post['email'] ?? NULL;
		if( !$email ) {
			throw new UnprocessableEntityHttpException( 'Email не указан' );
		}

		// Найдём пользователя
		$user = User::findOne( [ 'login' => $email ] );
		if( !$user ) {
			throw new NotFoundHttpException( 'Пользователь не найден' );
		}

		// Проверим статус
		if( $user->status === 'new' ) {
			// OK
		}
		elseif( $user->status === 'on' ) {
			// отправим на логин
			return Yii::$app->user->loginRequired();
		}
		else {
			throw new ForbiddenHttpException( 'Ошибка статуса пользователя' );
		}

		// Отправим письмо с контактами пользователя администратору
		$mailer = Yii::$app->mailer;
		$mailer
			->compose( [ 'html' => 'signup-noemail-html' ], [ 'param' => $post ] )
			->setSubject( 'Signup: Пользователю не приходит письмо с кодом подтверждения' )
			->setTo( Yii::$app->params['contact']['email'] )
			->setFrom( Yii::$app->params['contact']['email'] )
			->setReplyTo( $email )
			->send();
		LogSignup::log( $user, 'email-none' );

		// Отрисуем страницу с информацией о приёме данных
		return $this->renderAjax( 'signup-error', [] );
	}

	/**
	 * Вход в личный кабинет
	 */
	public function actionLogin() {
		// Аутентифицированного пользователя сразу проводим в кабинет
		if( Yii::$app->user->identity ) {
			return $this->goCabinet();
		}

		$user = new User( [ 'scenario' => User::SCENARIO_LOGIN ] );

		// Первый вход - нет post
		if( !$user->load( Yii::$app->request->post() ) ) {
			return $this->render( 'login', [ 'model' => $user ] );
		}

		// Проверим валидность данных
		if( !$user->validate() ) {
			if( $user->hasErrors( 'reCaptcha' ) ) {
				$user->addErrors( [ 'login' => $user->getErrors( 'reCaptcha' ) ] );
				LogLogin::log( $user, 'wrong-captcha' );
			}
			elseif( $user->hasErrors( 'login' ) ) {
				LogLogin::log( $user, 'wrong-login' );
			}
			elseif( $user->hasErrors( 'password' ) ) {
				LogLogin::log( $user, 'wrong-password' );
			}
			else {
				LogLogin::log( $user, 'unknown-error' );
			}
			return $this->render( 'login', [ 'model' => $user ] );
		}

		// Инициализируем identity
		$identity = User::findOne( [ 'login' => $user->login, 'password' => $user->password ] );
		if( !$identity ) {
			LogLogin::log( $user, 'unknown-error' );
			$user->addError( 'login', 'Пользователь не найден. Проверьте введённый логин (email).' );
			return $this->render( 'login', [ 'model' => $user ] );
		}

		// Проверим статус
		if( $identity->status === 'on' ) {
			// ОК
		}
		elseif( $identity->status === 'new' ) {
			// отправим на подтверждение email
			LogLogin::log( $user, 'wrong-status' );
			return $this->render( 'signup-verify', [ 'stage' => 'email-wait', 'email' => $user->login ] );
		}
		else {
			LogLogin::log( $user, 'wrong-status' );
			throw new ForbiddenHttpException;
		}

		// Залогиним пользователя
		$autoLoginTimeout = intval( Yii::$app->params['user']['autoLoginTimeout'] ?? 7 * 24 * 60 * 60 ); // default: 1 неделя
		if( Yii::$app->user->login( $identity, $user->autoLogin ? $autoLoginTimeout : 0  ) ) {
			LogLogin::log( $user, 'success' );
			return $this->goCabinet();
		}

		// Yii::$app->user->login(...) вернул FALSE
		LogLogin::log( $user, 'unknown-error' );
		$user->addError( 'login', 'Кажется, что-то пошло не так. Повторите попытку входа.' );
		return $this->render( 'login', [ 'model' => $user ] );
	}

	/**
	 * Разлогиним пользователя, удалим сессию
	 */
	public function actionLogout() {
		Yii::$app->user->logout( TRUE );
		return $this->goHome();
	}

	/**
	 * После регистрации по ссылке из письма предлагает выбрать роль:
	 * учитель - ученик - родитель
	 */
	public function actionSelectRole() {
		// Не аутентифицированного пользователя отправляем на логин
		if( Yii::$app->user->isGuest ) {
			return Yii::$app->user->loginRequired();
		}

		// Выберем identity
		$identity = Yii::$app->user->identity;

		// Проверим статус
		if( $identity->status !== 'on' ) {
			throw new ForbiddenHttpException;
		}

		// Проверим наличие роли
		if( $identity->roles ) {
			$this->goCabinet();
		}

		// Выберем роль из POST
		$post = Yii::$app->request->post();
		$roleEn = $post['roleEn'] ?? NULL;

		// Первый вход - нет post/роли
		if( !$roleEn ) {
			return $this->render( 'select-role', [ 'roles' => Role::getPublicRoles() ] );
		}

		// Проверим входные данные
		$role = Role::findOne( [ 'roleEn' => $roleEn, 'type' => 'public', 'status' => 'on' ] );
		if( !$role ) {
			throw new NotFoundHttpException;
		}

		// Назначаем пользователю роль
		$identity->link( 'roles', $role );

		// Проводим в кабинет
		return $this->goCabinet();
	}

	/*
	 * Проверка email и отправка письма для восстановления пароля
	 */
	public function actionPasswordReset() {
		// Аутентифицированного пользователя сразу проводим в кабинет
		if( Yii::$app->user->identity ) {
			return $this->goCabinet();
		}

		$form = Yii::$app->request->post();

		// Первый вход: нет данных
		if( empty( $form['login'] ) ) {
			return $this->render( 'password-reset' );
		}
		$login = $form['login'];

		// Проверим похож ли логин на email
		$validator = new \yii\validators\EmailValidator();
		$email = $validator->validate( $login ) ? $login : NULL;

		// Логин указан, найдём пользователя
		$user = User::findOne( [ 'login' => $login ] );

		// Если по логину не нашли, попробуем по email
		if( empty( $user ) ) {
			if( $email ) {
				$users = User::findAll( [ 'email' => $email ] );
				if( empty( $users ) ) {
					// Нет пользователя с таким email'ом
					return $this->render( 'password-reset', [ 'login' => $login, 'success' => FALSE, 'error' => 'emailNotFound' ] );
				}
				elseif( count( $users ) > 1 ) {
					// Несколько пользователя с таким email'ом
					return $this->render( 'password-reset', [ 'login' => $login, 'success' => FALSE, 'error' => 'multipleEmails' ] );
				}
				$user = $users[0];
			}
			else {
				// Нет пользователя с таким логином
				return $this->render( 'password-reset', [ 'login' => $login, 'success' => FALSE, 'error' => 'emailNotFound' ] );
			}
		}

		// Пользователь найден и у него нет email: это учащийся, которого завёл родитель/учитель
		if( empty( $user->email ) ) {
			// Определим "старшего" и сообщим, что нужно делать
			$supervisor = $user->supervisor;
			return $this->render( 'password-reset',
				[ 'login' => $login, 'success' => FALSE, 'error' => 'noEmail', 'supervisor' => $supervisor ] );
		}

		// Пользователь найден и у него есть email
		// Сформируем код для сброса пароля и отправим письмо
		$data = [ 'user_id' => $user->id, 'email' => $user->email, 'action' => 'password-reset' ];
		$code = Verification::open( $data );
		$this->sendVerificationEmail( $email, 'Грамотей.Онлайн: сброс и восстановление пароля.', 'password-reset', [ 'verifyUrl' => '/password-renew', 'code' => $code ] );

		// Выведем сообщение об отправке
		return $this->render( 'password-reset', [ 'login' => $login, 'email' => $user->email, 'success' => TRUE ] );
	}

	/*
	 * Проверка кода и смена пароля
	 */
	public function actionPasswordRenew() {
		// Аутентифицированного пользователя сразу проводим в кабинет
		if( Yii::$app->user->identity ) {
			return $this->goCabinet();
		}

		$form = Yii::$app->request->isGet ? Yii::$app->request->get() : Yii::$app->request->post();
		$code = $form['code'] ?? NULL;

		// Если кода нет, сообщим об ошибке
		if( empty( $code ) ) {
			throw new UnprocessableEntityHttpException( 'Ошибка: не указан код доступа.' );
		}

		// Проверим корректность кода и получим данные
		$data = Verification::check( $code );
		if( empty( $data ) ) {
			throw new UnprocessableEntityHttpException( 'Ошибка: по указанному коду данные не найдены.' );
		}

		// Выберем пользователя
		$user = User::findOne( $data['user_id'] );
		if( !$user ) {
			throw new UnprocessableEntityHttpException( 'Ошибка: по указанному коду пользователь не найден.' );
		}
		$user->setScenario( User::SCENARIO_CHPASS );
		$user->password = NULL;
		if( $user->load( $form ) && $user->save() ) {
			Verification::close( $code );
			return $this->render( 'password-renew',	[ 'success' => TRUE ] );
		}

		if( $user->hasErrors( 'reCaptcha' ) ) {
			$user->addErrors( [ 'password' => $user->getErrors( 'reCaptcha' ) ] );
		}
		return $this->render( 'password-renew',	[ 'success' => FALSE, 'code' => $code, 'user' => $user ] );
	}

	/**
	 * Авторизация [и регистрация] пользователя на сайте через uLogin.
	 * @link https://ulogin.ru/help.php
	 * @return type
	 */
	public function actionLoginBySocnet() {
		// получаем данные пользователя
		$response = json_decode(
			file_get_contents( 'http://ulogin.ru/token.php?token=' . $_POST['token'] . '&host=' . $_SERVER['HTTP_HOST'] ), TRUE
		);

		// invalid token
		if( isset( $response['error'] ) ) {
			die( $response['error'] );
		}

		// bdate – дата рождения в формате DD.MM.YYYY
		if( isset( $response['bdate'] ) ) {
			$bdate = explode( '.', $response['bdate'] );

			// как-то раз попалась дата DD.MM
			if( count( $bdate ) == 3 ) {
				$response['bdate'] = $bdate[2] . '-' . $bdate[1] . '-' . $bdate[0];
			}
			else {
				$response['bdate'] = NULL;
			}
		}

		// уникальный логин в системе
		$response['login'] = $response['network'] . $response['uid'];

		// sex – пол пользователя (0 – не определен, 1 – женский, 2 – мужской)
		$gender = NULL;
		if( isset( $response['sex'] ) ) {
			if( $response['sex'] == 1 ) {
				$gender = 'female';
			}
			elseif( $response['sex'] == 2 ) {
				$gender = 'male';
			}
		}
		$response['sex'] = $gender;

		// если пользователь еще не зарегистрирован
		$user = User::findOne( [ 'login' => $response['login'] ] );
		if( !isset( $user ) ) {
			$user = new User();
			$user->hash = uniqid( NULL, TRUE );
			$user->login = $response['login'];
			$user->password = Yii::$app->security->generateRandomString( 16 );
			$user->status = User::STATUS_ON;
			$user->regdate = date( 'Y-m-d H:i:s' );
			$user->nameLast = $response['last_name'] ?? NULL;
			$user->nameFirst = $response['first_name'] ?? NULL;
			$user->email = $response['email'] ?? NULL;
			$user->birthday = $response['bdate'] ?? NULL;
			$user->gender = $response['sex'];
			$user->subscription = $user->generatePromoSubscription();
			$user->referrerId = Yii::$app->session->get( 'referrerId', NULL );
			$user->save();
		}

		// залогируем пользователя
		Yii::$app->user->login( $user );

		// отправляем его в личный кабинет
		return $this->goCabinet();
	}

	/*
	 * Превратим админа в пользователя
	 */
	public function actionAdminToUser( $user_id ) {
		// Проверим, что пользователь - админ
		$admin = Yii::$app->user->identity;
		if( !$admin || !$admin->hasRole( 'admin' ) ) {
			throw new NotFoundHttpException; // ForbiddenHttpException | UnprocessableEntityHttpException;
		}

		// Проверим, что пользователь-цель существует
		$user = User::findIdentity( $user_id );
		if( !$user ) {
			throw new NotFoundHttpException( 'Пользователь-цель не найден' );
		}

		Yii::$app->session->removeAll();  // очистим сессию
		Yii::$app->user->switchIdentity( $user );  // сменим пользователя
		Yii::$app->session['admin.id'] = $admin->id;  // сохраним id админа в сессии
		Yii::$app->session['admin.referrer'] = Yii::$app->request->referrer;  // сохраним url страницы, с которой идёт переход
		return $this->goCabinet();
	}

	/*
	 * Превратим пользователя в админа, если он раньше был админом
	 */
	public function actionUserToAdmin() {
		// Проверим, что пользователь залогинен
		$user = Yii::$app->user->identity;
		if( !$user ) {
			throw new NotFoundHttpException; // ForbiddenHttpException | UnprocessableEntityHttpException;
		}
		// Проверим, что пользователь был админом
		$admin_id = Yii::$app->session['admin.id'];
		$admin_referrer = Yii::$app->session['admin.referrer'];
		if( !$admin_id ) {
			throw new NotFoundHttpException; // ForbiddenHttpException | UnprocessableEntityHttpException;
		}
		$admin = User::findIdentity( $admin_id );
		if( !$admin ) {
			throw new NotFoundHttpException; // ForbiddenHttpException | UnprocessableEntityHttpException;
		}
		if( !$admin->hasRole( 'admin' ) ) {
			throw new NotFoundHttpException; // ForbiddenHttpException | UnprocessableEntityHttpException;
		}

		Yii::$app->session->removeAll(); // очистим сессию
		Yii::$app->user->switchIdentity( $admin ); // сменим пользователя
		return $this->redirect( $admin_referrer ); // вернём админа на страницу, с которой он пришёл
	}

}
