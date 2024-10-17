<?php

namespace Drupal\elekDnevnik\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;

class ElekDnevnikUserRegisterForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'elek_dnevnik_user_register_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Registration form fields
    $form['first_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First Name'),
      '#required' => TRUE,
    ];

    $form['last_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Last Name'),
      '#required' => TRUE,
    ];

    $form['username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#required' => TRUE,
    ];

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#required' => TRUE,
    ];

    $form['password'] = [
      '#type' => 'password',
      '#title' => $this->t('Password'),
      '#required' => TRUE,
    ];

    // Load existing roles for the combobox
    $roles = \Drupal::entityTypeManager()->getStorage('user_role')->loadMultiple();
    $role_options = [];
    foreach ($roles as $role) {
      $role_options[$role->id()] = $role->label();
    }

    $form['roles'] = [
      '#type' => 'select',
      '#title' => $this->t('Role'),
      '#options' => $role_options,
      '#required' => TRUE,
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Register'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    // Check for existing user by email.
    $existing_user = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['mail' => $values['email']]);

    if (!empty($existing_user)) {
      \Drupal::logger('elekDnevnik')->error('Email address already in use: @email', ['@email' => $values['email']]);
      \Drupal::messenger()->addError($this->t('Email address is already in use.'));
      return;
    }

    // Create a new user entity.
    $user = User::create([
      'name' => $values['username'],
      'mail' => $values['email'],
      'pass' => $values['password'], // Save the password as plaintext
      'status' => 1,
      'roles' => [$values['roles']],
    ]);

    // Save user to the database.
    $user->save();

    // Prepare the data for the new registration table.
    $connection = \Drupal::database();

    $result = $connection->insert('user_registration')
      ->fields([
        'first_name' => $values['first_name'],
        'last_name' => $values['last_name'],
        'username' => $values['username'],
        'email' => $values['email'],
        'role' => $values['roles'], // Save selected role
        'password' => $values['password'], // Save plaintext password here too
      ])
      ->execute();

    // Log insertion result.
    if ($result) {
      \Drupal::logger('elekDnevnik')->info('User registration data saved for: @username', ['@username' => $values['username']]);
      \Drupal::messenger()->addStatus($this->t('Registration successful.'));
    } else {
      \Drupal::logger('elekDnevnik')->error('Failed to save registration data for: @username', ['@username' => $values['username']]);
      \Drupal::messenger()->addError($this->t('Registration failed, please try again.'));
    }
  }
}
