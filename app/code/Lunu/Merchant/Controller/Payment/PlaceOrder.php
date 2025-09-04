<?php
/**
 * Lunu PlaceOrder controller
 *
 * @category    Lunu
 * @package     Lunu_Merchant
 * @author      Lunu Solutions GmbH
 * @copyright   Lunu Solutions GmbH (https://lunu.io)
 */

namespace Lunu\Merchant\Controller\Payment;

use Lunu\Merchant\Model\Payment as LunuPayment;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Sales\Model\OrderFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;


class PlaceOrder extends Action {
    protected $orderFactory;
    protected $lunu_payment;
    protected $checkoutSession;
    protected $scopeConfig;

    protected $_eventManager;
    protected $quoteRepository;

    public function __construct(
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        Context $context,
        OrderFactory $orderFactory,
        Session $checkoutSession,
        LunuPayment $lunu_payment,
        ScopeConfigInterface $scopeConfig
    ) {
        parent::__construct($context);
        $this->quoteRepository = $quoteRepository;
        $this->_eventManager = $eventManager;
        $this->orderFactory = $orderFactory;
        $this->lunuPayment = $lunu_payment;
        $this->checkoutSession = $checkoutSession;
        $this->scopeConfig = $scopeConfig;
    }

    protected function _getCheckout() {
        return $this->_objectManager->get('Magento\Checkout\Model\Session');
    }

    public function execute() {
        $id = $this->checkoutSession->getLastOrderId();

        $order = $this->orderFactory->create()->load($id);
        $response = $this->getResponse();

        if (!$order->getIncrementId()) {
            $response->setBody(json_encode([
                'status' => false,
                'reason' => 'Order Not Found',
            ]));
            return;
        }

        $lunuPayment = $this->lunuPayment;
        $responseData = $lunuPayment->getLunuRequest($order);

        if (empty($responseData)) {
            $responseData = array(
                'success' => false,
                'reason' => $lunuPayment->errorMessage . "
" . $lunuPayment->rowBody
            );
        } else {
            /// Restores Cart
            $quoteRepository = $this->quoteRepository;
            $quote = $quoteRepository->get($order->getQuoteId());
            $quote->setIsActive(1);
            $quoteRepository->save($quote);
        }

        $response->setBody(json_encode($responseData));
    }
}
