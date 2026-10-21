import React, { useEffect, useState } from 'react';
import {
  Users,
  Plus,
  KeyRound,
  Shield,
  MapPin,
  Check,
  X,
  Copy,
  AlertCircle,
  Eye,
  RefreshCw,
  Lock,
  UserCheck,
  UserX,
  Trash2,
  Pencil,
} from 'lucide-react';
import { adminApi } from '../../lib/api';

export const StaffPage: React.FC = () => {
  const [staffList, setStaffList] = useState<any[]>([]);
  const [sites, setSites] = useState<any[]>([]);
  const [loading, setLoading] = useState<boolean>(true);

  // Create Guard Modal
  const [showCreateModal, setShowCreateModal] = useState<boolean>(false);
  const [staffId, setStaffId] = useState<string>('');
  const [fullName, setFullName] = useState<string>('');
  const [designation, setDesignation] = useState<string>('Security Officer');
  const [username, setUsername] = useState<string>('');
  const [password, setPassword] = useState<string>('');
  const [assignedSites, setAssignedSites] = useState<string[]>([]);
  const [creating, setCreating] = useState<boolean>(false);

  // Edit Guard Modal
  const [editGuardModal, setEditGuardModal] = useState<any>(null);
  const [editStaffId, setEditStaffId] = useState<string>('');
  const [editFullName, setEditFullName] = useState<string>('');
  const [editDesignation, setEditDesignation] = useState<string>('Security Officer');
  const [editUsername, setEditUsername] = useState<string>('');
  const [editAssignedSites, setEditAssignedSites] = useState<string[]>([]);
  const [editIsActive, setEditIsActive] = useState<boolean>(true);
  const [updatingStaff, setUpdatingStaff] = useState<boolean>(false);

  // One-time password display modal
  const [createdCredential, setCreatedCredential] = useState<any>(null);

  // Reset Password Modal
  const [resetModalGuard, setResetModalGuard] = useState<any>(null);
  const [newPassword, setNewPassword] = useState<string>('');
  const [resetting, setResetting] = useState<boolean>(false);

  // Delete Guard Modal
  const [deleteGuardTarget, setDeleteGuardTarget] = useState<any>(null);
  const [deleting, setDeleting] = useState<boolean>(false);

  const loadData = async () => {
    setLoading(true);
    try {
      const [staffData, sitesData] = await Promise.all([
        adminApi.listStaff(),
        adminApi.listSites(),
      ]);
      setStaffList(staffData);
      setSites(sitesData);
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadData();
  }, []);

  const generateStrongPassword = () => {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%';
    let res = '';
    for (let i = 0; i < 12; i++) {
      res += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return res;
  };

  const handleCreateGuard = async (e: React.FormEvent) => {
    e.preventDefault();
    setCreating(true);
    try {
      await adminApi.createStaff({
        staffId: staffId.trim(),
        fullName: fullName.trim(),
        designation: designation.trim(),
        username: username.toLowerCase().trim(),
        password,
        assignedSites: assignedSites || [],
      });

      // Show one-time password display
      setCreatedCredential({
        fullName,
        username,
        password,
        staffId,
      });

      setShowCreateModal(false);
      setStaffId('');
      setFullName('');
      setUsername('');
      setPassword('');
      setAssignedSites([]);
      loadData();
    } catch (err: any) {
      alert(err.message || 'Failed to create guard account');
    } finally {
      setCreating(false);
    }
  };

  const handleDeleteGuard = async () => {
    if (!deleteGuardTarget) return;
    setDeleting(true);
    try {
      await adminApi.deleteStaff(deleteGuardTarget.id);
      setDeleteGuardTarget(null);
      await loadData();
    } catch (err: any) {
      alert(err.message || 'Failed to delete staff member');
    } finally {
      setDeleting(false);
    }
  };

  const handleToggleActive = async (guard: any) => {
    try {
      await adminApi.updateStaff(guard.id, {
        isActive: !guard.isActive,
      });
      loadData();
    } catch {
      alert('Failed to update status');
    }
  };

  const handleResetPassword = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!resetModalGuard || !newPassword) return;

    setResetting(true);
    try {
      await adminApi.resetStaffPassword(resetModalGuard.id, newPassword);
      setCreatedCredential({
        fullName: resetModalGuard.fullName,
        username: resetModalGuard.username,
        password: newPassword,
        staffId: resetModalGuard.staffId,
      });
      setResetModalGuard(null);
      setNewPassword('');
    } catch (err: any) {
      alert(err.message || 'Failed to reset password');
    } finally {
      setResetting(false);
    }
  };

  const toggleSiteAssignment = (siteId: string) => {
    setAssignedSites((prev) =>
      prev.includes(siteId) ? prev.filter((id) => id !== siteId) : [...prev, siteId]
    );
  };

  const handleOpenEditGuard = (guard: any) => {
    setEditGuardModal(guard);
    setEditStaffId(guard.staffId || guard.staff_id || '');
    setEditFullName(guard.fullName || guard.full_name || '');
    setEditDesignation(guard.designation || 'Security Officer');
    setEditUsername(guard.username || '');
    setEditAssignedSites(guard.assignedSites || guard.assigned_sites || []);
    setEditIsActive(guard.isActive ?? guard.is_active ?? true);
  };

  const toggleEditSiteAssignment = (siteId: string) => {
    setEditAssignedSites((prev) =>
      prev.includes(siteId) ? prev.filter((id) => id !== siteId) : [...prev, siteId]
    );
  };

  const handleUpdateStaff = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!editGuardModal) return;
    setUpdatingStaff(true);
    try {
      await adminApi.updateStaff(editGuardModal.id, {
        staffId: editStaffId.trim(),
        fullName: editFullName.trim(),
        designation: editDesignation.trim(),
        username: editUsername.toLowerCase().trim(),
        assignedSites: editAssignedSites,
        isActive: editIsActive,
      });
      setEditGuardModal(null);
      await loadData();
    } catch (err: any) {
      alert(err.message || 'Failed to update staff member');
    } finally {
      setUpdatingStaff(false);
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-xl md:text-2xl font-extrabold text-slate-900 tracking-tight">
            Security Guard Staff Credentials
          </h1>
          <p className="text-xs text-slate-500 mt-0.5">
            Manage guard submission accounts, passwords, and assigned patrol checkpoints
          </p>
        </div>

        <button
          onClick={() => {
            setPassword(generateStrongPassword());
            setShowCreateModal(true);
          }}
          className="flex items-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-sm transition-all"
        >
          <Plus className="w-4 h-4" />
          <span>Create Guard Account</span>
        </button>
      </div>

      {/* Staff Table */}
      <div className="bg-white rounded-3xl border border-slate-200 shadow-xs overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead className="bg-slate-50 text-slate-600 font-bold uppercase tracking-wider text-[10px] border-b border-slate-200">
              <tr>
                <th className="px-5 py-3">Staff ID</th>
                <th className="px-5 py-3">Full Name</th>
                <th className="px-5 py-3">Designation</th>
                <th className="px-5 py-3">Username</th>
                <th className="px-5 py-3">Assigned Site(s)</th>
                <th className="px-5 py-3">Status</th>
                <th className="px-5 py-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 font-medium text-slate-700">
              {staffList.map((guard) => {
                const assignedNames = sites
                  .filter((s) => (guard.assignedSites || []).includes(s.id))
                  .map((s) => s.name);

                return (
                  <tr key={guard.id} className="hover:bg-slate-50 transition-colors">
                    <td className="px-5 py-4 font-mono font-bold text-blue-900">
                      {guard.staffId}
                    </td>
                    <td className="px-5 py-4 font-bold text-slate-900">
                      {guard.fullName}
                    </td>
                    <td className="px-5 py-4 text-slate-600">
                      {guard.designation}
                    </td>
                    <td className="px-5 py-4 font-mono text-slate-700 bg-slate-50/50">
                      {guard.username}
                    </td>
                    <td className="px-5 py-4">
                      <div className="flex flex-wrap gap-1 max-w-xs">
                        {assignedNames.map((name, i) => (
                          <span
                            key={i}
                            className="inline-block px-2 py-0.5 bg-slate-100 text-slate-700 rounded-md text-[10px] font-medium"
                          >
                            {name}
                          </span>
                        ))}
                      </div>
                    </td>
                    <td className="px-5 py-4">
                      <span
                        className={`inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold ${
                          guard.isActive
                            ? 'bg-emerald-100 text-emerald-800'
                            : 'bg-red-100 text-red-800'
                        }`}
                      >
                        {guard.isActive ? 'Active' : 'Disabled'}
                      </span>
                    </td>
                    <td className="px-5 py-4 text-right space-x-2 whitespace-nowrap">
                      <button
                        onClick={() => handleOpenEditGuard(guard)}
                        className="px-2.5 py-1 text-xs font-bold text-blue-700 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors border border-blue-200 inline-flex items-center gap-1 shadow-2xs"
                        title="Edit Staff Member Details"
                      >
                        <Pencil className="w-3 h-3" />
                        <span>Edit</span>
                      </button>

                      <button
                        onClick={() => {
                          setResetModalGuard(guard);
                          setNewPassword(generateStrongPassword());
                        }}
                        className="px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50 rounded-lg transition-colors border border-slate-200"
                      >
                        Reset Password
                      </button>

                      <button
                        onClick={() => handleToggleActive(guard)}
                        className={`px-2.5 py-1 text-xs font-semibold rounded-lg transition-colors border ${
                          guard.isActive
                            ? 'text-amber-700 hover:bg-amber-50 border-amber-200'
                            : 'text-emerald-700 hover:bg-emerald-50 border-emerald-200'
                        }`}
                      >
                        {guard.isActive ? 'Disable' : 'Enable'}
                      </button>

                      <button
                        onClick={() => setDeleteGuardTarget(guard)}
                        className="px-2.5 py-1 text-xs font-bold text-rose-700 bg-rose-50 hover:bg-rose-100 rounded-lg transition-colors border border-rose-200 inline-flex items-center gap-1 shadow-2xs"
                        title="Delete Employee Account"
                      >
                        <Trash2 className="w-3 h-3" />
                        <span>Delete</span>
                      </button>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </div>

      {/* One-Time Password Display Modal */}
      {createdCredential && (
        <div className="fixed inset-0 z-50 bg-black/70 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl p-6 shadow-2xl max-w-md w-full space-y-4 border border-slate-200">
            <div className="text-center space-y-1">
              <div className="w-12 h-12 mx-auto rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center">
                <Check className="w-6 h-6 stroke-[3]" />
              </div>
              <h2 className="text-lg font-black text-slate-900">
                Guard Credentials Generated
              </h2>
              <p className="text-xs text-slate-500">
                Provide these credentials to the security guard. Passwords are encrypted and cannot be displayed again.
              </p>
            </div>

            <div className="bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-2 text-xs font-mono">
              <div className="flex justify-between py-1 border-b border-slate-200">
                <span className="text-slate-500">Staff Member:</span>
                <span className="font-bold text-slate-900">{createdCredential.fullName}</span>
              </div>
              <div className="flex justify-between py-1 border-b border-slate-200">
                <span className="text-slate-500">Staff ID:</span>
                <span className="font-bold text-slate-900">{createdCredential.staffId}</span>
              </div>
              <div className="flex justify-between py-1 border-b border-slate-200">
                <span className="text-slate-500">Username:</span>
                <span className="font-bold text-blue-900">{createdCredential.username}</span>
              </div>
              <div className="flex justify-between py-1 items-center">
                <span className="text-slate-500">One-Time Password:</span>
                <span className="font-bold text-red-600 bg-red-50 px-2 py-0.5 rounded border border-red-200 select-all">
                  {createdCredential.password}
                </span>
              </div>
            </div>

            <button
              onClick={() => {
                navigator.clipboard.writeText(
                  `USS Patrol Login:\nUsername: ${createdCredential.username}\nPassword: ${createdCredential.password}`
                );
                alert('Copied credentials to clipboard!');
              }}
              className="w-full py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-xl text-xs font-bold flex items-center justify-center gap-2"
            >
              <Copy className="w-4 h-4" />
              <span>Copy Login Credentials</span>
            </button>

            <button
              onClick={() => setCreatedCredential(null)}
              className="w-full py-3 bg-[#0F2042] hover:bg-[#162e5c] text-white rounded-xl text-xs font-bold"
            >
              Done & Close
            </button>
          </div>
        </div>
      )}

      {/* Create Guard Account Modal */}
      {showCreateModal && (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl p-6 shadow-2xl max-w-md w-full space-y-4">
            <div className="flex items-center justify-between pb-3 border-b border-slate-100">
              <h2 className="font-extrabold text-base text-slate-900">Create Security Staff Account</h2>
              <button onClick={() => setShowCreateModal(false)} className="text-slate-400 hover:text-slate-700">
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleCreateGuard} className="space-y-3 text-xs">
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block font-bold text-slate-700 mb-1">Staff ID</label>
                  <input
                    type="text"
                    required
                    placeholder="e.g. USS-8803"
                    value={staffId}
                    onChange={(e) => setStaffId(e.target.value.toUpperCase())}
                    className="w-full p-2.5 border border-slate-300 rounded-xl uppercase font-mono"
                  />
                </div>
                <div>
                  <label className="block font-bold text-slate-700 mb-1">Designation</label>
                  <input
                    type="text"
                    required
                    value={designation}
                    onChange={(e) => setDesignation(e.target.value)}
                    className="w-full p-2.5 border border-slate-300 rounded-xl font-medium"
                  />
                </div>
              </div>

              <div>
                <label className="block font-bold text-slate-700 mb-1">Full Name</label>
                <input
                  type="text"
                  required
                  placeholder="Officer's full legal name"
                  value={fullName}
                  onChange={(e) => setFullName(e.target.value)}
                  className="w-full p-2.5 border border-slate-300 rounded-xl font-medium"
                />
              </div>

              <div>
                <label className="block font-bold text-slate-700 mb-1">Login Username</label>
                <input
                  type="text"
                  required
                  placeholder="e.g. name.surname"
                  value={username}
                  onChange={(e) => setUsername(e.target.value)}
                  className="w-full p-2.5 border border-slate-300 rounded-xl font-mono"
                />
              </div>

              <div>
                <div className="flex justify-between items-center mb-1">
                  <label className="font-bold text-slate-700">Initial Password</label>
                  <button
                    type="button"
                    onClick={() => setPassword(generateStrongPassword())}
                    className="text-blue-600 hover:underline flex items-center gap-1 text-[11px]"
                  >
                    <RefreshCw className="w-3 h-3" />
                    <span>Generate Strong</span>
                  </button>
                </div>
                <input
                  type="text"
                  required
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  className="w-full p-2.5 border border-slate-300 rounded-xl font-mono text-xs font-bold text-blue-900 bg-slate-50"
                />
              </div>

              {/* Site Assignments (Optional) */}
              <div>
                <label className="block font-bold text-slate-700 mb-1">
                  Assign Authorized Sites <span className="text-slate-400 font-normal text-[11px]">(Optional)</span>
                </label>
                <p className="text-[11px] text-slate-500 mb-1.5">
                  Sites can also be assigned or modified later from the Staff management page.
                </p>
                <div className="max-h-36 overflow-y-auto border border-slate-300 rounded-xl p-2 space-y-1 bg-slate-50">
                  {sites.map((s) => {
                    const isChecked = assignedSites.includes(s.id);
                    return (
                      <label
                        key={s.id}
                        className={`flex items-center gap-2 p-1.5 rounded-lg cursor-pointer text-xs ${
                          isChecked ? 'bg-blue-100 text-blue-900 font-bold' : 'hover:bg-slate-100'
                        }`}
                      >
                        <input
                          type="checkbox"
                          checked={isChecked}
                          onChange={() => toggleSiteAssignment(s.id)}
                          className="rounded text-blue-600 focus:ring-0"
                        />
                        <span className="truncate">{s.name} ({s.code})</span>
                      </label>
                    );
                  })}
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
                  {creating ? 'Creating...' : 'Create Account'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Edit Guard Account Modal */}
      {editGuardModal && (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl p-6 shadow-2xl max-w-md w-full space-y-4">
            <div className="flex items-center justify-between pb-3 border-b border-slate-100">
              <div>
                <h2 className="font-extrabold text-base text-slate-900">Edit Staff Account</h2>
                <p className="text-[11px] text-slate-500 font-mono">@{editGuardModal.username}</p>
              </div>
              <button onClick={() => setEditGuardModal(null)} className="text-slate-400 hover:text-slate-700">
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleUpdateStaff} className="space-y-3 text-xs">
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block font-bold text-slate-700 mb-1">Staff ID</label>
                  <input
                    type="text"
                    required
                    placeholder="e.g. SG1001"
                    value={editStaffId}
                    onChange={(e) => setEditStaffId(e.target.value.toUpperCase())}
                    className="w-full p-2.5 border border-slate-300 rounded-xl font-mono uppercase font-bold"
                  />
                </div>
                <div>
                  <label className="block font-bold text-slate-700 mb-1">Designation</label>
                  <select
                    value={editDesignation}
                    onChange={(e) => setEditDesignation(e.target.value)}
                    className="w-full p-2.5 border border-slate-300 rounded-xl font-semibold"
                  >
                    <option value="Security Guard">Security Guard</option>
                    <option value="Security Officer">Security Officer</option>
                    <option value="Security Supervisor">Security Supervisor</option>
                    <option value="Patrol Officer">Patrol Officer</option>
                    <option value="Control Room Operator">Control Room Operator</option>
                  </select>
                </div>
              </div>

              <div>
                <label className="block font-bold text-slate-700 mb-1">Full Legal Name</label>
                <input
                  type="text"
                  required
                  placeholder="e.g. KARAN SHARMA"
                  value={editFullName}
                  onChange={(e) => setEditFullName(e.target.value)}
                  className="w-full p-2.5 border border-slate-300 rounded-xl uppercase font-medium"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block font-bold text-slate-700 mb-1">Login Username</label>
                  <input
                    type="text"
                    required
                    placeholder="e.g. karan"
                    value={editUsername}
                    onChange={(e) => setEditUsername(e.target.value.toLowerCase())}
                    className="w-full p-2.5 border border-slate-300 rounded-xl font-mono font-medium"
                  />
                </div>
                <div>
                  <label className="block font-bold text-slate-700 mb-1">Account Status</label>
                  <select
                    value={editIsActive ? 'active' : 'disabled'}
                    onChange={(e) => setEditIsActive(e.target.value === 'active')}
                    className="w-full p-2.5 border border-slate-300 rounded-xl font-semibold"
                  >
                    <option value="active">Active</option>
                    <option value="disabled">Disabled</option>
                  </select>
                </div>
              </div>

              {/* Site Assignments */}
              <div>
                <label className="block font-bold text-slate-700 mb-1">
                  Assign Authorized Sites <span className="text-slate-400 font-normal text-[11px]">(Optional)</span>
                </label>
                <p className="text-[11px] text-slate-500 mb-1.5">
                  Select sites where this staff member is authorized to submit incident reports.
                </p>
                <div className="max-h-36 overflow-y-auto border border-slate-200 rounded-xl p-2.5 space-y-1 bg-slate-50/50">
                  {sites.length === 0 ? (
                    <p className="text-slate-400 italic text-[11px]">No sites available</p>
                  ) : (
                    sites.map((site) => {
                      const isAssigned = editAssignedSites.includes(site.id);
                      return (
                        <label
                          key={site.id}
                          className={`flex items-center gap-2 p-1.5 rounded-lg cursor-pointer transition-colors text-xs ${
                            isAssigned ? 'bg-blue-50 text-blue-900 font-semibold' : 'hover:bg-slate-100 text-slate-700'
                          }`}
                        >
                          <input
                            type="checkbox"
                            checked={isAssigned}
                            onChange={() => toggleEditSiteAssignment(site.id)}
                            className="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                          />
                          <span className="font-mono text-[10px] bg-slate-200 text-slate-700 px-1.5 py-0.5 rounded">
                            {site.code}
                          </span>
                          <span className="truncate">{site.name}</span>
                        </label>
                      );
                    })
                  )}
                </div>
              </div>

              <div className="flex justify-end gap-2 pt-3 border-t border-slate-100">
                <button
                  type="button"
                  onClick={() => setEditGuardModal(null)}
                  className="px-4 py-2 border rounded-xl font-semibold text-slate-700 hover:bg-slate-50"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={updatingStaff}
                  className="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold shadow-sm"
                >
                  {updatingStaff ? 'Saving...' : 'Save Changes'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Reset Password Modal */}
      {resetModalGuard && (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl p-6 shadow-2xl max-w-sm w-full space-y-4">
            <h2 className="font-extrabold text-base text-slate-900">
              Reset Password for {resetModalGuard.fullName}
            </h2>

            <form onSubmit={handleResetPassword} className="space-y-3 text-xs">
              <div>
                <div className="flex justify-between items-center mb-1">
                  <label className="font-bold text-slate-700">New Password</label>
                  <button
                    type="button"
                    onClick={() => setNewPassword(generateStrongPassword())}
                    className="text-blue-600 hover:underline flex items-center gap-1 text-[11px]"
                  >
                    <RefreshCw className="w-3 h-3" />
                    <span>Generate</span>
                  </button>
                </div>
                <input
                  type="text"
                  required
                  value={newPassword}
                  onChange={(e) => setNewPassword(e.target.value)}
                  className="w-full p-2.5 border border-slate-300 rounded-xl font-mono text-xs font-bold"
                />
              </div>

              <div className="flex justify-end gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setResetModalGuard(null)}
                  className="px-4 py-2 border rounded-xl font-semibold"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={resetting}
                  className="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold"
                >
                  {resetting ? 'Resetting...' : 'Set Password'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Delete Employee Confirmation Modal */}
      {deleteGuardTarget && (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl p-6 shadow-2xl max-w-sm w-full space-y-4 border border-rose-100">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 rounded-2xl bg-rose-100 text-rose-600 flex items-center justify-center shrink-0">
                <Trash2 className="w-5 h-5" />
              </div>
              <div>
                <h3 className="font-extrabold text-base text-slate-900">
                  Delete Employee Account
                </h3>
                <p className="text-xs text-slate-500">
                  This action is permanent and cannot be undone.
                </p>
              </div>
            </div>

            <div className="p-3 bg-rose-50/70 border border-rose-100 rounded-2xl text-xs space-y-1">
              <p className="font-bold text-slate-800">
                {deleteGuardTarget.fullName}
              </p>
              <p className="font-mono text-slate-600">
                Staff ID: {deleteGuardTarget.staffId} | User: @{deleteGuardTarget.username}
              </p>
            </div>

            <div className="flex justify-end gap-2 pt-2">
              <button
                type="button"
                onClick={() => setDeleteGuardTarget(null)}
                className="px-4 py-2 border rounded-xl font-semibold text-xs text-slate-700 hover:bg-slate-50"
              >
                Cancel
              </button>
              <button
                type="button"
                onClick={handleDeleteGuard}
                disabled={deleting}
                className="px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl font-bold text-xs shadow-sm transition-all"
              >
                {deleting ? 'Deleting...' : 'Delete Permanently'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
