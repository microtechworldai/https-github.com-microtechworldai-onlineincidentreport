import React, { useEffect, useState } from 'react';
import {
  Settings,
  Building,
  Save,
  Plus,
  Trash2,
  CheckCircle2,
  AlertCircle,
  Loader2,
  Image as ImageIcon,
  Upload,
  RotateCcw,
  Flame,
  Pencil,
  Check,
  X,
  FileText,
  Globe,
  Palette,
  Eye,
  Layers,
  Phone,
  Mail,
  MapPin,
} from 'lucide-react';
import { adminApi } from '../../lib/api';

const DEFAULT_SETTINGS = {
  companyName: 'ULTIMATE SECURITY SERVICES',
  tagline: 'Total Security Solutions',
  addressLine: 'P O Box No: 61591, Al Maktoum Street | Dubai | UAE',
  phone: '+971 4 251 0050',
  email: 'ops@ultimatesecurity.ae',
  website: 'www.ultimatesecurity.ae',
  reportTitle: 'INCIDENT REPORT',
  primaryColor: '#1E3A8A',
  detailsVersionMode: 'current',
  logoUrl: '/assets/uss-logo.png',
  timezone: 'Asia/Dubai',
  shiftOptions: ['Day', 'Night'],
  causeOptions: ['Incident', 'Disciplinary', 'Others'],
  disciplinaryOptions: [
    'Sleeping',
    'Poor Grooming',
    'Argument',
    'Missing',
    'Misbehavior',
    'Uniform',
    'Late Reporting',
    'Mobile',
    'Others',
  ],
  incidentOptions: [
    'Move In & Out',
    'Fire & Safety',
    'Intrusion & Access',
    'Theft and loss',
    'CCTV & Systems',
    'Utilities & Building',
    'Accident & Medical',
    'Disturbance',
    'Property Damage',
    'Parking & Vehicles',
    'Visitors & Contractors',
    'Others',
  ],
  notifiedOptions: [
    'Police',
    'Ambulance',
    'Civil Defense',
    'Client Management',
    'USS Management',
    'Others',
  ],
  signoffLabels: {
    siteSupervisor: 'Site Supervisor',
    operationsManager: 'Operations Manager',
    clientManager: 'Client Manager',
  },
};

