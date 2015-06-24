<?php

/**
 * @file
 * Contains
 * \Drupal\Tests\payment_form\Unit\Plugin\Field\FieldFormatter\PaymentFormUnitTest.
 */

namespace Drupal\Tests\payment_form\Unit\Plugin\Field\FieldFormatter {

  use Drupal\Core\DependencyInjection\Container;
  use Drupal\payment_form\Plugin\Field\FieldFormatter\PaymentForm;
  use Drupal\Tests\UnitTestCase;

  /**
 * @coversDefaultClass \Drupal\payment_form\Plugin\Field\FieldFormatter\PaymentForm
 *
 * @group Payment Form Field
 */
class PaymentFormUnitTest extends UnitTestCase {

  /**
   * The entity manager used for testing.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $entityManager;

  /**
   * The field definition used for testing.
   *
   * @var \Drupal\Core\Field\FieldDefinitionInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $fieldDefinition;

  /**
   * The field formatter under test.
   *
   * @var \Drupal\payment_form\Plugin\Field\FieldFormatter\PaymentForm|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $fieldFormatter;

  /**
   * The entity form builder used for testing.
   *
   * @var \Drupal\Core\Entity\EntityFormBuilderInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $entityFormBuilder;

  /**
   * The payment line item manager used for testing.
   *
   * @var \Drupal\payment\Plugin\Payment\LineItem\PaymentLineItemManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $paymentLineItemManager;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $renderer;

  /**
   * The request used for testing.
   *
   * @var \Symfony\Component\HttpFoundation\Request|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $request;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $requestStack;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    $this->entityFormBuilder = $this->getMock('\Drupal\Core\Entity\EntityFormBuilderInterface');

    $this->entityManager = $this->getMock('\Drupal\Core\Entity\EntityManagerInterface');

    $this->paymentLineItemManager = $this->getMockBuilder('\Drupal\payment\Plugin\Payment\LineItem\PaymentLineItemManagerInterface')
      ->disableOriginalConstructor()
      ->getMock();

    $this->fieldDefinition = $this->getMock('\Drupal\Core\Field\FieldDefinitionInterface');

    $this->renderer = $this->getMock('\Drupal\Core\Render\RendererInterface');

    $this->request = $this->getMockBuilder('\Symfony\Component\HttpFoundation\Request')
      ->disableOriginalConstructor()
      ->getMock();

    $this->requestStack = $this->getMockBuilder('\Symfony\Component\HttpFoundation\RequestStack')
      ->disableOriginalConstructor()
      ->getMock();
    $this->requestStack->expects($this->any())
      ->method('getCurrentRequest')
      ->willReturn($this->request);

    $this->fieldFormatter = new PaymentForm('payment_form', [], $this->fieldDefinition, [], $this->randomMachineName(), $this->randomMachineName(), []);
  }

  /**
   * @covers ::viewElements
   */
  public function testViewElements() {
    $entity_type_id = $this->randomMachineName();
    $bundle = $this->randomMachineName();
    $field_name = $this->randomMachineName();

    $plugin_id = $this->randomMachineName();
    $plugin_configuration = [
      $this->randomMachineName() => $this->randomMachineName(),
    ];

    $plugin_id_property = $this->getMock('\Drupal\Core\TypedData\TypedDataInterface');
    $plugin_id_property->expects($this->once())
      ->method('getValue')
      ->will($this->returnValue($plugin_id));
    $plugin_configuration_property = $this->getMock('\Drupal\Core\TypedData\TypedDataInterface');
    $plugin_configuration_property->expects($this->once())
      ->method('getValue')
      ->will($this->returnValue($plugin_configuration));
    $map = [
      ['plugin_id', $plugin_id_property],
      ['plugin_configuration', $plugin_configuration_property],
    ];
    $item = $this->getMockBuilder('\Drupal\payment_form\Plugin\Field\FieldType\PaymentForm')
      ->disableOriginalConstructor()
      ->getMock();
    $item->expects($this->exactly(2))
      ->method('get')
      ->will($this->returnValueMap($map));

    $entity = $this->getMock('\Drupal\Core\Entity\EntityInterface');
    $entity->expects($this->atLeastOnce())
      ->method('bundle')
      ->will($this->returnValue($bundle));
    $entity->expects($this->atLeastOnce())
      ->method('getEntityTypeId')
      ->will($this->returnValue($entity_type_id));

    $iterator = new \ArrayIterator([$item]);
    $items = $this->getMockBuilder('Drupal\Core\Field\FieldItemList')
      ->disableOriginalConstructor()
      ->setMethods(['getEntity', 'getIterator'])
      ->getMock();
    $items->expects($this->atLeastOnce())
      ->method('getEntity')
      ->will($this->returnValue($entity));
    $items->expects($this->atLeastOnce())
      ->method('getIterator')
      ->will($this->returnValue($iterator));

    $this->fieldDefinition->expects($this->once())
      ->method('getName')
      ->will($this->returnValue($field_name));

    // Create a dummy render array.
    $line_items_data = [[
      'plugin_id' => $plugin_id,
      'plugin_configuration' => $plugin_configuration,
    ]];
    $built_form = [[
      '#lazy_builder' => [
        'Drupal\payment_form\Plugin\Field\FieldFormatter\PaymentForm::lazyBuild', [
          $bundle,
          $entity_type_id,
          $field_name,
          serialize($line_items_data),
        ],
      ],
    ]];

    $this->assertSame($built_form, $this->fieldFormatter->viewElements($items));
  }

