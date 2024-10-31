<?php

namespace Drupal\elekDnevnik\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Database\Database;

class ElekStudentGradesController extends ControllerBase {

  public function gradesPage(Request $request) {
    $current_user = \Drupal::currentUser();
    $user_id = $current_user->id();

    $connection = Database::getConnection();
    $query = $connection->select('user_registration', 'ur')
      ->fields('ur', ['first_name', 'last_name'])
      ->condition('id', $user_id)
      ->execute()
      ->fetchObject();

    if (!$query) {
      return [
        '#markup' => $this->t('Student data not found.'),
      ];
    }

    $student_name = $query->first_name . ' ' . $query->last_name;

    $grades_query = $connection->select('student_grades', 'sg')
      ->fields('sg', ['naziv_predmeta', 'ocena'])
      ->condition('student_id', $user_id)
      ->execute();

    $grades_data = [];
    foreach ($grades_query as $record) {
      $subject = str_replace('_', ' ', ucfirst($record->naziv_predmeta)); // format subject name
      $grades_data[$subject][] = $record->ocena;
    }

    $grades_display = [];
    foreach ($grades_data as $subject => $grades) {
      $average = array_sum($grades) / count($grades);
      $grades_display[] = [
        'subject' => $subject,
        'grades' => implode(', ', $grades),
        'average' => number_format($average, 2),
      ];
    }

    return [
      '#theme' => 'student_grades_report',
      '#student_name' => $student_name,
      '#grades_display' => $grades_display,
      '#attached' => [
        'library' => [
          'elekDnevnik/student_grades',
        ],
      ],
    ];
  }

}
