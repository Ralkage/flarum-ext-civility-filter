import app from 'flarum/admin/app';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';

export default class CivilityLogPage extends ExtensionPage {
  oninit(vnode) {
    super.oninit(vnode);

    this.logs = [];
    this.logLoading = false;
    this.currentPage = 1;
    this.totalPages = 1;
    this.filterAction = '';

    this.testMessage = '';
    this.testDiscussionTitle = '';
    this.testResult = null;
    this.testLoading = false;

    this.stats = null;
    this.statsLoading = false;

    this.activeTab = 'settings';
  }

  content(vnode) {
    return (
      <div className="CivilityLogPage">
        <div className="CivilityLogPage-tabs" style="margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 4px;">
          {['settings', 'log', 'stats', 'test'].map((tab) => (
            <Button
              className={`Button ${this.activeTab === tab ? 'Button--primary' : ''}`}
              onclick={() => {
                this.activeTab = tab;
                if (tab === 'log' && !this.logs.length) this.loadLogs();
                if (tab === 'stats' && !this.stats) this.loadStats();
              }}
            >
              { {
                settings: 'Settings',
                log: app.translator.trans('ralkage-civility-filter.admin.nav.civility_log'),
                stats: app.translator.trans('ralkage-civility-filter.admin.nav.stats'),
                test: app.translator.trans('ralkage-civility-filter.admin.test.title'),
              }[tab] }
            </Button>
          ))}
        </div>

        <div style={this.activeTab !== 'settings' ? 'display:none' : ''}>{this.settingsTab()}</div>
        {this.activeTab === 'log' && this.logTab()}
        {this.activeTab === 'stats' && this.statsTab()}
        {this.activeTab === 'test' && this.testTab()}
      </div>
    );
  }

  // ── Settings ──

