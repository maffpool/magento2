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
 * @copyright Copyright (c) 2013 X.commerce, Inc. (http://www.magentocommerce.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
namespace Magento\Core\Model\File\Storage;

class RequestTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Magento\Core\Model\File\Storage\Request
     */
    protected $_model;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $_requestMock;

    /**
     * @var string
     */
    protected $_workingDir = '..var';

    /**
     * @var string
     */
    protected $_pathInfo = 'PathInfo';

    protected function setUp()
    {
        $path = '..PathInfo';
        $this->_requestMock = $this->getMock('\Magento\App\Request\Http', array(), array(), '', false);
        $this->_requestMock->expects($this->once())->method('getPathInfo')->will($this->returnValue($path));
        $this->_model = new \Magento\Core\Model\File\Storage\Request($this->_workingDir, $this->_requestMock);
    }

    protected function tearDown()
    {
        unset($this->_model);
        unset($this->_requestMock);
    }

    public function testGetPathInfo()
    {
        $this->assertEquals($this->_pathInfo, $this->_model->getPathInfo());
    }

    public function testGetFilePath()
    {
        $this->assertEquals($this->_workingDir . DS . $this->_pathInfo, $this->_model->getFilePath());
    }
}
