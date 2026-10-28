
const API = 'api.php';

let patients = [];
let anamneses = [];

const $ = (selector) => document.querySelector(selector);

const esc = (value = '') =>
  String(value).replace(/[&<>"']/g, ch => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;'
  }[ch]));

function notify(message, isError = false) {
  const box = $('#notice');
  if (!box) return;

  box.textContent = message;
  box.className = 'notice ' + (isError ? 'error' : 'success');
  box.hidden = false;

  window.scrollTo({ top: 0, behavior: 'smooth' });

  setTimeout(() => {
    box.hidden = true;
  }, 5000);
}

async function request(action, options = {}) {
  const response = await fetch(
    `${API}?action=${encodeURIComponent(action)}`,
    options
  );

  const text = await response.text();

  let data;

  try {
    data = JSON.parse(text);
  } catch (error) {
    console.error('Resposta recebida do servidor:', text);
    throw new Error(
      'Resposta inválida do servidor. Confira o PHP e o arquivo api.php.'
    );
  }

  if (!response.ok || !data.ok) {
    throw new Error(
      data.error || 'Não foi possível concluir a operação.'
    );
  }

  return data;
}

// ========================================
// NAVEGAÇÃO
// ========================================

function showPage(page) {
  document.querySelectorAll('.page').forEach(el => {
    el.classList.toggle('active', el.id === `page-${page}`);
  });

  document.querySelectorAll('.nav-link').forEach(el => {
    el.classList.toggle('active', el.dataset.page === page);
  });

  const titles = {
    dashboard: 'Painel inicial',
    patients: 'Pacientes',
    'new-anamnesis': 'Nova anamnese',
    history: 'Histórico'
  };

  const title = $('#page-title');
  if (title) title.textContent = titles[page] || 'MedFlow';

  if (page === 'patients') renderPatients();
  if (page === 'new-anamnesis') fillPatientSelect();
  if (page === 'history') renderHistory();
}

document.querySelectorAll('[data-page]').forEach(button => {
  button.addEventListener('click', () => {
    showPage(button.dataset.page);
  });
});

document.querySelectorAll('[data-go]').forEach(button => {
  button.addEventListener('click', () => {
    showPage(button.dataset.go);
  });
});

// ========================================
// FORMATAÇÃO DE DATAS
// ========================================

function fmtDate(date) {
  if (!date) return '—';

  const value = String(date);

  // Datas no formato YYYY-MM-DD.
  const match = value.match(/^(\d{4})-(\d{2})-(\d{2})/);

  if (match) {
    return `${match[3]}/${match[2]}/${match[1]}`;
  }

  return esc(value);
}

// ========================================
// PAINEL INICIAL
// ========================================