  settingsTab() {
    return (
      <div className="Form">
        <h3>General</h3>
        {this.buildSettingComponent({ setting: 'ralkage-civility-filter.enabled', type: 'boolean', label: app.translator.trans('ralkage-civility-filter.admin.settings.enabled_label'), help: app.translator.trans('ralkage-civility-filter.admin.settings.enabled_help') })}

        <h3>AI Provider</h3>
        {this.buildSettingComponent({ setting: 'ralkage-civility-filter.ai_provider', type: 'select', label: app.translator.trans('ralkage-civility-filter.admin.settings.ai_provider_label'), help: app.translator.trans('ralkage-civility-filter.admin.settings.ai_provider_help'), options: { 'anthropic': 'Anthropic (Claude)', 'openai': 'OpenAI (GPT)', 'openrouter': 'OpenRouter' }, default: 'anthropic' })}
        {this.providerFields()}

        <h3>Thresholds</h3>
        {this.buildSettingComponent({ setting: 'ralkage-civility-filter.warn_threshold', type: 'number', label: app.translator.trans('ralkage-civility-filter.admin.settings.warn_threshold_label'), help: app.translator.trans('ralkage-civility-filter.admin.settings.warn_threshold_help'), min: 0, max: 100, step: 5, placeholder: '60' })}
        {this.buildSettingComponent({ setting: 'ralkage-civility-filter.hold_threshold', type: 'number', label: app.translator.trans('ralkage-civility-filter.admin.settings.hold_threshold_label'), help: app.translator.trans('ralkage-civility-filter.admin.settings.hold_threshold_help'), min: 0, max: 100, step: 5, placeholder: '80' })}
        {this.buildSettingComponent({ setting: 'ralkage-civility-filter.block_threshold', type: 'number', label: app.translator.trans('ralkage-civility-filter.admin.settings.block_threshold_label'), help: app.translator.trans('ralkage-civility-filter.admin.settings.block_threshold_help'), min: 0, max: 100, step: 5, placeholder: '95' })}

        <h3>Filtering</h3>
        {this.buildSettingComponent({ setting: 'ralkage-civility-filter.monitored_tags', type: 'flarum-tags.select-tags', label: app.translator.trans('ralkage-civility-filter.admin.settings.monitored_tags_label'), help: app.translator.trans('ralkage-civility-filter.admin.settings.monitored_tags_help'), options: { allowBypassing: true, requireParentTag: false } })}
        {this.buildSettingComponent({ setting: 'ralkage-civility-filter.word_blocklist', type: 'textarea', label: app.translator.trans('ralkage-civility-filter.admin.settings.word_blocklist_label'), help: app.translator.trans('ralkage-civility-filter.admin.settings.word_blocklist_help'), placeholder: 'badword1\nbadword2\nbadphrase three' })}

        <h3>Custom Prompt</h3>
        {this.buildSettingComponent({ setting: 'ralkage-civility-filter.custom_prompt', type: 'textarea', label: app.translator.trans('ralkage-civility-filter.admin.settings.custom_prompt_label'), help: app.translator.trans('ralkage-civility-filter.admin.settings.custom_prompt_help') })}

        <h3>Auto-Suspend</h3>
        {this.buildSettingComponent({ setting: 'ralkage-civility-filter.auto_suspend_threshold', type: 'number', label: app.translator.trans('ralkage-civility-filter.admin.settings.auto_suspend_threshold_label'), help: app.translator.trans('ralkage-civility-filter.admin.settings.auto_suspend_threshold_help'), min: 0, placeholder: '0' })}
        {this.buildSettingComponent({ setting: 'ralkage-civility-filter.auto_suspend_days', type: 'number', label: app.translator.trans('ralkage-civility-filter.admin.settings.auto_suspend_days_label'), help: app.translator.trans('ralkage-civility-filter.admin.settings.auto_suspend_days_help'), min: 1, placeholder: '3' })}
        {this.buildSettingComponent({ setting: 'ralkage-civility-filter.auto_suspend_window', type: 'number', label: app.translator.trans('ralkage-civility-filter.admin.settings.auto_suspend_window_label'), help: app.translator.trans('ralkage-civility-filter.admin.settings.auto_suspend_window_help'), min: 1, placeholder: '7' })}

        <h3>Webhooks</h3>
        {this.buildSettingComponent({ setting: 'ralkage-civility-filter.webhook_url', type: 'text', label: app.translator.trans('ralkage-civility-filter.admin.settings.webhook_url_label'), help: app.translator.trans('ralkage-civility-filter.admin.settings.webhook_url_help'), placeholder: 'https://discord.com/api/webhooks/...' })}
        {this.buildSettingComponent({ setting: 'ralkage-civility-filter.webhook_min_action', type: 'select', label: app.translator.trans('ralkage-civility-filter.admin.settings.webhook_min_action_label'), help: app.translator.trans('ralkage-civility-filter.admin.settings.webhook_min_action_help'), options: { 'warned': 'Warned', 'moderated': 'Moderated', 'blocked': 'Blocked' }, default: 'warned' })}

        <h3>Logging & Limits</h3>
        {this.buildSettingComponent({ setting: 'ralkage-civility-filter.log_all', type: 'boolean', label: app.translator.trans('ralkage-civility-filter.admin.settings.log_all_label'), help: app.translator.trans('ralkage-civility-filter.admin.settings.log_all_help') })}
        {this.buildSettingComponent({ setting: 'ralkage-civility-filter.rate_limit', type: 'number', label: app.translator.trans('ralkage-civility-filter.admin.settings.rate_limit_label'), help: app.translator.trans('ralkage-civility-filter.admin.settings.rate_limit_help'), min: 0, placeholder: '0' })}

        <div className="Form-group">{this.submitButton()}</div>
      </div>
    );
  }

