import React, { useEffect, useState } from 'react';
import {
  History,
  Shield,
  User,
  Clock,
  Search,
  Download,
  Filter,
  CheckCircle2,
  AlertTriangle,
  FileText,
  Building2,
  Users,
  Lock,
  Eye,
  X,
} from 'lucide-react';
import { adminApi } from '../../lib/api';

export const AuditLogsPage: React.FC = () => {
  const [logs, setLogs] = useState<any[]>([]);
  const [search, setSearch] = useState<string>('');
  const [selectedCategory, setSelectedCategory] = useState<string>('all');
  const [loading, setLoading] = useState<boolean>(true);
  const [inspectLog, setInspectLog] = useState<any | null>(null);

  useEffect(() => {
    adminApi
      .listAuditLogs()
      .then((data) => setLogs(data))
      .catch(console.error)
      .finally(() => setLoading(false));
  }, []);

  const getActionCategory = (action: string): string => {
    const act = (action || '').toUpperCase();
    if (act.includes('INCIDENT') || act.includes('REPORT')) return 'incidents';
    if (act.includes('STAFF') || act.includes('GUARD') || act.includes('SITE') || act.includes('QR')) return 'staff_sites';
    if (act.includes('LOGIN') || act.includes('AUTH') || act.includes('PASSWORD')) return 'security';
    if (act.includes('SETTINGS') || act.includes('EXPORT') || act.includes('LOGO') || act.includes('BACKUP')) return 'system';
    return 'other';
  };

  const getActionBadge = (action: string) => {
    const act = (action || '').toUpperCase();

    if (act.includes('DELETE') || act.includes('REVOKE') || act.includes('FAILED')) {
      return {
        label: formatActionTitle(act),
        classes: 'bg-rose-50 text-rose-700 border-rose-200',
        dot: 'bg-rose-500',
      };
    }
    if (act.includes('RESTORE') || act.includes('CREATE') || act.includes('SUCCESS') || act.includes('SUBMIT')) {
      return {
        label: formatActionTitle(act),
        classes: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        dot: 'bg-emerald-500',
      };
    }
    if (act.includes('RESET') || act.includes('WARNING')) {
      return {
        label: formatActionTitle(act),
        classes: 'bg-amber-50 text-amber-700 border-amber-200',
        dot: 'bg-amber-500',
      };
    }
    return {
      label: formatActionTitle(act),
      classes: 'bg-blue-50 text-blue-700 border-blue-200',
      dot: 'bg-blue-500',
    };
  };

  const formatActionTitle = (action: string): string => {
    const map: Record<string, string> = {
      SUBMIT_INCIDENT: 'Incident Submitted',
      UPDATE_INCIDENT: 'Report Updated',
      SOFT_DELETE_INCIDENT: 'Moved to Trash',
      RESTORE_INCIDENT: 'Report Restored',
      PERMANENT_DELETE_INCIDENT: 'Permanently Erased',
      CREATE_GUARD_STAFF: 'Staff Created',
      UPDATE_GUARD_STAFF: 'Staff Updated',
      DELETE_GUARD_STAFF: 'Staff Deleted',
      RESET_GUARD_PASSWORD: 'Password Reset',
      CREATE_SITE: 'Site Created',
      UPDATE_SITE: 'Site Updated',
      DELETE_SITE: 'Site Deleted',
      GENERATE_SITE_QR: 'QR Generated',
      REVOKE_SITE_QR: 'QR Revoked',
      LOGIN_SUCCESS: 'Admin Login',
      LOGIN_FAILED: 'Failed Login',
      EXPORT_DAILY_PDF: 'Daily PDF Export',
      EXPORT_DAILY_EXCEL: 'Daily Excel Export',
      EXPORT_INCIDENT_PDF: 'Incident PDF Export',
      UPLOAD_COMPANY_LOGO: 'Logo Updated',
      UPDATE_SETTINGS: 'Settings Saved',
    };
    return map[action] || action.replace(/_/g, ' ');
  };

  const formatIpAddress = (ip: string | null | undefined): string => {
    if (!ip) return '127.0.0.1 (Localhost / LAN)';
    let clean = String(ip).replace(/^::ffff:/, '').trim();
    if (clean === '::1' || clean === '127.0.0.1') {
      return '127.0.0.1 (Localhost / LAN)';
    }
    return clean;
  };

  const formatDetailsPreview = (rawDetails: any) => {
    if (!rawDetails) return <span className="text-slate-400 italic text-[11px]">—</span>;
    let details = rawDetails;
    if (typeof details === 'string') {
      try {
        details = JSON.parse(details);
      } catch {
        return <span className="font-mono text-slate-700 text-xs">{details}</span>;
      }
    }

    if (!details || typeof details !== 'object') {
      return <span className="text-slate-400 italic text-[11px]">—</span>;
    }

    const items: React.ReactNode[] = [];

    // Ref number
    if (details.refNumber) {
      items.push(
        <span key="ref" className="px-2 py-0.5 bg-blue-100 text-blue-900 border border-blue-200 rounded-md font-mono font-bold text-[11px]">
          Ref: {details.refNumber}
        </span>
      );
    }

    // Site info
    const siteTitle = details.siteName || details.name;
    const siteCode = details.siteCode || details.code;
    if (siteTitle) {
      items.push(
        <span key="site" className="px-2 py-0.5 bg-slate-100 text-slate-800 border border-slate-200 rounded-md font-semibold text-[11px]">
          Site: {siteTitle} {siteCode ? `(${siteCode})` : ''}
        </span>
      );
    } else if (siteCode) {
      items.push(
        <span key="sitecode" className="px-2 py-0.5 bg-slate-100 text-slate-800 border border-slate-200 rounded-md font-mono text-[11px]">
          Site Code: {siteCode}
        </span>
      );
    }

    // Staff info
    if (details.fullName || details.staffId) {
      items.push(
        <span key="staff" className="px-2 py-0.5 bg-indigo-50 text-indigo-900 border border-indigo-200 rounded-md font-medium text-[11px]">
          Staff: {details.fullName || ''} {details.staffId ? `(${details.staffId})` : ''}
        </span>
      );
    }

    // Username
    if (details.username) {
      items.push(
        <span key="user" className="px-2 py-0.5 bg-slate-100 text-slate-700 border border-slate-200 rounded-md font-mono text-[11px]">
          @{details.username}
        </span>
      );
    }

    // Changes array
    if (Array.isArray(details.changes) && details.changes.length > 0) {
      items.push(
        <span key="changes" className="px-2 py-0.5 bg-amber-50 text-amber-800 border border-amber-200 rounded-md text-[11px]">
          Fields: {details.changes.slice(0, 3).join(', ')}{details.changes.length > 3 ? ` +${details.changes.length - 3} more` : ''}
        </span>
      );
    }

    // Category / Priority
    if (details.category) {
      items.push(
        <span key="cat" className="px-2 py-0.5 bg-purple-50 text-purple-800 border border-purple-200 rounded-md text-[11px]">
          {details.category}
        </span>
      );
    }
    if (details.priority) {
      items.push(
        <span key="prio" className="px-2 py-0.5 bg-rose-50 text-rose-800 border border-rose-200 rounded-md text-[11px]">
          {details.priority}
        </span>
      );
    }

    if (items.length > 0) {
      return <div className="flex flex-wrap items-center gap-1.5">{items}</div>;
    }

    // Other keys (ignore internal technical IDs)
    const ignoredKeys = new Set(['siteId', 'targetId', 'id', 'token']);
    const otherKeys = Object.keys(details).filter((k) => !ignoredKeys.has(k));

    if (otherKeys.length === 0) {
      return <span className="text-slate-400 italic text-[11px]">—</span>;
    }

    return (
      <div className="flex flex-wrap gap-1">
        {otherKeys.slice(0, 3).map((k) => (
          <span key={k} className="px-1.5 py-0.5 bg-slate-100 text-slate-600 rounded text-[11px] font-mono">
            {k}: {typeof details[k] === 'object' ? JSON.stringify(details[k]) : String(details[k])}
          </span>
        ))}
      </div>
    );
  };

  const filteredLogs = logs.filter((l) => {
    if (selectedCategory !== 'all') {
      const cat = getActionCategory(l.action);
      if (cat !== selectedCategory) return false;
    }

    if (!search) return true;
    const s = search.toLowerCase();
    const actionText = formatActionTitle(l.action || '').toLowerCase();
    const rawAction = (l.action || '').toLowerCase();
    const actor = (l.actor_name || '').toLowerCase();
    const target = (l.target_type || '').toLowerCase();
    const ip = (l.ip_address || '').toLowerCase();
    const detailsStr = JSON.stringify(l.details || {}).toLowerCase();

    return (
      actionText.includes(s) ||
      rawAction.includes(s) ||
      actor.includes(s) ||
      target.includes(s) ||
      ip.includes(s) ||
      detailsStr.includes(s)
    );
  });

  const exportAuditCsv = () => {
    const headers = ['Timestamp (GST)', 'Actor Type', 'Actor Name', 'Action Event', 'Target Type', 'Target ID', 'IP Address', 'Details'];
    const rows = filteredLogs.map((l) => [
      new Date(l.created_at).toLocaleString('en-GB', { timeZone: 'Asia/Dubai' }),
      l.actor_type,
      l.actor_name || 'System',
      formatActionTitle(l.action),
      l.target_type,
      l.target_id || '',
      l.ip_address || 'Internal/Localhost',
      JSON.stringify(l.details || {}).replace(/"/g, '""'),
    ]);
    const csvContent =
      'data:text/csv;charset=utf-8,' +
      [headers.join(','), ...rows.map((e) => `"${e.join('","')}"`)].join('\n');
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute('download', `USS_Audit_Compliance_Log_${new Date().toISOString().slice(0, 10)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-xl md:text-2xl font-black text-slate-900 tracking-tight">
            Security Audit Trail & Compliance Log
          </h1>
          <p className="text-xs text-slate-500 mt-0.5">
            Immutable tracking of logins, report submissions, staff administration, and operational sign-offs
          </p>
        </div>

        <button
          onClick={exportAuditCsv}
          className="flex items-center gap-2 px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-bold shadow-sm transition-all"
        >
          <Download className="w-4 h-4" />
          <span>Export Audit Log (CSV)</span>
        </button>
      </div>

      {/* Category Filter Pills & Search Bar */}
      <div className="bg-white p-5 rounded-3xl border border-slate-200 shadow-xs space-y-4">
        <div className="flex flex-wrap items-center gap-2 border-b border-slate-100 pb-3">
          <span className="text-xs font-bold text-slate-400 mr-1 flex items-center gap-1">
            <Filter className="w-3.5 h-3.5" />
            Category:
          </span>

          <button
            onClick={() => setSelectedCategory('all')}
            className={`px-3 py-1.5 rounded-xl text-xs font-bold transition-all ${
              selectedCategory === 'all'
                ? 'bg-blue-600 text-white shadow-xs'
                : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
            }`}
          >
            All Events ({logs.length})
          </button>

          <button
            onClick={() => setSelectedCategory('incidents')}
            className={`flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold transition-all ${
              selectedCategory === 'incidents'
                ? 'bg-blue-600 text-white shadow-xs'
                : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
            }`}
          >
            <FileText className="w-3.5 h-3.5" />
            <span>Incidents & Reports</span>
          </button>

          <button
            onClick={() => setSelectedCategory('staff_sites')}
            className={`flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold transition-all ${
              selectedCategory === 'staff_sites'
                ? 'bg-blue-600 text-white shadow-xs'
                : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
            }`}
          >
            <Users className="w-3.5 h-3.5" />
            <span>Staff & Client Sites</span>
          </button>

          <button
            onClick={() => setSelectedCategory('security')}
            className={`flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold transition-all ${
              selectedCategory === 'security'
                ? 'bg-blue-600 text-white shadow-xs'
                : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
            }`}
          >
            <Lock className="w-3.5 h-3.5" />
            <span>Security & Auth</span>
          </button>

          <button
            onClick={() => setSelectedCategory('system')}
            className={`flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold transition-all ${
              selectedCategory === 'system'
                ? 'bg-blue-600 text-white shadow-xs'
                : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
            }`}
          >
            <Shield className="w-3.5 h-3.5" />
            <span>System & Exports</span>
          </button>
        </div>

        {/* Search Bar */}
        <div className="flex items-center gap-3">
          <div className="relative flex-1">
            <Search className="w-4 h-4 text-slate-400 absolute left-3 top-3" />
            <input
              type="text"
              placeholder="Search by action, actor, site, reference number, or IP address..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="w-full text-xs pl-9 pr-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-600 font-medium"
            />
          </div>
          <span className="text-[11px] text-slate-500 font-mono font-bold whitespace-nowrap bg-slate-100 px-3 py-2 rounded-xl">
            {filteredLogs.length} matching event{filteredLogs.length === 1 ? '' : 's'}
          </span>
        </div>
      </div>

      {/* Audit Table */}
      <div className="bg-white rounded-3xl border border-slate-200 shadow-xs overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead className="bg-slate-50 text-slate-600 font-bold uppercase tracking-wider text-[10px] border-b border-slate-200">
              <tr>
                <th className="px-5 py-3.5">Timestamp (GST)</th>
                <th className="px-5 py-3.5">Actor</th>
                <th className="px-5 py-3.5">Action Event</th>
                <th className="px-5 py-3.5">Target Scope</th>
                <th className="px-5 py-3.5">IP Address</th>
                <th className="px-5 py-3.5">Details & Metadata</th>
                <th className="px-5 py-3.5 text-right">Inspect</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 font-medium text-slate-700">
              {filteredLogs.length === 0 ? (
                <tr>
                  <td colSpan={7} className="px-5 py-12 text-center text-slate-400">
                    No compliance audit entries found matching the filter.
                  </td>
                </tr>
              ) : (
                filteredLogs.map((log) => {
                  const badge = getActionBadge(log.action);

                  return (
                    <tr key={log.id} className="hover:bg-slate-50/80 transition-colors">
                      <td className="px-5 py-3.5 whitespace-nowrap font-mono text-slate-600 text-[11px]">
                        {new Date(log.created_at).toLocaleString('en-GB', {
                          timeZone: 'Asia/Dubai',
                          dateStyle: 'medium',
                          timeStyle: 'medium',
                        })}
                      </td>
                      <td className="px-5 py-3.5 whitespace-nowrap">
                        <span className="font-extrabold text-slate-900 block">
                          {log.actor_name || 'System'}
                        </span>
                        <span className="text-[10px] text-slate-400 font-mono uppercase">
                          {log.actor_type}
                        </span>
                      </td>
                      <td className="px-5 py-3.5 whitespace-nowrap">
                        <span
                          className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl text-[11px] font-bold border ${badge.classes}`}
                        >
                          <span className={`w-1.5 h-1.5 rounded-full ${badge.dot}`}></span>
                          {badge.label}
                        </span>
                      </td>
                      <td className="px-5 py-3.5 whitespace-nowrap">
                        <span className="font-mono text-slate-700 font-bold bg-slate-100 px-2 py-0.5 rounded text-[11px]">
                          {log.target_type}
                        </span>
                      </td>
                      <td className="px-5 py-3.5 whitespace-nowrap">
                        <span className="font-mono text-[11px] text-slate-700 bg-slate-100 px-2 py-1 rounded-md border border-slate-200 inline-flex items-center gap-1.5 shadow-2xs">
                          <span className="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                          {formatIpAddress(log.ip_address)}
                        </span>
                      </td>
                      <td className="px-5 py-3.5 min-w-[240px]">
                        {formatDetailsPreview(log.details)}
                      </td>
                      <td className="px-5 py-3.5 text-right whitespace-nowrap">
                        <button
                          onClick={() => setInspectLog(log)}
                          title="Inspect raw audit payload"
                          className="p-1.5 text-slate-500 hover:text-blue-600 hover:bg-slate-100 rounded-lg transition-colors inline-flex items-center gap-1"
                        >
                          <Eye className="w-3.5 h-3.5" />
                          <span className="text-[11px] font-semibold">Inspect</span>
                        </button>
                      </td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Inspect Audit Log Modal */}
      {inspectLog && (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl p-6 shadow-2xl max-w-lg w-full space-y-4 border border-slate-200">
            <div className="flex items-center justify-between border-b border-slate-100 pb-3">
              <div className="flex items-center gap-2">
                <History className="w-5 h-5 text-blue-600" />
                <h3 className="font-extrabold text-base text-slate-900">
                  Compliance Audit Event Record
                </h3>
              </div>
              <button
                onClick={() => setInspectLog(null)}
                className="p-1 text-slate-400 hover:text-slate-700"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <div className="space-y-2.5 text-xs">
              <div className="flex justify-between py-1.5 border-b border-slate-100">
                <span className="text-slate-500 font-bold">Event Action:</span>
                <span className="font-extrabold text-slate-900 font-mono">
                  {inspectLog.action} ({formatActionTitle(inspectLog.action)})
                </span>
              </div>
              <div className="flex justify-between py-1.5 border-b border-slate-100">
                <span className="text-slate-500 font-bold">Timestamp:</span>
                <span className="font-mono text-slate-700">
                  {new Date(inspectLog.created_at).toISOString()} (Dubai GST)
                </span>
              </div>
              <div className="flex justify-between py-1.5 border-b border-slate-100">
                <span className="text-slate-500 font-bold">Actor:</span>
                <span className="font-bold text-slate-900">
                  {inspectLog.actor_name || 'System'} ({inspectLog.actor_type})
                </span>
              </div>
              <div className="flex justify-between py-1.5 border-b border-slate-100">
                <span className="text-slate-500 font-bold">Target Entity:</span>
                <span className="font-mono text-slate-800">
                  {inspectLog.target_type} {inspectLog.target_id ? `(${inspectLog.target_id})` : ''}
                </span>
              </div>
              <div className="flex justify-between py-1.5 border-b border-slate-100">
                <span className="text-slate-500 font-bold">Origin IP:</span>
                <span className="font-mono text-slate-700">
                  {formatIpAddress(inspectLog.ip_address)}
                </span>
              </div>

              <div className="pt-2">
                <span className="text-slate-500 font-bold block mb-1">Raw Payload Details:</span>
                <pre className="p-3 bg-slate-900 text-emerald-400 rounded-2xl text-[11px] font-mono overflow-x-auto max-h-48">
                  {JSON.stringify(inspectLog.details || {}, null, 2)}
                </pre>
              </div>
            </div>

            <div className="flex justify-end pt-2">
              <button
                type="button"
                onClick={() => setInspectLog(null)}
                className="px-5 py-2 bg-slate-900 text-white rounded-xl text-xs font-bold hover:bg-slate-800"
              >
                Close
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
