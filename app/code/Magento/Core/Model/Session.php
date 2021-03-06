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
 * @package     Magento_Core
 * @copyright   Copyright (c) 2013 X.commerce, Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Magento\Core\Model;

/**
 * Core session model
 *
 * @todo extend from \Magento\Core\Model\Session\AbstractSession
 *
 * @method null|bool getCookieShouldBeReceived()
 * @method \Magento\Core\Model\Session setCookieShouldBeReceived(bool $flag)
 * @method \Magento\Core\Model\Session unsCookieShouldBeReceived()
 */
class Session extends \Magento\Core\Model\Session\AbstractSession
{
    /**
     * @var \Magento\Math\Random
     */
    protected $mathRandom;

    /**
     * @param \Magento\Core\Model\Session\Context $context
     * @param \Magento\Math\Random $mathRandom
     * @param array $data
     * @param string|null $sessionName
     * @internal param \Magento\Core\Helper\Data $coreData
     */
    public function __construct(
        \Magento\Core\Model\Session\Context $context,
        \Magento\Math\Random $mathRandom,
        array $data = array(),
        $sessionName = null
    ) {
        $this->mathRandom = $mathRandom;
        parent::__construct($context, $data);
        $this->init('core', $sessionName);
    }

    /**
     * Retrieve Session Form Key
     *
     * @return string A 16 bit unique key for forms
     */
    public function getFormKey()
    {
        if (!$this->getData('_form_key')) {
            $this->setData('_form_key', $this->mathRandom->getRandomString(16));
        }
        return $this->getData('_form_key');
    }
}