  providerFields() {
    const provider = this.setting('ralkage-civility-filter.ai_provider')() || 'anthropic';

    const modelsByProvider = {
      anthropic: {
        'claude-haiku-4-5-20251001': 'Claude Haiku 4.5 (Fastest)',
        'claude-sonnet-4-6-20250514': 'Claude Sonnet 4.6 (Balanced)',
        'claude-opus-4-6-20250609': 'Claude Opus 4.6 (Most Capable)',
      },
      openai: {
        'gpt-4o-mini': 'GPT-4o Mini (Fastest)',
        'gpt-4o': 'GPT-4o (Balanced)',
        'gpt-4.1': 'GPT-4.1 (Most Capable)',
      },
      openrouter: {
        'anthropic/claude-haiku-4-5-20251001': 'Claude Haiku 4.5',
        'anthropic/claude-sonnet-4-6-20250514': 'Claude Sonnet 4.6',
        'openai/gpt-4o-mini': 'GPT-4o Mini',
        'openai/gpt-4o': 'GPT-4o',
        'google/gemini-2.5-flash-preview': 'Gemini 2.5 Flash',
        'meta-llama/llama-4-maverick': 'Llama 4 Maverick',
      },
    };

    const apiKeyFields = {
      anthropic: { setting: 'ralkage-civility-filter.api_key', label: app.translator.trans('ralkage-civility-filter.admin.settings.api_key_label'), help: app.translator.trans('ralkage-civility-filter.admin.settings.api_key_help') },
      openai: { setting: 'ralkage-civility-filter.openai_api_key', label: app.translator.trans('ralkage-civility-filter.admin.settings.openai_api_key_label'), help: app.translator.trans('ralkage-civility-filter.admin.settings.openai_api_key_help') },
      openrouter: { setting: 'ralkage-civility-filter.openrouter_api_key', label: app.translator.trans('ralkage-civility-filter.admin.settings.openrouter_api_key_label'), help: app.translator.trans('ralkage-civility-filter.admin.settings.openrouter_api_key_help') },
    };

    const keyField = apiKeyFields[provider];
    const models = modelsByProvider[provider] || {};

    return (
      <div>
        {this.buildSettingComponent({ setting: keyField.setting, type: 'text', label: keyField.label, help: keyField.help })}
        {this.buildSettingComponent({ setting: 'ralkage-civility-filter.model', type: 'select', label: app.translator.trans('ralkage-civility-filter.admin.settings.model_label'), help: app.translator.trans('ralkage-civility-filter.admin.settings.model_help'), options: models, default: Object.keys(models)[0] })}
      </div>
    );
  }

  // ── Log ──

