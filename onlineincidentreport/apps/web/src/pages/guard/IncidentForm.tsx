import React, { useState, useEffect } from 'react';
import { useNavigate, useOutletContext } from 'react-router-dom';
import {
  ShieldAlert,
  Send,
  Loader2,
  CheckCircle,
  AlertCircle,
  Clock,
  MapPin,
  Calendar,
  Save,
  LogOut,
  Shield,
  FileText,
  UserCheck,
  CheckSquare,
  Square,
  Building2,
  Phone,
  Mail,
  HelpCircle,
  Upload,
  X,
} from 'lucide-react';
import {
  DEFAULT_CAUSES,
  DEFAULT_DISCIPLINARY,
  DEFAULT_INCIDENTS,
  DEFAULT_NOTIFIED,
  INCIDENT_PRIORITIES,
} from '@uss/shared';
import { PhotoUploader, PhotoItem } from '../../components/PhotoUploader';
import { RichTextEditor } from '../../components/RichTextEditor';
import { useOfflineDraft } from '../../hooks/useOfflineDraft';
import { guardApi } from '../../lib/api';

interface IncidentFormState {
  priority: 'Critical' | 'High' | 'Medium' | 'Low';
  incidentDate: string;
  incidentTime: string;
  shift: string;
  causeMain: string[];
  disciplinaryReasons: string[];
  disciplinaryOtherText: string;
  incidentTypes: string[];
  incidentOtherText: string;
  summaryLine: string;
  description: string;
  whoNotified: string[];
  notifiedOtherText: string;
  siteSupervisorName: string;
  siteSupervisorId: string;
  siteSupervisorDate: string;
  siteSupervisorSignature: string;
  photos: PhotoItem[];
}

