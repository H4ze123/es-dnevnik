<?php

namespace Drupal\elekDnevnik\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;

class ElekClassEntryForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'elek_class_entry_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['datum_upisa'] = [
      '#type' => 'date',
      '#title' => t('Datum upisa'),
      '#default_value' => date('Y-m-d'),
      '#required' => TRUE,
    ];

    $form['redni_broj_nedelje'] = [
      '#type' => 'number',
      '#title' => t('Redni broj nedelje'),
      '#required' => TRUE,
    ];

    $form['redni_broj_casa'] = [
      '#type' => 'textfield',
      '#title' => t('Redni broj časa'),
      '#required' => TRUE,
    ];

    $current_user = \Drupal::currentUser();
    $connection = \Drupal::database();

    $user_role_data = $connection->query("SELECT role FROM {user_registration} WHERE username = :username", [
      ':username' => $current_user->getAccountName()
    ])->fetchField();
    $roles = explode(',', $user_role_data);

    $form['naziv_predmeta'] = [
      '#type' => 'select',
      '#title' => t('Naziv predmeta'),
      '#options' => [],
      '#required' => TRUE,
    ];

    if (in_array('profesor', $roles)) {
        foreach ($roles as $role) {
          if (strpos($role, 'profesor') === false) {
            $formatted_subject = ucwords(str_replace('_', ' ', $role));
            $form['naziv_predmeta']['#options'][$role] = t($formatted_subject);
          }
        }
      }

    $form['odeljenje'] = [
      '#type' => 'select',
      '#title' => t('Odeljenje'),
      '#options' => [
        'I1' => t('I1'),
        'I2' => t('I2'),
        'I3' => t('I3'),
        'IV1' => t('IV1'),
        'IV2' => t('IV2'),
        'IV3' => t('IV3'),
      ],
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::updateStudents',
        'wrapper' => 'students-container',
      ],
    ];

    $form['students_container'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'students-container'],
    ];

    if ($form_state->getValue('odeljenje')) {
      $selected_class = $form_state->getValue('odeljenje');
      $students = $this->loadStudentsByClass($selected_class);

      if ($students) {
        $form['students_container']['ucenici'] = [
          '#type' => 'checkboxes',
          '#title' => t('Učenici'),
          '#options' => array_reduce($students, function ($carry, $student) {
            $carry[$student->id] = $student->first_name . ' ' . $student->last_name;
            return $carry;
          }, []),
        ];
      } else {
        $form['students_container']['ucenici'] = [
          '#markup' => t('Nema učenika u odeljenju @odeljenje.', ['@odeljenje' => $selected_class]),
        ];
      }
    }

    $form['ukupno_casova'] = [
      '#type' => 'number',
      '#title' => t('Ukupno časova'),
      '#required' => TRUE,
    ];

    $form['tema'] = [
      '#type' => 'textarea',
      '#title' => t('Tema'),
      '#required' => TRUE,
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => t('Snimi'),
    ];

    return $form;
  }

  protected function loadStudentsByClass($class) {
    $connection = \Drupal::database();
    return $connection->query("SELECT id, first_name, last_name FROM {user_registration} WHERE role LIKE :role", [
      ':role' => 'odeljenje_' . $class . ',ucenik'
    ])->fetchAll();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $connection = \Drupal::database();
    $date = $form_state->getValue('datum_upisa');

    $connection->insert('class_entries')
      ->fields([
        'datum_upisa' => $date,
        'redni_broj_nedelje' => $form_state->getValue('redni_broj_nedelje'),
        'redni_broj_casa' => $form_state->getValue('redni_broj_casa'),
        'naziv_predmeta' => $form_state->getValue('naziv_predmeta'),
        'ukupno_casova' => $form_state->getValue('ukupno_casova'),
        'tema' => $form_state->getValue('tema'),
        'odeljenje' => $form_state->getValue('odeljenje'),
        'profesor_id' => \Drupal::currentUser()->id(),
      ])
      ->execute();

    foreach ($form_state->getValue('ucenici') as $student_id => $is_absent) {
      if ($is_absent) {
        $connection->insert('student_attendance')
          ->fields([
            'student_id' => $student_id,
            'date' => $date,
            'is_absent' => TRUE,
          ])
          ->execute();
      }
    }

    \Drupal::messenger()->addMessage(t('Podaci o času i prisutnosti učenika su uspešno sačuvani.'));
  }

  public function generateReport(array &$form, FormStateInterface $form_state) {
    // Implementacija izveštaja (opciono)
    \Drupal::messenger()->addMessage(t('Izveštaj nije implementiran.'));
  }

  /**
   * AJAX callback
   */
  public function updateStudents(array &$form, FormStateInterface $form_state) {
    return $form['students_container'];
  }
}