  /**
   * @covers ::lazyBuild
   */
  public function testLazyBuild() {
    $bundle = $this->randomMachineName();
    $entity_type_id = $this->randomMachineName();
    $field_name = $this->randomMachineName();
    $destination_url = $this->randomMachineName();
    $currency_code = $this->randomMachineName();

    $this->fieldDefinition->expects($this->atLeastOnce())
      ->method('getSetting')
      ->with('currency_code')
      ->will($this->returnValue($currency_code));

    $definitions = [
      $field_name => $this->fieldDefinition,
    ];
    $this->entityManager->expects($this->atLeastOnce())
      ->method('getFieldDefinitions')
      ->with($entity_type_id, $bundle)
      ->will($this->returnValue($definitions));

    $payment_type = $this->getMockBuilder('\Drupal\payment_form\Plugin\Payment\Type\PaymentForm')
      ->disableOriginalConstructor()
      ->getMock();
    $payment_type->expects($this->once())
      ->method('setEntityTypeId')
      ->with($entity_type_id);
    $payment_type->expects($this->once())
      ->method('setBundle')
      ->with($bundle);
    $payment_type->expects($this->once())
      ->method('setFieldName')
      ->with($field_name);
    $payment_type->expects($this->once())
      ->method('setDestinationUrl')
      ->with($destination_url);

    $payment = $this->getMockBuilder('\Drupal\payment\Entity\Payment')
      ->disableOriginalConstructor()
      ->getMock();
    $payment->expects($this->once())
      ->method('setCurrencyCode')
      ->with($currency_code);
    $payment->expects($this->once())
      ->method('getPaymentType')
      ->will($this->returnValue($payment_type));

    $storage = $this->getMock('\Drupal\Core\Entity\EntityStorageInterface');
    $storage->expects($this->once())
      ->method('create')
      ->with([
        'bundle' => 'payment_form',
      ])
      ->will($this->returnValue($payment));

    $this->entityManager->expects($this->once())
      ->method('getStorage')
      ->with('payment')
      ->will($this->returnValue($storage));

    $plugin_id = $this->randomMachineName();
    $plugin_configuration = [];

    $payment_line_item = $this->getMock('\Drupal\payment\Plugin\Payment\LineItem\PaymentLineItemInterface');

    $this->paymentLineItemManager->expects($this->once())
      ->method('createInstance')
      ->with($plugin_id, $plugin_configuration)
      ->will($this->returnValue($payment_line_item));

    $form_build = [
      '#markup' => $this->randomMachineName(),
    ];

    $this->entityFormBuilder->expects($this->once())
      ->method('getForm')
      ->with($payment, 'payment_form')
      ->willReturn($form_build);

    $this->request->expects($this->atLeastOnce())
      ->method('getUri')
      ->will($this->returnValue($destination_url));

    $container = new Container();
    $container->set('entity.form_builder', $this->entityFormBuilder);
    $container->set('entity.manager', $this->entityManager);
    $container->set('plugin.manager.payment.line_item', $this->paymentLineItemManager);
    $container->set('request_stack', $this->requestStack);
    \Drupal::setContainer($container);

    $line_items_data = [[
      'plugin_id' => $plugin_id,
      'plugin_configuration' => $plugin_configuration,
    ]];

    $field_formatter = $this->fieldFormatter;
    $this->assertSame($form_build, $field_formatter::lazyBuild($bundle, $entity_type_id, $field_name, serialize($line_items_data)));
  }

}

}

namespace {

if (!function_exists('drupal_render_cache_generate_placeholder')) {
  function drupal_render_cache_generate_placeholder() {}
}

}
