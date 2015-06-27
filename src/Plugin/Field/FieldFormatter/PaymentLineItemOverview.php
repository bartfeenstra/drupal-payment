<?php

/**
 * @file
 * Contains \Drupal\payment\Plugin\field\formatter\PaymentLineItemOverview.
 */

namespace Drupal\payment\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\payment\Entity\PaymentInterface;

/**
 * A payment line item field formatter.
 *
 * @FieldFormatter(
 *   id = "payment_line_item_overview",
 *   label = @Translation("Overview"),
 *   field_types = {
 *     "payment_line_item",
 *   }
 * )
 */
class PaymentLineItemOverview extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items) {
    $payment_line_items = [];
    /** @var \Drupal\plugin\Plugin\Field\FieldType\PluginCollectionItemInterface $item */
    foreach ($items as $delta => $item) {
      $payment_line_items[$delta] = $item->getContainedPluginInstance();
    }
    $build[0] = array(
      '#payment' => $items->getEntity() instanceof PaymentInterface ? $items->getEntity() : NULL,
      '#payment_line_items' => $payment_line_items,
      '#type' => 'payment_line_items_display',
    );

    return $build;
  }

}
