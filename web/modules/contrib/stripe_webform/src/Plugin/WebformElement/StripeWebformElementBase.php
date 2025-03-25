<?php

namespace Drupal\stripe_webform\Plugin\WebformElement;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Plugin\WebformElementBase;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Abstract class for our webform stripe elements.
 */
abstract class StripeWebformElementBase extends WebformElementBase {

  protected $stripeWebformElementDefaultProperties = [
    'stripe_currency' => 'usd',
    'stripe_shared' => TRUE,
    'stripe_label' => '',
    'stripe_country' => 'US',
    'stripe_name' => '',
    'stripe_email' => '',
    'stripe_receipt_email' => '',
    'stripe_billing_address1' => '',
    'stripe_billing_address2' => '',
    'stripe_billing_city' => '',
    'stripe_billing_state' => '',
    'stripe_billing_country' => '',
    'stripe_billing_postal_code' => '',
    'stripe_billing_phone' => '',
    'webform_stripe_subscriptions' => FALSE,
    'webform_stripe_amount' => '',
    'webform_stripe_amount_multiply' => TRUE,
  ];

  /**
   * {@inheritdoc}
   */
  public function prepare(array &$element, ?WebformSubmissionInterface $webform_submission = NULL) {
    parent::prepare($element, $webform_submission);

    foreach ($this->stripeWebformElementDefaultProperties as $key => $value) {
      $element["#$key"] = $this->getElementProperty($element, $key);
    }

    if (is_numeric($element['#webform_stripe_amount'])) {
      $element['#stripe_amount'] = (float) $element['#webform_stripe_amount'];

      if ($element['#webform_stripe_amount_multiply']) {
        $element['#stripe_amount'] *= 100;
      }
    }

    if (empty($element['#stripe_label'])) {
      $element["#stripe_label"] = $webform_submission->getWebform()->label();
    }

    if (!empty($element['#webform_stripe_subscriptions'])) {
      $element["#stripe_paymentintent"]['setup_future_usage'] = 'off_session';
    }

    // We provide potential default submit buttons based on WebformActions.
    $element['#stripe_submit_selector'][] = '.webform-button--next';
    $element['#stripe_submit_selector'][] = '.webform-button--submit';
  }

