<?php

namespace Drupal\stripe_webform\Event;

use Drupal\webform\WebformSubmissionInterface;
use Stripe\Event as StripeEvent;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event that is fired when a webhook related to a webform submission is received.
 */
class StripeWebformWebhookEvent extends Event {

  const EVENT_NAME = StripeWebformEvents::WEBHOOK;

  public function __construct(
    public string $type,
    public WebformSubmissionInterface $webform_submission,
    protected StripeEvent $event,
  ) {}

}
