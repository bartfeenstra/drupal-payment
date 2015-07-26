<?php

/**
 * @file
 * Contains \Drupal\Tests\payment\Unit\Payment\PaymentAccessControlHandlerUnitTest.
 */

namespace Drupal\Tests\payment\Unit\Entity\Payment;

use Drupal\Core\DependencyInjection\Container;
use Drupal\payment\Entity\Payment\PaymentAccessControlHandler;
use Drupal\payment\Plugin\Payment\Method\PaymentMethodCapturePaymentInterface;
use Drupal\payment\Plugin\Payment\Method\PaymentMethodInterface;
use Drupal\payment\Plugin\Payment\Method\PaymentMethodRefundPaymentInterface;
use Drupal\payment\Plugin\Payment\Method\PaymentMethodUpdatePaymentStatusInterface;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\payment\Entity\Payment\PaymentAccessControlHandler
 *
 * @group Payment
 */
class PaymentAccessControlHandlerUnitTest extends UnitTestCase {

  /**
   * The access controller under test.
   *
   * @var \Drupal\payment\Entity\Payment\PaymentAccessControlHandler
   */
  protected $accessControlHandler;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    $cache_context_manager = $this->getMockBuilder('\Drupal\Core\Cache\Context\CacheContextsManager')
      ->disableOriginalConstructor()
      ->getMock();

    $container = new Container();
    $container->set('cache_contexts_manager', $cache_context_manager);
    \Drupal::setContainer($container);

    $entity_type = $this->getMock('\Drupal\Core\Entity\EntityTypeInterface');

