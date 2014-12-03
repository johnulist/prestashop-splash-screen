<?php

class splashscreen extends Module
{
    private $_html = '';
    private $_postErrors = array();
    
    public function __construct()
    {
        $this->name = 'splashscreen';
        $this->tab = 'front_office_features';
        $this->version = '0.2';
        $this->author = 'cornug';
        
        $this->bootstrap = true;
        parent::__construct();
        
        $this->displayName = $this->l('Splash screen');
        $this->description = $this->l('Show a splash screen when entering the shop.');
    }
    
    public function install()
    {
        if (!parent::install() ||
            !$this->registerHook('header'))
                return false;
        
        $this->initConfiguration();
        
        return true;
    }
    
    public function uninstall()
    {
        if (!parent::uninstall())
            return false;
        
        $this->deleteConfiguration();
        
        return true;
    }
    
    public function getContent()
    {
        $output = null;
        
        if (Tools::isSubmit('submitSplashScreen'))
        {
            $settings = $this->getConfiguration();
            
            $splashscreen_expires = strval(Tools::getValue('expires'));
            $splashscreen_id_cms = (int)Tools::getValue('id_cms');
            $splashscreen_url = strval(Tools::getValue('url'));
            
            if(!filter_var($splashscreen_url, FILTER_VALIDATE_URL))
                $output .= $this->displayError($this->l('Redirect URL must be a valid URL'));
            
            elseif(!Validate::isInt($splashscreen_expires) ||
                $splashscreen_expires < 1 ||
                $splashscreen_expires > 365)
                    $output .= $this->displayError($this->l('Cookie expiration must be a number of days between 1 and 365'));
            
            else {
                if($splashscreen_expires != (int)$settings['expires']) {
                    $settings['cookie'] = md5(time());
                }
                $settings['expires'] = $splashscreen_expires;
                $settings['id_cms'] = $splashscreen_id_cms;
                $settings['url'] = $splashscreen_url;
                
                $this->updateConfiguration($settings);
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
                        'name' => 'id_cms',
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
                        'name' => 'url',
                        'class' => 'fixed-width-xl',
                        'required' => true,
                        'desc' => $this->l('The user will be returned to this page if the user clicks on Exit.'),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Cookie expiration'),
                        'name' => 'expires',
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
    
    public function hookHeader($params)
    {
        $settings = $this->getConfiguration();
        
        $this->context->controller->addJqueryPlugin('cooki-plugin');
        $this->context->controller->addJqueryPlugin('cookie-plugin');
        $this->context->controller->addJqueryPlugin('fancybox');
        
        $this->context->controller->addJS($this->_path.'js/splashscreen.js');
        $this->context->controller->addCSS($this->_path.'css/splashscreen.css');
        
        $result = Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'cms_lang WHERE id_cms = '.(int)$settings['id_cms'].' AND id_lang='.(int)$this->context->language->id);
        $splashscreen_text = $result['content'];
        
        $this->smarty->assign(array(
            'splashscreen_cookie' => $settings['cookie'],
            'splashscreen_expires' => (int)$settings['expires'],
            'splashscreen_text' => $splashscreen_text,
            'splashscreen_url' => $settings['url'],
            'splashscreen_enter' => $this->l('Enter'),
            'splashscreen_exit' => $this->l('Exit'),
        ));
        
        return $this->display(__FILE__, '/splashscreen.tpl');
    }
    
    protected function listCMSPages()
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
    
    protected function getConfigFieldsValues()
    {
        $settings = $this->getConfiguration();
        
        return array(
            'expires' => Tools::getValue('expires', $settings['expires']),
            'id_cms' => Tools::getValue('id_cms', $settings['id_cms']),
            'url' => Tools::getValue('url', $settings['url'])
        );
    }
    
    protected function initConfiguration()
    {    
        $settings = array(
            'cookie' => md5(time),
            'expires' => 7,
            'id_cms' => 2,
            'url' => 'http://www.google.com/'
        );
        Configuration::updateValue('SPLASHSCREEN_SETTINGS', serialize($settings));
    }
    
    protected function getConfiguration()
    {
        $settings = unserialize(Configuration::get('SPLASHSCREEN_SETTINGS'));
        return $settings;
    }
    
    protected function updateConfiguration($settings)
    {
        Configuration::updateValue('SPLASHSCREEN_SETTINGS', serialize($settings));
    }
    
    protected function deleteConfiguration(){
        Configuration::deleteByName('SPLASHSCREEN_SETTINGS');
    }
}