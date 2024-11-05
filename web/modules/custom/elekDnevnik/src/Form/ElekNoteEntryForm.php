<?php

namespace Drupal\elekDnevnik\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;

class ElekNoteEntryForm extends FormBase {

  public function getFormId() {
    return 'elek_note_entry_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['datum_upisa'] = [
        '#type' => 'hidden',
        '#value' => date('Y-m-d'),
    ];

    $selected_date = $form_state->getValue('datum_upisa') ?? date('Y-m-d');
    $available_classes = $this->getAvailableClassNumbers($selected_date);

    $form['redni_broj_casa'] = [
        '#type' => 'select',
        '#title' => t('Redni broj časa'),
        '#options' => $available_classes,
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
        if ($role !== 'profesor' && strpos($role, 'odeljenje') === false) {
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

    $selected_class = $form_state->getValue('odeljenje');
    $students = $this->loadStudentsByClass($selected_class);
    
    if (!empty($students)) {
        $form['students_container']['ucenici'] = [
            '#type' => 'select',
            '#title' => t('Učenici'),
            '#options' => array_reduce($students, function ($carry, $student) {
                $carry[$student->id] = $student->first_name . ' ' . $student->last_name;
                return $carry;
            }, []),
            '#required' => TRUE,
        ];
    } else {
        $form['students_container']['ucenici'] = [
            '#markup' => t('Nema učenika u odeljenju @odeljenje.', ['@odeljenje' => $selected_class]),
        ];
    }

    $form['napomena'] = [
        '#type' => 'textarea',
        '#title' => t('Napomena'),
        '#required' => TRUE,
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => t('Snimi'),
    ];

    return $form;
  }

  protected function getAvailableClassNumbers($date) {
    $connection = \Drupal::database();
    $result = $connection->query("SELECT redni_broj_casa FROM {class_entries} WHERE datum_upisa = :date", [
      ':date' => $date,
    ])->fetchCol();

    $class_numbers = range(1, 7);
    foreach ($result as $taken_class) {
      unset($class_numbers[array_search($taken_class, $class_numbers)]);
    }

    return array_combine($class_numbers, $class_numbers);
  }

  protected function loadStudentsByClass($class) {
    $connection = \Drupal::database();
    return $connection->query("SELECT id, first_name, last_name FROM {user_registration} WHERE role LIKE :role", [
      ':role' => 'odeljenje_' . $class . ',ucenik'
    ])->fetchAll();
  }

  public function updateStudents(array &$form, FormStateInterface $form_state) {
    return $form['students_container'];
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $connection = \Drupal::database();
    $date = $form_state->getValue('datum_upisa');

    $connection->insert('student_notes')
      ->fields([
        'datum_upisa' => $date,
        'redni_broj_casa' => $form_state->getValue('redni_broj_casa'),
        'naziv_predmeta' => $form_state->getValue('naziv_predmeta'),
        'napomena' => $form_state->getValue('napomena'),
        'odeljenje' => $form_state->getValue('odeljenje'),
        'student_id' => $form_state->getValue('ucenici'),
      ])
      ->execute();

    \Drupal::messenger()->addMessage(t('Podaci o napomeni su uspešno sačuvani.'));
  }
}