export const IncidentForm: React.FC = () => {
  const navigate = useNavigate();
  const { profile, isOnline } = useOutletContext<{ profile: any; isOnline: boolean }>();

  // Dynamic system settings (fallback to official defaults)
  const [settings, setSettings] = useState<any>(profile?.settings || null);

  useEffect(() => {
    guardApi
      .getSettings()
      .then((res) => {
        if (res) setSettings(res);
      })
      .catch((err) => {
        console.warn('[GUARD_FORM] Using fallback profile settings:', err.message);
      });
  }, []);

  const disciplinaryOptions: string[] =
    settings?.disciplinaryOptions?.length > 0
      ? settings.disciplinaryOptions
      : DEFAULT_DISCIPLINARY;

  const incidentOptions: string[] =
    settings?.incidentOptions?.length > 0
      ? settings.incidentOptions
      : DEFAULT_INCIDENTS;

  const notifiedOptions: string[] =
    settings?.notifiedOptions?.length > 0
      ? settings.notifiedOptions
      : DEFAULT_NOTIFIED;

  // Dubai Date & Time Helpers
  const createFreshDraft = (): IncidentFormState => {
    const todayDubai = new Date().toISOString().slice(0, 10);
    const nowDubaiTime = new Date().toTimeString().slice(0, 5);
    return {
      priority: 'Medium',
      incidentDate: todayDubai,
      incidentTime: nowDubaiTime,
      shift: 'Day',
      causeMain: ['Incident'],
      disciplinaryReasons: [],
      disciplinaryOtherText: '',
      incidentTypes: [],
      incidentOtherText: '',
      summaryLine: '',
      description: '',
      whoNotified: [],
      notifiedOtherText: '',
      siteSupervisorName: '',
      siteSupervisorId: '',
      siteSupervisorDate: todayDubai,
      siteSupervisorSignature: '',
      photos: [],
    };
  };

  const draftKey = `uss_draft_${profile?.site?.id || 'site'}_${profile?.guard?.id || 'guard'}`;
  const { data: form, setData: setForm, lastSaved, clearDraft } = useOfflineDraft<IncidentFormState>(
    draftKey,
    createFreshDraft
  );

  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Toggle helpers
  const toggleArrayItem = (
    field: 'causeMain' | 'disciplinaryReasons' | 'incidentTypes' | 'whoNotified',
    item: string
  ) => {
    setForm((prev) => {
      const current = prev[field] || [];
      const updated = current.includes(item)
        ? current.filter((x) => x !== item)
        : [...current, item];

      // Auto update causeMain based on selections
      let causes = [...(prev.causeMain || [])];
      if (field === 'disciplinaryReasons') {
        if (updated.length > 0 && !causes.includes('Disciplinary')) {
          causes.push('Disciplinary');
        } else if (updated.length === 0 && causes.includes('Disciplinary')) {
          causes = causes.filter((c) => c !== 'Disciplinary');
        }
      }
      if (field === 'incidentTypes') {
        if (updated.length > 0 && !causes.includes('Incident')) {
          causes.push('Incident');
        } else if (updated.length === 0 && causes.includes('Incident')) {
          causes = causes.filter((c) => c !== 'Incident');
        }
      }

      return { ...prev, [field]: updated, causeMain: causes.length > 0 ? causes : ['Incident'] };
    });
  };

  const handleSignatureUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    if (!file.type.startsWith('image/')) {
      alert('Please upload an image file (PNG, JPG, or JPEG) for the signature.');
      return;
    }
    const reader = new FileReader();
    reader.onload = (event) => {
      const base64 = event.target?.result as string;
      if (base64) {
        setForm((prev) => ({
          ...prev,
          siteSupervisorSignature: base64,
        }));
      }
    };
    reader.readAsDataURL(file);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);

    // Validation
    if (!profile?.site?.id) {
      setError('Missing site information. Please re-login.');
      return;
    }
    if (form.disciplinaryReasons.length === 0 && form.incidentTypes.length === 0) {
      setError('Please select at least one Disciplinary reason or Incident classification.');
      return;
    }
    if (!form.summaryLine.trim()) {
      setError('Please provide a summary line: Explain What, Where, When, Who, Why and How action taken.');
      return;
    }
    const plainDesc = form.description.replace(/<[^>]+>/g, '').trim();
    if (plainDesc.length < 20) {
      setError('Please provide a thorough incident description (minimum 20 characters).');
      return;
    }

    setSubmitting(true);

    // Guaranteed fresh unique idempotency key for this exact submission attempt
    const uniqueSubmitKey = `${Date.now()}_${Math.random().toString(36).substring(2, 10)}_${profile.guard?.staffId || 'g'}`;

    try {
      const computedCauses = [...(form.causeMain || [])];
      if (form.disciplinaryReasons.length > 0 && !computedCauses.includes('Disciplinary')) {
        computedCauses.push('Disciplinary');
      }
      if (form.incidentTypes.length > 0 && !computedCauses.includes('Incident')) {
        computedCauses.push('Incident');
      }

      const payload = {
        idempotencyKey: uniqueSubmitKey,
        siteId: profile.site.id,
        priority: form.priority,
        incidentDate: form.incidentDate,
        incidentTime: form.incidentTime,
        shift: form.shift,
        causeMain: computedCauses.length > 0 ? computedCauses : ['Incident'],
        disciplinaryReasons: form.disciplinaryReasons,
        disciplinaryOtherText: form.disciplinaryOtherText || null,
        incidentTypes: form.incidentTypes.length > 0 ? form.incidentTypes : ['Others'],
        incidentOtherText: form.incidentOtherText || null,
        summaryLine: form.summaryLine.trim(),
        description: form.description.trim(),
        whoNotified: form.whoNotified,
        notifiedOtherText: form.notifiedOtherText || null,
        siteSupervisorName: form.siteSupervisorName.trim() || null,
        siteSupervisorId: form.siteSupervisorId.trim() || null,
        siteSupervisorDate: form.siteSupervisorDate || form.incidentDate,
        siteSupervisorSignature: form.siteSupervisorSignature || null,
        photos: form.photos.map((p) => ({
          originalFilename: p.originalFilename,
          mimeType: p.mimeType,
          dataBase64: p.dataBase64,
          caption: p.caption || null,
        })),
      };

      const res = await guardApi.submitIncident(payload);

      // Clear offline draft on successful submit
      clearDraft();
      try {
        sessionStorage.removeItem('uss_guard_token');
        for (let i = localStorage.length - 1; i >= 0; i--) {
          const k = localStorage.key(i);
          if (k && k.startsWith('uss_draft_')) {
            localStorage.removeItem(k);
          }
        }
      } catch (_) {}

      // Navigate to success screen
      navigate('/g/success', {
        state: {
          refNumber: res.refNumber,
          siteName: profile.site.name,
        },
      });
    } catch (err: any) {
      setError(err.message || 'Failed to submit report. Please try again.');
      setSubmitting(false);
    }
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-4 max-w-full overflow-hidden pb-10">
      {/* ========================================================================= */}
      {/* 1. OFFICIAL FORM HEADER (REPLICATES OFFICIAL USS INCIDENT REPORT)         */}
      {/* ========================================================================= */}
      <div className="bg-white rounded-2xl shadow-sm border border-slate-300 overflow-hidden">
        {/* Top Company & Title Banner */}
        <div className="p-4 sm:p-5 border-b border-slate-200 bg-slate-50/70">
          <div className="flex flex-col sm:flex-row items-center justify-between gap-3 text-center sm:text-left">
            {/* Logo */}
            <div className="w-14 h-14 rounded-xl bg-white p-1 flex items-center justify-center shadow-xs border border-slate-200 shrink-0 overflow-hidden">
              <img
                src={settings?.logoUrl || '/assets/uss-logo.png'}
                alt="USS"
                className="w-full h-full object-contain"
                onError={(e: any) => {
                  e.currentTarget.onerror = null;
                  e.currentTarget.src = '/assets/uss-logo.png';
                }}
              />
            </div>

            {/* Title */}
            <div className="flex-1 text-center">
              <h2 className="font-black text-xl sm:text-2xl text-slate-900 tracking-tight uppercase">
                INCIDENT REPORT
              </h2>
              <span className="text-[10px] sm:text-xs font-semibold text-slate-500 uppercase tracking-wider block">
                Official Security Operations Form
              </span>
            </div>

            {/* Official Company Details (from official PDF) */}
            <div className="text-center sm:text-right text-[10px] text-slate-600 font-medium space-y-0.5">
              <div className="font-extrabold text-slate-900 text-xs uppercase tracking-wide">
                {settings?.companyName || 'ULTIMATE SECURITY SERVICES'}
              </div>
              <div>P.O. Box No: 61591</div>
              <div>Al Maktoum Street, DUBAI | U.A.E</div>
              <div className="text-blue-700 font-semibold">{settings?.email || 'ops@ultimatesecurity.ae'}</div>
            </div>
          </div>

          {/* Draft Autosave indicator */}
          {lastSaved && (
            <div className="mt-3 flex items-center justify-center sm:justify-start gap-1.5 text-[11px] text-emerald-700 font-bold">
              <Save className="w-3.5 h-3.5" />
              <span>Draft autosaved locally ({lastSaved.toLocaleTimeString()})</span>
            </div>
          )}
        </div>

        {/* ========================================================================= */}
        {/* 2. METADATA GRID (Site Name, Person Reporting, Designation | Date, Time, Shift) */}
        {/* ========================================================================= */}
        <div className="grid grid-cols-1 sm:grid-cols-2 divide-y sm:divide-y-0 sm:divide-x divide-slate-200 text-xs">
          {/* Left Column */}
          <div className="p-3 sm:p-4 space-y-2.5">
            <div className="flex items-start justify-between gap-2">
              <span className="font-extrabold text-slate-700 min-w-[110px]">Site Name :</span>
              <span className="font-black text-slate-900 truncate text-right flex-1 flex items-center justify-end gap-1 text-xs sm:text-sm">
                <MapPin className="w-3.5 h-3.5 text-blue-600 shrink-0" />
                <span className="truncate">{profile?.site?.name || 'Assigned Site'}</span>
              </span>
            </div>

            <div className="flex items-center justify-between gap-2">
              <span className="font-extrabold text-slate-700 min-w-[110px]">Person Reporting :</span>
              <span className="font-black text-slate-900 truncate text-right flex-1">
                {profile?.guard?.fullName || 'Security Officer'}
              </span>
            </div>

            <div className="flex items-center justify-between gap-2">
              <span className="font-extrabold text-slate-700 min-w-[110px]">Designation :</span>
              <span className="font-bold text-slate-700 text-right flex-1 truncate">
                {profile?.guard?.designation || 'Staff'} ({profile?.guard?.staffId})
              </span>
            </div>
          </div>

          {/* Right Column */}
          <div className="p-3 sm:p-4 space-y-2.5">
            <div className="flex items-center justify-between gap-2">
              <span className="font-extrabold text-slate-700 min-w-[70px]">Date :</span>
              <div className="flex items-center gap-1.5 font-black text-slate-900">
                <Calendar className="w-3.5 h-3.5 text-blue-600" />
                <input
                  type="date"
                  value={form.incidentDate}
                  onChange={(e) => setForm({ ...form, incidentDate: e.target.value })}
                  className="bg-transparent font-black text-xs sm:text-sm text-slate-900 focus:outline-none"
                />
              </div>
            </div>

            <div className="flex items-center justify-between gap-2">
              <span className="font-extrabold text-slate-700 min-w-[70px]">Time :</span>
              <div className="flex items-center gap-1.5 font-black text-slate-900">
                <Clock className="w-3.5 h-3.5 text-blue-600" />
                <input
                  type="time"
                  value={form.incidentTime}
                  onChange={(e) => setForm({ ...form, incidentTime: e.target.value })}
                  className="bg-transparent font-black text-xs sm:text-sm text-slate-900 focus:outline-none"
                />
              </div>
            </div>

            <div className="flex items-center justify-between gap-2">
              <span className="font-extrabold text-slate-700 min-w-[70px]">Shift :</span>
              <select
                value={form.shift}
                onChange={(e) => setForm({ ...form, shift: e.target.value })}
                className="bg-white border border-slate-300 text-xs rounded-lg px-2 py-0.5 font-bold text-blue-900 shadow-2xs"
              >
                {(settings?.shiftOptions?.length > 0 ? settings.shiftOptions : ['Day', 'Night']).map((sh: string) => (
                  <option key={sh} value={sh}>
                    {sh} Shift
                  </option>
                ))}
              </select>
            </div>
          </div>
        </div>
      </div>

      {/* ========================================================================= */}
      {/* 3. PRIORITY SELECTOR (CRITICAL, HIGH, MEDIUM, LOW)                        */}
      {/* ========================================================================= */}
      <div className="bg-white rounded-2xl p-4 shadow-sm border border-slate-300 space-y-2">
        <span className="text-[11px] font-black uppercase tracking-wider text-slate-700 block">
          Incident Priority Level
        </span>
        <div className="grid grid-cols-4 gap-2">
          {(['Critical', 'High', 'Medium', 'Low'] as const).map((p) => {
            const isSelected = form.priority === p;
            const colors = {
              Critical: isSelected
                ? 'bg-rose-600 text-white border-rose-600 ring-2 ring-rose-200'
                : 'bg-rose-50/60 text-rose-800 border-rose-200 hover:bg-rose-100',
              High: isSelected
                ? 'bg-amber-600 text-white border-amber-600 ring-2 ring-amber-200'
                : 'bg-amber-50/60 text-amber-800 border-amber-200 hover:bg-amber-100',
              Medium: isSelected
                ? 'bg-blue-700 text-white border-blue-700 ring-2 ring-blue-200'
                : 'bg-blue-50/60 text-blue-800 border-blue-200 hover:bg-blue-100',
              Low: isSelected
                ? 'bg-emerald-700 text-white border-emerald-700 ring-2 ring-emerald-200'
                : 'bg-emerald-50/60 text-emerald-800 border-emerald-200 hover:bg-emerald-100',
            };

            return (
              <button
                type="button"
                key={p}
                onClick={() => setForm({ ...form, priority: p })}
                className={`py-2 px-1 text-center rounded-xl text-xs font-black border transition-all flex items-center justify-center gap-1.5 ${colors[p]}`}
              >
                {isSelected ? <CheckSquare className="w-3.5 h-3.5 shrink-0" /> : <Square className="w-3.5 h-3.5 shrink-0 opacity-50" />}
                <span>{p}</span>
              </button>
            );
          })}
        </div>
      </div>

      {/* ========================================================================= */}
      {/* 4. DISCIPLINARY SECTION (EXACT CHECKBOXES FROM OFFICIAL PDF)              */}
      {/* ========================================================================= */}
      <div className="bg-white rounded-2xl shadow-sm border border-slate-300 overflow-hidden">
        <div className="bg-[#0F2042] px-4 py-2.5 border-b border-[#0F2042] flex items-center justify-between">
          <span className="font-black text-xs sm:text-sm text-amber-300 uppercase tracking-wide">
            Disciplinary
          </span>
          <span className="text-[10px] text-amber-200/80 font-bold uppercase">
            Select any that apply
          </span>
        </div>

        <div className="p-3 sm:p-4 space-y-3">
          <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
            {disciplinaryOptions.map((reason) => {
              const isSelected = form.disciplinaryReasons.includes(reason);
              return (
                <label
                  key={reason}
                  className={`flex items-center gap-2 p-2.5 rounded-xl text-xs font-bold border cursor-pointer transition-all ${
                    isSelected
                      ? 'bg-amber-500/10 text-amber-950 border-amber-500 font-extrabold ring-1 ring-amber-400'
                      : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'
                  }`}
                >
                  <input
                    type="checkbox"
                    checked={isSelected}
                    onChange={() => toggleArrayItem('disciplinaryReasons', reason)}
                    className="rounded text-amber-600 focus:ring-0 w-4 h-4 shrink-0"
                  />
                  <span className="truncate">{reason}</span>
                </label>
              );
            })}
          </div>

          {(form.disciplinaryReasons.includes('Others') || form.disciplinaryReasons.some((r) => /other/i.test(r))) && (
            <input
              type="text"
              placeholder="Specify other disciplinary reason..."
              value={form.disciplinaryOtherText}
              onChange={(e) => setForm({ ...form, disciplinaryOtherText: e.target.value })}
              className="w-full text-xs sm:text-sm font-semibold p-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-amber-500 focus:outline-none bg-amber-50/30"
            />
          )}
        </div>
      </div>

      {/* ========================================================================= */}
      {/* 5. INCIDENT SECTION (EXACT 12 CHECKBOXES FROM OFFICIAL PDF)               */}
      {/* ========================================================================= */}
      <div className="bg-white rounded-2xl shadow-sm border border-slate-300 overflow-hidden">
        <div className="bg-[#0F2042] px-4 py-2.5 border-b border-[#0F2042] flex items-center justify-between">
          <span className="font-black text-xs sm:text-sm text-amber-300 uppercase tracking-wide">
            Incident Classification
          </span>
          <span className="text-[10px] text-amber-200/80 font-bold uppercase">
            Official Incident Options
          </span>
        </div>

        <div className="p-3 sm:p-4 space-y-3">
          <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
            {incidentOptions.map((inc) => {
              const isSelected = form.incidentTypes.includes(inc);
              return (
                <label
                  key={inc}
                  className={`flex items-center gap-2 p-2.5 rounded-xl text-xs font-bold border cursor-pointer transition-all ${
                    isSelected
                      ? 'bg-blue-600/10 text-blue-950 border-blue-600 font-extrabold ring-1 ring-blue-400'
                      : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'
                  }`}
                >
                  <input
                    type="checkbox"
                    checked={isSelected}
                    onChange={() => toggleArrayItem('incidentTypes', inc)}
                    className="rounded text-blue-600 focus:ring-0 w-4 h-4 shrink-0"
                  />
                  <span className="truncate">{inc}</span>
                </label>
              );
            })}
          </div>

          {(form.incidentTypes.includes('Others') || form.incidentTypes.some((i) => /other/i.test(i))) && (
            <input
              type="text"
              placeholder="Specify other incident classification..."
              value={form.incidentOtherText}
              onChange={(e) => setForm({ ...form, incidentOtherText: e.target.value })}
              className="w-full text-xs sm:text-sm font-semibold p-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 focus:outline-none bg-blue-50/30"
            />
          )}
        </div>
      </div>

      {/* ========================================================================= */}
      {/* 6. NARRATIVE SUMMARY PROMPT & SUMMARY LINE                                */}
      {/* ========================================================================= */}
      <div className="bg-white rounded-2xl shadow-sm border border-slate-300 overflow-hidden">
        {/* Exact Prompt Banner from Official PDF */}
        <div className="bg-[#0F2042] text-amber-300 px-4 py-2 text-center text-xs font-black tracking-wide">
          (Explain What, Where, When, Who, Why and How action taken)
        </div>

        <div className="p-3 sm:p-4">
          <textarea
            rows={2}
            required
            placeholder="Write concise summary line (e.g., Water leakage in B1 parking pipe detected at 02:40 hrs, facility team notified and valve closed)..."
            value={form.summaryLine}
            onChange={(e) => setForm({ ...form, summaryLine: e.target.value })}
            className="w-full text-xs sm:text-sm font-bold p-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 focus:outline-none text-red-800 bg-white leading-relaxed resize-none"
          />
        </div>
      </div>

      {/* ========================================================================= */}
      {/* 7. WHO WAS NOTIFIED? (CHECKBOXES FROM OFFICIAL PDF)                       */}
      {/* ========================================================================= */}
      <div className="bg-white rounded-2xl shadow-sm border border-slate-300 overflow-hidden">
        <div className="bg-[#0F2042] px-4 py-2.5 border-b border-[#0F2042] flex items-center justify-between">
          <span className="font-black text-xs sm:text-sm text-amber-300 uppercase tracking-wide">
            Who was notified?
          </span>
          <span className="text-[10px] text-amber-200/80 font-bold uppercase">
            Official Notification Record
          </span>
        </div>

        <div className="p-3 sm:p-4 space-y-3">
          <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
            {notifiedOptions.map((notif) => {
              const isSelected = form.whoNotified.includes(notif);
              return (
                <label
                  key={notif}
                  className={`flex items-center gap-2 p-2.5 rounded-xl text-xs font-bold border cursor-pointer transition-all ${
                    isSelected
                      ? 'bg-blue-600/10 text-blue-950 border-blue-600 font-extrabold ring-1 ring-blue-400'
                      : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'
                  }`}
                >
                  <input
                    type="checkbox"
                    checked={isSelected}
                    onChange={() => toggleArrayItem('whoNotified', notif)}
                    className="rounded text-blue-600 focus:ring-0 w-4 h-4 shrink-0"
                  />
                  <span className="truncate">{notif}</span>
                </label>
              );
            })}
          </div>

          {(form.whoNotified.includes('Others') || form.whoNotified.some((n) => /other/i.test(n))) && (
            <input
              type="text"
              placeholder="Specify other notified entity (e.g. Facilities Management, Civil Defense Officer)..."
              value={form.notifiedOtherText}
              onChange={(e) => setForm({ ...form, notifiedOtherText: e.target.value })}
              className="w-full text-xs sm:text-sm font-semibold p-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 focus:outline-none bg-blue-50/30"
            />
          )}
        </div>
      </div>

      {/* ========================================================================= */}
      {/* 8. DESCRIPTION OF INCIDENT                                                */}
      {/* ========================================================================= */}
      <div className="bg-white rounded-2xl shadow-sm border border-slate-300 overflow-hidden">
        <div className="bg-[#0F2042] px-4 py-2.5 border-b border-[#0F2042] flex items-center justify-between">
          <span className="font-black text-xs sm:text-sm text-amber-300 uppercase tracking-wide">
            Description of Incident
          </span>
          <span className="text-[10px] text-amber-200/80 font-bold uppercase">
            Detailed Narrative (Min 20 chars)
          </span>
        </div>

        <div className="p-3 sm:p-4">
          <RichTextEditor
            value={form.description}
            onChange={(html) => setForm({ ...form, description: html })}
            placeholder="Provide a thorough chronological narrative of what occurred, staff responses, actions taken, damages or persons involved..."
          />
        </div>
      </div>

      {/* ========================================================================= */}
      {/* 9. PHOTOGRAPHIC EVIDENCE (MAX 10)                                         */}
      {/* ========================================================================= */}
      <div className="bg-white rounded-2xl shadow-sm border border-slate-300 overflow-hidden">
        <div className="bg-[#0F2042] px-4 py-2.5 border-b border-[#0F2042] flex items-center justify-between">
          <span className="font-black text-xs sm:text-sm text-amber-300 uppercase tracking-wide">
            Photographic Evidence
          </span>
          <span className="text-[10px] text-amber-200/80 font-bold uppercase">
            Up to 10 Photos
          </span>
        </div>

        <div className="p-3 sm:p-4">
          <PhotoUploader
            photos={form.photos}
            onChange={(newPhotos) => setForm({ ...form, photos: newPhotos })}
            maxPhotos={10}
          />
        </div>
      </div>

      {/* ========================================================================= */}
      {/* 10. SITE SUPERVISOR SIGN-OFF (ON-SITE VERIFICATION)                       */}
      {/* ========================================================================= */}
      <div className="bg-white rounded-2xl shadow-sm border border-slate-300 overflow-hidden">
        <div className="bg-[#0F2042] px-4 py-2.5 border-b border-[#0F2042] flex items-center justify-between">
          <span className="font-black text-xs sm:text-sm text-amber-300 uppercase tracking-wide flex items-center gap-1.5">
            <UserCheck className="w-4 h-4 text-amber-300" />
            <span>Site Supervisor Sign-Off</span>
          </span>
          <span className="text-[10px] text-amber-200/80 font-bold uppercase">
            On-Duty Verification
          </span>
        </div>

        <div className="p-3 sm:p-4 space-y-3">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label className="block text-[11px] font-extrabold text-slate-700 uppercase mb-1">
                Site Supervisor Name:
              </label>
              <input
                type="text"
                placeholder="Supervisor Name (e.g. Ahmed Al Mansoori)..."
                value={form.siteSupervisorName}
                onChange={(e) => setForm({ ...form, siteSupervisorName: e.target.value })}
                className="w-full text-xs sm:text-sm font-semibold p-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 focus:outline-none bg-slate-50 text-slate-900"
              />
            </div>

            <div>
              <label className="block text-[11px] font-extrabold text-slate-700 uppercase mb-1">
                Supervisor ID No.:
              </label>
              <input
                type="text"
                placeholder="Supervisor ID (e.g. SS-904)..."
                value={form.siteSupervisorId}
                onChange={(e) => setForm({ ...form, siteSupervisorId: e.target.value })}
                className="w-full text-xs sm:text-sm font-semibold p-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 focus:outline-none bg-slate-50 text-slate-900"
              />
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
            <div>
              <label className="block text-[11px] font-extrabold text-slate-700 uppercase mb-1">
                Verification Date:
              </label>
              <input
                type="date"
                value={form.siteSupervisorDate}
                onChange={(e) => setForm({ ...form, siteSupervisorDate: e.target.value })}
                className="w-full text-xs sm:text-sm font-semibold p-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 focus:outline-none bg-slate-50 text-slate-900"
              />
            </div>
          </div>

          {/* Supervisor Signature Image Upload & Status */}
          <div className="pt-2 border-t border-slate-200 space-y-2">
            <label className="block text-[11px] font-extrabold text-slate-700 uppercase flex items-center justify-between">
              <span>Supervisor / Security Signature Image:</span>
              <span className="text-[10px] text-blue-600 font-bold lowercase">(upload image photo or verify on-site)</span>
            </label>

            {form.siteSupervisorSignature && form.siteSupervisorSignature.startsWith('data:image') ? (
              <div className="flex items-center gap-3 p-3 bg-blue-50/50 border border-blue-200 rounded-xl">
                <div className="bg-white border border-slate-200 rounded-lg p-1.5 shadow-xs shrink-0">
                  <img
                    src={form.siteSupervisorSignature}
                    alt="Supervisor Signature"
                    className="h-10 max-w-[160px] object-contain"
                  />
                </div>
                <div className="flex-1 min-w-0">
                  <p className="text-xs font-bold text-slate-900 truncate">Signature Attached</p>
                  <p className="text-[10px] text-slate-500 truncate">Will appear directly in PDF supervisor signature row</p>
                </div>
                <button
                  type="button"
                  onClick={() => setForm((prev) => ({ ...prev, siteSupervisorSignature: '' }))}
                  className="flex items-center gap-1 px-2.5 py-1.5 bg-red-100 text-red-700 hover:bg-red-200 rounded-lg text-xs font-bold transition-colors shrink-0"
                >
                  <X className="w-3.5 h-3.5" />
                  <span>Remove</span>
                </button>
              </div>
            ) : (
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                <label className="flex items-center justify-center gap-2 p-2.5 bg-blue-50/80 border border-dashed border-blue-400 rounded-xl cursor-pointer hover:bg-blue-100 transition-all text-blue-800 text-xs font-bold">
                  <Upload className="w-4 h-4 text-blue-600 shrink-0" />
                  <span>Upload Signature Image</span>
                  <input
                    type="file"
                    accept="image/*"
                    onChange={handleSignatureUpload}
                    className="hidden"
                  />
                </label>

                <label className="flex items-center gap-2 p-2.5 bg-slate-50 border border-slate-300 rounded-xl cursor-pointer hover:bg-slate-100 transition-all">
                  <input
                    type="checkbox"
                    checked={form.siteSupervisorSignature === 'Signed'}
                    onChange={(e) =>
                      setForm((prev) => ({
                        ...prev,
                        siteSupervisorSignature: e.target.checked ? 'Signed' : '',
                      }))
                    }
                    className="rounded text-blue-600 focus:ring-0 w-4 h-4 shrink-0"
                  />
                  <span className="text-xs font-bold text-slate-800">
                    Or check: Verified on-site
                  </span>
                </label>
              </div>
            )}
          </div>
        </div>
      </div>

      {/* ========================================================================= */}
      {/* 11. OFFICIAL APPROVAL PROTOCOL (OPERATIONS & CLIENT MANAGERS)             */}
      {/* ========================================================================= */}
      <div className="bg-white rounded-2xl shadow-sm border border-slate-300 overflow-hidden">
        <div className="bg-[#0F2042] px-4 py-2.5 border-b border-[#0F2042] flex items-center justify-between">
          <span className="font-black text-xs sm:text-sm text-amber-300 uppercase tracking-wide">
            Official Review & Approval Protocol
          </span>
          <span className="text-[10px] text-amber-200/80 font-bold uppercase">
            Operations & Client Review
          </span>
        </div>
        <div className="p-3.5 space-y-2">
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 text-[11px]">
          <div className="bg-white p-2.5 rounded-xl border border-slate-200 space-y-1">
            <div className="font-black text-slate-900 flex items-center justify-between">
              <span>Operations Manager</span>
              <span className="text-[10px] px-1.5 py-0.5 bg-blue-100 text-blue-800 rounded font-bold">Step 2</span>
            </div>
            <div className="text-slate-600 text-[10px]">
              Recommendation / Action Taken & Signature Image
            </div>
          </div>

          <div className="bg-white p-2.5 rounded-xl border border-slate-200 space-y-1">
            <div className="font-black text-slate-900 flex items-center justify-between">
              <span>Client Manager</span>
              <span className="text-[10px] px-1.5 py-0.5 bg-emerald-100 text-emerald-800 rounded font-bold">Step 3</span>
            </div>
            <div className="text-slate-600 text-[10px]">
              Action Taken by Client Manager & Signature Image
            </div>
          </div>
        </div>
      </div>

      {/* Error Alert */}
      {error && (
        <div className="p-3.5 bg-red-50 text-red-700 rounded-xl text-xs border border-red-200 flex items-start gap-2.5">
          <AlertCircle className="w-4 h-4 shrink-0 mt-0.5" />
          <span>{error}</span>
        </div>
      )}

      {/* ========================================================================= */}
      {/* 11. STICKY SUBMIT & SIGN OUT ACTION BAR                                   */}
      {/* ========================================================================= */}
      <div className="sticky bottom-3 z-30 pt-2 space-y-2">
        <div className="bg-white/95 backdrop-blur-md p-2 rounded-2xl shadow-xl border border-slate-200 flex items-center gap-2">
          <button
            type="submit"
            disabled={submitting}
            className="flex-1 py-3.5 bg-gradient-to-r from-emerald-600 to-teal-700 hover:from-emerald-700 hover:to-teal-800 active:scale-[0.99] disabled:opacity-50 text-white font-bold rounded-xl shadow-md transition-all flex items-center justify-center gap-2 touch-btn text-sm"
          >
            {submitting ? (
              <>
                <Loader2 className="w-5 h-5 animate-spin" />
                <span>Submitting Official Report...</span>
              </>
            ) : (
              <>
                <Send className="w-5 h-5" />
                <span>Submit Incident Report</span>
              </>
            )}
          </button>

          <button
            type="button"
            onClick={async () => {
              if (window.confirm('Are you sure you want to exit and sign out? Any unsaved changes will be safely discarded.')) {
                clearDraft();
                await guardApi.logout();
                navigate('/g');
              }
            }}
            className="px-3.5 py-3.5 bg-slate-100 hover:bg-rose-50 text-slate-700 hover:text-rose-700 border border-slate-300 rounded-xl font-bold transition-all flex items-center justify-center gap-1.5 touch-btn text-xs shrink-0"
            title="Sign Out Officer"
          >
            <LogOut className="w-4 h-4 text-rose-600" />
            <span className="hidden sm:inline">Sign Out</span>
          </button>
        </div>
        <p className="text-[11px] text-slate-500 text-center font-medium">
          Instant sync: Broadcasts live to USS Super Admin Dashboard upon submission.
        </p>
      </div>
    </form>
  );
};
