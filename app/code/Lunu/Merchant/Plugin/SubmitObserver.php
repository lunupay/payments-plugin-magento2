<?php

namespace Lunu\Merchant\Plugin;

use Magento\Sales\Model\Order;

class SubmitObserver
{
  public function beforeExecute($subject, $observer)
  {
    $order = $observer->getEvent()->getOrder();

    if (in_array($order->getState(), array(
        Order::STATE_NEW,
        Order::STATE_PENDING_PAYMENT,
        Order::STATE_PROCESSING
    ))) {
      $order->setCanSendNewEmailFlag(false);
    }

    return [$observer];
  }
}
