<?php

/**
 * @file
 * Contains \Drupal\Tests\payment\Unit\Plugin\Payment\Method\FilteredPaymentMethodManagerUnitTest.
 */

namespace Drupal\Tests\payment\Unit\Plugin\Payment\Method;

use Drupal\payment\Plugin\Payment\Method\FilteredPaymentMethodManager;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\payment\Plugin\Payment\Method\FilteredPaymentMethodManager
 *
 * @group Payment
 */
class FilteredPaymentMethodManagerUnitTest extends UnitTestCase {

  /**
   * The account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * The payment to filter methods by.
   *
   * @var \Drupal\payment\Entity\PaymentInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $payment;

  /**
   * The original payment method manager.
   *
   * @var \Drupal\payment\Plugin\Payment\Method\PaymentMethodManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $paymentMethodManager;

  /**
   * The class under test.
   *
   * @var \Drupal\payment\Plugin\Payment\Method\FilteredPaymentMethodManager
   */
  protected $sut;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    $this->account = $this->getMock('\Drupal\Core\Session\AccountInterface');

    $this->payment = $this->getMock('\Drupal\payment\Entity\PaymentInterface');

    $this->paymentMethodManager = $this->getMock('\Drupal\payment\Plugin\Payment\Method\PaymentMethodManagerInterface');

    $this->sut = new FilteredPaymentMethodManager($this->paymentMethodManager, $this->payment, $this->account);
  }

  /**
   * @covers ::__construct
   */
  public function testConstruct() {
    $this->sut = new FilteredPaymentMethodManager($this->paymentMethodManager, $this->payment, $this->account);
  }

  /**
   * @covers ::filterDefinition
   */
  public function testFilterDefinition() {
    $payment_method_id_a = $this->randomMachineName();
    $payment_method_a = $this->getMock('\Drupal\payment\Plugin\Payment\Method\PaymentMethodInterface');
    $payment_method_a->expects($this->atLeastOnce())
      ->method('executePaymentAccess')
      ->with($this->account)
      ->willReturn(TRUE);
    $payment_method_id_b = $this->randomMachineName();
    $payment_method_b = $this->getMock('\Drupal\payment\Plugin\Payment\Method\PaymentMethodInterface');
    $payment_method_b->expects($this->atLeastOnce())
      ->method('executePaymentAccess')
      ->with($this->account)
      ->willReturn(FALSE);

    $payment_method_definitions = [
      $payment_method_id_a => [
        'id' => $payment_method_id_a,
      ],
      $payment_method_id_b => [
        'id' => $payment_method_id_b,
      ],
    ];
    $this->paymentMethodManager->expects($this->atLeastOnce())
      ->method('getDefinitions')
      ->willReturn($payment_method_definitions);
    $map = [
      [$payment_method_id_a, [], $payment_method_a],
      [$payment_method_id_b, [], $payment_method_b],
    ];
    $this->paymentMethodManager->expects($this->atLeast(count($map)))
      ->method('createInstance')
      ->willReturnMap($map);

    $filtered_plugin_definitions = $this->sut->getDefinitions();
    $expected_filtered_plugin_definitions = [
      $payment_method_id_a => [
        'id' => $payment_method_id_a,
      ],
    ];
    $this->assertSame($expected_filtered_plugin_definitions, $filtered_plugin_definitions);
  }

  /**
   * @covers ::getOperationsProvider
   */
  public function testGetOperationsProvider() {
    $payment_method_id = $this->randomMachineName();
    $payment_method = $this->getMock('\Drupal\payment\Plugin\Payment\Method\PaymentMethodInterface');
    $payment_method->expects($this->atLeastOnce())
      ->method('executePaymentAccess')
      ->with($this->account)
      ->willReturn(TRUE);

    $payment_method_definitions = [
      $payment_method_id => [
        'id' => $payment_method_id,
      ],
    ];
    $this->paymentMethodManager->expects($this->atLeastOnce())
      ->method('getDefinitions')
      ->willReturn($payment_method_definitions);
    $this->paymentMethodManager->expects($this->atLeastOnce())
      ->method('createInstance')
      ->with($payment_method_id)
      ->willReturn($payment_method);
    $operations_provider = $this->getMock('\Drupal\payment\Plugin\Payment\OperationsProviderInterface');
    $this->paymentMethodManager->expects($this->atLeastOnce())
      ->method('getOperationsProvider')
      ->with($payment_method_id)
      ->willReturn($operations_provider);

    $this->assertSame($operations_provider, $this->sut->getOperationsProvider($payment_method_id));
  }

  /**
   * @covers ::getOperationsProvider
   *
   * @expectedException \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function testGetOperationsProviderWithNonExistentPlugin() {
    $this->paymentMethodManager->expects($this->atLeastOnce())
      ->method('getDefinitions')
      ->willReturn([]);

    $this->sut->getOperationsProvider($this->randomMachineName());
  }

}