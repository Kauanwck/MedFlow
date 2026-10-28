
<?php

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function respond(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function field(string $name, int $max = 5000): string {
    $value = trim((string)($_POST[$name] ?? ''));

    if (function_exists('mb_strlen')) {
        if (mb_strlen($value) > $max) {
            $value = mb_substr($value, 0, $max);
        }
    } elseif (strlen($value) > $max) {
        $value = substr($value, 0, $max);
    }

    return $value;
}

try {
    // Verifica se o PHP tem suporte ao SQLite.
    if (!extension_loaded('pdo_sqlite')) {
        respond([
            'ok' => false,
            'error' => 'PDO_SQLITE não está habilitado no PHP. Confira a configuração do XAMPP.'
        ], 500);
    }

    // Abre o banco de dados existente.
    $db = new PDO('sqlite:' . __DIR__ . '/medflow.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('PRAGMA foreign_keys = ON');

    // Cria as tabelas somente se ainda não existirem.
    $db->exec("
        CREATE TABLE IF NOT EXISTS patients (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            birth_date TEXT,
            phone TEXT,
            notes TEXT,
            created_at TEXT NOT NULL
                DEFAULT (datetime('now','localtime'))
        )
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS anamneses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            patient_id INTEGER NOT NULL,
            complaint TEXT NOT NULL,
            history TEXT NOT NULL,
            antecedents TEXT,
            habits TEXT,
            previous_treatments TEXT,
            functional_assessment TEXT,
            created_at TEXT NOT NULL
                DEFAULT (datetime('now','localtime')),
            FOREIGN KEY(patient_id)
                REFERENCES patients(id) ON DELETE RESTRICT
        )
    ");

    $action = isset($_GET['action'])
        ? (string)$_GET['action']
        : '';

    $method = isset($_SERVER['REQUEST_METHOD'])
        ? $_SERVER['REQUEST_METHOD']
        : 'GET';

    // =========================================
    // CONSULTAS GET
    // =========================================
    if ($method === 'GET') {

        // Lista os pacientes.
        if ($action === 'patients') {
            $stmt = $db->query("
                SELECT id, name, birth_date, phone, notes, created_at
                FROM patients
                ORDER BY id DESC
            ");

            respond([
                'ok' => true,
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]);
        }

        // Lista as anamneses com o nome do paciente.
        if ($action === 'anamneses') {
            $stmt = $db->query("
                SELECT
                    a.*,
                    p.name AS patient_name
                FROM anamneses a
                INNER JOIN patients p
                    ON p.id = a.patient_id
                ORDER BY a.id DESC
            ");

            respond([
                'ok' => true,
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]);
        }

        respond([
            'ok' => false,
            'error' => 'Ação não reconhecida.'
        ], 400);
    }

    // As operações de alteração exigem POST.
    if ($method !== 'POST') {
        respond([
            'ok' => false,
            'error' => 'Método não permitido.'
        ], 405);
    }

    // =========================================
    // CADASTRAR PACIENTE
    // =========================================
    if ($action === 'create_patient') {
        $name = field('name', 150);

        if ($name === '') {
            respond([
                'ok' => false,
                'error' => 'O nome do paciente é obrigatório.'
            ], 422);
        }

        $stmt = $db->prepare("
            INSERT INTO patients (
                name,
                birth_date,
                phone,
                notes
            ) VALUES (
                :name,
                :birth_date,
                :phone,
                :notes
            )
        ");

        $stmt->execute([
            ':name' => $name,
            ':birth_date' => field('birth_date', 10),
            ':phone' => field('phone', 30),
            ':notes' => field('notes', 2000)
        ]);

        respond([
            'ok' => true,
            'message' => 'Paciente cadastrado com sucesso.',
            'id' => (int)$db->lastInsertId()
        ]);
    }

    // =========================================
    // EXCLUIR PACIENTE
    // =========================================
    if ($action === 'delete_patient') {
        $id = isset($_POST['id'])
            ? filter_var($_POST['id'], FILTER_VALIDATE_INT)
            : false;

        if ($id === false || $id === null || $id < 1) {
            respond([
                'ok' => false,
                'error' => 'Identificador de paciente inválido.'
            ], 422);
        }

        // Confirma que o paciente existe.
        $check = $db->prepare(
            'SELECT id FROM patients WHERE id = ?'
        );
        $check->execute([$id]);

        if (!$check->fetch(PDO::FETCH_ASSOC)) {
            respond([
                'ok' => false,
                'error' => 'Paciente não encontrado.'
            ], 404);
        }

        // Protege o histórico de anamneses.
        $checkAnamnesis = $db->prepare(
            'SELECT COUNT(*) FROM anamneses WHERE patient_id = ?'
        );
        $checkAnamnesis->execute([$id]);

        if ((int)$checkAnamnesis->fetchColumn() > 0) {
            respond([
                'ok' => false,
                'error' => 'Este paciente possui anamneses vinculadas. A exclusão foi bloqueada para preservar o histórico.'
            ], 409);
        }

        // Exclui somente o paciente escolhido.
        $delete = $db->prepare(
            'DELETE FROM patients WHERE id = ?'
        );
        $delete->execute([$id]);

        respond([
            'ok' => true,
            'message' => 'Paciente excluído com sucesso.'
        ]);
    }

    // =========================================
    // CADASTRAR ANAMNESE
    // =========================================
    if ($action === 'create_anamnesis') {
        $patientId = isset($_POST['patient_id'])
            ? filter_var($_POST['patient_id'], FILTER_VALIDATE_INT)
            : false;

        $complaint = field('complaint', 4000);
        $history = field('history', 5000);

        if (
            $patientId === false ||
            $patientId === null ||
            $patientId < 1 ||
            $complaint === '' ||
            $history === ''
        ) {
            respond([
                'ok' => false,
                'error' => 'Selecione um paciente e preencha a queixa principal e a história da condição atual.'
            ], 422);
        }

        // Verifica se o paciente selecionado existe.
        $check = $db->prepare(
            'SELECT id FROM patients WHERE id = ?'
        );
        $check->execute([$patientId]);

        if (!$check->fetch(PDO::FETCH_ASSOC)) {
            respond([
                'ok' => false,
                'error' => 'Paciente não encontrado.'
            ], 422);
        }

        $stmt = $db->prepare("
            INSERT INTO anamneses (
                patient_id,
                complaint,
                history,
                antecedents,
                habits,
                previous_treatments,
                functional_assessment
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $patientId,
            $complaint,
            $history,
            field('antecedents', 4000),
            field('habits', 3000),
            field('previous_treatments', 3000),
            field('functional_assessment', 4000)
        ]);

        respond([
            'ok' => true,
            'message' => 'Anamnese salva com sucesso.',
            'id' => (int)$db->lastInsertId()
        ]);
    }

    // Ação desconhecida.
    respond([
        'ok' => false,
        'error' => 'Ação não reconhecida.'
    ], 400);

} catch (Throwable $e) {
    error_log('MedFlow API: ' . $e->getMessage());

    respond([
        'ok' => false,
        'error' => 'Erro interno ao acessar o banco de dados. Confira o log de erros do PHP e a configuração do SQLite.'
    ], 500);
}