    $this->accessControlHandler = new PaymentAccessControlHandler($entity_type);
  }

  /**
   * Gets a mock payment.
   *
   * @return \Drupal\payment\Entity\PaymentInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected function getMockPayment() {
    $payment = $this->getMock('\Drupal\payment\Entity\PaymentInterface');
    $payment->expects($this->any())
      ->method('getCacheContexts')
      ->willReturn([]);

    return $payment;
  }

  /**
   * @covers ::checkAccess
   *
   * @dataProvider providerTestCheckAccessCapture
   */
  public function testCheckAccessCapture($expected, $payment_method_interface, $payment_method_capture_access, $has_permissions) {
    $operation = 'capture';
    $language_code = $this->randomMachineName();

    $account = $this->getMock('\Drupal\Core\Session\AccountInterface');
    $map = array(
      array('payment.payment.capture.any', $has_permissions),
      array('payment.payment.capture.own', $has_permissions),
    );
    $account->expects($this->any())
      ->method('hasPermission')
      ->will($this->returnValueMap($map));

    $payment_method = $this->getMock($payment_method_interface);
    $payment_method->expects($this->any())
      ->method('capturePaymentAccess')
      ->with($account)
      ->will($this->returnValue($payment_method_capture_access));

    $payment = $this->getMockPayment();
    $payment->expects($this->atLeastOnce())
      ->method('getPaymentMethod')
      ->will($this->returnValue($payment_method));

    $payment->expects($this->any())
      ->method('getCacheTags')
      ->willReturn(array('payment'));

    $method = new \ReflectionMethod($this->accessControlHandler, 'checkAccess');
    $method->setAccessible(TRUE);

    $this->assertSame($expected, $method->invokeArgs($this->accessControlHandler, array($payment, $operation, $language_code, $account))->isAllowed());
  }

  /**
   * Provides data to self::testCheckAccessCapture().
   */
  public function providerTestCheckAccessCapture() {
    return array(
      array(TRUE, '\Drupal\Tests\payment\Unit\Entity\Payment\PaymentAccessUnitTestDummyPaymentMethodCapturePaymentInterface', TRUE, TRUE),
      array(FALSE, '\Drupal\Tests\payment\Unit\Entity\Payment\PaymentAccessUnitTestDummyPaymentMethodCapturePaymentInterface', FALSE, TRUE),
      array(FALSE, '\Drupal\Tests\payment\Unit\Entity\Payment\PaymentAccessUnitTestDummyPaymentMethodCapturePaymentInterface', TRUE, FALSE),
      array(FALSE, '\Drupal\Tests\payment\Unit\Entity\Payment\PaymentAccessUnitTestDummyPaymentMethodCapturePaymentInterface', FALSE, FALSE),
      array(FALSE, '\Drupal\payment\Plugin\Payment\Method\PaymentMethodInterface', TRUE, TRUE),
    );
  }

  /**
   * @covers ::checkAccess
   *
   * @dataProvider providerTestCheckAccessRefund
   */
  public function testCheckAccessRefund($expected, $payment_method_interface, $payment_method_refund_access, $has_permissions) {
    $operation = 'refund';
    $language_code = $this->randomMachineName();

    $account = $this->getMock('\Drupal\Core\Session\AccountInterface');
    $map = array(
      array('payment.payment.refund.any', $has_permissions),
      array('payment.payment.refund.own', $has_permissions),
    );
    $account->expects($this->any())
      ->method('hasPermission')
      ->will($this->returnValueMap($map));

    $payment_method = $this->getMock($payment_method_interface);
    $payment_method->expects($this->any())
      ->method('refundPaymentAccess')
      ->with($account)
      ->will($this->returnValue($payment_method_refund_access));

    $payment = $this->getMockPayment();
    $payment->expects($this->atLeastOnce())
      ->method('getPaymentMethod')
      ->will($this->returnValue($payment_method));

    $payment->expects($this->any())
      ->method('getCacheTags')
      ->willReturn(array('payment'));

    $method = new \ReflectionMethod($this->accessControlHandler, 'checkAccess');
    $method->setAccessible(TRUE);

    $this->assertSame($expected, $method->invokeArgs($this->accessControlHandler, array($payment, $operation, $language_code, $account))->isAllowed());
  }

  /**
   * Provides data to self::testCheckAccessRefund().
   */
  public function providerTestCheckAccessRefund() {
    return array(
      array(TRUE, '\Drupal\Tests\payment\Unit\Entity\Payment\PaymentAccessUnitTestDummyPaymentMethodRefundPaymentInterface', TRUE, TRUE),
      array(FALSE, '\Drupal\Tests\payment\Unit\Entity\Payment\PaymentAccessUnitTestDummyPaymentMethodRefundPaymentInterface', FALSE, TRUE),
      array(FALSE, '\Drupal\Tests\payment\Unit\Entity\Payment\PaymentAccessUnitTestDummyPaymentMethodRefundPaymentInterface', TRUE, FALSE),
      array(FALSE, '\Drupal\Tests\payment\Unit\Entity\Payment\PaymentAccessUnitTestDummyPaymentMethodRefundPaymentInterface', FALSE, FALSE),
      array(FALSE, '\Drupal\payment\Plugin\Payment\Method\PaymentMethodInterface', TRUE, TRUE),
    );
  }

  /**
   * @covers ::checkAccess
   */
  public function testCheckAccessUpdateStatusWithAccess() {
    $operation = 'update_status';
    $language_code = $this->randomMachineName();

    $account = $this->getMock('\Drupal\Core\Session\AccountInterface');
    $account->expects($this->at(0))
      ->method('hasPermission')
      ->with('payment.payment.update_status.any')
      ->will($this->returnValue(TRUE));
    $account->expects($this->at(1))
      ->method('hasPermission')
      ->with('payment.payment.update_status.own')
      ->will($this->returnValue(FALSE));

    $payment_method = $this->getMock('\Drupal\Tests\payment\Unit\Entity\Payment\PaymentAccessUnitTestDummyPaymentMethodUpdateStatusInterface');
    $payment_method->expects($this->once())
      ->method('updatePaymentStatusAccess')
      ->with($account)
      ->will($this->returnValue(TRUE));

    $payment = $this->getMockPayment();
    $payment->expects($this->atLeastOnce())
      ->method('getPaymentMethod')
      ->will($this->returnValue($payment_method));
    $payment->expects($this->any())
      ->method('getCacheTags')
      ->willReturn(array('payment'));

    $class = new \ReflectionClass($this->accessControlHandler);
    $method = $class->getMethod('checkAccess');
    $method->setAccessible(TRUE);
    $this->assertTrue($method->invokeArgs($this->accessControlHandler, array($payment, $operation, $language_code, $account))->isAllowed());
  }

  /**
   * @covers ::checkAccess
   */
  public function testCheckAccessUpdateStatusWithoutAccess() {
    $operation = 'update_status';
    $language_code = $this->randomMachineName();

    $account = $this->getMock('\Drupal\Core\Session\AccountInterface');
    $account->expects($this->never())
      ->method('hasPermission');

    $payment_method = $this->getMock('\Drupal\Tests\payment\Unit\Entity\Payment\PaymentAccessUnitTestDummyPaymentMethodUpdateStatusInterface');
    $payment_method->expects($this->once())
      ->method('updatePaymentStatusAccess')
      ->with($account)
      ->will($this->returnValue(FALSE));

    $payment = $this->getMockPayment();
    $payment->expects($this->atLeastOnce())
      ->method('getPaymentMethod')
      ->will($this->returnValue($payment_method));

    $class = new \ReflectionClass($this->accessControlHandler);
    $method = $class->getMethod('checkAccess');
    $method->setAccessible(TRUE);
    $this->assertFalse($method->invokeArgs($this->accessControlHandler, array($payment, $operation, $language_code, $account))->isAllowed());
  }

  /**
   * @covers ::checkAccess
   *
   * @dataProvider providerTestCheckAccessComplete
   */
  public function testCheckAccessComplete($expected_access, $account_id, $payment_owner_id, $payment_execution_has_completed) {
    $operation = 'complete';
    $language_code = $this->randomMachineName();

    $account = $this->getMock('\Drupal\Core\Session\AccountInterface');
    $account->expects($this->any())
      ->method('id')
      ->willReturn($account_id);
    $account->expects($this->never())
      ->method('hasPermission');

    $payment_execution_result = $this->getMock('\Drupal\payment\PaymentExecutionResultInterface');
    $payment_execution_result->expects($this->any())
      ->method('hasCompleted')
      ->willReturn($payment_execution_has_completed);

    $payment_method = $this->getMock('\Drupal\Tests\payment\Unit\Entity\Payment\PaymentAccessUnitTestDummyPaymentMethodUpdateStatusInterface');
    $payment_method->expects($this->once())
      ->method('getPaymentExecutionResult')
      ->willReturn($payment_execution_result);

    $payment = $this->getMockPayment();
    $payment->expects($this->atLeastOnce())
      ->method('getOwnerId')
      ->willReturn($payment_owner_id);
    $payment->expects($this->atLeastOnce())
      ->method('getPaymentMethod')
      ->willReturn($payment_method);

    $class = new \ReflectionClass($this->accessControlHandler);
    $method = $class->getMethod('checkAccess');
    $method->setAccessible(TRUE);
    $this->assertSame($expected_access, $method->invokeArgs($this->accessControlHandler, array($payment, $operation, $language_code, $account))->isAllowed());
  }

  /**
   * Provides data to self::testCheckAccessComplete()
   */
  public function providerTestCheckAccessComplete() {
    return [
      [TRUE, 7, 7, FALSE],
      [FALSE, 7, 7, TRUE],
      [FALSE, mt_rand(), mt_rand(), FALSE],
    ];
  }

  /**
   * @covers ::checkAccess
   */
  public function testCheckAccessCompleteWithoutPaymentMethod() {
    $operation = 'complete';
    $language_code = $this->randomMachineName();

    $account = $this->getMock('\Drupal\Core\Session\AccountInterface');
    $account->expects($this->never())
      ->method('hasPermission');

    $payment = $this->getMockPayment();
    $payment->expects($this->atLeastOnce())
      ->method('getPaymentMethod')
      ->willReturn(NULL);

    $class = new \ReflectionClass($this->accessControlHandler);
    $method = $class->getMethod('checkAccess');
    $method->setAccessible(TRUE);
    $this->assertFalse($method->invokeArgs($this->accessControlHandler, array($payment, $operation, $language_code, $account))->isAllowed());
  }

  /**
   * @covers ::checkAccess
   * @covers ::checkAccessPermission
   */
  public function testCheckAccessWithoutPermission() {
    $operation = $this->randomMachineName();
    $language_code = $this->randomMachineName();
    $account = $this->getMock('\Drupal\Core\Session\AccountInterface');
    $account->expects($this->any())
      ->method('hasPermission')
      ->will($this->returnValue(FALSE));
    $payment = $this->getMockPayment();
    $payment->expects($this->any())
      ->method('getCacheTags')
      ->willReturn(array('payment'));

    $class = new \ReflectionClass($this->accessControlHandler);
    $method = $class->getMethod('checkAccess');
    $method->setAccessible(TRUE);
    $this->assertFalse($method->invokeArgs($this->accessControlHandler, array($payment, $operation, $language_code, $account))->isAllowed());
  }

  /**
   * @covers ::checkAccess
   * @covers ::checkAccessPermission
   */
  public function testCheckAccessWithAnyPermission() {
    $operation = $this->randomMachineName();
    $language_code = $this->randomMachineName();
    $account = $this->getMock('\Drupal\Core\Session\AccountInterface');
    $account->expects($this->at(0))
      ->method('hasPermission')
      ->with('payment.payment.' . $operation . '.any')
      ->will($this->returnValue(TRUE));
    $account->expects($this->at(1))
      ->method('hasPermission')
      ->with('payment.payment.' . $operation . '.own')
      ->will($this->returnValue(FALSE));

    $payment = $this->getMockPayment();
    $payment->expects($this->any())
      ->method('getCacheTags')
      ->willReturn(array('payment'));

    $class = new \ReflectionClass($this->accessControlHandler);
    $method = $class->getMethod('checkAccess');
    $method->setAccessible(TRUE);
    $this->assertTrue($method->invokeArgs($this->accessControlHandler, array($payment, $operation, $language_code, $account))->isAllowed());
  }

  /**
   * @covers ::checkAccess
   * @covers ::checkAccessPermission
   */
  public function testCheckAccessWithOwnPermission() {
    $owner_id = mt_rand();
    $operation = $this->randomMachineName();
    $language_code = $this->randomMachineName();
    $account = $this->getMock('\Drupal\Core\Session\AccountInterface');
    $account->expects($this->any())
      ->method('id')
      ->will($this->returnValue($owner_id));
    $map = array(
      array('payment.payment.' . $operation . '.any', FALSE),
      array('payment.payment.' . $operation . '.own', TRUE),
    );
    $account->expects($this->any())
      ->method('hasPermission')
      ->will($this->returnValueMap($map));
    $payment = $this->getMockPayment();
    $payment->expects($this->at(0))
      ->method('getOwnerId')
      ->will($this->returnValue($owner_id));
    $payment->expects($this->at(1))
      ->method('getOwnerId')
      ->will($this->returnValue($owner_id + 1));
    $payment->expects($this->any())
      ->method('getCacheTags')
      ->willReturn(array('payment'));

    $class = new \ReflectionClass($this->accessControlHandler);
    $method = $class->getMethod('checkAccess');
    $method->setAccessible(TRUE);
    $this->assertTrue($method->invokeArgs($this->accessControlHandler, array($payment, $operation, $language_code, $account))->isAllowed());
    $this->assertFalse($method->invokeArgs($this->accessControlHandler, array($payment, $operation, $language_code, $account))->isAllowed());
  }

  /**
   * @covers ::checkCreateAccess
   */
  public function testCheckCreateAccess() {
    $account = $this->getMock('\Drupal\Core\Session\AccountInterface');
    $context = [];

    $class = new \ReflectionClass($this->accessControlHandler);
    $method = $class->getMethod('checkCreateAccess');
    $method->setAccessible(TRUE);
    $this->assertTrue($method->invokeArgs($this->accessControlHandler, array($account, $context))->isAllowed());
  }

  /**
   * @covers ::getCache
   */
  public function testGetCache() {
    $account = $this->getMock('\Drupal\Core\Session\AccountInterface');
    $cache_id = $this->randomMachineName();
    $operation = $this->randomMachineName();
    $language_code = $this->randomMachineName();

    $class = new \ReflectionClass($this->accessControlHandler);
    $method = $class->getMethod('getCache');
    $method->setAccessible(TRUE);
    $this->assertNull($method->invokeArgs($this->accessControlHandler, array($cache_id, $operation, $language_code, $account)));
  }

}

/**
 * Extends two interfaces, because we can only mock one.
 */
interface PaymentAccessUnitTestDummyPaymentMethodUpdateStatusInterface extends PaymentMethodUpdatePaymentStatusInterface, PaymentMethodInterface {
}

/**
 * Extends two interfaces, because we can only mock one.
 */
interface PaymentAccessUnitTestDummyPaymentMethodCapturePaymentInterface extends PaymentMethodCapturePaymentInterface, PaymentMethodInterface {
}

/**
 * Extends two interfaces, because we can only mock one.
 */
interface PaymentAccessUnitTestDummyPaymentMethodRefundPaymentInterface extends PaymentMethodRefundPaymentInterface, PaymentMethodInterface {
}
