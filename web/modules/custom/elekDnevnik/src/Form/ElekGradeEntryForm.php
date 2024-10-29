<?php

namespace Drupal\elekDnevnik\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;

class ElekGradeEntryForm extends FormBase {

  public function getFormId() {
    return 'elek_grade_entry_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $connection = \Drupal::database();
    $current_user = \Drupal::currentUser();

    $form['datum_upisa'] = [
      '#type' => 'date',
      '#title' => t('Datum upisa'),
      '#default_value' => date('Y-m-d'),
      '#required' => TRUE,
    ];

    $form['odeljenje'] = [
      '#type' => 'select',
      '#title' => t('Odeljenje'),
      '#options' => [
        'I1' => t('I1'),
        'I2' => t('I2'),
        'IV1' => t('IV1'),
        'IV2' => t('IV2'),
      ],
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::updateStudents',
        'wrapper' => 'students-container',
      ],
    ];

    $user_role_data = $connection->query("SELECT role FROM {user_registration} WHERE username = :username", [
      ':username' => $current_user->getAccountName(),
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

    $form['tip_ocene'] = [
      '#type' => 'select',
      '#title' => t('Tip ocene'),
      '#options' => [
        'kontrolni' => t('Kontrolni'),
        'odgovaranje' => t('Odgovaranje'),
      ],
      '#required' => TRUE,
    ];

    $form['students_container'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'students-container'],
    ];

    if ($class = $form_state->getValue('odeljenje')) {
      $students = $this->loadStudentsByClass($class, $form_state);

      $form['students_container']['ucenici'] = [
        '#type' => 'table',
        '#header' => [t('Učenik'), t('Ocena')],
      ];

      foreach ($students as $student) {
        $form['students_container']['ucenici'][$student->id]['name'] = [
          '#markup' => $student->first_name . ' ' . $student->last_name,
        ];
        $form['students_container']['ucenici'][$student->id]['grade'] = [
          '#type' => 'select',
          '#options' => [1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5'],
          '#empty_option' => t('- Select Grade -'),
        ];
      }

      $form['students_container']['pager'] = [
        '#type' => 'pager',
      ];
    }

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => t('Snimi'),
    ];

    return $form;
  }

  protected function loadStudentsByClass($class, FormStateInterface $form_state) {
    $query = \Drupal::database()->select('user_registration', 'u')
      ->fields('u', ['id', 'first_name', 'last_name'])
      ->condition('role', 'odeljenje_' . $class . ',ucenik', 'LIKE');
    $pager = $query->extend('Drupal\Core\Database\Query\PagerSelectExtender')->limit(5);
    return $pager->execute()->fetchAll();
  }

  public function updateStudents(array &$form, FormStateInterface $form_state) {
    return $form['students_container'];
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $connection = \Drupal::database();
    $datum_upisa = $form_state->getValue('datum_upisa');
    $odeljenje = $form_state->getValue('odeljenje');
    $naziv_predmeta = $form_state->getValue('naziv_predmeta');
    $tip_ocene = $form_state->getValue('tip_ocene');
    
    $students_grades = $form_state->getValue(['students_container', 'ucenici']);
    
    if (is_array($students_grades)) {
      $grades_entered = FALSE;
      
      foreach ($students_grades as $student_id => $data) {
        $grade = $data['grade'];
        
        if (!empty($grade)) {
          $grades_entered = TRUE;
          
          $connection->insert('student_grades')
            ->fields([
              'datum_upisa' => $datum_upisa,
              'odeljenje' => $odeljenje,
              'naziv_predmeta' => $naziv_predmeta,
              'tip_ocene' => $tip_ocene,
              'student_id' => $student_id,
              'ocena' => $grade,
            ])
            ->execute();
        }
      }
      
      if ($grades_entered) {
        \Drupal::messenger()->addMessage(t('Ocene su uspešno sačuvane.'));
      } else {
        \Drupal::messenger()->addMessage(t('Nema unesenih ocena.'), 'status');
      }
    } else {
      \Drupal::messenger()->addMessage(t('Nema unesenih ocena.'), 'status');
    }
  }  
}
