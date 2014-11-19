<?php

class splashscreen extends Module
{
    private $_html = '';
    private $_postErrors = array();

    public function __construct()
    {
        $this->name = 'splashscreen';
        $this->tab = 'front_office_features';
        $this->version = '0.1';
        $this->author = 'cornug';
        
        $this->bootstrap = true;
        parent::__construct();
        
        $this->displayName = $this->l('Splash screen');
        $this->description = $this->l('Show a splash screen when entering the shop.');
    }
    
    public function install()
    {
        if (!parent::install() ||
            !$this->registerHook('header') ||
            !Configuration::updateValue('SPLASHSCREEN_COOKIE', md5(time())) ||
            !Configuration::updateValue('SPLASHSCREEN_EXPIRES', 7) ||
            !Configuration::updateValue('SPLASHSCREEN_ID_CMS', 2) ||
            !Configuration::updateValue('SPLASHSCREEN_URL', 'http://www.google.com/'))
            return false;
        return true;
    }
    
    public function uninstall()
    {
        if (!parent::uninstall() ||
            !Configuration::deleteByName('SPLASHSCREEN_COOKIE') ||
            !Configuration::deleteByName('SPLASHSCREEN_EXPIRES') ||
            !Configuration::deleteByName('SPLASHSCREEN_ID_CMS') ||
            !Configuration::deleteByName('SPLASHSCREEN_URL'))
            return false;
        return true;
    }
    
    public function getContent()
    {
        $output = null;
        
        if (Tools::isSubmit('submitSplashScreen'))
        {
            $splashscreen_expires = strval(Tools::getValue('SPLASHSCREEN_EXPIRES'));
            $splashscreen_id_cms = (int)Tools::getValue('SPLASHSCREEN_ID_CMS');
            $splashscreen_url = strval(Tools::getValue('SPLASHSCREEN_URL'));
            
            if(!filter_var($splashscreen_url, FILTER_VALIDATE_URL))
                $output .= $this->displayError($this->l('Redirect URL must be a valid URL'));
            
            elseif(!Validate::isInt($splashscreen_expires) ||
                $splashscreen_expires < 1 ||
                $splashscreen_expires > 365)
                $output .= $this->displayError($this->l('Cookie expiration must be a number of days between 1 and 365'));
            
            else {
                if($splashscreen_expires != (int)Configuration::get('SPLASHSCREEN_EXPIRES')) {
                    Configuration::updateValue('SPLASHSCREEN_COOKIE', md5(time()));
                }
                Configuration::updateValue('SPLASHSCREEN_EXPIRES', $splashscreen_expires);
                Configuration::updateValue('SPLASHSCREEN_ID_CMS', $splashscreen_id_cms);
                Configuration::updateValue('SPLASHSCREEN_URL', $splashscreen_url);
                $output .= $this->displayConfirmation($this->l('Settings updated'));
            }
        }
        return $output.$this->renderForm();
    }
    
    public function renderForm()
    {
        $options = $this->listCMSPages();
        
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cog'
                ),
                'input' => array(
                    array(
                        'type' => 'select',
                        'label' => $this->l('CMS page to display'),
                        'desc' => $this->l('The content of the selected page will be displayed in the pop-up.'),
                        'name' => 'SPLASHSCREEN_ID_CMS',
                        'required' => true,
                        'options' => array(
                            'query' => $options,
                            'id' => 'id',
                            'name' => 'name',
                        ),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Redirect URL'),
                        'name' => 'SPLASHSCREEN_URL',
                        'class' => 'fixed-width-xl',
                        'required' => true,
                        'desc' => $this->l('The user will be returned to this page if the user clicks on Exit.'),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Cookie expiration'),
                        'name' => 'SPLASHSCREEN_EXPIRES',
                        'class' => 'fixed-width-xs',
                        'suffix' => $this->l('days'),
                        'maxlength' => 3,
                        'required' => true,
                        'desc' => $this->l('After this time, the pop-up will appear again.'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
        
        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitSplashScreen';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
        );
        
        return $helper->generateForm(array($fields_form));
    }
    
    public function getConfigFieldsValues()
    {
        return array(
            'SPLASHSCREEN_EXPIRES' => Tools::getValue('SPLASHSCREEN_EXPIRES', Configuration::get('SPLASHSCREEN_EXPIRES')),
            'SPLASHSCREEN_ID_CMS' => Tools::getValue('SPLASHSCREEN_ID_CMS', Configuration::get('SPLASHSCREEN_ID_CMS')),
            'SPLASHSCREEN_URL' => Tools::getValue('SPLASHSCREEN_URL', Configuration::get('SPLASHSCREEN_URL'))
        );
    }
    
    public function listCMSPages()
    {
        $cms = CMS::listCms($this->context->language->id);
        $list = array();
        foreach($cms AS $page)
        {
            $list[] = array(
                'id' => (int)$page['id_cms'],
                'name' => $page['meta_title'].' (ID: '.$page['id_cms'].')',
            );
        }
        return $list;
    }
    
    public function hookHeader($params)
    {
        $this->context->controller->addJqueryPlugin('cooki-plugin');
        $this->context->controller->addJqueryPlugin('cookie-plugin');
        $this->context->controller->addJqueryPlugin('fancybox');
        
        $this->context->controller->addJS($this->_path.'js/splashscreen.js');
        $this->context->controller->addCSS($this->_path.'css/splashscreen.css');
        
        $result = Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'cms_lang WHERE id_cms = '.(int)Configuration::get('SPLASHSCREEN_ID_CMS').' AND id_lang='.(int)$this->context->language->id);
        $splashscreen_text = $result['content'];
        
        $this->smarty->assign(array(
            'splashscreen_cookie' => Configuration::get('SPLASHSCREEN_COOKIE'),
            'splashscreen_expires' => (int)Configuration::get('SPLASHSCREEN_EXPIRES'),
            'splashscreen_text' => $splashscreen_text,
            'splashscreen_url' => Configuration::get('SPLASHSCREEN_URL'),
            'splashscreen_enter' => $this->l('Enter'),
            'splashscreen_exit' => $this->l('Exit'),
        ));
        
        return $this->display(__FILE__, '/splashscreen.tpl');
    }
}