<?php
$installer = $this;

$installer->startSetup();

$installer->addAttribute('order', 'referralcorner_url', array('type' => Varien_Db_Ddl_Table::TYPE_VARCHAR, 'visible'  => true, 'required' => false, 'default' => 'none'));
$installer->addAttribute('quote', 'referralcorner_url', array('type' => Varien_Db_Ddl_Table::TYPE_VARCHAR, 'visible'  => true, 'required' => false, 'default' => 'none'));

$installer->endSetup();
