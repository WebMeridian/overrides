<?php
$installer = $this;

$installer->startSetup();
Mage::log('test',null,'log.log');
$setup = new Mage_Eav_Model_Entity_Setup('core_setup');
function attr_params($attr){
  $defaulr_params = array(
      "type"     => "varchar",
      "backend"  => "",
      "label"    => $attr,
      "input"    => "hidden",
      "source"   => "",
      "visible"  => true,
      "required" => false,
      "default" => "",
      "frontend" => "",
      "unique"     => false,
      "note"       => $attr
    );
    return $defaulr_params;
}
$entityTypeId     = $setup->getEntityTypeId('customer');
$attributeSetId   = $setup->getDefaultAttributeSetId($entityTypeId);
$attributeGroupId = $setup->getDefaultAttributeGroupId($entityTypeId, $attributeSetId);

$attributes = ["old_cerusr"=>'Свідоцтво про держреєстрацію або витяг з ЄДР 2' ,"old_cerusr2" => "Свідоцтво про держреєстрацію або витяг з ЄДР 2","old_certificate_vat" => 'Свідоцтво платника ПДВ',"old_certificate_vat2" => 'Свідоцтво платника ПДВ 2'];

$used_in_forms=array();

$used_in_forms[]="adminhtml_customer";
//$used_in_forms[]="checkout_register";
//$used_in_forms[]="customer_account_create";
//$used_in_forms[]="customer_account_edit";
//$used_in_forms[]="adminhtml_checkout";

foreach ($attributes as $attribute_code => $label) {
    $installer->addAttribute("customer", $attribute_code, attr_params($label) );
    $attribute   = Mage::getSingleton("eav/config")->getAttribute("customer", $attribute_code);

    $setup->addAttributeToGroup(
        $entityTypeId,
        $attributeSetId,
        $attributeGroupId,
        $attribute_code,
        '999'  //sort_order
    );

    $attribute->setData("used_in_forms", $used_in_forms)
            ->setData("is_used_for_customer_segment", true)
            ->setData("is_system", 0)
            ->setData("is_user_defined", 1)
            ->setData("is_visible", 1)
            ->setData("sort_order", 100);
    $attribute->save();
}

$installer->endSetup();
