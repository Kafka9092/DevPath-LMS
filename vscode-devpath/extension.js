const vscode = require('vscode');
const https = require('https');
const http = require('http');

const CRITERIA_LABELS = {
  correctness: 'Корректность',
  readability: 'Читаемость',
  code_structure: 'Структура',
  best_practices: 'Best practices',
  maintainability: 'Поддерживаемость',
};

const ISSUE_TYPE_LABELS = {
  VULNERABILITY: 'Уязвимость',
  BUG: 'Ошибка',
  CODE_SMELL: 'Плохая практика',
  SECURITY_HOTSPOT: 'Точка риска',
};

const SEVERITY_LABELS = {
  BLOCKER: 'Блокер',
  CRITICAL: 'Критично',
  MAJOR: 'Важно',
  MINOR: 'Незначительно',
  INFO: 'Инфо',
};

const VSCODE_LANG_MAP = {
  php: 'php',
  javascript: 'javascript',
  typescript: 'typescript',
  python: 'python',
  java: 'java',
  csharp: 'csharp',
  go: 'go',
  ruby: 'ruby',
  rust: 'rust',
  kotlin: 'kotlin',
  swift: 'swift',
  cpp: 'cpp',
  c: 'c',
};

const FILE_EXT_LANG_MAP = {
  php: 'php',
  js: 'javascript',
  jsx: 'javascript',
  mjs: 'javascript',
  cjs: 'javascript',
  ts: 'typescript',
  tsx: 'typescript',
  py: 'python',
  java: 'java',
  cs: 'csharp',
  go: 'go',
  rb: 'ruby',
  rs: 'rust',
  kt: 'kotlin',
  kts: 'kotlin',
  swift: 'swift',
  cpp: 'cpp',
  cc: 'cpp',
  cxx: 'cpp',
  hpp: 'cpp',
  c: 'c',
  h: 'c',
};

let reviewViewProvider;

/** @param {vscode.ExtensionContext} context */
function activate(context) {
  reviewViewProvider = new ReviewViewProvider(context.extensionUri);

  const statusBtn = vscode.window.createStatusBarItem(vscode.StatusBarAlignment.Right, 100);
  statusBtn.command = 'devpath.openPanel';
  statusBtn.text = 'DevPath';
  statusBtn.tooltip = 'Открыть панель DevPath (анализ: Ctrl+Alt+D)';
  statusBtn.show();

  context.subscriptions.push(
    vscode.window.registerWebviewViewProvider(ReviewViewProvider.viewType, reviewViewProvider),
    vscode.commands.registerCommand('devpath.openPanel', () => openDevPathPanel()),
    vscode.commands.registerCommand('devpath.analyzeFile', () => analyzeActiveFile()),
    statusBtn,
    diagnosticCollection,
  );

  void vscode.window.showInformationMessage(
    'DevPath активен. Ctrl+Alt+D — проверить файл. Кнопка DevPath внизу — открыть панель слева.',
    'Понятно',
  );
}

function deactivate() {
  diagnosticCollection.clear();
}

const diagnosticCollection = vscode.languages.createDiagnosticCollection('devpath');

class ReviewViewProvider {
  static viewType = 'devpath.reviewView';

  /** @param {vscode.Uri} extensionUri */
  constructor(extensionUri) {
    this.extensionUri = extensionUri;
    /** @type {vscode.WebviewView | undefined} */
    this.view = undefined;
  }

  /** @param {vscode.WebviewView} webviewView */
  resolveWebviewView(webviewView) {
    this.view = webviewView;
    webviewView.webview.options = { enableScripts: true };
    webviewView.webview.html = this.getIdleHtml();

    webviewView.webview.onDidReceiveMessage((message) => {
      if (message.type === 'openLine' && typeof message.line === 'number') {
        openLineInEditor(message.line);
      }
    });
  }

