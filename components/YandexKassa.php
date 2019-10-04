<?php
/**
 * Компонент для работы с ЯндексКассой
 */

namespace app\components;

use Yii;
use yii\base\BaseObject;
use yii\helpers\Json;
use YandexCheckout\Client;
use YandexCheckout\Model\NotificationEventType;
use YandexCheckout\Model\Notification\{NotificationSucceeded, NotificationCanceled, NotificationWaitingForCapture, NotificationRefundSucceeded};

class YandexKassa extends BaseObject {

	public $name;
	public $shopId;
	public $secretKey;
	public $error;

	public function createPayment( $data ) {
		$client = new Client();
		$client->setAuth( $this->shopId, $this->secretKey );
		$idempotenceKey = uniqid( NULL, TRUE );
		$payment = $client->createPayment( $data, $idempotenceKey );
		return $payment;
	}

	public function getPaymentInfo( $id ) {
		$client = new Client();
		$client->setAuth( $this->shopId, $this->secretKey );
		$payment = $client->getPaymentInfo( $id );
		return $payment;
	}

	public function getPaymentInfoByWebhook( $data = NULL ) {
		if( empty( $data ) ) {
			$data = Yii::$app->request->rawBody;
		}
		$hash = is_string( $data ) ? Json::decode( $data ) : $data;

		// Cоздадим объект класса уведомлений в зависимости от события
		try {
			switch( $hash['event'] ) {
				case NotificationEventType::PAYMENT_SUCCEEDED:
					$notification = new NotificationSucceeded( $hash );
					break;
				case NotificationEventType::PAYMENT_CANCELED:
					$notification = new NotificationCanceled( $hash );
					break;
				case NotificationEventType::REFUND_SUCCEEDED:
					$notification = new NotificationRefundSucceeded( $hash );
					break;
				case NotificationEventType::PAYMENT_WAITING_FOR_CAPTURE:
					$notification = new NotificationWaitingForCapture( $hash );
					break;
				default: // Ошибка
					$this->error = "Неизвестное событие: {$hash['event']}";
					return NULL;
			}
		}
		catch( Exception $e ) {
			$this->error = "Exception: {$e}";
			return NULL;
		}

		$payment = $notification->getObject();
		if( empty( $payment ) ) {
			$this->error = 'Не удалось получить объект платежа из уведомления [$payment = $notification->getObject()]';
			return NULL;
		}

		unset( $this->error );
		return $this->getPaymentInfo( $payment->id );
	}

}
