<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Magento
 * @package     Magento_Backend
 * @copyright   Copyright (c) 2013 X.commerce, Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Magento\Backend\App;

/**
 * Generic backend controller
 *
 * @SuppressWarnings(PHPMD.NumberOfChildren)
 */
abstract class AbstractAction extends \Magento\App\Action\Action
{
    /**
     * Name of "is URLs checked" flag
     */
    const FLAG_IS_URLS_CHECKED = 'check_url_settings';

    /**
     * Session namespace to refer in other places
     */
    const SESSION_NAMESPACE = 'adminhtml';

    /**
     * Array of actions which can be processed without secret key validation
     *
     * @var array
     */
    protected $_publicActions = array();

    /**
     * Namespace for session.
     *
     * @var string
     */
    protected $_sessionNamespace = self::SESSION_NAMESPACE;

    /**
     * @var \Magento\Backend\Helper\Data
     */
    protected $_helper;

    /**
     * @var \Magento\Backend\Model\Session
     */
    protected $_session;

    /**
     * @var \Magento\AuthorizationInterface
     */
    protected $_authorization;

    /**
     * @var \Magento\Backend\Model\Auth
     */
    protected $_auth;

    /**
     * @var \Magento\Backend\Model\Url
     */
    protected $_backendUrl;

    /**
     * @var \Magento\Core\Model\LocaleInterface
     */
    protected $_locale;

    /**
     * @var bool
     */
    protected $_canUseBaseUrl;

    /**
     * @var \Magento\Core\App\Action\FormKeyValidator
     */
    protected $_formKeyValidator;

    /**
     * @var \Magento\App\Action\Title
     */
    protected $_title;

    /**
     * @param \Magento\Backend\App\Action\Context $context
     */
    public function __construct(Action\Context $context)
    {
        parent::__construct($context);
        $this->_authorization = $context->getAuthorization();
        $this->_auth = $context->getAuth();
        $this->_helper = $context->getHelper();
        $this->_backendUrl = $context->getBackendUrl();
        $this->_formKeyValidator = $context->getFormKeyValidator();
        $this->_title = $context->getTitle();
        $this->_locale = $context->getLocale();
        $this->_canUseBaseUrl = $context->getCanUseBaseUrl();
        $this->_session = $context->getSession();
    }

    protected function _isAllowed()
    {
        return true;
    }

    /**
     * Retrieve adminhtml session model object
     *
     * @return \Magento\Backend\Model\Session
     */
    protected function _getSession()
    {
        return $this->_session;
    }

    /**
     * Define active menu item in menu block
     * @param string $itemId current active menu item
     * @return \Magento\Backend\App\AbstractAction
     */
    protected function _setActiveMenu($itemId)
    {
        /** @var $menuBlock \Magento\Backend\Block\Menu */
        $menuBlock = $this->_view->getLayout()->getBlock('menu');
        $menuBlock->setActive($itemId);
        $parents = $menuBlock->getMenuModel()->getParentItems($itemId);
        $parents = array_reverse($parents);
        foreach ($parents as $item) {
            /** @var $item \Magento\Backend\Model\Menu\Item */
            $this->_title->add($item->getTitle(), true);
        }
        return $this;
    }

    /**
     * @param $label
     * @param $title
     * @param null $link
     * @return \Magento\Backend\App\AbstractAction
     */
    protected function _addBreadcrumb($label, $title, $link=null)
    {
        $this->_view->getLayout()->getBlock('breadcrumbs')->addLink($label, $title, $link);
        return $this;
    }

    /**
     * @param \Magento\View\Block\AbstractBlock $block
     * @return \Magento\Backend\App\AbstractAction
     */
    protected function _addContent(\Magento\View\Block\AbstractBlock $block)
    {
        return $this->_moveBlockToContainer($block, 'content');
    }

    /**
     * @param \Magento\View\Block\AbstractBlock $block
     * @return \Magento\Backend\App\AbstractAction
     */
    protected function _addLeft(\Magento\View\Block\AbstractBlock $block)
    {
        return $this->_moveBlockToContainer($block, 'left');
    }

    /**
     * @param \Magento\View\Block\AbstractBlock $block
     * @return \Magento\Backend\App\AbstractAction
     */
    protected function _addJs(\Magento\View\Block\AbstractBlock $block)
    {
        return $this->_moveBlockToContainer($block, 'js');
    }

    /**
     * Set specified block as an anonymous child to specified container
     *
     * The block will be moved to the container from previous parent after all other elements
     *
     * @param \Magento\View\Block\AbstractBlock $block
     * @param string $containerName
     * @return \Magento\Backend\App\AbstractAction
     */
    private function _moveBlockToContainer(\Magento\View\Block\AbstractBlock $block, $containerName)
    {
        $this->_view->getLayout()->setChild($containerName, $block->getNameInLayout(), '');
        return $this;
    }

    /**
     * @param \Magento\App\RequestInterface $request
     * @return $this|mixed
     */
    public function dispatch(\Magento\App\RequestInterface $request)
    {
        if (!$this->_processUrlKeys()) {
            return parent::dispatch($request);
        }

        if ($request->isDispatched() && $request->getActionName() !== 'denied' && !$this->_isAllowed()) {
            $this->_forward('denied');
            $this->_actionFlag->set('', self::FLAG_NO_DISPATCH, true);
            return parent::dispatch($request);
        }

        if ($this->_isUrlChecked()) {
            $this->_actionFlag->set('', self::FLAG_IS_URLS_CHECKED, true);
        }

        $this->_processLocaleSettings();

        return parent::dispatch($request);
    }

