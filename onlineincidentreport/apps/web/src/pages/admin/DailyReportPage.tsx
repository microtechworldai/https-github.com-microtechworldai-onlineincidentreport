import React, { useEffect, useState } from 'react';
import {
  FileText,
  FileSpreadsheet,
  Download,
  Calendar,
  Building2,
  CheckCircle2,
  AlertCircle,
  Loader2,
  Clock,
  Send,
} from 'lucide-react';
import { adminApi } from '../../lib/api';

export const DailyReportPage: React.FC = () => {
  const [sites, setSites] = useState<any[]>([]);
  const [selectedSiteId, setSelectedSiteId] = useState<string>('');
  const [reportDate, setReportDate] = useState<string>(new Date().toISOString().slice(0, 10));
  const [markSubmitted, setMarkSubmitted] = useState<boolean>(true);

  const [previewIncidents, setPreviewIncidents] = useState<any[]>([]);
  const [loadingPreview, setLoadingPreview] = useState<boolean>(false);
  const [exportingPdf, setExportingPdf] = useState<boolean>(false);
  const [exportingExcel, setExportingExcel] = useState<boolean>(false);
  const [statusMessage, setStatusMessage] = useState<string | null>(null);

  useEffect(() => {
    adminApi.listSites().then((data) => {
      setSites(data);
      if (data.length > 0 && !selectedSiteId) {
        setSelectedSiteId(data[0].id);
      }
    });
  }, []);

  useEffect(() => {
    if (selectedSiteId && reportDate) {
      setLoadingPreview(true);
      adminApi
        .listIncidents({
          siteId: selectedSiteId,
          dateFrom: reportDate,
          dateTo: reportDate,
          limit: 100,
        })
        .then((res) => setPreviewIncidents(res.incidents || []))
        .catch(console.error)
        .finally(() => setLoadingPreview(false));
    }
  }, [selectedSiteId, reportDate]);

  const selectedSite = sites.find((s) => s.id === selectedSiteId);

  const handleExportPdf = async () => {
    if (!selectedSiteId || !reportDate) return;
    setExportingPdf(true);
    setStatusMessage(null);

    try {
      const blob = await adminApi.exportDailyPdf(selectedSiteId, reportDate, markSubmitted);
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `USS-Daily-Report-${selectedSite?.code || 'SITE'}-${reportDate}.pdf`;
      a.click();
      setStatusMessage('Daily consolidated PDF generated and downloaded successfully.');
    } catch (err: any) {
      alert(err.message || 'Failed to export PDF');
    } finally {
      setExportingPdf(false);
    }
  };

  const handleExportExcel = async () => {
    if (!selectedSiteId || !reportDate) return;
    setExportingExcel(true);
    setStatusMessage(null);

    try {
      const blob = await adminApi.exportDailyExcel(selectedSiteId, reportDate, markSubmitted);
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `USS-Daily-Report-${selectedSite?.code || 'SITE'}-${reportDate}.xlsx`;
      a.click();
      setStatusMessage('Daily consolidated Excel generated and downloaded successfully.');
    } catch (err: any) {
      alert(err.message || 'Failed to export Excel');
    } finally {
      setExportingExcel(false);
    }
  };

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-xl md:text-2xl font-extrabold text-slate-900 tracking-tight">
          Daily Client Consolidated Report Generator
        </h1>
        <p className="text-xs text-slate-500 mt-0.5">
          Generate official site-specific daily incident dossiers for client presentation (PDF & Excel only)
        </p>
      </div>

      {statusMessage && (
        <div className="p-4 bg-emerald-50 text-emerald-800 rounded-2xl border border-emerald-200 text-xs flex items-center gap-2">
          <CheckCircle2 className="w-4 h-4 shrink-0 text-emerald-600" />
          <span>{statusMessage}</span>
        </div>
      )}

      {/* Control Panel Card */}
      <div className="bg-white p-6 rounded-3xl border border-slate-200 shadow-xs space-y-5">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label className="block text-xs font-bold text-slate-700 uppercase mb-1.5">
              Select Facility / Site
            </label>
            <div className="relative">
              <select
                value={selectedSiteId}
                onChange={(e) => setSelectedSiteId(e.target.value)}
                className="w-full text-xs p-3.5 pl-10 bg-slate-50 border border-slate-300 rounded-2xl font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-600"
              >
                {sites.map((s) => (
                  <option key={s.id} value={s.id}>
                    {s.name} ({s.code} • {s.type})
                  </option>
                ))}
              </select>
              <Building2 className="w-5 h-5 text-slate-400 absolute left-3 top-3" />
            </div>
          </div>

          <div>
            <label className="block text-xs font-bold text-slate-700 uppercase mb-1.5">
              Report Date (Asia/Dubai)
            </label>
            <div className="relative">
              <input
                type="date"
                value={reportDate}
                onChange={(e) => setReportDate(e.target.value)}
                className="w-full text-xs p-3.5 pl-10 bg-slate-50 border border-slate-300 rounded-2xl font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-600"
              />
              <Calendar className="w-5 h-5 text-slate-400 absolute left-3 top-3" />
            </div>
          </div>
        </div>

        {/* Submission Option Toggle */}
        <div className="flex items-center gap-3 p-3.5 bg-blue-50/70 rounded-2xl border border-blue-200/80">
          <input
            type="checkbox"
            id="markSubmittedToggle"
            checked={markSubmitted}
            onChange={(e) => setMarkSubmitted(e.target.checked)}
            className="w-4 h-4 text-blue-600 rounded focus:ring-0"
          />
          <label htmlFor="markSubmittedToggle" className="text-xs font-semibold text-blue-900 cursor-pointer">
            Mark all included incidents as <span className="font-bold underline">"Submitted to Client"</span> and record client dossier timestamp
          </label>
        </div>

        {/* Action Buttons */}
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
          <button
            onClick={handleExportPdf}
            disabled={exportingPdf || previewIncidents.length === 0}
            className="flex items-center justify-center gap-2.5 py-4 bg-[#0F2042] hover:bg-[#162e5c] disabled:opacity-50 text-white rounded-2xl font-bold text-xs shadow-md transition-all touch-btn"
          >
            {exportingPdf ? (
              <Loader2 className="w-4 h-4 animate-spin text-amber-400" />
            ) : (
              <FileText className="w-4 h-4 text-amber-400" />
            )}
            <span>Generate Consolidated PDF Report</span>
          </button>

          <button
            onClick={handleExportExcel}
            disabled={exportingExcel || previewIncidents.length === 0}
            className="flex items-center justify-center gap-2.5 py-4 bg-emerald-700 hover:bg-emerald-800 disabled:opacity-50 text-white rounded-2xl font-bold text-xs shadow-md transition-all touch-btn"
          >
            {exportingExcel ? (
              <Loader2 className="w-4 h-4 animate-spin text-emerald-200" />
            ) : (
              <FileSpreadsheet className="w-4 h-4 text-emerald-200" />
            )}
            <span>Generate Consolidated Excel Workbook (.xlsx)</span>
          </button>
        </div>
      </div>

      {/* Preview Section */}
      <div className="bg-white rounded-3xl border border-slate-200 shadow-xs overflow-hidden">
        <div className="p-5 border-b border-slate-100 flex items-center justify-between">
          <div>
            <h2 className="font-bold text-sm text-slate-900">
              Incidents Included in Dossier ({previewIncidents.length})
            </h2>
            <p className="text-xs text-slate-500">
              Site: <span className="font-semibold text-slate-800">{selectedSite?.name}</span> • Date: <span className="font-semibold text-slate-800">{reportDate}</span>
            </p>
          </div>
        </div>

        {loadingPreview ? (
          <div className="py-12 flex justify-center">
            <Loader2 className="w-6 h-6 animate-spin text-blue-600" />
          </div>
        ) : previewIncidents.length === 0 ? (
          <div className="p-8 text-center text-slate-400 text-xs">
            No security incidents were recorded for {selectedSite?.name} on {reportDate}.
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead className="bg-slate-50 text-slate-600 font-bold uppercase tracking-wider text-[10px] border-b border-slate-200">
                <tr>
                  <th className="px-5 py-3">Ref Number</th>
                  <th className="px-5 py-3">Time / Shift</th>
                  <th className="px-5 py-3">Classification</th>
                  <th className="px-5 py-3">Summary</th>
                  <th className="px-5 py-3">Reporter</th>
                  <th className="px-5 py-3">Photos</th>
                  <th className="px-5 py-3">Status</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 font-medium text-slate-700">
                {previewIncidents.map((inc) => (
                  <tr key={inc.id} className="hover:bg-slate-50">
                    <td className="px-5 py-3 font-mono font-bold text-blue-900">{inc.ref_number}</td>
                    <td className="px-5 py-3">{inc.incident_time} ({inc.shift})</td>
                    <td className="px-5 py-3 truncate max-w-[160px]">
                      {(inc.incident_types || []).join(', ')}
                    </td>
                    <td className="px-5 py-3 truncate max-w-[240px]">{inc.summary_line}</td>
                    <td className="px-5 py-3 truncate max-w-[140px]">{inc.reported_by_name}</td>
                    <td className="px-5 py-3 font-mono text-center">{inc.photos?.length || 0}</td>
                    <td className="px-5 py-3">
                      <span className="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800">
                        {inc.status}
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
};
