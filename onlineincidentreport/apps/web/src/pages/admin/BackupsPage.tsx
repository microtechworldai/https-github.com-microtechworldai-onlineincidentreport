import React, { useState, useRef } from 'react';
import {
  Database,
  Download,
  ShieldCheck,
  Clock,
  Server,
  CheckCircle2,
  Loader2,
  UploadCloud,
  FileJson,
  AlertTriangle,
  X,
  Check,
  RefreshCw,
} from 'lucide-react';
import { adminApi } from '../../lib/api';

export const BackupsPage: React.FC = () => {
  const [downloading, setDownloading] = useState<boolean>(false);
  const [lastExportTime, setLastExportTime] = useState<string | null>(null);

  // Restore from computer state
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [parsedBackup, setParsedBackup] = useState<any | null>(null);
  const [fileError, setFileError] = useState<string | null>(null);
  const [showConfirmModal, setShowConfirmModal] = useState<boolean>(false);
  const [restoring, setRestoring] = useState<boolean>(false);
  const [restoreSuccess, setRestoreSuccess] = useState<any | null>(null);

  const handleDownloadBackup = async () => {
    setDownloading(true);
    try {
      const blob = await adminApi.exportBackup();
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `uss_incidents_db_backup_${new Date().toISOString().slice(0, 10)}.json`;
      a.click();
      setLastExportTime(new Date().toLocaleTimeString('en-GB', { timeZone: 'Asia/Dubai' }));
    } catch {
      alert('Failed to generate database export');
    } finally {
      setDownloading(false);
    }
  };

  const handleFileSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
    setFileError(null);
    setRestoreSuccess(null);
    const file = e.target.files?.[0];
    if (!file) return;

    if (!file.name.endsWith('.json') && file.type !== 'application/json') {
      setFileError('Please select a valid JSON backup file (.json)');
      setSelectedFile(null);
      setParsedBackup(null);
      return;
    }

    setSelectedFile(file);
    const reader = new FileReader();
    reader.onload = (event) => {
      try {
        const json = JSON.parse(event.target?.result as string);
        // Validate basic backup structure
        const hasSites = Array.isArray(json.sites);
        const hasGuards = Array.isArray(json.guard_staff || json.guardStaff);
        const hasIncidents = Array.isArray(json.incidents);
        const hasSettings = Boolean(json.system_settings || json.settings);

        if (!hasSites && !hasGuards && !hasIncidents && !hasSettings) {
          setFileError('Invalid backup file: Could not detect USS database tables (sites, staff, incidents, or settings).');
          setParsedBackup(null);
          return;
        }

        setParsedBackup({
          raw: json,
          counts: {
            sites: (json.sites || []).length,
            guards: (json.guard_staff || json.guardStaff || []).length,
            incidents: (json.incidents || []).length,
            hasSettings,
          },
        });
      } catch (err: any) {
        setFileError(`Failed to parse JSON file: ${err.message}`);
        setParsedBackup(null);
      }
    };
    reader.onerror = () => {
      setFileError('Failed to read the selected file from disk');
      setParsedBackup(null);
    };
    reader.readAsText(file);
  };

  const handleExecuteRestore = async () => {
    if (!parsedBackup?.raw) return;
    setRestoring(true);
    setFileError(null);
    try {
      const res = await adminApi.restoreBackup(parsedBackup.raw);
      setRestoreSuccess(res);
      setShowConfirmModal(false);
      setSelectedFile(null);
      setParsedBackup(null);
      if (fileInputRef.current) fileInputRef.current.value = '';
    } catch (err: any) {
      alert(err.message || 'Failed to restore database from backup');
    } finally {
      setRestoring(false);
    }
  };

  const clearSelectedFile = () => {
    setSelectedFile(null);
    setParsedBackup(null);
    setFileError(null);
    if (fileInputRef.current) fileInputRef.current.value = '';
  };

  return (
    <div className="space-y-6 max-w-5xl">
      <div>
        <h1 className="text-xl md:text-2xl font-extrabold text-slate-900 tracking-tight">
          Database Backups & Disaster Recovery
        </h1>
        <p className="text-xs text-slate-500 mt-0.5">
          Automated nightly backups status, manual database exports, and restore procedures
        </p>
      </div>

      {restoreSuccess && (
        <div className="bg-emerald-50 border border-emerald-200 rounded-3xl p-5 flex items-start justify-between gap-4">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-2xl bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
              <Check className="w-5 h-5 stroke-[3]" />
            </div>
            <div className="space-y-1">
              <h3 className="font-extrabold text-sm text-emerald-900">
                Database Successfully Restored from Backup File
              </h3>
              <p className="text-xs text-emerald-700">
                Restored: {restoreSuccess.restored?.sites || 0} sites, {restoreSuccess.restored?.guardStaff || 0} staff accounts, {restoreSuccess.restored?.incidents || 0} incidents. All records have been safely synced.
              </p>
            </div>
          </div>
          <button
            onClick={() => setRestoreSuccess(null)}
            className="text-emerald-700 hover:text-emerald-900 text-xs font-bold p-1"
          >
            <X className="w-4 h-4" />
          </button>
        </div>
      )}

      <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
        {/* Trigger Manual Export Card */}
        <div className="bg-white p-6 rounded-3xl border border-slate-200 shadow-xs space-y-4 flex flex-col justify-between">
          <div className="space-y-4">
            <div className="flex items-center gap-3">
              <div className="p-3 bg-blue-50 text-blue-700 rounded-2xl">
                <Download className="w-6 h-6" />
              </div>
              <div>
                <h2 className="font-extrabold text-sm text-slate-900">
                  On-Demand Database Dump
                </h2>
                <p className="text-xs text-slate-500">
                  Export all incidents, sites, guards, and settings
                </p>
              </div>
            </div>

            <p className="text-xs text-slate-600 leading-relaxed">
              Generate and securely download a comprehensive JSON snapshot of the active PostgreSQL database. Passwords remain encrypted.
            </p>

            {lastExportTime && (
              <div className="flex items-center gap-2 text-xs text-emerald-700 font-medium bg-emerald-50 p-2.5 rounded-xl border border-emerald-100">
                <CheckCircle2 className="w-4 h-4 shrink-0" />
                <span>Export downloaded successfully at {lastExportTime} (GST)</span>
              </div>
            )}
          </div>

          <button
            onClick={handleDownloadBackup}
            disabled={downloading}
            className="w-full py-3.5 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white font-bold rounded-xl text-xs flex items-center justify-center gap-2 shadow-sm transition-all"
          >
            {downloading ? (
              <>
                <Loader2 className="w-4 h-4 animate-spin" />
                <span>Exporting Database Dump...</span>
              </>
            ) : (
              <>
                <Download className="w-4 h-4" />
                <span>Trigger & Download Database Backup</span>
              </>
            )}
          </button>
        </div>

        {/* Automated Nightly Backup Cron Info Card */}
        <div className="bg-white p-6 rounded-3xl border border-slate-200 shadow-xs space-y-4">
          <div className="flex items-center gap-3">
            <div className="p-3 bg-slate-100 text-slate-800 rounded-2xl">
              <Server className="w-6 h-6" />
            </div>
            <div>
              <h2 className="font-extrabold text-sm text-slate-900">
                Automated Production Cron
              </h2>
              <p className="text-xs text-slate-500">
                Nightly PostgreSQL pg_dump daemon
              </p>
            </div>
          </div>

          <div className="space-y-2 text-xs">
            <div className="flex justify-between py-1.5 border-b border-slate-100">
              <span className="text-slate-500">Schedule:</span>
              <span className="font-bold text-slate-800 font-mono">03:00 GST Every Night</span>
            </div>
            <div className="flex justify-between py-1.5 border-b border-slate-100">
              <span className="text-slate-500">Retention Period:</span>
              <span className="font-bold text-slate-800 font-mono">30 Days Gzip Archive</span>
            </div>
            <div className="flex justify-between py-1.5 border-b border-slate-100">
              <span className="text-slate-500">Backup Script:</span>
              <span className="font-bold text-slate-800 font-mono">infra/scripts/backup.sh</span>
            </div>
            <div className="flex justify-between py-1.5">
              <span className="text-slate-500">Restore Procedure:</span>
              <span className="font-bold text-slate-800 font-mono">infra/scripts/restore.sh</span>
            </div>
          </div>
        </div>
      </div>

      {/* Restore Database from Computer Card */}
      <div className="bg-white p-6 rounded-3xl border border-slate-200 shadow-xs space-y-5">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div className="flex items-center gap-3">
            <div className="p-3 bg-emerald-50 text-emerald-700 rounded-2xl">
              <UploadCloud className="w-6 h-6" />
            </div>
            <div>
              <h2 className="font-extrabold text-base text-slate-900">
                Restore Database from Computer
              </h2>
              <p className="text-xs text-slate-500">
                Upload a JSON backup file from your local machine to restore system data
              </p>
            </div>
          </div>

          <input
            type="file"
            ref={fileInputRef}
            accept=".json,application/json"
            onChange={handleFileSelect}
            className="hidden"
          />

          <button
            type="button"
            onClick={() => fileInputRef.current?.click()}
            className="flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-xs transition-all w-fit"
          >
            <UploadCloud className="w-4 h-4" />
            <span>Select Backup File from Computer</span>
          </button>
        </div>

        <p className="text-xs text-slate-600 leading-relaxed">
          Select an existing database backup JSON file exported previously. The restore engine safely merges client sites, security staff credentials, incident reports, and system branding while preserving password encryptions and compliance audit trails.
        </p>

        {fileError && (
          <div className="p-3 bg-rose-50 border border-rose-200 rounded-2xl text-xs text-rose-700 flex items-center gap-2">
            <AlertTriangle className="w-4 h-4 shrink-0 text-rose-600" />
            <span>{fileError}</span>
          </div>
        )}

        {/* Selected File Inspection Box */}
        {selectedFile && parsedBackup && (
          <div className="bg-slate-50 border border-slate-200 rounded-2xl p-4 space-y-4">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-200/80 pb-3">
              <div className="flex items-center gap-2.5 min-w-0">
                <FileJson className="w-6 h-6 text-emerald-600 shrink-0" />
                <div className="min-w-0">
                  <p className="font-bold text-xs text-slate-900 truncate">
                    {selectedFile.name}
                  </p>
                  <p className="text-[10px] text-slate-500 font-mono">
                    {(selectedFile.size / 1024).toFixed(1)} KB • Validated JSON
                  </p>
                </div>
              </div>

              <button
                type="button"
                onClick={clearSelectedFile}
                className="text-slate-400 hover:text-slate-600 text-xs font-semibold flex items-center gap-1 self-start sm:self-auto"
              >
                <X className="w-4 h-4" />
                <span>Remove File</span>
              </button>
            </div>

            {/* Found Record Metrics */}
            <div className="space-y-1.5">
              <span className="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                Records Detected in File:
              </span>
              <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs">
                <div className="p-2.5 bg-white border border-slate-200 rounded-xl">
                  <span className="text-slate-500 block text-[10px]">Client Sites</span>
                  <span className="font-extrabold text-slate-900 text-sm font-mono">
                    {parsedBackup.counts.sites}
                  </span>
                </div>
                <div className="p-2.5 bg-white border border-slate-200 rounded-xl">
                  <span className="text-slate-500 block text-[10px]">Security Staff</span>
                  <span className="font-extrabold text-slate-900 text-sm font-mono">
                    {parsedBackup.counts.guards}
                  </span>
                </div>
                <div className="p-2.5 bg-white border border-slate-200 rounded-xl">
                  <span className="text-slate-500 block text-[10px]">Incident Reports</span>
                  <span className="font-extrabold text-slate-900 text-sm font-mono">
                    {parsedBackup.counts.incidents}
                  </span>
                </div>
                <div className="p-2.5 bg-white border border-slate-200 rounded-xl">
                  <span className="text-slate-500 block text-[10px]">System Settings</span>
                  <span className="font-extrabold text-emerald-700 text-sm">
                    {parsedBackup.counts.hasSettings ? 'Included' : 'None'}
                  </span>
                </div>
              </div>
            </div>

            <div className="pt-2 flex justify-end">
              <button
                type="button"
                onClick={() => setShowConfirmModal(true)}
                className="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-sm transition-all flex items-center gap-2"
              >
                <RefreshCw className="w-4 h-4" />
                <span>Proceed to Restore Database</span>
              </button>
            </div>
          </div>
        )}
      </div>

      {/* Confirmation Modal */}
      {showConfirmModal && parsedBackup && (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl p-6 shadow-2xl max-w-md w-full space-y-4 border border-emerald-100">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 rounded-2xl bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                <UploadCloud className="w-5 h-5" />
              </div>
              <div>
                <h3 className="font-extrabold text-base text-slate-900">
                  Confirm Database Restore
                </h3>
                <p className="text-xs text-slate-500">
                  Restore from {selectedFile?.name}
                </p>
              </div>
            </div>

            <div className="p-3.5 bg-amber-50 border border-amber-200 rounded-2xl text-xs text-amber-900 space-y-1">
              <p className="font-bold flex items-center gap-1.5">
                <AlertTriangle className="w-4 h-4 text-amber-600 shrink-0" />
                <span>Notice regarding database restoration</span>
              </p>
              <p className="text-[11px] leading-relaxed text-amber-800">
                This will update active sites, security staff accounts, and incident records using the uploaded snapshot. An entry will be recorded in the compliance audit trail.
              </p>
            </div>

            <div className="bg-slate-50 p-3 rounded-2xl border border-slate-200 text-xs space-y-1">
              <div className="flex justify-between">
                <span className="text-slate-500">Sites to sync:</span>
                <span className="font-bold font-mono text-slate-900">{parsedBackup.counts.sites}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-500">Guards to sync:</span>
                <span className="font-bold font-mono text-slate-900">{parsedBackup.counts.guards}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-500">Incidents to sync:</span>
                <span className="font-bold font-mono text-slate-900">{parsedBackup.counts.incidents}</span>
              </div>
            </div>

            <div className="flex justify-end gap-2 pt-2">
              <button
                type="button"
                onClick={() => setShowConfirmModal(false)}
                disabled={restoring}
                className="px-4 py-2 border rounded-xl font-semibold text-xs text-slate-700 hover:bg-slate-50"
              >
                Cancel
              </button>
              <button
                type="button"
                onClick={handleExecuteRestore}
                disabled={restoring}
                className="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold text-xs shadow-sm transition-all flex items-center gap-1.5"
              >
                {restoring ? (
                  <>
                    <Loader2 className="w-4 h-4 animate-spin" />
                    <span>Restoring Database...</span>
                  </>
                ) : (
                  <>
                    <Check className="w-4 h-4 stroke-[3]" />
                    <span>Yes, Restore Now</span>
                  </>
                )}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
