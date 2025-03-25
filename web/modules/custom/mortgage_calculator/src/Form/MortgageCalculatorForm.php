<?php


namespace Drupal\mortgage_calculator\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;


class MortgageCalculatorForm extends FormBase
{

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'mortgage_calculator_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $form['loan_amount'] = [
            '#type' => 'number',
            '#title' => $this->t('Loan Amount'),
            '#required' => true,
        ];

        $form['interest_rate'] = [
            '#type' => 'number',
            '#title' => $this->t('Interest Rate'),
            '#required' => true,
        ];

        $form['loan_term'] = [
            '#type' => 'number',
            '#title' => $this->t('Loan Term (Years)'),
            '#required' => true,
        ];

        $form['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Calculate'),
        ];

        if ($form_state->get('monthly_payment')) {
            $form['result'] = [
                '#markup' => '<div class="mortgage-result"><h3>' . $this->t('Monthly Payment: @amount SEK', [
                        '@amount' => $form_state->get('monthly_payment')
                    ]) . '</h3></div>',
            ];
        }

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {
        if (!is_numeric($form_state->getValue('loan_amount'))) {
            $form_state->setErrorByName('loan_amount', $this->t('Loan Amount must be a number.'));
        }

        if (!is_numeric($form_state->getValue('interest_rate'))) {
            $form_state->setErrorByName('interest_rate', $this->t('Interest Rate must be a number.'));
        }

        if (!is_numeric($form_state->getValue('loan_term'))) {
            $form_state->setErrorByName('loan_term', $this->t('Loan Term must be a number.'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $loan_amount = $form_state->getValue('loan_amount');
        $interest_rate = $form_state->getValue('interest_rate');
        $loan_term = $form_state->getValue('loan_term');

        $monthly_payment = $this->calculateMonthlyPayment($loan_amount, $interest_rate, $loan_term);

        $form_state->set('monthly_payment', number_format($monthly_payment, 2));
        $form_state->setRebuild(true);

    }

    private function calculateMonthlyPayment(mixed $loan_amount, mixed $interest_rate, mixed $loan_term): float
    {
        $monthAmount = ($loan_amount / $loan_term) / 1200;
        $interestRate = $interest_rate / 12;
        return  $monthAmount + ($monthAmount * $interestRate);
    }
}