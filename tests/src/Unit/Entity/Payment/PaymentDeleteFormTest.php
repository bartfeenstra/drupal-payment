<?php

namespace Drupal\Tests\payment\Unit\Entity\Payment {

  use Drupal\Component\Datetime\TimeInterface;
  use Drupal\Core\Entity\EntityManagerInterface;
  use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
  use Drupal\Core\Form\FormStateInterface;
  use Drupal\Core\StringTranslation\TranslatableMarkup;
  use Drupal\Core\Url;
  use Drupal\payment\Entity\Payment\PaymentDeleteForm;
  use Drupal\payment\Entity\PaymentInterface;
  use Drupal\Tests\UnitTestCase;
  use Psr\Log\LoggerInterface;
  use Symfony\Component\DependencyInjection\ContainerInterface;

  /**
   * @coversDefaultClass \Drupal\payment\Entity\Payment\PaymentDeleteForm
   *
   * @group Payment
   */
  class PaymentDeleteFormTest extends UnitTestCase {

    /**
     * The entity manager.
     *
     * @var \Drupal\Core\Entity\EntityManagerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $entityManager;

    /**
     * The logger.
     *
     * @var \Psr\Log\LoggerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $logger;

    /**
     * The payment.
     *
     * @var \Drupal\payment\Entity\PaymentInterface|\PHPUnit_Framework_MockObject_MockObject
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
     * @var \Drupal\payment\Entity\Payment\PaymentDeleteForm
     */
    protected $sut;

    /**
     * {@inheritdoc}
     */
    public function setUp() {
      $this->entityManager = $this->getMock(EntityManagerInterface::class);

      $this->logger = $this->getMock(LoggerInterface::class);

      $this->payment = $this->getMock(PaymentInterface::class);

      $this->stringTranslation = $this->getStringTranslationStub();
      $this->entityTypeBundleInfo = $this->prophesize(EntityTypeBundleInfoInterface::class)->reveal();
      $this->time = $this->prophesize(TimeInterface::class)->reveal();

      $this->sut = new PaymentDeleteForm($this->entityManager, $this->entityTypeBundleInfo, $this->time, $this->stringTranslation, $this->logger);
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
        ['payment.logger', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->logger],
        ['string_translation', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->stringTranslation],
      ];
      $container->expects($this->any())
        ->method('get')
        ->willReturnMap($map);

      $sut = PaymentDeleteForm::create($container);
      $this->assertInstanceOf(PaymentDeleteForm::class, $sut);
    }

    /**
     * @covers ::getQuestion
     */
    function testGetQuestion() {
      $this->assertInstanceOf(TranslatableMarkup::class, $this->sut->getQuestion());
    }

    /**
     * @covers ::getConfirmText
     */
    function testGetConfirmText() {
      $this->assertInstanceOf(TranslatableMarkup::class, $this->sut->getConfirmText());
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

      $cancel_url = $this->sut->getCancelUrl();
      $this->assertSame($url, $cancel_url);
    }

    /**
     * @covers ::submitForm
     */
    function testSubmitForm() {
      $this->logger->expects($this->atLeastOnce())
        ->method('info');

      $this->payment->expects($this->once())
        ->method('delete');

      $form = [];
      $form_state = $this->getMock(FormStateInterface::class);
      $form_state->expects($this->once())
        ->method('setRedirect')
        ->with('<front>');

      $this->sut->submitForm($form, $form_state);
    }

  }

}

namespace {

if (!function_exists('drupal_set_message')) {
  function drupal_set_message() {}
}

}
