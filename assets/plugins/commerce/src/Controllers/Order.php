<?php

namespace FormLister;

class Order extends Form
{
    public function render()
    {
        $delivery = $this->modx->commerce->getDeliveries();

        $payments = [];
        foreach ($this->modx->commerce->getPayments() as $code => $payment) {
            $payments[$code] = [
                'title'  => $payment['title'],
                'markup' => $payment['processor']->getMarkup(),
            ];
        }

        foreach (['delivery' => $this->getCFGDef('default_delivery', key($delivery)), 'payments' => $this->getCFGDef('default_payment', key($payments))] as $type => $default) {
            $output = '';
            $rows   = $$type;
            $index  = 0;
            $markup = '';

            foreach ($rows as $code => $row) {
                $output .= $this->DLTemplate->parseChunk('order_form_' . $type . '_row', [
                    'code'   => $code,
                    'title'  => $row['title'],
                    'price'  => isset($row['price']) ? $row['price'] : '',
                    'markup' => isset($row['markup']) ? $row['markup'] : '',
                    'active' => 1 * ($default == $code),
                    'index'  => $index,
                ]);

                $markup .= isset($row['markup']) ? $row['markup'] : '';
            }

            if (!empty($output)) {
                $output = $this->DLTemplate->parseChunk('order_form_' . $type, [
                    'wrap'   => $output,
                    'markup' => $markup,
                ]);
            }

            $this->setPlaceholder($type, $output);
        }

        return parent::render();
    }

    public function process()
    {
        if ($this->checkSubmitProtection()) {
            return;
        }

        $cart = $this->modx->commerce->getCart();
        $items = $cart->getItems();
        $params = [
            '_FL'   => $this,
            'items' => &$items,
        ];

        $this->modx->invokeEvent('OnBeforeOrderProcessing', $params);

        if (is_array($params['items'])) {
            $cart->setItems($items);
        }

        $fields = $this->getFormData('fields');

        if (!empty($fields['payment_method'])) {
            $payment = $this->modx->commerce->getPayment($fields['payment_method']);
            $this->setField('payment_method_title', $payment['title']);
        }

        if (!empty($fields['delivery_method'])) {
            $delivery = $this->modx->commerce->getDelivery($fields['delivery_method']);
            $this->setField('delivery_method_title', $delivery['title']);
        }

        $processor = $this->modx->commerce->loadProcessor();
        $processor->createOrder($items, $this->getFormData('fields'));
        parent::process();
        $processor->postProcessForm($this);
        $this->modx->invokeEvent('OnOrderProcessed');
        $this->redirect();
    }
}