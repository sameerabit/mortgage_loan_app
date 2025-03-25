<?php

namespace Drupal\stripe_webform\Plugin\WebformElement;

/**
 * Provides a 'stripe' element.
 *
 * @WebformElement(
 *   id = "stripe",
 *   label = @Translation("Stripe credit card"),
 *   category = @Translation("Stripe"),
 *   composite = TRUE,
 *   description = @Translation("Provides a placeholder for a stripe credit card element."),
 * )
 */
class StripeWebformCC extends StripeWebformElementBase {
}