  /**
   * {@inheritdoc}
   */
  protected function defineDefaultProperties() {
    $info = $this->getInfo();
    // Matching defaults from the element.
    $this->stripeWebformElementDefaultProperties['stripe_currency'] = $info['#stripe_currency'];
    $this->stripeWebformElementDefaultProperties['stripe_country'] = $info['#stripe_country'];
    $default = parent::defineDefaultProperties();
    unset($default['required']);
    unset($default['required_error']);
    unset($default['prepopulate']);
    unset($default['disabled']);
    return $this->stripeWebformElementDefaultProperties + $default;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);
    if ($this->getWebform()->getSetting('ajax', FALSE)) {
      $form_state->setErrorByName('stripe_webform', $this->t('This element does not support AJAX webforms, please disable AJAX before adding stripe elements.'));
    }
  }

  /**
   * Validates my element.
   *
   * @param array $element
   *   The form element to process.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $complete_form
   *   The complete form structure.
   */
  public static function validateStripeAmount(&$element, FormStateInterface $form_state, &$complete_form) {
    // Validate minimum amount depending on currency.
    // @see https://stripe.com/docs/currencies#minimum-and-maximum-charge-amounts
    $minimum = [];
    $minimum['USD'] = 50;
    $minimum['AED'] = 200;
    $minimum['AUD'] = 50;
    $minimum['BGN'] = 100;
    $minimum['BRL'] = 50;
    $minimum['CAD'] = 50;
    $minimum['CHF'] = 50;
    $minimum['CZK'] = 1500;
    $minimum['DKK'] = 250;
    $minimum['EUR'] = 50;
    $minimum['GBP'] = 30;
    $minimum['HKD'] = 400;
    $minimum['HUF'] = 17500;
    $minimum['INR'] = 50;
    $minimum['JPY'] = 5000;
    $minimum['MXN'] = 1000;
    $minimum['MYR'] = 200;
    $minimum['NOK'] = 300;
    $minimum['NZD'] = 50;
    $minimum['PLN'] = 200;
    $minimum['RON'] = 200;
    $minimum['SEK'] = 300;
    $minimum['SGD'] = 50;

    $currency = strtoupper($form_state->getValue(['properties', 'stripe_currency']));
    $multiply = $form_state->getValue(['properties', 'webform_stripe_amount_multiply']);
    if (is_numeric($element['#value']) && !empty($currency) && !empty($minimum[$currency])) {
      $amount = $element['#value'];
      if ($multiply) {
        $amount *= 100;
      }
      if ($amount < $minimum[$currency]) {
        $form_state->setError($element, t('The <a target="_blank" href="https://stripe.com/docs/currencies#minimum-and-maximum-charge-amounts">minimum charge amount</a> is @minimum for the selected currency @currency.', ['@minimum' => $minimum[$currency], '@currency' => $currency]));
        if ($currency != 'USD') {
          \Drupal::messenger()->addWarning('For currencies other than USD, please try avoid using values too close to the minimum, as depending on the exchange rate of the moment, you can still get an error when rendering the form.');
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form['stripe'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Stripe settings'),
      '#description' => $this->t('You can use tokens (including webform:submission tokens) to dynamically set this values before processing payment.'),
    ];

    $form['stripe']['stripe_country'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Country'),
      '#description' => $this->t('The two-letter country code of your Stripe account (e.g., US).'),
    ];

    $form['stripe']['stripe_currency'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Currency'),
      '#description' => $this->t('Three character currency code (e.g., usd).'),
    ];

    $form['stripe']['stripe_shared'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Share payment with other stripe elements'),
      '#description' => $this->t('If enabled, the same payment configuration will be used for all stripe elements available on the form.'),
      '#return_type' => TRUE,
    ];

    $form['stripe']['webform_stripe_subscriptions'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable subscriptions'),
      '#description' => $this->t('If you are going to use the submit handler to create monthly subscriptions, you need to enable it so that the captured payment information can be reused.'),
      '#return_type' => TRUE,
    ];

    $form['stripe']['stripe_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#description' => $this->t('It will be used as a placeholder for the total if needed by stripe (i.e. Payment Request Buttons). If blank, the webform title will be used.'),
    ];

    $form['stripe']['webform_stripe_amount'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Amount'),
      '#description' => $this->t('Amount in dollars (not cents) the card will be charged. The amount must be an integer (i.e. for $0.50 you should use 50). You can use tokens.'),
      '#element_validate' => [
        [$this, 'validateStripeAmount'],
      ],
    ];

    $form['stripe']['webform_stripe_amount_multiply'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Amount is a full/decimal number (i.e. 10.00). It will be multiplied by 100 when sent to stripe.'),
      '#description' => $this->t('If you are using the amount value in other places and you need it to be a full/decimal amount (i.e 1.00, 0.50, 2, etc), by enabling this we are going to <a href="https://stripe.com/docs/api/payment_intents/object#payment_intent_object-amount">multiply it by 100 before sending it to stripe</a>.'),
      '#return_type' => TRUE,
    ];

    $form['stripe']['stripe_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
    ];

    $form['stripe']['stripe_email'] = [
      '#type' => 'textfield',
      '#title' => $this->t('E-mail'),
    ];

    $form['stripe']['stripe_receipt_email'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Receipt E-mail'),
    ];

    $form['stripe']['billing_details'] = [
      '#type' => 'details',
      '#title' => $this->t('Billing details'),
      '#open' => TRUE,
    ];

    $form['stripe']['billing_details']['stripe_billing_address1'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Address Line 1'),
    ];

    $form['stripe']['billing_details']['stripe_billing_address2'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Address Line 2'),
    ];

    $form['stripe']['billing_details']['stripe_billing_city'] = [
      '#type' => 'textfield',
      '#title' => $this->t('City'),
    ];

    $form['stripe']['billing_details']['stripe_billing_state'] = [
      '#type' => 'textfield',
      '#title' => $this->t('State'),
    ];

    $form['stripe']['billing_details']['stripe_billing_country'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Country'),
    ];

    $form['stripe']['billing_details']['stripe_billing_postal_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Postal Code'),
    ];

    $form['stripe']['billing_details']['stripe_billing_phone'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Phone'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function formatTextItem(array $element, WebformSubmissionInterface $webform_submission, array $options = []) {
    $value = $this->getValue($element, $webform_submission, $options);
    $value = $value['payment_intent'];
    $format = $this->getItemFormat($element);

    if ($format === 'raw') {
      return $value;
    }

    $options += ['prefixing' => TRUE];
    if ($options['prefixing']) {
      if (isset($element['#field_prefix'])) {
        $value = strip_tags($element['#field_prefix']) . $value;
      }
      if (isset($element['#field_suffix'])) {
        $value .= strip_tags($element['#field_suffix']);
      }
    }

    return $value;
  }

}
