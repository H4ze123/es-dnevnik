<?php

namespace Drupal\elekDnevnik\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;

class ElekActivityEntryForm extends FormBase {

  public function getFormId() {
    return 'elek_activity_entry_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['datum_aktivnosti'] = [
      '#type' => 'date',
      '#title' => t('Datum upisa'),
      '#default_value' => date('Y-m-d'),
      '#min' => date('Y-m-d'),
      '#required' => TRUE,
    ];
      
    $current_user = \Drupal::currentUser();
    $connection = \Drupal::database();

    $user_data = $connection->query("SELECT role, id FROM {user_registration} WHERE username = :username", [
      ':username' => $current_user->getAccountName()
    ])->fetchAssoc();

    $user_role_data = $user_data['role'];
    $prof_id = $user_data['id'];
    $roles = explode(',', $user_role_data);

    $form['#prof_id'] = $prof_id;

    $form['naziv_predmeta'] = [
      '#type' => 'select',
      '#title' => t('Naziv predmeta'),
      '#options' => [],
      '#required' => TRUE,
    ];

    $form['vrsta_aktivnosti'] = [
      '#type' => 'select',
      '#title' => t('Vrsta aktivnosti'),
      '#options' => [
        'odgovaranje' => t('Odgovaranje'),
        'prezentacija' => t('Prezentacija'),
        'Kontrolni' => t('Kontrolni'),
        'Blic' => t('Blic'),
      ],
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
      '#required' => TRUE
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => t('Snimi'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $connection = \Drupal::database();
    $date = $form_state->getValue('datum_aktivnosti');
    $prof_id = $form['#prof_id'];

    $connection->insert('student_activity')
      ->fields([
        'datum_aktivnosti' => $date,
        'naziv_predmeta' => $form_state->getValue('naziv_predmeta'),
        'vrsta_aktivnosti' => $form_state->getValue('vrsta_aktivnosti'),
        'odeljenje' => $form_state->getValue('odeljenje'),
        'profesor_id' => $prof_id,
      ])
      ->execute();

    \Drupal::messenger()->addMessage(t('Podaci o aktivnosti su uspešno sačuvani.'));
  }
}