export const SettingsPage: React.FC = () => {
  const [settings, setSettings] = useState<any>(null);
  const [loading, setLoading] = useState<boolean>(true);
  const [saving, setSaving] = useState<boolean>(false);
  const [success, setSuccess] = useState<boolean>(false);

  // Logo state
  const [logoFile, setLogoFile] = useState<File | null>(null);
  const [logoPreview, setLogoPreview] = useState<string | null>(null);
  const [logoUploading, setLogoUploading] = useState<boolean>(false);
  const [logoMessage, setLogoMessage] = useState<string | null>(null);

  // New option tag states
  const [newIncidentOpt, setNewIncidentOpt] = useState<string>('');
  const [newDisciplinaryOpt, setNewDisciplinaryOpt] = useState<string>('');
  const [newNotifiedOpt, setNewNotifiedOpt] = useState<string>('');
  const [newShiftOpt, setNewShiftOpt] = useState<string>('');

  // Editing state for changing existing option names
  const [editingItem, setEditingItem] = useState<{
    field: 'incidentOptions' | 'disciplinaryOptions' | 'notifiedOptions' | 'shiftOptions';
    index: number;
    value: string;
  } | null>(null);

  useEffect(() => {
    adminApi
      .getSettings()
      .then((data) => {
        setSettings({
          companyName: data.company_name || data.companyName || DEFAULT_SETTINGS.companyName,
          tagline: data.tagline || DEFAULT_SETTINGS.tagline,
          addressLine: data.address_line || data.addressLine || DEFAULT_SETTINGS.addressLine,
          phone: data.phone || DEFAULT_SETTINGS.phone,
          email: data.email || DEFAULT_SETTINGS.email,
          website: data.website || DEFAULT_SETTINGS.website,
          reportTitle: data.report_title || data.reportTitle || DEFAULT_SETTINGS.reportTitle,
          primaryColor: data.primary_color || data.primaryColor || DEFAULT_SETTINGS.primaryColor,
          detailsVersionMode: data.details_version_mode || data.detailsVersionMode || 'current',
          logoUrl: data.logo_url || data.logoUrl || DEFAULT_SETTINGS.logoUrl,
          timezone: data.timezone || DEFAULT_SETTINGS.timezone,
          shiftOptions: Array.isArray(data.shift_options)
            ? data.shift_options
            : data.shift_options
              ? JSON.parse(data.shift_options)
              : DEFAULT_SETTINGS.shiftOptions,
          causeOptions: Array.isArray(data.cause_options)
            ? data.cause_options
            : data.cause_options
              ? JSON.parse(data.cause_options)
              : DEFAULT_SETTINGS.causeOptions,
          disciplinaryOptions: Array.isArray(data.disciplinary_options)
            ? data.disciplinary_options
            : data.disciplinary_options
              ? JSON.parse(data.disciplinary_options)
              : DEFAULT_SETTINGS.disciplinaryOptions,
          incidentOptions: Array.isArray(data.incident_options)
            ? data.incident_options
            : data.incident_options
              ? JSON.parse(data.incident_options)
              : DEFAULT_SETTINGS.incidentOptions,
          notifiedOptions: Array.isArray(data.notified_options)
            ? data.notified_options
            : data.notified_options
              ? JSON.parse(data.notified_options)
              : DEFAULT_SETTINGS.notifiedOptions,
          signoffLabels: data.signoff_labels || DEFAULT_SETTINGS.signoffLabels,
        });
      })
      .finally(() => setLoading(false));
  }, []);

  const handleLogoChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    if (!file.type.startsWith('image/')) {
      alert('Please select an image file (PNG, JPG, SVG, WebP)');
      return;
    }

    setLogoFile(file);
    const reader = new FileReader();
    reader.onload = (event) => {
      setLogoPreview(event.target?.result as string);
    };
    reader.readAsDataURL(file);
  };

  const handleApplyLogo = async () => {
    if (!logoPreview) return;
    setLogoUploading(true);
    setLogoMessage(null);

    try {
      const res = await adminApi.uploadLogo(logoPreview, logoFile?.name || 'uss-logo.png');
      const updatedUrl = res.logoUrl || logoPreview;
      setSettings((prev: any) => ({ ...prev, logoUrl: updatedUrl }));
      setLogoFile(null);
      setLogoPreview(null);
      setLogoMessage('Company logo updated and applied across all apps and PDF reports!');
      setTimeout(() => setLogoMessage(null), 4000);
    } catch (err: any) {
      alert(err.message || 'Failed to upload logo');
    } finally {
      setLogoUploading(false);
    }
  };

  const handleResetDefaultLogo = async () => {
    if (!window.confirm('Reset company logo to default USS emblem?')) return;
    setLogoUploading(true);
    try {
      await adminApi.updateSettings({ ...settings, logoUrl: '/assets/uss-logo.png' });
      setSettings((prev: any) => ({ ...prev, logoUrl: '/assets/uss-logo.png' }));
      setLogoPreview(null);
      setLogoFile(null);
      setLogoMessage('Restored default USS logo.');
      setTimeout(() => setLogoMessage(null), 3000);
    } catch (err: any) {
      alert(err.message || 'Failed to reset logo');
    } finally {
      setLogoUploading(false);
    }
  };

  const handleResetToAllDefaults = () => {
    if (!window.confirm('Reset all company settings and classifications to official factory defaults?')) {
      return;
    }
    setSettings({ ...DEFAULT_SETTINGS });
  };

  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    setSuccess(false);

    try {
      // Ensure no fax property is sent
      const payload = { ...settings };
      delete (payload as any).fax;

      await adminApi.updateSettings(payload);
      setSuccess(true);
      setTimeout(() => setSuccess(false), 3000);
    } catch (err: any) {
      alert(err.message || 'Failed to save settings');
    } finally {
      setSaving(false);
    }
  };

  const startEditOption = (
    field: 'incidentOptions' | 'disciplinaryOptions' | 'notifiedOptions' | 'shiftOptions',
    index: number,
    currentValue: string
  ) => {
    setEditingItem({ field, index, value: currentValue });
  };

  const saveEditOption = () => {
    if (!editingItem || !editingItem.value.trim()) {
      setEditingItem(null);
      return;
    }
    const val = editingItem.value.trim();
    setSettings((prev: any) => {
      const list = [...(prev[editingItem.field] || [])];
      list[editingItem.index] = val;
      return { ...prev, [editingItem.field]: list };
    });
    setEditingItem(null);
  };

  const cancelEditOption = () => {
    setEditingItem(null);
  };

  const addOption = (
    field: 'incidentOptions' | 'disciplinaryOptions' | 'notifiedOptions' | 'shiftOptions',
    value: string,
    clearFn: (v: string) => void
  ) => {
    const trimmed = value.trim();
    if (!trimmed) return;
    setSettings((prev: any) => {
      const currentList = prev[field] || [];
      if (currentList.some((item: string) => item.toLowerCase() === trimmed.toLowerCase())) {
        alert(`"${trimmed}" already exists in the list.`);
        return prev;
      }
      return {
        ...prev,
        [field]: [...currentList, trimmed],
      };
    });
    clearFn('');
  };

  const removeOption = (
    field: 'incidentOptions' | 'disciplinaryOptions' | 'notifiedOptions' | 'shiftOptions',
    index: number
  ) => {
    setSettings((prev: any) => ({
      ...prev,
      [field]: (prev[field] || []).filter((_: any, i: number) => i !== index),
    }));
  };

  if (loading || !settings) {
    return (
      <div className="flex justify-center py-20">
        <Loader2 className="w-8 h-8 animate-spin text-blue-600" />
      </div>
    );
  }

  return (
    <form onSubmit={handleSave} className="space-y-6 max-w-5xl pb-16">
      {/* Top Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-xl md:text-2xl font-extrabold text-slate-900 tracking-tight">
            Company Setup & Branding
          </h1>
          <p className="text-xs text-slate-500 mt-0.5">
            Single source of truth for company identity, Dubai office contacts, PDF layout, and dynamic incident form options.
          </p>
        </div>

        <div className="flex items-center gap-2">
          <button
            type="button"
            onClick={handleResetToAllDefaults}
            className="flex items-center gap-1.5 px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold transition-all"
            title="Reset to official factory defaults"
          >
            <RotateCcw className="w-3.5 h-3.5" />
            <span>Reset to Defaults</span>
          </button>
          <button
            type="submit"
            disabled={saving}
            className="flex items-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-sm transition-all"
          >
            <Save className="w-4 h-4" />
            <span>{saving ? 'Saving Changes...' : 'Save Settings'}</span>
          </button>
        </div>
      </div>

      {success && (
        <div className="p-4 bg-emerald-50 text-emerald-800 rounded-2xl border border-emerald-200 text-xs flex items-center gap-2 shadow-xs">
          <CheckCircle2 className="w-4 h-4 shrink-0 text-emerald-600" />
          <span>Company settings and incident configurations saved & broadcasted successfully.</span>
        </div>
      )}

      {/* LIVE PDF HEADER PREVIEW */}
      <div className="bg-gradient-to-br from-slate-900 to-slate-800 text-white p-6 rounded-3xl shadow-md border border-slate-700">
        <div className="flex items-center justify-between pb-3 border-b border-slate-700/80 mb-4">
          <div className="flex items-center gap-2">
            <Eye className="w-4 h-4 text-blue-400" />
            <h2 className="text-xs font-black uppercase tracking-wider text-slate-200">
              Live PDF Header Preview (As Rendered in Reports)
            </h2>
          </div>
          <span className="text-[10px] bg-blue-500/20 text-blue-300 border border-blue-400/30 px-2 py-0.5 rounded-full font-bold">
            Zero Fax • Right-Aligned Company Block
          </span>
        </div>

        {/* Paper Replica Container */}
        <div className="bg-white text-slate-900 p-5 rounded-xl border border-slate-200 shadow-inner">
          <div className="flex items-start justify-between gap-4">
            {/* Top-Left Logo */}
            <div className="w-24 h-16 flex items-center justify-center p-1 border border-dashed border-slate-300 rounded-lg bg-slate-50">
              <img
                src={logoPreview || settings.logoUrl || '/assets/uss-logo.png'}
                alt="Logo"
                className="max-h-full max-w-full object-contain"
                onError={(e: any) => {
                  e.target.src = '/assets/uss-logo.png';
                }}
              />
            </div>

            {/* Centered Document Title */}
            <div className="flex-1 text-center self-center px-2">
              <h3 className="text-base sm:text-lg font-black tracking-wide text-slate-900 uppercase">
                {settings.reportTitle || 'INCIDENT REPORT'}
              </h3>
              <div className="inline-block w-16 h-0.5 bg-slate-800 mt-1"></div>
            </div>

            {/* Top-Right Company Name & Dubai Office Block (Tight, Smaller) */}
            <div className="text-right max-w-[240px] space-y-0.5">
              <p className="text-[11px] font-black tracking-tight text-slate-900 leading-tight uppercase">
                {settings.companyName || 'ULTIMATE SECURITY SERVICES'}
              </p>
              <p className="text-[9px] text-slate-600 leading-snug">
                {settings.addressLine || 'P O Box No: 61591, Al Maktoum Street | Dubai | UAE'}
              </p>
              <p className="text-[9px] font-medium text-slate-700 leading-snug">
                <span className="font-bold">Office:</span> {settings.phone || '+971 4 251 0050'}
              </p>
              <p className="text-[9px] font-medium text-slate-700 leading-snug">
                {settings.email || 'ops@ultimatesecurity.ae'}
              </p>
              {settings.website && (
                <p className="text-[8.5px] text-blue-700 font-medium leading-snug">
                  {settings.website}
                </p>
              )}
            </div>
          </div>
        </div>
        <p className="text-[11px] text-slate-400 mt-2 text-right">
          Changes in the fields below immediately update this official header preview.
        </p>
      </div>

      {/* 3.1 Company Identity & Dubai Contact Fields */}
      <div className="bg-white p-6 rounded-3xl border border-slate-200 shadow-xs space-y-5">
        <div className="flex items-center gap-2.5 pb-3 border-b border-slate-100">
          <div className="w-8 h-8 rounded-xl bg-blue-50 text-blue-700 flex items-center justify-center font-bold">
            <Building className="w-4 h-4" />
          </div>
          <div>
            <h2 className="font-extrabold text-sm text-slate-900">
              Company Identity & Dubai Office Details
            </h2>
            <p className="text-xs text-slate-500">
              Single source of truth used everywhere. Fax field is permanently discontinued.
            </p>
          </div>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
          <div>
            <label className="block font-bold text-slate-700 mb-1">Company Legal Name</label>
            <input
              type="text"
              required
              value={settings.companyName}
              onChange={(e) => setSettings({ ...settings, companyName: e.target.value })}
              className="w-full p-2.5 border border-slate-300 rounded-xl font-bold focus:ring-2 focus:ring-blue-500 focus:outline-none"
              placeholder="e.g. ULTIMATE SECURITY SERVICES"
            />
          </div>

          <div>
            <label className="block font-bold text-slate-700 mb-1">Report Title (Header)</label>
            <input
              type="text"
              required
              value={settings.reportTitle}
              onChange={(e) => setSettings({ ...settings, reportTitle: e.target.value })}
              className="w-full p-2.5 border border-slate-300 rounded-xl font-bold focus:ring-2 focus:ring-blue-500 focus:outline-none uppercase"
              placeholder="e.g. INCIDENT REPORT"
            />
          </div>

          <div className="sm:col-span-2">
            <label className="block font-bold text-slate-700 mb-1">Office Address Line</label>
            <input
              type="text"
              required
              value={settings.addressLine}
              onChange={(e) => setSettings({ ...settings, addressLine: e.target.value })}
              className="w-full p-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:outline-none"
              placeholder="P O Box No: 61591, Al Maktoum Street | Dubai | UAE"
            />
          </div>

          <div>
            <label className="block font-bold text-slate-700 mb-1 flex items-center gap-1.5">
              <Phone className="w-3.5 h-3.5 text-blue-600" />
              <span>Office Telephone</span>
            </label>
            <input
              type="text"
              required
              value={settings.phone}
              onChange={(e) => setSettings({ ...settings, phone: e.target.value })}
              className="w-full p-2.5 border border-slate-300 rounded-xl font-mono focus:ring-2 focus:ring-blue-500 focus:outline-none"
              placeholder="+971 4 251 0050"
            />
          </div>

          <div>
            <label className="block font-bold text-slate-700 mb-1 flex items-center gap-1.5">
              <Mail className="w-3.5 h-3.5 text-blue-600" />
              <span>Operations Email</span>
            </label>
            <input
              type="email"
              required
              value={settings.email}
              onChange={(e) => setSettings({ ...settings, email: e.target.value })}
              className="w-full p-2.5 border border-slate-300 rounded-xl font-mono focus:ring-2 focus:ring-blue-500 focus:outline-none"
              placeholder="ops@ultimatesecurity.ae"
            />
          </div>

          <div>
            <label className="block font-bold text-slate-700 mb-1 flex items-center gap-1.5">
              <Globe className="w-3.5 h-3.5 text-blue-600" />
              <span>Website (Optional)</span>
            </label>
            <input
              type="text"
              value={settings.website || ''}
              onChange={(e) => setSettings({ ...settings, website: e.target.value })}
              className="w-full p-2.5 border border-slate-300 rounded-xl font-mono focus:ring-2 focus:ring-blue-500 focus:outline-none"
              placeholder="www.ultimatesecurity.ae"
            />
          </div>

          <div>
            <label className="block font-bold text-slate-700 mb-1 flex items-center gap-1.5">
              <Palette className="w-3.5 h-3.5 text-blue-600" />
              <span>Primary Brand Color</span>
            </label>
            <div className="flex items-center gap-2">
              <input
                type="color"
                value={settings.primaryColor || '#1E3A8A'}
                onChange={(e) => setSettings({ ...settings, primaryColor: e.target.value })}
                className="w-10 h-10 rounded-xl border border-slate-300 cursor-pointer p-0.5"
              />
              <input
                type="text"
                value={settings.primaryColor || '#1E3A8A'}
                onChange={(e) => setSettings({ ...settings, primaryColor: e.target.value })}
                className="flex-1 p-2.5 border border-slate-300 rounded-xl font-mono uppercase focus:ring-2 focus:ring-blue-500 focus:outline-none"
              />
            </div>
          </div>

          {/* Details Version Mode Setting */}
          <div className="sm:col-span-2 pt-2 border-t border-slate-100">
            <label className="block font-bold text-slate-900 mb-1">
              Historical Reports Company Details Policy
            </label>
            <p className="text-xs text-slate-500 mb-2">
              Controls whether older reports re-generated today use the current branding or their snapshot details.
            </p>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <label
                className={`flex items-start gap-3 p-3.5 rounded-2xl border cursor-pointer transition-all ${
                  settings.detailsVersionMode === 'current'
                    ? 'border-blue-600 bg-blue-50/50 text-blue-950 font-bold'
                    : 'border-slate-200 bg-white text-slate-700'
                }`}
              >
                <input
                  type="radio"
                  name="detailsVersionMode"
                  value="current"
                  checked={settings.detailsVersionMode === 'current'}
                  onChange={() => setSettings({ ...settings, detailsVersionMode: 'current' })}
                  className="mt-0.5 text-blue-600"
                />
                <div>
                  <div className="text-xs font-bold">Current Company Details (Default & Recommended)</div>
                  <div className="text-[11px] text-slate-500 font-normal mt-0.5">
                    Logo, phone, and address changes update everywhere immediately, including re-generated PDFs of older reports.
                  </div>
                </div>
              </label>

              <label
                className={`flex items-start gap-3 p-3.5 rounded-2xl border cursor-pointer transition-all ${
                  settings.detailsVersionMode === 'submission_time'
                    ? 'border-blue-600 bg-blue-50/50 text-blue-950 font-bold'
                    : 'border-slate-200 bg-white text-slate-700'
                }`}
              >
                <input
                  type="radio"
                  name="detailsVersionMode"
                  value="submission_time"
                  checked={settings.detailsVersionMode === 'submission_time'}
                  onChange={() => setSettings({ ...settings, detailsVersionMode: 'submission_time' })}
                  className="mt-0.5 text-blue-600"
                />
                <div>
                  <div className="text-xs font-bold">Details as at Time of Submission</div>
                  <div className="text-[11px] text-slate-500 font-normal mt-0.5">
                    Uses company snapshot saved when the report was originally submitted by guard.
                  </div>
                </div>
              </label>
            </div>
          </div>
        </div>
      </div>

      {/* Company Logo Upload */}
      <div className="bg-white p-6 rounded-3xl border border-slate-200 shadow-xs space-y-5">
        <div className="flex items-center justify-between pb-3 border-b border-slate-100">
          <div className="flex items-center gap-2.5">
            <div className="w-8 h-8 rounded-xl bg-blue-50 text-blue-700 flex items-center justify-center font-bold">
              <ImageIcon className="w-4 h-4" />
            </div>
            <div>
              <h2 className="font-extrabold text-sm text-slate-900">
                Company Emblem / Logo
              </h2>
              <p className="text-xs text-slate-500">
                Applies to guard app header, admin portal, PDF letterheads, QR posters, and emails.
              </p>
            </div>
          </div>
        </div>

        {logoMessage && (
          <div className="p-3.5 bg-emerald-50 text-emerald-800 rounded-2xl border border-emerald-200 text-xs flex items-center gap-2">
            <CheckCircle2 className="w-4 h-4 shrink-0 text-emerald-600" />
            <span>{logoMessage}</span>
          </div>
        )}

        <div className="flex flex-col md:flex-row items-center gap-6 p-4 rounded-2xl bg-slate-50/70 border border-slate-100">
          <div className="relative group shrink-0">
            <div className="w-28 h-28 rounded-2xl bg-white border-2 border-slate-200 shadow-xs flex items-center justify-center p-2.5 overflow-hidden">
              <img
                src={logoPreview || settings.logoUrl || '/assets/uss-logo.png'}
                alt="Company Logo"
                className="max-w-full max-h-full object-contain"
                onError={(e: any) => {
                  e.target.src = '/assets/uss-logo.png';
                }}
              />
            </div>
            {logoPreview && (
              <span className="absolute -top-2 -right-2 px-2 py-0.5 bg-amber-500 text-white text-[10px] font-bold rounded-full shadow-sm">
                Unsaved
              </span>
            )}
          </div>

          <div className="flex-1 space-y-3 text-center md:text-left">
            <div>
              <h3 className="text-xs font-bold text-slate-900">Select New Brand Emblem / Logo</h3>
              <p className="text-[11px] text-slate-500 mt-0.5">
                PNG, JPG, SVG, WebP. Transparent background recommended. Auto-resized for web, PDF, and icons.
              </p>
            </div>

            <div className="flex flex-wrap items-center justify-center md:justify-start gap-2.5 pt-1">
              <label className="cursor-pointer inline-flex items-center gap-2 px-4 py-2 bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 rounded-xl text-xs font-bold transition-all shadow-xs">
                <Upload className="w-3.5 h-3.5 text-blue-600" />
                <span>Choose Image</span>
                <input
                  type="file"
                  accept="image/png,image/jpeg,image/webp,image/svg+xml"
                  onChange={handleLogoChange}
                  className="hidden"
                />
              </label>

              {logoPreview && (
                <button
                  type="button"
                  onClick={handleApplyLogo}
                  disabled={logoUploading}
                  className="inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-xs transition-all disabled:opacity-50"
                >
                  {logoUploading ? (
                    <>
                      <Loader2 className="w-3.5 h-3.5 animate-spin" />
                      <span>Saving...</span>
                    </>
                  ) : (
                    <>
                      <Save className="w-3.5 h-3.5" />
                      <span>Upload & Apply Logo</span>
                    </>
                  )}
                </button>
              )}

              <button
                type="button"
                onClick={handleResetDefaultLogo}
                disabled={logoUploading}
                className="inline-flex items-center gap-1.5 px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-medium transition-all"
                title="Restore default USS emblem"
              >
                <RotateCcw className="w-3.5 h-3.5" />
                <span>Reset to USS Default</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* 3.3 Dynamic Incident Content & Checkbox Configurations */}
      {/* Incident Classifications */}
      <div className="bg-white p-6 rounded-3xl border border-slate-200 shadow-xs space-y-4">
        <div className="flex items-center justify-between pb-2 border-b border-slate-100">
          <div>
            <h2 className="font-extrabold text-sm text-slate-900 flex items-center gap-2">
              <span>Incident Classifications (Section 4.3)</span>
              <span className="text-[10px] font-bold text-blue-700 bg-blue-50 px-2 py-0.5 rounded-full border border-blue-200">
                {settings.incidentOptions.length} Options
              </span>
            </h2>
            <p className="text-xs text-slate-500">
              Change, add, or delete incident classifications shown to guards on patrol & in reports
            </p>
          </div>
        </div>

        <div className="flex flex-wrap gap-2.5">
          {settings.incidentOptions.map((opt: string, idx: number) => {
            const isEditing =
              editingItem?.field === 'incidentOptions' && editingItem.index === idx;

            if (isEditing) {
              return (
                <div
                  key={idx}
                  className="inline-flex items-center gap-1.5 p-1 bg-white border-2 border-blue-600 rounded-xl shadow-xs"
                >
                  <input
                    type="text"
                    autoFocus
                    value={editingItem.value}
                    onChange={(e) =>
                      setEditingItem({ ...editingItem, value: e.target.value })
                    }
                    onKeyDown={(e) => {
                      if (e.key === 'Enter') {
                        e.preventDefault();
                        saveEditOption();
                      } else if (e.key === 'Escape') {
                        cancelEditOption();
                      }
                    }}
                    className="px-2 py-0.5 text-xs font-bold text-slate-900 border-none outline-none w-36 bg-transparent"
                  />
                  <button
                    type="button"
                    onClick={saveEditOption}
                    className="p-1 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                    title="Save change"
                  >
                    <Check className="w-3.5 h-3.5" />
                  </button>
                  <button
                    type="button"
                    onClick={cancelEditOption}
                    className="p-1 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100 transition-colors"
                    title="Cancel"
                  >
                    <X className="w-3.5 h-3.5" />
                  </button>
                </div>
              );
            }

            return (
              <span
                key={idx}
                className="group inline-flex items-center gap-2 px-3 py-1.5 bg-blue-50 text-blue-950 rounded-xl text-xs font-bold border border-blue-200 hover:border-blue-400 transition-all shadow-2xs"
              >
                <span>{opt}</span>
                <div className="flex items-center gap-1 border-l border-blue-200 pl-1.5">
                  <button
                    type="button"
                    onClick={() => startEditOption('incidentOptions', idx, opt)}
                    className="text-blue-500 hover:text-blue-800 p-0.5 rounded hover:bg-blue-100 transition-colors"
                    title="Rename option"
                  >
                    <Pencil className="w-3 h-3" />
                  </button>
                  <button
                    type="button"
                    onClick={() => removeOption('incidentOptions', idx)}
                    className="text-slate-400 hover:text-rose-600 p-0.5 rounded hover:bg-rose-50 transition-colors"
                    title="Delete option"
                  >
                    <X className="w-3 h-3" />
                  </button>
                </div>
              </span>
            );
          })}
        </div>

        <div className="flex gap-2 text-xs pt-1">
          <input
            type="text"
            placeholder="Add new incident classification..."
            value={newIncidentOpt}
            onChange={(e) => setNewIncidentOpt(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === 'Enter') {
                e.preventDefault();
                addOption('incidentOptions', newIncidentOpt, setNewIncidentOpt);
              }
            }}
            className="flex-1 p-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 focus:outline-none"
          />
          <button
            type="button"
            onClick={() => addOption('incidentOptions', newIncidentOpt, setNewIncidentOpt)}
            className="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-xl font-bold flex items-center gap-1.5 transition-all shadow-2xs"
          >
            <Plus className="w-4 h-4" />
            <span>Add</span>
          </button>
        </div>
      </div>

      {/* Disciplinary Infractions */}
      <div className="bg-white p-6 rounded-3xl border border-slate-200 shadow-xs space-y-4">
        <div className="flex items-center justify-between pb-2 border-b border-slate-100">
          <div>
            <h2 className="font-extrabold text-sm text-slate-900 flex items-center gap-2">
              <span>Disciplinary Infractions (Section 4.2)</span>
              <span className="text-[10px] font-bold text-amber-800 bg-amber-50 px-2 py-0.5 rounded-full border border-amber-200">
                {settings.disciplinaryOptions.length} Options
              </span>
            </h2>
            <p className="text-xs text-slate-500">
              Configure infraction categories under Disciplinary cause
            </p>
          </div>
        </div>

        <div className="flex flex-wrap gap-2.5">
          {settings.disciplinaryOptions.map((opt: string, idx: number) => {
            const isEditing =
              editingItem?.field === 'disciplinaryOptions' && editingItem.index === idx;

            if (isEditing) {
              return (
                <div
                  key={idx}
                  className="inline-flex items-center gap-1.5 p-1 bg-white border-2 border-amber-500 rounded-xl shadow-xs"
                >
                  <input
                    type="text"
                    autoFocus
                    value={editingItem.value}
                    onChange={(e) =>
                      setEditingItem({ ...editingItem, value: e.target.value })
                    }
                    onKeyDown={(e) => {
                      if (e.key === 'Enter') {
                        e.preventDefault();
                        saveEditOption();
                      } else if (e.key === 'Escape') {
                        cancelEditOption();
                      }
                    }}
                    className="px-2 py-0.5 text-xs font-bold text-slate-900 border-none outline-none w-36 bg-transparent"
                  />
                  <button
                    type="button"
                    onClick={saveEditOption}
                    className="p-1 bg-amber-600 text-white rounded-lg hover:bg-amber-700 transition-colors"
                    title="Save change"
                  >
                    <Check className="w-3.5 h-3.5" />
                  </button>
                  <button
                    type="button"
                    onClick={cancelEditOption}
                    className="p-1 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100 transition-colors"
                    title="Cancel"
                  >
                    <X className="w-3.5 h-3.5" />
                  </button>
                </div>
              );
            }

            return (
              <span
                key={idx}
                className="group inline-flex items-center gap-2 px-3 py-1.5 bg-amber-50 text-amber-950 rounded-xl text-xs font-bold border border-amber-300 hover:border-amber-400 transition-all shadow-2xs"
              >
                <span>{opt}</span>
                <div className="flex items-center gap-1 border-l border-amber-200 pl-1.5">
                  <button
                    type="button"
                    onClick={() => startEditOption('disciplinaryOptions', idx, opt)}
                    className="text-amber-600 hover:text-amber-800 p-0.5 rounded hover:bg-amber-100 transition-colors"
                    title="Rename option"
                  >
                    <Pencil className="w-3 h-3" />
                  </button>
                  <button
                    type="button"
                    onClick={() => removeOption('disciplinaryOptions', idx)}
                    className="text-slate-400 hover:text-rose-600 p-0.5 rounded hover:bg-rose-50 transition-colors"
                    title="Delete option"
                  >
                    <X className="w-3 h-3" />
                  </button>
                </div>
              </span>
            );
          })}
        </div>

        <div className="flex gap-2 text-xs pt-1">
          <input
            type="text"
            placeholder="Add new disciplinary reason..."
            value={newDisciplinaryOpt}
            onChange={(e) => setNewDisciplinaryOpt(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === 'Enter') {
                e.preventDefault();
                addOption('disciplinaryOptions', newDisciplinaryOpt, setNewDisciplinaryOpt);
              }
            }}
            className="flex-1 p-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-amber-500 focus:outline-none"
          />
          <button
            type="button"
            onClick={() => addOption('disciplinaryOptions', newDisciplinaryOpt, setNewDisciplinaryOpt)}
            className="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-xl font-bold flex items-center gap-1.5 transition-all shadow-2xs"
          >
            <Plus className="w-4 h-4" />
            <span>Add</span>
          </button>
        </div>
      </div>

      {/* Authorities & Who Was Notified */}
      <div className="bg-white p-6 rounded-3xl border border-slate-200 shadow-xs space-y-4">
        <div className="flex items-center justify-between pb-2 border-b border-slate-100">
          <div>
            <h2 className="font-extrabold text-sm text-slate-900 flex items-center gap-2">
              <span>Who Was Notified Checklist (Section 4.5)</span>
              <span className="text-[10px] font-bold text-slate-700 bg-slate-100 px-2 py-0.5 rounded-full border border-slate-300">
                {settings.notifiedOptions.length} Options
              </span>
            </h2>
            <p className="text-xs text-slate-500">
              Editable list of notified entities (including "USS Management", Police, Civil Defense, etc.)
            </p>
          </div>
        </div>

        <div className="flex flex-wrap gap-2.5">
          {settings.notifiedOptions.map((opt: string, idx: number) => {
            const isEditing =
              editingItem?.field === 'notifiedOptions' && editingItem.index === idx;

            if (isEditing) {
              return (
                <div
                  key={idx}
                  className="inline-flex items-center gap-1.5 p-1 bg-white border-2 border-slate-600 rounded-xl shadow-xs"
                >
                  <input
                    type="text"
                    autoFocus
                    value={editingItem.value}
                    onChange={(e) =>
                      setEditingItem({ ...editingItem, value: e.target.value })
                    }
                    onKeyDown={(e) => {
                      if (e.key === 'Enter') {
                        e.preventDefault();
                        saveEditOption();
                      } else if (e.key === 'Escape') {
                        cancelEditOption();
                      }
                    }}
                    className="px-2 py-0.5 text-xs font-bold text-slate-900 border-none outline-none w-36 bg-transparent"
                  />
                  <button
                    type="button"
                    onClick={saveEditOption}
                    className="p-1 bg-slate-700 text-white rounded-lg hover:bg-slate-800 transition-colors"
                    title="Save change"
                  >
                    <Check className="w-3.5 h-3.5" />
                  </button>
                  <button
                    type="button"
                    onClick={cancelEditOption}
                    className="p-1 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100 transition-colors"
                    title="Cancel"
                  >
                    <X className="w-3.5 h-3.5" />
                  </button>
                </div>
              );
            }

            return (
              <span
                key={idx}
                className="group inline-flex items-center gap-2 px-3 py-1.5 bg-slate-100 text-slate-900 rounded-xl text-xs font-bold border border-slate-300 hover:border-slate-400 transition-all shadow-2xs"
              >
                <span>{opt}</span>
                <div className="flex items-center gap-1 border-l border-slate-300 pl-1.5">
                  <button
                    type="button"
                    onClick={() => startEditOption('notifiedOptions', idx, opt)}
                    className="text-slate-500 hover:text-slate-800 p-0.5 rounded hover:bg-slate-200 transition-colors"
                    title="Rename label"
                  >
                    <Pencil className="w-3 h-3" />
                  </button>
                  <button
                    type="button"
                    onClick={() => removeOption('notifiedOptions', idx)}
                    className="text-slate-400 hover:text-rose-600 p-0.5 rounded hover:bg-rose-50 transition-colors"
                    title="Delete option"
                  >
                    <X className="w-3 h-3" />
                  </button>
                </div>
              </span>
            );
          })}
        </div>

        <div className="flex gap-2 text-xs pt-1">
          <input
            type="text"
            placeholder="Add emergency agency or organization..."
            value={newNotifiedOpt}
            onChange={(e) => setNewNotifiedOpt(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === 'Enter') {
                e.preventDefault();
                addOption('notifiedOptions', newNotifiedOpt, setNewNotifiedOpt);
              }
            }}
            className="flex-1 p-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-slate-500 focus:outline-none"
          />
          <button
            type="button"
            onClick={() => addOption('notifiedOptions', newNotifiedOpt, setNewNotifiedOpt)}
            className="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-xl font-bold flex items-center gap-1.5 transition-all shadow-2xs"
          >
            <Plus className="w-4 h-4" />
            <span>Add</span>
          </button>
        </div>
      </div>

      {/* Shifts & Sign-off Roles */}
      <div className="bg-white p-6 rounded-3xl border border-slate-200 shadow-xs space-y-4">
        <div className="flex items-center justify-between pb-2 border-b border-slate-100">
          <div>
            <h2 className="font-extrabold text-sm text-slate-900 flex items-center gap-2">
              <Layers className="w-4 h-4 text-blue-600" />
              <span>Shift Names & Sign-Off Designations</span>
            </h2>
            <p className="text-xs text-slate-500">
              Customize operational shifts and official signatory titles on the PDF report
            </p>
          </div>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
          <div>
            <label className="block font-bold text-slate-700 mb-1">Sign-Off: Left Box</label>
            <input
              type="text"
              value={settings.signoffLabels?.siteSupervisor || 'Site Supervisor'}
              onChange={(e) =>
                setSettings({
                  ...settings,
                  signoffLabels: {
                    ...settings.signoffLabels,
                    siteSupervisor: e.target.value,
                  },
                })
              }
              className="w-full p-2.5 border border-slate-300 rounded-xl font-semibold"
            />
          </div>

          <div>
            <label className="block font-bold text-slate-700 mb-1">Sign-Off: Middle Box</label>
            <input
              type="text"
              value={settings.signoffLabels?.operationsManager || 'Operations Manager'}
              onChange={(e) =>
                setSettings({
                  ...settings,
                  signoffLabels: {
                    ...settings.signoffLabels,
                    operationsManager: e.target.value,
                  },
                })
              }
              className="w-full p-2.5 border border-slate-300 rounded-xl font-semibold"
            />
          </div>

          <div>
            <label className="block font-bold text-slate-700 mb-1">Sign-Off: Right Box</label>
            <input
              type="text"
              value={settings.signoffLabels?.clientManager || 'Client Manager'}
              onChange={(e) =>
                setSettings({
                  ...settings,
                  signoffLabels: {
                    ...settings.signoffLabels,
                    clientManager: e.target.value,
                  },
                })
              }
              className="w-full p-2.5 border border-slate-300 rounded-xl font-semibold"
            />
          </div>
        </div>
      </div>

      {/* Bottom Save Action Bar */}
      <div className="sticky bottom-4 z-20 bg-white/95 backdrop-blur-md p-4 rounded-3xl border border-slate-200 shadow-xl flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h3 className="text-xs sm:text-sm font-extrabold text-slate-900">
            Apply Company Identity Across Entire System
          </h3>
          <p className="text-[11px] sm:text-xs text-slate-500">
            Updates guard mobile app, dashboard, PDF reports, Excel headers, QR posters, and email notifications immediately.
          </p>
        </div>
        <div className="flex items-center gap-3 shrink-0">
          {success && (
            <span className="text-xs font-bold text-emerald-700 flex items-center gap-1.5">
              <CheckCircle2 className="w-4 h-4 text-emerald-600" />
              <span>Saved & Synced!</span>
            </span>
          )}
          <button
            type="submit"
            disabled={saving}
            className="flex items-center gap-2 px-6 py-3 bg-blue-600 hover:bg-blue-700 active:scale-[0.99] text-white rounded-xl text-xs font-black shadow-md transition-all touch-btn"
          >
            <Save className="w-4 h-4" />
            <span>{saving ? 'Saving Changes...' : 'Save All Settings & Options'}</span>
          </button>
        </div>
      </div>
    </form>
  );
};
