<?php

namespace Drupal\elekDnevnik\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ElekStudentGradesController extends ControllerBase {

    protected $currentUser;

    public function __construct(AccountInterface $current_user) {
        $this->currentUser = $current_user;
    }

    public static function create(ContainerInterface $container) {
        return new static(
            $container->get('current_user')
        );
    }

    public function viewGrades() {
        $current_user = \Drupal::currentUser();
        $connection = \Drupal::database();

        $user_data = $connection->query("SELECT id FROM {user_registration} WHERE username = :username", [
            ':username' => $current_user->getAccountName()
        ])->fetchAssoc();

        $student_id = $user_data['id'];

        $query = Database::getConnection()->select('student_grades', 'g');
        $query->fields('g', ['naziv_predmeta', 'ocena']);
        $query->condition('student_id', $student_id);

        $results = $query->execute()->fetchAll();

        if (empty($results)) {
            \Drupal::logger('elekDnevnik')->info('Nema rezultata za korisnika ID: @student_id', ['@student_id' => $student_id]);
        }

        $grades_by_subject = [];
        foreach ($results as $result) {
            $subject = $result->naziv_predmeta;
            if (!isset($grades_by_subject[$subject])) {
                $grades_by_subject[$subject] = [];
            }
            $grades_by_subject[$subject][] = $result->ocena;
        }

        $rows = [];
        foreach ($grades_by_subject as $subject => $grades) {
            $grades_string = implode(', ', $grades);
            $average_grade = array_sum($grades) / count($grades);
            $rows[] = [
                'subject' => ucfirst(str_replace('_', ' ', $subject)),
                'grades' => $grades_string,
                'average' => number_format($average_grade, 2),
            ];
        }

        $header = [
            'subject' => $this->t('Predmet'),
            'grades' => $this->t('Ocene'),
            'average' => $this->t('Prosečna ocena'),
        ];

        $build = [
            '#type' => 'table',
            '#header' => $header,
            '#rows' => $rows,
            '#empty' => $this->t('Nemate zabeležene ocene.'),
        ];

        return $build;
    }
}
