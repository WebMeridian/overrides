<?php

class WebMeridian_Override_Model_Order extends Mage_Sales_Model_Order
{
  public function sendNewOrderEmail()
  {
      $this->queueNewOrderEmail(true);
      return $this;
  }

  public function queueNewOrderEmail($forceMode = false)
  {
      $storeId = $this->getStore()->getId();

      if (!Mage::helper('sales')->canSendNewOrderEmail($storeId)) {
          return $this;
      }

      // Get the destination email addresses to send copies to
      $copyTo = $this->_getEmails(self::XML_PATH_EMAIL_COPY_TO);
      $copyMethod = Mage::getStoreConfig(self::XML_PATH_EMAIL_COPY_METHOD, $storeId);

      // Start store emulation process
      /** @var $appEmulation Mage_Core_Model_App_Emulation */
      $appEmulation = Mage::getSingleton('core/app_emulation');
      $initialEnvironmentInfo = $appEmulation->startEnvironmentEmulation($storeId);

      try {
          // Retrieve specified view block from appropriate design package (depends on emulated store)
          $paymentBlock = Mage::helper('payment')->getInfoBlock($this->getPayment())
              ->setIsSecureMode(true);
          $paymentBlock->getMethod()->setStore($storeId);
          $paymentBlockHtml = $paymentBlock->toHtml();
      } catch (Exception $exception) {
          // Stop store emulation process
          $appEmulation->stopEnvironmentEmulation($initialEnvironmentInfo);
          throw $exception;
      }

      // Stop store emulation process
      $appEmulation->stopEnvironmentEmulation($initialEnvironmentInfo);

      // Retrieve corresponding email template id and customer name
      if ($this->getCustomerIsGuest()) {
          $templateId = Mage::getStoreConfig(self::XML_PATH_EMAIL_GUEST_TEMPLATE, $storeId);
          $customerName = $this->getBillingAddress()->getName();
      } else {
          $templateId = Mage::getStoreConfig(self::XML_PATH_EMAIL_TEMPLATE, $storeId);
          $customerName = $this->getCustomerName();
      }

      /** @var $mailer Mage_Core_Model_Email_Template_Mailer */
      $mailer = Mage::getModel('core/email_template_mailer');
      /** @var $emailInfo Mage_Core_Model_Email_Info */
      $emailInfo = Mage::getModel('core/email_info');
      $emailInfo->addTo($this->getCustomerEmail(), $customerName);

      $mailer->addEmailInfo($emailInfo);

      // Set all required params and send emails
      $mailer->setSender(Mage::getStoreConfig(self::XML_PATH_EMAIL_IDENTITY, $storeId));
      $mailer->setStoreId($storeId);
      $mailer->setTemplateId($templateId);
      $mailer->setTemplateParams(array(
          'order'        => $this,
          'billing'      => $this->getBillingAddress(),
          'payment_html' => $paymentBlockHtml,
          'items'   => $this->get1cdata(),
      ));
      /** @var $emailQueue Mage_Core_Model_Email_Queue */
      $emailQueue = Mage::getModel('core/email_queue');
      $emailQueue->setEntityId($this->getId())
          ->setEntityType(self::ENTITY)
          ->setEventType(self::EMAIL_EVENT_NAME_NEW_ORDER)
          ->setIsForceCheck(!$forceMode);

      $mailer->setQueue($emailQueue)->send();

      if(!empty($copyTo)){

        $templateId = 'sales_email_report';
        if ($this->getCustomerIsGuest()) {
            $customerName = $this->getBillingAddress()->getName();
            $customer = $this->getBillingAddress();
        } else {
            $customerName = $this->getCustomerName();
            $customer = Mage::getModel('customer/customer')->load($this->getCustomerId());
        }

        $shipping_method = '';
        $shipping_m = '';
        $shipping_webdepartment_addres = '';
        $webshipping_order = Mage::getModel('webmeridian_shipping/address')->getCollection()->addFieldToFilter('order_id', $this->getId())->getFirstItem();
        if(!empty($webshipping_order)){
          $shipping_addres = $webshipping_order->getShippingAddress();
          if($shipping_addres['shipping_rate'] == "webdepartment_webnovaposhta" || $shipping_addres['shipping_rate'] == webdoor_webdelivery){
              $shipping_webdepartment_addres = $webshipping_order->getShippingAddress();
          }
        }
        switch ($this->getShippingMethod()) {
            case 'ocpickup_ocpickup':
                $shipping_method = '1:'.$this->getShippingDescription();
                break;
            case 'occourier_occourier':
                $shipping_method = '2:'.$this->getShippingDescription();
                break;
            case 'webdepartment_webdelivery':
                $shipping_m = '1:'.$this->getShippingDescription();

                $shipping_method = '3: До дверей';
                break;
            case 'webdepartment_webnovaposhta':
                $shipping_m = '2:'.$this->getShippingDescription();
                $shipping_method = '3: До дверей';

                break;
            case 'webdoor_webdelivery':
                $shipping_m = '1:'.$this->getShippingDescription();
                $shipping_method = '4:'.$this->getShippingDescription();
                break;
            case 'webdoor_webnovaposhta':
                $shipping_m = '2:'.$this->getShippingDescription();
                $shipping_method = '5:'.$this->getShippingDescription();
                break;
            default:
                $shipping_method = '6:'.$this->getShippingDescription();
                break;
        }
        $payment = $this->getPayment();


        $payment_method = '';
        switch ($payment->getMethodInstance()->getCode()) {
            case 'checkmo':
                $payment_method = '1:'.$payment->getMethodInstance()->getTitle();
                break;
            case 'cashondelivery':
                $payment_method = '2:'.$payment->getMethodInstance()->getTitle();
                break;
            case 'purchaseorder':
                $payment_method = '3:'.$payment->getMethodInstance()->getTitle();
                break;
            default:
                $shipping_method = '4:'.$payment->getMethodInstance()->getTitle();
                break;
        }
        $file       = Mage::getBaseUrl('media') . 'customer' ;
        $ship_address = $this->getShippingAddress()->getData();
        $order_date = new DateTime($this->getCreatedAt());
        $customer_html = '1. '.date_format($order_date, 'Y-m-d').'<br>';
        $customer_html .= '2. '.date_format($order_date, 'H:i:s').'<br>';
        $customer_html .= '3. '.$this->getCustomerEmail().'<br>';
        $customer_html .= '4. '.(($customer->getCompany())?$customer->getCompany():'').'<br>';
        $customer_html .= '5. '.(($customer->getPhone())?$customer->getPhone():'').'<br/>';
        $customer_html .= '6. '.(($customer->getOfficeAddress())?$customer->getOfficeAddress():'').'<br/>';
        $customer_html .= '7. '.(($customer->getEdrpou())?$customer->getEdrpou():'').'<br/>';

        $attributeDetails = Mage::getSingleton("eav/config")->getAttribute("customer", 'client_type');
        $optionValue = '';
        if($customer->getClientType()){
          $optionValue = $attributeDetails->getSource()->getOptionText($customer->getClientType());
        }
        $product_weight = '';
        $products = '';
        $items = $this->getAllVisibleItems();
        foreach($items as $i):
           $_product = Mage::getModel('catalog/product')->load($i->getProductId());
           $product_weight += $_product->getWeight();
           $products .= $_product->getSku().'*'.$i->getQtyOrdered().'*'.$i->getPrice().'*'.$_product->getName().'<br>';
        endforeach;
        $customer_html .= '8. Див. прикріплення<br/>';
        $customer_html .= '9. '.$optionValue.'<br/>';
        $customer_html .= '10.  Див. прикріплення<br/>';
        $customer_html .= '11. '.(($customer->getCodeInn())?$customer->getCodeInn():'').'<br/>';
        $customer_html .= '14. '.(($customer->getFirstname())?$customer->getFirstname():'').' ';
        $customer_html .= (($customer->getMiddlename())?$customer->getMiddlename():'').' ';
        $customer_html .= (($customer->getLastname())?$customer->getLastname():'').'<br/>';
        $customer_html .= '15. '.(($customer->getPhone())?$customer->getPhone():'').'<br/>';
        $customer_html .= '16. '.$ship_address['region'].', '.$ship_address['city'].', '.$ship_address['street'].'<br/>';
        $customer_html .= '17. '.$product_weight.'кг. <br/>';
        $customer_html .= '18. '.round($this->getGrandTotal(),2).'<br/>';
        $customer_html .= '19. '.$shipping_method.'<br/>';
        $customer_html .= $ship_address['city'].', '.$shipping_addres.'<br/>';
        $customer_html .= '20. '.$payment_method.'<br/>';

        $customer_html .= '21. Замовлення:<br><br> '.$products.'<br/>';
        $vars = array(
            'order'        => $this,
            'billing'      => $this->getBillingAddress(),
            'payment_html' => $paymentBlockHtml,
            'items'   => $this->get1cdata(),
            'customer' => $customer,
            'customer_html' => $customer_html,
        );

        $senderName = Mage::getStoreConfig('trans_email/ident_support/name');
        $senderEmail = Mage::getStoreConfig(self::XML_PATH_EMAIL_IDENTITY, $storeId);
        $sender = array('name' => $senderName, 'email' => $senderEmail);

        $emailTemplate = Mage::getModel('core/email_template')->loadByCode($templateId);

        $emailTemplate->getProcessedTemplate($vars);
        $emailTemplate->setSenderEmail($senderEmail);
        $emailTemplate->setSenderName($senderName);

        if($customer->getCerusr() != $customer->getOldCerusr()){
          $i = $_SERVER['DOCUMENT_ROOT'].DS.'media'.DS.'customer'.''.$customer->getCerusr();
          $file = file_get_contents($i);
          $attachment = $emailTemplate->getMail()->createAttachment($file);
          $attachment->type = 'image/png';
          $attachment->filename = 'svidoctvo_edr1.png';
          $customer->setOldCerusr($customer->getCerusr());
        }

        if($customer->getCerusr2() != $customer->getOldCerusr2()){
          $i = $_SERVER['DOCUMENT_ROOT'].DS.'media'.DS.'customer'.''.$customer->getCerusr2();
          $file = file_get_contents($i);
          $attachment = $emailTemplate->getMail()->createAttachment($file);
          $attachment->type = 'image/png';
          $attachment->filename = 'svidoctvo_edr2.png';
          $customer->setOldCerusr2($customer->getCerusr2());
        }

        if($customer->getCertificateVat() != $customer->getOldCertificateVat()){
          $i = $_SERVER['DOCUMENT_ROOT'].DS.'media'.DS.'customer'.''.$customer->getCertificateVat();
          $file = file_get_contents($i);
          $attachment = $emailTemplate->getMail()->createAttachment($file);
          $attachment->type = 'image/png';
          $attachment->filename = 'svidoctvo_pdv1.png';
          $customer->setOldCertificateVat($customer->getCertificateVat());
        }

        if($customer->getCertificateVat2() != $customer->getOldCertificateVat2()){
          $i = $_SERVER['DOCUMENT_ROOT'].DS.'media'.DS.'customer'.''.$customer->getCertificateVat2();
          $file = file_get_contents($i);
          $attachment = $emailTemplate->getMail()->createAttachment($file);
          $attachment->type = 'image/png';
          $attachment->filename = 'svidoctvo_pdv2.png';
          $customer->setOldCertificateVat2($customer->getCertificateVat2());
        }
        $customer->save();
        $emailTemplate->sendTransactional($templateId, $sender, $copyTo, "Admin", $vars);

      }


      $this->setEmailSent(true);
      $this->_getResource()->saveAttribute($this, 'email_sent');

      return $this;
  }

  public function overrideSpesialPriceAfterImport(){
    $products = Mage::getModel('catalog/product')->getCollection()
                ->addAttributeToSelect('*');

    foreach ($products as $product) {

        if($product->getAction() == '0'){
          $product->setSpecialToDate('2010-01-01');
          // below code use for time format
          $product->setSpecialToDateIsFormated(true);
          $product->getResource()->saveAttribute($product, 'special_to_date');
        }

    }
  }
}
