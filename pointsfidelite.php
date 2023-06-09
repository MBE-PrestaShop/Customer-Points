<?php

/**
 * 2007-2023 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author    PrestaShop SA <contact@prestashop.com>
 *  @copyright 2007-2023 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

if (!defined('_PS_VERSION_')) {
    exit;
}
require_once __DIR__.'/classes/pointsfidelite/pointsfideliteentity.php';
class Pointsfidelite extends Module
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'pointsfidelite';
        $this->tab = 'pricing_promotion';
        $this->version = '1.0.0';
        $this->author = 'Mail Boxes ETC, Rep. Dominicana';
        $this->need_instance = 1;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Customer Points');
        $this->description = $this->l('Customer loyalty points generator');

        $this->confirmUninstall = $this->l('');

        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        include(dirname(__FILE__) . '/sql/install.php');

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('displayBackOfficeHeader') &&
            $this->registerHook('displayCustomerAccount');
    }

    public function uninstall()
    {
        include(dirname(__FILE__) . '/sql/uninstall.php');

        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool)Tools::isSubmit('submitPointsfideliteModule')) == true) {
            $this->postProcess();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure.tpl');

        return $output . $this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitPointsfideliteModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Enable Points System'),
                        'name' => 'POINTS_SYSTEM_ENABLED',
                        'is_bool' => true,
                        'desc' => $this->l('Enable or disable the customer points system.'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Points Conversion Rate'),
                        'name' => 'POINTS_CONVERSION_RATE',
                        'desc' => $this->l('Specify the conversion rate of currency to points.'),
                        'suffix' => $this->l('currency unit = 1 point'),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Minimum Order Amount for Earning Points'),
                        'name' => 'MIN_ORDER_AMOUNT',
                        'desc' => $this->l('Specify the minimum order amount required for earning points.'),
                        'suffix' => $this->l('currency'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
            'POINTS_SYSTEM_ENABLED' => Configuration::get('POINTS_SYSTEM_ENABLED', true),
            'POINTS_CONVERSION_RATE' => Configuration::get('POINTS_CONVERSION_RATE', 1),
            'MIN_ORDER_AMOUNT' => Configuration::get('MIN_ORDER_AMOUNT', 0),
        );
    }

    public function hookDisplayCustomerAccount()
    {
        $pointsEnabled = Configuration::get('POINTS_SYSTEM_ENABLED');
        $conversionRate = Configuration::get('POINTS_CONVERSION_RATE');
        $minOrderAmount = Configuration::get('MIN_ORDER_AMOUNT');

        if ($pointsEnabled) {
            // Verificar si el cliente ha realizado un pedido válido
            $customerId = $this->context->customer->id;
            $orders = Order::getCustomerOrders($customerId);
            $totalPoints = 0;

            foreach ($orders as $order) {
                if ($order['valid']) {
                    // Calcular los puntos basados en el monto de la orden
                    $orderAmount = (float) $order['total_paid_tax_incl'];
                    if ($orderAmount >= $minOrderAmount) {
                        //echo $orderAmount;
                        $points = $orderAmount / $conversionRate;
                        $totalPoints += $points;
                    }
                }
            }
            
            $customerPoints = new pointsfideliteEntity();
            
            // Guardar o actualizar los puntos en la tabla pointsfidelite
            $existingPoints = Db::getInstance()->getValue("SELECT * FROM `" . _DB_PREFIX_ . "pointsfidelite` WHERE `id_customer` = " . (int)$customerId);
            if($existingPoints !== false){
               // Actualizar los puntos existentes
                Db::getInstance()->execute("UPDATE `" . _DB_PREFIX_ . "pointsfidelite` SET `point` = " . floor($totalPoints) . " WHERE `id_customer` = " . (int)$customerId);

            }else{
                $customerPoints->id_customer = $customerId;
                $customerPoints->point = floor($totalPoints);
                $customerPoints->save();
            }
        }

        $this->smarty->assign([
            'customerPoints' => floor($totalPoints),
            //'url' => $this->context->link->getModuleLink('blockwishlist', 'action', ['action' => 'deleteProductFromWishlist']),
          ]);

        // Renderiza el contenido en el área de la cuenta del cliente
        return $this->display(__FILE__, 'views/templates/hook/displayCustomerAccount.tpl');
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be loaded in the BO.
     */
    public function hookDisplayBackOfficeHeader()
    {
        if (Tools::getValue('configure') == $this->name) {
            $this->context->controller->addJS($this->_path . '/views/js/back.js');
            $this->context->controller->addCSS($this->_path . '/views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path . '/views/js/front.js');
        $this->context->controller->addCSS($this->_path . '/views/css/front.css');
    }
}