  logTab() {
    return (
      <div className="CivilityLogPage-log">
        <div style="margin-bottom: 15px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
          <select className="FormControl" style="width: 200px;" value={this.filterAction} onchange={(e) => { this.filterAction = e.target.value; this.currentPage = 1; this.loadLogs(); }}>
            <option value="">{app.translator.trans('ralkage-civility-filter.admin.log.all_actions')}</option>
            <option value="allowed">{app.translator.trans('ralkage-civility-filter.admin.log.allowed')}</option>
            <option value="warned">{app.translator.trans('ralkage-civility-filter.admin.log.warned')}</option>
            <option value="moderated">{app.translator.trans('ralkage-civility-filter.admin.log.moderated')}</option>
            <option value="blocked">{app.translator.trans('ralkage-civility-filter.admin.log.blocked')}</option>
          </select>
          <Button className="Button" icon="fas fa-download" onclick={() => this.exportLogs()}>
            {app.translator.trans('ralkage-civility-filter.admin.log.export')}
          </Button>
          <Button className="Button Button--danger" icon="fas fa-trash" onclick={() => this.clearLogs()}>
            {app.translator.trans('ralkage-civility-filter.admin.log.clear')}
          </Button>
        </div>

        {this.logLoading ? <LoadingIndicator /> : this.logs.length === 0 ? (
          <p className="CivilityLogPage-empty">{app.translator.trans('ralkage-civility-filter.admin.log.no_logs')}</p>
        ) : (
          <div>
            <table className="CivilityLogPage-table">
              <thead>
                <tr style="border-bottom: 2px solid #e8ecf3;">
                  <th style="padding: 8px; text-align: left;">{app.translator.trans('ralkage-civility-filter.admin.log.date')}</th>
                  <th style="padding: 8px; text-align: left;">{app.translator.trans('ralkage-civility-filter.admin.log.username')}</th>
                  <th style="padding: 8px; text-align: center;">{app.translator.trans('ralkage-civility-filter.admin.log.score')}</th>
                  <th style="padding: 8px; text-align: center;">{app.translator.trans('ralkage-civility-filter.admin.log.action')}</th>
                  <th style="padding: 8px; text-align: left;">{app.translator.trans('ralkage-civility-filter.admin.log.categories')}</th>
                  <th style="padding: 8px; text-align: left;">{app.translator.trans('ralkage-civility-filter.admin.log.excerpt')}</th>
                  <th style="padding: 8px; text-align: center;">{app.translator.trans('ralkage-civility-filter.admin.log.actions_col')}</th>
                </tr>
              </thead>
              <tbody>
                {this.logs.map((log) => {
                  const a = log.attributes;
                  return (
                    <tr style="border-bottom: 1px solid #e8ecf3;">
                      <td style="padding: 8px; font-size: 12px;">{this.formatDate(a.createdAt)}</td>
                      <td style="padding: 8px;">{a.username}</td>
                      <td style="padding: 8px; text-align: center; font-weight: bold;">{a.civilityScore}</td>
                      <td style="padding: 8px; text-align: center;">
                        <span className={`CivilityLogPage-badge CivilityLogPage-badge--${a.actionTaken}`}>{a.actionTaken}</span>
                      </td>
                      <td style="padding: 8px; font-size: 12px;">{(a.categories || []).join(', ')}</td>
                      <td style="padding: 8px; font-size: 12px; max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{(a.messageExcerpt || '').substring(0, 80)}</td>
                      <td style="padding: 8px; text-align: center; white-space: nowrap;">
                        {a.contentId > 0 && a.actionTaken === 'moderated' && (
                          <Button className="Button Button--sm" icon="fas fa-check" title={app.translator.trans('ralkage-civility-filter.admin.log.approve')} onclick={() => this.moderateAction('approve', a.contentId, a.userId)} style="margin: 0 2px;" />
                        )}
                        {a.contentId > 0 && (
                          <Button className="Button Button--sm" icon="fas fa-trash-alt" title={app.translator.trans('ralkage-civility-filter.admin.log.delete')} onclick={() => this.moderateAction('delete', a.contentId, a.userId)} style="margin: 0 2px;" />
                        )}
                        {a.userId > 0 && (
                          <Button className="Button Button--sm" icon="fas fa-user-slash" title={app.translator.trans('ralkage-civility-filter.admin.log.suspend')} onclick={() => this.moderateAction('suspend', a.contentId, a.userId)} style="margin: 0 2px;" />
                        )}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>

            {this.totalPages > 1 && (
              <div style="margin-top: 15px; display: flex; gap: 5px; justify-content: center; align-items: center;">
                <Button className="Button" disabled={this.currentPage <= 1} onclick={() => { this.currentPage--; this.loadLogs(); }}>&laquo; Prev</Button>
                <span style="padding: 5px 10px;">Page {this.currentPage} / {this.totalPages}</span>
                <Button className="Button" disabled={this.currentPage >= this.totalPages} onclick={() => { this.currentPage++; this.loadLogs(); }}>Next &raquo;</Button>
              </div>
            )}
          </div>
        )}
      </div>
    );
  }

  // ── Stats ──

  statsTab() {
    if (this.statsLoading) return <LoadingIndicator />;
    if (!this.stats) return <p className="CivilityLogPage-empty">{app.translator.trans('ralkage-civility-filter.admin.stats.no_data')}</p>;

    const s = this.stats;
    return (
      <div className="CivilityLogPage-stats">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 25px;">
          <div className="CivilityLogPage-statCard">
            <div className="CivilityLogPage-statValue">{s.total}</div>
            <div className="CivilityLogPage-statLabel">{app.translator.trans('ralkage-civility-filter.admin.stats.total_checks')}</div>
          </div>
          <div className="CivilityLogPage-statCard">
            <div className="CivilityLogPage-statValue">{s.averageScore}</div>
            <div className="CivilityLogPage-statLabel">{app.translator.trans('ralkage-civility-filter.admin.stats.average_score')}</div>
          </div>
          {Object.entries(s.actions || {}).map(([action, count]) => (
            <div className="CivilityLogPage-statCard">
              <div className="CivilityLogPage-statValue">{count}</div>
              <div className="CivilityLogPage-statLabel"><span className={`CivilityLogPage-badge CivilityLogPage-badge--${action}`}>{action}</span></div>
            </div>
          ))}
        </div>

        {Object.keys(s.categories || {}).length > 0 && (
          <div style="margin-bottom: 25px;">
            <h4>{app.translator.trans('ralkage-civility-filter.admin.stats.top_categories')}</h4>
            <div style="display: flex; flex-wrap: wrap; gap: 8px;">
              {Object.entries(s.categories).map(([cat, count]) => (
                <span style="background: #e8ecf3; padding: 4px 10px; border-radius: 12px; font-size: 13px;">{cat}: <strong>{count}</strong></span>
              ))}
            </div>
          </div>
        )}

        {(s.topOffenders || []).length > 0 && (
          <div style="margin-bottom: 25px;">
            <h4>{app.translator.trans('ralkage-civility-filter.admin.stats.top_offenders')}</h4>
            <table className="CivilityLogPage-table">
              <thead>
                <tr style="border-bottom: 2px solid #e8ecf3;">
                  <th style="padding: 8px;">{app.translator.trans('ralkage-civility-filter.admin.stats.user')}</th>
                  <th style="padding: 8px; text-align: center;">{app.translator.trans('ralkage-civility-filter.admin.stats.violations')}</th>
                  <th style="padding: 8px; text-align: center;">{app.translator.trans('ralkage-civility-filter.admin.stats.avg_score')}</th>
                </tr>
              </thead>
              <tbody>
                {s.topOffenders.map((u) => (
                  <tr style="border-bottom: 1px solid #e8ecf3;">
                    <td style="padding: 8px;">{u.username}</td>
                    <td style="padding: 8px; text-align: center; font-weight: bold;">{u.violation_count}</td>
                    <td style="padding: 8px; text-align: center;">{u.avg_score}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {(s.dailyTrend || []).length > 0 && (
          <div>
            <h4>{app.translator.trans('ralkage-civility-filter.admin.stats.daily_trend')}</h4>
            <table className="CivilityLogPage-table">
              <thead>
                <tr style="border-bottom: 2px solid #e8ecf3;">
                  <th style="padding: 6px 8px;">{app.translator.trans('ralkage-civility-filter.admin.stats.date')}</th>
                  <th style="padding: 6px 8px; text-align: center;">{app.translator.trans('ralkage-civility-filter.admin.stats.total')}</th>
                  <th style="padding: 6px 8px; text-align: center;">{app.translator.trans('ralkage-civility-filter.admin.stats.flagged')}</th>
                  <th style="padding: 6px 8px;">Bar</th>
                </tr>
              </thead>
              <tbody>
                {s.dailyTrend.map((d) => {
                  const maxTotal = Math.max(...s.dailyTrend.map((x) => x.total || 1));
                  const pct = Math.round(((d.total || 0) / maxTotal) * 100);
                  const flagPct = d.total > 0 ? Math.round(((d.flagged || 0) / d.total) * 100) : 0;
                  return (
                    <tr style="border-bottom: 1px solid #e8ecf3;">
                      <td style="padding: 6px 8px; font-size: 12px;">{d.date}</td>
                      <td style="padding: 6px 8px; text-align: center;">{d.total}</td>
                      <td style="padding: 6px 8px; text-align: center;">{d.flagged}</td>
                      <td style="padding: 6px 8px;">
                        <div style="display: flex; height: 16px; border-radius: 3px; overflow: hidden; background: #e8ecf3;">
                          <div style={`width: ${pct - flagPct}%; background: #d4edda;`}></div>
                          <div style={`width: ${flagPct}%; background: #f8d7da;`}></div>
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
      </div>
    );
  }

  // ── Test ──

  testTab() {
    return (
      <div className="CivilityLogPage-test">
        <h3>{app.translator.trans('ralkage-civility-filter.admin.test.title')}</h3>
        <div className="Form-group" style="margin-bottom: 15px;">
          <label>{app.translator.trans('ralkage-civility-filter.admin.test.discussion_title_label')}</label>
          <input className="FormControl" type="text" value={this.testDiscussionTitle} placeholder={app.translator.trans('ralkage-civility-filter.admin.test.discussion_title_placeholder')} oninput={(e) => { this.testDiscussionTitle = e.target.value; }} />
        </div>
        <div className="Form-group" style="margin-bottom: 15px;">
          <label>{app.translator.trans('ralkage-civility-filter.admin.test.message_label')}</label>
          <textarea className="FormControl" rows="5" value={this.testMessage} placeholder={app.translator.trans('ralkage-civility-filter.admin.test.message_placeholder')} oninput={(e) => { this.testMessage = e.target.value; }} />
        </div>
        <Button className="Button Button--primary" loading={this.testLoading} onclick={() => this.runTest()}>
          {app.translator.trans('ralkage-civility-filter.admin.test.submit')}
        </Button>

        {this.testResult && (
          <div className="CivilityLogPage-testResult" style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 6px;">
            {this.testResult.error ? (
              <p style="color: #e74c3c;">{this.testResult.error}</p>
            ) : (
              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <div><strong>{app.translator.trans('ralkage-civility-filter.admin.test.result_score')}:</strong> <span style="font-size: 24px; font-weight: bold;">{this.testResult.score}</span></div>
                <div><strong>{app.translator.trans('ralkage-civility-filter.admin.test.result_action')}:</strong> <span className={`CivilityLogPage-badge CivilityLogPage-badge--${this.testResult.action}`}>{this.testResult.action}</span></div>
                <div style="grid-column: 1 / -1;"><strong>{app.translator.trans('ralkage-civility-filter.admin.test.result_reason')}:</strong> {this.testResult.reason}</div>
                <div><strong>{app.translator.trans('ralkage-civility-filter.admin.test.result_categories')}:</strong> {(this.testResult.categories || []).join(', ') || 'None'}</div>
                <div><strong>{app.translator.trans('ralkage-civility-filter.admin.test.result_latency')}:</strong> {this.testResult.latency}ms</div>
                {!this.testResult.api_success && <div style="grid-column: 1 / -1; color: #e74c3c;">{app.translator.trans('ralkage-civility-filter.admin.test.api_error')}</div>}
              </div>
            )}
          </div>
        )}
      </div>
    );
  }

  // ── API Methods ──

  async loadLogs() {
    this.logLoading = true; m.redraw();
    try {
      const q = [`page[number]=${this.currentPage}`];
      if (this.filterAction) q.push(`filter[action]=${this.filterAction}`);
      const r = await app.request({ method: 'GET', url: `${app.forum.attribute('apiUrl')}/civility-logs?${q.join('&')}` });
      this.logs = r.data || [];
      const meta = r.meta || {};
      this.totalPages = Math.ceil((meta.total || 0) / (meta.perPage || 50));
    } catch (e) { this.logs = []; }
    this.logLoading = false; m.redraw();
  }

  async loadStats() {
    this.statsLoading = true; m.redraw();
    try {
      this.stats = await app.request({ method: 'GET', url: `${app.forum.attribute('apiUrl')}/civility-logs/stats` });
    } catch (e) { this.stats = null; }
    this.statsLoading = false; m.redraw();
  }

  async runTest() {
    this.testLoading = true; this.testResult = null; m.redraw();
    try {
      this.testResult = await app.request({ method: 'POST', url: `${app.forum.attribute('apiUrl')}/civility-logs/test`, body: { message: this.testMessage, discussionTitle: this.testDiscussionTitle } });
    } catch (e) { this.testResult = { error: e.message || 'Request failed' }; }
    this.testLoading = false; m.redraw();
  }

  async clearLogs() {
    if (!confirm(app.translator.trans('ralkage-civility-filter.admin.log.clear_confirm'))) return;
    try {
      await app.request({ method: 'DELETE', url: `${app.forum.attribute('apiUrl')}/civility-logs` });
      this.logs = []; this.currentPage = 1; m.redraw();
    } catch (e) {}
  }

  exportLogs() {
    window.open(`${app.forum.attribute('apiUrl')}/civility-logs/export?token=${app.session.csrfToken}`, '_blank');
  }

  async moderateAction(action, postId, userId) {
    const labels = { approve: 'Approve this post?', delete: 'Hide this post?', suspend: 'Suspend this user for 3 days?' };
    if (!confirm(labels[action] || 'Are you sure?')) return;
    try {
      const r = await app.request({ method: 'POST', url: `${app.forum.attribute('apiUrl')}/civility-logs/moderate`, body: { action, postId, userId, days: 3 } });
      if (r.success) {
        app.alerts.show({ type: 'success' }, `${action.charAt(0).toUpperCase() + action.slice(1)} successful`);
      }
    } catch (e) {
      app.alerts.show({ type: 'error' }, e.message || 'Action failed');
    }
  }

  formatDate(s) {
    if (!s) return '';
    const d = new Date(s);
    return d.toLocaleDateString() + ' ' + d.toLocaleTimeString();
  }
}