  showLoading(fileName) {
    if (!this.view) {
      return;
    }
    this.view.webview.html = this.wrapHtml(`
      <div class="center">
        <p class="muted">Анализируем <strong>${escapeHtml(fileName)}</strong>…</p>
        <p class="muted small">SonarQube + AI — обычно 1–3 минуты, не закрывайте панель</p>
      </div>
    `);
  }

  showError(message) {
    if (!this.view) {
      return;
    }
    this.view.webview.html = this.wrapHtml(`
      <div class="error">
        <h3>Ошибка</h3>
        <p>${escapeHtml(message)}</p>
      </div>
    `);
  }

  /** @param {any} review */
  showReview(review) {
    if (!this.view) {
      return;
    }
    this.view.webview.html = this.wrapHtml(renderReviewHtml(review));
  }

  getIdleHtml() {
    return this.wrapHtml(`
      <div class="center">
        <p>Откройте файл и нажмите <strong>DevPath: Analyze Current File</strong></p>
        <p class="muted small">Или нажмите <strong>Ctrl+Alt+D</strong></p>
      </div>
    `);
  }

  wrapHtml(body) {
    return `<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8" />
  <meta http-equiv="Content-Security-Policy" content="default-src 'none'; style-src 'unsafe-inline'; script-src 'unsafe-inline';" />
  <style>
    :root {
      --violet: #7c3aed;
      --violet-soft: rgba(124, 58, 237, 0.15);
      --border: var(--vscode-panel-border);
      --text: var(--vscode-foreground);
      --muted: var(--vscode-descriptionForeground);
      --card: var(--vscode-sideBar-background, var(--vscode-editor-background));
    }
    * { box-sizing: border-box; }
    body {
      font-family: var(--vscode-font-family);
      font-size: 13px;
      color: var(--text);
      margin: 0;
      padding: 16px;
      line-height: 1.5;
    }
    h3, h4 { margin: 0 0 8px; font-weight: 600; }
    .center { text-align: center; padding: 24px 8px; }
    .muted { color: var(--muted); }
    .small { font-size: 11px; }
    .error { border: 1px solid #ef4444; border-radius: 8px; padding: 12px; background: rgba(239,68,68,0.08); }
    .score-row { display: flex; gap: 16px; align-items: flex-start; margin-bottom: 16px; }
    .score-ring-wrap {
      flex-shrink: 0;
    }
    .score-ring-conic {
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .score-ring-hole {
      border-radius: 50%;
      background: var(--card);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
    }
    .score-num { font-size: 22px; font-weight: 700; line-height: 1; }
    .grade { color: var(--violet); font-weight: 600; font-size: 13px; }
    .lang { font-size: 12px; color: var(--muted); margin-top: 4px; }
    .section { margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--border); }
    .section-title {
      font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em;
      color: var(--muted); margin-bottom: 10px;
    }
    .bar-row { margin-bottom: 10px; }
    .bar-label { display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 4px; }
    .bar-track { height: 4px; background: var(--violet-soft); border-radius: 4px; overflow: hidden; }
    .bar-fill { height: 100%; background: var(--violet); border-radius: 4px; }
    ul.plain { margin: 0; padding-left: 16px; }
    ul.plain li { margin-bottom: 6px; color: var(--muted); }
    .rec {
      border: 1px solid var(--border); border-radius: 8px;
      padding: 10px 12px; font-size: 12px; color: var(--muted);
    }
    .issue {
      display: block; width: 100%; text-align: left;
      border: 1px solid var(--border); border-left: 3px solid #f97316;
      border-radius: 8px; background: var(--card);
      padding: 10px 12px; margin-bottom: 8px; cursor: pointer;
      color: inherit; font: inherit;
    }
    .issue:hover { border-color: var(--violet); }
    .issue.major { border-left-color: #f97316; }
    .issue.critical { border-left-color: #ef4444; }
    .issue.minor { border-left-color: #fbbf24; }
    .issue-meta { font-size: 10px; color: var(--muted); margin-bottom: 4px; }
    .issue-title { font-weight: 600; font-size: 12px; }
    .issue-fix { font-size: 11px; color: var(--muted); margin-top: 6px; }
    .summary { color: var(--muted); font-size: 13px; margin-top: 12px; }
  </style>
</head>
<body>${body}
<script>
  const vscode = acquireVsCodeApi();
  document.querySelectorAll('[data-line]').forEach((el) => {
    el.addEventListener('click', () => {
      const line = parseInt(el.getAttribute('data-line'), 10);
      if (line > 0) {
        vscode.postMessage({ type: 'openLine', line });
      }
    });
  });
</script>
</body>
</html>`;
  }
}

