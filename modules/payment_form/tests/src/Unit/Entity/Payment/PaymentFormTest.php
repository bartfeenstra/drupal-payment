<?php

namespace Drupal\Tests\payment_form\Unit\Entity\Payment;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Session\AccountInterface;
use Drupal\payment\Entity\PaymentInterface;
use Drupal\payment\OperationResultInterface;
use Drupal\payment\Plugin\Payment\Method\PaymentMethodInterface;
use Drupal\payment\Plugin\Payment\Method\PaymentMethodManagerInterface;
use Drupal\payment\Plugin\Payment\Type\PaymentTypeInterface;
use Drupal\payment\Response\ResponseInterface;
use Drupal\payment_form\Entity\Payment\PaymentForm;
use Drupal\plugin\Plugin\Plugin\PluginSelector\PluginSelectorInterface;
use Drupal\plugin\Plugin\Plugin\PluginSelector\PluginSelectorManagerInterface;
use Drupal\plugin\PluginType\PluginTypeInterface;
use Drupal\plugin\PluginType\PluginTypeManagerInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * @coversDefaultClass \Drupal\payment_form\Entity\Payment\PaymentForm
 *
 * @group Payment Form Field
 */
class PaymentFormTest extends UnitTestCase {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $configFactory;

  /**
   * The configuration the config factory returns.
   *
   * @see self::__construct
   *
   * @var array
   */
  protected $configFactoryConfiguration = [];

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $currentUser;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $entityManager;

  /**
   * The form display.
   *
   * @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $formDisplay;

  /**
   * A payment entity.
   *
   * @var \Drupal\payment\Entity\Payment|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $payment;

  /**
   * The payment method manager.
   *
   * @var \Drupal\payment\Plugin\Payment\Method\PaymentMethodManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $paymentMethodManager;

  /**
   * The payment method plugin type.
   *
   * @var \Drupal\plugin\PluginType\PluginTypeInterface|\Prophecy\Prophecy\ObjectProphecy
   */
  protected $paymentMethodType;

  /**
   * The plugin selector.
   *
   * @var \Drupal\plugin\Plugin\Plugin\PluginSelector\PluginSelectorInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $pluginSelector;

  /**
   * The plugin selector manager.
   *
   * @var \Drupal\plugin\Plugin\Plugin\PluginSelector\PluginSelectorManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $pluginSelectorManager;

  /**
   * The string translation.
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
   * @var \Drupal\payment_form\Entity\Payment\PaymentForm
   */
  protected $sut;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    $this->currentUser = $this->getMock(AccountInterface::class);

    $this->entityManager = $this->getMock(EntityManagerInterface::class);

    $this->formDisplay = $this->getMock(EntityFormDisplayInterface::class);

    $this->payment = $this->getMock(PaymentInterface::class);

    $this->paymentMethodManager = $this->getMock(PaymentMethodManagerInterface::class);

    $this->stringTranslation = $this->getStringTranslationStub();

    $this->paymentMethodType = $this->prophesize(PluginTypeInterface::class);
    $this->paymentMethodType->getPluginManager()->willReturn($this->paymentMethodManager);

    $this->pluginSelector = $this->getMock(PluginSelectorInterface::class);

    $this->pluginSelectorManager = $this->getMock(PluginSelectorManagerInterface::class);

    $this->configFactoryConfiguration = [
      'payment_form.payment_type' => [
        'limit_allowed_plugins' => TRUE,
        'allowed_plugin_ids' => [$this->randomMachineName()],
        'plugin_selector_id' => $this->randomMachineName(),
      ],
    ];

    $this->configFactory = $this->getConfigFactoryStub($this->configFactoryConfiguration);

    $this->entityTypeBundleInfo = $this->prophesize(EntityTypeBundleInfoInterface::class)->reveal();
    $this->time = $this->prophesize(TimeInterface::class)->reveal();

