import React, { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import {
  Search,
  Filter,
  Download,
  Plus,
  Trash2,
  RotateCcw,
  Eye,
  Building2,
  Calendar,
  Layers,
  CheckSquare,
  Square,
  RefreshCw,
  FileText,
  AlertTriangle,
  Flame,
  CheckCircle,
  Bell,
  X,
  ChevronLeft,
  ChevronRight,
  ExternalLink,
} from 'lucide-react';
import { INCIDENT_STATUSES, INCIDENT_PRIORITIES } from '@uss/shared';
import { adminApi } from '../../lib/api';
import { IncidentDetailModal } from './IncidentDetailModal';
import { useRealtimeIncidents } from '../../hooks/useRealtimeIncidents';

export const IncidentsList: React.FC = () => {
  const [searchParams, setSearchParams] = useSearchParams();
  const activeTabParam = searchParams.get('tab') === 'trash' ? 'trash' : 'active';

  // Pagination state
  const [currentPage, setCurrentPage] = useState<number>(1);
  const pageSize = 10;

  const formatDateDDMMYYYY = (dStr: string) => {
    if (!dStr) return '';
    const parts = dStr.split('-');
    if (parts.length === 3 && parts[0].length === 4) {
      return `${parts[2]}/${parts[1]}/${parts[0]}`;
    }
    return dStr;
  };

  // Filters
  const [siteId, setSiteId] = useState<string>('');
  const [status, setStatus] = useState<string>('');
  const [priorityFilter, setPriorityFilter] = useState<string>('');
  const [search, setSearch] = useState<string>('');
  const [dateFrom, setDateFrom] = useState<string>('');
  const [dateTo, setDateTo] = useState<string>('');
  const [groupBy, setGroupBy] = useState<'none' | 'site' | 'day'>('none');

  // Tab State: 'active' or 'trash'
  const [viewTab, setViewTab] = useState<'active' | 'trash'>(activeTabParam);

  const [activeCount, setActiveCount] = useState<number>(0);
  const [trashCount, setTrashCount] = useState<number>(0);
  const [sites, setSites] = useState<any[]>([]);

  // Real-time SSE hook with automatic reconnection & 10s fallback
  const {
    incidents,
    setIncidents,
    total,
    loading,
    connectionStatus,
    lastUpdated,
    activeToast,
    dismissToast,
    refresh: loadData,
  } = useRealtimeIncidents({
    siteId,
    status,
    priority: priorityFilter,
    search,
    dateFrom,
    dateTo,
    onlyDeleted: viewTab === 'trash',
    includeDeleted: viewTab === 'trash',
    limit: 300,
  });

  // Selected for details or bulk
  const [selectedIncident, setSelectedIncident] = useState<any>(null);
  const [selectedIds, setSelectedIds] = useState<string[]>([]);

  // Manual create modal state
  const [showCreateModal, setShowCreateModal] = useState<boolean>(false);
  const [newSiteId, setNewSiteId] = useState<string>('');
  const [newPriority, setNewPriority] = useState<string>('Medium');
  const [newDate, setNewDate] = useState<string>(new Date().toISOString().slice(0, 10));
  const [newTime, setNewTime] = useState<string>(new Date().toTimeString().slice(0, 5));
  const [newShift, setNewShift] = useState<string>('Day');
  const [newSummary, setNewSummary] = useState<string>('');
  const [newDescription, setNewDescription] = useState<string>('');

  // Sync tab with URL
  useEffect(() => {
    if (searchParams.get('tab') === 'trash') {
      setViewTab('trash');
    } else {
      setViewTab('active');
    }
  }, [searchParams]);

  useEffect(() => {
    adminApi.listSites().then(setSites).catch(console.error);
    adminApi
      .getDashboardStats()
      .then((statsData) => {
        if (statsData) {
          setActiveCount(statsData.totalIncidents || 0);
          setTrashCount(statsData.deletedCount || 0);
        }
      })
      .catch(console.error);
  }, [viewTab]);

  const handleTabChange = (tab: 'active' | 'trash') => {
    setViewTab(tab);
    setSelectedIds([]);
    setSearchParams(tab === 'trash' ? { tab: 'trash' } : {});
  };

  useEffect(() => {
    loadData();
  }, [siteId, status, priorityFilter, dateFrom, dateTo, viewTab]);

  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    loadData();
  };

  const handleSoftDelete = async (id: string, refNumber?: string) => {
    if (!confirm(`Move report "${refNumber || id}" to Trash? It can be restored at any time.`)) return;
    try {
      await adminApi.softDeleteIncident(id);
      await loadData();
    } catch {
      alert('Failed to delete incident');
    }
  };

  const handleRestore = async (id: string, refNumber?: string) => {
    try {
      await adminApi.restoreIncident(id);
      await loadData();
    } catch {
      alert('Failed to restore incident');
    }
  };

  const handlePermanentDelete = async (id: string, refNumber?: string) => {
    if (!confirm(`PERMANENT DELETION: Are you sure you want to permanently erase report "${refNumber || id}"? This cannot be undone.`)) return;
    try {
      await adminApi.permanentDeleteIncident(id);
      await loadData();
    } catch {
      alert('Failed to delete report permanently');
    }
  };

  const handleBulkRestore = async () => {
    if (selectedIds.length === 0) return;
    if (!confirm(`Restore ${selectedIds.length} incident reports to active records?`)) return;
    try {
      for (const id of selectedIds) {
        await adminApi.restoreIncident(id);
      }
      setSelectedIds([]);
      await loadData();
    } catch {
      alert('Failed to restore some reports');
    }
  };

  const handleBulkPermanentDelete = async () => {
    if (selectedIds.length === 0) return;
    if (!confirm(`PERMANENT DELETION: Are you sure you want to permanently erase ${selectedIds.length} reports? This cannot be undone.`)) return;
    try {
      for (const id of selectedIds) {
        await adminApi.permanentDeleteIncident(id);
      }
      setSelectedIds([]);
      await loadData();
    } catch {
      alert('Failed to permanently erase some reports');
    }
  };

  const handleCreateManual = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newSiteId || !newSummary.trim() || !newDescription.trim()) {
      alert('Please fill all required fields');
      return;
    }
    try {
      await adminApi.createIncident({
        siteId: newSiteId,
        priority: newPriority,
        incidentDate: newDate,
        incidentTime: newTime,
        shift: newShift,
        summaryLine: newSummary.trim(),
        description: newDescription.trim(),
        causeMain: ['Incident'],
        incidentTypes: ['Others'],
      });
      setShowCreateModal(false);
      setNewSummary('');
      setNewDescription('');
      setNewPriority('Medium');
      await loadData();
    } catch (err: any) {
      alert(err.message || 'Failed to create report');
    }
  };

  const toggleSelectAll = () => {
    if (selectedIds.length === incidents.length) {
      setSelectedIds([]);
    } else {
      setSelectedIds(incidents.map((i) => i.id));
    }
  };

  const toggleSelectOne = (id: string) => {
    setSelectedIds((prev) =>
      prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]
    );
  };

  const downloadPdf = async (id: string, ref: string) => {
    try {
      const blob = await adminApi.exportSinglePdf(id);
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `${ref}.pdf`;
      a.click();
    } catch {
      alert('Failed to download PDF');
    }
  };

  const getPriorityBadge = (prio: string) => {
    switch (prio) {
      case 'Critical':
        return (
          <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-rose-100 text-rose-800 border border-rose-300">
            <span className="w-1.5 h-1.5 rounded-full bg-rose-600 animate-pulse"></span>
            Critical
          </span>
        );
      case 'High':
        return (
          <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-100 text-amber-800 border border-amber-300">
            <span className="w-1.5 h-1.5 rounded-full bg-amber-600"></span>
            High
          </span>
        );
      case 'Low':
        return (
          <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-slate-100 text-slate-700 border border-slate-200">
            Low
          </span>
        );
      case 'Medium':
      default:
        return (
          <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800 border border-blue-200">
            Medium
          </span>
        );
    }
  };

  // Reset pagination on filter or view change
  useEffect(() => {
    setCurrentPage(1);
  }, [siteId, status, priorityFilter, search, dateFrom, dateTo, viewTab, groupBy]);

  // Grouping & pagination helper
  const totalPages = Math.max(1, Math.ceil(incidents.length / pageSize));
  const paginatedIncidents =
    groupBy === 'none'
      ? incidents.slice((currentPage - 1) * pageSize, currentPage * pageSize)
      : incidents;

  const groupedIncidents: Record<string, any[]> = {};
  if (groupBy === 'site') {
    incidents.forEach((inc) => {
      const key = inc.site_name || 'Unknown Site';
      if (!groupedIncidents[key]) groupedIncidents[key] = [];
      groupedIncidents[key].push(inc);
    });
  } else if (groupBy === 'day') {
    incidents.forEach((inc) => {
      const key = inc.incident_date || 'Unknown Date';
      if (!groupedIncidents[key]) groupedIncidents[key] = [];
      groupedIncidents[key].push(inc);
    });
  } else {
    groupedIncidents['All Incidents'] = paginatedIncidents;
  }

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <div className="flex flex-wrap items-center gap-3">
            <h1 className="text-xl md:text-2xl font-black text-slate-900 tracking-tight">
              Incident Reports Explorer
            </h1>
            <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-white border border-slate-200 shadow-2xs">
              <span
                className={`w-2 h-2 rounded-full ${
                  connectionStatus === 'Live'
                    ? 'bg-emerald-500 animate-pulse'
                    : connectionStatus === 'Reconnecting'
                    ? 'bg-amber-500 animate-ping'
                    : 'bg-blue-500'
                }`}
              />
              <span
                className={
                  connectionStatus === 'Live'
                    ? 'text-emerald-700 font-extrabold'
                    : connectionStatus === 'Reconnecting'
                    ? 'text-amber-700 font-bold'
                    : 'text-slate-600'
                }
              >
                {connectionStatus}
              </span>
              <span className="text-[10px] text-slate-400 font-mono pl-1 border-l border-slate-200">
                {lastUpdated.toLocaleTimeString()}
              </span>
            </div>
          </div>
          <p className="text-xs text-slate-500 mt-0.5">
            Manage, review, sign-off, restore, or export all site security incident logs
          </p>
        </div>

        <div className="flex items-center gap-2.5">
          <button
            onClick={() => setShowCreateModal(true)}
            className="flex items-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-sm transition-all"
          >
            <Plus className="w-4 h-4" />
            <span>Create Report Manually</span>
          </button>
        </div>
      </div>

      {/* Realtime Incident Alert Toast */}
      {activeToast && (
        <div className="bg-gradient-to-r from-blue-700 to-indigo-800 text-white p-4 rounded-2xl shadow-xl flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center shrink-0">
              <Bell className="w-5 h-5 animate-bounce" />
            </div>
            <div>
              <div className="text-xs font-bold text-blue-200 uppercase tracking-wider">
                ⚡ Real-Time Report Delivered (&lt; 2s)
              </div>
              <div className="text-sm font-black">
                {activeToast.refNumber} — {activeToast.siteName}
              </div>
            </div>
          </div>
          <div className="flex items-center gap-2">
            <button
              onClick={() => {
                const target = incidents.find((i) => i.id === activeToast.id);
                if (target) setSelectedIncident(target);
                dismissToast();
              }}
              className="px-3.5 py-1.5 bg-white text-blue-900 rounded-xl text-xs font-black hover:bg-blue-50 transition-colors shadow-xs"
            >
              Open Report
            </button>
            <button
              onClick={dismissToast}
              className="p-1 hover:bg-white/20 rounded-lg transition-colors text-white/80 hover:text-white"
            >
              <X className="w-4 h-4" />
            </button>
          </div>
        </div>
      )}

      {/* Primary Tab Navigation: Active Reports vs Deleted Reports Panel */}
      <div className="flex items-center gap-3 border-b border-slate-200 pb-3">
        <button
          onClick={() => handleTabChange('active')}
          className={`flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition-all ${
            viewTab === 'active'
              ? 'bg-blue-600 text-white shadow-sm'
              : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200'
          }`}
        >
          <FileText className="w-4 h-4" />
          <span>Active Incident Reports</span>
          <span
            className={`px-2 py-0.5 rounded-full text-[10px] font-extrabold ${
              viewTab === 'active' ? 'bg-blue-800 text-white' : 'bg-slate-100 text-slate-700'
            }`}
          >
            {activeCount}
          </span>
        </button>

        <button
          onClick={() => handleTabChange('trash')}
          className={`flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition-all ${
            viewTab === 'trash'
              ? 'bg-rose-600 text-white shadow-sm'
              : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200'
          }`}
        >
          <Trash2 className="w-4 h-4" />
          <span>Deleted Incident Reports (Recycle Bin)</span>
          <span
            className={`px-2 py-0.5 rounded-full text-[10px] font-extrabold ${
              viewTab === 'trash' ? 'bg-rose-800 text-white' : 'bg-rose-50 text-rose-700'
            }`}
          >
            {trashCount}
          </span>
        </button>
      </div>

      {/* Dedicated Recycle Bin Alert Panel when viewing Trash */}
      {viewTab === 'trash' && (
        <div className="bg-rose-50 border border-rose-200 rounded-3xl p-5 space-y-3">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div className="flex items-start gap-3">
              <div className="w-10 h-10 rounded-2xl bg-rose-100 text-rose-600 flex items-center justify-center shrink-0">
                <Trash2 className="w-5 h-5" />
              </div>
              <div>
                <h3 className="font-extrabold text-sm text-rose-950">
                  Deleted Incident Reports Recycle Bin Panel
                </h3>
                <p className="text-xs text-rose-700 mt-0.5">
                  Reports deleted by administrators are safely retained here. You can 1-click restore them back to active site monitoring or delete them permanently.
                </p>
              </div>
            </div>

            {selectedIds.length > 0 && (
              <div className="flex items-center gap-2 shrink-0">
                <button
                  onClick={handleBulkRestore}
                  className="flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-xs transition-all"
                >
                  <RotateCcw className="w-3.5 h-3.5" />
                  <span>Restore Selected ({selectedIds.length})</span>
                </button>
                <button
                  onClick={handleBulkPermanentDelete}
                  className="flex items-center gap-1.5 px-3 py-1.5 bg-rose-700 hover:bg-rose-800 text-white rounded-xl text-xs font-bold shadow-xs transition-all"
                >
                  <Trash2 className="w-3.5 h-3.5" />
                  <span>Delete Permanently ({selectedIds.length})</span>
                </button>
              </div>
            )}
          </div>
        </div>
      )}

      {/* Filter and Control Bar */}
      <div className="bg-white p-6 md:p-7 rounded-3xl border border-slate-200/90 shadow-xs space-y-5 overflow-hidden">
        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-4">
          {/* Site Filter */}
          <div className="min-w-0">
            <label className="text-xs font-extrabold text-slate-500 uppercase tracking-wider block mb-1.5">
              Filter by Site
            </label>
            <select
              value={siteId}
              onChange={(e) => setSiteId(e.target.value)}
              className="w-full text-sm py-2.5 px-3 bg-slate-50 hover:bg-white focus:bg-white border border-slate-300 rounded-xl font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all truncate"
            >
              <option value="">All Sites Combined</option>
              {sites.map((s) => (
                <option key={s.id} value={s.id}>
                  {s.name} ({s.code})
                </option>
              ))}
            </select>
          </div>

          {/* Priority Filter */}
          <div className="min-w-0">
            <label className="text-xs font-extrabold text-slate-500 uppercase tracking-wider block mb-1.5">
              Priority Level
            </label>
            <select
              value={priorityFilter}
              onChange={(e) => setPriorityFilter(e.target.value)}
              className="w-full text-sm py-2.5 px-3 bg-slate-50 hover:bg-white focus:bg-white border border-slate-300 rounded-xl font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all truncate"
            >
              <option value="">All Priorities</option>
              {INCIDENT_PRIORITIES.map((p) => (
                <option key={p} value={p}>
                  {p} Priority
                </option>
              ))}
            </select>
          </div>

          {/* Status Filter */}
          <div className="min-w-0">
            <label className="text-xs font-extrabold text-slate-500 uppercase tracking-wider block mb-1.5">
              Status Workflow
            </label>
            <select
              value={status}
              onChange={(e) => setStatus(e.target.value)}
              className="w-full text-sm py-2.5 px-3 bg-slate-50 hover:bg-white focus:bg-white border border-slate-300 rounded-xl font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all truncate"
            >
              <option value="">All Statuses</option>
              {INCIDENT_STATUSES.map((st) => (
                <option key={st} value={st}>
                  {st}
                </option>
              ))}
            </select>
          </div>

          {/* Date From */}
          <div className="min-w-0">
            <label className="text-xs font-extrabold text-slate-500 uppercase tracking-wider block mb-1.5">
              Date From
            </label>
            <input
              type="date"
              value={dateFrom}
              onChange={(e) => setDateFrom(e.target.value)}
              className="w-full text-sm py-2 px-3 bg-slate-50 hover:bg-white focus:bg-white border border-slate-300 rounded-xl font-medium text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all"
            />
          </div>

          {/* Date To */}
          <div className="min-w-0">
            <label className="text-xs font-extrabold text-slate-500 uppercase tracking-wider block mb-1.5">
              Date To
            </label>
            <input
              type="date"
              value={dateTo}
              onChange={(e) => setDateTo(e.target.value)}
              className="w-full text-sm py-2 px-3 bg-slate-50 hover:bg-white focus:bg-white border border-slate-300 rounded-xl font-medium text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all"
            />
          </div>

          {/* Text Search */}
          <div className="min-w-0">
            <label className="text-xs font-extrabold text-slate-500 uppercase tracking-wider block mb-1.5">
              Search Keywords
            </label>
            <form onSubmit={handleSearchSubmit} className="relative">
              <input
                type="text"
                placeholder="Ref, narrative, reporter..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                className="w-full text-sm pl-9 pr-3 py-2 bg-slate-50 hover:bg-white focus:bg-white border border-slate-300 rounded-xl font-medium text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all placeholder:text-slate-400"
              />
              <Search className="w-4 h-4 text-slate-400 absolute left-3 top-3" />
            </form>
          </div>
        </div>

        {/* Grouping and Total Found */}
        <div className="flex flex-wrap items-center justify-between gap-4 pt-3 border-t border-slate-100 text-sm">
          <div className="flex items-center gap-3 flex-wrap">
            <span className="font-bold text-slate-600 text-xs sm:text-sm">Group By:</span>
            <div className="inline-flex rounded-xl p-1 bg-slate-100 border border-slate-200">
              <button
                onClick={() => setGroupBy('none')}
                className={`px-3.5 py-1.5 rounded-lg font-bold text-xs sm:text-sm transition-all ${
                  groupBy === 'none' ? 'bg-white shadow-xs text-blue-900' : 'text-slate-600 hover:text-slate-900'
                }`}
              >
                None
              </button>
              <button
                onClick={() => setGroupBy('site')}
                className={`px-3.5 py-1.5 rounded-lg font-bold text-xs sm:text-sm transition-all ${
                  groupBy === 'site' ? 'bg-white shadow-xs text-blue-900' : 'text-slate-600 hover:text-slate-900'
                }`}
              >
                By Site
              </button>
              <button
                onClick={() => setGroupBy('day')}
                className={`px-3.5 py-1.5 rounded-lg font-bold text-xs sm:text-sm transition-all ${
                  groupBy === 'day' ? 'bg-white shadow-xs text-blue-900' : 'text-slate-600 hover:text-slate-900'
                }`}
              >
                By Day
              </button>
            </div>
          </div>

          <div className="text-slate-600 font-mono text-xs sm:text-sm font-bold">
            Found {total} {viewTab === 'trash' ? 'deleted' : 'active'} report{total === 1 ? '' : 's'}
          </div>
        </div>
      </div>

      {/* Incidents Table / Grouped Sections */}
      {loading ? (
        <div className="flex items-center justify-center py-20">
          <RefreshCw className="w-6 h-6 animate-spin text-blue-600" />
        </div>
      ) : incidents.length === 0 ? (
        <div className="bg-white rounded-3xl border border-slate-200 p-12 text-center text-slate-400 space-y-2">
          <FileText className="w-12 h-12 mx-auto opacity-40" />
          <p className="font-bold text-slate-700 text-sm">
            {viewTab === 'trash'
              ? 'Recycle Bin is currently empty'
              : 'No incident reports found matching your criteria'}
          </p>
          <p className="text-xs text-slate-500">
            {viewTab === 'trash'
              ? 'Any incident reports deleted from the active list will appear here.'
              : 'Incident reports will appear when security staff submit reports or when created manually.'}
          </p>
        </div>
      ) : (
        Object.entries(groupedIncidents).map(([groupTitle, groupItems]) => (
          <div
            key={groupTitle}
            className="bg-white rounded-3xl border border-slate-200 shadow-xs overflow-hidden"
          >
            {groupBy !== 'none' && (
              <div className="bg-slate-50 px-5 py-3 border-b border-slate-200 flex items-center justify-between">
                <span className="font-extrabold text-sm text-[#0F2042]">
                  {groupTitle}
                </span>
                <span className="text-xs font-mono font-bold text-slate-500">
                  {groupItems.length} incident(s)
                </span>
              </div>
            )}

            <div className="overflow-x-auto dashboard-scrollbar pb-1">
              <table className="w-full text-left text-xs">
                <thead className="bg-slate-50 text-slate-600 font-bold uppercase tracking-wider text-[10px] border-b border-slate-200">
                  <tr>
                    <th className="px-3 py-3 w-8">
                      <button onClick={toggleSelectAll}>
                        {selectedIds.length === incidents.length && incidents.length > 0 ? (
                          <CheckSquare className="w-4 h-4 text-blue-600" />
                        ) : (
                          <Square className="w-4 h-4 text-slate-400" />
                        )}
                      </button>
                    </th>
                    <th className="px-3.5 py-3">Reference No</th>
                    <th className="px-3.5 py-3">Priority</th>
                    {groupBy !== 'site' && <th className="px-3.5 py-3">Site</th>}
                    {groupBy !== 'day' && <th className="px-3.5 py-3">Date</th>}
                    <th className="px-3.5 py-3">Time/Shift</th>
                    <th className="px-3.5 py-3">Cause & Incident</th>
                    <th className="px-3.5 py-3">Summary</th>
                    <th className="px-3.5 py-3">Reporter</th>
                    <th className="px-3.5 py-3">Status</th>
                    <th className="px-3.5 py-3 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 font-medium text-slate-700">
                  {groupItems.map((inc) => {
                    const isChecked = selectedIds.includes(inc.id);
                    const isSubmittedToClient =
                      inc.status === 'Submitted to Client' ||
                      (typeof inc.status === 'string' &&
                        (inc.status.trim().toLowerCase() === 'submitted to client' ||
                         inc.status.trim().toLowerCase().includes('client')));

                    return (
                      <tr
                        key={inc.id}
                        className={`hover:bg-slate-50/80 transition-colors ${
                          inc.deleted_at
                            ? 'bg-rose-50/30'
                            : inc._isNew
                            ? 'bg-blue-50/80 border-l-4 border-blue-600'
                            : isSubmittedToClient
                            ? 'bg-emerald-50/30 border-l-4 border-emerald-800'
                            : ''
                        }`}
                      >
                        <td className="px-3 py-3">
                          <button onClick={() => toggleSelectOne(inc.id)}>
                            {isChecked ? (
                              <CheckSquare className="w-4 h-4 text-blue-600" />
                            ) : (
                              <Square className="w-4 h-4 text-slate-300" />
                            )}
                          </button>
                        </td>

                        <td className="px-3.5 py-3 font-mono font-bold whitespace-nowrap">
                          <button
                            type="button"
                            onClick={() => setSelectedIncident(inc)}
                            className="group inline-flex items-center gap-1.5 text-blue-600 hover:text-blue-800 hover:underline transition-colors text-left"
                            title="Open incident review & details"
                          >
                            <span className="font-semibold">{inc.ref_number}</span>
                            <ExternalLink className="w-3 h-3 opacity-60 group-hover:opacity-100 transition-opacity shrink-0" />
                            {inc._isNew && (
                              <span className="px-1.5 py-0.5 rounded text-[9px] font-black bg-blue-600 text-white animate-pulse">
                                LIVE
                              </span>
                            )}
                          </button>
                        </td>

                        <td className="px-3.5 py-3 whitespace-nowrap">
                          {getPriorityBadge(inc.priority || 'Medium')}
                        </td>

                        {groupBy !== 'site' && (
                          <td className="px-3.5 py-3 font-bold text-slate-900 truncate max-w-[150px]" title={inc.site_name}>
                            {inc.site_name}
                          </td>
                        )}

                        {groupBy !== 'day' && (
                          <td className="px-3.5 py-3 whitespace-nowrap text-slate-600 font-mono text-[11px]">
                            {formatDateDDMMYYYY(inc.incident_date)}
                          </td>
                        )}

                        <td className="px-3.5 py-3 whitespace-nowrap text-slate-600 font-mono text-[11px]">
                          {inc.incident_time || '00:00'} ({inc.shift})
                        </td>

                        <td className="px-3.5 py-3 truncate max-w-[150px]">
                          <span className="font-semibold text-slate-900 block truncate" title={(inc.incident_types || []).join(', ')}>
                            {(inc.incident_types || []).join(', ')}
                          </span>
                          <span className="text-[11px] text-slate-400 block truncate" title={(inc.cause_main || []).join(', ')}>
                            {(inc.cause_main || []).join(', ')}
                          </span>
                        </td>

                        <td className="px-3.5 py-3 truncate max-w-[180px] xl:max-w-[240px]" title={inc.summary_line || inc.description}>
                          {inc.summary_line || inc.description}
                        </td>

                        <td className="px-3.5 py-3 truncate max-w-[110px]" title={inc.reported_by_name}>
                          {inc.reported_by_name}
                        </td>

                        <td className="px-3.5 py-3 whitespace-nowrap">
                          <span
                            className={`inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold ${
                              inc.status === 'Resolved' || inc.status === 'Submitted to Client'
                                ? 'bg-emerald-100 text-emerald-800'
                                : inc.status === 'Follow-up Required'
                                ? 'bg-amber-100 text-amber-800'
                                : 'bg-blue-100 text-blue-800'
                            }`}
                          >
                            {inc.status}
                          </span>
                        </td>

                        <td className="px-3.5 py-3 text-right whitespace-nowrap space-x-1.5">
                          <button
                            onClick={() => setSelectedIncident(inc)}
                            title="View / Edit / Sign Off"
                            className="px-2 py-1 text-blue-700 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors inline-flex items-center gap-1 font-bold text-xs"
                          >
                            <Eye className="w-3.5 h-3.5" />
                            <span>Review</span>
                          </button>

                          <button
                            onClick={() => downloadPdf(inc.id, inc.ref_number)}
                            title="Export PDF"
                            className="p-1.5 text-slate-600 hover:bg-slate-100 rounded-lg transition-colors inline-flex items-center"
                          >
                            <Download className="w-3.5 h-3.5" />
                          </button>

                          {inc.deleted_at ? (
                            <>
                              <button
                                onClick={() => handleRestore(inc.id, inc.ref_number)}
                                title="Restore Report"
                                className="px-2.5 py-1 text-emerald-700 bg-emerald-50 hover:bg-emerald-100 rounded-lg transition-colors font-bold text-xs inline-flex items-center gap-1 border border-emerald-200"
                              >
                                <RotateCcw className="w-3.5 h-3.5" />
                                <span>Restore</span>
                              </button>
                              <button
                                onClick={() => handlePermanentDelete(inc.id, inc.ref_number)}
                                title="Delete Permanently"
                                className="px-2.5 py-1 text-rose-700 bg-rose-50 hover:bg-rose-100 rounded-lg transition-colors font-bold text-xs inline-flex items-center gap-1 border border-rose-200"
                              >
                                <Trash2 className="w-3.5 h-3.5" />
                                <span>Delete Permanently</span>
                              </button>
                            </>
                          ) : (
                            <button
                              onClick={() => handleSoftDelete(inc.id, inc.ref_number)}
                              title="Move to Trash"
                              className="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors"
                            >
                              <Trash2 className="w-3.5 h-3.5" />
                            </button>
                          )}
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>

            {/* Pagination Controls */}
            {totalPages > 1 && (
              <div className="bg-slate-50/90 px-5 py-3 border-t border-slate-200 flex flex-wrap items-center justify-between gap-3 text-xs text-slate-600">
                <div>
                  Showing{' '}
                  <span className="font-bold text-slate-900">
                    {(currentPage - 1) * pageSize + 1}
                  </span>{' '}
                  to{' '}
                  <span className="font-bold text-slate-900">
                    {Math.min(currentPage * pageSize, incidents.length)}
                  </span>{' '}
                  of <span className="font-bold text-slate-900">{incidents.length}</span> incident reports
                </div>

                <div className="flex items-center gap-1">
                  <button
                    type="button"
                    disabled={currentPage === 1}
                    onClick={() => setCurrentPage((p) => Math.max(1, p - 1))}
                    className="p-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-100 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
                    title="Previous Page"
                  >
                    <ChevronLeft className="w-4 h-4 text-slate-700" />
                  </button>

                  {Array.from({ length: totalPages }, (_, i) => i + 1).map((pg) => (
                    <button
                      key={pg}
                      type="button"
                      onClick={() => setCurrentPage(pg)}
                      className={`min-w-[32px] h-8 px-2 rounded-lg font-bold text-xs transition-colors ${
                        currentPage === pg
                          ? 'bg-blue-600 text-white shadow-xs'
                          : 'border border-slate-200 bg-white hover:bg-slate-100 text-slate-700'
                      }`}
                    >
                      {pg}
                    </button>
                  ))}

                  <button
                    type="button"
                    disabled={currentPage === totalPages}
                    onClick={() => setCurrentPage((p) => Math.min(totalPages, p + 1))}
                    className="p-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-100 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
                    title="Next Page"
                  >
                    <ChevronRight className="w-4 h-4 text-slate-700" />
                  </button>
                </div>
              </div>
            )}
          </div>
        ))
      )}

      {/* Selected Incident Review & Sign-Off Modal */}
      {selectedIncident && (
        <IncidentDetailModal
          incident={selectedIncident}
          onClose={() => setSelectedIncident(null)}
          onUpdated={() => {
            setSelectedIncident(null);
            loadData();
          }}
        />
      )}

      {/* Manual Create Incident Modal */}
      {showCreateModal && (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl p-6 shadow-2xl max-w-lg w-full space-y-4">
            <h2 className="font-extrabold text-base text-slate-900">
              Create Incident Report Manually
            </h2>

            <form onSubmit={handleCreateManual} className="space-y-3 text-xs">
              <div className="grid grid-cols-2 gap-2">
                <div>
                  <label className="block font-bold text-slate-700 mb-1">Target Site</label>
                  <select
                    required
                    value={newSiteId}
                    onChange={(e) => setNewSiteId(e.target.value)}
                    className="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl font-bold"
                  >
                    <option value="">Select Site...</option>
                    {sites.map((s) => (
                      <option key={s.id} value={s.id}>
                        {s.name} ({s.code})
                      </option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="block font-bold text-slate-700 mb-1">Triage Priority</label>
                  <select
                    value={newPriority}
                    onChange={(e) => setNewPriority(e.target.value)}
                    className="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl font-bold text-slate-900"
                  >
                    {INCIDENT_PRIORITIES.map((p) => (
                      <option key={p} value={p}>
                        {p} Priority
                      </option>
                    ))}
                  </select>
                </div>
              </div>

              <div className="grid grid-cols-3 gap-2">
                <div>
                  <label className="block font-bold text-slate-700 mb-1">Date</label>
                  <input
                    type="date"
                    required
                    value={newDate}
                    onChange={(e) => setNewDate(e.target.value)}
                    className="w-full p-2 bg-slate-50 border border-slate-300 rounded-xl font-medium"
                  />
                </div>
                <div>
                  <label className="block font-bold text-slate-700 mb-1">Time</label>
                  <input
                    type="time"
                    required
                    value={newTime}
                    onChange={(e) => setNewTime(e.target.value)}
                    className="w-full p-2 bg-slate-50 border border-slate-300 rounded-xl font-medium"
                  />
                </div>
                <div>
                  <label className="block font-bold text-slate-700 mb-1">Shift</label>
                  <select
                    value={newShift}
                    onChange={(e) => setNewShift(e.target.value)}
                    className="w-full p-2 bg-slate-50 border border-slate-300 rounded-xl font-bold"
                  >
                    <option value="Day">Day</option>
                    <option value="Night">Night</option>
                  </select>
                </div>
              </div>

              <div>
                <label className="block font-bold text-slate-700 mb-1">Summary Line</label>
                <input
                  type="text"
                  required
                  placeholder="What, Where, When, Who, Why, How..."
                  value={newSummary}
                  onChange={(e) => setNewSummary(e.target.value)}
                  className="w-full p-2.5 border border-slate-300 rounded-xl font-medium"
                />
              </div>

              <div>
                <label className="block font-bold text-slate-700 mb-1">Detailed Description</label>
                <textarea
                  required
                  rows={4}
                  placeholder="Full narrative of the incident..."
                  value={newDescription}
                  onChange={(e) => setNewDescription(e.target.value)}
                  className="w-full p-2.5 border border-slate-300 rounded-xl"
                ></textarea>
              </div>

              <div className="flex justify-end gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setShowCreateModal(false)}
                  className="px-4 py-2 border rounded-xl font-semibold"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-5 py-2 bg-blue-600 text-white rounded-xl font-bold hover:bg-blue-700"
                >
                  Create Report
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};