    /**
     * Check whether url is checked
     *
     * @return bool
     */
    protected function _isUrlChecked()
    {
        return !$this->_actionFlag->get('', self::FLAG_IS_URLS_CHECKED)
            && !$this->getRequest()->getParam('forwarded')
            && !$this->_getSession()->getIsUrlNotice(true)
            && !$this->_canUseBaseUrl;
    }

    /**
     * Check url keys. If non valid - redirect
     *
     * @return bool
     */
    public function _processUrlKeys()
    {
        $_isValidFormKey = true;
        $_isValidSecretKey = true;
        $_keyErrorMsg = '';
        if ($this->_auth->isLoggedIn()) {
            if ($this->getRequest()->isPost()) {
                $_isValidFormKey = $this->_formKeyValidator->validate($this->getRequest());
                $_keyErrorMsg = __('Invalid Form Key. Please refresh the page.');
            } elseif ($this->_backendUrl->useSecretKey()) {
                $_isValidSecretKey = $this->_validateSecretKey();
                $_keyErrorMsg = __('You entered an invalid Secret Key. Please refresh the page.');
            }
        }
        if (!$_isValidFormKey || !$_isValidSecretKey) {
            $this->_actionFlag->set('', self::FLAG_NO_DISPATCH, true);
            $this->_actionFlag->set('', self::FLAG_NO_POST_DISPATCH, true);
            if ($this->getRequest()->getQuery('isAjax', false) || $this->getRequest()->getQuery('ajax', false)) {
                $this->getResponse()->setBody($this->_objectManager->get('Magento\Core\Helper\Data')->jsonEncode(array(
                    'error' => true,
                    'message' => $_keyErrorMsg
                )));
            } else {
                $this->_redirect($this->_backendUrl->getStartupPageUrl());
            }
            return false;
        }
        return true;
    }

    /**
     * Set session locale,
     * process force locale set through url params
     *
     * @return \Magento\Backend\App\AbstractAction
     */
    protected function _processLocaleSettings()
    {
        $forceLocale = $this->getRequest()->getParam('locale', null);
        if ($this->_objectManager->get('Magento\Core\Model\Locale\Validator')->isValid($forceLocale)) {
            $this->_getSession()->setSessionLocale($forceLocale);
        }

        if (is_null($this->_getSession()->getLocale())) {
            $this->_getSession()->setLocale($this->_locale->getLocaleCode());
        }

        return $this;
    }

    public function deniedAction()
    {
        $this->getResponse()->setHeader('HTTP/1.1', '403 Forbidden');
        if (!$this->_auth->isLoggedIn()) {
            $this->_redirect('*/auth/login');
            return;
        }
        $this->_view->loadLayout(array('default', 'adminhtml_denied'));
        $this->_view->renderLayout();
    }

    /**
     * No route action
     *
     * @param null $coreRoute
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function norouteAction($coreRoute = null)
    {
        $this->getResponse()->setHeader('HTTP/1.1', '404 Not Found');
        $this->getResponse()->setHeader('Status', '404 File not found');
        $this->_view->loadLayout(array('default', 'adminhtml_noroute'));
        $this->_view->renderLayout();
    }

    /**
     * Set redirect into response
     *
     * @param   string $path
     * @param   array $arguments
     * @return \Magento\Backend\App\AbstractAction
     */
    protected function _redirect($path, $arguments=array())
    {
        $this->_getSession()->setIsUrlNotice($this->_actionFlag->get('', self::FLAG_IS_URLS_CHECKED));
        $this->getResponse()->setRedirect($this->getUrl($path, $arguments));
        return $this;
    }

    protected function _forward($action, $controller = null, $module = null, array $params = null)
    {
        $this->_getSession()->setIsUrlNotice($this->_actionFlag->get('', self::FLAG_IS_URLS_CHECKED));
        return parent::_forward($action, $controller, $module, $params);
    }

    /**
     * Generate url by route and parameters
     *
     * @param   string $route
     * @param   array $params
     * @return  string
     */
    public function getUrl($route = '', $params=array())
    {
        return $this->_helper->getUrl($route, $params);
    }

    /**
     * Validate Secret Key
     *
     * @return bool
     */
    protected function _validateSecretKey()
    {
        if (is_array($this->_publicActions) && in_array($this->getRequest()->getActionName(), $this->_publicActions)) {
            return true;
        }

        $secretKey = $this->getRequest()->getParam(\Magento\Backend\Model\Url::SECRET_KEY_PARAM_NAME, null);
        if (!$secretKey || $secretKey != $this->_backendUrl->getSecretKey()) {
            return false;
        }
        return true;
    }

    /**
     * Render specified template
     *
     * @param string $tplName
     * @param array $data parameters required by template
     */
    protected function _outTemplate($tplName, $data = array())
    {
        $this->_view->getLayout()->initMessages('Magento\Backend\Model\Session');
        $block = $this->_view->getLayout()
            ->createBlock('Magento\Backend\Block\Template')->setTemplate("{$tplName}.phtml");
        foreach ($data as $index => $value) {
            $block->assign($index, $value);
        }
        $html = $block->toHtml();
        $this->_objectManager->get('Magento\Core\Model\Translate')->processResponseBody($html);
        $this->getResponse()->setBody($html);
    }
}