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
 * @package     unit_tests
 * @copyright   Copyright (c) 2013 X.commerce, Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Helper class for basic object retrieving, such as blocks, models etc...
 */
namespace Magento\TestFramework\Helper;

class ObjectManager
{
    /**
     * Special cases configuration
     *
     * @var array
     */
    protected $_specialCases = array(
        'Magento\Core\Model\Resource\AbstractResource' => '_getResourceModelMock',
        'Magento\Core\Model\Translate' => '_getTranslatorMock',
    );

    /**
     * Test object
     *
     * @var \PHPUnit_Framework_TestCase
     */
    protected $_testObject;

    /**
     * Class constructor
     *
     * @param \PHPUnit_Framework_TestCase $testObject
     */
    public function __construct(\PHPUnit_Framework_TestCase $testObject)
    {
        $this->_testObject = $testObject;
    }

    /**
     * Get mock for argument
     *
     * @param string $argClassName
     * @param array $originalArguments
     * @return null|object|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function _createArgumentMock($argClassName, array $originalArguments)
    {
        $object = null;
        if ($argClassName) {
            $object = $this->_processSpecialCases($argClassName, $originalArguments);
            if (null === $object) {
                $object = $this->_getMockWithoutConstructorCall($argClassName);
            }
        }
        return $object;
    }

    /**
     * Process special cases
     *
     * @param string $className
     * @param array $arguments
     * @return null|object
     */
    protected function _processSpecialCases($className, $arguments)
    {
        $object = null;
        $interfaces = class_implements($className);
        if (in_array('Magento\ObjectManager\ContextInterface', $interfaces)) {
            $object = $this->getObject($className, $arguments);
        } elseif (isset($this->_specialCases[$className])) {
            $method = $this->_specialCases[$className];
            $object = $this->$method($className);
        }

        return $object;
    }

    /**
     * Retrieve specific mock of core resource model
     *
     * @return \Magento\Core\Model\Resource\Resource|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function _getResourceModelMock()
    {
        $resourceMock = $this->_testObject->getMock('Magento\Core\Model\Resource\Resource',
            array('getIdFieldName', '__sleep', '__wakeup'),
            array(), '', false
        );
        $resourceMock->expects($this->_testObject->any())
            ->method('getIdFieldName')
            ->will($this->_testObject->returnValue('id'));

        return $resourceMock;
    }

    /**
     * Retrieve mock of core translator model
     *
     * @param string $className
     * @return \Magento\Core\Model\Translate|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function _getTranslatorMock($className)
    {
        $translator = $this->_testObject->getMockBuilder($className)
            ->disableOriginalConstructor()
            ->setMethods(array('translate'))
            ->getMock();
        $translateCallback = function ($arguments) {
            return is_array($arguments) ? vsprintf(array_shift($arguments), $arguments) : '';
        };
        $translator->expects($this->_testObject->any())
            ->method('translate')
            ->will($this->_testObject->returnCallback($translateCallback));
        return $translator;
    }

    /**
     * Get mock without call of original constructor
     *
     * @param string $className
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function _getMockWithoutConstructorCall($className)
    {
        $class = new \ReflectionClass($className);
        $mock = null;
        if ($class->isAbstract()) {
            $mock = $this->_testObject->getMockForAbstractClass($className, array(), '', false, false);
        } else {
            $mock = $this->_testObject->getMock($className, array(), array(), '', false, false);
        }
        return $mock;
    }

    /**
     * Get class instance
     *
     * @param $className
     * @param array $arguments
     * @return object
     */
    public function getObject($className, array $arguments = array())
    {
        $constructArguments = $this->getConstructArguments($className, $arguments);
        $reflectionClass = new \ReflectionClass($className);
        return $reflectionClass->newInstanceArgs($constructArguments);
    }

    /**
     * Retrieve list of arguments that used for new object instance creation
     *
     * @param string $className
     * @param array $arguments
     * @return array
     */
    public function getConstructArguments($className, array $arguments = array())
    {
        $constructArguments = array();
        if (!method_exists($className, '__construct')) {
            return $constructArguments;
        }
        $method = new \ReflectionMethod($className, '__construct');

        foreach ($method->getParameters() as $parameter) {
            $parameterName = $parameter->getName();
            $argClassName = null;
            $defaultValue = null;

            if (isset($arguments[$parameterName])) {
                $constructArguments[$parameterName] = $arguments[$parameterName];
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $defaultValue =  $parameter->getDefaultValue();
            }

            try {
                if ($parameter->getClass()) {
                    $argClassName =  $parameter->getClass()->getName();
                }
                $object = $this->_createArgumentMock($argClassName, $arguments);
            } catch (\ReflectionException $e) {
                $parameterString = $parameter->__toString();
                $firstPosition = strpos($parameterString, '<required>');
                if ($firstPosition !== false) {
                    $parameterString = substr($parameterString, $firstPosition + 11);
                    $parameterString = substr($parameterString, 0, strpos($parameterString, ' '));
                    $object = $this->_testObject->getMock(
                        $parameterString, array(), array(), '', false
                    );
                }
            }

            $constructArguments[$parameterName] = null === $object ? $defaultValue : $object;
        }
        return $constructArguments;
    }
}
