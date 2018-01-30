<?php

namespace Drupal\Tests\payment\Unit\Entity\Payment;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\payment\Entity\Payment\PaymentCaptureForm;
use Drupal\payment\Entity\PaymentInterface;
use Drupal\payment\OperationResultInterface;
use Drupal\payment\Plugin\Payment\Method\PaymentMethodCapturePaymentInterface;
use Drupal\payment\Response\ResponseInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * @coversDefaultClass \Drupal\payment\Entity\Payment\PaymentCaptureForm
 *
 * @group Payment
 */
class PaymentCaptureFormTest extends UnitTestCase {

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $entityManager;

  /**
   * The payment.
   *
   * @var \Drupal\payment\Entity\Payment|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $payment;

  /**
   * The string translator.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $stringTranslation;

  /**
   * The entity type bundle service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $entityTypeBundleInfo;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $time;

  /**
   * The class under test.
   *
   * @var \Drupal\payment\Entity\Payment\PaymentCaptureForm
   */
  protected $sut;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    $this->entityManager = $this->getMock(EntityManagerInterface::class);

    $this->stringTranslation = $this->getStringTranslationStub();

    $this->payment = $this->getMock(PaymentInterface::class);
    $this->entityTypeBundleInfo = $this->prophesize(EntityTypeBundleInfoInterface::class)->reveal();
    $this->time = $this->prophesize(TimeInterface::class)->reveal();

    $this->sut = new PaymentCaptureForm($this->entityManager, $this->entityTypeBundleInfo, $this->time, $this->stringTranslation);
    $this->sut->setEntity($this->payment);
  }

  /**
   * @covers ::create
   * @covers ::__construct
   */
  function testCreate() {
    $container = $this->getMock(ContainerInterface::class);
    $map = [
      ['entity.manager', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->entityManager],
      ['entity_type.bundle.info', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->entityTypeBundleInfo],
      ['datetime.time', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->time],
      ['string_translation', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->stringTranslation],
    ];
    $container->expects($this->any())
      ->method('get')
      ->willReturnMap($map);

    $sut = PaymentCaptureForm::create($container);
    $this->assertInstanceOf(PaymentCaptureForm::class, $sut);
  }

  /**
   * @covers ::getConfirmText
   */
  function testGetConfirmText() {
    $this->assertInstanceOf(TranslatableMarkup::class, $this->sut->getConfirmText());
  }

  /**
   * @covers ::getQuestion
   */
  function testGetQuestion() {
    $this->assertInstanceOf(TranslatableMarkup::class, $this->sut->getQuestion());
  }

  /**
   * @covers ::getCancelUrl
   */
  function testGetCancelUrl() {
    $url = new Url($this->randomMachineName());

    $this->payment->expects($this->atLeastOnce())
      ->method('urlInfo')
      ->with('canonical')
      ->willReturn($url);

    $this->assertSame($url, $this->sut->getCancelUrl());
  }

  /**
   * @covers ::submitForm
   */
  function testSubmitFormWithCompletionResponse() {
    $response = $this->getMockBuilder(Response::class)
      ->disableOriginalConstructor()
      ->getMock();

    $completion_response = $this->getMock(ResponseInterface::class);
    $completion_response->expects($this->atLeastOnce())
      ->method('getResponse')
      ->willReturn($response);

    $operation_result = $this->getMock(OperationResultInterface::class);
    $operation_result->expects($this->atLeastOnce())
      ->method('getCompletionResponse')
      ->willReturn($completion_response);
    $operation_result->expects($this->atLeastOnce())
      ->method('isCompleted')
      ->willReturn(FALSE);

    $payment_method = $this->getMock(PaymentMethodCapturePaymentInterface::class);
    $payment_method->expects($this->once())
      ->method('capturePayment')
      ->willReturn($operation_result);

    $this->payment->expects($this->atLeastOnce())
      ->method('getPaymentMethod')
      ->willReturn($payment_method);

    $form = [];

    $form_state = $this->getMock(FormStateInterface::class);
    $form_state->expects($this->atLeastOnce())
      ->method('setResponse')
      ->with($response);

    $this->sut->submitForm($form, $form_state);
  }

  /**
   * @covers ::submitForm
   */
  function testSubmitFormWithoutCompletionResponse() {
    $operation_result = $this->getMock(OperationResultInterface::class);
    $operation_result->expects($this->atLeastOnce())
      ->method('isCompleted')
      ->willReturn(TRUE);

    $payment_method = $this->getMock(PaymentMethodCapturePaymentInterface::class);
    $payment_method->expects($this->once())
      ->method('capturePayment')
      ->willReturn($operation_result);

    $url = new Url($this->randomMachineName());

    $this->payment->expects($this->atLeastOnce())
      ->method('getPaymentMethod')
      ->willReturn($payment_method);
    $this->payment->expects($this->atLeastOnce())
      ->method('urlInfo')
      ->with('canonical')
      ->willReturn($url);

    $form = [];

    $form_state = $this->getMock(FormStateInterface::class);
    $form_state->expects($this->atLeastOnce())
      ->method('setRedirectUrl')
      ->with($url);

    $this->sut->submitForm($form, $form_state);
  }

}
