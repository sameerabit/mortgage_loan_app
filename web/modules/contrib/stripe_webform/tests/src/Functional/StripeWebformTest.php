<?php

namespace Drupal\Tests\stripe_webform\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Test description.
 *
 * @group stripe_webform
 */
class StripeWebformTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'stripe',
    'stripe_webform',
    'webform',
  ];

  /**
   * Test callback.
   */
  public function testStripeWebform() {
    $admin_user = $this->drupalCreateUser([
      'access administration pages',
      'administer webform',
    ]);
    $this->drupalLogin($admin_user);
    $this->drupalGet(Url::fromRoute('webform.config.elements'));
    $this->assertSession()->pageTextContains('Provides a placeholder for a stripe credit card element.');
    $this->assertSession()->pageTextContains('Provides a placeholder for a stripe payment request element.');
  }

}