function renderDashboard() {
  $('#stat-patients').textContent = patients.length;
  $('#stat-anamneses').textContent = anamneses.length;
  $('#stat-last').textContent = patients[0]?.name || '—';

  const recent = patients.slice(0, 5);

  $('#recent-patients').innerHTML = recent.length
    ? `
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Nome</th>
              <th>Telefone</th>
              <th>Cadastro</th>
            </tr>
          </thead>
          <tbody>
            ${recent.map(p => `
              <tr>
                <td>${esc(p.name)}</td>
                <td>${esc(p.phone || '—')}</td>
                <td>${esc(p.created_at || '—')}</td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      </div>
    `
    : '<div class="empty-state">Nenhum paciente cadastrado ainda.</div>';
}

// ========================================
// LISTAR E EXCLUIR PACIENTES
// ========================================

function renderPatients() {
  const search = $('#patient-search');
  const table = $('#patients-table');

  if (!table) return;

  const term = (search?.value || '').toLowerCase();

  const rows = patients.filter(p =>
    String(p.name || '').toLowerCase().includes(term)
  );

  table.innerHTML = rows.length
    ? rows.map(p => `
      <tr>
        <td><strong>${esc(p.name)}</strong></td>
        <td>${fmtDate(p.birth_date)}</td>
        <td>${esc(p.phone || '—')}</td>
        <td>${esc(p.created_at || '—')}</td>
        <td>
          <button
            type="button"
            class="btn small btn-delete"
            data-delete-patient="${Number(p.id)}"
          >Excluir</button>
        </td>
      </tr>
    `).join('')
    : '<tr><td colspan="5">Nenhum paciente encontrado.</td></tr>';

  // Liga os botões depois de criar as linhas da tabela.
  table.querySelectorAll('[data-delete-patient]').forEach(button => {
    button.addEventListener('click', async () => {
      const id = Number(button.dataset.deletePatient);

      const patient = patients.find(
        p => Number(p.id) === id
      );

      if (!patient) {
        notify('Paciente não encontrado na lista.', true);
        return;
      }

      const confirmed = confirm(
        `Tem certeza que deseja excluir o paciente "${patient.name}"?\n\n` +
        'Esta ação não poderá ser desfeita.'
      );

      if (!confirmed) return;

      button.disabled = true;
      button.textContent = 'Excluindo...';

      try {
        const body = new FormData();
        body.append('id', String(id));

        const result = await request('delete_patient', {
          method: 'POST',
          body
        });

        await loadData();

        notify(result.message || 'Paciente excluído com sucesso.');

      } catch (error) {
        notify(error.message, true);
        renderPatients();
      }
    });
  });
}

// ========================================
// SELEÇÃO DE PACIENTES NA ANAMNESE
// ========================================

function fillPatientSelect() {
  const select = $('#anam-patient');
  if (!select) return;

  const previous = select.value;

  select.innerHTML =
    '<option value="">Selecione um paciente</option>' +
    patients.map(p => `
      <option value="${Number(p.id)}">${esc(p.name)}</option>
    `).join('');

  if (patients.some(p => String(p.id) === previous)) {
    select.value = previous;
  }
}

// ========================================
// HISTÓRICO DE ANAMNESES
// ========================================

function renderHistory() {
  const container = $('#history-list');
  if (!container) return;

  const term = ($('#history-search')?.value || '').toLowerCase();

  const rows = anamneses.filter(a =>
    `${a.patient_name || ''} ${a.complaint || ''}`
      .toLowerCase()
      .includes(term)
  );

  container.innerHTML = rows.length
    ? rows.map(a => `
      <article class="history-card">
        <div class="history-top">
          <div>
            <span class="record-id">FICHA #${Number(a.id)}</span>
            <h3>${esc(a.patient_name)}</h3>
            <small>${esc(a.created_at || '')}</small>
          </div>
          <button
            class="btn secondary small"
            type="button"
            data-print="${Number(a.id)}"
          >Imprimir ficha</button>
        </div>

        <div class="history-field">
          <strong>Queixa principal</strong>
          <p>${esc(a.complaint)}</p>
        </div>

        <div class="history-field">
          <strong>História da condição atual</strong>
          <p>${esc(a.history)}</p>
        </div>

        <details>
          <summary>Ver demais informações</summary>
          <div class="detail-grid">
            ${[
              ['Antecedentes', a.antecedents],
              ['Hábitos', a.habits],
              ['Tratamentos anteriores', a.previous_treatments],
              ['Avaliação funcional', a.functional_assessment]
            ].map(([label, value]) => `
              <div>
                <strong>${label}</strong>
                <p>${esc(value || 'Não informado')}</p>
              </div>
            `).join('')}
          </div>
        </details>
      </article>
    `).join('')
    : '<div class="empty-state">Nenhuma anamnese encontrada. Cadastre um paciente e crie uma ficha.</div>';

  container.querySelectorAll('[data-print]').forEach(button => {
    button.addEventListener('click', () => {
      printAnamnesis(Number(button.dataset.print));
    });
  });
}

// ========================================
// IMPRESSÃO DE UMA ANAMNESE
// ========================================

function printAnamnesis(id) {
  const a = anamneses.find(x => Number(x.id) === id);
  if (!a) return;

  const fields = [
    ['Queixa principal', a.complaint],
    ['História da condição atual', a.history],
    ['Antecedentes pessoais', a.antecedents],
    ['Hábitos de vida', a.habits],
    ['Tratamentos anteriores', a.previous_treatments],
    ['Avaliação funcional', a.functional_assessment]
  ];

  const w = window.open('', '_blank');

  if (!w) {
    notify('Permita pop-ups para imprimir a ficha.', true);
    return;
  }

  w.document.write(`
    <!doctype html>
    <html lang="pt-BR">
    <head>
      <meta charset="utf-8">
      <title>MedFlow - Ficha ${Number(id)}</title>
      <style>
        body {
          font: 15px Arial, sans-serif;
          color: #222;
          max-width: 800px;
          margin: 35px auto;
          line-height: 1.5;
        }
        h1 { color: #1767b1; }
        h2 { font-size: 16px; margin-bottom: 4px; }
        section {
          border-bottom: 1px solid #ddd;
          padding: 10px 0;
          white-space: pre-wrap;
        }
        .muted { color: #666; }
        @media print { button { display: none; } }
      </style>
    </head>
    <body>
      <h1>MedFlow</h1>
      <h2>Ficha de anamnese fisioterapêutica #${Number(id)}</h2>
      <p>
        <b>Paciente:</b> ${esc(a.patient_name)}<br>
        <span class="muted">Data do registro: ${esc(a.created_at || '')}</span>
      </p>
      ${fields.map(([label, value]) => `
        <section>
          <h2>${label}</h2>
          ${esc(value || 'Não informado')}
        </section>
      `).join('')}
      <p class="muted">Protótipo acadêmico — utilize apenas dados fictícios.</p>
      <button onclick="window.print()">Imprimir</button>
    </body>
    </html>
  `);

  w.document.close();
  w.focus();

  setTimeout(() => w.print(), 300);
}

// ========================================
// FORMULÁRIO DE PACIENTE
// ========================================

$('#patient-form').addEventListener('submit', async event => {
  event.preventDefault();

  const form = event.currentTarget;

  try {
    const result = await request('create_patient', {
      method: 'POST',
      body: new FormData(form)
    });

    form.reset();

    await loadData();
    showPage('patients');

    notify(result.message);

  } catch (error) {
    notify(error.message, true);
  }
});

// ========================================
// FORMULÁRIO DE ANAMNESE
// ========================================

$('#anamnesis-form').addEventListener('submit', async event => {
  event.preventDefault();

  const form = event.currentTarget;

  try {
    const result = await request('create_anamnesis', {
      method: 'POST',
      body: new FormData(form)
    });

    form.reset();

    await loadData();
    showPage('history');

    notify(result.message);

  } catch (error) {
    notify(error.message, true);
  }
});

// ========================================
// PESQUISA E IMPRESSÃO
// ========================================

$('#patient-search').addEventListener('input', renderPatients);
$('#history-search').addEventListener('input', renderHistory);

$('#print-history').addEventListener('click', () => {
  window.print();
});

// ========================================
// CARREGAR DADOS DO PHP
// ========================================

async function loadData() {
  const [patientResult, anamnesisResult] = await Promise.all([
    request('patients'),
    request('anamneses')
  ]);

  patients = patientResult.data;
  anamneses = anamnesisResult.data;

  renderDashboard();
  renderPatients();
  fillPatientSelect();
  renderHistory();
}

loadData().catch(error => {
  notify(
    `${error.message} Verifique se o Apache está iniciado e se a API PHP está funcionando.`,
    true
  );
});
