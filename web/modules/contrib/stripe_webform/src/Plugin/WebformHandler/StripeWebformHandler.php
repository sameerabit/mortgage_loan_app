<?php

namespace Drupal\stripe_webform\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;
use Stripe\Exception\ExceptionInterface;
use Stripe\StripeClient;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Webform submission stripe handler.
 *
 * @WebformHandler(
 *   id = "stripe",
 *   label = @Translation("Stripe"),
 *   category = @Translation("Stripe"),
 *   description = @Translation("Creates subscription and other stripe objects."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_OPTIONAL,
 * )
 */
class StripeWebformHandler extends WebformHandlerBase {

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The token manager.
   *
   * @var \Drupal\webform\WebformTokenManagerInterface
   */
  protected $tokenManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->configFactory = $container->get('config.factory');
    $instance->tokenManager = $container->get('webform.token_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'amount' => '',
      'price_id' => '',
      'quantity' => '',
      'currency' => 'usd',
      'metadata' => '',
      'stripe_customer_create' => '',
      'stripe_subscription_create' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $webform = $this->getWebform();

    $form['stripe'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Stripe settings'),
    ];

    $form['stripe']['amount'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Amount'),
      '#default_value' => $this->configuration['amount'],
      '#parents' => ['settings', 'amount'],
      '#description' => $this->t('Amount to charge the credit card. You may use tokens.'),
      '#required' => TRUE,
    ];

    $form['stripe']['subscription'] = [
      '#type' => 'details',
      '#title' => t('Subscriptions'),
      '#description' => $this->t('Optional fields to subscribe the customer to a plan instead of a directly charging it.'),
    ];
    $form['stripe']['subscription']['price_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Price ID'),
      '#default_value' => $this->configuration['price_id'],
      '#parents' => ['settings', 'price_id'],
      '#description' => $this->t('Stripe subscriptions price_ id. You may use tokens.'),
    ];
    $form['stripe']['subscription']['quantity'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Quantity'),
      '#default_value' => $this->configuration['quantity'],
      '#parents' => ['settings', 'quantity'],
      '#description' => $this->t('Quantity of the plan to subscribe. You may use tokens.'),
    ];

    $form['stripe']['metadata_details'] = [
      '#type' => 'details',
      '#title' => $this->t('Meta data'),
      '#description' => $this->t('Additional <a href=":url" target="_blank">metadata</a> in YAML format, each line a <em>key: value</em> element. You may use tokens.', [':url' => 'https://stripe.com/docs/api#metadata']),
    ];

    $form['stripe']['metadata_details']['metadata'] = [
      '#type' => 'webform_codemirror',
      '#mode' => 'yaml',
      '#title' => $this->t('Meta data'),
      '#parents' => ['settings', 'metadata'],
      '#default_value' => $this->configuration['metadata'],
    ];

    $form['stripe']['advanced'] = [
      '#type' => 'details',
      '#title' => t('Advanced settings'),
      '#open' => FALSE,
    ];
    $form['stripe']['advanced']['stripe_customer_create'] = [
      '#type' => 'webform_codemirror',
      '#mode' => 'yaml',
      '#title' => $this->t('Customer create object'),
      '#parents' => ['settings', 'stripe_customer_create'],
      '#default_value' => $this->configuration['stripe_customer_create'],
      '#description' => $this->t('Additional fields of the stripe API call to <a href=":url" target="_blank">create a customer</a>. You cannot override the keys set by the fields above.', [':url' => 'https://stripe.com/docs/api#create_customer']),
    ];
    $form['stripe']['advanced']['stripe_subscription_create'] = [
      '#type' => 'webform_codemirror',
      '#mode' => 'yaml',
      '#title' => $this->t('Subscription create object'),
      '#parents' => ['settings', 'stripe_subscription_create'],
      '#default_value' => $this->configuration['stripe_subscription_create'],
      '#description' => $this->t('Additional fields of the stripe API call to <a href=":url" target="_blank">create a subscription</a>. You cannot override the keys set by the fields above.', [':url' => 'https://stripe.com/docs/api#create_subscription']),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $values = $form_state->getValues();
    foreach ($this->configuration as $name => $value) {
      if (isset($values[$name])) {
        $this->configuration[$name] = $values[$name];
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE) {
    // Do nothing if updating the submission.
    if ($update) {
      return;
    }

    $elements = $webform_submission->getWebform()->getElementsInitializedFlattenedAndHasValue('view');
    foreach ($elements as $key => $element) {
      if (in_array($element['#type'], ['stripe', 'stripe_paymentrequest'])) {
        $stripe_data = $webform_submission->getElementData($key);
        if (!empty($stripe_data['processed'])) {
          break;
        }
      }
    }

    // Do nothing if there's no submitted payment intent information.
    if (empty($stripe_data['processed'])) {
      return;
    }

    $uuid = $this->configFactory->get('system.site')->get('uuid');

    $config = $this->configFactory->get('stripe.settings');
    $apikeySecret = $config->get('apikey.' . $config->get('environment') . '.secret');

    // Replace tokens.
    $data = $this->tokenManager->replace($this->configuration, $webform_submission);

    try {
      $stripe = new StripeClient($apikeySecret);

      $metadata = [
        'uuid' => $uuid,
        'webform' => $webform_submission->getWebform()->label(),
        'webform_id' => $webform_submission->getWebform()->id(),
        'webform_submission_id' => $webform_submission->id(),
      ];

      if (!empty($data['metadata'])) {
        $metadata += Yaml::decode($data['metadata']);
      }

      $payment_intent = $stripe->paymentIntents->retrieve($stripe_data['payment_intent']);
      $payment_method = $stripe->paymentMethods->retrieve($payment_intent->payment_method);

      // Create a Customer:
      $stripe_customer_create = [
        'name' => $payment_method->billing_details->name,
        'email' => $payment_method->billing_details->email,
        'address' => $payment_method->billing_details->address->toArray(),
        'metadata' => $metadata,
      ];

      if (!empty($element['#webform_stripe_subscriptions'])) {
        $stripe_customer_create += [
          'payment_method' => $payment_intent->payment_method,
          'invoice_settings' => [
            'default_payment_method' => $payment_intent->payment_method,
          ],
        ];
      }

      if (!empty($data['stripe_customer_create'])) {
        $stripe_customer_create += Yaml::decode($data['stripe_customer_create']);
      }
      $customer = $stripe->customers->create($stripe_customer_create);

      // Subscriptions
      // .
      if (!empty($element['#webform_stripe_subscriptions'])) {
        if (!empty($data['price_id'])) {
          $price = $stripe->prices->retrieve($data['price_id']);
          $interval = "+{$price->recurring->interval_count} {$price->recurring->interval}";
          $anchor = strtotime($interval);

          $stripe_subscription_create = [
            'customer' => $customer->id,
            'items' => [
              [
                'price' => $price->id,
                'quantity' => $data['quantity'] ?: 1,
              ],
            ],
            'metadata' => $metadata,
            'billing_cycle_anchor' => $anchor,
            'proration_behavior' => 'none',
          ];
          if (!empty($data['stripe_subscription_create'])) {
            $stripe_subscription_create += Yaml::decode($data['stripe_subscription_create']);
          }

          $stripe->subscriptions->create($stripe_subscription_create);
        }
      }
    }
    catch (ExceptionInterface $e) {
      $this->messenger()->addError($this->t('Stripe error: %error', ['%error' => $e->getMessage()]), 'error');
    }
  }

}
