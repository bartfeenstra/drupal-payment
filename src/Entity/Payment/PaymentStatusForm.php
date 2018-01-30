<?php

namespace Drupal\payment\Entity\Payment;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\payment\Plugin\Payment\Method\PaymentMethodUpdatePaymentStatusInterface;
use Drupal\payment\Plugin\Payment\PaymentAwarePluginManagerDecorator;
use Drupal\plugin\Plugin\Plugin\PluginSelector\PluginSelectorManagerInterface;
use Drupal\plugin\PluginDiscovery\LimitedPluginDiscoveryDecorator;
use Drupal\plugin\PluginType\PluginTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the payment status update form.
 */
class PaymentStatusForm extends ContentEntityForm {

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The plugin selector manager.
   *
   * @var \Drupal\plugin\Plugin\Plugin\PluginSelector\PluginSelectorManagerInterface
   */
  protected $pluginSelectorManager;

  /**
   * The "payment_status" plugin type.
   *
   * @var \Drupal\plugin\PluginType\PluginTypeInterface
   */
  protected $paymentStatusPluginType;

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Core\Session\AccountInterface
   *   The current user.
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $url_generator
   *   The URL generator.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *    The string translator.
   * @param \Drupal\plugin\Plugin\Plugin\PluginSelector\PluginSelectorManagerInterface $plugin_selector_manager
   * @param \Drupal\plugin\PluginType\PluginTypeInterface $plugin_type
   *   The "payment_status" plugin type.
   */
  function __construct(EntityManagerInterface $entity_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info, TimeInterface $time, AccountInterface $current_user, UrlGeneratorInterface $url_generator, TranslationInterface $string_translation, PluginSelectorManagerInterface $plugin_selector_manager, PluginTypeInterface $plugin_type) {
    parent::__construct($entity_manager, $entity_type_bundle_info, $time);
    $this->currentUser = $current_user;
    $this->paymentStatusPluginType = $plugin_type;
    $this->pluginSelectorManager = $plugin_selector_manager;
    $this->stringTranslation = $string_translation;
    $this->urlGenerator = $url_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var \Drupal\plugin\PluginType\PluginTypeManagerInterface $plugin_type_manager */
    $plugin_type_manager = $container->get('plugin.plugin_type_manager');

    return new static($container->get('entity.manager'), $container->get('entity_type.bundle.info'), $container->get('datetime.time'), $container->get('current_user'), $container->get('url_generator'), $container->get('string_translation'), $container->get('plugin.manager.plugin.plugin_selector'), $plugin_type_manager->getPluginType('payment_status'));
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form['payment_status'] = $this->getPluginSelector($form_state)->buildSelectorForm([], $form_state);

    return parent::form($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions = array($actions['submit']);

    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $this->getPluginSelector($form_state)->validateSelectorForm($form['payment_status'], $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\payment\Entity\PaymentInterface $payment */
    $payment = $this->getEntity();
    $plugin_selector = $this->getPluginSelector($form_state);
    $plugin_selector->submitSelectorForm($form['payment_status'], $form_state);
    $payment->setPaymentStatus($plugin_selector->getSelectedPlugin());
    $payment->save();

    $form_state->setRedirectUrl($payment->urlInfo());
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
      /** @var \Drupal\payment\Entity\PaymentInterface $payment */
      $payment = $this->getEntity();

      $payment_method = $payment->getPaymentMethod();
      $payment_status_discovery = new LimitedPluginDiscoveryDecorator($this->paymentStatusPluginType->getPluginManager());
      if ($payment_method instanceof PaymentMethodUpdatePaymentStatusInterface) {
        $payment_status_discovery->setDiscoveryLimit($payment_method->getSettablePaymentStatuses($this->currentUser, $payment));
      }
      $payment_status_manager = new PaymentAwarePluginManagerDecorator($payment, $this->paymentStatusPluginType->getPluginManager(), $payment_status_discovery);

      $plugin_selector = $this->pluginSelectorManager->createInstance('payment_select_list');
      $plugin_selector->setSelectablePluginType($this->paymentStatusPluginType, $payment_status_manager);
      $plugin_selector->setRequired();
      $plugin_selector->setLabel($this->t('Payment status'));

      $form_state->set('plugin_selector', $plugin_selector);
    }

    return $plugin_selector;
  }

}
