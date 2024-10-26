<?php

namespace Drupal\elekDnevnik\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;

class ElekClassEntryForm extends FormBase {

  public function getFormId() {
    return 'elek_class_entry_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['datum_upisa'] = [
      '#type' => 'date',
      '#title' => t('Datum upisa'),
      '#default_value' => date('Y-m-d'),
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::updateWeekAndClasses',
        'wrapper' => 'class-info-container',
      ],
    ];

    $selected_date = $form_state->getValue('datum_upisa') ?? date('Y-m-d');
    $week_number = $this->getWeekNumberFromDate($selected_date);

    $form['redni_broj_nedelje'] = [
      '#type' => 'number',
      '#title' => t('Redni broj nedelje'),
      '#default_value' => $week_number,
      '#required' => TRUE,
      '#disabled' => TRUE,
    ];

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
      '#ajax' => [
        'callback' => '::updateTotalClasses',
        'wrapper' => 'total-classes-container',
      ],
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
        'callback' => '::updateTotalClasses',
        'wrapper' => 'total-classes-container',
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

    $total_classes = $this->getTotalClassesForSubjectAndClass($form_state->getValue('naziv_predmeta'), $form_state->getValue('odeljenje'));

    $form['ukupno_casova'] = [
      '#type' => 'number',
      '#title' => t('Ukupno časova'),
      '#default_value' => $total_classes,
      '#required' => TRUE,
      '#disabled' => TRUE,
      '#prefix' => '<div id="total-classes-container">',
      '#suffix' => '</div>',
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

  protected function getWeekNumberFromDate($date) {
    $first_week_date = '2024-09-01';
    $date_diff = (strtotime($date) - strtotime($first_week_date)) / (60 * 60 * 24 * 7);
    return ceil($date_diff) + 1;
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

  protected function getTotalClassesForSubjectAndClass($subject, $class) {
    if (!$subject || !$class) {
      return 0;
    }
    
    $connection = \Drupal::database();
    return $connection->query("SELECT COUNT(*) FROM {class_entries} WHERE naziv_predmeta = :subject AND odeljenje = :class", [
      ':subject' => $subject,
      ':class' => $class,
    ])->fetchField();
  }

  protected function loadStudentsByClass($class) {
    $connection = \Drupal::database();
    return $connection->query("SELECT id, first_name, last_name FROM {user_registration} WHERE role LIKE :role", [
      ':role' => 'odeljenje_' . $class . ',ucenik'
    ])->fetchAll();
  }

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

  public function updateWeekAndClasses(array &$form, FormStateInterface $form_state) {
    return $form;
  }

  public function updateTotalClasses(array &$form, FormStateInterface $form_state) {
    $subject = $form_state->getValue('naziv_predmeta');
    $class = $form_state->getValue('odeljenje');
    $form['ukupno_casova']['#default_value'] = $this->getTotalClassesForSubjectAndClass($subject, $class);
  
    return $form['ukupno_casova'];
  }

  public function updateStudents(array &$form, FormStateInterface $form_state) {
    return $form['students_container'];
  }
}
