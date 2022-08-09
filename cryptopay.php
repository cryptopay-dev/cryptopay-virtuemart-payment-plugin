<?php

defined('_JEXEC') or die('Restricted access');
define('CRYPTOPAY_VIRTUEMART_EXTENSION_VERSION', '1.0.0');

if (!class_exists('vmPSPlugin'))
    require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');

class plgVmPaymentCryptopay extends vmPSPlugin
{
    function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);

        $this->_loggable = TRUE;
        $this->tableFields = array_keys($this->getTableSQLFields());
        $this->_tablepkey = 'id';
        $this->_tableId = 'id';
        $varsToPush = $this->getVarsToPush();

        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
    }

    public function getVmPluginCreateTableSQL()
    {
        return $this->createTableSQL('Payment Cryptopay Table');
    }

    /**
     * This function is used to define the column names to be added in the payment table creation.
     */
    function getTableSQLFields()
    {
        $SQLfields = array(
            'id' => 'int(1) UNSIGNED NOT NULL AUTO_INCREMENT',
            'virtuemart_order_id' => 'int(1) UNSIGNED',
            'order_number' => 'char(64)',
            'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
            'payment_name' => 'varchar(5000)',
            'payment_order_total' => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\'',
            'payment_currency' => 'char(3)'
        );

        return $SQLfields;
    }

    function getCosts(VirtueMartCart $cart, $method, $cart_prices)
    {
        return 0;
    }

    /**
     * This function is called After plgVmOnCheckAutomaticSelectedPayment
     * Checks if the payment conditions are fulfilled for the payment method.
     * If you want to show/hide the payment plugin on some specific conditions then this function is very useful.
     */
    protected function checkConditions($cart, $method, $cart_prices)
    {
        return true;
    }

    /**
     * It creates the plugin specific database table for your plugin further working.
     */
    function plgVmOnStoreInstallPaymentPluginTable($jplugin_id)
    {
        return $this->onStoreInstallPluginTable($jplugin_id);
    }

    /**
     * This function is used to calculate the price of the payment method. Price calculation is done on
     * checkbox selection of payment method at cart view.
     * If you forget to add this function in your plugin code,
     * your payment plugin will not be selectable at all on the cart view.
     */
    public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name)
    {
        return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
    }

    function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId)
    {
        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id)))
            return NULL;

        if (!$this->selectedThisElement($method->payment_element))
            return false;

        $this->getPaymentCurrency($method);

        $paymentCurrencyId = $method->payment_currency;

        return;
    }

    /**
     * This function is first called when you finally setup the configuration of payment plugin and redirect to the
     * cart view on store.
     * In case you have set your payment plugin as the default payment method by VirtueMart’s Configuration,
     * this function is used.
     */
    function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter)
    {
        return $this->onCheckAutomaticSelected($cart, $cart_prices, $paymentCounter);
    }

    /**
     * This function is triggered after plgVmConfirmedOrder, to display the payment related information in order.
     */
    public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name)
    {
        $this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
    }

    function plgVmonShowOrderPrintPayment($order_number, $method_id)
    {
        return $this->onShowOrderPrint($order_number, $method_id);
    }

    function plgVmDeclarePluginParamsPayment($name, $id, &$data)
    {
        return $this->declarePluginParams('payment', $name, $id, $data);
    }

    /**
     * You can get the saved plugin configuration values by the help of this function’s parameter.
     * This function is called whenever you try to update the configuration of the payment plugin.
     */
    function plgVmDeclarePluginParamsPaymentVM3(&$data)
    {
        return $this->declarePluginParams('payment', $data);
    }

    /**
     * Used for storing values of payment plugins configuration in database table.
     */
    function plgVmSetOnTablePluginParamsPayment($name, $id, &$table)
    {
        return $this->setOnTablePluginParams($name, $id, $table);
    }

    /**
     * This is a required function for your payment plugin and called after plgVmonSelectedCalculatePricePayment.
     * This function is used to display the payment plugin name on cart view payment option list.
     * This function has a parameter $html by the help of which you can add additional view,
     * if required to be shown with payment name.
     */
    function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn)
    {
        $session = JFactory::getSession();
        $errors = $session->get('errorMessages', 0, 'vm');

        if ($errors != "") {
            $errors = unserialize($errors);
            $session->set('errorMessages', "", 'vm');
        } else
            $errors = array();

        return $this->displayListFE($cart, $selected, $htmlIn);
    }

    public function getGMTTimeStamp()
    {
        $tz_minutes = date('Z') / 60;

        if ($tz_minutes >= 0)
            $tz_minutes = '+' . sprintf("%03d", $tz_minutes);

        $stamp = date('YdmHis000000') . $tz_minutes;

        return $stamp;
    }

    /**
     * Callback function
     */
    function plgVmOnPaymentNotification()
    {
        try {
            $request = file_get_contents('php://input');
            $body = json_decode($request, true);

            if ($body['type'] != 'Invoice') {
                return 'It is not Invoice';
            }

            $data = $body['data'];
            $virtuemartOrderId = str_replace(
                'magento_order_',
                "",
                'magento_order_' . $data['custom_id']
            );

            $modelOrder = VmModel::getModel('orders');
            $order = $modelOrder->getOrder($virtuemartOrderId);

            if (!$order)
                throw new Exception('Order #' . $virtuemartOrderId . ' does not exists');
            $method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id);

            if (!$this->validateCallback($request, $_SERVER['HTTP_X_CRYPTOPAY_SIGNATURE'], $method)) {
                return 'Webhook validation failed.';
            }

            if (!$this->selectedThisElement($method->payment_element))
                return 'it is not the correct element';

            if ($data['status'] == 'new') {
                $this->updateOrderStatus($method->pending_status, $virtuemartOrderId, $order);
                return 'Update to pending';
            }

            if ($data['status'] == 'completed' || $data['status'] == 'unresolved' && $data['status_context'] == 'overpaid') {
                $this->updateOrderStatus($method->paid_status, $virtuemartOrderId, $order);
                return 'Update to paid';
            }

            if ($data['status'] == 'cancelled' || $data['status'] == 'refunded' || $data['status'] == 'unresolved') {
                $this->updateOrderStatus($method->canceled_status, $virtuemartOrderId, $order);
            }

            return 'success';
        } catch (\Exception $e) {
            return 'Webhook receive error.';
        }
    }

    /**
     * This function is triggered when the user click on the Confirm Purchase button on cart view.
     * You can store the transaction/order related details using this function.
     */
    function plgVmConfirmedOrder($cart, $order)
    {
        if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id)))
            return NULL;

        if (!$this->selectedThisElement($method->payment_element))
            return false;

        if (!class_exists('VirtueMartModelOrders'))
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');

        if (!class_exists('VirtueMartModelCurrency'))
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'currency.php');

        vmLanguage::loadJLang('com_virtuemart', true);
        vmLanguage::loadJLang('com_virtuemart_orders', true);

        $orderID = $order['details']['BT']->virtuemart_order_id;

        $modelOrder = new VirtueMartModelOrders();
        $orderModel = $modelOrder->getOrder($orderID);
        $orderModel['order_status'] = $method->pending_status;
        $orderModel['virtuemart_order_id'] = $orderID;
        $orderModel['customer_notified'] = 1;
        $modelOrder->updateStatusForOneOrder($orderID, $orderModel, true);

        $currency_code_3 = shopFunctions::getCurrencyByID($method->currency_id, 'currency_code_3');

        $paymentCurrency = CurrencyDisplay::getInstance($method->currency_id);
        $totalInCurrency = round($paymentCurrency->convertCurrencyTo($method->currency_id, $order['details']['BT']->order_total, false), 2);

        $params = array(
            'customId' => 'virtuemart_order_' . $orderID,
            'widgetKey' => $method->widget_key,
            'isShowQr' => $method->qr_code == 1 ? 'true' : 'false',
            'theme' => $method->theme,
            'priceCurrency' => $currency_code_3,
            'priceAmount' => $totalInCurrency,
            'successRedirectUrl' => JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&on=' . $this->$order['details']['BT']->order_number . '&pm=' . $this->$order['details']['BT']->virtuemart_paymentmethod_id),
            'unsuccessRedirectUrl' => (JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginUserPaymentCancel&on=' . $this->$order['details']['BT']->order_number . '&pm=' . $this->$order['details']['BT']->virtuemart_paymentmethod_id))
        );

        $redirectUrl = $method->environment == 'sandbox'
            ? 'https://pay-business-sandbox.cryptopay.me'
            : 'https://business-pay.cryptopay.me';

        $url = $redirectUrl . '?' . http_build_query($params);
        header('Location: ' . $url);
//        $cart->emptyCart();
        exit;
    }

    /**
     * This event is fired when the  method returns to the shop after the transaction
     * @param $html
     * @return bool|null
     */
    function plgVmOnPaymentResponseReceived(&$html)
    {
        if (!class_exists('VirtueMartCart'))
            require(JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');
        $cart = VirtueMartCart::getCart();
        $cart->emptyCart();

        return true;
    }

    /**
     * Validate the callback request
     */
    private function validateCallback($body, $signature, $method)
    {
        $callbackSecret = $method->callback_secret;
        $expected = hash_hmac('sha256', $body, $callbackSecret);
        return $expected === $signature;
    }

    /**
     * Update order status
     */
    private function updateOrderStatus($status, $virtuemartOrderId, $order)
    {
        $modelOrder = new VirtueMartModelOrders();
        $order['order_status'] = $status;
        $order['virtuemart_order_id'] = $virtuemartOrderId;
        $order['customer_notified'] = 1;

        $modelOrder->updateStatusForOneOrder($virtuemartOrderId, $order, true);
    }
}

defined('_JEXEC') or die('Restricted access');

if (!class_exists('VmConfig'))
    require(JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_virtuemart' . DS . 'helpers' . DS . 'config.php');

if (!class_exists('ShopFunctions'))
    require(JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'shopfunctions.php');

defined('JPATH_BASE') or die();
