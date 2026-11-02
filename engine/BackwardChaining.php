
<?php

class BackwardChaining
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Memeriksa hipotesis pelanggaran.
     *
     * $jawaban berbentuk:
     * [id_fakta => 'ya' atau 'tidak']
     *
     * Status hasil:
     * - terbukti
     * - perlu_fakta
     * - tidak_terbukti
     */
    public function periksa(int $pelanggaranId, array $jawaban): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, kode
             FROM rules
             WHERE pelanggaran_id = ? AND aktif = 1
             ORDER BY id'
        );

        $stmt->execute([$pelanggaranId]);
        $rules = $stmt->fetchAll();

        if (!$rules) {
            return [
                'status' => 'tidak_terbukti',
                'rule_id' => null,
                'fakta_belum_diketahui' => [],
                'pesan' => 'Tidak ada rule aktif untuk hipotesis ini.'
            ];
        }

        $faktaBelumDiketahui = [];

        foreach ($rules as $rule) {
            $stmtKondisi = $this->pdo->prepare(
                'SELECT rf.fakta_id, rf.nilai,
                        f.kode, f.pertanyaan
                 FROM rule_fakta rf
                 JOIN fakta f ON f.id = rf.fakta_id
                 WHERE rf.rule_id = ? AND f.aktif = 1'
            );

            $stmtKondisi->execute([$rule['id']]);
            $kondisi = $stmtKondisi->fetchAll();

            if (!$kondisi) {
                continue;
            }

            $ruleTerpenuhi = true;
            $ruleGagal = false;
            $belumDiketahui = [];

            foreach ($kondisi as $item) {
                $faktaId = (int)$item['fakta_id'];
                $nilaiYangDiharapkan = $item['nilai'];

                if (!array_key_exists($faktaId, $jawaban)) {
                    $ruleTerpenuhi = false;

                    $belumDiketahui[$faktaId] = [
                        'id' => $faktaId,
                        'kode' => $item['kode'],
                        'pertanyaan' => $item['pertanyaan']
                    ];

                    continue;
                }

                $nilaiJawaban = $jawaban[$faktaId];

                if (!in_array($nilaiJawaban, ['ya', 'tidak'], true)) {
                    $ruleTerpenuhi = false;

                    $belumDiketahui[$faktaId] = [
                        'id' => $faktaId,
                        'kode' => $item['kode'],
                        'pertanyaan' => $item['pertanyaan']
                    ];

                    continue;
                }

                if ($nilaiJawaban !== $nilaiYangDiharapkan) {
                    $ruleTerpenuhi = false;
                    $ruleGagal = true;
                    break;
                }
            }

            if ($ruleTerpenuhi) {
                return [
                    'status' => 'terbukti',
                    'rule_id' => (int)$rule['id'],
                    'rule_kode' => $rule['kode'],
                    'fakta_belum_diketahui' => [],
                    'pesan' => 'Hipotesis terbukti berdasarkan rule dan jawaban.'
                ];
            }

            if (!$ruleGagal) {
                $faktaBelumDiketahui += $belumDiketahui;
            }
        }

        if ($faktaBelumDiketahui) {
            return [
                'status' => 'perlu_fakta',
                'rule_id' => null,
                'fakta_belum_diketahui' => array_values(
                    $faktaBelumDiketahui
                ),
                'pesan' => 'Diperlukan jawaban tambahan untuk menguji hipotesis.'
            ];
        }

        return [
            'status' => 'tidak_terbukti',
            'rule_id' => null,
            'fakta_belum_diketahui' => [],
            'pesan' => 'Tidak ada rule yang terpenuhi berdasarkan jawaban.'
        ];
    }
}
