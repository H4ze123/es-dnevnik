<?php

namespace Drupal\elekDnevnik\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserAuthInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ElekLoginForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'elek_login_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#required' => TRUE,
    ];

    $form['password'] = [
      '#type' => 'password',
      '#title' => $this->t('Password'),
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Login'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $username = $form_state->getValue('username');
    $password = $form_state->getValue('password');

    $user = user_load_by_name($username);

    if ($user) {
      $auth = \Drupal::service('user.auth');
      if ($auth->authenticate($username, $password)) {
        user_login_finalize($user);
        $this->messenger()->addStatus($this->t('Login successful.'));

        // Redirect after login
        $form_state->setRedirect('<front>');  
      }
      else {
        $this->messenger()->addError($this->t('Invalid password.'));
      }
    }
    else {
      $this->messenger()->addError($this->t('Username does not exist.'));
    }
  }
}