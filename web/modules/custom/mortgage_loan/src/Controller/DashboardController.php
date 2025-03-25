<?php

namespace Drupal\mortgage_loan\Controller;

use Drupal\Core\Controller\ControllerBase;

class DashboardController extends ControllerBase {

    public function dashboard(): array
    {
        $build = [];

        $build['intro'] = [
            '#markup' => '<h2>Mortgage Loan Dashboard</h2><p>Welcome to your custom loan management panel.</p>',
        ];

        $build['calculator_link'] = [
            '#markup' => '<p><a href="/mortgage-calculator" class="button">→ Go to Mortgage Calculator</a></p>',
        ];

        $build['view_link'] = [
            '#markup' => '<p><a href="/mortgage-loans" class="button">📋 View All Applications</a></p>',
        ];

        return $build;
    }

}