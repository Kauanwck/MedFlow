
<?php
// MedFlow - protótipo acadêmico.
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>MedFlow | Anamnese Fisioterapêutica</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="app">
  <aside class="sidebar">
    <div class="brand">
      <span class="brand-icon">M</span>
      <div>
        <strong>MedFlow</strong>
        <small>Gestão fisioterapêutica</small>
      </div>
    </div>

    <nav>
      <button class="nav-link active" data-page="dashboard">▦ <span>Painel inicial</span></button>
      <button class="nav-link" data-page="patients">♙ <span>Pacientes</span></button>
      <button class="nav-link" data-page="new-anamnesis">✚ <span>Nova anamnese</span></button>
      <button class="nav-link" data-page="history">▤ <span>Histórico</span></button>
    </nav>

    <div class="side-note">
      Protótipo acadêmico<br>
      Não utilizar com dados reais de saúde.
    </div>
  </aside>

  <main class="main">
    <header class="topbar">
      <div>
        <p class="eyebrow">SISTEMA DE ATENDIMENTO</p>
        <h1 id="page-title">Painel inicial</h1>
      </div>
      <span class="status"><i></i> PHP + SQLite</span>
    </header>

    <div id="notice" class="notice" hidden></div>

    <section class="page active" id="page-dashboard">
      <div class="welcome">
        <div>
          <p class="eyebrow light">BEM-VINDO AO MEDFLOW</p>
          <h2>Organize seus atendimentos.</h2>
          <p>Cadastre pacientes e mantenha as fichas de anamnese em um só lugar.</p>
        </div>
        <div class="welcome-mark">M</div>
      </div>

      <div class="stats">
        <article class="stat-card">
          <span>Pacientes cadastrados</span>
          <strong id="stat-patients">0</strong>
          <small>Registros no sistema</small>
        </article>
        <article class="stat-card">
          <span>Fichas de anamnese</span>
          <strong id="stat-anamneses">0</strong>
          <small>Fichas registradas</small>
        </article>
        <article class="stat-card">
          <span>Último cadastro</span>
          <strong id="stat-last">—</strong>
          <small>Paciente mais recente</small>
        </article>
      </div>

      <div class="section-heading">
        <div>
          <h2>Acesso rápido</h2>
          <p>Escolha uma ação para continuar.</p>
        </div>
      </div>

      <div class="quick-grid">
        <button class="quick-card" data-go="patients">
          <span class="quick-icon">♙</span>
          <strong>Cadastrar paciente</strong>
          <small>Adicionar dados básicos</small>
          <b>→</b>
        </button>
        <button class="quick-card" data-go="new-anamnesis">
          <span class="quick-icon">✚</span>
          <strong>Nova anamnese</strong>
          <small>Registrar informações clínicas fictícias</small>
          <b>→</b>
        </button>
        <button class="quick-card" data-go="history">
          <span class="quick-icon">▤</span>
          <strong>Ver histórico</strong>
          <small>Consultar fichas cadastradas</small>
          <b>→</b>
        </button>
      </div>

      <div class="panel">
        <div class="section-heading">
          <div>
            <h2>Cadastros recentes</h2>
            <p>Últimos pacientes adicionados.</p>
          </div>
          <button class="text-button" data-go="patients">Ver pacientes →</button>
        </div>
        <div id="recent-patients" class="empty-state">
          Nenhum paciente cadastrado ainda.
        </div>
      </div>
    </section>

    <section class="page" id="page-patients">
      <div class="section-heading">
        <div>
          <h2>Cadastrar paciente</h2>
          <p>Preencha os dados básicos abaixo.</p>
        </div>
      </div>

      <form id="patient-form" class="form-card">
        <div class="form-grid">
          <label class="span-2">
            Nome completo *
            <input name="name" required maxlength="150" placeholder="Ex.: Maria da Silva">
          </label>

          <label>
            Data de nascimento
            <input type="date" name="birth_date">
          </label>

          <label>
            Telefone
            <input name="phone" maxlength="30" placeholder="(00) 00000-0000">
          </label>

          <label class="span-2">
            Observações
            <textarea name="notes" rows="3" placeholder="Informações adicionais (opcional)"></textarea>
          </label>
        </div>

        <div class="form-actions">
          <button class="btn primary" type="submit">Salvar paciente</button>
          <button class="btn secondary" type="reset">Limpar</button>
        </div>
      </form>

      <div class="panel">
        <div class="section-heading">
          <div>
            <h2>Pacientes cadastrados</h2>
            <p>Pesquise pelo nome.</p>
          </div>
        </div>

        <input id="patient-search" class="search" placeholder="Buscar paciente...">

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Nome</th>
                <th>Nascimento</th>
                <th>Telefone</th>
                <th>Cadastro</th>
                <th>Ações</th>
              </tr>
            </thead>
            <tbody id="patients-table">
              <tr>
                <td colspan="5">Carregando...</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <section class="page" id="page-new-anamnesis">
      <div class="section-heading">
        <div>
          <h2>Nova ficha de anamnese</h2>
          <p>Os campos marcados com * são obrigatórios.</p>
        </div>
      </div>

      <form id="anamnesis-form" class="form-card">
        <div class="form-grid">
          <label class="span-2">
            Paciente *
            <select name="patient_id" id="anam-patient" required>
              <option value="">Selecione um paciente</option>
            </select>
          </label>

          <label class="span-2">
            Queixa principal *
            <textarea name="complaint" rows="3" required placeholder="Descreva a queixa principal (use dados fictícios)"></textarea>
          </label>

          <label class="span-2">
            História da condição atual *
            <textarea name="history" rows="4" required placeholder="Quando começou? Como evoluiu?"></textarea>
          </label>

          <label class="span-2">
            Antecedentes pessoais
            <textarea name="antecedents" rows="3" placeholder="Antecedentes relevantes (opcional)"></textarea>
          </label>

          <label>
            Hábitos de vida
            <textarea name="habits" rows="3" placeholder="Atividade física, rotina etc."></textarea>
          </label>

          <label>
            Tratamentos anteriores
            <textarea name="previous_treatments" rows="3" placeholder="Tratamentos anteriores"></textarea>
          </label>

          <label class="span-2">
            Avaliação funcional
            <textarea name="functional_assessment" rows="3" placeholder="Mobilidade, limitações e observações"></textarea>
          </label>
        </div>

        <div class="form-actions">
          <button class="btn primary" type="submit">Salvar anamnese</button>
          <button class="btn secondary" type="reset">Limpar</button>
        </div>
      </form>
    </section>

    <section class="page" id="page-history">
      <div class="section-heading">
        <div>
          <h2>Histórico de anamneses</h2>
          <p>Consulte ou imprima as fichas registradas.</p>
        </div>
        <button class="btn secondary" id="print-history">Imprimir lista</button>
      </div>

      <div class="panel">
        <input id="history-search" class="search" placeholder="Buscar por paciente ou queixa...">
        <div id="history-list" class="history-list">
          <div class="empty-state">Carregando fichas...</div>
        </div>
      </div>
    </section>

    <footer>
      MedFlow • Protótipo acadêmico para demonstração • PHP e SQLite
    </footer>
  </main>
</div>

<script src="script.js"></script>
</body>
</html>
