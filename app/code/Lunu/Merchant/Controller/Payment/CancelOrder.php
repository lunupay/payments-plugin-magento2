<?php

namespace Lunu\Merchant\Controller\Payment;

use Magento\Framework\App\Action\Action;

class CancelOrder extends Action {
    protected function _getCheckout() {
        return $this->_objectManager->get('Magento\Checkout\Model\Session');
    }

    public function execute() {
        $this->_redirect('checkout/cart');
    }
}
