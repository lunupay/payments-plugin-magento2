<?php
/**
 * Lunu payment method model
 *
 * @category    Lunu
 * @package     Lunu_Merchant
 * @author      Lunu Solutions GmbH
 * @copyright   Lunu Solutions GmbH (https://lunu.io)
 */
namespace Lunu\Merchant\Model;

use Magento\Directory\Model\CountryFactory;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Response\Http;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\UrlInterface;
use Magento\Payment\Helper\Data;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Payment\Model\Method\Logger;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;

DEFINE('LUNUPAYMENT_STATUS_PENDING', 'pending');
DEFINE('LUNUPAYMENT_STATUS_PAID', 'paid');
DEFINE('LUNUPAYMENT_STATUS_FAILED', 'failed');
DEFINE('LUNUPAYMENT_STATUS_EXPIRED', 'expired');
DEFINE('LUNUPAYMENT_STATUS_CANCELED', 'canceled');
DEFINE('LUNUPAYMENT_STATUS_AWAITING_CONFIRMATION', 'awaiting_payment_confirmation');
DEFINE('LUNUPAYMENT_SERVER_NAME', (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : ''));

$LUNUPAYMENT_DEV = strpos(LUNUPAYMENT_SERVER_NAME, 'dev.lunu.io') !== false;
$LUNUPAYMENT_RC = strpos(LUNUPAYMENT_SERVER_NAME, 'rc.lunu.io') !== false;
$LUNUPAYMENT_TESTING = strpos(LUNUPAYMENT_SERVER_NAME, 'testing.lunu.io') !== false;
$LUNUPAYMENT_SANDBOX = strpos(LUNUPAYMENT_SERVER_NAME, 'sandbox.lunu.io') !== false;

DEFINE('LUNUPAYMENT_PROCESSING_ENDPOINT', (
   $LUNUPAYMENT_DEV
        ? 'api.dev'
        : (
            $LUNUPAYMENT_RC
                ? 'api.rc'
                : (
                    $LUNUPAYMENT_TESTING
                        ? 'api.testing'
                        : (
                            $LUNUPAYMENT_SANDBOX
                              ? 'api.sandbox'
                              : 'api'
                        )
                )
        )
));

DEFINE('LUNUPAYMENT_WIDGET_VERSION', (
    $LUNUPAYMENT_DEV
        ? 'beta'
        : (
            $LUNUPAYMENT_RC
                ? 'rc'
                : (
                    $LUNUPAYMENT_TESTING
                        ? 'testing'
                        : ($LUNUPAYMENT_SANDBOX ? 'sandbox' : 'alpha')
                  )
        )
));

// DEFINE('LUNUPAYMENT_PROCESSING_ENDPOINT_SANDBOX', 'api.sandbox');
// DEFINE('LUNUPAYMENT_WIDGET_SANDBOX', 'sandbox');

DEFINE('LUNUPAYMENT_PROCESSING_ENDPOINT_SANDBOX', 'api.testing');
DEFINE('LUNUPAYMENT_WIDGET_SANDBOX', 'testing');

// DEFINE('LUNUPAYMENT_PROCESSING_ENDPOINT_SANDBOX', 'api.dev');
// DEFINE('LUNUPAYMENT_WIDGET_SANDBOX', 'beta');


class Payment extends AbstractMethod {
    const LUNU_DEFAULT_EXPIRES = 3600;
    const VERSION = '2.0.1';
    const USER_AGENT_ORIGIN = 'Lunu Magento_2.3.x';
    const CODE = 'lunu_merchant';

    protected $_code = 'lunu_merchant';
    protected $_isInitializeNeeded = true;
    protected $urlBuilder;
    protected $storeManager;
    protected $orderManagement;
    private $orderSender;

    public $errorMessage = '';
    public $rowBody = '';

    public function __construct(
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        Data $paymentData,
        ScopeConfigInterface $scopeConfig,
        Logger $logger,
        UrlInterface $urlBuilder,
        StoreManagerInterface $storeManager,
        OrderManagementInterface $orderManagement,
        Order $order,
        OrderSender $orderSender,
        array $data = [],
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data
        );

