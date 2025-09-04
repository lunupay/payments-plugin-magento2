<?php
/**
 * Lunu Callback controller
 *
 * @category    Lunu
 * @package     Lunu_Merchant
 * @author      Lunu Solutions GmbH
 * @copyright   Lunu Solutions GmbH (https://lunu.io)
 */
namespace Lunu\Merchant\Controller\Payment;

use Lunu\Merchant\Model\Payment as LunuPayment;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;

class Callback extends Action {
    protected $order;
    protected $lunu_payment;

    public function __construct(
        Context $context,
        LunuPayment $lunu_payment
    ) {
        parent::__construct($context);
        $this->lunuPayment = $lunu_payment;
        $this->execute();
    }

    public function execute() {
        $this->lunuPayment->validateLunuCallback();
        $this->getResponse()->setBody('OK');
    }
}