/** @param {any} data */
function renderReviewHtml(data) {
  const ai = data.ai_evaluation;

  if (data.is_code === false) {
    return `<div class="error"><h3>Не код</h3><p>${escapeHtml(data.not_code_message || 'Фрагмент не распознан как исходный код.')}</p><p class="muted small">${escapeHtml(data.not_code_hint || 'Выделите функцию или класс целиком.')}</p></div>`;
  }

  if (data.is_code !== true && data.overall_score == null && !ai) {
    return '<div class="error"><h3>Нет результата</h3><p class="muted">Сервер вернул пустой ответ. Проверьте Docker и воркер: <code>docker compose up -d hr-kafka-code-review-consumer</code></p></div>';
  }

  if (!ai || Object.keys(ai).length === 0) {
    const sonarErr = data.sonar_error || ai?.sonar_error;
    if (sonarErr) {
      return `<div class="error"><h3>SonarQube</h3><p>${escapeHtml(sonarErr)}</p></div>`;
    }
    return '<p class="muted">Нет данных AI-разбора. Проверьте Ollama и логи воркера.</p>';
  }

  const score = ai.overall_score ?? data.overall_score ?? 0;
  const criteria = ai.criteria || {};
  const issues = sortIssues(ai.explained_issues || []);
  const grade = ai.grade_label || gradeLabelForScore(score);

  let html = `
    <div class="score-row">
      ${renderScoreRing(score)}
      <div>
        <div class="section-title">Итоговая оценка</div>
        <div class="grade">${escapeHtml(grade)}</div>
        <div class="lang">${escapeHtml(data.detected_language || '')}</div>
      </div>
    </div>
    <p class="summary">${escapeHtml(ai.summary || '')}</p>
  `;

  const criteriaKeys = Object.keys(criteria);
  if (criteriaKeys.length) {
    html += '<div class="section"><div class="section-title">Детальная оценка</div>';
    for (const key of criteriaKeys) {
      const val = Number(criteria[key]) || 0;
      html += `
        <div class="bar-row">
          <div class="bar-label"><span>${escapeHtml(CRITERIA_LABELS[key] || key)}</span><span>${val}</span></div>
          <div class="bar-track"><div class="bar-fill" style="width:${Math.min(100, val)}%"></div></div>
        </div>`;
    }
    html += '</div>';
  }

  if (ai.strengths?.length) {
    html += `<div class="section"><h4>Сильные стороны</h4><ul class="plain">${ai.strengths.map((s) => `<li>${escapeHtml(s)}</li>`).join('')}</ul></div>`;
  }

  if (ai.improvements?.length) {
    html += `<div class="section"><h4>Что улучшить</h4><ul class="plain">${ai.improvements.map((s) => `<li>${escapeHtml(s)}</li>`).join('')}</ul></div>`;
  }

  if (ai.recommendation) {
    html += `<div class="section"><div class="section-title">Рекомендация</div><div class="rec">${escapeHtml(ai.recommendation)}</div></div>`;
  }

  html += `<div class="section"><div class="section-title">Замечания (${issues.length})</div>`;
  if (!issues.length) {
    html += '<p class="muted">Критичных замечаний не найдено.</p>';
  } else {
    for (const issue of issues) {
      const sev = (issue.severity || 'MAJOR').toUpperCase();
      const cls = sev === 'CRITICAL' || sev === 'BLOCKER' ? 'critical' : sev === 'MINOR' || sev === 'INFO' ? 'minor' : 'major';
      const line = issue.line ? Number(issue.line) : 0;
      html += `
        <button class="issue ${cls}" data-line="${line}" type="button">
          <div class="issue-meta">${line ? `Строка ${line} · ` : ''}${escapeHtml(ISSUE_TYPE_LABELS[issue.type] || issue.type)} · ${escapeHtml(SEVERITY_LABELS[sev] || sev)}</div>
          <div class="issue-title">${escapeHtml(issue.explanation || issue.sonar_message || '')}</div>
          ${issue.how_to_fix ? `<div class="issue-fix">${escapeHtml(issue.how_to_fix)}</div>` : ''}
        </button>`;
    }
  }
  html += '</div>';

  return html;
}