        $this->urlBuilder = $urlBuilder;
        $this->storeManager = $storeManager;
        $this->orderManagement = $orderManagement;
        $this->order = $order;
        $this->orderSender = $orderSender;
    }

    public function lunu_log($message = '', $data = null) {
        $this->errorMessage = $message;
//        if (!$this->getConfigData('lunu_gift_enabled')) {
//            return;
//        }
        ob_start();
        var_dump($data);
        file_put_contents(
            dirname(dirname(dirname(dirname(dirname(dirname(__FILE__)))))) . '/var/log/lunu_log.txt',
            date('Y-m-d H:i:s') . ' ' . $message . ' ' . ob_get_clean() . PHP_EOL,
            FILE_APPEND
        );
    }

    public function request(
        $route = '',
        $data = array(),
        $headers = array()
    ) {
        $sandbox = $this->getConfigData('test');
        $app_id = $this->getConfigData($sandbox ? 'sandbox_app_id' : 'app_id');
        $api_secret = $this->getConfigData($sandbox ? 'sandbox_api_secret' : 'api_secret');

        # Check if credentials was passed
        if (empty($app_id) || empty($api_secret)) {
            $this->lunu_log('Auth token missing');
            return null;
        }

        $url = 'https://' . (
            $sandbox
                ? LUNUPAYMENT_PROCESSING_ENDPOINT_SANDBOX
                : LUNUPAYMENT_PROCESSING_ENDPOINT
        ) . '.lunu.io/api/v1/payments/' . $route;

        $curl = curl_init($url);
        curl_setopt_array($curl, array(
            CURLOPT_POST => 1,
            CURLOPT_RETURNTRANSFER => 1
        ));
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($curl, CURLOPT_HTTPHEADER, array_merge(array(
            'Authorization: Basic ' . base64_encode($app_id . ':' . $api_secret)
        ), $headers));
        curl_setopt($curl, CURLOPT_USERAGENT, self::USER_AGENT_ORIGIN . ' v' . self::VERSION);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

        $raw_response = $this->rowBody = curl_exec($curl);
        $decoded_response = json_decode($raw_response, true);
        $response = $decoded_response ? $decoded_response : $raw_response;
        $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        unset($data['email']);

        if ($http_status === 200 && !empty($response['response'])) {
            $this->lunu_log('Request', array(
                'url' => $url,
                'request_data' => $data,
                'status' => $http_status,
                'body' => $raw_response
            ));
            return $response['response'];
        }

        $this->lunu_log('Error of request to the Lunu processing service', array(
            'url' => $url,
            'request_data' => $data,
            'status' => $http_status,
            'body' => $raw_response
        ));
        return null;
    }


    public function getLunuRequest(Order $order) {
        $order->setCanSendNewEmailFlag(false);

        $shop_order_id = $order->getIncrementId();
        $description = [
            'Order #' . $shop_order_id
        ];
        foreach ($order->getAllItems() as $item) {
            $description[] = number_format($item->getQtyOrdered(), 0) . ' × ' . $item->getName();
        }

        $urlBuilder =  $this->urlBuilder;

        $payment_timeout = intval(trim($this->getConfigData('payment_timeout') ?? ''));

        if ($payment_timeout < 1) {
            $payment_timeout = self::LUNU_DEFAULT_EXPIRES;
        }

        $time = time();

//        echo implode(" ", array(
//            'email' => $order->getCustomerEmail(),
//            'shop_order_id' => $shop_order_id,
//            'callback_url' => $urlBuilder->getUrl('lunu/payment/callback'),
//            'amount' => '' . floatval($order->getGrandTotal()),
//            'fiat_code' => '' . $order->getOrderCurrencyCode(),
//            'amount_of_shipping' => '' . floatval($order->getShippingInclTax()),
//            'description' => join(', ', $description),
//            'expires' => date("c", $time + $payment_timeout),
//        ));

        $lunu_payment = $this->request('create', array(
            'email' => $order->getCustomerEmail(),
            'shop_order_id' => $shop_order_id,
            'callback_url' => $urlBuilder->getUrl('lunu/payment/callback'),
            'amount' => '' . floatval($order->getGrandTotal()),
            'fiat_code' => '' . $order->getOrderCurrencyCode(),
            'amount_of_shipping' => '' . floatval($order->getShippingInclTax()),
            'description' => join(', ', $description),
            'expires' => date("c", $time + $payment_timeout),
        ), array(
            'Idempotence-Key: magento_' . $time . '_' . $shop_order_id,
            'Content-Type: application/json'
        ));

        if (empty($lunu_payment)) {
            return null;
        }


        $lunu_payment_id = $lunu_payment['id'];
        $confirmation_token = $lunu_payment['confirmation_token'];
        if (empty($lunu_payment_id) || empty($confirmation_token)) {
            $this->lunu_log('Invalid callback data', array(
                'payment' => $lunu_payment
            ));
            return null;
        }

        $payment = $order->getPayment();
        $payment->setAdditionalInformation('lunu_payment_id', $lunu_payment_id);
        $payment->setAdditionalInformation('lunu_payment_status', LUNUPAYMENT_STATUS_PENDING);
        $payment->save();


        return array(
            'status' => true,
            'payment_url' => 'https://widget.lunu.io/' . (
                $this->getConfigData('test')
                    ? LUNUPAYMENT_WIDGET_SANDBOX
                    : LUNUPAYMENT_WIDGET_VERSION
            ) . '/#/?' . http_build_query(array(
                'action' => 'select',
                'token' => $confirmation_token,
                'success' => $urlBuilder->getUrl('checkout/onepage/success'),
                'cancel' => $urlBuilder->getUrl('lunu/payment/cancelOrder'),
                'enableLunuGift' => $this->getConfigData('lunu_gift_enabled')
            ))
        );
    }

    public function validateLunuCallback() {
        $callback_json_input = file_get_contents('php://input');
        $callback_data = json_decode($callback_json_input, true);

        $this->lunu_log('Callback payment', $callback_data);

        if (!is_array($callback_data)) {
            $this->lunu_log('Empty callback data', array(
                'json' => $callback_json_input
            ));
            return;
        }

        $callback_payment_id = $callback_data['id'];
        if (empty($callback_payment_id)) {
            $this->lunu_log('Empty payment id in callback data', array(
                'callback_data' => $callback_data
            ));
            return;
        }

        if (empty($callback_data['shop_order_id'])) {
            $this->lunu_log('Parameter order_id is empty');
            return;
        }
        $shop_order_id = $callback_data['shop_order_id'];

        $callback_payment_status = strtolower($callback_data['status']);
        $valid_statuses = [
            LUNUPAYMENT_STATUS_AWAITING_CONFIRMATION,
            LUNUPAYMENT_STATUS_PAID,
            LUNUPAYMENT_STATUS_FAILED,
            LUNUPAYMENT_STATUS_EXPIRED,
            LUNUPAYMENT_STATUS_CANCELED
        ];
        if (!in_array($callback_payment_status, $valid_statuses)) {
            $this->lunu_log('Callback payment status is invalid', array(
                'callback_payment_status' => $callback_payment_status,
            ));
            return;
        }

        $order = $this->order->loadByIncrementId($shop_order_id);
        if (empty($order) || !$order->getIncrementId()) {
            $this->lunu_log('Magento Order #' . $shop_order_id . ' does not exist');
            return;
        }

        $payment = $order->getPayment();
        if (empty($payment)) {
            $this->lunu_log('Magento Payment of Order #' . $shop_order_id . ' does not exist');
            return;
        }

        $lunu_payment_current_status = $payment->getAdditionalInformation('lunu_payment_status');
        if (
            $callback_payment_status == $lunu_payment_current_status
            || $lunu_payment_current_status == LUNUPAYMENT_STATUS_PAID
            || $lunu_payment_current_status == LUNUPAYMENT_STATUS_FAILED
            || $lunu_payment_current_status == LUNUPAYMENT_STATUS_CANCELED
            || $lunu_payment_current_status == LUNUPAYMENT_STATUS_EXPIRED
        ) {
            $this->lunu_log('Lunu Payment completed already', array(
                'lunu_payment_current_status' => $lunu_payment_current_status,
                'callback_payment_status' => $callback_payment_status
            ));
            return;
        }

        $lunu_payment_id = $payment->getAdditionalInformation('lunu_payment_id');
        if (empty($lunu_payment_id) || empty($callback_payment_id) || $lunu_payment_id != $callback_payment_id) {
            $this->lunu_log('Order payment id does not match', array(
                'callback_payment_id' => $callback_payment_id,
                'payment_id' => $lunu_payment_id
            ));
            return;
        }

        $lunu_payment = $this->request('get/' . $callback_payment_id);

        $this->lunu_log('Checking payment', $lunu_payment);

        if (empty($lunu_payment)) {
            $this->lunu_log('Lunu Payment #' . $callback_payment_id . ' does not exist');
            return;
        }

        $lunu_payment_status = strtolower($lunu_payment['status']);
        if (!in_array($lunu_payment_status, $valid_statuses)) {
            $this->lunu_log('Lunu Payment status is invalid', array(
                'callback_payment_status' => $callback_payment_status,
                'lunu_payment_status' => $lunu_payment_status
            ));
            return;
        }

        $lunu_payment_amount = floatval($lunu_payment['amount']);
        $order_amount = floatval($order->getGrandTotal());

        if ($order_amount !== $lunu_payment_amount) {
            $this->lunu_log('Incorrect payment amount', array(
                'lunu_payment_amount' => $lunu_payment_amount,
                'order_amount' => $order_amount
            ));
            return;
        }

        $payment->setAdditionalInformation('lunu_payment_status', $lunu_payment_status);
        $config = $order->getConfig();

        if ($lunu_payment_status == LUNUPAYMENT_STATUS_AWAITING_CONFIRMATION) {
            $order->setState(Order::STATE_PAYMENT_REVIEW);
            $order->setStatus($config->getStateDefaultStatus(Order::STATE_PAYMENT_REVIEW));
            $order->save();
        } elseif ($lunu_payment_status == LUNUPAYMENT_STATUS_PAID) {
            $order->setState(Order::STATE_COMPLETE);
            $order->setStatus($config->getStateDefaultStatus(Order::STATE_COMPLETE));
            $order->save();

            try {
              $this->orderSender->send($order);
            } catch (\Exception $e) {
  //            $this->logger->critical($e);
            }

        } elseif (in_array($lunu_payment_status, array(
            LUNUPAYMENT_STATUS_FAILED,
            LUNUPAYMENT_STATUS_EXPIRED,
            LUNUPAYMENT_STATUS_CANCELED
        ))) {
            $this->orderManagement->cancel($shop_order_id);
        }
    }
}
