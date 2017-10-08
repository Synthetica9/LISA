<?php

/**
 * @author Adriaan Knapen <a.d.knapen@protonmail.com>
 * @date 2-3-2017
 */

/**
 * Class Transaction
 * @property CI_DB_query_builder $db
 */
class Transaction extends ModelFrame
{
    const FIELD_TRANSACTION_ID = 'transaction_id';
    const FIELD_AUTHOR_ID = 'author_id';
    const FIELD_SUBJECT_ID = 'subject_id';
    const FIELD_TIME = 'time';
    const FIELD_DELTA = 'delta';

    protected function dependencies()
    {
        return [
            Login::class,
            Consumption::class,
        ];
    }

    public function add($subjectId, $authorId, $amount, $delta) {
        $this->db->insert(
            self::name(),
            [
                self::FIELD_SUBJECT_ID => $subjectId,
                self::FIELD_AUTHOR_ID => $authorId,
                Consumption::FIELD_AMOUNT => $amount,
                self::FIELD_DELTA => $delta,
            ]
        );
    }

    public function getAllFromSubject($subjectId) {
        return $this->db
            ->where([self::FIELD_SUBJECT_ID => $subjectId])
            ->get(self::name())
            ->result_array();
    }

    public function getAllFromAuthor($authorId) {
        return $this->db
            ->where([self::FIELD_AUTHOR_ID => $authorId])
            ->get(self::name())
            ->result_array();
    }

    public function getAll() {
        return $this->db
            ->get(self::name())
            ->result_array();
    }

    public function getCountForSubject($subjectId) {
        return $this->db
            ->where([self::FIELD_SUBJECT_ID => $subjectId])
            ->count_all(self::name());
    }

    /**
     * Checks if a subject user is on the leaderboard of the sum of most positive or negative transactions.
     *
     * @param int $subjectId The login ID of the subject.
     * @param bool $positive True if transactions with only positive deltas should regarded, false for only negative
     *      deltas.
     * @param int $position The amount of spots on the leaderboard.
     * @return bool True if the user is on the leaderboard, false otherwise.
     */
    public function getSumDeltaSubjectIdWithinTop(int $subjectId, bool $positive, int $position): bool {
        // Retrieve the sum of the delta of all negative or positive transactions, ordered and limited to retrieve only highest values.
        $sumQuery = $this->db
            ->select(self::FIELD_SUBJECT_ID)
            ->select_sum(self::FIELD_DELTA, 'sum')
            ->where(self::FIELD_DELTA . ($positive?'>':'<') . ' 0')
            ->group_by(self::FIELD_SUBJECT_ID)
            ->limit($position)
            ->order_by('sum ' . ($positive?'DESC':'ASC'))
            ->get_compiled_select(self::name());

        // Check if the specified subject is within the earlier retrieved leaderboard.
        $result = $this->db
            ->from('(' . $sumQuery . ') `'. $this->db->dbprefix('t') . '`')
            ->where('t.' . self::FIELD_SUBJECT_ID . '=' . $subjectId)
            ->get();

        // If a row is returned, then the user is on the leaderboard.
        return $result->num_rows() > 0;
    }

    public function v1() {
        return [
            'requires' => [
                Login::class => 1,
            ],
            'add' => [
                self::FIELD_TRANSACTION_ID => [
                    'type' => 'primary',
                ],
                self::FIELD_AUTHOR_ID => [
                    'type' => 'foreign',
                    'table' => Login::name(),
                    'field' => Login::FIELD_LOGIN_ID,
                ],
                self::FIELD_SUBJECT_ID => [
                    'type' => 'foreign',
                    'table' => Login::name(),
                    'field' => Login::FIELD_LOGIN_ID,
                ],
                Consumption::FIELD_AMOUNT => [
                    'type' => 'INT',
                    'constraint' => 9,
                ],
                self::FIELD_DELTA => [
                    'type' => 'INT',
                    'constraint' => 9,
                ],
                self::FIELD_TIME => [
                    'type' => 'TIMESTAMP'
                ],
            ],
        ];
    }
}