import React, { useEffect, useState } from 'react';
import {
  Building2,
  QrCode,
  Plus,
  RefreshCw,
  Ban,
  Download,
  Printer,
  X,
  MapPin,
  User,
  Phone,
  Mail,
  Pencil,
  Shield,
  Loader2,
  Trash2,
  Globe,
  Wifi,
  Copy,
  Check,
  ExternalLink,
  Sparkles,
  Navigation,
} from 'lucide-react';
import { adminApi } from '../../lib/api';

export const SitesPage: React.FC = () => {
  const [sites, setSites] = useState<any[]>([]);
  const [settings, setSettings] = useState<any>(null);
  const [loading, setLoading] = useState<boolean>(true);

  // Create Site Modal
  const [showCreateModal, setShowCreateModal] = useState<boolean>(false);
  const [name, setName] = useState<string>('');
  const [code, setCode] = useState<string>('');
  const [type, setType] = useState<string>('Commercial');
  const [address, setAddress] = useState<string>('');
  const [clientName, setClientName] = useState<string>('');
  const [clientContact, setClientContact] = useState<string>('');
  const [clientEmail, setClientEmail] = useState<string>('');
  const [clientMobile, setClientMobile] = useState<string>('');
  const [latitude, setLatitude] = useState<string>('');
  const [longitude, setLongitude] = useState<string>('');
  const [creating, setCreating] = useState<boolean>(false);

  // Edit Site Modal
  const [editSiteModal, setEditSiteModal] = useState<any>(null);
  const [editName, setEditName] = useState<string>('');
  const [editCode, setEditCode] = useState<string>('');
  const [editType, setEditType] = useState<string>('Commercial');
  const [editAddress, setEditAddress] = useState<string>('');
  const [editClientName, setEditClientName] = useState<string>('');
  const [editClientContact, setEditClientContact] = useState<string>('');
  const [editClientEmail, setEditClientEmail] = useState<string>('');
  const [editClientMobile, setEditClientMobile] = useState<string>('');
  const [editLatitude, setEditLatitude] = useState<string>('');
  const [editLongitude, setEditLongitude] = useState<string>('');
  const [locatingGps, setLocatingGps] = useState<boolean>(false);
  const [updatingSite, setUpdatingSite] = useState<boolean>(false);

  // QR Poster Modal & Multi-Network Settings
  const [posterSite, setPosterSite] = useState<any>(null);
  const [posterData, setPosterData] = useState<any>(null);
  const [loadingPoster, setLoadingPoster] = useState<boolean>(false);
  const [qrNetworkMode, setQrNetworkMode] = useState<'public' | 'lan'>('public');
  const [customQrUrl, setCustomQrUrl] = useState<string>('');
  const [copiedLink, setCopiedLink] = useState<boolean>(false);

  // Delete Site Modal
  const [deleteSiteTarget, setDeleteSiteTarget] = useState<any>(null);
  const [deletingSite, setDeletingSite] = useState<boolean>(false);

  const loadSites = async () => {
    setLoading(true);
    try {
      const [data, settingsData] = await Promise.all([
        adminApi.listSites(),
        adminApi.getSettings().catch(() => null),
      ]);
      setSites(data);
      if (settingsData) setSettings(settingsData);
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadSites();
  }, []);

  const handleCreateSite = async (e: React.FormEvent) => {
    e.preventDefault();
    setCreating(true);
    try {
      await adminApi.createSite({
        name: name.trim(),
        code: code.toUpperCase().trim(),
        type,
        address: address.trim(),
        clientName: clientName.trim(),
        clientContact: clientContact.trim(),
        clientEmail: clientEmail.trim(),
        clientMobile: clientMobile.trim(),
        latitude: latitude.trim(),
        longitude: longitude.trim(),
      });
      setShowCreateModal(false);
      setName('');
      setCode('');
      setAddress('');
      setClientName('');
      setClientContact('');
      setClientEmail('');
      setClientMobile('');
      setLatitude('');
      setLongitude('');
      loadSites();
    } catch (err: any) {
      alert(err.message || 'Failed to create site');
    } finally {
      setCreating(false);
    }
  };

  const handleGetGps = (isEdit: boolean) => {
    if (!navigator.geolocation) {
      alert('Geolocation is not supported by your browser.');
      return;
    }
    setLocatingGps(true);
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        setLocatingGps(false);
        const latStr = `${pos.coords.latitude.toFixed(6)}° N`;
        const lngStr = `${pos.coords.longitude.toFixed(6)}° E`;
        if (isEdit) {
          setEditLatitude(latStr);
          setEditLongitude(lngStr);
        } else {
          setLatitude(latStr);
          setLongitude(lngStr);
        }
      },
      (err) => {
        setLocatingGps(false);
        alert(`Location detection failed: ${err.message}`);
      },
      { enableHighAccuracy: true, timeout: 10000 }
    );
  };

  const handleOpenEditSite = (site: any) => {
    setEditSiteModal(site);
    setEditName(site.name || '');
    setEditCode(site.code || '');
    setEditType(site.type || 'Commercial');
    setEditAddress(site.address || '');
    setEditClientName(site.client_name || site.clientName || '');
    setEditClientContact(site.client_contact || site.clientContact || '');
    setEditClientEmail(site.client_email || site.clientEmail || '');
    setEditClientMobile(site.client_mobile || site.clientMobile || '');
    setEditLatitude(site.latitude || '');
    setEditLongitude(site.longitude || '');
  };

  const handleUpdateSite = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!editSiteModal) return;
    setUpdatingSite(true);
    try {
      await adminApi.updateSite(editSiteModal.id, {
        name: editName.trim(),
        code: editCode.toUpperCase().trim(),
        type: editType,
        address: editAddress.trim(),
        clientName: editClientName.trim(),
        clientContact: editClientContact.trim(),
        clientEmail: editClientEmail.trim(),
        clientMobile: editClientMobile.trim(),
        latitude: editLatitude.trim(),
        longitude: editLongitude.trim(),
      });
      setEditSiteModal(null);
      await loadSites();
    } catch (err: any) {
      alert(err.message || 'Failed to update site');
    } finally {
      setUpdatingSite(false);
    }
  };

  const handleRegenerateQr = async (siteId: string) => {
    if (!confirm('Regenerate QR Code? Any previously printed QR code for this site will stop working.')) return;
    try {
      await adminApi.generateSiteQr(siteId);
      loadSites();
    } catch (err: any) {
      alert(err.message || 'Failed to regenerate QR');
    }
  };

  const handleRevokeQr = async (siteId: string) => {
    if (!confirm('Revoke QR Code? Security guards will not be able to log in with this site QR.')) return;
    try {
      await adminApi.revokeSiteQr(siteId);
      loadSites();
    } catch (err: any) {
      alert(err.message || 'Failed to revoke QR');
    }
  };

  const handleDeleteSite = async () => {
    if (!deleteSiteTarget) return;
    setDeletingSite(true);
    try {
      await adminApi.deleteSite(deleteSiteTarget.id);
      setDeleteSiteTarget(null);
      await loadSites();
    } catch (err: any) {
      alert(err.message || 'Failed to delete site');
    } finally {
      setDeletingSite(false);
    }
  };

  const openQrPoster = async (
    site: any,
    overrideBaseUrl?: string,
    targetMode?: 'public' | 'lan'
  ) => {
    setPosterSite(site);
    setLoadingPoster(true);
    setCopiedLink(false);

    try {
      const isLocalHost =
        window.location.hostname === 'localhost' ||
        window.location.hostname === '127.0.0.1';
      const isLanIp =
        window.location.hostname.startsWith('192.168.') ||
        window.location.hostname.startsWith('10.');
      const isBrowserOnPublicDomain = !isLocalHost && !isLanIp;

      const effectiveMode = targetMode || qrNetworkMode;
      let effectiveBaseUrl =
        overrideBaseUrl !== undefined ? overrideBaseUrl : customQrUrl;

      // Auto-detect public URL if accessing from a tunnel or public domain
      if (isBrowserOnPublicDomain && !effectiveBaseUrl) {
        effectiveBaseUrl = window.location.origin;
        setCustomQrUrl(window.location.origin);
      }

      let requestedUrl = '';
      if (effectiveMode === 'public') {
        if (overrideBaseUrl && !overrideBaseUrl.includes('trycloudflare.com') && !overrideBaseUrl.includes('loca.lt')) {
          requestedUrl = overrideBaseUrl;
        } else if (customQrUrl && !customQrUrl.includes('trycloudflare.com') && !customQrUrl.includes('loca.lt')) {
          requestedUrl = customQrUrl;
        } else if (isBrowserOnPublicDomain) {
          requestedUrl = window.location.origin;
        }
      } else {
        requestedUrl = effectiveBaseUrl && (effectiveBaseUrl.includes('192.168.') || effectiveBaseUrl.includes('10.')) ? effectiveBaseUrl : '';
      }

      const res = await adminApi.getSiteQrPoster(site.id, requestedUrl, effectiveMode);
      setPosterData(res);

      if (res.activeTunnelUrl && effectiveMode === 'public') {
        setCustomQrUrl(res.activeTunnelUrl);
      } else if (!customQrUrl && isBrowserOnPublicDomain && effectiveMode === 'public') {
        setCustomQrUrl(window.location.origin);
      }
    } catch (err: any) {
      alert(err.message || 'Failed to fetch poster');
      setPosterSite(null);
    } finally {
      setLoadingPoster(false);
    }
  };

  const printPoster = () => {
    window.print();
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-xl md:text-2xl font-extrabold text-slate-900 tracking-tight">
            Client Sites & Patrol QR Codes
          </h1>
          <p className="text-xs text-slate-500 mt-0.5">
            Manage deployed sites and generate printable security QR checkpoint posters
          </p>
        </div>

        <button
          onClick={() => setShowCreateModal(true)}
          className="flex items-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-sm transition-all"
        >
          <Plus className="w-4 h-4" />
          <span>Add New Site</span>
        </button>
      </div>

      {/* Sites Grid */}
      {loading ? (
        <div className="flex justify-center py-20">
          <Loader2 className="w-8 h-8 animate-spin text-blue-600" />
        </div>
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-5 gap-3.5">
          {sites.map((site) => (
            <div
              key={site.id}
              className="min-w-0 bg-white rounded-2xl border border-slate-200/90 p-3.5 shadow-xs flex flex-col justify-between hover:shadow-md transition-shadow"
            >
              <div className="space-y-2.5 min-w-0">
                {/* Header: Icon, Name, Code/Type & Status Badge */}
                <div className="flex items-start justify-between gap-1.5 min-w-0">
                  <div className="flex items-center gap-2 min-w-0">
                    <div className="w-8 h-8 rounded-xl bg-blue-50 text-blue-700 flex items-center justify-center font-bold text-xs shrink-0 border border-blue-100">
                      <Building2 className="w-4 h-4 text-blue-600" />
                    </div>
                    <div className="min-w-0">
                      <h3 className="font-extrabold text-xs text-slate-900 leading-tight truncate" title={site.name}>
                        {site.name}
                      </h3>
                      <div className="flex items-center gap-1 mt-0.5">
                        <span className="text-[9px] font-mono font-bold text-blue-700 bg-blue-50 px-1 py-0.2 rounded border border-blue-100 truncate max-w-[85px]" title={site.code}>
                          {site.code}
                        </span>
                        <span className="text-[9px] font-bold text-slate-400 uppercase tracking-wider truncate">
                          {site.type}
                        </span>
                      </div>
                    </div>
                  </div>

                  <span
                    className={`px-2 py-0.5 rounded-full text-[9px] font-bold shrink-0 ${
                      site.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-500'
                    }`}
                  >
                    {site.is_active ? 'Active' : 'Archived'}
                  </span>
                </div>

                {/* Details List */}
                <div className="space-y-1 text-[11px] text-slate-600 pt-2 border-t border-slate-100 min-w-0">
                  <div className="flex items-center gap-1.5 truncate" title={site.address}>
                    <MapPin className="w-3 h-3 text-slate-400 shrink-0" />
                    <span className="truncate">{site.address || 'Address not specified'}</span>
                  </div>
                  <div className="flex items-center gap-1.5 truncate" title={site.client_name || site.clientName}>
                    <User className="w-3 h-3 text-slate-400 shrink-0" />
                    <span className="truncate">Client: <strong className="text-slate-800">{site.client_name || site.clientName || 'N/A'}</strong></span>
                  </div>
                  {(site.client_contact || site.clientContact) && (
                    <div className="flex items-center gap-1.5 truncate" title={site.client_contact || site.clientContact}>
                      <User className="w-3 h-3 text-slate-400 shrink-0" />
                      <span className="truncate">Rep: {site.client_contact || site.clientContact}</span>
                    </div>
                  )}
                  {(site.client_email || site.clientEmail) && (
                    <div className="flex items-center gap-1.5 truncate" title={site.client_email || site.clientEmail}>
                      <Mail className="w-3 h-3 text-blue-500 shrink-0" />
                      <span className="truncate text-blue-700 font-mono text-[10px]">{site.client_email || site.clientEmail}</span>
                    </div>
                  )}
                  {(site.client_mobile || site.clientMobile) && (
                    <div className="flex items-center gap-1.5 truncate" title={site.client_mobile || site.clientMobile}>
                      <Phone className="w-3 h-3 text-emerald-600 shrink-0" />
                      <span className="truncate text-slate-700 font-mono text-[10px]">{site.client_mobile || site.clientMobile}</span>
                    </div>
                  )}
                  {(site.latitude || site.longitude) && (
                    <div className="flex items-center gap-1.5 truncate" title={`GPS: ${site.latitude || 'N/A'}, ${site.longitude || 'N/A'}`}>
                      <Navigation className="w-3 h-3 text-blue-600 shrink-0" />
                      <span className="truncate text-slate-800 font-mono text-[10px]">
                        GPS: <strong>{site.latitude || 'N/A'}, {site.longitude || 'N/A'}</strong>
                      </span>
                    </div>
                  )}
                </div>
              </div>

              {/* QR Management Bottom Bar */}
              <div className="pt-2.5 mt-2.5 border-t border-slate-100 space-y-2 min-w-0">
                <div className="flex items-center justify-between text-[11px]">
                  <span className="text-[10px] font-semibold text-slate-500">QR Patrol:</span>
                  {site.active_qr_token ? (
                    <span className="text-[10px] font-mono text-emerald-600 font-bold flex items-center gap-1">
                      <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Active
                    </span>
                  ) : (
                    <span className="text-[10px] text-red-500 font-bold">No QR</span>
                  )}
                </div>

                <div className="flex flex-col gap-1.5 pt-0.5">
                  <button
                    onClick={() => openQrPoster(site)}
                    disabled={!site.active_qr_token}
                    className="w-full flex items-center justify-center gap-1.5 py-1.5 bg-slate-900 hover:bg-slate-800 disabled:opacity-40 text-white rounded-lg text-xs font-bold transition-colors shadow-xs"
                  >
                    <QrCode className="w-3.5 h-3.5 text-amber-400" />
                    <span>View QR Poster</span>
                  </button>

                  <div className="grid grid-cols-4 gap-1">
                    <button
                      onClick={() => handleRegenerateQr(site.id)}
                      title="Regenerate QR Token"
                      className="flex items-center justify-center py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-bold transition-colors"
                    >
                      <RefreshCw className="w-3 h-3" />
                    </button>
                    {site.active_qr_token ? (
                      <button
                        onClick={() => handleRevokeQr(site.id)}
                        title="Revoke QR Token"
                        className="flex items-center justify-center py-1.5 bg-amber-50 hover:bg-amber-100 text-amber-600 rounded-lg text-xs font-bold transition-colors"
                      >
                        <Ban className="w-3 h-3" />
                      </button>
                    ) : (
                      <div className="py-1.5 bg-slate-50 rounded-lg opacity-30 flex items-center justify-center">
                        <Ban className="w-3 h-3 text-slate-400" />
                      </div>
                    )}
                    <button
                      onClick={() => handleOpenEditSite(site)}
                      title="Edit Site Details"
                      className="flex items-center justify-center py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-lg text-xs font-bold transition-colors"
                    >
                      <Pencil className="w-3 h-3" />
                    </button>
                    <button
                      onClick={() => setDeleteSiteTarget(site)}
                      title="Delete Site"
                      className="flex items-center justify-center py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-lg text-xs font-bold transition-colors"
                    >
                      <Trash2 className="w-3 h-3" />
                    </button>
                  </div>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Printable QR Poster Modal */}
      {posterSite && (
        <div className="fixed inset-0 z-50 bg-black/75 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4 overflow-y-auto">
          <div className="bg-white rounded-3xl shadow-2xl max-w-lg w-full overflow-hidden border border-slate-200 print:shadow-none print:border-none print:max-w-none print:m-0 my-auto">
            {/* Modal Controls (Hidden in Print) */}
            <div className="bg-slate-900 text-white p-4 flex items-center justify-between print:hidden border-b border-slate-800">
              <div className="flex items-center gap-2">
                <div className="w-8 h-8 rounded-xl bg-blue-600/30 border border-blue-500/40 flex items-center justify-center">
                  <QrCode className="w-4 h-4 text-blue-400" />
                </div>
                <div>
                  <span className="font-bold text-xs block leading-tight">Printable Site QR Poster</span>
                  <span className="text-[10px] text-slate-400 font-mono">{posterSite.code}</span>
                </div>
              </div>
              <div className="flex items-center gap-2">
                <button
                  onClick={printPoster}
                  className="flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-bold transition shadow-xs"
                >
                  <Printer className="w-3.5 h-3.5" />
                  <span>Print Poster</span>
                </button>
                <button
                  onClick={() => setPosterSite(null)}
                  className="p-1.5 text-slate-400 hover:text-white rounded-lg hover:bg-slate-800 transition"
                >
                  <X className="w-5 h-5" />
                </button>
              </div>
            </div>

            {/* Network Compatibility Controls (Hidden in Print) */}
            <div className="bg-slate-900 text-slate-200 p-4 border-b border-slate-800 space-y-3 print:hidden">
              <div className="flex items-center justify-between">
                <span className="text-[11px] font-bold uppercase tracking-wider text-slate-400">
                  Network Compatibility
                </span>
                <span className="text-[10px] px-2 py-0.5 rounded-full font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 flex items-center gap-1">
                  <Sparkles className="w-3 h-3" />
                  {qrNetworkMode === 'public' ? 'Works on Any Network' : 'Same Router Only'}
                </span>
              </div>

              {/* Mode Pills */}
              <div className="grid grid-cols-2 gap-2 p-1 bg-slate-800/80 rounded-xl border border-slate-700/60 text-xs">
                <button
                  type="button"
                  onClick={() => {
                    setQrNetworkMode('public');
                    openQrPoster(posterSite, customQrUrl, 'public');
                  }}
                  className={`flex items-center justify-center gap-1.5 py-1.5 px-2 rounded-lg font-bold transition ${
                    qrNetworkMode === 'public'
                      ? 'bg-blue-600 text-white shadow-xs'
                      : 'text-slate-400 hover:text-white'
                  }`}
                >
                  <Globe className="w-3.5 h-3.5" />
                  <span>Any Network (4G/5G/Wi-Fi)</span>
                </button>
                <button
                  type="button"
                  onClick={() => {
                    setQrNetworkMode('lan');
                    openQrPoster(posterSite, '', 'lan');
                  }}
                  className={`flex items-center justify-center gap-1.5 py-1.5 px-2 rounded-lg font-bold transition ${
                    qrNetworkMode === 'lan'
                      ? 'bg-blue-600 text-white shadow-xs'
                      : 'text-slate-400 hover:text-white'
                  }`}
                >
                  <Wifi className="w-3.5 h-3.5" />
                  <span>Local Wi-Fi Only</span>
                </button>
              </div>

              {/* Mode Explanation & Config */}
              {qrNetworkMode === 'public' ? (
                <div className="space-y-2.5 bg-slate-950/70 p-3 rounded-xl border border-slate-800 text-xs">
                  {posterData?.tunnelActive ? (
                    <div className="flex items-center justify-between gap-2 p-2 bg-emerald-950/60 border border-emerald-800/80 rounded-lg text-emerald-300 text-[11px]">
                      <div className="flex items-center gap-2 min-w-0">
                        <span className="w-2.5 h-2.5 rounded-full bg-emerald-400 shrink-0 animate-pulse"></span>
                        <span className="truncate"><strong>Live Cloudflare Tunnel:</strong> {posterData.activeTunnelUrl}</span>
                      </div>
                      <button
                        type="button"
                        onClick={() => {
                          setCustomQrUrl(posterData.activeTunnelUrl);
                          openQrPoster(posterSite, posterData.activeTunnelUrl, 'public');
                        }}
                        className="px-2 py-0.5 bg-emerald-700/80 hover:bg-emerald-600 text-white font-bold rounded text-[10px] shrink-0"
                      >
                        Use Tunnel
                      </button>
                    </div>
                  ) : (
                    <div className="flex items-center gap-2 p-2 bg-amber-950/50 border border-amber-800/60 rounded-lg text-amber-300 text-[11px]">
                      <Globe className="w-4 h-4 shrink-0 text-amber-400" />
                      <span><strong>Public Tunnel is offline.</strong> To enable 4G/5G mobile access, run <code className="bg-amber-900/60 px-1 py-0.5 rounded text-white font-mono">npm run tunnel</code> or enter a live public domain below.</span>
                    </div>
                  )}

                  <div className="flex items-start gap-2 text-slate-300 text-[11px] leading-relaxed">
                    <Globe className="w-4 h-4 text-emerald-400 shrink-0 mt-0.5" />
                    <p>
                      Generates a QR code with a <strong className="text-white">Public HTTPS URL</strong> so guards can scan from <strong className="text-emerald-400">any UAE carrier (4G/5G, du, Etisalat) or any guest Wi-Fi</strong> worldwide.
                    </p>
                  </div>
                  <div className="flex items-center gap-2">
                    <input
                      type="text"
                      placeholder="e.g. https://xxx.trycloudflare.com or https://incidents.mycompany.com"
                      value={customQrUrl}
                      onChange={(e) => setCustomQrUrl(e.target.value)}
                      className="bg-slate-900 border border-slate-700 text-slate-100 px-3 py-1.5 rounded-lg text-xs font-mono flex-1 focus:ring-1 focus:ring-blue-500 focus:outline-hidden"
                    />
                    <button
                      type="button"
                      onClick={() => openQrPoster(posterSite, customQrUrl, 'public')}
                      disabled={loadingPoster}
                      className="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white rounded-lg text-xs font-bold shrink-0 transition"
                    >
                      Update QR
                    </button>
                  </div>
                  <div className="pt-1 text-[10px] text-slate-400 flex flex-wrap items-center justify-between gap-1 border-t border-slate-800/80 mt-1">
                    <span>💡 Free Tunnel: run <code className="bg-slate-800 px-1 py-0.5 rounded text-amber-300 font-mono">npm run tunnel</code></span>
                    {posterData?.tunnelPassword && (
                      <span className="text-amber-300 font-mono">
                        Password: <strong>{posterData.tunnelPassword}</strong>
                      </span>
                    )}
                    {window.location.origin && !window.location.hostname.includes('localhost') && !window.location.hostname.startsWith('192.168.') && !window.location.hostname.startsWith('10.') && (
                      <button
                        type="button"
                        onClick={() => {
                          setCustomQrUrl(window.location.origin);
                          openQrPoster(posterSite, window.location.origin, 'public');
                        }}
                        className="text-blue-400 hover:underline font-bold"
                      >
                        Use current URL ({window.location.hostname})
                      </button>
                    )}
                  </div>
                </div>
              ) : (
                <div className="bg-amber-950/40 p-3 rounded-xl border border-amber-800/60 text-[11px] text-amber-200/90 leading-relaxed space-y-2">
                  <div className="flex items-center gap-1.5 font-bold text-amber-300">
                    <Wifi className="w-3.5 h-3.5" />
                    <span>Local Wi-Fi Router Access Only</span>
                  </div>
                  <p>
                    Guard mobile phones must be connected to the <strong>exact same Wi-Fi router</strong> as this server. Will <strong>NOT</strong> open on 4G, 5G, or other networks.
                  </p>
                  {posterData?.lanTargetUrls && posterData.lanTargetUrls.length > 0 && (
                    <div className="pt-2 border-t border-amber-800/50 space-y-1.5">
                      <span className="text-[10px] uppercase font-bold text-slate-300 block">Select Server Wi-Fi Network IP:</span>
                      <div className="flex flex-wrap gap-1.5">
                        {posterData.lanTargetUrls.map((item: any) => (
                          <button
                            key={item.ip}
                            type="button"
                            onClick={() => {
                              const chosen = `http://${item.ip}:5173`;
                              setCustomQrUrl(chosen);
                              openQrPoster(posterSite, chosen, 'lan');
                            }}
                            className={`px-2.5 py-1 rounded text-xs font-mono border transition ${
                              posterData.targetUrl?.includes(item.ip)
                                ? 'bg-amber-500 text-slate-950 font-bold border-amber-400 shadow-xs'
                                : 'bg-slate-900 text-slate-200 hover:bg-slate-800 border-slate-700'
                            }`}
                          >
                            {item.ip}
                          </button>
                        ))}
                      </div>
                    </div>
                  )}
                </div>
              )}
            </div>

            {/* Poster Content Canvas Designed for Physical A4 Print */}
            {loadingPoster ? (
              <div className="p-12 text-center">
                <Loader2 className="w-8 h-8 animate-spin mx-auto text-blue-600" />
              </div>
            ) : (
              <div className="p-8 text-center space-y-6 bg-white">
                {/* Header */}
                <div className="space-y-1">
                  <div className="mx-auto flex items-center justify-center mb-2">
                    <img
                      src={settings?.logoUrl || '/assets/uss-logo.png'}
                      alt={settings?.companyName || 'Ultimate Security Services'}
                      className="h-16 w-auto max-w-[200px] object-contain mx-auto"
                      onError={(e: any) => {
                        e.currentTarget.src = '/assets/uss-logo.png';
                      }}
                    />
                  </div>
                  <h2 className="text-xl font-black text-slate-900 tracking-tight uppercase">
                    {settings?.companyName || 'ULTIMATE SECURITY SERVICES'}
                  </h2>
                  <p className="text-[10px] text-slate-500 font-bold uppercase tracking-widest">
                    Dubai Operations • Patrol Checkpoint
                  </p>
                </div>

                {/* QR Code Container */}
                <div className="p-4 bg-white border-4 border-slate-900 rounded-3xl inline-block shadow-lg mx-auto">
                  {posterData?.qrDataUrl ? (
                    <img
                      src={posterData.qrDataUrl}
                      alt="Site Patrol QR Code"
                      className="w-64 h-64 mx-auto object-contain"
                    />
                  ) : (
                    <div className="w-64 h-64 flex items-center justify-center text-slate-400">
                      QR Code
                    </div>
                  )}
                </div>

                {/* Mobile Network Link */}
                {posterData?.targetUrl && (
                  <div className="p-2.5 bg-blue-50/70 border border-blue-200 rounded-xl text-left print:hidden flex items-center justify-between gap-2 text-xs">
                    <div className="min-w-0">
                      <div className="flex items-center gap-1.5">
                        <span className="text-[10px] uppercase font-bold text-blue-700 tracking-wider">
                          Mobile Checkpoint Link
                        </span>
                        {qrNetworkMode === 'public' ? (
                          <span className="text-[9px] font-bold px-1.5 py-0.2 rounded bg-emerald-100 text-emerald-800">
                            Global Access
                          </span>
                        ) : (
                          <span className="text-[9px] font-bold px-1.5 py-0.2 rounded bg-amber-100 text-amber-800">
                            Local Wi-Fi Only
                          </span>
                        )}
                      </div>
                      <p className="font-mono text-slate-700 truncate text-[11px]">
                        {posterData.targetUrl}
                      </p>
                    </div>
                    <div className="flex items-center gap-1.5 shrink-0">
                      <a
                        href={posterData.targetUrl}
                        target="_blank"
                        rel="noreferrer"
                        className="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-[11px] font-bold flex items-center gap-1 transition"
                      >
                        <ExternalLink className="w-3 h-3 text-slate-500" />
                        <span>Test Link</span>
                      </a>
                      <button
                        type="button"
                        onClick={() => {
                          navigator.clipboard?.writeText(posterData.targetUrl);
                          setCopiedLink(true);
                          setTimeout(() => setCopiedLink(false), 2000);
                        }}
                        className="px-2.5 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-[11px] font-bold flex items-center gap-1 transition shadow-xs"
                      >
                        {copiedLink ? <Check className="w-3 h-3 text-emerald-300" /> : <Copy className="w-3 h-3" />}
                        <span>{copiedLink ? 'Copied!' : 'Copy Link'}</span>
                      </button>
                    </div>
                  </div>
                )}

                {/* Site Name printed clearly under the QR code as required! */}
                <div className="space-y-1">
                  <span className="text-[10px] font-bold text-blue-700 tracking-widest uppercase block">
                    Security Patrol Checkpoint
                  </span>
                  <h3 className="text-2xl font-black text-[#0F2042] tracking-tight">
                    {posterSite.name}
                  </h3>
                  <p className="text-xs font-mono font-bold text-slate-600">
                    Site Code: {posterSite.code} • {posterSite.type}
                  </p>
                  <p className="text-[11px] text-slate-500 pt-1">
                    {posterSite.address}
                  </p>
                </div>

                {/* Guard Instructions */}
                <div className="p-3 bg-slate-50 rounded-2xl border border-slate-200 text-[11px] text-slate-600 space-y-0.5">
                  <p className="font-bold text-slate-800">Security Guard Patrol Instructions:</p>
                  <p>1. Scan this QR code with your mobile phone camera.</p>
                  <p>2. Enter your authorized guard username and password.</p>
                  <p>3. Submit incident report with photos to complete patrol log.</p>
                </div>
              </div>
            )}
          </div>
        </div>
      )}

      {/* Add New Site Modal */}
      {showCreateModal && (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl p-6 shadow-2xl max-w-md w-full space-y-4">
            <div className="flex items-center justify-between pb-3 border-b border-slate-100">
              <h2 className="font-extrabold text-base text-slate-900">Add New Client Site</h2>
              <button onClick={() => setShowCreateModal(false)} className="text-slate-400 hover:text-slate-700">
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleCreateSite} className="space-y-3 text-xs">
              <div>
                <label className="block font-bold text-slate-700 mb-1">Site / Building Name</label>
                <input
                  type="text"
                  required
                  placeholder="e.g. Dubai Hills Estate Villas"
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  className="w-full p-2.5 border border-slate-300 rounded-xl"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block font-bold text-slate-700 mb-1">Site Code</label>
                  <input
                    type="text"
                    required
                    placeholder="e.g. DHE-VIL"
                    value={code}
                    onChange={(e) => setCode(e.target.value.toUpperCase())}
                    className="w-full p-2.5 border border-slate-300 rounded-xl uppercase font-mono"
                  />
                </div>
                <div>
                  <label className="block font-bold text-slate-700 mb-1">Site Type</label>
                  <select
                    value={type}
                    onChange={(e) => setType(e.target.value)}
                    className="w-full p-2.5 border border-slate-300 rounded-xl font-bold"
                  >
                    <option value="Commercial">Commercial</option>
                    <option value="Residential">Residential</option>
                    <option value="Hospital">Hospital</option>
                  </select>
                </div>
              </div>

              <div>
                <label className="block font-bold text-slate-700 mb-1">Physical Address</label>
                <input
                  type="text"
                  required
                  placeholder="Street, Community, Dubai, UAE"
                  value={address}
                  onChange={(e) => setAddress(e.target.value)}
                  className="w-full p-2.5 border border-slate-300 rounded-xl"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block font-bold text-slate-700 mb-1">Client Entity</label>
                  <input
                    type="text"
                    required
                    placeholder="Client Company Name"
                    value={clientName}
                    onChange={(e) => setClientName(e.target.value)}
                    className="w-full p-2.5 border border-slate-300 rounded-xl"
                  />
                </div>
                <div>
                  <label className="block font-bold text-slate-700 mb-1">Client Contact</label>
                  <input
                    type="text"
                    placeholder="Representative Name"
                    value={clientContact}
                    onChange={(e) => setClientContact(e.target.value)}
                    className="w-full p-2.5 border border-slate-300 rounded-xl"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block font-bold text-slate-700 mb-1 flex items-center gap-1">
                    <Mail className="w-3.5 h-3.5 text-blue-600" />
                    <span>Client Email ID</span>
                  </label>
                  <input
                    type="email"
                    placeholder="e.g. ops@client.ae"
                    value={clientEmail}
                    onChange={(e) => setClientEmail(e.target.value)}
                    className="w-full p-2.5 border border-slate-300 rounded-xl font-medium"
                  />
                </div>
                <div>
                  <label className="block font-bold text-slate-700 mb-1 flex items-center gap-1">
                    <Phone className="w-3.5 h-3.5 text-emerald-600" />
                    <span>Client Mobile Number</span>
                  </label>
                  <input
                    type="tel"
                    placeholder="e.g. +971 50 123 4567"
                    value={clientMobile}
                    onChange={(e) => setClientMobile(e.target.value)}
                    className="w-full p-2.5 border border-slate-300 rounded-xl font-medium"
                  />
                </div>
              </div>

              {/* Latitude and Longitude GPS Coordinates */}
              <div className="bg-slate-50 p-3 rounded-2xl border border-slate-200/80 space-y-2">
                <div className="flex items-center justify-between">
                  <label className="font-bold text-slate-800 flex items-center gap-1.5 text-xs">
                    <Navigation className="w-3.5 h-3.5 text-blue-600" />
                    <span>GPS Coordinates (Lat / Long)</span>
                  </label>
                  <button
                    type="button"
                    onClick={() => handleGetGps(false)}
                    disabled={locatingGps}
                    className="flex items-center gap-1 text-[11px] text-blue-600 hover:text-blue-800 font-bold bg-blue-50 px-2 py-0.5 rounded-md border border-blue-200 transition"
                  >
                    {locatingGps ? <Loader2 className="w-3 h-3 animate-spin" /> : <Navigation className="w-3 h-3" />}
                    <span>{locatingGps ? 'Detecting...' : 'Auto-Detect GPS'}</span>
                  </button>
                </div>
                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <input
                      type="text"
                      placeholder="e.g. 25.1185° N"
                      value={latitude}
                      onChange={(e) => setLatitude(e.target.value)}
                      className="w-full p-2 border border-slate-300 rounded-xl bg-white font-mono text-xs"
                    />
                  </div>
                  <div>
                    <input
                      type="text"
                      placeholder="e.g. 55.1384° E"
                      value={longitude}
                      onChange={(e) => setLongitude(e.target.value)}
                      className="w-full p-2 border border-slate-300 rounded-xl bg-white font-mono text-xs"
                    />
                  </div>
                </div>
              </div>

              <div className="flex justify-end gap-2 pt-3 border-t border-slate-100">
                <button
                  type="button"
                  onClick={() => setShowCreateModal(false)}
                  className="px-4 py-2 border rounded-xl font-semibold"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={creating}
                  className="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold shadow-sm"
                >
                  {creating ? 'Saving...' : 'Create Site & Generate QR'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Edit Site Modal */}
      {editSiteModal && (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl p-6 shadow-2xl max-w-md w-full space-y-4">
            <div className="flex items-center justify-between pb-3 border-b border-slate-100">
              <div>
                <h2 className="font-extrabold text-base text-slate-900">Edit Client Site Details</h2>
                <p className="text-[11px] text-slate-500 font-mono">Site Code: {editSiteModal.code}</p>
              </div>
              <button onClick={() => setEditSiteModal(null)} className="text-slate-400 hover:text-slate-700">
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleUpdateSite} className="space-y-3 text-xs">
              <div>
                <label className="block font-bold text-slate-700 mb-1">Site / Building Name</label>
                <input
                  type="text"
                  required
                  placeholder="e.g. Dubai Hills Estate Villas"
                  value={editName}
                  onChange={(e) => setEditName(e.target.value)}
                  className="w-full p-2.5 border border-slate-300 rounded-xl font-medium"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block font-bold text-slate-700 mb-1">Site Code</label>
                  <input
                    type="text"
                    required
                    placeholder="e.g. DHE-VIL"
                    value={editCode}
                    onChange={(e) => setEditCode(e.target.value.toUpperCase())}
                    className="w-full p-2.5 border border-slate-300 rounded-xl uppercase font-mono font-bold"
                  />
                </div>
                <div>
                  <label className="block font-bold text-slate-700 mb-1">Site Type</label>
                  <select
                    value={editType}
                    onChange={(e) => setEditType(e.target.value)}
                    className="w-full p-2.5 border border-slate-300 rounded-xl font-bold"
                  >
                    <option value="Commercial">Commercial</option>
                    <option value="Residential">Residential</option>
                    <option value="Hospital">Hospital</option>
                  </select>
                </div>
              </div>

              <div>
                <label className="block font-bold text-slate-700 mb-1">Physical Address</label>
                <input
                  type="text"
                  required
                  placeholder="Street, Community, Dubai, UAE"
                  value={editAddress}
                  onChange={(e) => setEditAddress(e.target.value)}
                  className="w-full p-2.5 border border-slate-300 rounded-xl font-medium"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block font-bold text-slate-700 mb-1">Client Entity</label>
                  <input
                    type="text"
                    required
                    placeholder="Client Company Name"
                    value={editClientName}
                    onChange={(e) => setEditClientName(e.target.value)}
                    className="w-full p-2.5 border border-slate-300 rounded-xl font-medium"
                  />
                </div>
                <div>
                  <label className="block font-bold text-slate-700 mb-1">Client Contact</label>
                  <input
                    type="text"
                    placeholder="Representative Name"
                    value={editClientContact}
                    onChange={(e) => setEditClientContact(e.target.value)}
                    className="w-full p-2.5 border border-slate-300 rounded-xl font-medium"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block font-bold text-slate-700 mb-1 flex items-center gap-1">
                    <Mail className="w-3.5 h-3.5 text-blue-600" />
                    <span>Client Email ID</span>
                  </label>
                  <input
                    type="email"
                    placeholder="e.g. ops@client.ae"
                    value={editClientEmail}
                    onChange={(e) => setEditClientEmail(e.target.value)}
                    className="w-full p-2.5 border border-slate-300 rounded-xl font-medium"
                  />
                </div>
                <div>
                  <label className="block font-bold text-slate-700 mb-1 flex items-center gap-1">
                    <Phone className="w-3.5 h-3.5 text-emerald-600" />
                    <span>Client Mobile Number</span>
                  </label>
                  <input
                    type="tel"
                    placeholder="e.g. +971 50 123 4567"
                    value={editClientMobile}
                    onChange={(e) => setEditClientMobile(e.target.value)}
                    className="w-full p-2.5 border border-slate-300 rounded-xl font-medium"
                  />
                </div>
              </div>

              {/* Edit Latitude and Longitude GPS Coordinates */}
              <div className="bg-slate-50 p-3 rounded-2xl border border-slate-200/80 space-y-2">
                <div className="flex items-center justify-between">
                  <label className="font-bold text-slate-800 flex items-center gap-1.5 text-xs">
                    <Navigation className="w-3.5 h-3.5 text-blue-600" />
                    <span>GPS Coordinates (Lat / Long)</span>
                  </label>
                  <button
                    type="button"
                    onClick={() => handleGetGps(true)}
                    disabled={locatingGps}
                    className="flex items-center gap-1 text-[11px] text-blue-600 hover:text-blue-800 font-bold bg-blue-50 px-2 py-0.5 rounded-md border border-blue-200 transition"
                  >
                    {locatingGps ? <Loader2 className="w-3 h-3 animate-spin" /> : <Navigation className="w-3 h-3" />}
                    <span>{locatingGps ? 'Detecting...' : 'Auto-Detect GPS'}</span>
                  </button>
                </div>
                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <input
                      type="text"
                      placeholder="e.g. 25.1185° N"
                      value={editLatitude}
                      onChange={(e) => setEditLatitude(e.target.value)}
                      className="w-full p-2 border border-slate-300 rounded-xl bg-white font-mono text-xs"
                    />
                  </div>
                  <div>
                    <input
                      type="text"
                      placeholder="e.g. 55.1384° E"
                      value={editLongitude}
                      onChange={(e) => setEditLongitude(e.target.value)}
                      className="w-full p-2 border border-slate-300 rounded-xl bg-white font-mono text-xs"
                    />
                  </div>
                </div>
              </div>

              <div className="flex justify-end gap-2 pt-3 border-t border-slate-100">
                <button
                  type="button"
                  onClick={() => setEditSiteModal(null)}
                  className="px-4 py-2 border rounded-xl font-semibold text-slate-700 hover:bg-slate-50"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={updatingSite}
                  className="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold shadow-sm"
                >
                  {updatingSite ? 'Saving...' : 'Save Site Details'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Delete Site Confirmation Modal */}
      {deleteSiteTarget && (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl p-6 shadow-2xl max-w-sm w-full space-y-4 border border-rose-100">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 rounded-2xl bg-rose-100 text-rose-600 flex items-center justify-center shrink-0">
                <Trash2 className="w-5 h-5" />
              </div>
              <div>
                <h3 className="font-extrabold text-base text-slate-900">
                  Delete Client Site
                </h3>
                <p className="text-xs text-slate-500">
                  This action is permanent and cannot be undone.
                </p>
              </div>
            </div>

            <div className="p-3 bg-rose-50/70 border border-rose-100 rounded-2xl text-xs space-y-1">
              <p className="font-bold text-slate-800">
                {deleteSiteTarget.name} ({deleteSiteTarget.code})
              </p>
              <p className="text-slate-600">
                {deleteSiteTarget.address}
              </p>
            </div>

            <p className="text-[11px] text-slate-500">
              Removing this site revokes its active patrol QR codes. Existing incident records linked to this site remain preserved in the audit database.
            </p>

            <div className="flex justify-end gap-2 pt-2">
              <button
                type="button"
                onClick={() => setDeleteSiteTarget(null)}
                className="px-4 py-2 border rounded-xl font-semibold text-xs text-slate-700 hover:bg-slate-50"
              >
                Cancel
              </button>
              <button
                type="button"
                onClick={handleDeleteSite}
                disabled={deletingSite}
                className="px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl font-bold text-xs shadow-sm transition-all"
              >
                {deletingSite ? 'Deleting...' : 'Delete Permanently'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
