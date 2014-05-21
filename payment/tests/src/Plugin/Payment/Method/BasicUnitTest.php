<?php

/**
 * @file
 * Contains \Drupal\payment\Tests\Plugin\Payment\Method\BasicUnitTest.
 */

namespace Drupal\payment\Tests\Plugin\Payment\Method;

/**
 * @coversDefaultClass \Drupal\payment\Plugin\Payment\Method\Basic
 */
class BasicUnitTest extends PaymentMethodBaseUnitTestBase {

  /**
   * The payment method plugin under test.
   *
   * @var \Drupal\payment\Plugin\Payment\Method\Basic|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $plugin;

  /**
   * The payment status manager used for testing.
   *
   * @var \Drupal\payment\Plugin\Payment\Status\PaymentStatusManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $paymentStatusManager;

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'description' => '',
      'name' => '\Drupal\payment\Plugin\Payment\Method\Basic unit test',
      'group' => 'Payment',
    );
  }

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->pluginDefinition += array(
      'status' => 'payment_expired',
    );

    $this->paymentStatusManager = $this->getMock('\Drupal\payment\Plugin\Payment\Status\PaymentStatusManagerInterface');

    $this->plugin = $this->getMockBuilder('\Drupal\payment\Plugin\Payment\Method\Basic')
      ->setConstructorArgs(array(array(), '', $this->pluginDefinition, $this->moduleHandler, $this->eventDispatcher, $this->token, $this->paymentStatusManager))
      ->setMethods(array('t'))
      ->getMock();
    $this->plugin->expects($this->any())
      ->method('t')
      ->will($this->returnArgument(0));
  }

  /**
   * @covers ::defaultConfiguration
   */
  public function testDefaultConfiguration() {
    $this->assertInternalType('array', $this->plugin->defaultConfiguration());
  }

  /**
   * @covers ::getStatus
   */
  public function testGetStatus() {
    $this->assertSame($this->pluginDefinition['status'], $this->plugin->getStatus());
  }

  /**
   * @covers ::executePayment
   */
  public function testExecutePayment() {
    $payment_status_plugin_id = $this->randomName();
    /** @var \Drupal\payment\Plugin\Payment\Method\Basic|\PHPUnit_Framework_MockObject_MockObject $payment_method_plugin */
    $payment_method_plugin = $this->getMockBuilder('\Drupal\payment\Plugin\Payment\Method\Basic')
      ->setConstructorArgs(array(array(), '', array(), $this->moduleHandler, $this->eventDispatcher, $this->token, $this->paymentStatusManager))
      ->setMethods(array('executePaymentAccess', 'getStatus'))
      ->getMock();
    $payment_method_plugin->expects($this->once())
      ->method('getStatus')
      ->will($this->returnValue($payment_status_plugin_id));

    $payment_status = $this->getMock('\Drupal\payment\Plugin\Payment\Status\PaymentStatusInterface');

    $this->paymentStatusManager->expects($this->once())
      ->method('createInstance')
      ->with($payment_status_plugin_id)
      ->will($this->returnValue($payment_status));

    $payment_type = $this->getMock('\Drupal\payment\Plugin\Payment\Type\PaymentTypeInterface');
    $payment_type->expects($this->once())
      ->method('resumeContext');

    $payment = $this->getMockBuilder('\Drupal\payment\Entity\Payment')
      ->disableOriginalConstructor()
      ->getMock();
    $payment->expects($this->once())
      ->method('save');
    $payment->expects($this->once())
      ->method('getPaymentType')
      ->will($this->returnValue($payment_type));
    $payment->expects($this->once())
      ->method('setStatus')
      ->with($payment_status);

    $payment_method_plugin->executePayment($payment);
  }

  /**
   * @covers ::getSupportedCurrencies
   */
  public function testGetSupportedCurrencies() {
    $this->assertSame(TRUE, $this->plugin->getSupportedCurrencies());
  }
}