    $this->sut = new PaymentForm($this->entityManager, $this->entityTypeBundleInfo, $this->time, $this->stringTranslation, $this->currentUser, $this->pluginSelectorManager, $this->paymentMethodType->reveal());
    $this->sut->setConfigFactory($this->configFactory);
    $this->sut->setEntity($this->payment);
  }

  /**
   * @covers ::create
   * @covers ::__construct
   */
  function testCreate() {
    $plugin_type_manager = $this->prophesize(PluginTypeManagerInterface::class);
    $plugin_type_manager->getPluginType('payment_method')
      ->willReturn($this->paymentMethodType->reveal());

    $container = $this->getMock(ContainerInterface::class);
    $map = [
      ['current_user', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->currentUser],
      ['entity.manager', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->entityManager],
      ['entity_type.bundle.info', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->entityTypeBundleInfo],
      ['datetime.time', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->time],
      ['entity.manager', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->entityManager],
      ['plugin.manager.plugin.plugin_selector', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->pluginSelectorManager],
      ['plugin.plugin_type_manager', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $plugin_type_manager->reveal()],
      ['string_translation', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->stringTranslation],
    ];
    $container->expects($this->any())
      ->method('get')
      ->willReturnMap($map);

    $sut = PaymentForm::create($container);
    $this->assertInstanceOf(PaymentForm::class, $sut);
  }

  /**
   * @covers ::form
   * @covers ::getPluginSelector
   * @covers ::getPaymentMethodManager
   */
  public function testForm() {
    $plugin_selector_build = [
      '#type' => $this->randomMachineName(),
    ];
    $this->pluginSelector->expects($this->atLeastOnce())
      ->method('buildSelectorForm')
      ->willReturn($plugin_selector_build);

    $this->pluginSelectorManager->expects($this->once())
      ->method('createInstance')
      ->with($this->configFactoryConfiguration['payment_form.payment_type']['plugin_selector_id'])
      ->willReturn($this->pluginSelector);

    $payment_type = $this->getMock(PaymentTypeInterface::class);
    $this->payment->expects($this->any())
      ->method('getPaymentType')
      ->willReturn($payment_type);
    $entity_type = $this->getMock(EntityTypeInterface::class);
    $this->payment->expects($this->any())
      ->method('getEntityType')
      ->willReturn($entity_type);

    $form = [
      'langcode' => [],
    ];
    $form_state = new FormState();
    $this->sut->setFormDisplay($this->formDisplay, $form_state);
    $build = $this->sut->form($form, $form_state);
    // Build the form a second time to make sure the plugin selector is only
    // instantiated once.
    $this->sut->form($form, $form_state);
    $this->assertInternalType('array', $build);
    $this->assertArrayHasKey('line_items', $build);
    $this->assertSame($this->payment, $build['line_items']['#payment_line_items']);
    $this->assertArrayHasKey('payment_method', $build);
    $this->assertSame($plugin_selector_build, $build['payment_method']);
  }

  /**
   * @covers ::validateForm
   * @covers ::getPluginSelector
   * @covers ::getPaymentMethodManager
   */
  public function testValidateForm() {
    $form = [
      'payment_method' => [
        '#type' => $this->randomMachineName(),
      ],
    ];
    $form_state = new FormState();

    $this->pluginSelectorManager->expects($this->once())
      ->method('createInstance')
      ->with($this->configFactoryConfiguration['payment_form.payment_type']['plugin_selector_id'])
      ->willReturn($this->pluginSelector);

    $this->pluginSelector->expects($this->once())
      ->method('validateSelectorForm')
      ->with($form['payment_method'], $form_state);

    $this->sut->validateForm($form, $form_state);
  }

  /**
   * @covers ::submitForm
   * @covers ::getPluginSelector
   * @covers ::getPaymentMethodManager
   */
  public function testSubmitForm() {
    $symfony_response = $this->prophesize(Response::class);
    $completion_response = $this->prophesize(ResponseInterface::class);
    $completion_response->getResponse()->willReturn($symfony_response->reveal());

    $result = $this->getMock(OperationResultInterface::class);
    $result->expects($this->atLeastOnce())
      ->method('getCompletionResponse')
      ->willReturn($completion_response->reveal());

    $form = [
      'payment_method' => [
        '#type' => $this->randomMachineName(),
      ],
    ];
    $form_state = new FormState();

    $payment_method = $this->getMock(PaymentMethodInterface::class);

    $this->pluginSelectorManager->expects($this->once())
      ->method('createInstance')
      ->with($this->configFactoryConfiguration['payment_form.payment_type']['plugin_selector_id'])
      ->willReturn($this->pluginSelector);

    $this->pluginSelector->expects($this->atLeastOnce())
      ->method('getSelectedPlugin')
      ->willReturn($payment_method);
    $this->pluginSelector->expects($this->atLeastOnce())
      ->method('submitSelectorForm')
      ->with($form['payment_method'], $form_state);

    $this->payment->expects($this->atLeastOnce())
      ->method('setPaymentMethod')
      ->with($payment_method);
    $this->payment->expects($this->atLeastOnce())
      ->method('save');
    $this->payment->expects($this->atLeastOnce())
      ->method('execute')
      ->willReturn($result);

    $this->sut->submitForm($form, $form_state);
    $this->assertSame($symfony_response->reveal(), $form_state->getResponse());
  }

  /**
   * @covers ::actions
   * @covers ::getPaymentMethodManager
   */
  public function testActionsWithAvailablePlugins() {
    $form = [];
    $form_state = new FormState();
    $form_state->set('plugin_selector', $this->pluginSelector);

    $plugin_id_a = reset($this->configFactoryConfiguration['payment_form.payment_type']['allowed_plugin_ids']);
    $plugin_id_b = $this->randomMachineName();

    $plugin_a = $this->getMock(PaymentMethodInterface::class);
    $plugin_a->expects($this->atLeastOnce())
      ->method('executePaymentAccess')
      ->with($this->currentUser)
      ->willReturn(AccessResult::allowed());

    $plugin_definitions = [
      $plugin_id_a => [
        'id' => $plugin_id_a,
      ],
      $plugin_id_b => [
        'id' => $plugin_id_b,
      ],
    ];

    $this->paymentMethodManager->expects($this->atLeastOnce())
      ->method('getDefinitions')
      ->willReturn($plugin_definitions);
    $this->paymentMethodManager->expects($this->once())
      ->method('createInstance')
      ->with($plugin_id_a)
      ->willReturn($plugin_a);

    $this->configFactory = $this->getConfigFactoryStub($this->configFactoryConfiguration);

    $method = new \ReflectionMethod($this->sut, 'actions');
    $method->setAccessible(TRUE);
    $actions = $method->invokeArgs($this->sut, [$form, $form_state]);
    $this->assertFalse($actions['submit']['#disabled']);
  }

  /**
   * @covers ::actions
   * @covers ::getPaymentMethodManager
   */
  public function testActionsWithoutAvailablePlugins() {
    $form = [];
    $form_state = new FormState();
    $form_state->set('plugin_selector', $this->pluginSelector);

    $this->paymentMethodManager->expects($this->atLeastOnce())
      ->method('getDefinitions')
      ->willReturn([]);

    $method = new \ReflectionMethod($this->sut, 'actions');
    $method->setAccessible(TRUE);
    $actions = $method->invokeArgs($this->sut, [$form, $form_state]);
    $this->assertTrue($actions['submit']['#disabled']);
  }

}
