<?php

namespace Drupal\payment_form\Entity\Payment;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\payment\Plugin\Payment\Method\PaymentExecutionPaymentMethodManager;
use Drupal\plugin\Plugin\Plugin\PluginSelector\PluginSelectorManagerInterface;
use Drupal\plugin\PluginDiscovery\LimitedPluginDiscoveryDecorator;
use Drupal\plugin\PluginType\PluginTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the payment form.
 */
class PaymentForm extends ContentEntityForm {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The payment method plugin type.
   *
   * @var \Drupal\plugin\PluginType\PluginTypeInterface
   */
  protected $paymentMethodType;

  /**
   * The plugin selector manager.
   *
   * @var \Drupal\plugin\Plugin\Plugin\PluginSelector\PluginSelectorManagerInterface
   */
  protected $pluginSelectorManager;

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   * @param \Drupal\Core\Session\AccountInterface $current_user
   * @param \Drupal\plugin\Plugin\Plugin\PluginSelector\PluginSelectorManagerInterface $plugin_selector_manager
   * @param \Drupal\plugin\PluginType\PluginTypeInterface $payment_method_type
   */
  public function __construct(EntityManagerInterface $entity_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info, TimeInterface $time, TranslationInterface $string_translation, AccountInterface $current_user, PluginSelectorManagerInterface $plugin_selector_manager, PluginTypeInterface $payment_method_type) {
    parent::__construct($entity_manager, $entity_type_bundle_info, $time);
    $this->currentUser = $current_user;
    $this->paymentMethodType = $payment_method_type;
    $this->pluginSelectorManager = $plugin_selector_manager;
    $this->stringTranslation = $string_translation;
  }


  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var \Drupal\plugin\PluginType\PluginTypeManagerInterface $plugin_type_manager */
    $plugin_type_manager = $container->get('plugin.plugin_type_manager');

    return new static($container->get('entity.manager'), $container->get('entity_type.bundle.info'), $container->get('datetime.time'), $container->get('string_translation'), $container->get('current_user'), $container->get('plugin.manager.plugin.plugin_selector'), $plugin_type_manager->getPluginType('payment_method'));
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $payment = $this->getEntity();

    $form['line_items'] = [
      '#payment_line_items' => $payment,
      '#type' => 'payment_line_items_display',
    ];
    $form['payment_method'] = $this->getPluginSelector($form_state)->buildSelectorForm([], $form_state);

    return parent::form($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $this->getPluginSelector($form_state)->validateSelectorForm($form['payment_method'], $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\payment\Entity\PaymentInterface $payment */
    $payment = $this->getEntity();
    $plugin_selector = $this->getPluginSelector($form_state);
    $plugin_selector->submitSelectorForm($form['payment_method'], $form_state);
    $payment->setPaymentMethod($plugin_selector->getSelectedPlugin());
    $payment->save();
    $result = $payment->execute();
    if (!$result->isCompleted()) {
      $form_state->setResponse($result->getCompletionResponse()->getResponse());
    }
  }

  /**
   * Returns an array of supported actions for the current entity form.
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    // Only use the existing submit action.
    $actions = parent::actions($form, $form_state);
    $actions = [
      'submit' => $actions['submit'],
    ];
    $actions['submit']['#value'] = $this->t('Pay');
    $actions['submit']['#disabled'] = count($this->getPaymentMethodManager()->getDefinitions()) == 0;

    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  protected function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    // Remove this override once https://drupal.org/node/2409143 has been fixed.
    $this->getFormDisplay($form_state)
      ->extractFormValues($entity, $form, $form_state);
  }

  /**
   * Gets the plugin selector.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return \Drupal\plugin\Plugin\Plugin\PluginSelector\PluginSelectorInterface
   */
  protected function getPluginSelector(FormStateInterface $form_state) {
    if ($form_state->has('plugin_selector')) {
      $plugin_selector = $form_state->get('plugin_selector');
    }
    else {
      $config = $this->config('payment_form.payment_type');
      $plugin_selector_id = $config->get('plugin_selector_id');
      $plugin_selector = $this->pluginSelectorManager->createInstance($plugin_selector_id);
      $plugin_selector->setSelectablePluginType($this->paymentMethodType);
      $plugin_selector->setSelectablePluginDiscovery($this->getPaymentMethodManager());
      $plugin_selector->setSelectablePluginFactory($this->getPaymentMethodManager());
      $plugin_selector->setRequired();
      $plugin_selector->setLabel($this->t('Payment method'));
      $form_state->set('plugin_selector', $plugin_selector);
    }

    return $plugin_selector;
  }

  /**
   * Gets the payment method manager.
   *
   * @return \Drupal\payment\Plugin\Payment\Method\PaymentMethodManagerInterface
   */
  protected function getPaymentMethodManager() {
    $config = $this->config('payment_form.payment_type');
    $limit_allowed_plugins = $config->get('limit_allowed_plugins');
    $payment_method_discovery = $this->paymentMethodType->getPluginManager();
    if ($limit_allowed_plugins) {
      $allowed_plugin_ids = $config->get('allowed_plugin_ids');
      $payment_method_discovery = new LimitedPluginDiscoveryDecorator($payment_method_discovery);
      $payment_method_discovery->setDiscoveryLimit($allowed_plugin_ids);
    }
    $payment_method_manager = new PaymentExecutionPaymentMethodManager($this->getEntity(), $this->currentUser, $this->paymentMethodType->getPluginManager(), $payment_method_discovery);

    return $payment_method_manager;
  }

}
