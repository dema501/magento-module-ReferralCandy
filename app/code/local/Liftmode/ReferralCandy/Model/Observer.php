<?php

class Liftmode_ReferralCandy_Model_Observer {
    const RC_MODULE_ENABLE    = 'sales/referralcandy/enable';
    const RC_ACCESS_ID        = 'sales/referralcandy/access_id';
    const RC_SECRET_KEY       = 'sales/referralcandy/secret_key';
    const RC_ITEMS_COUNT_PER_SESSION = 'sales/referralcandy/send_items_count_per_session';

    public function notifyReferralCandyAboutNewOrder(Varien_Event_Observer $observer) {
        if (!Mage::getStoreConfig(self::RC_MODULE_ENABLE, Mage::app()->getStore())) {
            return $this;
        }

        $_order = $observer->getEvent()->getOrder();

        $params = $this->getPurchaseParams($_order);
        $return = $this->notifyReferralCandyAboutNewPurchase($params);

        if ($return['response']['status'] === true) {
            $referralcorner_url = $return['response']['referralcorner_url'];
            $_order->setReferralcornerUrl($referralcorner_url);
            $_order->save();
        }

        return $this;
    }

    public function notifyReferralCandyAboutCanceledOrder(Varien_Event_Observer $observer) {
        if (!Mage::getStoreConfig(self::RC_MODULE_ENABLE, Mage::app()->getStore())) {
            return $this;
        }

        $_order = $observer->getEvent()->getOrder();

        $rc = Mage::getModel('referralcandy/referralCandy', array (
            'access_id'  => Mage::getStoreConfig(self::RC_ACCESS_ID, Mage::app()->getStore()),
            'secret_key' => Mage::getStoreConfig(self::RC_SECRET_KEY, Mage::app()->getStore())
        ));

        $params = array(
            'customer_email'        => $_order->getCustomerEmail(),
            'returned'              => true,
        );

        $return = $rc->doRequest('referral', $params);
        Mage::log(array('notify referral order------>>>', $params, $return), null, 'ReferralCandy.log');

        return $this;
    }

    public function notifyReferralCandyAboutNewPurchase(array $params = array()) {
        if (sizeof($params) === 0) {
            return false;
        }

        $rc = Mage::getModel('referralcandy/referralCandy', array (
            'access_id'  => Mage::getStoreConfig(self::RC_ACCESS_ID, Mage::app()->getStore()),
            'secret_key' => Mage::getStoreConfig(self::RC_SECRET_KEY, Mage::app()->getStore())
        ));

        $return = $rc->doRequest('purchase', $params);
        Mage::log(array('notify order------>>>', $params, $return), null, 'ReferralCandy.log');

        return $return;
    }

    public function cron() {
        if (!Mage::getStoreConfig(self::RC_MODULE_ENABLE, Mage::app()->getStore())) {
            return $this;
        }

        $itemsCount = (int) Mage::getStoreConfig(self::RC_ITEMS_COUNT_PER_SESSION, Mage::app()->getStore());

        if (!($itemsCount > 0)) {
            $itemsCount = 100;
        }

        $_ordersCollection = Mage::getResourceModel('sales/order_collection')
                    ->addFieldToFilter('status', Mage_Sales_Model_Order::STATE_COMPLETE)
                    ->addAttributeToFilter('referralcorner_url', array('eq' => 'none'))
                    ->setPage(0, $itemsCount)
                    ->load();

        Mage::log(array('found orders------>>>', sizeof($_ordersCollection)), null, 'ReferralCandy.log');

        foreach ($_ordersCollection as $_order) {
            $params = $this->getPurchaseParams($_order);
            $return = $this->notifyReferralCandyAboutNewPurchase($params);

            if ($return['response']['status'] === true) {
                $referralcorner_url = $return['response']['referralcorner_url'];
                $_order->setReferralcornerUrl($referralcorner_url);
                $_order->getResource()->saveAttribute($_order, 'referralcorner_url');
            }

            sleep(2);
        }

        $_ordersCollection->clear();
    }

    private function getPurchaseParams($_order) {
        return array(
                'first_name'            => $_order->getCustomerFirstname(),
                'last_name'             => $_order->getCustomerLastname(),
                'email'                 => $_order->getCustomerEmail(),
                'discount_code'         => $_order->getCouponCode(),
                'order_timestamp'       => $_order->getCreatedAtStoreDate()->getTimestamp(),
                'browser_ip'            => $_order->getRemoteIp(),
                'user_agent'            => Mage::helper('core/http')->getHttpUserAgent(), // 'Bond/0.0.7',
                'invoice_amount'        => (float) $_order->getGrandTotal(),
                'currency_code'         => $_order->getOrderCurrencyCode(),
                'external_reference_id' => $_order->getIncrementId(),
            );
    }
}
