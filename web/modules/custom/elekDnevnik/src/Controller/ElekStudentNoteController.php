<?php

namespace Drupal\elekDnevnik\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ElekStudentNoteController extends ControllerBase {

    protected $currentUser;

    public function __construct(AccountInterface $current_user) {
        $this->currentUser = $current_user;
    }

    public static function create(ContainerInterface $container) {
        return new static(
            $container->get('current_user')
        );
    }

    public function viewNote() {
        $current_user = \Drupal::currentUser();
        $connection = \Drupal::database();

        $user_data = $connection->query("SELECT id FROM {user_registration} WHERE username = :username", [
            ':username' => $current_user->getAccountName()
        ])->fetchAssoc();

        $homeroom_id = $user_data['id'];

        $user_query = Database::getConnection()->select('user_registration', 'u')
            ->fields('u', ['role'])
            ->condition('id', $homeroom_id, '=');
        $homeroom_role = $user_query->execute()->fetchfield();

        $odeljenje = strtoupper(str_replace('odeljenje_', '', explode(',', $homeroom_role)[0]));

        $note_query = Database::getConnection()->select('student_notes', 'g')
            ->fields('g', ['datum_upisa', 'redni_broj_casa', 'naziv_predmeta', 'napomena', 'student_id'])
            ->condition('odeljenje', $odeljenje, '=');
        $notes = $note_query->execute()->fetchAll();

        if (empty($notes)) {
            \Drupal::logger('elekDnevnik')->info('Nema napomena za odeljenje: @odeljenje', ['@odeljenje' => $odeljenje]);
        }

        $rows = [];
        foreach ($notes as $note) {
            $student_data = $connection->query("SELECT first_name, last_name FROM {user_registration} WHERE id = :student_id", [
                ':student_id' => $note->student_id
            ])->fetchAssoc();

            $full_name = isset($student_data['first_name']) && isset($student_data['last_name'])
                ? $student_data['first_name'] . ' ' . $student_data['last_name']
                : $this->t('Nepoznato');

            $rows[] = [
                'datum_upisa' => $note->datum_upisa,
                'redni_broj_casa' => $note->redni_broj_casa,
                'naziv_predmeta' => ucfirst(str_replace('_', ' ', $note->naziv_predmeta)),
                'puno_ime' => $full_name,
            ];
        }

        $header = [
            'datum_upisa' => $this->t('Datum upisa'),
            'redni_broj_casa' => $this->t('Redni broj casa'),
            'naziv_predmeta' => $this->t('Predmet'),
            'puno_ime' => $this->t('Ime'),
        ];

        $build = [
            '#type' => 'table',
            '#header' => $header,
            '#rows' => $rows,
            '#empty' => $this->t('Nemate zabelezenih napomena.'),
        ];

        return $build;
    }
}
