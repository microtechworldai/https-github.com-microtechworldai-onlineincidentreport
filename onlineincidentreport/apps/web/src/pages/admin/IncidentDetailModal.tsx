import React, { useState } from 'react';
import {
  X,
  Download,
  Copy,
  Trash2,
  Save,
  MessageSquare,
  Clock,
  History,
  Image as ImageIcon,
  CheckCircle,
  AlertCircle,
  FileText,
  Maximize2,
  Minimize2,
  Upload,
  Eye,
} from 'lucide-react';
import { INCIDENT_STATUSES, INCIDENT_PRIORITIES } from '@uss/shared';
import { adminApi } from '../../lib/api';
import { RichTextEditor } from '../../components/RichTextEditor';

interface IncidentDetailModalProps {
  incident: any;
  onClose: () => void;
  onUpdated: () => void;
}

export const IncidentDetailModal: React.FC<IncidentDetailModalProps> = ({
  incident,
  onClose,
  onUpdated,
}) => {
  const [activeTab, setActiveTab] = useState<'form' | 'photos' | 'signoff' | 'notes' | 'history'>('form');
  const [isFullscreen, setIsFullscreen] = useState(false);

  // Form edit states
  const [status, setStatus] = useState<string>(incident.status || 'Submitted');
  const [priority, setPriority] = useState<string>(incident.priority || 'Medium');
  const [summaryLine, setSummaryLine] = useState<string>(incident.summary_line || incident.summaryLine || '');
  const [description, setDescription] = useState<string>(incident.description || '');

  // Section 4.7 Sign-Off Fields (support both snake_case and camelCase)
  const [siteSupervisorName, setSiteSupervisorName] = useState<string>(
    incident.site_supervisor_name || incident.siteSupervisorName || ''
  );
  const [siteSupervisorId, setSiteSupervisorId] = useState<string>(
    incident.site_supervisor_id || incident.siteSupervisorId || ''
  );
  const [siteSupervisorDate, setSiteSupervisorDate] = useState<string>(
    incident.site_supervisor_date || incident.siteSupervisorDate || ''
  );
  const [siteSupervisorSignature, setSiteSupervisorSignature] = useState<string>(
    incident.site_supervisor_signature || incident.siteSupervisorSignature || incident.site_supervisor_signature_url || ''
  );

  const [recommendationActionTaken, setRecommendationActionTaken] = useState<string>(
    incident.recommendation_action_taken || incident.recommendationActionTaken || ''
  );

  const [operationsManagerName, setOperationsManagerName] = useState<string>(
    incident.operations_manager_name || incident.operationsManagerName || ''
  );
  const [operationsManagerDate, setOperationsManagerDate] = useState<string>(
    incident.operations_manager_date || incident.operationsManagerDate || ''
  );
  const [operationsManagerSignature, setOperationsManagerSignature] = useState<string>(
    incident.operations_manager_signature || incident.operationsManagerSignature || incident.operations_manager_signature_url || ''
  );

  const [clientManagerActionTaken, setClientManagerActionTaken] = useState<string>(
    incident.client_manager_action_taken || incident.clientManagerActionTaken || ''
  );
  const [clientManagerName, setClientManagerName] = useState<string>(
    incident.client_manager_name || incident.clientManagerName || ''
  );
  const [clientManagerDate, setClientManagerDate] = useState<string>(
    incident.client_manager_date || incident.clientManagerDate || ''
  );
  const [clientManagerSignature, setClientManagerSignature] = useState<string>(
    incident.client_manager_signature || incident.clientManagerSignature || incident.client_manager_signature_url || ''
  );

  // New Note
  const [newNote, setNewNote] = useState<string>('');
  const [savingNote, setSavingNote] = useState(false);

  // Saving state
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [successMsg, setSuccessMsg] = useState<string | null>(null);

  // Lightbox photo
  const [lightboxPhoto, setLightboxPhoto] = useState<any>(null);

  const handleSignatureUpload = (
    e: React.ChangeEvent<HTMLInputElement>,
    setter: (val: string) => void
  ) => {
    const file = e.target.files?.[0];
    if (!file) return;
    if (!file.type.startsWith('image/')) {
      alert('Please upload an image file (PNG, JPG, or JPEG).');
      return;
    }
    const reader = new FileReader();
    reader.onload = (event) => {
      const base64 = event.target?.result as string;
      if (base64) {
        setter(base64);
      }
    };
    reader.readAsDataURL(file);
  };

  const handleSave = async () => {
    setSaving(true);
    setError(null);
    setSuccessMsg(null);

    try {
      const payload = {
        status,
        priority,
        summaryLine,
        description,
        siteSupervisorName: siteSupervisorName || null,
        site_supervisor_name: siteSupervisorName || null,
        siteSupervisorId: siteSupervisorId || null,
        site_supervisor_id: siteSupervisorId || null,
        siteSupervisorDate: siteSupervisorDate || null,
        site_supervisor_date: siteSupervisorDate || null,
        siteSupervisorSignature: siteSupervisorSignature || null,
        site_supervisor_signature: siteSupervisorSignature || null,
        site_supervisor_signature_url: siteSupervisorSignature || null,
        recommendationActionTaken: recommendationActionTaken || null,
        recommendation_action_taken: recommendationActionTaken || null,
        operationsManagerName: operationsManagerName || null,
        operations_manager_name: operationsManagerName || null,
        operationsManagerDate: operationsManagerDate || null,
        operations_manager_date: operationsManagerDate || null,
        operationsManagerSignature: operationsManagerSignature || null,
        operations_manager_signature: operationsManagerSignature || null,
        operations_manager_signature_url: operationsManagerSignature || null,
        clientManagerActionTaken: clientManagerActionTaken || null,
        client_manager_action_taken: clientManagerActionTaken || null,
        clientManagerName: clientManagerName || null,
        client_manager_name: clientManagerName || null,
        clientManagerDate: clientManagerDate || null,
        client_manager_date: clientManagerDate || null,
        clientManagerSignature: clientManagerSignature || null,
        client_manager_signature: clientManagerSignature || null,
        client_manager_signature_url: clientManagerSignature || null,
      };

      await adminApi.updateIncident(incident.id, payload);

      // Mutate current incident in-memory so all tabs and views reflect changes immediately
      Object.assign(incident, payload, {
        summary_line: summaryLine,
        status,
        priority,
        description,
      });

      setSuccessMsg('Incident record and recommendations updated successfully.');
      onUpdated();
    } catch (err: any) {
      setError(err.message || 'Failed to update incident.');
    } finally {
      setSaving(false);
    }
  };

  const handleAddNote = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newNote.trim()) return;
    setSavingNote(true);

    try {
      await adminApi.addNote(incident.id, newNote.trim());
      setNewNote('');
      onUpdated();
    } catch (err: any) {
      alert(err.message || 'Failed to add note');
    } finally {
      setSavingNote(false);
    }
  };

  const handleDuplicate = async () => {
    if (!confirm('Duplicate this incident as a new report for today?')) return;
    try {
      const duplicated = await adminApi.duplicateIncident(incident.id);
      alert(`Created duplicate report: ${duplicated.ref_number}`);
      onUpdated();
      onClose();
    } catch (err: any) {
      alert(err.message || 'Failed to duplicate report');
    }
  };

  const handleDownloadPdf = async () => {
    try {
      const blob = await adminApi.exportSinglePdf(incident.id);
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `${incident.ref_number || 'Incident'}.pdf`;
      a.click();
    } catch {
      alert('Failed to download PDF');
    }
  };

  return (
    <div
      className={
        isFullscreen
          ? 'fixed inset-0 z-50 bg-black/80 flex flex-col p-0'
          : 'fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-2 sm:p-4 md:p-6 overflow-y-auto'
      }
    >
      <div
        className={
          isFullscreen
            ? 'bg-white w-screen h-screen flex flex-col overflow-hidden'
            : 'bg-white rounded-3xl shadow-2xl border border-slate-200 w-full max-w-5xl max-h-[92vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-150'
        }
      >
        {/* Modal Header */}
        <div className="bg-[#0F2042] px-6 py-4 text-white flex items-center justify-between shrink-0">
          <div className="flex items-center gap-3">
            <div className="p-2 bg-blue-600 rounded-xl">
              <FileText className="w-5 h-5 text-white" />
            </div>
            <div>
              <div className="flex items-center gap-2">
                <span className="font-mono text-base font-bold text-amber-300">
                  {incident.ref_number}
                </span>
                <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-white/20 text-slate-100">
                  {incident.site_name}
                </span>
              </div>
              <p className="text-xs text-slate-300">
                Logged: {incident.incident_date} at {incident.incident_time} ({incident.shift} Shift)
              </p>
            </div>
          </div>

          <div className="flex items-center gap-2">
            <button
              onClick={() => setIsFullscreen(!isFullscreen)}
              className="flex items-center gap-1.5 px-3 py-1.5 bg-white/10 hover:bg-white/20 text-white rounded-lg text-xs font-semibold transition-colors"
              title={isFullscreen ? 'Exit Full View' : 'Open in Full View'}
            >
              {isFullscreen ? <Minimize2 className="w-3.5 h-3.5" /> : <Maximize2 className="w-3.5 h-3.5" />}
              <span className="hidden sm:inline">{isFullscreen ? 'Standard View' : 'Full View'}</span>
            </button>

            <button
              onClick={handleDownloadPdf}
              className="flex items-center gap-1.5 px-3 py-1.5 bg-white/10 hover:bg-white/20 text-white rounded-lg text-xs font-semibold transition-colors"
              title="Download official PDF report"
            >
              <Download className="w-3.5 h-3.5" />
              <span className="hidden sm:inline">Export PDF</span>
            </button>

            <button
              onClick={handleDuplicate}
              className="flex items-center gap-1.5 px-3 py-1.5 bg-white/10 hover:bg-white/20 text-white rounded-lg text-xs font-semibold transition-colors"
              title="Duplicate as new draft"
            >
              <Copy className="w-3.5 h-3.5" />
              <span className="hidden sm:inline">Duplicate</span>
            </button>

            <button
              onClick={onClose}
              className="p-1.5 text-slate-400 hover:text-white rounded-lg hover:bg-white/10 transition-colors ml-2"
            >
              <X className="w-5 h-5" />
            </button>
          </div>
        </div>

        {/* Tab Navigation */}
        <div className="flex border-b border-slate-200 px-6 bg-slate-50 text-xs font-bold shrink-0">
          <button
            onClick={() => setActiveTab('form')}
            className={`py-3 px-4 border-b-2 transition-all ${
              activeTab === 'form'
                ? 'border-blue-600 text-blue-600 bg-white'
                : 'border-transparent text-slate-600 hover:text-slate-900'
            }`}
          >
            Form Details & Status
          </button>
          <button
            onClick={() => setActiveTab('photos')}
            className={`py-3 px-4 border-b-2 transition-all flex items-center gap-1.5 ${
              activeTab === 'photos'
                ? 'border-blue-600 text-blue-600 bg-white'
                : 'border-transparent text-slate-600 hover:text-slate-900'
            }`}
          >
            <ImageIcon className="w-3.5 h-3.5" />
            <span>Photos ({incident.photos?.length || 0})</span>
          </button>
          <button
            onClick={() => setActiveTab('signoff')}
            className={`py-3 px-4 border-b-2 transition-all ${
              activeTab === 'signoff'
                ? 'border-blue-600 text-blue-600 bg-white'
                : 'border-transparent text-slate-600 hover:text-slate-900'
            }`}
          >
            Management Sign-Off (Section 4.7)
          </button>
          <button
            onClick={() => setActiveTab('notes')}
            className={`py-3 px-4 border-b-2 transition-all flex items-center gap-1.5 ${
              activeTab === 'notes'
                ? 'border-blue-600 text-blue-600 bg-white'
                : 'border-transparent text-slate-600 hover:text-slate-900'
            }`}
          >
            <MessageSquare className="w-3.5 h-3.5" />
            <span>Follow-up Notes ({incident.notes?.length || 0})</span>
          </button>
          <button
            onClick={() => setActiveTab('history')}
            className={`py-3 px-4 border-b-2 transition-all flex items-center gap-1.5 ${
              activeTab === 'history'
                ? 'border-blue-600 text-blue-600 bg-white'
                : 'border-transparent text-slate-600 hover:text-slate-900'
            }`}
          >
            <History className="w-3.5 h-3.5" />
            <span>Versions ({incident.versions?.length || 0})</span>
          </button>
        </div>

        {/* Modal Body */}
        <div className="flex-1 p-6 overflow-y-auto space-y-5 text-xs">
          {error && (
            <div className="p-3 bg-red-50 text-red-700 rounded-xl border border-red-200 flex items-center gap-2">
              <AlertCircle className="w-4 h-4 shrink-0" />
              <span>{error}</span>
            </div>
          )}

          {successMsg && (
            <div className="p-3 bg-emerald-50 text-emerald-700 rounded-xl border border-emerald-200 flex items-center gap-2">
              <CheckCircle className="w-4 h-4 shrink-0" />
              <span>{successMsg}</span>
            </div>
          )}

          {/* TAB 1: FORM DETAILS */}
          {activeTab === 'form' && (
            <div className="space-y-4">
              {/* Status & Priority Controls Grid */}
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {/* Status Workflow Selector */}
                <div className="bg-slate-50 p-4 rounded-2xl border border-slate-200 flex flex-col justify-between gap-3">
                  <div>
                    <span className="text-xs font-black text-slate-700 uppercase tracking-wider block">
                      Incident Status Workflow
                    </span>
                    <p className="text-xs text-slate-500 font-medium mt-0.5">
                      Transition report through review states
                    </p>
                  </div>

                  <select
                    value={status}
                    onChange={(e) => setStatus(e.target.value)}
                    className="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl font-bold text-sm text-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-600 shadow-2xs"
                  >
                    {INCIDENT_STATUSES.map((st) => (
                      <option key={st} value={st}>
                        {st}
                      </option>
                    ))}
                  </select>
                </div>

                {/* Priority Level Selector */}
                <div className="bg-slate-50 p-4 rounded-2xl border border-slate-200 flex flex-col justify-between gap-3">
                  <div>
                    <div className="flex items-center justify-between">
                      <span className="text-xs font-black text-slate-700 uppercase tracking-wider block">
                        Triage Priority Level
                      </span>
                      <span
                        className={`text-[10px] px-2 py-0.5 rounded-full font-extrabold uppercase ${
                          priority === 'Critical'
                            ? 'bg-rose-100 text-rose-800'
                            : priority === 'High'
                            ? 'bg-amber-100 text-amber-800'
                            : priority === 'Medium'
                            ? 'bg-blue-100 text-blue-800'
                            : 'bg-slate-100 text-slate-700'
                        }`}
                      >
                        {priority}
                      </span>
                    </div>
                    <p className="text-xs text-slate-500 font-medium mt-0.5">
                      Severity ranking (Critical, High, Medium, Low)
                    </p>
                  </div>

                  <select
                    value={priority}
                    onChange={(e) => setPriority(e.target.value)}
                    className={`w-full px-3.5 py-2 bg-white border rounded-xl font-extrabold text-sm focus:outline-none focus:ring-2 focus:ring-blue-600 shadow-2xs ${
                      priority === 'Critical'
                        ? 'text-rose-700 border-rose-300'
                        : priority === 'High'
                        ? 'text-amber-700 border-amber-300'
                        : priority === 'Medium'
                        ? 'text-blue-700 border-blue-300'
                        : 'text-slate-700 border-slate-300'
                    }`}
                  >
                    {INCIDENT_PRIORITIES.map((p) => (
                      <option key={p} value={p}>
                        {p} Priority
                      </option>
                    ))}
                  </select>
                </div>
              </div>

              {/* Classification Info */}
              <div className="space-y-2.5 bg-slate-50 p-4 rounded-2xl border border-slate-200">
                <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                  <div>
                    <span className="text-xs font-black text-slate-500 uppercase tracking-wide">Cause (Section 4.2)</span>
                    <p className="font-bold text-sm sm:text-base text-slate-900 mt-0.5">
                      {(incident.cause_main || []).join(', ') || 'N/A'}
                    </p>
                  </div>
                  <div>
                    <span className="text-xs font-black text-slate-500 uppercase tracking-wide">Incident Type (Section 4.3)</span>
                    <p className="font-bold text-sm sm:text-base text-slate-900 mt-0.5">
                      {(incident.incident_types || []).join(', ') || 'N/A'}
                    </p>
                    {incident.incident_other_text && (
                      <p className="text-xs text-blue-700 italic mt-0.5">Other: {incident.incident_other_text}</p>
                    )}
                  </div>
                  <div>
                    <span className="text-xs font-black text-slate-500 uppercase tracking-wide">Notified (Section 4.5)</span>
                    <p className="font-bold text-sm sm:text-base text-slate-900 mt-0.5">
                      {(incident.who_notified || []).join(', ') || 'None'}
                    </p>
                    {incident.notified_other_text && (
                      <p className="text-xs text-blue-700 italic mt-0.5">Other: {incident.notified_other_text}</p>
                    )}
                  </div>
                  <div>
                    <span className="text-xs font-black text-slate-500 uppercase tracking-wide">Reporter</span>
                    <p className="font-bold text-sm sm:text-base text-slate-900 mt-0.5">
                      {incident.reported_by_name} ({incident.reported_by_designation})
                    </p>
                  </div>
                </div>

                {/* Disciplinary Reasons if applicable */}
                {((incident.disciplinary_reasons && incident.disciplinary_reasons.length > 0) || incident.disciplinary_other_text) && (
                  <div className="pt-2 border-t border-slate-200 flex items-start gap-2 text-xs">
                    <span className="font-extrabold text-amber-900 uppercase">Disciplinary Reasons (Section 4.2):</span>
                    <span className="font-bold text-slate-800">
                      {(incident.disciplinary_reasons || []).join(', ')}
                      {incident.disciplinary_other_text ? ` (${incident.disciplinary_other_text})` : ''}
                    </span>
                  </div>
                )}
              </div>

              {/* Editable Summary Line */}
              <div>
                <label className="block text-sm font-black text-slate-900 uppercase mb-1.5">
                  Summary Line (What, Where, When, Who, Why, How)
                </label>
                <input
                  type="text"
                  value={summaryLine}
                  onChange={(e) => setSummaryLine(e.target.value)}
                  className="w-full text-sm font-semibold p-3.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 focus:outline-none text-slate-900 bg-white"
                />
              </div>

              {/* Editable Description */}
              <div>
                <label className="block text-sm font-black text-slate-900 uppercase mb-1.5">
                  Description of Incident (Detailed Narrative)
                </label>
                <RichTextEditor
                  value={description}
                  onChange={(html) => setDescription(html)}
                  placeholder="Detailed narrative description of the incident..."
                />
              </div>
            </div>
          )}

          {/* TAB 2: ATTACHED PHOTOS */}
          {activeTab === 'photos' && (
            <div className="space-y-4">
              {incident.photos && incident.photos.length > 0 ? (
                <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
                  {incident.photos.map((p: any, idx: number) => (
                    <div
                      key={p.id}
                      onClick={() => setLightboxPhoto(p)}
                      className="group cursor-pointer bg-slate-50 border border-slate-200 rounded-2xl overflow-hidden p-2 space-y-2 hover:shadow-md transition-all"
                    >
                      <div className="relative aspect-video rounded-xl overflow-hidden bg-slate-200">
                        <img
                          src={p.url || `/uploads/${p.storage_key}`}
                          alt={p.original_filename}
                          className="w-full h-full object-cover group-hover:scale-105 transition-transform"
                        />
                        <div className="absolute bottom-1 right-1 bg-black/60 text-white text-[10px] px-1.5 py-0.5 rounded font-mono">
                          Photo #{idx + 1}
                        </div>
                      </div>
                      <div className="px-1">
                        <p className="font-bold text-slate-800 truncate text-xs">
                          {p.original_filename}
                        </p>
                        <p className="text-[11px] text-slate-500 truncate mt-0.5">
                          {p.caption || 'No caption'}
                        </p>
                      </div>
                    </div>
                  ))}
                </div>
              ) : (
                <div className="text-center py-12 text-slate-400">
                  <ImageIcon className="w-10 h-10 mx-auto mb-2 opacity-50" />
                  <p>No photographs attached to this incident report.</p>
                </div>
              )}
            </div>
          )}

          {/* TAB 3: MANAGEMENT SIGN-OFF (SECTION 4.7) */}
          {activeTab === 'signoff' && (
            <div className="space-y-5">
              <p className="text-xs text-slate-600 font-medium">
                Complete official management sign-offs and operational recommendations to be included in the client PDF report.
              </p>

              {/* Site Supervisor / Security Review */}
              <div className="bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-3.5">
                <span className="font-black text-sm uppercase text-slate-800 block">
                  Site Supervisor / Security Review
                </span>
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                  <div>
                    <label className="text-xs text-slate-700 font-bold block mb-1">Supervisor Name</label>
                    <input
                      type="text"
                      placeholder="Supervisor Full Name"
                      value={siteSupervisorName}
                      onChange={(e) => setSiteSupervisorName(e.target.value)}
                      className="w-full text-sm font-medium p-2.5 bg-white border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 focus:outline-none"
                    />
                  </div>
                  <div>
                    <label className="text-xs text-slate-700 font-bold block mb-1">ID No.</label>
                    <input
                      type="text"
                      placeholder="e.g. USS-SUP-101"
                      value={siteSupervisorId}
                      onChange={(e) => setSiteSupervisorId(e.target.value)}
                      className="w-full text-sm font-medium p-2.5 bg-white border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 focus:outline-none"
                    />
                  </div>
                  <div>
                    <label className="text-xs text-slate-700 font-bold block mb-1">Date</label>
                    <input
                      type="date"
                      value={siteSupervisorDate}
                      onChange={(e) => setSiteSupervisorDate(e.target.value)}
                      className="w-full text-sm font-medium p-2.5 bg-white border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 focus:outline-none"
                    />
                  </div>
                </div>

                {/* Attached Security Signature Section */}
                <div className="pt-3 border-t border-slate-200">
                  <label className="text-xs font-bold text-slate-700 uppercase flex items-center justify-between mb-2">
                    <span>Attached Security / Supervisor Signature:</span>
                    <span className="text-[11px] text-slate-500 font-normal">Click image to inspect in full view</span>
                  </label>

                  {siteSupervisorSignature && siteSupervisorSignature !== 'Signed' ? (
                    <div className="flex flex-wrap items-center gap-3 p-3 bg-white border border-slate-200 rounded-xl shadow-xs">
                      <div
                        onClick={() =>
                          setLightboxPhoto({
                            url: siteSupervisorSignature,
                            original_filename: 'Security_Supervisor_Signature.jpg',
                            caption: `Attached Security Signature - ${siteSupervisorName || 'Officer'}`,
                          })
                        }
                        className="cursor-pointer group relative bg-slate-50 border border-slate-300 rounded-lg p-1.5 shrink-0 hover:border-blue-500 hover:shadow transition-all"
                        title="Click to open signature in Full View"
                      >
                        <img
                          src={siteSupervisorSignature}
                          alt="Security Signature"
                          className="h-12 max-w-[160px] object-contain"
                        />
                        <div className="absolute inset-0 bg-blue-900/30 opacity-0 group-hover:opacity-100 flex items-center justify-center rounded-lg transition-opacity">
                          <Maximize2 className="w-5 h-5 text-white drop-shadow" />
                        </div>
                      </div>

                      <div className="flex-1 min-w-[180px]">
                        <button
                          type="button"
                          onClick={() =>
                            setLightboxPhoto({
                              url: siteSupervisorSignature,
                              original_filename: 'Security_Supervisor_Signature.jpg',
                              caption: `Attached Security Signature - ${siteSupervisorName || 'Officer'}`,
                            })
                          }
                          className="text-xs font-bold text-blue-700 hover:text-blue-900 flex items-center gap-1.5"
                        >
                          <Maximize2 className="w-3.5 h-3.5" />
                          <span>Open in Full View</span>
                        </button>
                        <p className="text-[11px] text-slate-500 mt-0.5">
                          Verified & attached to official PDF supervisor row
                        </p>
                      </div>

                      <div className="flex items-center gap-2">
                        <label className="flex items-center gap-1 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-bold cursor-pointer transition-colors">
                          <Upload className="w-3.5 h-3.5 text-slate-600" />
                          <span>Change</span>
                          <input
                            type="file"
                            accept="image/*"
                            onChange={(e) => handleSignatureUpload(e, setSiteSupervisorSignature)}
                            className="hidden"
                          />
                        </label>
                        <button
                          type="button"
                          onClick={() => setSiteSupervisorSignature('')}
                          className="px-2.5 py-1.5 bg-red-50 hover:bg-red-100 text-red-600 rounded-lg text-xs font-bold transition-colors"
                        >
                          Remove
                        </button>
                      </div>
                    </div>
                  ) : (
                    <div className="flex flex-wrap items-center gap-2">
                      <label className="flex items-center gap-1.5 px-3.5 py-2 bg-blue-50 hover:bg-blue-100 text-blue-800 border border-blue-200 rounded-xl text-xs font-bold cursor-pointer transition-all">
                        <Upload className="w-4 h-4 text-blue-600" />
                        <span>Upload Signature Image</span>
                        <input
                          type="file"
                          accept="image/*"
                          onChange={(e) => handleSignatureUpload(e, setSiteSupervisorSignature)}
                          className="hidden"
                        />
                      </label>
                      <button
                        type="button"
                        onClick={() => setSiteSupervisorSignature(siteSupervisorSignature === 'Signed' ? '' : 'Signed')}
                        className={`px-3 py-2 rounded-xl text-xs font-bold border transition-all ${
                          siteSupervisorSignature === 'Signed'
                            ? 'bg-emerald-50 text-emerald-800 border-emerald-300 font-extrabold'
                            : 'bg-slate-50 text-slate-700 border-slate-300 hover:bg-slate-100'
                        }`}
                      >
                        {siteSupervisorSignature === 'Signed' ? '✓ Marked as Signed' : 'Mark as Signed (On-site)'}
                      </button>
                    </div>
                  )}
                </div>
              </div>

              {/* RECOMMENDATION / ACTION TAKEN */}
              <div className="space-y-1.5 bg-blue-50/40 p-4 rounded-2xl border border-blue-100">
                <label className="block text-sm font-black uppercase text-blue-950 flex items-center justify-between">
                  <span>RECOMMENDATION / ACTION TAKEN</span>
                  <span className="text-[11px] font-semibold text-blue-600 lowercase">(saves to official report & PDF)</span>
                </label>
                <textarea
                  rows={4}
                  placeholder="Official operational recommendation or preventative measure..."
                  value={recommendationActionTaken}
                  onChange={(e) => setRecommendationActionTaken(e.target.value)}
                  className="w-full text-sm font-medium leading-relaxed p-3.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 focus:outline-none bg-white text-slate-900 shadow-2xs"
                ></textarea>
              </div>

              {/* Operations Manager Row */}
              <div className="bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-3.5">
                <span className="font-black text-sm uppercase text-slate-800 block">
                  Operations Manager Endorsement
                </span>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div>
                    <label className="text-xs text-slate-700 font-bold block mb-1">Manager Name</label>
                    <input
                      type="text"
                      placeholder="e.g. USS Operations Director"
                      value={operationsManagerName}
                      onChange={(e) => setOperationsManagerName(e.target.value)}
                      className="w-full text-sm font-medium p-2.5 bg-white border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 focus:outline-none"
                    />
                  </div>
                  <div>
                    <label className="text-xs text-slate-700 font-bold block mb-1">Date</label>
                    <input
                      type="date"
                      value={operationsManagerDate}
                      onChange={(e) => setOperationsManagerDate(e.target.value)}
                      className="w-full text-sm font-medium p-2.5 bg-white border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 focus:outline-none"
                    />
                  </div>
                </div>

                {/* Operations Manager Signature */}
                <div className="pt-2 border-t border-slate-200">
                  <label className="text-xs font-bold text-slate-700 uppercase flex items-center justify-between mb-2">
                    <span>Operations Manager Signature:</span>
                  </label>
                  {operationsManagerSignature && operationsManagerSignature !== 'Signed' ? (
                    <div className="flex items-center gap-3 p-2.5 bg-white border border-slate-200 rounded-xl">
                      <div
                        onClick={() =>
                          setLightboxPhoto({
                            url: operationsManagerSignature,
                            original_filename: 'Operations_Manager_Signature.jpg',
                            caption: `Operations Manager Signature - ${operationsManagerName || 'Director'}`,
                          })
                        }
                        className="cursor-pointer group relative bg-slate-50 border border-slate-300 rounded-lg p-1 shrink-0 hover:border-blue-500"
                        title="Click to open signature in Full View"
                      >
                        <img
                          src={operationsManagerSignature}
                          alt="OM Signature"
                          className="h-10 max-w-[140px] object-contain"
                        />
                        <div className="absolute inset-0 bg-blue-900/30 opacity-0 group-hover:opacity-100 flex items-center justify-center rounded-lg transition-opacity">
                          <Maximize2 className="w-4 h-4 text-white drop-shadow" />
                        </div>
                      </div>
                      <div className="flex-1">
                        <button
                          type="button"
                          onClick={() =>
                            setLightboxPhoto({
                              url: operationsManagerSignature,
                              original_filename: 'Operations_Manager_Signature.jpg',
                              caption: `Operations Manager Signature - ${operationsManagerName || 'Director'}`,
                            })
                          }
                          className="text-xs font-bold text-blue-700 hover:text-blue-900 flex items-center gap-1"
                        >
                          <Maximize2 className="w-3.5 h-3.5" />
                          <span>Open in Full View</span>
                        </button>
                      </div>
                      <div className="flex items-center gap-1.5">
                        <label className="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-bold cursor-pointer">
                          <span>Change</span>
                          <input
                            type="file"
                            accept="image/*"
                            onChange={(e) => handleSignatureUpload(e, setOperationsManagerSignature)}
                            className="hidden"
                          />
                        </label>
                        <button
                          type="button"
                          onClick={() => setOperationsManagerSignature('')}
                          className="px-2 py-1.5 bg-red-50 hover:bg-red-100 text-red-600 rounded-lg text-xs font-bold"
                        >
                          Remove
                        </button>
                      </div>
                    </div>
                  ) : (
                    <div className="flex items-center gap-2">
                      <label className="flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-800 border border-slate-300 rounded-xl text-xs font-bold cursor-pointer">
                        <Upload className="w-3.5 h-3.5 text-slate-600" />
                        <span>Upload Signature Image</span>
                        <input
                          type="file"
                          accept="image/*"
                          onChange={(e) => handleSignatureUpload(e, setOperationsManagerSignature)}
                          className="hidden"
                        />
                      </label>
                      <button
                        type="button"
                        onClick={() =>
                          setOperationsManagerSignature(operationsManagerSignature === 'Signed' ? '' : 'Signed')
                        }
                        className={`px-3 py-1.5 rounded-xl text-xs font-bold border ${
                          operationsManagerSignature === 'Signed'
                            ? 'bg-emerald-50 text-emerald-800 border-emerald-300 font-extrabold'
                            : 'bg-slate-50 text-slate-700 border-slate-300 hover:bg-slate-100'
                        }`}
                      >
                        {operationsManagerSignature === 'Signed' ? '✓ Marked as Signed' : 'Mark Signed'}
                      </button>
                    </div>
                  )}
                </div>
              </div>

              {/* ACTION TAKEN BY CLIENT MANAGER */}
              <div className="space-y-1.5 bg-emerald-50/40 p-4 rounded-2xl border border-emerald-100">
                <label className="block text-sm font-black uppercase text-emerald-950 flex items-center justify-between">
                  <span>ACTION TAKEN BY CLIENT MANAGER</span>
                  <span className="text-[11px] font-semibold text-emerald-700 lowercase">(saves to official report & PDF)</span>
                </label>
                <textarea
                  rows={4}
                  placeholder="Client response, facilities work order numbers, building management feedback..."
                  value={clientManagerActionTaken}
                  onChange={(e) => setClientManagerActionTaken(e.target.value)}
                  className="w-full text-sm font-medium leading-relaxed p-3.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-emerald-600 focus:outline-none bg-white text-slate-900 shadow-2xs"
                ></textarea>
              </div>

              {/* Client Manager Row */}
              <div className="bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-3.5">
                <span className="font-black text-sm uppercase text-slate-800 block">
                  Client Manager Acknowledgment
                </span>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div>
                    <label className="text-xs text-slate-700 font-bold block mb-1">Client Contact Name</label>
                    <input
                      type="text"
                      placeholder="Client Representative Name"
                      value={clientManagerName}
                      onChange={(e) => setClientManagerName(e.target.value)}
                      className="w-full text-sm font-medium p-2.5 bg-white border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 focus:outline-none"
                    />
                  </div>
                  <div>
                    <label className="text-xs text-slate-700 font-bold block mb-1">Date</label>
                    <input
                      type="date"
                      value={clientManagerDate}
                      onChange={(e) => setClientManagerDate(e.target.value)}
                      className="w-full text-sm font-medium p-2.5 bg-white border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 focus:outline-none"
                    />
                  </div>
                </div>

                {/* Client Manager Signature */}
                <div className="pt-2 border-t border-slate-200">
                  <label className="text-xs font-bold text-slate-700 uppercase flex items-center justify-between mb-2">
                    <span>Client Manager Signature:</span>
                  </label>
                  {clientManagerSignature && clientManagerSignature !== 'Signed' ? (
                    <div className="flex items-center gap-3 p-2.5 bg-white border border-slate-200 rounded-xl">
                      <div
                        onClick={() =>
                          setLightboxPhoto({
                            url: clientManagerSignature,
                            original_filename: 'Client_Manager_Signature.jpg',
                            caption: `Client Manager Signature - ${clientManagerName || 'Representative'}`,
                          })
                        }
                        className="cursor-pointer group relative bg-slate-50 border border-slate-300 rounded-lg p-1 shrink-0 hover:border-blue-500"
                        title="Click to open signature in Full View"
                      >
                        <img
                          src={clientManagerSignature}
                          alt="Client Signature"
                          className="h-10 max-w-[140px] object-contain"
                        />
                        <div className="absolute inset-0 bg-blue-900/30 opacity-0 group-hover:opacity-100 flex items-center justify-center rounded-lg transition-opacity">
                          <Maximize2 className="w-4 h-4 text-white drop-shadow" />
                        </div>
                      </div>
                      <div className="flex-1">
                        <button
                          type="button"
                          onClick={() =>
                            setLightboxPhoto({
                              url: clientManagerSignature,
                              original_filename: 'Client_Manager_Signature.jpg',
                              caption: `Client Manager Signature - ${clientManagerName || 'Representative'}`,
                            })
                          }
                          className="text-xs font-bold text-blue-700 hover:text-blue-900 flex items-center gap-1"
                        >
                          <Maximize2 className="w-3.5 h-3.5" />
                          <span>Open in Full View</span>
                        </button>
                      </div>
                      <div className="flex items-center gap-1.5">
                        <label className="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-bold cursor-pointer">
                          <span>Change</span>
                          <input
                            type="file"
                            accept="image/*"
                            onChange={(e) => handleSignatureUpload(e, setClientManagerSignature)}
                            className="hidden"
                          />
                        </label>
                        <button
                          type="button"
                          onClick={() => setClientManagerSignature('')}
                          className="px-2 py-1.5 bg-red-50 hover:bg-red-100 text-red-600 rounded-lg text-xs font-bold"
                        >
                          Remove
                        </button>
                      </div>
                    </div>
                  ) : (
                    <div className="flex items-center gap-2">
                      <label className="flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-800 border border-slate-300 rounded-xl text-xs font-bold cursor-pointer">
                        <Upload className="w-3.5 h-3.5 text-slate-600" />
                        <span>Upload Signature Image</span>
                        <input
                          type="file"
                          accept="image/*"
                          onChange={(e) => handleSignatureUpload(e, setClientManagerSignature)}
                          className="hidden"
                        />
                      </label>
                      <button
                        type="button"
                        onClick={() =>
                          setClientManagerSignature(clientManagerSignature === 'Signed' ? '' : 'Signed')
                        }
                        className={`px-3 py-1.5 rounded-xl text-xs font-bold border ${
                          clientManagerSignature === 'Signed'
                            ? 'bg-emerald-50 text-emerald-800 border-emerald-300 font-extrabold'
                            : 'bg-slate-50 text-slate-700 border-slate-300 hover:bg-slate-100'
                        }`}
                      >
                        {clientManagerSignature === 'Signed' ? '✓ Marked as Signed' : 'Mark Signed'}
                      </button>
                    </div>
                  )}
                </div>
              </div>
            </div>
          )}

          {/* TAB 4: FOLLOW-UP NOTES */}
          {activeTab === 'notes' && (
            <div className="space-y-4">
              <form onSubmit={handleAddNote} className="space-y-2">
                <label className="block text-xs font-bold text-slate-700 uppercase">
                  Add Follow-Up Note
                </label>
                <div className="flex gap-2">
                  <input
                    type="text"
                    required
                    placeholder="Enter timestamped follow-up note..."
                    value={newNote}
                    onChange={(e) => setNewNote(e.target.value)}
                    className="flex-1 text-xs p-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 focus:outline-none"
                  />
                  <button
                    type="submit"
                    disabled={savingNote}
                    className="px-4 bg-blue-600 text-white font-bold rounded-xl text-xs hover:bg-blue-700 disabled:opacity-50"
                  >
                    Add Note
                  </button>
                </div>
              </form>

              <div className="space-y-2 pt-2">
                {(incident.notes || []).map((n: any) => (
                  <div key={n.id} className="p-3 bg-slate-50 rounded-xl border border-slate-200 space-y-1">
                    <div className="flex items-center justify-between text-[11px]">
                      <span className="font-bold text-slate-800">{n.admin_name || 'Admin'}</span>
                      <span className="text-slate-400 font-mono">
                        {new Date(n.created_at).toLocaleString('en-GB', { timeZone: 'Asia/Dubai' })}
                      </span>
                    </div>
                    <p className="text-xs text-slate-700">{n.note}</p>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* TAB 5: VERSION HISTORY */}
          {activeTab === 'history' && (
            <div className="space-y-3">
              {(incident.versions || []).map((v: any) => (
                <div key={v.id} className="p-3 bg-slate-50 rounded-xl border border-slate-200 space-y-1">
                  <div className="flex items-center justify-between text-[11px]">
                    <span className="font-bold text-blue-900">{v.admin_name || 'Admin'}</span>
                    <span className="text-slate-400 font-mono">
                      {new Date(v.created_at).toLocaleString('en-GB', { timeZone: 'Asia/Dubai' })}
                    </span>
                  </div>
                  <p className="text-xs text-slate-600">{v.changes_summary}</p>
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Modal Footer with Save Button */}
        <div className="px-6 py-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between shrink-0">
          <div className="text-[11px] text-slate-400 font-mono">
            ID: {incident.id}
          </div>

          <div className="flex items-center gap-3">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 border border-slate-300 text-slate-700 hover:bg-slate-100 rounded-xl text-xs font-semibold"
            >
              Close
            </button>
            <button
              type="button"
              onClick={handleSave}
              disabled={saving}
              className="flex items-center gap-2 px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-sm transition-all disabled:opacity-50"
            >
              <Save className="w-4 h-4" />
              <span>{saving ? 'Saving...' : 'Save Changes'}</span>
            </button>
          </div>
        </div>
      </div>

      {/* Photo Lightbox */}
      {lightboxPhoto && (
        <div
          onClick={() => setLightboxPhoto(null)}
          className="fixed inset-0 z-60 bg-black/90 flex flex-col items-center justify-center p-4 cursor-pointer"
        >
          <img
            src={lightboxPhoto.url || `/uploads/${lightboxPhoto.storage_key}`}
            alt={lightboxPhoto.original_filename}
            className="max-w-full max-h-[85vh] object-contain rounded-xl shadow-2xl"
          />
          <p className="text-white text-xs mt-3 text-center">
            {lightboxPhoto.original_filename} • {lightboxPhoto.caption || 'No caption'}
          </p>
        </div>
      )}
    </div>
  );
};
