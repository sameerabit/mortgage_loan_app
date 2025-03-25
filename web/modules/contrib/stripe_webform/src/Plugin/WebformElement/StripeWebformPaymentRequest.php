<?php

namespace Drupal\stripe_webform\Plugin\WebformElement;

/**
 * Provides a 'stripe' element.
 *
 * @WebformElement(
 *   id = "stripe_paymentrequest",
 *   label = @Translation("Stripe Payment Request button"),
 *   category = @Translation("Stripe"),
 *   composite = TRUE,
 *   description = @Translation("Provides a placeholder for a stripe payment request element."),
 * )
 */
class StripeWebformPaymentRequest extends StripeWebformElementBase {
}