/** @param {any[]} issues */
function sortIssues(issues) {
  return [...issues].sort((a, b) => (Number(a.line) || 9999) - (Number(b.line) || 9999));
}

function escapeHtml(text) {
  return String(text)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

/** @param {number} score */
function renderScoreRing(score, size = 88) {
  const clamped = Math.min(100, Math.max(0, Number(score) || 0));
  const ring = 6;
  const inner = size - ring * 2;

  return `
    <div class="score-ring-wrap">
      <div class="score-ring-conic" style="width:${size}px;height:${size}px;background:conic-gradient(from -90deg,#7c3aed 0%,#7c3aed ${clamped}%,rgba(124,58,237,0.18) ${clamped}%,rgba(124,58,237,0.18) 100%);">
        <div class="score-ring-hole" style="width:${inner}px;height:${inner}px;">
          <span class="score-num">${Math.round(clamped)}</span>
          <span class="muted small">из 100</span>
        </div>
      </div>
    </div>`;
}

/** @param {number} score */
function gradeLabelForScore(score) {
  const n = Number(score) || 0;
  if (n >= 90) return 'Отлично';
  if (n >= 80) return 'Хорошо';
  if (n >= 60) return 'Удовлетворительно';
  return 'Требует доработки';
}

async function openDevPathPanel() {
  await ensureReviewPanel();
}

async function ensureReviewPanel() {
  try {
    await vscode.commands.executeCommand('workbench.view.extension.devpath-sidebar');
  } catch {
    // ignore
  }
  for (let i = 0; i < 15 && !reviewViewProvider?.view; i += 1) {
    await sleep(100);
  }
}

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

async function analyzeActiveFile() {
  const editor = vscode.window.activeTextEditor;
  if (!editor) {
    vscode.window.showWarningMessage('Нет открытого файла.');
    return;
  }

  const doc = editor.document;
  const hasSelection = !editor.selection.isEmpty;
  const selectedText = hasSelection ? doc.getText(editor.selection).trim() : '';
  let code = selectedText || doc.getText();
  const analyzingSelection = Boolean(selectedText);

  if (!code.trim()) {
    vscode.window.showWarningMessage('Файл пустой.');
    return;
  }

  const lineCount = code.split(/\r?\n/).length;
  if (!analyzingSelection && lineCount > 350) {
    const pick = await vscode.window.showWarningMessage(
      `Файл большой (${lineCount} строк) — анализ может занять несколько минут или дать ошибку 504. Выделите фрагмент кода (функцию) и нажмите снова.`,
      { modal: true },
      'Всё равно проверить',
      'Отмена',
    );
    if (pick !== 'Всё равно проверить') {
      return;
    }
  }

  const config = vscode.workspace.getConfiguration('devpath');
  const apiUrl = (config.get('apiUrl') || 'https://localhost:8089').replace(/\/$/, '');
  const allowInsecure = config.get('allowInsecureTls') !== false;

  const fileName = doc.fileName.split(/[/\\]/).pop() || 'file';
  const language = mapLanguage(doc.languageId, fileName);

  await ensureReviewPanel();

  reviewViewProvider?.showLoading(
    analyzingSelection ? `${fileName} (выделение)` : fileName,
  );

  try {
    const response = await postJson(
      `${apiUrl}/api/v1/analyze/preview`,
      {
        code,
        language,
        filename: fileName,
        file_path: doc.fileName,
      },
      allowInsecure,
    );

    let review;

    if (response.status === 202) {
      const jobId = response.body?.data?.id;
      if (!jobId) {
        throw new Error('Сервер не вернул job_id для асинхронного анализа');
      }
      reviewViewProvider?.showLoading(`${fileName} — в очереди…`);
      review = await pollJob(apiUrl, jobId, allowInsecure);
    } else if (response.ok) {
      review = response.body?.data;
    } else {
      throw new Error(formatApiError(response.status, response.rawBody, response.body));
    }

    if (!review) {
      throw new Error('Пустой ответ сервера');
    }

    if (!isMeaningfulReview(review)) {
      throw new Error('Сервер вернул неполный результат. Проверьте воркер: docker compose up -d hr-kafka-code-review-consumer');
    }

    reviewViewProvider?.showReview(review);
    applyDiagnostics(editor, review);
    vscode.window.showInformationMessage(formatReviewToast(review));
  } catch (e) {
    const message = e instanceof Error ? e.message : String(e);
    reviewViewProvider?.showError(message);
    vscode.window.showErrorMessage(`DevPath: ${message}`);
  }
}

function formatApiError(status, rawBody, body) {
  if (status === 504) {
    return 'Сервер не успел ответить (504). Убедитесь, что Docker запущен и выполните: docker compose restart nginx php. Должен работать воркер kafka:code-review-consume.';
  }
  if (body?.message && typeof body.message === 'string' && !body.message.includes('<html')) {
    return body.message;
  }
  if (body?.error && typeof body.error === 'string') {
    return body.error;
  }
  if (typeof rawBody === 'string' && rawBody.includes('504')) {
    return 'Таймаут сервера (504). Проверьте меньший фрагмент кода.';
  }
  return `HTTP ${status}`;
}

/** @param {vscode.TextEditor} editor @param {any} review */
function applyDiagnostics(editor, review) {
  const issues = review.ai_evaluation?.explained_issues || review.issues || [];
  const uri = editor.document.uri;
  /** @type {vscode.Diagnostic[]} */
  const diagnostics = [];

  for (const issue of issues) {
    const line = Number(issue.line);
    if (!line || line < 1) {
      continue;
    }
    const lineIndex = line - 1;
    const range = new vscode.Range(lineIndex, 0, lineIndex, Number.MAX_SAFE_INTEGER);
    const sev = String(issue.severity || 'MAJOR').toUpperCase();
    let severity = vscode.DiagnosticSeverity.Warning;
    if (sev === 'BLOCKER' || sev === 'CRITICAL') {
      severity = vscode.DiagnosticSeverity.Error;
    } else if (sev === 'INFO' || sev === 'MINOR') {
      severity = vscode.DiagnosticSeverity.Information;
    }

    const diag = new vscode.Diagnostic(
      range,
      issue.explanation || issue.sonar_message || issue.message || 'Замечание DevPath',
      severity,
    );
    diag.source = 'DevPath';
    diagnostics.push(diag);
  }

  diagnosticCollection.set(uri, diagnostics);
}

function openLineInEditor(line) {
  const editor = vscode.window.activeTextEditor;
  if (!editor) {
    return;
  }
  const pos = new vscode.Position(Math.max(0, line - 1), 0);
  editor.selection = new vscode.Selection(pos, pos);
  editor.revealRange(new vscode.Range(pos, pos), vscode.TextDecorationRangeBehavior.OpenIfNearby);
}

function mapLanguage(languageId, fileName) {
  const fromId = VSCODE_LANG_MAP[languageId];
  if (fromId) {
    return fromId;
  }
  if (languageId && languageId !== 'plaintext' && languageId !== 'txt') {
    return languageId;
  }
  const ext = (fileName || '').split('.').pop()?.toLowerCase();
  return (ext && FILE_EXT_LANG_MAP[ext]) || null;
}

/** @param {any} review */
function isMeaningfulReview(review) {
  if (!review || typeof review !== 'object') {
    return false;
  }
  if (review.is_code === false) {
    return true;
  }
  if (review.is_code === true) {
    return true;
  }
  if (review.overall_score != null || review.ai_evaluation) {
    return true;
  }
  return Object.keys(review).filter((k) => k !== 'type').length > 0;
}

/** @param {any} review */
function formatReviewToast(review) {
  if (review.is_code === false) {
    return `DevPath: ${review.not_code_message || 'Фрагмент не распознан как код'}`;
  }

  const score = review.ai_evaluation?.overall_score ?? review.overall_score;
  if (score === null || score === undefined) {
    const err = review.ai_evaluation?.sonar_error || review.sonar_error;
    if (err) {
      return `DevPath: SonarQube — ${err}`;
    }
    return 'DevPath: анализ завершён без оценки. Смотрите панель слева.';
  }

  const grade = review.ai_evaluation?.grade_label;
  const rounded = Math.round(Number(score));
  return `DevPath: оценка ${rounded}/100${grade ? ` (${grade})` : ''}`;
}

async function pollJob(apiUrl, jobId, allowInsecure) {
  const deadline = Date.now() + 180_000;

  while (Date.now() < deadline) {
    const response = await getJson(`${apiUrl}/api/v1/jobs/${jobId}`, allowInsecure);

    if (response.status === 504) {
      throw new Error(formatApiError(504, response.rawBody, response.body));
    }

    const data = response.body?.data;
    const status = data?.status;

    if (status === 'pending') {
      await sleep(2000);
      continue;
    }

    if (!response.ok) {
      throw new Error(formatApiError(response.status, response.rawBody, response.body));
    }

    if (status === 'completed' && data?.review) {
      const review = data.review;
      if (!isMeaningfulReview(review)) {
        throw new Error('Воркер вернул пустой результат. Запустите: docker compose up -d hr-kafka-code-review-consumer');
      }
      return review;
    }

    throw new Error('Неожиданный ответ сервера при ожидании анализа');
  }

  throw new Error(
    'Анализ занял больше 3 минут. Проверьте Docker и воркер: docker compose up -d hr-kafka-code-review-consumer',
  );
}

function getJson(url, allowInsecure) {
  return requestJson('GET', url, null, allowInsecure);
}

function postJson(url, body, allowInsecure) {
  return requestJson('POST', url, body, allowInsecure);
}

function requestJson(method, url, body, allowInsecure) {
  return new Promise((resolve, reject) => {
    const parsed = new URL(url);
    const payload = body ? JSON.stringify(body) : null;
    const isHttps = parsed.protocol === 'https:';
    const lib = isHttps ? https : http;

    const headers = { Accept: 'application/json' };
    if (payload) {
      headers['Content-Type'] = 'application/json';
      headers['Content-Length'] = String(Buffer.byteLength(payload));
    }

    const options = {
      hostname: parsed.hostname,
      port: parsed.port || (isHttps ? 443 : 80),
      path: parsed.pathname + parsed.search,
      method,
      headers,
      ...(isHttps && allowInsecure ? { rejectUnauthorized: false } : {}),
    };

    const req = lib.request(options, (res) => {
      let raw = '';
      res.on('data', (chunk) => { raw += chunk; });
      res.on('end', () => {
        let parsedBody = {};
        try {
          parsedBody = raw ? JSON.parse(raw) : {};
        } catch {
          parsedBody = { message: raw };
        }
        resolve({
          ok: res.statusCode >= 200 && res.statusCode < 300,
          status: res.statusCode || 0,
          body: parsedBody,
          rawBody: raw,
        });
      });
    });

    req.on('error', reject);
    if (payload) {
      req.write(payload);
    }
    req.end();
  });
}

module.exports = { activate, deactivate };
