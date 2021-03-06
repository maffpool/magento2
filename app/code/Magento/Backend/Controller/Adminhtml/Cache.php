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

namespace Magento\Backend\Controller\Adminhtml;

use Magento\Backend\App\Action;

class Cache extends \Magento\Backend\App\Action
{
    /**
     * @var \Magento\App\Cache\TypeListInterface
     */
    private $_cacheTypeList;

    /**
     * @var \Magento\App\Cache\StateInterface
     */
    private $_cacheState;

    /**
     * @var \Magento\App\Cache\Frontend\Pool
     */
    private $_cacheFrontendPool;

    /**
     * @param Action\Context $context
     * @param \Magento\App\Cache\TypeListInterface $cacheTypeList
     * @param \Magento\App\Cache\StateInterface $cacheState
     * @param \Magento\App\Cache\Frontend\Pool $cacheFrontendPool
     */
    public function __construct(
        Action\Context $context,
        \Magento\App\Cache\TypeListInterface $cacheTypeList,
        \Magento\App\Cache\StateInterface $cacheState,
        \Magento\App\Cache\Frontend\Pool $cacheFrontendPool
    ) {
        parent::__construct($context);
        $this->_cacheTypeList = $cacheTypeList;
        $this->_cacheState = $cacheState;
        $this->_cacheFrontendPool = $cacheFrontendPool;
    }

    /**
     * Retrieve session model
     *
     * @return \Magento\Adminhtml\Model\Session
     */
    protected function _getSession()
    {
        return $this->_objectManager->get('Magento\Adminhtml\Model\Session');
    }

    /**
     * Display cache management grid
     */
    public function indexAction()
    {
        $this->_title->add(__('Cache Management'));

        $this->_view->loadLayout();
        $this->_setActiveMenu('Magento_Adminhtml::system_cache');
        $this->_view->renderLayout();
    }

    /**
     * Flush cache storage
     */
    public function flushAllAction()
    {
        $this->_eventManager->dispatch('adminhtml_cache_flush_all');
        /** @var $cacheFrontend \Magento\Cache\FrontendInterface */
        foreach ($this->_cacheFrontendPool as $cacheFrontend) {
            $cacheFrontend->getBackend()->clean();
        }
        $this->_getSession()->addSuccess(
            __("You flushed the cache storage.")
        );
        $this->_redirect('adminhtml/*');
    }

    /**
     * Flush all magento cache
     */
    public function flushSystemAction()
    {
        /** @var $cacheFrontend \Magento\Cache\FrontendInterface */
        foreach ($this->_cacheFrontendPool as $cacheFrontend) {
            $cacheFrontend->clean();
        }
        $this->_eventManager->dispatch('adminhtml_cache_flush_system');
        $this->_getSession()->addSuccess(
            __("The Magento cache storage has been flushed.")
        );
        $this->_redirect('adminhtml/*');
    }

    /**
     * Mass action for cache enabling
     */
    public function massEnableAction()
    {
        try {
            $types = $this->getRequest()->getParam('types');
            $updatedTypes = 0;
            $this->_validateTypes($types);
            foreach ($types as $code) {
                if (!$this->_cacheState->isEnabled($code)) {
                    $this->_cacheState->setEnabled($code, true);
                    $updatedTypes++;
                }
            }
            if ($updatedTypes > 0) {
                $this->_cacheState->persist();
                $this->_getSession()->addSuccess(
                    __("%1 cache type(s) enabled.", $updatedTypes)
                );
            }
        } catch (\Magento\Core\Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        }
        catch (\Exception $e) {
            $this->_getSession()->addException(
                $e,
                __('An error occurred while enabling cache.')
            );
        }
        $this->_redirect('adminhtml/*');
    }

    /**
     * Mass action for cache disabling
     */
    public function massDisableAction()
    {
        try {
            $types = $this->getRequest()->getParam('types');
            $updatedTypes = 0;
            $this->_validateTypes($types);
            foreach ($types as $code) {
                if ($this->_cacheState->isEnabled($code)) {
                    $this->_cacheState->setEnabled($code, false);
                    $updatedTypes++;
                }
                $this->_cacheTypeList->cleanType($code);
            }
            if ($updatedTypes > 0) {
                $this->_cacheState->persist();
                $this->_getSession()->addSuccess(
                    __("%1 cache type(s) disabled.", $updatedTypes)
                );
            }
        } catch (\Magento\Core\Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        }
        catch (\Exception $e) {
            $this->_getSession()->addException(
                $e,
                __('An error occurred while disabling cache.')
            );
        }
        $this->_redirect('adminhtml/*');
    }

    /**
     * Mass action for cache refresh
     */
    public function massRefreshAction()
    {
        try {
            $types = $this->getRequest()->getParam('types');
            $updatedTypes = 0;
            $this->_validateTypes($types);
            foreach ($types as $type) {
                $this->_cacheTypeList->cleanType($type);
                $this->_eventManager->dispatch('adminhtml_cache_refresh_type', array('type' => $type));
                $updatedTypes++;
            }
            if ($updatedTypes > 0) {
                $this->_getSession()->addSuccess(
                    __("%1 cache type(s) refreshed.", $updatedTypes)
                );
            }
        } catch (\Magento\Core\Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        }
        catch (\Exception $e) {
            $this->_getSession()->addException(
                $e,
                __('An error occurred while refreshing cache.')
            );
        }
        $this->_redirect('adminhtml/*');
    }

    /**
     * Check whether specified cache types exist
     *
     * @param array $types
     */
    protected function _validateTypes(array $types)
    {
        if (empty($types)) {
            return;
        }
        $allTypes = array_keys($this->_cacheTypeList->getTypes());
        $invalidTypes = array_diff($types, $allTypes);
        if (count($invalidTypes) > 0) {
            throw new \Magento\Core\Exception(__("Specified cache type(s) don't exist: " . join(', ', $invalidTypes)));
        }
    }

    /**
     * Clean JS/css files cache
     */
    public function cleanMediaAction()
    {
        try {
            $this->_objectManager->get('Magento\Core\Model\Page\Asset\MergeService')
                ->cleanMergedJsCss();
            $this->_eventManager->dispatch('clean_media_cache_after');
            $this->_getSession()->addSuccess(
                __('The JavaScript/CSS cache has been cleaned.')
            );
        }
        catch (\Magento\Core\Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        }
        catch (\Exception $e) {
            $this->_getSession()->addException(
                $e,
                __('An error occurred while clearing the JavaScript/CSS cache.')
            );
        }
        $this->_redirect('adminhtml/*');
    }

    /**
     * Clean JS/css files cache
     */
    public function cleanImagesAction()
    {
        try {
            $this->_objectManager->create('Magento\Catalog\Model\Product\Image')->clearCache();
            $this->_eventManager->dispatch('clean_catalog_images_cache_after');
            $this->_getSession()->addSuccess(
                __('The image cache was cleaned.')
            );
        }
        catch (\Magento\Core\Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        }
        catch (\Exception $e) {
            $this->_getSession()->addException(
                $e,
                __('An error occurred while clearing the image cache.')
            );
        }
        $this->_redirect('adminhtml/*');
    }

    /**
     * Check if cache management is allowed
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Magento_Adminhtml::cache');
    }
}
